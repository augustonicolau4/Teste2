import { Router } from 'express';
import axios from 'axios';
import qrcode from 'qrcode-terminal';
import { z } from 'zod';
import { config } from './config.js';
import { logger } from './logger.js';
import * as evolution from './evolution.js';
import { handleInbound, shouldAutoReply } from './ai.js';
import { metricsText } from './telemetry.js';
import {
  enqueueWhatsAppText,
  enqueueWhatsAppDocument,
  enqueuePrint,
  getQueueStats,
} from './queue.js';

export const router = Router();

const optionalId = z.union([z.string(), z.number()]).optional();

// --------------------------------------------------------------------------
// Saude e metricas
// --------------------------------------------------------------------------

router.get('/health', async (req, res) => {
  const queues = await getQueueStats();
  res.json({ status: 'ok', uptime: process.uptime(), queues });
});

router.get('/metrics', (req, res) => {
  res.setHeader('Content-Type', 'text/plain; version=0.0.4; charset=utf-8');
  res.send(metricsText());
});

// --------------------------------------------------------------------------
// QR code (emparelhamento da instancia)
// --------------------------------------------------------------------------

router.get('/qr', async (req, res) => {
  try {
    let qr = await evolution.connectInstance();
    if (!qr.base64 && !qr.code) {
      // Instancia ainda nao existe: criar e tentar de novo.
      await evolution.createInstance();
      qr = await evolution.connectInstance();
    }

    if (qr.code) {
      qrcode.generate(qr.code, { small: true }, (ascii) => logger.info(`\n${ascii}`));
    }

    res.json({ instance: config.EVOLUTION_INSTANCE, base64: qr.base64, code: qr.code });
  } catch (error) {
    res.status(502).json({ error: 'evolution_error', detail: error.message });
  }
});

// --------------------------------------------------------------------------
// Envio de mensagens
// --------------------------------------------------------------------------

const textSchema = z.object({
  number: z.string().min(3, 'number e obrigatorio'),
  message: z.string().min(1, 'message e obrigatorio'),
  orderId: optionalId,
});

router.post('/send/text', async (req, res) => {
  const parsed = textSchema.safeParse(req.body);
  if (!parsed.success) {
    return res.status(422).json({ error: 'validation_error', detail: parsed.error.issues });
  }
  try {
    const job = await enqueueWhatsAppText({
      number: parsed.data.number,
      text: parsed.data.message,
      orderId: parsed.data.orderId,
    });
    res.status(202).json({ jobId: job.id, queued: true });
  } catch (error) {
    res.status(500).json({ error: 'enqueue_error', detail: error.message });
  }
});

const documentSchema = z
  .object({
    number: z.string().min(3, 'number e obrigatorio'),
    documentBase64: z.string().optional(),
    documentUrl: z.string().url().optional(),
    fileName: z.string().min(1).default('documento.pdf'),
    caption: z.string().optional().default(''),
    documentType: z.string().optional().default('application/pdf'),
    orderId: optionalId,
  })
  .refine((data) => Boolean(data.documentBase64 || data.documentUrl), {
    message: 'documentBase64 ou documentUrl e obrigatorio',
    path: ['documentBase64'],
  });

router.post('/send/document', async (req, res) => {
  const parsed = documentSchema.safeParse(req.body);
  if (!parsed.success) {
    return res.status(422).json({ error: 'validation_error', detail: parsed.error.issues });
  }
  try {
    const job = await enqueueWhatsAppDocument({
      number: parsed.data.number,
      documentBase64: parsed.data.documentBase64,
      documentUrl: parsed.data.documentUrl,
      fileName: parsed.data.fileName,
      caption: parsed.data.caption,
      documentType: parsed.data.documentType,
      orderId: parsed.data.orderId,
    });
    res.status(202).json({ jobId: job.id, queued: true });
  } catch (error) {
    res.status(500).json({ error: 'enqueue_error', detail: error.message });
  }
});

// --------------------------------------------------------------------------
// Webhook de entrada (Evolution -> Laravel + auto-resposta)
// --------------------------------------------------------------------------

/** Normaliza os varios formatos de mensagem enviados pela Evolution API. */
function normalizeInbound(body) {
  const data = body?.data ?? body ?? {};
  const key = data?.key ?? {};
  const remoteJid = key.remoteJid ?? data?.from ?? '';
  const message = data?.message ?? {};

  const rawText =
    message.conversation ??
    message.extendedTextMessage?.text ??
    message.imageMessage?.caption ??
    message.videoMessage?.caption ??
    message.buttonsResponseMessage?.selectedDisplayText ??
    message.listResponseMessage?.title ??
    '';

  return {
    number: evolution.normalizeNumber(String(remoteJid).split('@')[0]),
    fromMe: Boolean(key.fromMe),
    messageId: key.id ?? null,
    pushName: data?.pushName ?? null,
    body: rawText,
    instance: body?.instance ?? data?.instance ?? config.EVOLUTION_INSTANCE,
    raw: body,
  };
}

router.post('/webhook/evolution', async (req, res) => {
  try {
    const inbound = normalizeInbound(req.body);

    // Ignorar mensagens enviadas pelo proprio bot ou sem numero valido.
    if (inbound.fromMe || !inbound.number) {
      return res.json({ ok: true, ignored: true });
    }

    // 1) Encaminhar para o Laravel (a aplicacao pode devolver um `reply`).
    let laravelReply = null;
    try {
      const { data } = await axios.post(
        `${config.LARAVEL_BASE_URL}/api/v1/whatsapp/inbound`,
        inbound,
        { headers: { 'X-Service-Token': config.SERVICE_TOKEN }, timeout: 10000 },
      );
      laravelReply = typeof data?.reply === 'string' ? data.reply : null;
    } catch (error) {
      logger.warn(
        { err: error.message, from: inbound.number },
        'Nao foi possivel encaminhar a mensagem de entrada para o Laravel',
      );
    }

    // 2) Auto-resposta: resposta do Laravel OU gatilho local (IA com fallback).
    let reply = laravelReply;
    if (!reply && shouldAutoReply(inbound.body)) {
      const ai = await handleInbound(
        { body: inbound.body, from: inbound.number, context: { pushName: inbound.pushName } },
        { from: inbound.number },
      );
      reply = ai.reply;
    }

    if (reply) {
      await enqueueWhatsAppText({ number: inbound.number, text: reply });
    }

    res.json({ ok: true, forwarded: true, replied: Boolean(reply) });
  } catch (error) {
    res.status(500).json({ error: 'webhook_error', detail: error.message });
  }
});

// --------------------------------------------------------------------------
// Impressao
// --------------------------------------------------------------------------

const printSchema = z
  .object({
    base64: z.string().optional(),
    url: z.string().url().optional(),
    fileName: z.string().min(1).default('documento.pdf'),
  })
  .refine((data) => Boolean(data.base64 || data.url), {
    message: 'base64 ou url e obrigatorio',
    path: ['base64'],
  });

function makePrintHandler(kind) {
  return async (req, res) => {
    const parsed = printSchema.safeParse(req.body);
    if (!parsed.success) {
      return res.status(422).json({ error: 'validation_error', detail: parsed.error.issues });
    }
    try {
      const job = await enqueuePrint(kind, {
        base64: parsed.data.base64,
        url: parsed.data.url,
        fileName: parsed.data.fileName,
      });
      res.status(202).json({ jobId: job.id, queued: true, kind });
    } catch (error) {
      res.status(500).json({ error: 'enqueue_error', detail: error.message });
    }
  };
}

router.post('/print/ticket', makePrintHandler('ticket'));
router.post('/print/a4', makePrintHandler('a4'));

export default router;
