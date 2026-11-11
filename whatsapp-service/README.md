# autogest-whatsapp-service

Microsservico Node.js (ESM, Node >= 20) que funciona como **gateway WhatsApp**,
**fila de notificacoes em tempo real** e **ponte de impressao termica** para o ERP
Laravel `autogest_nicola` (gestao de oficina automovel + faturacao certificada, Angola).

- Gateway WhatsApp via [Evolution API](https://github.com/EvolutionAPI/evolution-api).
- Filas assincronas com BullMQ sobre Redis (retry exponencial).
- Callbacks de estado para o Laravel autenticados com o header `X-Service-Token`.
- Assistente de IA leve com fallback local (portugues de Angola).
- Impressao termica/A4 com spool local e `pdf-to-printer` opcional.
- Metricas no formato Prometheus em `GET /metrics`.

## Arranque rapido

```bash
cp .env.example .env      # ajuste os valores
npm install
npm start                 # ou: npm run dev (node --watch)
```

Servico disponivel em `http://localhost:3000`.

Com Docker:

```bash
docker compose up --build
```

## Variaveis de ambiente

| Variavel | Omissao | Descricao |
| --- | --- | --- |
| `NODE_ENV` | `development` | `development` \| `production` \| `test`. |
| `PORT` | `3000` | Porta HTTP do servico. |
| `LARAVEL_BASE_URL` | `http://127.0.0.1:8000` | URL base do ERP Laravel. |
| `SERVICE_TOKEN` | `dev-service-token` | Segredo partilhado (header `X-Service-Token`). |
| `EVOLUTION_API_URL` | `http://127.0.0.1:8080` | URL base da Evolution API. |
| `EVOLUTION_API_KEY` | _(vazio)_ | Chave da Evolution API (header `apikey`). |
| `EVOLUTION_INSTANCE` | `autogest-nicola` | Nome da instancia WhatsApp. |
| `REDIS_URL` | `redis://127.0.0.1:6379` | Ligacao Redis (BullMQ). |
| `LOG_LEVEL` | `info` | `trace`..`fatal` \| `silent`. |
| `PRINTER_ENABLED` | `false` | Se `true`, tenta imprimir via `pdf-to-printer`. |
| `PRINTER_INTERFACE` | _(vazio)_ | Nome da impressora (vazio = predefinida). |
| `DEFAULT_COUNTRY_CODE` | `244` | Indicativo usado na normalizacao de numeros. |

Todos os valores sao validados no arranque com `zod`; configuracao invalida
impede o arranque (falha ruidosa).

## Endpoints

Rotas publicas (sem token): `GET /health`, `GET /metrics`, `GET /qr`, `POST /webhook/evolution`.
Todas as restantes exigem o header `X-Service-Token`.

| Metodo | Rota | Descricao |
| --- | --- | --- |
| GET | `/health` | `{ status, uptime, queues }`. |
| GET | `/metrics` | Contadores Prometheus (texto simples). |
| GET | `/qr` | QR de emparelhamento (base64 + ascii nos logs). |
| POST | `/send/text` | `{ number, message, orderId? }` -> `{ jobId }`. |
| POST | `/send/document` | `{ number, documentBase64 \| documentUrl, fileName, caption, documentType }` -> `{ jobId }`. |
| POST | `/webhook/evolution` | Mensagens de entrada: encaminha para o Laravel e faz auto-resposta. |
| POST | `/print/ticket` | `{ base64 \| url, fileName }` -> `{ jobId }` (talao). |
| POST | `/print/a4` | `{ base64 \| url, fileName }` -> `{ jobId }` (A4). |

Erros sao sempre JSON: `{ "error": "...", "detail": ... }`.

### Exemplos

```bash
# Enviar texto
curl -X POST http://localhost:3000/send/text \
  -H "X-Service-Token: change-me-please" \
  -H "Content-Type: application/json" \
  -d '{"number":"923456789","message":"A sua viatura esta pronta."}'

# Enviar PDF (base64 ou URL)
curl -X POST http://localhost:3000/send/document \
  -H "X-Service-Token: change-me-please" \
  -H "Content-Type: application/json" \
  -d '{"number":"923456789","documentUrl":"https://exemplo.ao/fatura.pdf","fileName":"fatura.pdf"}'

# Imprimir talao
curl -X POST http://localhost:3000/print/ticket \
  -H "X-Service-Token: change-me-please" \
  -H "Content-Type: application/json" \
  -d '{"base64":"JVBERi0xLjQK...","fileName":"talao.pdf"}'
```

## Integracao com o Laravel (autogest_nicola)

1. **Token partilhado.** Defina `SERVICE_TOKEN` aqui e o mesmo valor no Laravel
   (ex.: `WHATSAPP_SERVICE_TOKEN`). Todos os pedidos entre os dois usam o header
   `X-Service-Token`.

2. **Endpoints que o Laravel deve expor** (o servico Node chama-os):

   | Rota Laravel | Metodo | Corpo (Node -> Laravel) | Resposta usada |
   | --- | --- | --- | --- |
   | `/api/v1/whatsapp/callback` | POST | `{ jobId, status: "sent"\|"failed", messageId, error, number, orderId, name }` | _ignorada_ |
   | `/api/v1/whatsapp/inbound` | POST | `{ number, fromMe, messageId, pushName, body, instance, raw }` | `{ reply?: string }` (opcional) |
   | `/api/v1/ai/ask` | POST | `{ message, from, context }` | `{ reply: string }` |

3. **Proteger as rotas.** No Laravel, valide o header antes de processar, por exemplo:

   ```php
   $token = $request->header('X-Service-Token');
   abort_unless($token && hash_equals(config('services.whatsapp.token'), $token), 401);
   ```

   e em `config/services.php`:

   ```php
   'whatsapp' => ['token' => env('WHATSAPP_SERVICE_TOKEN')],
   ```

4. **Configurar o webhook na Evolution API** para apontar para
   `http://<host-do-servico>:3000/webhook/evolution` (evento `MESSAGES_UPSERT`).

5. **Enviar a partir do Laravel** (ex.: ao concluir uma OS) chamando
   `POST /send/text` ou `POST /send/document` com o header `X-Service-Token`.

## Impressao

Os trabalhos de impressao sao sempre gravados em `./spool/`. Quando
`PRINTER_ENABLED=true` e o modulo opcional `pdf-to-printer` esta instalado, o
servico tenta enviar para a impressora (`PRINTER_INTERFACE`). Se a impressora ou
o modulo nao existirem, o servico **nao falha** — mantem apenas o ficheiro no spool.

## Arquitetura

```
src/
  config.js        validacao de ambiente (zod), objeto congelado
  logger.js        pino (pretty em dev/TTY)
  evolution.js     cliente Evolution API + normalizeNumber
  queue.js         filas BullMQ, workers, callbacks Laravel, spool
  routes.js        router Express (todos os endpoints)
  ai.js            assistente com fallback local
  telemetry.js     contadores Prometheus (/metrics)
  middleware/auth.js  validacao do X-Service-Token
  index.js         bootstrap Express, workers, cron, shutdown
```

Filas BullMQ:
- `whatsapp-outbound`: `send-text`, `send-media`, `send-document-pdf` (5 tentativas, backoff exponencial).
- `printer`: `print-ticket`, `print-a4` (3 tentativas).

Um cron reprocessa jobs falhados a cada 5 minutos.

## Testes

```bash
npm test          # node --test
```

## Notas de producao

- Coloque o servico atras de HTTPS (nginx/Traefik) e nunca exponha `SERVICE_TOKEN`.
- Mantenha o Redis persistente (AOF) para nao perder a fila.
- A Evolution API precisa de uma base de dados (ver `docker-compose.yml`).
