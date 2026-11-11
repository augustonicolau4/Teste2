import { config } from '../config.js';

/**
 * Middleware de autenticacao entre servicos.
 * Valida o header `X-Service-Token` contra a configuracao partilhada
 * para todas as rotas, exceto as publicas.
 */

// Rotas publicas (sem token): saude, metricas, webhook de entrada e QR de emparelhamento.
const PUBLIC_ROUTES = new Set(['/health', '/metrics', '/webhook/evolution', '/qr']);

export function isPublicRoute(path) {
  return PUBLIC_ROUTES.has(path);
}

export function authMiddleware(req, res, next) {
  if (isPublicRoute(req.path)) return next();

  const token = req.get('X-Service-Token');
  if (!token || token !== config.SERVICE_TOKEN) {
    return res.status(401).json({
      error: 'unauthorized',
      detail: 'Header X-Service-Token invalido ou ausente.',
    });
  }

  return next();
}

export default authMiddleware;
