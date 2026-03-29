# Installation Guide

This guide covers both Laravel Sail (recommended) and manual installation of FusterAI.

---

## Prerequisites

### Laravel Sail (Docker)
- [Docker Desktop](https://docs.docker.com/get-docker/) 24+ (includes Docker Compose v2+)
- 2 GB RAM minimum (4 GB recommended)
- No local PHP, Node.js, or PostgreSQL required

### Manual Installation
- **PHP 8.4+** with extensions: `pdo_pgsql`, `redis`, `pcntl`, `bcmath`, `gd`, `xml`, `sockets`
- **PostgreSQL 15+** with [pgvector](https://github.com/pgvector/pgvector) extension
- **Redis 7+**
- **Node.js 20+** and npm
- **Composer 2.x**

---

## Option 1 — Laravel Sail (Recommended)

Laravel Sail is FusterAI's Docker-based development environment. All dependencies are containerized and the official `laravelsail/php84-composer` image has every required PHP extension pre-installed — no compilation needed.

### Step 1 — Clone the repository

```bash
git clone https://github.com/your-org/fusterai.git
cd fusterai
```

### Step 2 — Install PHP dependencies

No local PHP needed — use a throwaway container:

```bash
docker run --rm -u "$(id -u):$(id -g)" \
    -v "$(pwd):/var/www/html" \
    -w /var/www/html \
    laravelsail/php84-composer:latest \
    composer install --ignore-platform-reqs
```

### Step 3 — Configure environment

```bash
cp .env.example .env
./vendor/bin/sail artisan key:generate
```

Open `.env` and set the required values:

```env
# REQUIRED — change to a strong password
DB_PASSWORD=change_me_in_production

# REQUIRED — change to random strings (used to sign WebSocket connections)
REVERB_APP_KEY=your-random-key-here
REVERB_APP_SECRET=your-random-secret-here

# OPTIONAL — enables AI features (reply suggestions, categorization, etc.)
ANTHROPIC_API_KEY=sk-ant-...

# OPTIONAL — set a master key for MeiliSearch in production
MEILISEARCH_KEY=your-meilisearch-master-key
```

### Step 4 — Start all services

```bash
./vendor/bin/sail up -d --build
```

> **Tip:** Add `alias sail='./vendor/bin/sail'` to your shell profile so you can just type `sail up`.

That's it. The container automatically:
1. Builds the PHP 8.4 + Node.js application image
2. Starts PostgreSQL 17 with pgvector, Redis, MeiliSearch, Mailpit
3. Waits for the database to be ready
4. Runs all database migrations
5. Installs frontend dependencies and builds assets
6. Caches routes and views, creates the storage symlink
7. Starts the web server on port **8000** and Reverb WebSocket on port **8080**

The first boot takes a few minutes (npm install downloads packages). Watch progress with:

```bash
sail logs laravel.test -f
```

When you see `[program:php] started`, the app is ready.

### Step 5 — Access the application

Open these in your browser:

| Service | URL | What it is |
|---|---|---|
| **FusterAI App** | http://localhost:8000 | Main helpdesk UI — start here |
| **Horizon** | http://localhost:8000/horizon | Queue monitor — check job processing |
| **API Docs** | http://localhost:8000/docs/api | Auto-generated OpenAPI documentation |
| **Mailpit** | http://localhost:8025 | Catches all outbound emails in dev |
| **MeiliSearch** | http://localhost:7700 | Search engine dashboard |
| **Reverb (WebSockets)** | ws://localhost:8080 | Internal — no browser UI |
| **PostgreSQL** | localhost:5432 | Connect with TablePlus, DBeaver, etc. |
| **Redis** | localhost:6379 | Connect with redis-cli or RedisInsight |

> **Using port 80 instead of 8000?** Set `APP_PORT=80` in your `.env` — the app will then be at http://localhost with no port number.

> **Verify all services are healthy:**
> ```bash
> sail ps
> ```
> All services should show `Up` or `Up (healthy)`. If any show `Exit`, check logs with `sail logs <service-name>`.

### Step 6 — Create your first workspace and admin user

```bash
sail artisan tinker
```

```php
use App\Models\{User, Workspace};

$workspace = Workspace::create([
    'name' => 'Acme Corp',
    'slug' => 'acme',
]);

$user = User::create([
    'name'         => 'Admin',
    'email'        => 'admin@acme.com',
    'password'     => bcrypt('your-secure-password'),
    'workspace_id' => $workspace->id,
    'role'         => 'admin',
]);

echo "Done! Login at http://localhost:8000\n";
```

Visit http://localhost:8000 and log in with the credentials you just created.

---

## Option 2 — Manual Installation

Use this if you want to run FusterAI on a server where you manage PHP, PostgreSQL, and Redis yourself.

### Step 1 — Install system dependencies

**macOS (Homebrew):**

```bash
brew install php@8.4 postgresql@17 redis node composer
brew services start postgresql@17
brew services start redis

# Install pgvector
cd /tmp && git clone --branch v0.7.0 https://github.com/pgvector/pgvector.git
cd pgvector && make && make install
```

**Ubuntu/Debian:**

```bash
# PHP 8.3
sudo add-apt-repository ppa:ondrej/php
sudo apt update
sudo apt install -y php8.4 php8.4-cli php8.4-fpm php8.4-pgsql php8.4-redis \
  php8.4-bcmath php8.4-gd php8.4-xml php8.4-curl php8.4-zip php8.4-pcntl php8.4-sockets

# PostgreSQL 17 + pgvector
sudo apt install -y postgresql-17 postgresql-17-pgvector

# Redis
sudo apt install -y redis-server

# Node.js 20
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs

# Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

### Step 2 — Create the database

```bash
sudo -u postgres psql

# Inside psql:
CREATE USER fusterai WITH PASSWORD 'your-secure-password';
CREATE DATABASE fusterai OWNER fusterai;
\c fusterai
CREATE EXTENSION vector;
\q
```

### Step 3 — Clone and install dependencies

```bash
git clone https://github.com/your-org/fusterai.git
cd fusterai

# Install PHP dependencies
composer install

# Install JavaScript dependencies
npm install
```

### Step 4 — Configure environment

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env` with your database and Redis credentials:

```env
DB_HOST=127.0.0.1
DB_DATABASE=fusterai
DB_USERNAME=fusterai
DB_PASSWORD=your-secure-password

REDIS_HOST=127.0.0.1
```

Set Reverb WebSocket keys (generate random strings):

```env
REVERB_APP_ID=fusterai
REVERB_APP_KEY=your-random-key
REVERB_APP_SECRET=your-random-secret
```

### Step 5 — Migrate and build

```bash
# Run database migrations
php artisan migrate

# Build frontend assets
npm run build

# Create storage symlink (needed to serve uploaded files)
php artisan storage:link
```

### Step 6 — Generate OAuth keys (for REST API + MCP)

```bash
php artisan passport:keys
```

### Step 7 — Start services

You need four processes running simultaneously. Use tmux, screen, or a process manager:

```bash
# Terminal 1 — Web server
php artisan serve

# Terminal 2 — Queue workers (Horizon)
php artisan horizon

# Terminal 3 — WebSockets
php artisan reverb:start

# Terminal 4 — Scheduler (email fetch, snooze, etc.)
php artisan schedule:work
```

Or run all at once with the bundled dev script:

```bash
composer run dev
```

### Step 8 — Create first workspace and user

```bash
php artisan tinker
```

```php
use App\Models\{User, Workspace};

$workspace = Workspace::create(['name' => 'Acme Corp', 'slug' => 'acme']);

User::create([
    'name'         => 'Admin',
    'email'        => 'admin@acme.com',
    'password'     => bcrypt('your-secure-password'),
    'workspace_id' => $workspace->id,
    'role'         => 'admin',
]);
```

---

## MeiliSearch Setup (Optional)

MeiliSearch provides fast full-text search. Without it, FusterAI falls back to basic database search.

### Sail
MeiliSearch is already included in `docker-compose.yml`. Set `SCOUT_DRIVER=meilisearch` in your `.env`.

### Manual install

```bash
# macOS
brew install meilisearch
meilisearch --master-key="your-master-key"

# Linux
curl -L https://install.meilisearch.com | sh
./meilisearch --master-key="your-master-key"
```

After starting MeiliSearch, index existing conversations:

```bash
php artisan scout:import "App\Domains\Conversation\Models\Conversation"
```

---

## Post-Install Checklist

- [ ] Log in and set workspace name (Settings → General)
- [ ] Configure your first mailbox (Settings → Mailboxes → Add Mailbox)
- [ ] Add AI API key if desired (Settings → AI Config)
- [ ] Create agent user accounts (Settings → Users)
- [ ] Import knowledge base documents for AI RAG (Knowledge Base → New)
- [ ] Set up automation rules (Settings → Automation)

---

## Troubleshooting

### Database connection refused

```bash
# Check PostgreSQL is running
sail ps
sail logs pgsql

# Test connection manually
sail artisan db:monitor
```

### Migrations fail with "extension vector does not exist"

The pgvector extension must be enabled before migrating:

```bash
# Sail
sail exec pgsql psql -U fusterai -d fusterai -c "CREATE EXTENSION IF NOT EXISTS vector;"

# Manual
psql -U fusterai -d fusterai -c "CREATE EXTENSION IF NOT EXISTS vector;"
```

### WebSocket connection fails

Check Reverb is running and the frontend environment variables match:

```env
REVERB_APP_KEY=same-value
VITE_REVERB_APP_KEY=same-value   # Must match REVERB_APP_KEY
VITE_REVERB_HOST=localhost
VITE_REVERB_PORT=8080
```

After changing Vite variables, rebuild the frontend: `npm run build`

### Queue jobs not processing

```bash
# Check Horizon status
php artisan horizon:status

# View failed jobs
php artisan queue:failed

# Retry all failed jobs
php artisan queue:retry all
```

### Permission errors on storage/

```bash
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache  # adjust user as needed
```

### "Vite manifest not found" error (blank page or 500)

This means the frontend assets haven't been built yet. The container builds them automatically on startup via `npm ci && npm run build`, but if that step was skipped (e.g. after a manual container restart without `--build`), run it manually:

```bash
sail exec laravel.test npm ci
sail exec laravel.test npm run build
```

Then reload the page. No restart needed.

### Old / wrong UI showing (stale build artifacts)

If you see branding or UI from a different project, you have stale `public/build/` assets. Delete them and rebuild:

```bash
rm -rf public/build
sail exec laravel.test npm run build
```

### node_modules platform mismatch on macOS

If you see an error like `Cannot find module @rollup/rollup-linux-arm64-gnu`, your `node_modules` were installed on macOS but the container runs Linux. The `docker-compose.yml` uses a named Docker volume (`sail-node-modules`) to keep a separate Linux copy, but you need to reinstall inside the container once:

```bash
sail exec laravel.test npm ci
sail exec laravel.test npm run build
```

### Artisan commands fail with "/var/www/html/storage/logs" permission denied (running locally)

This happens when a Docker-cached `bootstrap/cache/config.php` is still on disk with the container's path baked in. Delete the cache files manually (artisan can't run yet to clear them):

```bash
rm -f bootstrap/cache/config.php bootstrap/cache/routes*.php \
      bootstrap/cache/services.php bootstrap/cache/packages.php
```

Then update your `.env` to point to local services:

```env
DB_HOST=127.0.0.1
REDIS_HOST=127.0.0.1
MAIL_HOST=127.0.0.1
MEILISEARCH_HOST=http://127.0.0.1:7700
```

And run:

```bash
php artisan optimize:clear
php artisan serve
```
