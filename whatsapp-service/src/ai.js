import axios from 'axios';
import { config } from './config.js';
import { logger } from './logger.js';

/**
 * Assistente de IA leve.
 * Tenta obter resposta no endpoint de IA do Laravel e, se estiver indisponivel,
 * recorre a respostas locais baseadas em regras (funciona offline).
 * Idioma: portugues de Angola.
 */

const normalize = (text) => String(text ?? '').trim().toLowerCase();

/** Palavras que fazem disparar a auto-resposta no webhook de entrada. */
export const AI_TRIGGER_PREFIXES = [
  '/',
  '!',
  'menu',
  'ajuda',
  'bot',
  'ola',
  'olá',
  'bom dia',
  'boa tarde',
  'boa noite',
];

/** Indica se o corpo da mensagem deve disparar uma auto-resposta. */
export function shouldAutoReply(text) {
  const value = normalize(text);
  if (!value) return false;
  return AI_TRIGGER_PREFIXES.some((prefix) => value.startsWith(prefix));
}

/** Respostas locais (fallback offline) em portugues de Angola. */
export function localReply(message) {
  const text = normalize(message);

  if (/estado\s+(da\s+)?viatura|situa[cç][aã]o\s+(da\s+)?viatura|matr[ií]cula/.test(text)) {
    return 'Para consultar o estado da sua viatura, envie a matricula no formato LD-00-00-AA. Assim que a ordem de servico for atualizada, informamos o progresso.';
  }

  if (/estado\s+(da\s+)?(os|ordem)|ordem\s+de\s+servi[cç]o|\bos\b/.test(text)) {
    return 'Para saber o estado da ordem de servico (OS), envie o numero da OS (ex.: OS-1024). Pode tambem ligar-nos durante o horario de funcionamento.';
  }

  if (/hor[aá]rio|aberto|fecha|funciona|funcionamento/.test(text)) {
    return 'Horario de funcionamento: Segunda a Sexta, 08h00 as 17h00; Sabado, 08h00 as 13h00. Domingo e feriados: fechado.';
  }

  if (/contacto|telefone|n[uú]mero|whatsapp|email|e-mail/.test(text)) {
    return 'Contactos: telefone (+244) 923 000 000, e-mail geral@autogest-nicola.ao. Estamos disponiveis no horario de funcionamento.';
  }

  if (/ol[aá]|bom dia|boa tarde|boa noite|menu|ajuda|op[cç][oõ]es/.test(text)) {
    return 'Ola! Bem-vindo a Oficina AutoGest Nicola. Pode perguntar sobre: 1) Estado da viatura, 2) Estado da OS, 3) Horario, 4) Contactos. Como o podemos ajudar?';
  }

  return 'Obrigado pela sua mensagem. Um tecnico da Oficina AutoGest Nicola ira responder em breve. Para respostas imediatas, escreva "menu".';
}

/**
 * Processa uma mensagem recebida e devolve { reply, source }.
 * `source` e "laravel" quando a resposta vem da IA do Laravel, "local" caso contrario.
 */
export async function handleInbound(message, ctx = {}) {
  const text = typeof message === 'string' ? message : message?.body ?? message?.text ?? '';
  const from = ctx.from ?? message?.from ?? '';
  const context = ctx.context ?? (typeof message === 'object' ? message?.context : undefined) ?? {};

  try {
    const { data } = await axios.post(
      `${config.LARAVEL_BASE_URL}/api/v1/ai/ask`,
      { message: String(text ?? ''), from, context },
      { headers: { 'X-Service-Token': config.SERVICE_TOKEN }, timeout: 10000 },
    );
    const reply = typeof data?.reply === 'string' ? data.reply.trim() : '';
    if (reply) return { reply, source: 'laravel' };
  } catch (error) {
    logger.warn(
      { err: error.message },
      'IA do Laravel indisponivel; a usar respostas locais',
    );
  }

  return { reply: localReply(text), source: 'local' };
}

export default { handleInbound, localReply, shouldAutoReply, AI_TRIGGER_PREFIXES };
