import 'dotenv/config';
import { z } from 'zod';

/**
 * O `z.coerce.boolean()` do zod trata qualquer string nao vazia como `true`,
 * pelo que precisamos de um preprocessamento explicito para "false"/"0"/"off".
 */
const booleanish = z.preprocess((value) => {
  if (typeof value === 'boolean') return value;
  if (typeof value === 'number') return value !== 0;
  if (typeof value === 'string') {
    const normalized = value.trim().toLowerCase();
    if (['1', 'true', 'yes', 'on', 'sim'].includes(normalized)) return true;
    if (['0', 'false', 'no', 'off', 'nao', ''].includes(normalized)) return false;
  }
  return value;
}, z.boolean());

const envSchema = z.object({
  NODE_ENV: z.enum(['development', 'production', 'test']).default('development'),
  PORT: z.coerce.number().int().positive().max(65535).default(3000),

  // Laravel (ERP)
  LARAVEL_BASE_URL: z.string().url().default('http://127.0.0.1:8000'),
  SERVICE_TOKEN: z.string().min(1).default('dev-service-token'),

  // Evolution API
  EVOLUTION_API_URL: z.string().url().default('http://127.0.0.1:8080'),
  EVOLUTION_API_KEY: z.string().default(''),
  EVOLUTION_INSTANCE: z.string().min(1).default('autogest-nicola'),

  // Fila / Redis
  REDIS_URL: z.string().min(1).default('redis://127.0.0.1:6379'),

  // Logs
  LOG_LEVEL: z
    .enum(['trace', 'debug', 'info', 'warn', 'error', 'fatal', 'silent'])
    .default('info'),

  // Impressao
  PRINTER_ENABLED: booleanish.default(false),
  PRINTER_INTERFACE: z.string().default(''),

  // Normalizacao de numeros
  DEFAULT_COUNTRY_CODE: z
    .string()
    .regex(/^[0-9]{1,4}$/)
    .default('244'),
});

const parsed = envSchema.safeParse(process.env);

if (!parsed.success) {
  // Falha ruidosa: configuracao invalida impede um arranque seguro do servico.
  const issues = parsed.error.issues
    .map((issue) => `${issue.path.join('.') || '(root)'}: ${issue.message}`)
    .join('; ');
  throw new Error(`Configuracao de ambiente invalida (verifique o .env): ${issues}`);
}

// Remove barras finais para permitir concatenacao segura de caminhos.
const stripTrailingSlash = (url) => url.replace(/\/+$/, '');

export const config = Object.freeze({
  ...parsed.data,
  LARAVEL_BASE_URL: stripTrailingSlash(parsed.data.LARAVEL_BASE_URL),
  EVOLUTION_API_URL: stripTrailingSlash(parsed.data.EVOLUTION_API_URL),
  isProduction: parsed.data.NODE_ENV === 'production',
  isDevelopment: parsed.data.NODE_ENV !== 'production',
});

export default config;
