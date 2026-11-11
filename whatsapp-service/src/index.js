import express from 'express';
import cron from 'node-cron';
import { config } from './config.js';
import { logger } from './logger.js';
import { router } from './routes.js';
import { authMiddleware } from './middleware/auth.js';
import { startWorkers, shutdown as shutdownQueue, retryFailedJobs } from './queue.js';
import * as evolution from './evolution.js';

const app = express();
app.disable('x-powered-by');

// Cabecalhos de seguranca minimos (equivalente leve ao helmet).
app.use((req, res, next) => {
  res.setHeader('X-Content-Type-Options', 'nosniff');
  res.setHeader('X-Frame-Options', 'DENY');
  res.setHeader('X-XSS-Protection', '0');
  res.setHeader('Referrer-Policy', 'no-referrer');
  next();
});

// Limite elevado para acomodar PDFs em base64.
app.use(express.json({ limit: '15mb' }));

// Registo de pedidos.
app.use((req, res, next) => {
  const startedAt = Date.now();
  res.on('finish', () => {
    logger.info(
      { method: req.method, path: req.originalUrl, status: res.statusCode, ms: Date.now() - startedAt },
      'http',
    );
  });
  next();
});

// Autenticacao entre servicos (exceto rotas publicas).
app.use(authMiddleware);

// Rotas da aplicacao.
app.use(router);

// 404 em JSON.
app.use((req, res) => {
  res.status(404).json({ error: 'not_found', detail: `Rota nao encontrada: ${req.method} ${req.originalUrl}` });
});

// Tratamento de erros em JSON (inclui payload demasiado grande).
// eslint-disable-next-line no-unused-vars
app.use((err, req, res, next) => {
  const status = err.status ?? err.statusCode ?? 500;
  logger.error({ err: err.message, path: req.originalUrl }, 'Erro nao tratado');
  res.status(status).json({
    error: status === 413 ? 'payload_too_large' : 'internal_error',
    detail: err.message,
  });
});

// --------------------------------------------------------------------------
// Arranque
// --------------------------------------------------------------------------

const server = app.listen(config.PORT, () => {
  logger.info({ port: config.PORT, env: config.NODE_ENV }, 'autogest-whatsapp-service a escutar');
});

startWorkers();

/** Verifica a ligacao a Evolution API no arranque (nao fatal se indisponivel). */
async function verifyEvolution() {
  const status = await evolution.checkConnection();
  if (status.connected) {
    logger.info({ instance: status.instance }, 'Instancia Evolution ligada');
  } else {
    logger.warn(
      { instance: status.instance, state: status.state },
      'Instancia Evolution nao ligada; consulte GET /qr para emparelhar',
    );
  }
}
verifyEvolution();

// Retry de jobs falhados a cada 5 minutos.
cron.schedule('*/5 * * * *', async () => {
  try {
    const retried = await retryFailedJobs();
    logger.info({ retried }, 'Reprocessamento automatico de jobs falhados concluido');
  } catch (error) {
    logger.warn({ err: error.message }, 'Falha no reprocessamento automatico de jobs');
  }
});

// --------------------------------------------------------------------------
// Encerramento limpo
// --------------------------------------------------------------------------

let shuttingDown = false;

async function gracefulShutdown(signal) {
  if (shuttingDown) return;
  shuttingDown = true;
  logger.info({ signal }, 'A desligar o servico...');

  const forceTimer = setTimeout(() => {
    logger.error('Desligamento forcado por timeout');
    process.exit(1);
  }, 10000);
  forceTimer.unref();

  server.close(async () => {
    try {
      await shutdownQueue();
    } finally {
      clearTimeout(forceTimer);
      logger.info('Servico desligado com sucesso');
      process.exit(0);
    }
  });
}

process.on('SIGTERM', () => gracefulShutdown('SIGTERM'));
process.on('SIGINT', () => gracefulShutdown('SIGINT'));
process.on('unhandledRejection', (reason) =>
  logger.error({ err: String(reason) }, 'Promessa rejeitada nao tratada'),
);
process.on('uncaughtException', (error) => {
  logger.fatal({ err: error.message, stack: error.stack }, 'Excecao nao capturada');
  gracefulShutdown('uncaughtException');
});

export { app, server };
