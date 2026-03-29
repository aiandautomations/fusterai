# AI Setup Guide

FusterAI is AI-native — every conversation benefits from automatic reply suggestions, categorization, summarization, and semantic knowledge base search. This guide covers how to configure and get the most out of these features.

---

## Overview of AI Features

| Feature | When it runs | What it does |
|---|---|---|
| **Reply Suggestions** | Customer sends a message | Generates a context-aware draft reply using your knowledge base |
| **Auto-categorization** | New conversation created | Sets tags and priority automatically |
| **Summarization** | Manual trigger or conversation close | Creates a concise summary of the conversation |
| **RAG Search** | During reply suggestion | Searches knowledge base via pgvector for relevant context |
| **MCP Server** | External AI agents connect | Exposes helpdesk tools to Claude Desktop and other agents |

All AI features gracefully degrade when no API key is configured — the helpdesk remains fully functional.

---

## Step 1 — Get an API Key

### Anthropic Claude (Recommended)

1. Sign up at [console.anthropic.com](https://console.anthropic.com)
2. Go to **API Keys → Create Key**
3. Copy the key (starts with `sk-ant-`)

Recommended model: `claude-opus-4-6` (default) — best for helpdesk reasoning and reply quality.

For lower cost and faster responses: `claude-haiku-4-5-20251001`

### OpenAI (Alternative)

1. Sign up at [platform.openai.com](https://platform.openai.com)
2. Go to **API Keys → Create new secret key**
3. Copy the key (starts with `sk-`)

### Self-hosted / OpenAI-compatible (e.g., Ollama)

Set the base URL and key for any OpenAI-compatible API:

```env
OPENAI_COMPATIBLE_BASE_URL=http://localhost:11434/v1
OPENAI_COMPATIBLE_API_KEY=ollama  # or any value
```

---

## Step 2 — Configure in Admin Panel

1. Log in to FusterAI
2. Go to **Settings → AI Config**
3. Enter your API key
4. Select the AI provider and model
5. Toggle individual features on/off
6. Click **Save**

Alternatively, set via environment variable (used as fallback if no DB config):

```env
ANTHROPIC_API_KEY=sk-ant-...
```

---

## Step 3 — Set Up the Knowledge Base (RAG)

The knowledge base powers **Retrieval-Augmented Generation (RAG)** — when the AI generates a reply suggestion, it first searches your documents and injects relevant excerpts into the prompt, making replies accurate and specific to your product.

### Create a knowledge base

1. Go to **Knowledge Base → New Knowledge Base**
2. Give it a name (e.g., "Product Documentation", "Support FAQs")
3. Click **Create**

### Add documents

1. Inside your knowledge base, click **Add Document**
2. Add title and content (paste text, import from URL, or upload)
3. Click **Save**

FusterAI automatically:
- Splits documents into chunks
- Generates embeddings via your configured AI provider
- Stores embeddings in PostgreSQL pgvector
- Indexes text in MeiliSearch for hybrid search

### How RAG works in reply suggestions

```
Customer message received
        ↓
GenerateReplySuggestionJob dispatched
        ↓
Semantic search on knowledge base
  (pgvector cosine similarity against message embedding)
        ↓
Top-k relevant document chunks selected
        ↓
Chunks injected into Claude system prompt
        ↓
Claude generates context-aware draft reply
        ↓
Reply streamed to AI Assist panel via Reverb WebSocket
```

### Re-index after bulk imports

If you've added many documents programmatically:

```bash
php artisan scout:import "App\Domains\AI\Models\KbDocument"
```

---

## AI Queue Workers

AI jobs run in a dedicated Horizon pool for isolation and control:

| Job | Trigger | Queue |
|---|---|---|
| `GenerateReplySuggestionJob` | New customer message | `ai` |
| `CategorizeConversationJob` | New conversation created | `ai` |
| `SummarizeConversationJob` | Manual trigger or close | `ai` |
| `IndexKbDocumentJob` | Knowledge base document saved | `ai` |

### Monitor AI jobs

Visit **Horizon dashboard** at `/horizon` → click the **ai** queue to see pending, processed, and failed AI jobs.

### Tune AI worker count

Edit `config/horizon.php` to allocate more workers for AI:

```php
'production' => [
    'ai' => [
        'connection' => 'redis',
        'queue'      => ['ai'],
        'balance'    => 'auto',
        'processes'  => 8,     // increase for high volume
        'timeout'    => 120,   // seconds per job (Claude can be slow)
        'tries'      => 3,
    ],
    // ...
],
```

---

## MCP Server (Model Context Protocol)

FusterAI exposes an MCP server so external AI agents (Claude Desktop, custom agents) can interact directly with your helpdesk.

### Available tools

| Tool | Description |
|---|---|
| `get_conversation(id)` | Full conversation with all thread messages |
| `search_conversations(query)` | Semantic search across all conversations |
| `get_customer_history(email)` | All past conversations for a customer |
| `search_knowledge_base(query)` | RAG search across knowledge bases |
| `create_note(conv_id, text)` | Add an internal note to a conversation |
| `assign_conversation(id, user)` | Assign a conversation to an agent |

### Connect Claude Desktop

1. Create a Personal Access Token in FusterAI:
   - Settings → API Tokens → Create Token

2. Add to your Claude Desktop config (`~/.claude/claude_desktop_config.json`):

```json
{
  "mcpServers": {
    "fusterai": {
      "url": "http://localhost:8000/mcp",
      "headers": {
        "Authorization": "Bearer your-personal-access-token"
      }
    }
  }
}
```

3. Restart Claude Desktop — FusterAI tools will appear in the tools panel.

### OAuth 2.1 Flow (for third-party clients)

For OAuth-authenticated clients, Passport-issued tokens are used. The OAuth endpoints are:

```
Authorization: GET  /oauth/authorize
Token:         POST /oauth/token
Revoke:        POST /oauth/tokens/{id}/revoke
MCP Server:    GET/POST /mcp
```

---

## Extending AI with Hooks

Use the module hook system to customize AI behavior:

### Modify the system prompt

```php
use App\Facades\Hook;
use App\Domains\Conversation\Models\Conversation;

Hook::filter('ai.system_prompt', function (string $prompt, Conversation $conv) {
    // Append custom instructions
    return $prompt . "\n\nAlways respond in English, even if the customer writes in another language.";
});
```

### Hook into suggestion generation

```php
Hook::listen('ai.suggestion_generated', function ($suggestion, Conversation $conv) {
    // Log, notify, post-process, etc.
    \Log::info("AI suggestion generated for conversation #{$conv->id}");
});
```

### Provide additional context

```php
Hook::filter('ai.context', function (array $context, Conversation $conv) {
    // Add custom data to the AI context
    $context['account_tier'] = $conv->customer->meta['account_tier'] ?? 'free';
    return $context;
});
```

---

## Cost Estimates

Rough token usage per operation (Claude claude-opus-4-6):

| Operation | Input tokens | Output tokens | ~Cost (claude-opus-4-6) |
|---|---|---|---|
| Reply suggestion | ~2,000–5,000 | ~300–500 | ~$0.03–0.08 |
| Categorization | ~500–1,500 | ~100 | ~$0.007–0.02 |
| Summarization | ~1,000–8,000 | ~200–400 | ~$0.01–0.10 |
| KB indexing (embedding) | ~200–2,000 | — | ~$0.001 |

Estimates vary based on conversation length and knowledge base size. Monitor actual usage in the Anthropic console.

**To reduce costs:**
- Use `claude-haiku-4-5-20251001` for categorization and summarization
- Use `claude-opus-4-6` only for reply suggestions where quality matters
- Set model per-feature in Settings → AI Config

---

## Disabling AI Features

To disable specific features without removing the API key:

Go to **Settings → AI Config** and toggle features off individually.

Or set environment variables:

```env
AI_REPLY_SUGGESTIONS=false
AI_AUTO_CATEGORIZATION=false
AI_SUMMARIZATION=false
AI_RAG=false
```
