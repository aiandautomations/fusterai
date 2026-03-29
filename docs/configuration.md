# Configuration Reference

All configuration is done via the `.env` file. Copy `.env.example` (manual install) or `.env.docker` (Docker) as a starting point.

---

## Application

| Variable | Default | Required | Description |
|---|---|---|---|
| `APP_NAME` | `FusterAI` | No | Application name shown in UI and emails |
| `APP_ENV` | `local` | Yes | `local`, `staging`, or `production` |
| `APP_KEY` | _(empty)_ | **Yes** | 32-byte encryption key — run `php artisan key:generate` |
| `APP_DEBUG` | `true` | Yes | Set to `false` in production |
| `APP_URL` | `http://localhost` | Yes | Public URL of your FusterAI instance |
| `APP_HOST` | `localhost` | No | Hostname portion of APP_URL (used by Docker) |
| `APP_PORT` | `8000` | No | HTTP port to expose |
| `APP_LOCALE` | `en` | No | Default application locale |
| `BCRYPT_ROUNDS` | `12` | No | Password hashing rounds (10–15 is typical) |

---

## Database (PostgreSQL)

| Variable | Default | Required | Description |
|---|---|---|---|
| `DB_CONNECTION` | `pgsql` | Yes | Must be `pgsql` — FusterAI requires PostgreSQL |
| `DB_HOST` | `127.0.0.1` | Yes | PostgreSQL host (`postgres` in Docker) |
| `DB_PORT` | `5432` | No | PostgreSQL port |
| `DB_DATABASE` | `fusterai` | Yes | Database name |
| `DB_USERNAME` | `fusterai` | Yes | Database user |
| `DB_PASSWORD` | `secret` | **Yes** | Database password — change in production |

> **Requirement:** pgvector extension must be enabled in your database. Docker handles this automatically via the `pgvector/pgvector:pg17` image. For manual setups, run `CREATE EXTENSION vector;` in your database.

---

## Cache, Sessions & Queues (Redis)

| Variable | Default | Required | Description |
|---|---|---|---|
| `REDIS_CLIENT` | `phpredis` | No | `phpredis` or `predis` |
| `REDIS_HOST` | `127.0.0.1` | Yes | Redis host (`redis` in Docker) |
| `REDIS_PASSWORD` | `null` | No | Redis auth password (set in production) |
| `REDIS_PORT` | `6379` | No | Redis port |
| `SESSION_DRIVER` | `redis` | No | `redis` recommended; `database` or `file` also work |
| `SESSION_LIFETIME` | `120` | No | Session duration in minutes |
| `QUEUE_CONNECTION` | `redis` | Yes | Must be `redis` for Horizon to work |
| `CACHE_STORE` | `redis` | No | Cache backend |
| `BROADCAST_CONNECTION` | `reverb` | Yes | Must be `reverb` for real-time updates |

---

## WebSockets (Laravel Reverb)

| Variable | Default | Required | Description |
|---|---|---|---|
| `REVERB_APP_ID` | `fusterai` | Yes | Application identifier |
| `REVERB_APP_KEY` | _(placeholder)_ | **Yes** | Public key for WebSocket auth — use a random string |
| `REVERB_APP_SECRET` | _(placeholder)_ | **Yes** | Private secret for WebSocket signing — use a random string |
| `REVERB_HOST` | `localhost` | Yes | Host Reverb listens on (`0.0.0.0` in Docker) |
| `REVERB_PORT` | `8080` | No | WebSocket port |
| `REVERB_SCHEME` | `http` | No | `http` or `https` (use `https` with a reverse proxy + TLS) |
| `VITE_REVERB_APP_KEY` | `${REVERB_APP_KEY}` | Yes | Must match `REVERB_APP_KEY` — baked into frontend bundle |
| `VITE_REVERB_HOST` | `${REVERB_HOST}` | Yes | WebSocket host from the browser's perspective |
| `VITE_REVERB_PORT` | `${REVERB_PORT}` | Yes | WebSocket port from the browser's perspective |
| `VITE_REVERB_SCHEME` | `${REVERB_SCHEME}` | Yes | `http` or `wss` |

> **Important:** The `VITE_*` variables are embedded into the compiled JavaScript at build time. If you change them, you must rebuild: `npm run build`.

---

## AI

| Variable | Default | Required | Description |
|---|---|---|---|
| `ANTHROPIC_API_KEY` | _(empty)_ | No | Anthropic API key from [console.anthropic.com](https://console.anthropic.com). Required to enable AI features. |
| `OPENAI_API_KEY` | _(empty)_ | No | OpenAI API key |
| `OPENAI_COMPATIBLE_BASE_URL` | _(empty)_ | No | Base URL for any OpenAI-compatible API (Ollama, OpenRouter, etc.) |
| `OPENAI_COMPATIBLE_API_KEY` | _(empty)_ | No | API key for the OpenAI-compatible endpoint |

> **AI feature flags** can also be toggled in Settings → AI Config in the admin panel. The env vars are used as fallbacks if no database config exists.

When `ANTHROPIC_API_KEY` is not set, all AI features are gracefully disabled. The helpdesk remains fully functional for email, conversations, and team collaboration.

---

## Search (MeiliSearch + Scout)

| Variable | Default | Required | Description |
|---|---|---|---|
| `SCOUT_DRIVER` | `meilisearch` | No | `meilisearch` for full-text search, `database` for basic search |
| `MEILISEARCH_HOST` | `http://localhost:7700` | No | MeiliSearch server URL (`http://meilisearch:7700` in Docker) |
| `MEILISEARCH_KEY` | _(placeholder)_ | No | MeiliSearch master key — required if analytics or multi-tenancy is used |

After changing `SCOUT_DRIVER` or setting up MeiliSearch for the first time:

```bash
php artisan scout:import "App\Domains\Conversation\Models\Conversation"
```

---

## Queue Monitor (Horizon)

| Variable | Default | Required | Description |
|---|---|---|---|
| `HORIZON_DRIVER` | `redis` | Yes | Must be `redis` |

Horizon dashboard is available at `/horizon`. Access is restricted to admin users in production (`APP_ENV=production`). All environments allow local access.

---

## Mail (Outbound)

| Variable | Default | Description |
|---|---|---|
| `MAIL_MAILER` | `log` | `log` (dev), `smtp`, `ses`, `mailgun`, `postmark` |
| `MAIL_HOST` | `127.0.0.1` | SMTP server host |
| `MAIL_PORT` | `2525` | SMTP port (587 for STARTTLS, 465 for SSL, 25 for plain) |
| `MAIL_USERNAME` | `null` | SMTP username |
| `MAIL_PASSWORD` | `null` | SMTP password |
| `MAIL_FROM_ADDRESS` | `hello@example.com` | Default "From" address for system emails |
| `MAIL_FROM_NAME` | `${APP_NAME}` | Default "From" name |
| `MAIL_SCHEME` | `null` | `null`, `tls`, or `ssl` |

> **Note:** Per-mailbox SMTP settings (for sending replies) are configured in the **Mailboxes** section of the admin panel, not via these env vars. These mail settings are only for system notifications (assignments, daily digests).

For local development, use **Mailpit** (included in Docker) which catches all outbound emails:
```env
MAIL_MAILER=smtp
MAIL_HOST=localhost   # or 'mailpit' inside Docker
MAIL_PORT=1025
```
Then view emails at http://localhost:8025.

---

## File Storage

| Variable | Default | Description |
|---|---|---|
| `FILESYSTEM_DISK` | `local` | `local` or `s3` |
| `AWS_ACCESS_KEY_ID` | _(empty)_ | S3 / compatible access key |
| `AWS_SECRET_ACCESS_KEY` | _(empty)_ | S3 / compatible secret key |
| `AWS_DEFAULT_REGION` | `us-east-1` | Bucket region |
| `AWS_BUCKET` | _(empty)_ | Bucket name |
| `AWS_ENDPOINT` | _(empty)_ | Custom endpoint for MinIO, Backblaze B2, Cloudflare R2, etc. |
| `AWS_USE_PATH_STYLE_ENDPOINT` | `false` | Set `true` for MinIO and other S3-compatible services |

**Examples by provider:**

```env
# MinIO (self-hosted)
FILESYSTEM_DISK=s3
AWS_ENDPOINT=http://localhost:9000
AWS_USE_PATH_STYLE_ENDPOINT=true
AWS_BUCKET=fusterai

# Cloudflare R2
FILESYSTEM_DISK=s3
AWS_ENDPOINT=https://<account-id>.r2.cloudflarestorage.com
AWS_USE_PATH_STYLE_ENDPOINT=true

# Backblaze B2
FILESYSTEM_DISK=s3
AWS_ENDPOINT=https://s3.us-west-004.backblazeb2.com
```

---

## OAuth 2.1 (Laravel Passport)

| Variable | Default | Description |
|---|---|---|
| `PASSPORT_PRIVATE_KEY` | _(empty)_ | PEM-encoded private key for JWT signing |
| `PASSPORT_PUBLIC_KEY` | _(empty)_ | Corresponding public key |

Generate keys with:
```bash
php artisan passport:keys
```

These keys are required for the REST API (Bearer token auth) and MCP server (OAuth 2.1 flow).

---

## CORS

| Variable | Default | Description |
|---|---|---|
| `CORS_ALLOWED_ORIGINS` | `*` | Comma-separated origins allowed to call the REST API. Use `*` in dev only. |

Production example:
```env
CORS_ALLOWED_ORIGINS=https://yourapp.com,https://api.yourapp.com
```

---

## Logging

| Variable | Default | Description |
|---|---|---|
| `LOG_CHANNEL` | `stack` | `stack`, `single`, `daily`, `stderr`, `syslog` |
| `LOG_LEVEL` | `debug` | `debug`, `info`, `notice`, `warning`, `error`, `critical` |
| `LOG_STACK` | `single` | Sub-channels when using `stack` |

---

## Production Hardening

Minimum changes needed before going to production:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

DB_PASSWORD=<strong-random-password>
REDIS_PASSWORD=<strong-random-password>

REVERB_APP_KEY=<random-32-char-string>
REVERB_APP_SECRET=<random-32-char-string>
REVERB_SCHEME=https

SESSION_SECURE_COOKIE=true

MAIL_MAILER=smtp   # or ses, mailgun, postmark
```

After updating env for production, optimize the application:

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```
