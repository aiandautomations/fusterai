<p align="center">
  <img src="public/logo.svg" alt="FusterAI Logo" width="72" />
</p>

<h1 align="center">FusterAI</h1>

<p align="center">
  <strong>AI-First Support Agent — Built for 2026</strong><br />
  Self-hosted, open-source customer support platform with AI reply suggestions, auto-categorization,<br />
  RAG knowledge base, live chat, automation rules, REST API, and MCP server.
</p>

<p align="center">
  <a href="#-quick-start-docker"><img src="https://img.shields.io/badge/Laravel_Sail-ready-FF2D20?logo=docker&logoColor=white" alt="Laravel Sail" /></a>
  <a href="#-tech-stack"><img src="https://img.shields.io/badge/Laravel-12-FF2D20?logo=laravel&logoColor=white" alt="Laravel 12" /></a>
  <a href="#-tech-stack"><img src="https://img.shields.io/badge/PHP-8.4-777BB4?logo=php&logoColor=white" alt="PHP 8.4" /></a>
  <a href="#-tech-stack"><img src="https://img.shields.io/badge/React-19-61DAFB?logo=react&logoColor=black" alt="React 19" /></a>
  <a href="#-tech-stack"><img src="https://img.shields.io/badge/AI-Ready-6B21A8" alt="AI Ready" /></a>
  <a href="LICENSE"><img src="https://img.shields.io/badge/license-MIT-22c55e" alt="MIT License" /></a>
  <a href="#"><img src="https://img.shields.io/badge/version-1.0.0-0ea5e9" alt="Version 1.0.0" /></a>
</p>

---

## Table of Contents

- [Overview](#-overview)
- [Features](#-features)
- [Tech Stack](#-tech-stack)
- [Quick Start — Laravel Sail](#-quick-start--laravel-sail)
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

## 🌟 Overview

**FusterAI** is a fully self-hosted, open-source customer support platform built AI-first for 2026. Every conversation benefits from automatic reply suggestions, smart categorization, summarization, and semantic knowledge base search — all running on your own infrastructure.

> **Why FusterAI?**
> - 🔒 **Self-hosted** — your customer data never leaves your infrastructure
> - 🤖 **AI-native** — AI baked into every workflow, not bolted on
> - 🔌 **Extensible** — module/plugin system with hooks and filters
> - ⚡ **Real-time** — WebSocket-powered live updates via Laravel Reverb
> - 📦 **One-command setup** — `sail up` and you're running in minutes

---

## ✨ Features

### Core Helpdesk
- 📬 **Multi-mailbox** — Connect unlimited email inboxes per workspace
- 💬 **Conversation management** — Assign, tag, snooze, merge, prioritize
- 📝 **Rich text replies** — Tiptap editor with formatting, attachments, canned responses
- 🗒️ **Internal notes** — Private team notes on any conversation
- 👥 **Team collaboration** — Collision detection, assignment, follower notifications
- 🏷️ **Tags & priorities** — Low / Normal / High / Urgent with auto-categorization
- ⏰ **Snooze** — Revisit conversations at a specific time
- 🔀 **Merge conversations** — Combine duplicate tickets
- 📎 **Attachments** — Local disk or S3-compatible storage

### AI Features
- 💡 **Reply suggestions** — Context-aware drafts using your knowledge base (RAG)
- 🏷️ **Auto-categorization** — Tags and priority set automatically on new tickets
- 📋 **Summarization** — One-click conversation summaries
- 🧠 **Knowledge base** — Import docs, FAQs, and policies; AI searches them via pgvector
- 🔧 **MCP Server** — Expose helpdesk tools to any AI agent or assistant

### Communication Channels
- 📧 **Email** — IMAP fetch + per-mailbox SMTP send with signatures
- 💬 **Live Chat** — Embeddable widget + real-time agent console
- 🌐 **REST API** — Full CRUD + reply API with Bearer auth
- 🪝 **Webhooks** — Inbound webhook processing for any platform

### Automation & Productivity
- ⚡ **Automation rules** — Trigger → Condition → Action workflows
- 📣 **Canned responses** — Insert saved replies with `/` shortcut in the editor
- 🔔 **Notifications** — In-app and email notifications for assignments and replies
- 📊 **Reports** — Conversation trends, agent performance, resolution time
- 🔍 **Full-text search** — MeiliSearch-powered global search

### Developer Experience
- 🔌 **Module system** — Hook/filter architecture for custom extensions
- 📖 **Auto-generated API docs** — Scramble-powered OpenAPI at `/docs/api`
- 🧪 **64+ tests** — Pest 4 test suite with feature and unit coverage
- 🔭 **Horizon dashboard** — Queue monitoring at `/horizon`
- 📋 **Activity logs** — Full audit trail (Spatie Activity Log)

---

## 🛠️ Tech Stack

| Layer | Technology |
|---|---|
| **Backend** | Laravel 12 + PHP 8.4 |
| **Frontend** | React 19 + Inertia.js |
| **UI** | shadcn/ui + Tailwind CSS v4 |
| **Rich Text Editor** | Tiptap 3 |
| **Database** | PostgreSQL 17 + pgvector |
| **Cache / Queue** | Redis 7 |
| **Queue Monitor** | Laravel Horizon |
| **WebSockets** | Laravel Reverb (self-hosted) |
| **AI** | laravel/ai (Anthropic, OpenAI, or any compatible provider) |
| **MCP** | Laravel MCP (Model Context Protocol server) |
| **Search** | MeiliSearch + Laravel Scout |
| **Auth / OAuth** | Laravel Passport (OAuth 2.1) |
| **Permissions** | Spatie Laravel Permission |
| **Storage** | Laravel Filesystem (local / S3-compatible) |
| **Testing** | Pest 4 |

---

## 🚀 Quick Start — Laravel Sail

> **Prerequisites:** [Docker Desktop](https://docs.docker.com/get-docker/) (includes Docker Compose v2+)

### 1. Clone

```bash
git clone https://github.com/your-org/fusterai.git
cd fusterai
```

### 2. Configure

```bash
cp .env.example .env
```

Edit `.env` and set at minimum:

```env
APP_KEY=                          # Generated in step 3
ANTHROPIC_API_KEY=sk-ant-...      # Optional — enables AI features
DB_PASSWORD=change_me_please
```

### 3. Install & Launch

```bash
# Install PHP dependencies (using a throwaway container — no local PHP needed)
docker run --rm -u "$(id -u):$(id -g)" \
    -v "$(pwd):/var/www/html" \
    -w /var/www/html \
    laravelsail/php84-composer:latest \
    composer install --ignore-platform-reqs

# Generate app key
./vendor/bin/sail artisan key:generate

# Add alias (optional but recommended)
alias sail='./vendor/bin/sail'

# Start everything
sail up -d --build
```

Sail automatically:
1. Builds the PHP 8.4 application image (using the official `laravelsail/php84-composer` base)
2. Starts PostgreSQL 17 with pgvector extension
3. Starts Redis 7
4. Starts MeiliSearch for full-text search
5. Starts Mailpit for local email testing
6. Runs `npm ci` + `npm run build` to compile frontend assets
7. Runs migrations, caches config/routes, and starts the web + WebSocket servers

> **First boot takes ~2 minutes** while npm installs and builds the frontend. Check progress with `sail logs laravel.test -f`.

### 4. Access

Once all services are running, open these URLs in your browser:

| Service | URL | What it is |
|---|---|---|
| **FusterAI App** | http://localhost:8000 | Main helpdesk UI |
| **Horizon** | http://localhost:8000/horizon | Queue & job monitor |
| **API Docs** | http://localhost:8000/docs/api | Auto-generated OpenAPI docs |
| **Mailpit** | http://localhost:8025 | Catch-all email UI (dev only) |
| **MeiliSearch** | http://localhost:7700 | Search dashboard |
| **Reverb (WebSockets)** | ws://localhost:8080 | Internal — no browser UI |
| **PostgreSQL** | localhost:5432 | Connect via any DB client |
| **Redis** | localhost:6379 | Connect via redis-cli |

> **Custom port?** Set `APP_PORT=80` in your `.env` to access the app at http://localhost (no port number). The default is `8000`.

> **Check everything is up:**
> ```bash
> ./vendor/bin/sail ps
> ```

### 5. Create first admin user

```bash
sail artisan tinker
```

```php
use App\Models\{User, Workspace};

$ws   = Workspace::create(['name' => 'Acme Corp', 'slug' => 'acme']);
$user = User::create([
    'name'         => 'Admin',
    'email'        => 'admin@acme.com',
    'password'     => bcrypt('password'),
    'workspace_id' => $ws->id,
    'role'         => 'admin',
]);
```

### Common Sail Commands

```bash
# Start / stop
sail up -d              # Start in background
sail down               # Stop all containers
sail down -v            # Stop and delete all data volumes

# Artisan & Composer
sail artisan migrate
sail artisan tinker
sail composer require foo/bar

# Frontend
sail npm run dev        # Vite HMR (dev)
sail npm run build      # Production build

# Logs
sail logs               # All services
sail logs laravel.test  # App only
sail logs horizon       # Queue workers

# Rebuild after Dockerfile changes
sail build --no-cache
sail up -d
```

---

## 💻 Manual Installation

### Prerequisites

- PHP **8.4+** with extensions: `pdo_pgsql`, `redis`, `pcntl`, `bcmath`, `gd`, `xml`, `sockets`
- **PostgreSQL 15+** with [pgvector](https://github.com/pgvector/pgvector) extension
- **Redis 7+**
- **Node.js 20+** and npm
- **Composer 2.x**

### Steps

```bash
# 1. Clone and install PHP + JS dependencies
git clone https://github.com/your-org/fusterai.git
cd fusterai
composer install
npm install

# 2. Environment
cp .env.example .env
php artisan key:generate

# 3. Database — enable pgvector then migrate
psql -U postgres -c "CREATE DATABASE fusterai;"
psql -U postgres -d fusterai -c "CREATE EXTENSION vector;"
php artisan migrate

# 4. Build frontend assets
npm run build

# 5. Storage symlink
php artisan storage:link
```

### Start Development Services

```bash
# Option A — all-in-one (requires concurrently)
composer run dev

# Option B — separate terminals
php artisan serve           # Web server    → http://localhost:8000
php artisan horizon         # Queue workers
php artisan reverb:start    # WebSockets    → ws://localhost:8080
npm run dev                 # Vite HMR
```

---

## ⚙️ Configuration

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
# Anthropic
ANTHROPIC_API_KEY=sk-ant-...

# OpenAI
# OPENAI_API_KEY=sk-...

# Any OpenAI-compatible provider (Ollama, OpenRouter, etc.)
# OPENAI_COMPATIBLE_BASE_URL=http://localhost:11434/v1
# OPENAI_COMPATIBLE_API_KEY=

# Feature flags — all default to true when an API key is configured
AI_REPLY_SUGGESTIONS=true
AI_AUTO_CATEGORIZATION=true
AI_SUMMARIZATION=true
AI_RAG=true
```

> **Note:** AI features gracefully degrade when no API key is set. The helpdesk works fully without it.

### Search

```env
# 'database' works out of the box (no extra setup)
# 'meilisearch' recommended for production
SCOUT_DRIVER=meilisearch
MEILISEARCH_HOST=http://localhost:7700
MEILISEARCH_KEY=your-master-key

# After configuring, index existing conversations:
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

## 🏗️ Architecture

### Queue Worker Pools (Horizon)

| Pool | Queue | Workers | Purpose |
|---|---|---|---|
| `email-inbound` | email-inbound | 3–6 | IMAP fetch + inbound email processing |
| `email-outbound` | email-outbound | 5–10 | SMTP send, auto-replies, 168 retries |
| `ai` | ai | 4–8 | Reply suggestions, categorization, KB indexing |
| `notifications` | notifications | 2–4 | In-app + email notifications |
| `webhooks` | webhooks | 3–6 | Inbound webhook processing |
| `default` | default | 2–4 | General background jobs |

### Real-time Events (Reverb)

| Event | Trigger | Frontend Effect |
|---|---|---|
| `ConversationUpdated` | Any status/assign change | Refreshes list + view |
| `NewThreadReceived` | New message | Appends message in real-time |
| `AiSuggestionReady` | AI finishes generating | Populates AI assist panel |
| `AgentTyping` | Agent opens reply box | Shows "X is viewing" indicator |
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
activity_logs       — Full audit trail
```

---

## 🤖 AI Features

### Reply Suggestions (RAG)

1. Customer sends a message → `GenerateReplySuggestionJob` is dispatched
2. Semantic search on your knowledge base (pgvector cosine similarity)
3. Top-k relevant document chunks injected into Claude's system prompt
4. Claude generates a context-aware draft reply
5. Response streamed to the AI Assist panel via Reverb WebSocket

### MCP Server

FusterAI exposes a [Model Context Protocol](https://modelcontextprotocol.io) server so AI agents can interact with your helpdesk:

**Available tools:**

| Tool | Description |
|---|---|
| `get_conversation(id)` | Full conversation with all threads |
| `search_conversations(query)` | Semantic search across tickets |
| `get_customer_history(email)` | All past tickets for a customer |
| `search_knowledge_base(query)` | RAG search across knowledge bases |
| `create_note(conv_id, text)` | Add an internal note |
| `assign_conversation(id, user)` | Assign to an agent |

**Connect from any MCP-compatible client:**

```json
{
  "mcpServers": {
    "fusterai": {
      "url": "http://localhost:8000/mcp",
      "headers": { "Authorization": "Bearer your-pat" }
    }
  }
}
```

---

## 📡 Communication Channels

### Email

- Per-mailbox IMAP configuration (encrypted at rest)
- Scheduled fetch every minute via `php artisan fetch:emails`
- Thread matching via `In-Reply-To` email headers
- Per-mailbox dynamic SMTP transport
- Auto-reply on new conversations

### Live Chat Widget

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

Full REST API with Bearer token authentication. Docs at `/docs/api`.

```bash
# Create a conversation via API
curl -X POST http://localhost:8000/api/conversations \
  -H "Authorization: Bearer your-token" \
  -H "Content-Type: application/json" \
  -d '{"subject":"Help needed","customer_email":"user@example.com","body":"Hello!"}'
```

---

## 🔌 Module System

Extend FusterAI without modifying core code:

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
    // send Slack notification, etc.
});

// Modify AI system prompt
Hook::filter('ai.system_prompt', function (string $prompt, Conversation $conv) {
    return $prompt . "\n\nAlways respond in a friendly, professional tone.";
});
```

**React Slot System:**

```tsx
// Render module UI in designated slots
<SlotRenderer name="conversation.sidebar.bottom" props={{ conversation }} />
```

---

## 📖 API Reference

Auto-generated OpenAPI docs are available at **`/docs/api`** when running the application.

**Authentication:** `Authorization: Bearer <personal-access-token>`

**Key endpoints:**

```
GET    /api/conversations              List conversations (paginated)
POST   /api/conversations              Create conversation
GET    /api/conversations/{id}         Get conversation with threads
PATCH  /api/conversations/{id}         Update status / priority / assignment
POST   /api/conversations/{id}/reply   Send a reply

GET    /api/customers                  List customers
GET    /api/customers/{id}             Customer with conversation history

POST   /api/webhooks/inbound           Process inbound webhook message
```

---

## 🧑‍💻 Development

### Running Tests

```bash
composer test                           # Run all tests
composer test:coverage                  # With HTML coverage report
vendor/bin/pest tests/Feature/          # Feature tests only
vendor/bin/pest --filter="conversation" # Filter by name
```

### Code Quality

```bash
vendor/bin/pint             # Format PHP code
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
php artisan schedule:work           # Start the scheduler (runs fetch every minute)
```

### Re-index Search

```bash
php artisan scout:flush "App\Domains\Conversation\Models\Conversation"
php artisan scout:import "App\Domains\Conversation\Models\Conversation"
```

---

## 🚢 Deployment

### Production Checklist

- [ ] `APP_ENV=production`, `APP_DEBUG=false`
- [ ] Strong `APP_KEY`, `DB_PASSWORD`, and Redis `REQUIREPASS`
- [ ] `SESSION_SECURE_COOKIE=true` with HTTPS
- [ ] Real SMTP provider (not `log` driver)
- [ ] `FILESYSTEM_DISK=s3` for attachment storage
- [ ] `SCOUT_DRIVER=meilisearch` for full-text search
- [ ] Secure random `REVERB_APP_KEY` and `REVERB_APP_SECRET`
- [ ] Reverse proxy (Nginx/Caddy) in front of app + Reverb
- [ ] SSL/TLS certificates

### Nginx — WebSocket Proxy

```nginx
# WebSocket proxy for Reverb
location /app {
    proxy_pass         http://localhost:8080;
    proxy_http_version 1.1;
    proxy_set_header   Upgrade $http_upgrade;
    proxy_set_header   Connection "Upgrade";
    proxy_set_header   Host $host;
}
```

### Cache Assets for Production

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
npm run build
```

### Scale Horizon Workers

Edit `config/horizon.php` to tune workers per pool:

```php
'production' => [
    'email-outbound' => ['processes' => 10, 'tries' => 168],
    'ai'             => ['processes' => 8,  'timeout' => 120],
    // ...
],
```

---

## 📚 Documentation

Detailed guides are in the [`docs/`](docs/) folder:

| Guide | Description |
|---|---|
| [Installation](docs/installation.md) | Docker and manual setup, step-by-step |
| [Getting Started](docs/getting-started.md) | First mailbox, first conversation, team setup |
| [Configuration](docs/configuration.md) | All environment variables explained |
| [AI Setup](docs/ai-setup.md) | Anthropic API, knowledge base RAG, MCP server |
| [Module Development](docs/modules.md) | Build custom plugins with the hook/filter system |

---

## 🤝 Contributing

We welcome contributions of all kinds!

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
- [ ] Frontend slot system for module UI
- [ ] Example modules: SatisfactionSurvey, SlaManager
- [ ] Multi-language / i18n support
- [ ] Two-factor authentication (TOTP)
- [ ] Customer portal (self-service ticket status)

---

## 📄 License

FusterAI is open-source software licensed under the **[MIT License](LICENSE)**.

---

## 🙏 Acknowledgements

FusterAI is built on the shoulders of giants:

[Laravel](https://laravel.com) · [Inertia.js](https://inertiajs.com) · [React](https://react.dev) · [shadcn/ui](https://ui.shadcn.com) · [Tiptap](https://tiptap.dev) · [Spatie](https://spatie.be) · [MeiliSearch](https://meilisearch.com) · [pgvector](https://github.com/pgvector/pgvector) · [Laravel Horizon](https://laravel.com/docs/horizon) · [Laravel Reverb](https://laravel.com/docs/reverb)

---

<p align="center">Built with ❤️ — Star ⭐ the repo if FusterAI helps your team!</p>
