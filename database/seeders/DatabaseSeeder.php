<?php

namespace Database\Seeders;

use App\Domains\AI\Models\KnowledgeBase;
use App\Domains\AI\Models\Module;
use App\Domains\Automation\Models\AutomationRule;
use App\Domains\Conversation\Models\Conversation;
use App\Domains\Conversation\Models\Tag;
use App\Domains\Customer\Models\Customer;
use App\Domains\Mailbox\Models\Mailbox;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command->warn('Seeder skipped in production environment.');

            return;
        }

        // ── Workspace ────────────────────────────────────────────────────────────

        $workspace = Workspace::firstOrCreate(
            ['slug' => 'default'],
            ['name' => 'FusterAI Demo', 'slug' => 'default']
        );

        // ── Users ────────────────────────────────────────────────────────────────

        $admin = User::firstOrCreate(['email' => 'admin@fusterai.com'], [
            'name' => 'Admin User',
            'password' => Hash::make('password'),
            'workspace_id' => $workspace->id,
            'role' => 'admin',
        ]);

        $agent1 = User::firstOrCreate(['email' => 'sarah@example.com'], [
            'name' => 'Sarah Johnson',
            'password' => Hash::make('password'),
            'workspace_id' => $workspace->id,
            'role' => 'agent',
        ]);

        $agent2 = User::firstOrCreate(['email' => 'mike@example.com'], [
            'name' => 'Mike Chen',
            'password' => Hash::make('password'),
            'workspace_id' => $workspace->id,
            'role' => 'agent',
        ]);

        $agent3 = User::firstOrCreate(['email' => 'rachel@example.com'], [
            'name' => 'Rachel Green',
            'password' => Hash::make('password'),
            'workspace_id' => $workspace->id,
            'role' => 'agent',
        ]);

        // ── Mailboxes ────────────────────────────────────────────────────────────

        $supportMailbox = Mailbox::firstOrCreate(['email' => 'support@fusterai.dev'], [
            'workspace_id' => $workspace->id,
            'name' => 'Support',
            'active' => true,
            'channel_type' => 'email',
        ]);

        $salesMailbox = Mailbox::firstOrCreate(['email' => 'sales@fusterai.dev'], [
            'workspace_id' => $workspace->id,
            'name' => 'Sales',
            'active' => true,
            'channel_type' => 'email',
        ]);

        $chatMailbox = Mailbox::firstOrCreate(['email' => 'chat@fusterai.dev'], [
            'workspace_id' => $workspace->id,
            'name' => 'Live Chat',
            'active' => true,
            'channel_type' => 'chat',
        ]);

        // Assign agents to mailboxes
        $supportMailbox->users()->syncWithoutDetaching([$admin->id, $agent1->id, $agent2->id, $agent3->id]);
        $salesMailbox->users()->syncWithoutDetaching([$admin->id, $agent2->id, $agent3->id]);
        $chatMailbox->users()->syncWithoutDetaching([$admin->id, $agent1->id, $agent3->id]);

        // ── Tags ─────────────────────────────────────────────────────────────────

        $tags = [];
        foreach ([
            ['name' => 'billing',      'color' => '#f59e0b'],
            ['name' => 'bug',          'color' => '#ef4444'],
            ['name' => 'feature',      'color' => '#8b5cf6'],
            ['name' => 'urgent',       'color' => '#dc2626'],
            ['name' => 'onboarding',   'color' => '#10b981'],
            ['name' => 'refund',       'color' => '#f97316'],
            ['name' => 'security',     'color' => '#0ea5e9'],
            ['name' => 'performance',  'color' => '#64748b'],
            ['name' => 'integration',  'color' => '#a855f7'],
            ['name' => 'feedback',     'color' => '#06b6d4'],
        ] as $tagData) {
            $tags[$tagData['name']] = Tag::firstOrCreate(
                ['workspace_id' => $workspace->id, 'name' => $tagData['name']],
                ['color' => $tagData['color']]
            );
        }

        // ── Customers ────────────────────────────────────────────────────────────

        $customerData = [
            ['name' => 'Emma Wilson',      'email' => 'emma@techcorp.io',         'company' => 'TechCorp'],
            ['name' => 'James Martinez',   'email' => 'james@startup.co',         'company' => 'Startup Co'],
            ['name' => 'Priya Sharma',     'email' => 'priya@designstudio.com',   'company' => 'Design Studio'],
            ['name' => 'David Kim',        'email' => 'david@ecommerce.shop',     'company' => 'ECommerce Shop'],
            ['name' => 'Lisa Thompson',    'email' => 'lisa@agency.net',          'company' => 'Creative Agency'],
            ['name' => 'Omar Hassan',      'email' => 'omar@saas.io',             'company' => 'SaaS Inc'],
            ['name' => 'Anna Kowalski',    'email' => 'anna@freelance.me',        'company' => null],
            ['name' => 'Tom Bradley',      'email' => 'tom@enterprise.com',       'company' => 'Enterprise Ltd'],
            ['name' => 'Sophie Dubois',    'email' => 'sophie@frenchco.fr',       'company' => 'FrenchCo'],
            ['name' => 'Carlos Rivera',    'email' => 'carlos@latam.io',          'company' => 'LatAm Digital'],
            ['name' => 'Yuki Tanaka',      'email' => 'yuki@tokyotech.jp',        'company' => 'Tokyo Tech'],
            ['name' => 'Mark Stevens',     'email' => 'mark@cloudinfra.co',       'company' => 'CloudInfra'],
            ['name' => 'Nina Petrov',      'email' => 'nina@mediahouse.ru',       'company' => 'MediaHouse'],
            ['name' => 'Ben Nguyen',       'email' => 'ben@devshop.vn',           'company' => 'DevShop'],
            ['name' => 'Alice Parker',     'email' => 'alice@growth.io',          'company' => 'Growth.io'],
            ['name' => 'Ryan Foster',      'email' => 'ryan@rocketship.co',       'company' => 'Rocketship'],
        ];

        $customerModels = [];
        foreach ($customerData as $c) {
            $customerModels[] = Customer::firstOrCreate(
                ['workspace_id' => $workspace->id, 'email' => $c['email']],
                ['name' => $c['name'], 'company' => $c['company']]
            );
        }

        // ── Conversations + Threads ──────────────────────────────────────────────

        $conversations = [

            // ── Open / Active ─────────────────────────────────────────────────────
            [
                'mailbox' => $supportMailbox,
                'customer' => $customerModels[0],
                'subject' => 'Cannot login to my account after password reset',
                'status' => 'open',
                'priority' => 'urgent',
                'assigned' => $agent1,
                'tags' => ['bug', 'urgent'],
                'channel' => 'email',
                'threads' => [
                    ['from' => 'customer', 'body' => "Hi, I reset my password but now I can't login. It keeps saying 'Invalid credentials' even though I'm sure the password is correct. This is urgent — I have a client meeting in 2 hours."],
                    ['from' => 'agent',    'body' => "Hi Emma, I'm sorry to hear you're having trouble. Let me check your account right away. Can you try clearing your browser cache and cookies first, then attempt to login again?", 'user' => $agent1],
                    ['from' => 'customer', 'body' => "I tried clearing cache but it's still not working. Please help!"],
                    ['from' => 'note',     'body' => 'Checked auth logs — looks like a session invalidation issue after the password reset. Need to investigate the token refresh flow.', 'user' => $agent1],
                ],
            ],
            [
                'mailbox' => $supportMailbox,
                'customer' => $customerModels[1],
                'subject' => 'Invoice #1042 shows wrong amount',
                'status' => 'open',
                'priority' => 'high',
                'assigned' => $agent2,
                'tags' => ['billing'],
                'channel' => 'email',
                'threads' => [
                    ['from' => 'customer', 'body' => "Hello, invoice #1042 shows \$299 but my plan is \$199/month. I think there's been an error. Please review and correct this."],
                    ['from' => 'agent',    'body' => "Hi James, I'm looking into your invoice now. This looks like it may have included a pro-rated amount from your plan upgrade. I'll send you a corrected invoice shortly.", 'user' => $agent2],
                ],
            ],
            [
                'mailbox' => $supportMailbox,
                'customer' => $customerModels[3],
                'subject' => 'API rate limiting causing issues in production',
                'status' => 'open',
                'priority' => 'urgent',
                'assigned' => $admin,
                'tags' => ['bug', 'urgent'],
                'channel' => 'email',
                'threads' => [
                    ['from' => 'customer', 'body' => "We're hitting API rate limits in production. Our app makes ~500 req/min but getting 429 errors after ~200 requests. Our Business plan should support higher limits. This is degrading our service."],
                    ['from' => 'agent',    'body' => "Hi David, I've flagged this as urgent. Your Business plan has a 1000 req/min limit. Let me check if there's a misconfiguration on our end.", 'user' => $admin],
                    ['from' => 'note',     'body' => 'Checked infrastructure — rate limit is applied per IP, not per account. Need to whitelist their IP range or update to token-based limiting.', 'user' => $admin],
                    ['from' => 'customer', 'body' => "Any updates? This has been down for 3 hours and we're losing money."],
                ],
            ],
            [
                'mailbox' => $supportMailbox,
                'customer' => $customerModels[8],
                'subject' => 'Webhook not delivering to our endpoint',
                'status' => 'open',
                'priority' => 'high',
                'assigned' => $agent1,
                'tags' => ['integration', 'bug'],
                'channel' => 'email',
                'threads' => [
                    ['from' => 'customer', 'body' => "Hello, we configured webhooks to fire on new conversations but they're not being delivered. Our endpoint is publicly accessible and returns 200. We've been waiting 2 days for this to work."],
                    ['from' => 'agent',    'body' => "Hi Sophie, let me check your webhook delivery logs. Can you share the endpoint URL you've configured and your workspace ID?", 'user' => $agent1],
                    ['from' => 'customer', 'body' => 'Endpoint: https://api.frenchco.fr/hooks/fusterai — workspace ID in my account settings.'],
                ],
            ],
            [
                'mailbox' => $supportMailbox,
                'customer' => $customerModels[9],
                'subject' => 'Two-factor authentication locked me out',
                'status' => 'open',
                'priority' => 'high',
                'assigned' => $agent3,
                'tags' => ['security'],
                'channel' => 'email',
                'threads' => [
                    ['from' => 'customer', 'body' => "I enabled 2FA but lost access to my authenticator app. I can't login and need my account restored urgently. I have team members waiting on tickets."],
                    ['from' => 'agent',    'body' => 'Hi Carlos, I understand this is stressful. For security, I need to verify your identity before resetting 2FA. Can you confirm your billing email and the last 4 digits of the payment card on file?', 'user' => $agent3],
                ],
            ],
            [
                'mailbox' => $supportMailbox,
                'customer' => $customerModels[10],
                'subject' => 'Email replies not threading correctly',
                'status' => 'open',
                'priority' => 'normal',
                'assigned' => $agent2,
                'tags' => ['bug'],
                'channel' => 'email',
                'threads' => [
                    ['from' => 'customer', 'body' => "When customers reply to our emails, new tickets are being created instead of appending to the existing conversation. This started happening 3 days ago. We're using Gmail IMAP."],
                    ['from' => 'agent',    'body' => 'Hi Yuki, this sounds like an In-Reply-To header matching issue. Can you share an example email header (with personal info redacted) from a customer reply that created a duplicate?', 'user' => $agent2],
                    ['from' => 'customer', 'body' => "Here's a redacted header from this morning: Message-ID: <CAGk...@mail.gmail.com>, In-Reply-To missing from the original."],
                    ['from' => 'note',     'body' => 'The issue is in ProcessInboundEmailJob — looks like our email signature stripping is removing the In-Reply-To header in some cases. Filed as internal bug.', 'user' => $agent2],
                ],
            ],
            [
                'mailbox' => $supportMailbox,
                'customer' => $customerModels[11],
                'subject' => 'Dashboard loading extremely slow',
                'status' => 'open',
                'priority' => 'normal',
                'assigned' => $agent1,
                'tags' => ['performance'],
                'channel' => 'email',
                'threads' => [
                    ['from' => 'customer', 'body' => 'Your dashboard has been taking 15-20 seconds to load for the past week. We have ~12,000 conversations. Is this a known issue?'],
                    ['from' => 'agent',    'body' => "Hi Mark, 15-20 seconds is definitely not expected. We're investigating potential query performance issues on large workspaces. Can you tell me your rough conversation count per mailbox?", 'user' => $agent1],
                ],
            ],

            // ── Pending ───────────────────────────────────────────────────────────
            [
                'mailbox' => $supportMailbox,
                'customer' => $customerModels[2],
                'subject' => 'Feature request: Dark mode support',
                'status' => 'pending',
                'priority' => 'normal',
                'assigned' => null,
                'tags' => ['feature', 'feedback'],
                'channel' => 'email',
                'threads' => [
                    ['from' => 'customer', 'body' => 'Hi team! Love the product. Would it be possible to add a dark mode? I work late and the bright interface is hard on the eyes. Many colleagues have asked for this too.'],
                    ['from' => 'agent',    'body' => "Hi Priya, dark mode is on our roadmap! I've added your vote. We'll notify you when it's available.", 'user' => $agent1],
                    ['from' => 'customer', 'body' => 'Amazing! Any ETA?'],
                ],
            ],
            [
                'mailbox' => $supportMailbox,
                'customer' => $customerModels[12],
                'subject' => 'Request to add team member but getting error',
                'status' => 'pending',
                'priority' => 'normal',
                'assigned' => $agent3,
                'tags' => [],
                'channel' => 'email',
                'threads' => [
                    ['from' => 'customer', 'body' => "Trying to invite a new agent but get 'Seat limit reached' even though we're on the Business plan with unlimited seats. Can you check?"],
                    ['from' => 'agent',    'body' => "Hi Nina, I see you're on Business plan which does include unlimited seats. Let me check if there's a billing discrepancy. Can you confirm when you upgraded to Business?", 'user' => $agent3],
                    ['from' => 'customer', 'body' => 'We upgraded about 2 weeks ago via the billing portal.'],
                ],
            ],
            [
                'mailbox' => $supportMailbox,
                'customer' => $customerModels[13],
                'subject' => 'SAML SSO configuration not working',
                'status' => 'pending',
                'priority' => 'high',
                'assigned' => $admin,
                'tags' => ['security', 'integration'],
                'channel' => 'email',
                'threads' => [
                    ['from' => 'customer', 'body' => "We're trying to configure SAML SSO with Okta but the assertion consumer service URL doesn't seem right. Users get redirected back to login. We need this working for our company security policy."],
                    ['from' => 'agent',    'body' => 'Hi Ben, SAML configuration can be tricky. Can you share your Okta IdP metadata URL? Also make sure your ACS URL is set to: https://app.fusterai.com/auth/saml/callback', 'user' => $admin],
                    ['from' => 'customer', 'body' => "Updated the ACS URL and it got further, but now getting 'Invalid signature'. Our metadata URL: https://dev-12345.okta.com/app/metadata.xml"],
                ],
            ],
            [
                'mailbox' => $salesMailbox,
                'customer' => $customerModels[5],
                'subject' => 'Interested in Enterprise plan — need pricing',
                'status' => 'pending',
                'priority' => 'high',
                'assigned' => $agent2,
                'tags' => ['onboarding'],
                'channel' => 'email',
                'threads' => [
                    ['from' => 'customer', 'body' => "Hello, we're a team of 50 evaluating helpdesk solutions. Very interested in FusterAI but need enterprise pricing for our volume."],
                    ['from' => 'agent',    'body' => "Hi Omar! Our Enterprise plan offers unlimited agents, custom SLAs, dedicated support, and SSO. I'd love to schedule a demo. Are you available this week?", 'user' => $agent2],
                    ['from' => 'customer', 'body' => "Thursday or Friday afternoon works. Let's connect!"],
                    ['from' => 'note',     'body' => 'Very promising lead — 50 agents, currently on Zendesk. Budget mentioned is $2k/month. Scheduled demo for Thursday 3pm.', 'user' => $agent2],
                ],
            ],
            [
                'mailbox' => $salesMailbox,
                'customer' => $customerModels[14],
                'subject' => 'Can we get a 30-day trial extension?',
                'status' => 'pending',
                'priority' => 'normal',
                'assigned' => $agent3,
                'tags' => ['onboarding'],
                'channel' => 'email',
                'threads' => [
                    ['from' => 'customer', 'body' => "Hi, our 14-day trial ends tomorrow but we haven't had time to fully evaluate due to a product launch. Is it possible to get a 30-day extension?"],
                    ['from' => 'agent',    'body' => "Hi Alice! Of course — I've extended your trial by 30 days. Take your time evaluating. Happy to schedule a guided walkthrough if that would help!", 'user' => $agent3],
                    ['from' => 'customer', 'body' => "That's wonderful, thank you! A walkthrough would be great actually."],
                ],
            ],

            // ── Snoozed ───────────────────────────────────────────────────────────
            [
                'mailbox' => $supportMailbox,
                'customer' => $customerModels[15],
                'subject' => 'Bulk import of historical tickets',
                'status' => 'open',
                'priority' => 'low',
                'assigned' => $agent1,
                'tags' => ['onboarding'],
                'channel' => 'email',
                'snoozed' => true,
                'threads' => [
                    ['from' => 'customer', 'body' => "We're migrating from Zendesk and have 50,000 historical tickets. Do you have a bulk import tool or can you help with the migration?"],
                    ['from' => 'agent',    'body' => "Hi Ryan! We have a CSV import tool and can also do a custom migration. I'll loop in our onboarding team — they'll reach out within 2 business days.", 'user' => $agent1],
                    ['from' => 'note',     'body' => 'Snoozed for 2 days — waiting for onboarding team to take over. Large enterprise migration opportunity.', 'user' => $agent1],
                ],
            ],

            // ── Closed ────────────────────────────────────────────────────────────
            [
                'mailbox' => $supportMailbox,
                'customer' => $customerModels[4],
                'subject' => 'How to export all conversation data?',
                'status' => 'closed',
                'priority' => 'low',
                'assigned' => $agent1,
                'tags' => [],
                'channel' => 'email',
                'threads' => [
                    ['from' => 'customer', 'body' => 'Hi, I need to export all our conversation history for compliance. Is there a way to do this in bulk?'],
                    ['from' => 'agent',    'body' => "Hi Lisa! Go to Settings → General → Export Data. You'll receive a CSV with all conversations within a few minutes.", 'user' => $agent1],
                    ['from' => 'customer', 'body' => 'That worked! Thank you.'],
                    ['from' => 'agent',    'body' => 'Let us know if you need anything else!', 'user' => $agent1],
                ],
            ],
            [
                'mailbox' => $salesMailbox,
                'customer' => $customerModels[6],
                'subject' => 'Refund request for annual subscription',
                'status' => 'closed',
                'priority' => 'high',
                'assigned' => $agent2,
                'tags' => ['billing', 'refund'],
                'channel' => 'email',
                'threads' => [
                    ['from' => 'customer', 'body' => 'I signed up for annual but decided to go elsewhere. Requesting a full refund within 30 days. Order #ORD-2024-8821.'],
                    ['from' => 'agent',    'body' => "Hi Anna, I've located your order. You're within our 30-day window — processing refund now. 3-5 business days to appear.", 'user' => $agent2],
                    ['from' => 'customer', 'body' => 'Thank you for processing this quickly.'],
                ],
            ],
            [
                'mailbox' => $supportMailbox,
                'customer' => $customerModels[0],
                'subject' => 'How do I set up email signatures?',
                'status' => 'closed',
                'priority' => 'low',
                'assigned' => $agent3,
                'tags' => [],
                'channel' => 'email',
                'threads' => [
                    ['from' => 'customer', 'body' => "Where can I configure email signatures for my team's outgoing replies?"],
                    ['from' => 'agent',    'body' => 'Hi Emma! Go to Settings → Mailboxes → [Your Mailbox] → Signature. Each mailbox has its own signature that gets appended to outgoing emails automatically.', 'user' => $agent3],
                    ['from' => 'customer', 'body' => 'Found it, perfect! Thanks!'],
                ],
            ],
            [
                'mailbox' => $supportMailbox,
                'customer' => $customerModels[1],
                'subject' => 'Canned responses not appearing in editor',
                'status' => 'closed',
                'priority' => 'normal',
                'assigned' => $agent1,
                'tags' => ['bug'],
                'channel' => 'email',
                'threads' => [
                    ['from' => 'customer', 'body' => "I created canned responses in settings but when I type '/' in the reply editor, nothing shows up. Is there a specific trigger?"],
                    ['from' => 'agent',    'body' => "Hi James! You need to type '/' followed by the first few letters of your canned response name. For example '/greeting' to find responses starting with 'greeting'. Try that!", 'user' => $agent1],
                    ['from' => 'customer', 'body' => "That worked! The search wasn't obvious. Maybe you could add a tooltip?"],
                    ['from' => 'agent',    'body' => "Great feedback — I've passed this to our product team. Thanks!", 'user' => $agent1],
                ],
            ],
            [
                'mailbox' => $supportMailbox,
                'customer' => $customerModels[7],
                'subject' => 'Account suspended — no warning received',
                'status' => 'closed',
                'priority' => 'urgent',
                'assigned' => $admin,
                'tags' => ['billing', 'urgent'],
                'channel' => 'email',
                'threads' => [
                    ['from' => 'customer', 'body' => "Our account was suddenly suspended with no warning. We have 8 agents who can't access the system. What happened?"],
                    ['from' => 'agent',    'body' => "Hi Tom, I'm escalating this immediately. Checking your account now.", 'user' => $admin],
                    ['from' => 'note',     'body' => "Payment failed 3 times — credit card expired. Customer wasn't receiving billing emails (went to spam). Manually restoring access and updating email.", 'user' => $admin],
                    ['from' => 'agent',    'body' => "Tom, I've restored your account access. The suspension was due to a failed payment — your card on file expired. I've sent instructions to update your payment method. I've also waived the suspension fee given the circumstance.", 'user' => $admin],
                    ['from' => 'customer', 'body' => 'Thank you for the quick resolution. Updating the card now.'],
                ],
            ],
            [
                'mailbox' => $supportMailbox,
                'customer' => $customerModels[3],
                'subject' => 'How to merge duplicate conversations?',
                'status' => 'closed',
                'priority' => 'low',
                'assigned' => $agent2,
                'tags' => [],
                'channel' => 'email',
                'threads' => [
                    ['from' => 'customer', 'body' => 'A customer emailed us from two different addresses and we now have duplicate conversations. Can these be merged?'],
                    ['from' => 'agent',    'body' => "Hi David! Yes — open either conversation, click the '...' menu in the top right, and select 'Merge'. You can then search for the conversation to merge it with.", 'user' => $agent2],
                    ['from' => 'customer', 'body' => 'Worked perfectly. Thanks!'],
                ],
            ],
            [
                'mailbox' => $supportMailbox,
                'customer' => $customerModels[9],
                'subject' => 'Reports showing incorrect agent response times',
                'status' => 'closed',
                'priority' => 'normal',
                'assigned' => $agent3,
                'tags' => ['bug'],
                'channel' => 'email',
                'threads' => [
                    ['from' => 'customer', 'body' => 'The average response time in reports is showing 48+ hours but our actual response time is under 2 hours. Something seems wrong with the calculation.'],
                    ['from' => 'agent',    'body' => "Hi Carlos, I've reproduced this! The bug is that response time was being calculated including weekends/holidays. A fix has been deployed — your reports should now be accurate.", 'user' => $agent3],
                    ['from' => 'customer', 'body' => 'Just checked — looks correct now. Thank you for the fast fix!'],
                ],
            ],

            // ── Live Chat ─────────────────────────────────────────────────────────
            [
                'mailbox' => $chatMailbox,
                'customer' => $customerModels[7],
                'subject' => 'Live chat: Need help with integration',
                'status' => 'open',
                'priority' => 'normal',
                'assigned' => $agent1,
                'tags' => ['integration'],
                'channel' => 'chat',
                'threads' => [
                    ['from' => 'customer', 'body' => "Hi! I'm trying to integrate the API with our React app but getting CORS errors."],
                    ['from' => 'agent',    'body' => 'Hey Tom! Go to Settings → API → Allowed Origins and add your domain there.', 'user' => $agent1],
                    ['from' => 'customer', 'body' => 'Found it! Adding the domain now...'],
                    ['from' => 'customer', 'body' => 'That fixed it! Thank you! 🎉'],
                    ['from' => 'agent',    'body' => 'Awesome! Glad that sorted it 😊', 'user' => $agent1],
                ],
            ],
            [
                'mailbox' => $chatMailbox,
                'customer' => $customerModels[0],
                'subject' => 'Live chat: Pricing question',
                'status' => 'closed',
                'priority' => 'low',
                'assigned' => $agent1,
                'tags' => [],
                'channel' => 'chat',
                'threads' => [
                    ['from' => 'customer', 'body' => "What's the difference between Pro and Business plans?"],
                    ['from' => 'agent',    'body' => 'Pro is for teams up to 10 agents with 5 mailboxes. Business removes those limits and adds AI, automation, and priority support.', 'user' => $agent1],
                    ['from' => 'customer', 'body' => 'Business sounds right for us. Thanks!'],
                ],
            ],
            [
                'mailbox' => $chatMailbox,
                'customer' => $customerModels[2],
                'subject' => 'Live chat: Widget not loading on our site',
                'status' => 'open',
                'priority' => 'high',
                'assigned' => $agent3,
                'tags' => ['bug', 'integration'],
                'channel' => 'chat',
                'threads' => [
                    ['from' => 'customer', 'body' => "The live chat widget isn't appearing on our website. We copied the embed code but nothing shows up."],
                    ['from' => 'agent',    'body' => "Hi Priya! First, check your browser console for errors. Also make sure you're loading the widget after the DOM is ready. What framework is your site built with?", 'user' => $agent3],
                    ['from' => 'customer', 'body' => "We're using Next.js. Browser console shows: 'Uncaught ReferenceError: FusterAI is not defined'."],
                ],
            ],
            [
                'mailbox' => $chatMailbox,
                'customer' => $customerModels[10],
                'subject' => 'Live chat: Pre-sales question about AI features',
                'status' => 'closed',
                'priority' => 'normal',
                'assigned' => $agent2,
                'tags' => [],
                'channel' => 'chat',
                'threads' => [
                    ['from' => 'customer', 'body' => 'Does FusterAI automatically respond to common questions with AI?'],
                    ['from' => 'agent',    'body' => 'Great question! The AI suggests replies in real-time but agents review and send them. You can also set up automated responses for common questions using our automation rules. Want me to show you a demo?', 'user' => $agent2],
                    ['from' => 'customer', 'body' => 'That sounds perfect. Yes to the demo!'],
                    ['from' => 'agent',    'body' => "I'll book one for you — check your email for the calendar invite 🗓", 'user' => $agent2],
                ],
            ],

            // ── Spam ──────────────────────────────────────────────────────────────
            [
                'mailbox' => $supportMailbox,
                'customer' => $customerModels[5],
                'subject' => 'Congratulations! You have won a prize',
                'status' => 'spam',
                'priority' => 'low',
                'assigned' => null,
                'tags' => [],
                'channel' => 'email',
                'threads' => [
                    ['from' => 'customer', 'body' => 'Dear customer, you have been selected for a special prize. Click here to claim your reward...'],
                ],
            ],

            // ── Multi-turn complex cases ──────────────────────────────────────────
            [
                'mailbox' => $supportMailbox,
                'customer' => $customerModels[11],
                'subject' => 'Data retention policy compliance question',
                'status' => 'open',
                'priority' => 'normal',
                'assigned' => $admin,
                'tags' => ['security'],
                'channel' => 'email',
                'threads' => [
                    ['from' => 'customer', 'body' => "We're undergoing a SOC2 audit and need to understand your data retention policies. Specifically: how long do you retain conversation data, where is it stored, and can we request deletion?"],
                    ['from' => 'agent',    'body' => 'Hi Mark, great questions for compliance. Conversation data is retained for the lifetime of your account plus 30 days after cancellation. Data is stored in EU-West (AWS). You can request a full data export or deletion anytime from Settings → Privacy.', 'user' => $admin],
                    ['from' => 'customer', 'body' => 'Is there a Data Processing Agreement (DPA) we can sign?'],
                    ['from' => 'agent',    'body' => 'Yes! Our DPA is available at trust.fusterai.com/dpa — you can sign it electronically there. If you need custom DPA terms, contact legal@fusterai.com.', 'user' => $admin],
                    ['from' => 'customer', 'body' => 'Perfect, exactly what I needed. Signing now.'],
                ],
            ],
            [
                'mailbox' => $salesMailbox,
                'customer' => $customerModels[14],
                'subject' => 'Upgrade from Starter to Business plan',
                'status' => 'closed',
                'priority' => 'normal',
                'assigned' => $agent3,
                'tags' => ['billing', 'onboarding'],
                'channel' => 'email',
                'threads' => [
                    ['from' => 'customer', 'body' => "We've been on Starter for 3 months and we've grown to 15 agents. Time to upgrade to Business. What's the best way to do this?"],
                    ['from' => 'agent',    'body' => "Hi Alice! Congrats on the growth! To upgrade, go to Settings → Billing → Change Plan → Business. If you upgrade mid-cycle you'll be charged pro-rated. Since you've been a great customer, I'll apply a 20% discount on your first Business year.", 'user' => $agent3],
                    ['from' => 'customer', 'body' => "That's very generous! Upgrading now. Thanks Rachel!"],
                    ['from' => 'agent',    'body' => "Done! I've applied the discount to your account. Welcome to Business 🎉", 'user' => $agent3],
                ],
            ],
        ];

        Conversation::withoutSyncingToSearch(function () use ($conversations, $workspace, $tags, $admin) {
            foreach ($conversations as $convData) {
                // Skip if already exists (by subject + customer)
                $existing = Conversation::where('workspace_id', $workspace->id)
                    ->where('customer_id', $convData['customer']->id)
                    ->where('subject', $convData['subject'])
                    ->first();

                if ($existing) {
                    continue;
                }

                $conversation = Conversation::create([
                    'workspace_id' => $workspace->id,
                    'mailbox_id' => $convData['mailbox']->id,
                    'customer_id' => $convData['customer']->id,
                    'subject' => $convData['subject'],
                    'status' => $convData['status'],
                    'priority' => $convData['priority'],
                    'channel_type' => $convData['channel'],
                    'assigned_user_id' => $convData['assigned']?->id,
                    'last_reply_at' => now()->subMinutes(rand(5, 2880)),
                    'snoozed_until' => ($convData['snoozed'] ?? false) ? now()->addHours(rand(12, 48)) : null,
                ]);

                // Attach tags
                $tagIds = collect($convData['tags'])
                    ->map(fn ($t) => $tags[$t]?->id)
                    ->filter()
                    ->values()
                    ->toArray();

                if ($tagIds) {
                    $conversation->tags()->sync($tagIds);
                }

                // Create threads
                $baseTime = now()->subMinutes(rand(60, 2880));
                foreach ($convData['threads'] as $i => $thread) {
                    $isNote = $thread['from'] === 'note';
                    $isAgent = $thread['from'] === 'agent' || $isNote;

                    $conversation->threads()->create([
                        'user_id' => $isAgent ? ($thread['user']?->id ?? $admin->id) : null,
                        'customer_id' => $thread['from'] === 'customer' ? $convData['customer']->id : null,
                        'type' => $isNote ? 'note' : 'message',
                        'body' => '<p>'.e($thread['body']).'</p>',
                        'body_plain' => $thread['body'],
                        'source' => $convData['channel'],
                        'created_at' => $baseTime->copy()->addMinutes($i * rand(3, 20)),
                    ]);
                }
            }
        });

        // ── Knowledge Base ───────────────────────────────────────────────────────

        $kb = KnowledgeBase::firstOrCreate(
            ['workspace_id' => $workspace->id, 'name' => 'Help Center'],
            [
                'description' => 'FusterAI documentation and support articles',
                'active' => true,
            ]
        );

        $kbArticles = [
            ['title' => 'Getting started with FusterAI',      'content' => 'FusterAI is an AI-first helpdesk platform. This guide walks you through setting up your workspace, inviting team members, and connecting your first email mailbox.'],
            ['title' => 'How to configure email IMAP/SMTP',   'content' => 'To connect your email inbox, go to Settings → Mailboxes → New Mailbox. Enter your IMAP settings for receiving emails and SMTP settings for sending. FusterAI supports Gmail, Outlook, and any standard IMAP/SMTP provider.'],
            ['title' => 'Understanding AI reply suggestions',  'content' => 'FusterAI automatically generates reply suggestions using Claude AI. Suggestions appear in the AI panel on the right side of each conversation. You can accept, edit, or dismiss suggestions. AI context includes the full conversation history and your knowledge base.'],
            ['title' => 'Setting up automation rules',        'content' => 'Automation rules let you automatically route, tag, and reply to conversations. Go to Settings → Automation → New Rule. Choose a trigger (conversation created, reply received) and define conditions and actions.'],
            ['title' => 'SLA policies explained',             'content' => 'SLA (Service Level Agreement) policies define response and resolution time targets by ticket priority. Urgent tickets default to 1h first response / 4h resolution. You can customize policies in Settings → SLA.'],
            ['title' => 'Exporting conversation data',        'content' => "To export your data for compliance or migration: go to Settings → General → Export Data. You'll receive a download link via email within 15 minutes. The export includes all conversations, threads, customers, and attachments."],
            ['title' => 'Live chat widget installation',      'content' => 'Add the FusterAI live chat widget to your website by copying the embed code from Settings → Channels → Live Chat. Paste it before the closing </body> tag. The widget supports React, Next.js, and plain HTML.'],
            ['title' => 'REST API authentication',            'content' => 'Authenticate API requests using a Bearer token. Generate a personal access token from Settings → API → Personal Tokens. Pass it as: Authorization: Bearer <your-token>. Rate limit: 60 requests per minute.'],
        ];

        foreach ($kbArticles as $article) {
            $kb->documents()->firstOrCreate(
                ['kb_id' => $kb->id, 'title' => $article['title']],
                ['content' => $article['content'], 'source_url' => null]
            );
        }

        // ── Automation Rules ─────────────────────────────────────────────────────

        $rules = [
            [
                'name' => 'Auto-assign urgent to admin',
                'trigger' => 'conversation.created',
                'conditions' => [['field' => 'priority', 'operator' => 'equals', 'value' => 'urgent']],
                'actions' => [['type' => 'assign_to', 'value' => (string) $admin->id]],
                'active' => true,
            ],
            [
                'name' => 'Tag billing keywords',
                'trigger' => 'conversation.created',
                'conditions' => [['field' => 'subject', 'operator' => 'contains', 'value' => 'invoice']],
                'actions' => [['type' => 'add_tag', 'value' => 'billing']],
                'active' => true,
            ],
            [
                'name' => 'Auto-close spam conversations',
                'trigger' => 'conversation.created',
                'conditions' => [['field' => 'subject', 'operator' => 'contains', 'value' => 'unsubscribe']],
                'actions' => [['type' => 'set_status', 'value' => 'spam']],
                'active' => true,
            ],
            [
                'name' => 'Assign security tickets to admin',
                'trigger' => 'conversation.created',
                'conditions' => [['field' => 'subject', 'operator' => 'contains', 'value' => 'security']],
                'actions' => [['type' => 'assign_to', 'value' => (string) $admin->id], ['type' => 'add_tag', 'value' => 'security']],
                'active' => true,
            ],
            [
                'name' => 'Close resolved live chats',
                'trigger' => 'conversation.replied',
                'conditions' => [['field' => 'channel', 'operator' => 'equals', 'value' => 'chat']],
                'actions' => [['type' => 'set_status', 'value' => 'closed']],
                'active' => false,
            ],
        ];

        foreach ($rules as $i => $rule) {
            AutomationRule::firstOrCreate(
                ['workspace_id' => $workspace->id, 'name' => $rule['name']],
                [
                    'trigger' => $rule['trigger'],
                    'conditions' => $rule['conditions'],
                    'actions' => $rule['actions'],
                    'active' => $rule['active'],
                    'order' => $i + 1,
                ]
            );
        }

        // ── Modules ──────────────────────────────────────────────────────────────

        Module::firstOrCreate(
            ['alias' => 'SatisfactionSurvey'],
            [
                'name' => 'Satisfaction Survey',
                'version' => '1.0.0',
                'active' => false,
                'config' => ['delay_minutes' => 5],
            ]
        );

        Module::firstOrCreate(
            ['alias' => 'SlaManager'],
            [
                'name' => 'SLA Manager',
                'version' => '1.0.0',
                'active' => false,
                'config' => [],
            ]
        );

        $this->command->info('✓ Seeded: 1 workspace, 4 users, 3 mailboxes, 16 customers, 35 conversations, 10 tags, 5 automation rules, 8 KB articles, 2 modules');
    }
}
