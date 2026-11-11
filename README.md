<p align="center">
  <strong>autogest_nicola</strong><br>
  Sistema de Gestão de Oficina Automóvel e Facturação Certificada (AGT / Angola)
</p>

Sistema de gestão empresarial para oficinas automóvel, com facturação certificada
pela **AGT** (Administração Geral Tributária de Angola), gestão de inventário,
ordens de serviço, RH, CRM e integração com impressão dual (A4 / Ticket 80mm / 58mm),
além de um microsserviço Node.js para WhatsApp / IA.

---

## Stack

| Camada | Tecnologias |
|---|---|
| Backend | PHP `^8.2`, Laravel `^12.0`, Livewire `^4.4`, Sanctum `^4.4` |
| Domínio | `laravel-dompdf`, `maatwebsite/excel`, `simple-qrcode`, `spatie/laravel-permission` |
| Frontend | Vite + Tailwind + Alpine.js |
| Microsserviço | Node.js `>=20` (Express, BullMQ, Redis, Pino, Zod) |

---

## Pré-requisitos

| Ferramenta | Versão mínima |
|---|---|
| PHP | 8.2+ (extensões `openssl`, `pdo_mysql`, `mbstring`, `xml`, `bcmath`, `gd`) |
| Composer | 2.x |
| Node.js | 20+ |
| MySQL | 8+ (ou SQLite para teste rápido) |
| Redis | opcional (necessário apenas para o microsserviço WhatsApp) |

---

## Instalação

### 1. Obter o código

```bash
git clone https://github.com/augustonicolau4/Teste2.git autogest_nicola
cd autogest_nicola
```

### 2. Backend (Laravel)

```bash
# dependências PHP
composer install

# criar o ficheiro de ambiente e gerar a chave da aplicação
cp .env.example .env
php artisan key:generate
```

Criar a base de dados MySQL (uma vez):

```sql
CREATE DATABASE autogest_nicola CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Ajuste no `.env` apenas o que for necessário (`DB_USERNAME` / `DB_PASSWORD`).

> **Sem MySQL?** Para teste rápido use SQLite — no `.env` coloque
> `DB_CONNECTION=sqlite` e `DB_DATABASE=/caminho/absoluto/database.sqlite`,
> depois `touch database/database.sqlite`.

Migrar e popular os dados-base + demonstração:

```bash
php artisan migrate --seed
```

Isto cria a empresa emitente, impostos (IVA 14/7/0, ISE, NS), séries de
facturação, os 6 utilizadores por perfil e dados de demonstração (clientes,
viaturas, produtos, ordens de serviço e 1 factura-recibo).

As **chaves RSA de assinatura digital fiscal** (2048 bits) são geradas
automaticamente na primeira emissão, em `storage/app/keys/`. Não é necessária
qualquer acção manual.

### 3. Frontend

```bash
npm install
npm run build        # produção
# ou: npm run dev     # desenvolvimento (hot reload)
```

### 4. Microsserviço WhatsApp / IA (opcional)

Necessário apenas se usar integração WhatsApp ou impressão térmica.

```bash
cd whatsapp-service
cp .env.example .env
npm install
```

Garanta que `SERVICE_TOKEN` (no `whatsapp-service/.env`) é **igual** a
`WHATSAPP_SERVICE_TOKEN` (na raiz do Laravel). Requer também Redis e um gateway
WhatsApp (Evolution API).

---

## Arrancar

```bash
# Opção A — tudo de uma vez (servidor + fila + logs + vite)
composer dev

# Opção B — manual
php artisan serve            # terminal 1
php artisan queue:work       # terminal 2 (fila da base de dados)
```

Aceder em **http://localhost:8000**

---

## Atalho (após clonar)

```bash
composer install && cp .env.example .env && php artisan key:generate
php artisan migrate --seed
npm install && npm run build
composer dev
```

> O script `composer setup` também automatiza a instalação e o build.

---

## Acesso inicial

Todos os utilizadores de demonstração usam a password `password`.

| Perfil | Email |
|---|---|
| ADMIN | `admin@autogest.co.ao` |
| GESTOR | `gestor@autogest.co.ao` |
| FINANCEIRO | `financeiro@autogest.co.ao` |
| RH | `rh@autogest.co.ao` |
| APROVISIONADOR | `stock@autogest.co.ao` |
| PROMOTOR | `promotor@autogest.co.ao` |

> ⚠️ **Altere estas credenciais antes de qualquer uso em produção.**

---

## Configuração

Todas as variáveis de ambiente estão documentadas em [`.env.example`](.env.example).
As principais:

- **Fiscal / AGT** — empresa emitente, séries, IVA e retenção em `config/autogest.php`.
- **Assinatura digital** — chave RSA e algoritmo (SHA-256) em `config/autogest.php`.
- **Integrações** — `WHATSAPP_SERVICE_URL`, `WHATSAPP_SERVICE_TOKEN`, `AUTOGEST_AI_*`.
- **Impressão** — impressora térmica por omissão (`AUTOGEST_TICKET_PRINTER`).

---

## Testes

```bash
composer test
```

---

## Licença

Software proprietário. Todos os direitos reservados.
