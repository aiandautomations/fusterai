# FusterAI — AI-First Customer Support Platform

> Self-hosted, open-source helpdesk built for 2026. AI reply suggestions, RAG knowledge base, live chat, automation rules, MCP server — all on your own infrastructure.

<p align="center">
  <a href="#-quick-start-docker"><img src="https://img.shields.io/badge/Laravel_Sail-ready-FF2D20?logo=docker&logoColor=white" alt="Laravel Sail" /></a>
  <a href="#-tech-stack"><img src="https://img.shields.io/badge/Laravel-12-FF2D20?logo=laravel&logoColor=white" alt="Laravel 12" /></a>
  <a href="#-tech-stack"><img src="https://img.shields.io/badge/PHP-8.4-777BB4?logo=php&logoColor=white" alt="PHP 8.4" /></a>
  <a href="#-tech-stack"><img src="https://img.shields.io/badge/React-19-61DAFB?logo=react&logoColor=black" alt="React 19" /></a>
  <a href="#-tech-stack"><img src="https://img.shields.io/badge/AI-Claude%20%2F%20OpenAI-6B21A8" alt="AI Ready" /></a>
  <a href="LICENSE"><img src="https://img.shields.io/badge/license-MIT-22c55e" alt="MIT License" /></a>
  <a href="#"><img src="https://img.shields.io/badge/version-1.0.0-0ea5e9" alt="Version 1.0.0" /></a>
</p>

---

## Table of Contents

- [Overview](#-overview)
- [Features](#-features)
- [Tech Stack](#-tech-stack)
- [Quick Start — Docker / Sail](#-quick-start--docker--sail)
- [Manual Installation](#-manual-installation)
- [Configuration](#-configuration)
- [Architecture](#-architecture)
- [AI Features](#-ai-features)
- [Communication Channels](#-communication-channels)
- [Module System](#-module-system)
- [API Reference](#-api-reference)
- [Development](#-development)
- [Deployment](#-deployment)
- [Documentation](#-documentation)
- [Contributing](#-contributing)
- [License](#-license)

---

## Overview

**FusterAI** is a fully self-hosted, open-source customer support platform built AI-first. Every conversation benefits from automatic reply suggestions, smart categorization, summarization, and semantic knowledge base search — all running on your own infrastructure, with no per-seat SaaS pricing.

**Why FusterAI instead of Zendesk, Freshdesk, or other open-source tools?**

| | FusterAI | FreeScout / osTicket | Chatwoot | Zendesk / Freshdesk |
|---|---|---|---|---|
| Self-hosted | ✅ | ✅ | ✅ | ❌ SaaS only |
| Your data stays on your servers | ✅ | ✅ | ✅ | ❌ |
| GDPR-ready by default | ✅ | Partial | Partial | Requires DPA |
| AI reply suggestions (RAG) | ✅ | ❌ | ❌ | Higher-tier plans |
| MCP server for AI agents | ✅ | ❌ | ❌ | ❌ |
| Real-time WebSockets | ✅ | ❌ | ✅ | ✅ |
| Modern stack (2025+) | ✅ | ❌ | Partial | ✅ |
| Module / plugin system | ✅ | ✅ | ❌ | ✅ |
| Free & open-source | ✅ | ✅ | ✅ | ❌ |

**Key differentiators:**
- **AI-native, not AI-bolted-on** — reply suggestions, auto-tagging, and summarization run on every ticket automatically
- **MCP server** — expose your helpdesk as tools to Claude Desktop, Cursor, or any AI agent
- **One-command setup** — `sail up -d` and you're running in minutes (Docker required)
- **Complete data control** — every byte of customer data lives on your own servers, always

---

## Privacy & Data Sovereignty

FusterAI is built for teams where data control is non-negotiable.

**Your data never leaves your infrastructure.**
Customer emails, support threads, attachments, AI-generated summaries, knowledge base documents — everything is stored in your own PostgreSQL database and file storage. FusterAI makes no outbound calls to any third party except the AI API you explicitly configure.

**GDPR compliance by design:**
- No vendor data processing agreement (DPA) required for the platform itself — you own and control the data store
- Encryption at rest for all mailbox credentials (IMAP/SMTP passwords encrypted with AES-256)
- Full audit trail on every action (Spatie Activity Log)
- Customer data deletion is a direct database operation — no support tickets to a vendor
- Self-hosted means you choose the hosting region: EU data stays in the EU

**On AI calls:** when AI features are enabled, conversation context is sent to your chosen AI provider (Anthropic or OpenAI). For maximum privacy, point `OPENAI_COMPATIBLE_BASE_URL` at a local [Ollama](https://ollama.com) instance and no data leaves your network at all.

---

## Features

### Core Helpdesk
- **Multi-mailbox** — connect unlimited email inboxes per workspace
- **Conversation management** — assign, tag, snooze, merge, prioritize, reopen
- **Rich text replies** — Tiptap editor with bold/italic/lists/links/attachments
- **Canned responses** — insert saved replies with `/` shortcut while typing
- **Internal notes** — private team notes on any conversation
- **Team collaboration** — collision detection ("X is viewing"), assignments, follower notifications
- **Tags & priorities** — Low / Normal / High / Urgent with AI auto-categorization
- **Snooze** — resurface conversations at a specific time
- **Merge conversations** — combine duplicate tickets
- **Attachments** — local disk or S3-compatible object storage

### AI Features
- **Reply suggestions (RAG)** — Claude searches your knowledge base and drafts a context-aware reply for every new ticket
- **Auto-categorization** — tags and priority set automatically when a ticket arrives
- **Summarization** — one-click conversation summaries
- **Knowledge base** — import docs, FAQs, and policies; AI searches via pgvector embeddings
- **MCP server** — expose helpdesk tools to any AI agent or assistant (Claude Desktop, Cursor, etc.)
- **Provider-agnostic** — Anthropic Claude, OpenAI, or any OpenAI-compatible API (Ollama, OpenRouter)

### Communication Channels
- **Email** — IMAP fetch + per-mailbox SMTP send with signatures and auto-reply
- **Live chat** — embeddable JavaScript widget + real-time agent console
- **REST API** — full CRUD + reply endpoints with Bearer token auth
- **Inbound webhooks** — process messages from any platform

### Automation & Productivity
- **Automation rules** — trigger → condition → action workflows (e.g. auto-assign urgent tickets)
- **SLA management** — SLA policies, breach notifications, pause/resume tracking
- **In-app notifications** — assignments, replies, @mentions
- **Email digests** — daily summary emails for agents
- **Reports** — conversation trends, agent performance, resolution time, channel breakdowns
- **Full-text search** — MeiliSearch-powered global search across all conversations

### Developer Experience
- **Module system** — hook/filter/slot architecture for custom extensions without forking core
- **Auto-generated API docs** — Scramble OpenAPI docs at `/docs/api`
- **64+ tests** — Pest 4 test suite with feature and unit coverage
- **GitHub Actions CI/CD** — automated tests on PHP 8.2 and 8.3
- **Horizon dashboard** — queue monitoring at `/horizon`
- **Activity logs** — full audit trail via Spatie Activity Log
- **PHPStan level 5** — static analysis on CI

---

## Tech Stack

| Layer | Technology |
|---|---|
| **Backend** | Laravel 12 + PHP 8.4 |
| **Frontend** | React 19 + Inertia.js |
| **UI** | shadcn/ui + Tailwind CSS v4 |
| **Rich Text Editor** | Tiptap 3 |
| **Database** | PostgreSQL 17 + pgvector (1536-dim embeddings) |
| **Cache / Queue** | Redis 7 |
| **Queue Monitor** | Laravel Horizon |
| **WebSockets** | Laravel Reverb (self-hosted) |
| **AI** | laravel/ai — Anthropic Claude, OpenAI, or compatible providers |
| **MCP** | Laravel MCP (Model Context Protocol server) |
| **Search** | MeiliSearch + Laravel Scout |
| **Auth / OAuth** | Laravel Passport (OAuth 2.1) |
| **Permissions** | Spatie Laravel Permission |
| **Storage** | Laravel Filesystem (local / S3-compatible) |
| **Testing** | Pest 4 |

---

## Quick Start

Choose the setup path that suits you:

| | [GitHub Codespaces](#-option-a--github-codespaces-zero-install) | [Docker / Sail](#-option-b--docker--sail) | [Manual](#-option-c--manual-installation) |
|---|---|---|---|
| Local install needed | None | Docker Desktop | PHP, Node, PostgreSQL, Redis |
| Time to running | ~3 min | ~3 min | ~5 min |
| Best for | Trying it out / contributing | Local dev | Custom server setups |

---

### Option A — GitHub Codespaces (zero install)

Click **Code → Codespaces → Create codespace** on the GitHub repo page.

Codespaces builds the full stack (PHP 8.4, PostgreSQL, Redis, MeiliSearch) and runs the setup wizard automatically. When the terminal shows **✓ FusterAI is ready!**, open the forwarded port `8000` in your browser.

**Demo login:** `admin@fusterai.com` / `password`

> To use AI features, add `ANTHROPIC_API_KEY` in the Codespace's **Secrets** settings before creating the codespace.

---

### Option B — Docker / Sail

> **Prerequisite:** [Docker Desktop](https://docs.docker.com/get-docker/) — no local PHP, Node, or PostgreSQL needed.

```bash
# 1. Clone
git clone https://github.com/your-org/fusterai.git
cd fusterai

# 2. Install PHP dependencies (throwaway container — no local PHP needed)
docker run --rm -u "$(id -u):$(id -g)" \
    -v "$(pwd):/var/www/html" \
    -w /var/www/html \
    laravelsail/php84-composer:latest \
    composer install --ignore-platform-reqs

# 3. Configure
cp .env.example .env
./vendor/bin/sail artisan key:generate

# 4. Start everything
./vendor/bin/sail up -d --build
```

Sail starts PostgreSQL 17 (with pgvector), Redis, MeiliSearch, and Mailpit, then runs migrations, builds the frontend, and starts the web + WebSocket servers automatically.

> **First boot takes ~2 minutes.** Watch progress: `./vendor/bin/sail logs laravel.test -f`

**5. Run the setup wizard:**

```bash
# Interactive — creates your workspace and admin account
./vendor/bin/sail artisan fusterai:install

# Or load demo data instead (4 agents, 16 customers, 35 conversations)
./vendor/bin/sail artisan fusterai:install --demo
```

**Demo login:** `admin@fusterai.com` / `password`

**6. Open the app:**

| Service | URL |
|---|---|
| **FusterAI** | http://localhost:8000 |
| **Horizon** (queue monitor) | http://localhost:8000/horizon |
| **API Docs** | http://localhost:8000/docs/api |
| **Mailpit** (dev email) | http://localhost:8025 |
| **MeiliSearch** | http://localhost:7700 |

> Set `APP_PORT=80` in `.env` to access the app at http://localhost.

**Common Sail commands:**

```bash
sail up -d              # Start in background
sail down               # Stop all containers
sail down -v            # Stop and delete all data volumes
sail artisan tinker
sail composer require foo/bar
sail npm run dev        # Vite HMR
sail logs laravel.test  # App logs
sail build --no-cache && sail up -d   # Rebuild after Dockerfile changes
```

---

### Option C — Manual Installation

**Prerequisites:** PHP 8.4+ (`pdo_pgsql`, `redis`, `pcntl`, `bcmath`, `gd`, `xml`, `sockets`), PostgreSQL 15+ with [pgvector](https://github.com/pgvector/pgvector), Redis 7+, Node.js 20+, Composer 2.x.

```bash
# 1. Clone and install
git clone https://github.com/your-org/fusterai.git
cd fusterai
composer install && npm install

# 2. Environment
cp .env.example .env
php artisan key:generate

# 3. Database
psql -U postgres -c "CREATE DATABASE fusterai;"
psql -U postgres -d fusterai -c "CREATE EXTENSION vector;"

# 4. Run the setup wizard (handles migrations, OAuth keys, storage, and first user)
php artisan fusterai:install

# 5. Build frontend
npm run build
```

**Start development services:**

```bash
# All-in-one (uses Horizon, Reverb, Vite, and Pail concurrently)
composer run dev

# Or separate terminals
php artisan serve           # → http://localhost:8000
php artisan horizon         # Queue workers
php artisan reverb:start    # WebSockets → ws://localhost:8080
npm run dev                 # Vite HMR
```

---

## Configuration

### Core Variables

```env
APP_KEY=base64:...            # php artisan key:generate
APP_URL=http://localhost

DB_HOST=127.0.0.1
DB_DATABASE=fusterai
DB_USERNAME=postgres
DB_PASSWORD=secret

REDIS_HOST=127.0.0.1

REVERB_APP_ID=fusterai
REVERB_APP_KEY=your-key
REVERB_APP_SECRET=your-secret
```

### AI Configuration

```env
# Anthropic Claude (recommended)
ANTHROPIC_API_KEY=sk-ant-...

# OpenAI
# OPENAI_API_KEY=sk-...

# Any OpenAI-compatible provider (Ollama, OpenRouter, LM Studio, etc.)
# OPENAI_COMPATIBLE_BASE_URL=http://localhost:11434/v1
# OPENAI_COMPATIBLE_API_KEY=

# Feature flags — all default to true when an API key is set
AI_REPLY_SUGGESTIONS=true
AI_AUTO_CATEGORIZATION=true
AI_SUMMARIZATION=true
AI_RAG=true
```

> **No API key?** AI features degrade gracefully. The helpdesk works fully without one.

### Search

```env
# 'database' works out of the box (no extra setup)
# 'meilisearch' is recommended for production
SCOUT_DRIVER=meilisearch
MEILISEARCH_HOST=http://localhost:7700
MEILISEARCH_KEY=your-master-key

# After configuring, import existing conversations:
# php artisan scout:import "App\Domains\Conversation\Models\Conversation"
```

### File Storage (S3-Compatible)

```env
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=...
AWS_SECRET_ACCESS_KEY=...
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=fusterai-files

# Works with MinIO, Backblaze B2, Cloudflare R2, etc.
AWS_ENDPOINT=https://your-endpoint
AWS_USE_PATH_STYLE_ENDPOINT=true
```

---

## Architecture

### Queue Worker Pools (Horizon)

| Pool | Queue | Workers | Purpose |
|---|---|---|---|
| `email-inbound` | email-inbound | 3–6 | IMAP fetch + inbound email processing |
| `email-outbound` | email-outbound | 5–10 | SMTP send, auto-replies, retry on failure |
| `ai` | ai | 4–8 | Reply suggestions, categorization, KB indexing |
| `notifications` | notifications | 2–4 | In-app + email notifications |
| `webhooks` | webhooks | 3–6 | Inbound webhook processing |
| `default` | default | 2–4 | General background jobs |

### Real-time Events (Reverb WebSockets)

| Event | Trigger | Effect |
|---|---|---|
| `ConversationUpdated` | Status / assign change | Refreshes list and conversation view |
| `NewThreadReceived` | New message arrives | Appends message in real-time |
| `AiSuggestionReady` | AI finishes generating | Populates AI assist panel |
| `AgentTyping` | Agent opens reply box | Shows "X is viewing" collision indicator |
| `LiveChatMessage` | Widget message | Chat widget ↔ agent console |

### Database Tables

```
workspaces          — Multi-tenant isolation
users               — Agents (workspace_id, role)
mailboxes           — Email config (IMAP/SMTP encrypted via AES-256)
customers           — Contact records
conversations       — Tickets with status, priority, AI fields
threads             — Messages, notes, activities, AI suggestions
attachments         — File uploads
tags                — Workspace-scoped labels
canned_responses    — Saved reply templates
knowledge_bases     — Document collections
kb_documents        — Documents + pgvector embeddings (1536-dim)
automation_rules    — Trigger → Condition → Action
sla_policies        — SLA rules with pause/resume tracking
activity_logs       — Full audit trail
```

---

## AI Features

### Reply Suggestions (RAG Pipeline)

1. Customer sends a message → `GenerateReplySuggestionJob` dispatched
2. Semantic search on your knowledge base (pgvector cosine similarity)
3. Top-k relevant document chunks injected into Claude's system prompt
4. Claude generates a context-aware draft reply
5. Response streamed to the AI Assist panel via Reverb WebSocket

### MCP Server

FusterAI exposes a [Model Context Protocol](https://modelcontextprotocol.io) server so AI agents can interact with your helpdesk directly.

**Available tools:**

| Tool | Description |
|---|---|
| `get_conversation(id)` | Full conversation with all threads |
| `search_conversations(query)` | Semantic search across tickets |
| `get_customer_history(email)` | All past tickets for a customer |
| `search_knowledge_base(query)` | RAG search across knowledge bases |
| `create_note(conv_id, text)` | Add an internal note |
| `assign_conversation(id, user)` | Assign to an agent |

**Connect from Claude Desktop, Cursor, or any MCP client:**

```json
{
  "mcpServers": {
    "fusterai": {
      "url": "http://localhost:8000/mcp",
      "headers": { "Authorization": "Bearer your-personal-access-token" }
    }
  }
}
```

---

## Communication Channels

### Email

- Per-mailbox IMAP configuration (encrypted at rest with AES-256)
- Scheduled fetch every minute via `php artisan fetch:emails`
- Thread matching via `In-Reply-To` email headers (no duplicate conversations)
- Per-mailbox dynamic SMTP transport with signatures
- Auto-reply on new conversations

### Live Chat Widget

Embed on any website with two lines of HTML:

```html
<script>
  window.FusterAIConfig = {
    workspaceId: 'your-workspace-id',
    serverUrl: 'https://your-fusterai.com'
  };
</script>
<script src="https://your-fusterai.com/livechat/widget.js" async></script>
```

### REST API

Full REST API with Bearer token authentication. Interactive docs at `/docs/api`.

```bash
# Create a conversation via API
curl -X POST http://localhost:8000/api/conversations \
  -H "Authorization: Bearer your-token" \
  -H "Content-Type: application/json" \
  -d '{"subject":"Help needed","customer_email":"user@example.com","body":"Hello!"}'
```

### Inbound Webhooks

Accept messages from any platform (Twilio, Stripe, custom systems) via `POST /api/webhooks/inbound` with a workspace webhook token.

---

## Module System

Extend FusterAI without touching core code. Drop a module in `Modules/` and it auto-loads.

```
Modules/MyModule/
├── module.json
├── Providers/MyModuleServiceProvider.php
└── Http/Controllers/
```

**PHP Hooks:**

```php
// React to events
Hook::listen('conversation.created', function (Conversation $conv) {
    // send Slack notification, call external API, etc.
});

// Modify AI system prompt per-conversation
Hook::filter('ai.system_prompt', function (string $prompt, Conversation $conv) {
    return $prompt . "\n\nAlways respond in the customer's language.";
});
```

**React Slot System:**

```tsx
// Render module UI in designated sidebar/header slots
<SlotRenderer name="conversation.sidebar.bottom" props={{ conversation }} />
```

**Included example modules:**
- `SatisfactionSurvey` — CSAT survey on conversation close
- `SlaManager` — SLA policies, breach alerts, pause/resume tracking
- `ConversationRouting` — round-robin and least-loaded auto-assignment

---

## API Reference

Auto-generated OpenAPI docs at **`/docs/api`**.

**Authentication:** `Authorization: Bearer <personal-access-token>`

**Key endpoints:**

```
GET    /api/conversations              List conversations (paginated, filterable)
POST   /api/conversations              Create conversation
GET    /api/conversations/{id}         Get conversation with threads
PATCH  /api/conversations/{id}         Update status / priority / assignment
POST   /api/conversations/{id}/reply   Send a reply

GET    /api/customers                  List customers
GET    /api/customers/{id}             Customer with conversation history

POST   /api/webhooks/inbound           Process inbound webhook message
```

---

## Development

### Running Tests

```bash
composer test                           # Run all tests
composer test:coverage                  # With HTML coverage report
vendor/bin/pest tests/Feature/          # Feature tests only
vendor/bin/pest --filter="conversation" # Filter by name
```

### Code Quality

```bash
vendor/bin/pint             # Format PHP code (Laravel Pint)
vendor/bin/pint --test      # Check without fixing
composer analyse            # PHPStan static analysis (level 5)
```

### Queue Management

```bash
php artisan horizon                 # Start all queues
php artisan horizon:pause           # Pause processing
php artisan horizon:continue        # Resume processing
php artisan queue:retry all         # Retry all failed jobs
php artisan queue:flush             # Clear failed jobs
```

### Email Fetching

```bash
php artisan fetch:emails            # Manual fetch from all mailboxes
php artisan schedule:work           # Run scheduler (fetches every minute)
```

### Re-index Search

```bash
php artisan scout:flush "App\Domains\Conversation\Models\Conversation"
php artisan scout:import "App\Domains\Conversation\Models\Conversation"
```

---

## Deployment

### Production Checklist

- [ ] `APP_ENV=production`, `APP_DEBUG=false`
- [ ] Strong `APP_KEY`, `DB_PASSWORD`, and Redis `REQUIREPASS`
- [ ] `SESSION_SECURE_COOKIE=true` with HTTPS
- [ ] Real SMTP provider (not `log` driver)
- [ ] `FILESYSTEM_DISK=s3` for attachment storage
- [ ] `SCOUT_DRIVER=meilisearch` for full-text search
- [ ] Secure random `REVERB_APP_KEY` and `REVERB_APP_SECRET`
- [ ] Reverse proxy (Nginx or Caddy) in front of app + Reverb
- [ ] SSL/TLS certificates (Let's Encrypt / Certbot)

### Nginx — WebSocket Proxy

```nginx
# Proxy Reverb WebSocket connections
location /app {
    proxy_pass         http://localhost:8080;
    proxy_http_version 1.1;
    proxy_set_header   Upgrade $http_upgrade;
    proxy_set_header   Connection "Upgrade";
    proxy_set_header   Host $host;
}
```

### Cache for Production

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
npm run build
```

### Scale Horizon Workers

Edit `config/horizon.php` to tune workers per environment:

```php
'production' => [
    'email-outbound' => ['processes' => 10, 'tries' => 168],
    'ai'             => ['processes' => 8,  'timeout' => 120],
    // ...
],
```

---

## Documentation

Detailed guides in the [`docs/`](docs/) folder:

| Guide | Description |
|---|---|
| [Installation](docs/installation.md) | Docker and manual setup, step-by-step |
| [Getting Started](docs/getting-started.md) | First mailbox, first conversation, team setup |
| [Configuration](docs/configuration.md) | All environment variables explained |
| [AI Setup](docs/ai-setup.md) | Anthropic API, knowledge base RAG, MCP server |
| [Module Development](docs/modules.md) | Build custom plugins with the hook/filter system |

---

## Contributing

Contributions of all kinds are welcome.

1. **Fork** the repo and create a branch:
   ```bash
   git checkout -b feat/your-feature
   ```
2. **Write tests** for new functionality
3. **Run checks** before pushing:
   ```bash
   composer test && vendor/bin/pint && composer analyse
   ```
4. **Open a Pull Request** against `main`

### Commit Convention ([Conventional Commits](https://www.conventionalcommits.org/))

```
feat:     new feature
fix:      bug fix
docs:     documentation only
refactor: code change without feature/fix
test:     adding or updating tests
chore:    maintenance (deps, config, CI)
```

### Roadmap

- [ ] WhatsApp Business Cloud API channel
- [ ] Slack Events API + Bot channel
- [ ] SMS via Twilio
- [ ] Multi-language / i18n support
- [ ] Two-factor authentication (TOTP)
- [ ] Customer portal (self-service ticket status)

---

## License

FusterAI is open-source software licensed under the **[MIT License](LICENSE)**.

---

## Acknowledgements

FusterAI is built on the shoulders of giants:

[Laravel](https://laravel.com) · [Inertia.js](https://inertiajs.com) · [React](https://react.dev) · [shadcn/ui](https://ui.shadcn.com) · [Tiptap](https://tiptap.dev) · [Spatie](https://spatie.be) · [MeiliSearch](https://meilisearch.com) · [pgvector](https://github.com/pgvector/pgvector) · [Laravel Horizon](https://laravel.com/docs/horizon) · [Laravel Reverb](https://laravel.com/docs/reverb) · [Anthropic](https://anthropic.com)

---

<p align="center">Star ⭐ the repo if FusterAI helps your team.</p>
