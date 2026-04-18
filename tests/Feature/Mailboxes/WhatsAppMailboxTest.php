<?php

use App\Domains\Mailbox\Models\Channel;
use App\Domains\Mailbox\Models\Mailbox;
use App\Models\Workspace;

beforeEach(function () {
    $this->workspace = Workspace::factory()->create();
    $this->admin = adminUser($this->workspace);
    $this->agent = agentUser($this->workspace);
    $this->mailbox = Mailbox::factory()->create(['workspace_id' => $this->workspace->id]);
});

// ── show ───────────────────────────────────────────────────────────────────────

test('admin can view the WhatsApp setup page', function () {
    $this->actingAs($this->admin)
        ->get("/mailboxes/{$this->mailbox->id}/whatsapp")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Mailboxes/WhatsAppSetup'));
});

test('agent cannot view the WhatsApp setup page', function () {
    $this->actingAs($this->agent)
        ->get("/mailboxes/{$this->mailbox->id}/whatsapp")
        ->assertForbidden();
});

test('cannot view WhatsApp setup for another workspace mailbox', function () {
    $other = Workspace::factory()->create();
    $mailbox = Mailbox::factory()->create(['workspace_id' => $other->id]);

    $this->actingAs($this->admin)
        ->get("/mailboxes/{$mailbox->id}/whatsapp")
        ->assertForbidden();
});

// ── update ─────────────────────────────────────────────────────────────────────

test('admin can save WhatsApp credentials', function () {
    $this->actingAs($this->admin)
        ->post("/mailboxes/{$this->mailbox->id}/whatsapp", [
            'phone_number_id' => '12345678',
            'access_token' => 'EAAtest',
            'app_secret' => 'secret123',
        ])
        ->assertRedirect();

    expect(
        Channel::where('mailbox_id', $this->mailbox->id)->where('type', 'whatsapp')->exists()
    )->toBeTrue();
});

test('updating credentials is idempotent — only one channel record is created', function () {
    $this->actingAs($this->admin)
        ->post("/mailboxes/{$this->mailbox->id}/whatsapp", [
            'phone_number_id' => '111',
            'access_token' => 'token1',
            'app_secret' => 'secret1',
        ]);

    $this->actingAs($this->admin)
        ->post("/mailboxes/{$this->mailbox->id}/whatsapp", [
            'phone_number_id' => '222',
            'access_token' => 'token2',
            'app_secret' => 'secret2',
        ]);

    expect(
        Channel::where('mailbox_id', $this->mailbox->id)->where('type', 'whatsapp')->count()
    )->toBe(1);
});

test('agent cannot save WhatsApp credentials', function () {
    $this->actingAs($this->agent)
        ->post("/mailboxes/{$this->mailbox->id}/whatsapp", [
            'phone_number_id' => '12345678',
            'access_token' => 'EAAtest',
            'app_secret' => 'secret123',
        ])
        ->assertForbidden();
});

test('all WhatsApp credential fields are required', function () {
    $this->actingAs($this->admin)
        ->post("/mailboxes/{$this->mailbox->id}/whatsapp", [])
        ->assertSessionHasErrors(['phone_number_id', 'access_token', 'app_secret']);
});
