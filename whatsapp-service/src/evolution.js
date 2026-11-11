import axios from 'axios';
import { config } from './config.js';
import { logger } from './logger.js';

/**
 * Cliente HTTP para a Evolution API (https://github.com/EvolutionAPI/evolution-api).
 * Todas as chamadas usam o header `apikey`.
 */
const http = axios.create({
  baseURL: config.EVOLUTION_API_URL,
  timeout: 20000,
  headers: {
    'Content-Type': 'application/json',
    apikey: config.EVOLUTION_API_KEY,
  },
});

/**
 * Normaliza um numero de telefone para o formato aceite pela Evolution API.
 *  - Remove espacos, "+", "-", parenteses e qualquer caractere nao numerico.
 *  - Remove o prefixo internacional "00".
 *  - Garante o indicativo do pais (por omissao 244 - Angola).
 *  - Converte numeros locais de 9 digitos (ex.: 9XXXXXXXX) para 2449XXXXXXXX.
 */
export function normalizeNumber(raw, defaultCountryCode = config.DEFAULT_COUNTRY_CODE) {
  if (raw === null || raw === undefined) return '';
  let digits = String(raw).replace(/\D/g, '');
  if (!digits) return '';

  const cc = String(defaultCountryCode).replace(/\D/g, '') || '244';

  // Prefixo internacional 00 -> remover.
  if (digits.startsWith('00')) digits = digits.slice(2);

  // Ja contem o indicativo do pais seguido do numero local.
  if (digits.startsWith(cc) && digits.length > cc.length + 8) return digits;

  // Numero local com zero a esquerda (ex.: 09XXXXXXXX) -> remover zeros.
  if (digits.startsWith('0')) digits = digits.replace(/^0+/, '');

  // Numero local de 9 digitos (Angola: 9XXXXXXXX) ou mais curto -> prefixar indicativo.
  if (digits.length <= 9) return cc + digits;

  return digits;
}

/** Extrai o payload base64 de uma eventual data URL. */
function toBase64Payload(value) {
  const str = String(value ?? '');
  const marker = 'base64,';
  const index = str.indexOf(marker);
  if (str.startsWith('data:') && index !== -1) return str.slice(index + marker.length);
  return str;
}

/** Normaliza as diferentes formas de resposta de QR da Evolution API. */
function extractQr(data) {
  const source = data?.qrcode ?? data?.instance ?? data ?? {};
  const base64 = source?.base64 ?? data?.base64 ?? data?.qrcode?.base64 ?? null;
  const code = source?.code ?? data?.code ?? data?.qrcode?.code ?? null;
  const count = source?.count ?? data?.count ?? null;
  return { base64: base64 ?? null, code: code ?? null, count: count ?? null };
}

/** Extrai o id da mensagem das varias formas devolvidas pela Evolution API. */
function extractMessageId(data) {
  return data?.key?.id ?? data?.messageId ?? data?.id ?? null;
}

/** Cria (ou solicita a criacao de) uma instancia na Evolution API. */
export async function createInstance(instance = config.EVOLUTION_INSTANCE) {
  const { data } = await http.post('/instance/create', {
    instanceName: instance,
    qrcode: true,
    integration: 'WHATSAPP-BAILEYS',
  });
  logger.info({ instance }, 'Instancia Evolution criada/solicitada');
  return { instance, qr: extractQr(data), raw: data };
}

/** Liga uma instancia e devolve o QR code (base64) para emparelhamento. */
export async function connectInstance(instance = config.EVOLUTION_INSTANCE) {
  const { data } = await http.get(`/instance/connect/${encodeURIComponent(instance)}`);
  return { instance, ...extractQr(data), raw: data };
}

/** Verifica o estado de ligacao da instancia. Nunca lanca (devolve estado "unreachable"). */
export async function checkConnection(instance = config.EVOLUTION_INSTANCE) {
  try {
    const { data } = await http.get(`/instance/connectionState/${encodeURIComponent(instance)}`);
    const state = data?.instance?.state ?? data?.state ?? 'unknown';
    return { instance, state, connected: state === 'open' };
  } catch (error) {
    logger.warn(
      { instance, err: error.message },
      'Nao foi possivel verificar a ligacao a instancia Evolution',
    );
    return { instance, state: 'unreachable', connected: false };
  }
}

/** Envia uma mensagem de texto. */
export async function sendText(instance, number, text) {
  const target = normalizeNumber(number);
  if (!target) throw new Error('Numero de destino invalido');

  const { data } = await http.post(`/message/sendText/${encodeURIComponent(instance)}`, {
    number: target,
    text: String(text ?? ''),
  });

  return { messageId: extractMessageId(data), number: target, raw: data };
}

/** Envia um documento/media (aceita base64 ou URL publico). */
export async function sendMedia(instance, number, base64OrUrl, fileName = 'documento.pdf', caption = '') {
  const target = normalizeNumber(number);
  if (!target) throw new Error('Numero de destino invalido');

  const value = String(base64OrUrl ?? '');
  if (!value) throw new Error('Conteudo do media em falta');

  const isUrl = /^https?:\/\//i.test(value);

  const { data } = await http.post(`/message/sendMedia/${encodeURIComponent(instance)}`, {
    number: target,
    mediatype: 'document',
    media: isUrl ? value : toBase64Payload(value),
    fileName,
    caption: caption ?? '',
    mimetype: 'application/pdf',
  });

  return { messageId: extractMessageId(data), number: target, raw: data };
}
