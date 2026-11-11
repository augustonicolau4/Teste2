import pino from 'pino';
import { config } from './config.js';

/**
 * Em desenvolvimento e quando a saida e um terminal real, usamos pino-pretty.
 * Fora disso (producao, CI, testes) fica o logger JSON rapido por predefinicao,
 * evitando o worker thread do pino-pretty.
 */
const usePretty = config.isDevelopment && Boolean(process.stdout.isTTY);

export const logger = pino({
  level: config.LOG_LEVEL,
  base: config.isDevelopment ? undefined : { service: 'autogest-whatsapp-service' },
  transport: usePretty
    ? {
        target: 'pino-pretty',
        options: {
          colorize: true,
          translateTime: 'SYS:HH:MM:ss',
          ignore: 'pid,hostname',
          singleLine: false,
        },
      }
    : undefined,
});

export default logger;
