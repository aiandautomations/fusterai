# Getting Started

This guide walks you through your first steps after installing FusterAI — setting up a mailbox, connecting email, and handling your first conversation.

> **Prerequisite:** FusterAI is installed and running. See the [Installation Guide](installation.md) if you haven't set it up yet.

---

## 1. Log In

Visit your FusterAI instance (default: http://localhost:8000) and log in with the admin credentials you created during installation.

---

## 2. Explore the Interface

FusterAI uses a three-panel layout:

```
┌──────────────┬───────────────────────┬──────────────────────────────────┐
│   Sidebar    │   Conversation List   │   Thread View + AI Assist Panel  │
│              │                       │                                  │
│  Mailboxes   │  Open (14)            │  Customer: John Doe              │
│  All Open    │  ─────────────────    │  Subject: Billing question       │
│  Mine        │  📧 Billing question  │                                  │
│  Unassigned  │  📧 Can't log in      │  [Thread messages here]          │
│  Pending     │  📧 Feature request   │                                  │
│  Snoozed     │                       │  [AI Assist panel]               │
│              │                       │  [Reply editor (Tiptap)]         │
│  Tags        │                       │                                  │
└──────────────┴───────────────────────┴──────────────────────────────────┘
```

**Sidebar folders:**
- **All Open** — every open conversation in your workspace
- **Mine** — conversations assigned to you
- **Unassigned** — conversations with no agent assigned
- **Pending** — waiting for customer reply
- **Snoozed** — hidden until a future date/time

---

## 3. Configure Your First Mailbox

A **mailbox** connects an email address to FusterAI so you can receive and send emails.

1. Go to **Settings → Mailboxes → Add Mailbox**
2. Fill in the mailbox name and email address
3. Configure **IMAP** (for receiving email):
   - Host, port, username, password
   - Common: Gmail → `imap.gmail.com:993`, Outlook → `outlook.office365.com:993`
4. Configure **SMTP** (for sending replies):
   - Host, port, username, password, encryption
   - Common: Gmail → `smtp.gmail.com:587`, Outlook → `smtp.office365.com:587`
5. Optionally configure an **auto-reply** message for new conversations
6. Add your email **signature** (supports HTML via the rich text editor)
7. Click **Save**

> **Gmail users:** You'll need to use an [App Password](https://support.google.com/accounts/answer/185833) if you have 2FA enabled. Go to Google Account → Security → App Passwords.

### Test the mailbox

FusterAI fetches new emails every minute via the scheduler. To trigger an immediate fetch:

```bash
# Sail
sail artisan fetch:emails

# Manual
php artisan fetch:emails
```

Send a test email to your mailbox address and it should appear in FusterAI within a minute.

---

## 4. Create Your Team

Invite additional agents to collaborate:

1. Go to **Settings → Users → Invite User**
2. Enter their name, email, and role:
   - **Admin** — full access including settings
   - **Agent** — can view and handle conversations

Agents will receive an email invitation with a link to set their password.

---

## 5. Set Up Tags

Tags help categorize conversations for filtering and reporting.

1. Go to **Settings → Tags → Add Tag**
2. Give it a name and choose a color
3. Tags can be manually applied to conversations from the conversation sidebar
4. With AI enabled, FusterAI will auto-apply tags based on conversation content

---

## 6. Handle Your First Conversation

Click any conversation in the list to open it:

### Reading a conversation
- **Thread view** shows all messages, internal notes, and activity
- **Customer panel** (right sidebar) shows customer details and past conversations
- **AI summary** appears at the top if the conversation has been summarized

### Replying
1. Click the **Reply** tab in the editor at the bottom
2. Type your reply (supports bold, italic, lists, links, attachments)
3. Use `/` to insert a **canned response** (saved reply template)
4. Click **Send Reply**

### Internal notes
1. Click the **Note** tab in the editor
2. Type your note — only agents can see notes, not the customer
3. Click **Add Note**

### Conversation actions (right sidebar)
- **Assign** to yourself or another agent
- **Set priority** (Low / Normal / High / Urgent)
- **Add tags**
- **Snooze** until a specific date/time
- **Change status** (Open → Pending → Closed)
- **Merge** with another conversation

---

## 7. Enable AI Features

If you have an Anthropic API key, AI features activate automatically:

1. Go to **Settings → AI Config**
2. Enter your Anthropic API key
3. Select the model (default: `claude-opus-4-6`)
4. Enable/disable individual features:
   - **Reply Suggestions** — AI drafts a reply for every new customer message
   - **Auto-categorization** — AI sets tags and priority on new conversations
   - **Summarization** — one-click conversation summaries

### Using AI reply suggestions

When a customer sends a message, look for the **AI Assist** panel in the conversation view:
- The AI will generate a draft reply using your knowledge base (if configured)
- Click **Use Draft** to copy it into the reply editor
- Edit as needed, then send

### Building a knowledge base

The AI uses your knowledge base as context when generating replies (RAG):

1. Go to **Knowledge Base → New Knowledge Base**
2. Add documents (FAQs, product guides, policies)
3. FusterAI will index them automatically using pgvector embeddings
4. From this point, AI replies will reference your documentation

---

## 8. Set Up Automation Rules

Automate repetitive tasks with trigger → condition → action rules:

1. Go to **Settings → Automation → New Rule**
2. Set a **trigger**: conversation created, status changed, tag applied, etc.
3. Add **conditions**: mailbox, priority, customer email, subject contains, etc.
4. Add **actions**: assign to agent, set priority, add tag, send auto-reply, etc.

**Example rules:**
- "If subject contains `urgent` → set priority to Urgent → assign to Senior Agent"
- "If mailbox is `billing@` → add tag `billing` → notify billing team"
- "If status changes to Closed → send satisfaction survey (via webhook)"

---

## 9. Configure the Live Chat Widget

Embed a real-time chat widget on your website:

1. Go to **Settings → Live Chat**
2. Copy the embed code
3. Add it to your website's HTML before `</body>`:

```html
<script>
  window.FusterAIConfig = {
    workspaceId: 'your-workspace-id',
    serverUrl: 'https://your-fusterai.com'
  };
</script>
<script src="https://your-fusterai.com/livechat/widget.js" async></script>
```

Chat messages appear in the **Live Chat** section of the sidebar in real-time.

---

## 10. Use the REST API

Create an API token to integrate FusterAI with other tools:

1. Go to **Settings → API Tokens → Create Token**
2. Copy the token (shown only once)
3. Use it in API requests:

```bash
curl http://localhost:8000/api/conversations \
  -H "Authorization: Bearer your-token"
```

Full API documentation is available at http://localhost:8000/docs/api.

---

## Keyboard Shortcuts

| Shortcut | Action |
|---|---|
| `c` | Open new conversation |
| `r` | Reply to current conversation |
| `n` | Add internal note |
| `Escape` | Close modal / cancel |
| `j` / `k` | Next / previous conversation |
| `/` | Focus search |

---

## Next Steps

- [Configuration Reference](configuration.md) — all environment variables explained
- [AI Setup Guide](ai-setup.md) — detailed AI configuration and RAG setup
- [Module Development](modules.md) — extend FusterAI with custom modules
