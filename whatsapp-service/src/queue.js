import { Queue, Worker } from 'bullmq';
import Redis from 'ioredis';
import axios from 'axios';
import fs from 'node:fs';
import path from 'node:path';
import { config } from './config.js';
import { logger } from './logger.js';
import { counters } from './telemetry.js';
import * as evolution from './evolution.js';

/**
 * Filas BullMQ (Redis) + workers.
 *  - whatsapp-outbound: send-text | send-media | send-document-pdf
 *  - printer:           print-ticket | print-a4
 * Cada envio com sucesso/falha gera um callback para o Laravel.
 */

// BullMQ exige maxRetriesPerRequest: null nas ligacoes usadas por Queue/Worker.
const REDIS_OPTIONS = { maxRetriesPerRequest: null, enableReadyCheck: false };

const redisClients = [];
// Evita inundar os logs quando o Redis esta em baixo (ioredis reconecta em loop).
const lastRedisErrorLog = new WeakMap();
const REDIS_ERROR_LOG_INTERVAL_MS = 30000;

function createRedisClient() {
  const client = new Redis(config.REDIS_URL, REDIS_OPTIONS);
  client.on('error', (error) => {
    const now = Date.now();
    const last = lastRedisErrorLog.get(client) ?? 0;
    if (now - last > REDIS_ERROR_LOG_INTERVAL_MS) {
      lastRedisErrorLog.set(client, now);
      logger.error({ err: error.message }, 'Erro de ligacao ao Redis');
    }
  });
  redisClients.push(client);
  return client;
}

/** Limita a espera por uma promessa (evita pedidos HTTP pendurados sem Redis). */
function withTimeout(promise, ms, message = 'timeout') {
  let timer;
  const timeout = new Promise((resolve, reject) => {
    timer = setTimeout(() => reject(new Error(message)), ms);
  });
  return Promise.race([promise, timeout]).finally(() => clearTimeout(timer));
}

const queueConnection = createRedisClient();

const defaultJobOptions = {
  attempts: 5,
  backoff: { type: 'exponential', delay: 2000 },
  removeOnComplete: { age: 3600, count: 1000 },
  removeOnFail: { age: 86400, count: 5000 },
};

export const whatsappQueue = new Queue('whatsapp-outbound', {
  connection: queueConnection,
  defaultJobOptions,
});

export const printerQueue = new Queue('printer', {
  connection: queueConnection,
  defaultJobOptions: {
    ...defaultJobOptions,
    attempts: 3,
    backoff: { type: 'exponential', delay: 3000 },
  },
});

// --------------------------------------------------------------------------
// Callback para o Laravel
// --------------------------------------------------------------------------

async function notifyLaravel(payload) {
  const url = `${config.LARAVEL_BASE_URL}/api/v1/whatsapp/callback`;
  try {
    await axios.post(
      url,
      { service: 'autogest-whatsapp-service', timestamp: new Date().toISOString(), ...payload },
      { headers: { 'X-Service-Token': config.SERVICE_TOKEN }, timeout: 10000 },
    );
    return true;
  } catch (error) {
    logger.warn(
      { err: error.message, jobId: payload.jobId },
      'Falha ao enviar callback de entrega para o Laravel',
    );
    return false;
  }
}

// --------------------------------------------------------------------------
// Processadores
// --------------------------------------------------------------------------

async function processWhatsApp(job) {
  const instance = job.data.instance || config.EVOLUTION_INSTANCE;
  let result;

  switch (job.name) {
    case 'send-text':
      result = await evolution.sendText(
        instance,
        job.data.number,
        job.data.text ?? job.data.message ?? '',
      );
      break;
    case 'send-media':
      result = await evolution.sendMedia(
        instance,
        job.data.number,
        job.data.media ?? job.data.base64 ?? job.data.url,
        job.data.fileName,
        job.data.caption ?? '',
      );
      break;
    case 'send-document-pdf':
      result = await evolution.sendMedia(
        instance,
        job.data.number,
        job.data.documentBase64 ?? job.data.documentUrl ?? job.data.media,
        job.data.fileName ?? 'documento.pdf',
        job.data.caption ?? '',
      );
      break;
    default:
      throw new Error(`Tipo de job WhatsApp desconhecido: ${job.name}`);
  }

  counters.messagesSentTotal.inc();
  counters.jobsProcessedTotal.inc({ queue: 'whatsapp-outbound', name: job.name });

  await notifyLaravel({
    jobId: job.id,
    status: 'sent',
    messageId: result.messageId ?? null,
    error: null,
    number: job.data.number,
    orderId: job.data.orderId ?? null,
    name: job.name,
  });

  return { messageId: result.messageId ?? null };
}

// Diretorio de spool local (usado quando nao ha impressora fisica).
const SPOOL_DIR = path.resolve(process.cwd(), 'spool');

async function resolvePrintBytes({ base64, url }) {
  if (url) {
    const response = await axios.get(url, { responseType: 'arraybuffer', timeout: 30000 });
    return Buffer.from(response.data);
  }
  const value = String(base64 ?? '');
  if (!value) throw new Error('Job de impressao sem conteudo (base64 ou url)');
  const marker = 'base64,';
  const index = value.indexOf(marker);
  const payload = value.startsWith('data:') && index !== -1 ? value.slice(index + marker.length) : value;
  return Buffer.from(payload, 'base64');
}

async function tryPhysicalPrint(spoolFile, bytes) {
  let mod;
  try {
    // Import dinamico: pdf-to-printer e opcional/nativo e pode nao estar instalado.
    mod = await import('pdf-to-printer');
  } catch (error) {
    logger.warn({ err: error.message }, 'pdf-to-printer indisponivel; mantido apenas em spool');
    return;
  }

  try {
    const printFn = mod.print ?? mod.default?.print ?? mod.default;
    if (typeof printFn !== 'function') throw new Error('pdf-to-printer sem funcao print');

    const pdfPath = spoolFile.endsWith('.pdf') ? spoolFile : `${spoolFile}.pdf`;
    if (pdfPath !== spoolFile) fs.writeFileSync(pdfPath, bytes);

    const options = config.PRINTER_INTERFACE ? { printer: config.PRINTER_INTERFACE } : {};
    await printFn(pdfPath, options);
    logger.info(
      { pdfPath, printer: config.PRINTER_INTERFACE || '(predefinida)' },
      'Documento enviado para impressora',
    );
  } catch (error) {
    logger.warn(
      { err: error.message },
      'Falha ao imprimir fisicamente; documento permanece no spool',
    );
  }
}

async function processPrinter(job) {
  const bytes = await resolvePrintBytes(job.data);

  fs.mkdirSync(SPOOL_DIR, { recursive: true });
  const safeName = String(job.data.fileName ?? `${job.name}.pdf`).replace(/[^\w.\-]+/g, '_');
  const spoolFile = path.join(SPOOL_DIR, `${Date.now()}-${job.id}-${safeName}`);
  fs.writeFileSync(spoolFile, bytes);

  logger.info(
    { jobId: job.id, name: job.name, spoolFile, bytes: bytes.length },
    'Trabalho de impressao gravado no spool',
  );

  if (config.PRINTER_ENABLED) {
    await tryPhysicalPrint(spoolFile, bytes);
  }

  counters.jobsProcessedTotal.inc({ queue: 'printer', name: job.name });
  return { spoolFile };
}

// --------------------------------------------------------------------------
// Workers
// --------------------------------------------------------------------------

let started = false;
const workers = [];

export function startWorkers() {
  if (started) return workers;
  started = true;

  const whatsappWorker = new Worker('whatsapp-outbound', processWhatsApp, {
    connection: createRedisClient(),
    concurrency: 5,
  });

  whatsappWorker.on('completed', (job, result) =>
    logger.debug({ jobId: job.id, name: job.name, result }, 'Job WhatsApp concluido'),
  );

  whatsappWorker.on('failed', async (job, error) => {
    counters.messagesFailedTotal.inc();
    logger.error(
      { jobId: job?.id, name: job?.name, attempts: job?.attemptsMade, err: error?.message },
      'Job WhatsApp falhou',
    );

    if (job) {
      const maxAttempts = job.opts?.attempts ?? 1;
      // So notificamos o Laravel quando as tentativas se esgotam.
      if (job.attemptsMade >= maxAttempts) {
        await notifyLaravel({
          jobId: job.id,
          status: 'failed',
          messageId: null,
          error: error?.message ?? 'erro desconhecido',
          number: job.data?.number ?? null,
          orderId: job.data?.orderId ?? null,
          name: job.name,
        });
      }
    }
  });

  const printerWorker = new Worker('printer', processPrinter, {
    connection: createRedisClient(),
    concurrency: 2,
  });

  printerWorker.on('completed', (job) =>
    logger.debug({ jobId: job.id, name: job.name }, 'Job de impressao concluido'),
  );
  printerWorker.on('failed', (job, error) =>
    logger.error({ jobId: job?.id, name: job?.name, err: error?.message }, 'Job de impressao falhou'),
  );

  workers.push(whatsappWorker, printerWorker);
  logger.info('Workers BullMQ iniciados (whatsapp-outbound, printer)');
  return workers;
}

// --------------------------------------------------------------------------
// Helpers de enfileiramento
// --------------------------------------------------------------------------

export function enqueueWhatsAppText(data) {
  return whatsappQueue.add('send-text', data);
}

export function enqueueWhatsAppMedia(data) {
  return whatsappQueue.add('send-media', data);
}

export function enqueueWhatsAppDocument(data) {
  return whatsappQueue.add('send-document-pdf', data);
}

/** kind: "ticket" | "a4" */
export function enqueuePrint(kind, data) {
  const name = kind === 'a4' ? 'print-a4' : 'print-ticket';
  return printerQueue.add(name, data);
}

// --------------------------------------------------------------------------
// Utilidades de operacao
// --------------------------------------------------------------------------

export async function getQueueStats() {
  try {
    const [whatsapp, printer] = await withTimeout(
      Promise.all([whatsappQueue.getJobCounts(), printerQueue.getJobCounts()]),
      2000,
      'redis_unavailable',
    );
    return { 'whatsapp-outbound': whatsapp, printer };
  } catch (error) {
    logger.warn({ err: error.message }, 'Nao foi possivel obter estatisticas das filas');
    return { 'whatsapp-outbound': 'unavailable', printer: 'unavailable' };
  }
}

/** Reprocessa jobs falhados (usado pelo cron a cada 5 minutos). */
export async function retryFailedJobs(limit = 100) {
  const results = { whatsapp: 0, printer: 0 };
  try {
    const waFailed = await withTimeout(whatsappQueue.getFailed(0, limit), 5000, 'redis_unavailable');
    for (const job of waFailed) {
      try {
        await job.retry();
        results.whatsapp += 1;
      } catch {
        /* ignorado: job pode ter mudado de estado */
      }
    }
    const prFailed = await withTimeout(printerQueue.getFailed(0, limit), 5000, 'redis_unavailable');
    for (const job of prFailed) {
      try {
        await job.retry();
        results.printer += 1;
      } catch {
        /* ignorado */
      }
    }
  } catch (error) {
    logger.warn({ err: error.message }, 'Falha ao reprocessar jobs falhados');
  }
  return results;
}

/** Encerramento limpo dos workers, filas e ligacoes Redis. */
export async function shutdown() {
  await Promise.allSettled(workers.map((worker) => worker.close()));
  await Promise.allSettled([whatsappQueue.close(), printerQueue.close()]);
  await Promise.allSettled(
    redisClients.map((client) => client.quit().catch(() => client.disconnect())),
  );
}
