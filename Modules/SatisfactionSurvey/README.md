# Satisfaction Survey Module

Automatically emails a **👍 / 👎 CSAT survey** to customers when a conversation is closed. The rating is recorded and displayed in the conversation sidebar.

---

## How It Works

```
Agent closes conversation
        │
        ▼ Hook: conversation.closed
SendSurveyJob (5-min delay, email-outbound queue)
        │
        ▼
Customer receives email with signed 👍 / 👎 links (7-day expiry)
        │
        ▼ GET /survey/respond?rating=good&signature=...
SurveyController validates signature → saves SurveyResponse
        │
        ▼
Thank-you page shown to customer
        │
        ▼ Next time agent opens conversation
Sidebar shows CSAT rating + date
```

---

## Installation

### 1. Enable the module

Go to **Settings → Modules** and toggle **Satisfaction Survey** on.

The module is seeded as inactive by default (opt-in). Enabling it will:
- Load the module's service provider on the next request
- Register the `conversation.closed` action hook
- Register the public `/survey/respond` route

### 2. Run migrations

```bash
php artisan migrate
```

This creates the `survey_responses` table.

### 3. Ensure the queue worker is running

Survey emails are dispatched to the `email-outbound` queue with a 5-minute delay. Make sure Horizon is running:

```bash
php artisan horizon
```

### 4. Verify signed URLs work

Signed URLs require `APP_KEY` to be set and `APP_URL` to match the URL your customers will receive in email. Double-check both in your `.env`:

```env
APP_KEY=base64:...
APP_URL=https://yourdomain.com
```

---

## Configuration

The module reads `config` from the `modules` database row (JSON column). You can update it via a migration or directly in the database.

| Key | Default | Description |
|---|---|---|
| `delay_minutes` | `5` | Minutes after close before survey email is sent |

To change the delay:

```sql
UPDATE modules SET config = '{"delay_minutes": 10}' WHERE alias = 'SatisfactionSurvey';
```

---

## File Structure

```
Modules/SatisfactionSurvey/
├── module.json                                 Metadata & hook registry
├── README.md                                   This file
│
├── Providers/
│   └── SatisfactionSurveyServiceProvider.php  Entry point — loads views, migrations, routes, hooks
│
├── Jobs/
│   └── SendSurveyJob.php                      Queued job — sends survey email via signed URL
│
├── Mail/
│   └── SurveyMail.php                         Mailable — wraps the survey email blade template
│
├── Models/
│   └── SurveyResponse.php                     Eloquent model — one row per conversation
│
├── Http/
│   └── Controllers/
│       └── SurveyController.php               Handles customer click — validates signature, saves rating
│
├── Routes/
│   └── web.php                                Public route: GET /survey/respond
│
├── Database/
│   └── Migrations/
│       └── ..._create_survey_responses_table.php
│
└── Resources/
    ├── views/
    │   ├── emails/
    │   │   └── survey.blade.php               HTML email with 👍 👎 buttons
    │   └── responded.blade.php                Standalone thank-you page (no app layout)
    └── js/
        └── SurveySidebarPanel.tsx             Reference React component + slot registration docs
```

---

## Hooks Registered

| Type | Hook | Description |
|---|---|---|
| Action | `conversation.closed` | Dispatches `SendSurveyJob` when a conversation is closed |
| Filter | `conversation.show.extra` | Appends `survey` data to the conversation show Inertia payload |
| Filter | `ai.system_prompt` | Tells the AI that a survey will be sent on close |

---

## Database Schema

```sql
survey_responses
    id               bigint PK
    conversation_id  bigint FK → conversations (cascade delete)
    customer_id      bigint FK → customers (null on delete), nullable
    rating           enum('good', 'bad')
    comment          text, nullable        -- reserved for future use
    ip_address       varchar(45), nullable
    responded_at     timestamp
    created_at       timestamp
    updated_at       timestamp

    UNIQUE (conversation_id)               -- one response per conversation
```

---

## Routes

| Method | URI | Description |
|---|---|---|
| `GET` | `/survey/respond` | Public endpoint — validates HMAC signature, records rating, shows thank-you page |

The URL is signed using Laravel's `URL::temporarySignedRoute()` with a **7-day expiry**. Tampered or expired links return HTTP 403.

---

## Sidebar Panel

When the `SatisfactionSurvey` module is active, the conversation sidebar shows a **CSAT** section:

- **Awaiting response** — conversation is closed but the customer hasn't clicked yet
- **👍 Good** + date — customer rated the experience positively
- **👎 Bad** + date — customer rated the experience negatively
- Hidden — conversation is still open (survey not sent yet)

### Frontend slot system (for future use)

The module ships a `SurveySidebarPanel.tsx` component. If you want to render it via the `SlotRenderer` slot system instead of the current inline approach, register it in `resources/js/app.tsx`:

```tsx
import SurveySidebarPanel from '../../Modules/SatisfactionSurvey/Resources/js/SurveySidebarPanel';
import { registerSlot } from '@/Components/SlotRenderer';

registerSlot('conversation.sidebar.bottom', SurveySidebarPanel);
```

Then place the renderer in the conversation sidebar:

```tsx
<SlotRenderer
    name="conversation.sidebar.bottom"
    props={{ survey, conversationStatus: conversation.status }}
/>
```

---

## Edge Cases

| Scenario | Behaviour |
|---|---|
| Customer has no email address | `SendSurveyJob` exits early — no email sent |
| Customer clicks the link twice | `firstOrCreate` — response is idempotent, first rating wins |
| Link is expired (> 7 days) | HTTP 403 with a clear message |
| Conversation is re-opened after close | No new survey sent (response already exists check) |
| Module is disabled mid-flight | Jobs already in queue will still run; new closes won't trigger new jobs |

---

## Extending This Module

### Add a comment field to the thank-you page

1. Add a `<textarea>` to `responded.blade.php` and POST it to a new `SurveyController::comment()` method
2. Save to the `comment` column on `survey_responses`

### Report CSAT scores

Query `survey_responses` grouped by `rating` and join with `conversations` to filter by mailbox, date range, or agent — then surface the data in the Reports page.

### Webhook on response

Register an additional action hook in the service provider:

```php
Hook::doAction('survey.responded', $response);
```

Another module can listen to this and post to Slack, create a follow-up task, etc.

---

## Uninstalling

1. Disable the module in **Settings → Modules**
2. Delete the `Modules/SatisfactionSurvey/` directory
3. Run the migration rollback:

```bash
php artisan migrate:rollback --step=1
```

4. Remove the module row from the database:

```sql
DELETE FROM modules WHERE alias = 'SatisfactionSurvey';
```
