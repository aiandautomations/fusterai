<?php

use App\Domains\Channel\Jobs\ProcessWhatsAppWebhookJob;
use App\Domains\Mailbox\Models\Channel;
use App\Domains\Mailbox\Models\Mailbox;
use App\Models\Workspace;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
    $this->workspace = Workspace::factory()->create();
    $this->mailbox = Mailbox::factory()->create([
        'workspace_id' => $this->workspace->id,
        'channel_type' => 'whatsapp',
        'webhook_token' => 'test-token-abc',
    ]);
});

test('meta webhook verification succeeds with correct token', function () {
    $this->get('/api/webhooks/whatsapp/test-token-abc?'.http_build_query([
        'hub_mode' => 'subscribe',
        'hub_challenge' => '1234567890',
        'hub_verify_token' => 'test-token-abc',
    ]))->assertOk()->assertSee('1234567890');
});

test('meta webhook verification fails with wrong token', function () {
    $this->get('/api/webhooks/whatsapp/test-token-abc?'.http_build_query([
        'hub_mode' => 'subscribe',
        'hub_challenge' => '1234567890',
        'hub_verify_token' => 'wrong-token',
    ]))->assertForbidden();
});

test('meta webhook verification fails for unknown token', function () {
    $this->get('/api/webhooks/whatsapp/nonexistent?'.http_build_query([
        'hub_mode' => 'subscribe',
        'hub_challenge' => '123',
        'hub_verify_token' => 'nonexistent',
    ]))->assertNotFound();
});

test('inbound whatsapp message dispatches processing job', function () {
    $secret = 'test-app-secret';
    Channel::create([
        'mailbox_id' => $this->mailbox->id,
        'type' => 'whatsapp',
        'config' => ['app_secret' => $secret],
        'active' => true,
    ]);

    $payload = [
        'entry' => [[
            'changes' => [[
                'value' => [
                    'messages' => [[
                        'from' => '15551234567',
                        'id' => 'wamid.abc123',
                        'type' => 'text',
                        'text' => ['body' => 'Hello support!'],
                    ]],
                    'contacts' => [[
                        'profile' => ['name' => 'Test Customer'],
                        'wa_id' => '15551234567',
                    ]],
                ],
            ]],
        ]],
    ];

    $body = json_encode($payload);
    $signature = 'sha256='.hash_hmac('sha256', $body, $secret);

    $this->postJson('/api/webhooks/whatsapp/test-token-abc', $payload, [
        'X-Hub-Signature-256' => $signature,
    ])
        ->assertOk()
        ->assertJson(['status' => 'ok']);

    Queue::assertPushed(ProcessWhatsAppWebhookJob::class);
});

test('inbound webhook with invalid token returns 403', function () {
    $this->postJson('/api/webhooks/whatsapp/bad-token', [])
        ->assertStatus(403);
});
