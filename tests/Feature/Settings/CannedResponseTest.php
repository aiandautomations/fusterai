<?php

use App\Domains\Mailbox\Models\Mailbox;
use App\Models\CannedResponse;
use App\Models\Workspace;

beforeEach(function () {
    $this->workspace = Workspace::factory()->create();
    $this->user      = agentUser($this->workspace);
    $this->mailbox   = Mailbox::factory()->create(['workspace_id' => $this->workspace->id]);
});

test('agent can create a workspace-wide canned response', function () {
    $this->actingAs($this->user)
        ->post('/settings/canned-responses', [
            'name'    => 'Thanks for reaching out',
            'content' => '<p>Thank you for contacting us!</p>',
        ])
        ->assertRedirect();

    expect(CannedResponse::where('workspace_id', $this->workspace->id)->count())->toBe(1);
});

test('agent can create a mailbox-specific canned response', function () {
    $this->actingAs($this->user)
        ->post('/settings/canned-responses', [
            'name'       => 'Mailbox reply',
            'content'    => '<p>Hello</p>',
            'mailbox_id' => $this->mailbox->id,
        ])
        ->assertRedirect();

    expect(
        CannedResponse::where('mailbox_id', $this->mailbox->id)->exists()
    )->toBeTrue();
});

test('canned response cannot reference a mailbox from another workspace', function () {
    $other        = Workspace::factory()->create();
    $otherMailbox = Mailbox::factory()->create(['workspace_id' => $other->id]);

    $this->actingAs($this->user)
        ->post('/settings/canned-responses', [
            'name'       => 'Cross-workspace attempt',
            'content'    => '<p>Hello</p>',
            'mailbox_id' => $otherMailbox->id,
        ])
        ->assertSessionHasErrors('mailbox_id');
});

test('agent cannot update a canned response from another workspace', function () {
    $other    = Workspace::factory()->create();
    $response = CannedResponse::factory()->create(['workspace_id' => $other->id]);

    $this->actingAs($this->user)
        ->patch("/settings/canned-responses/{$response->id}", [
            'name'    => 'Hijacked',
            'content' => '<p>Hijacked content</p>',
        ])
        ->assertForbidden();

    expect($response->fresh()->name)->not->toBe('Hijacked');
});

test('agent cannot delete a canned response from another workspace', function () {
    $other    = Workspace::factory()->create();
    $response = CannedResponse::factory()->create(['workspace_id' => $other->id]);

    $this->actingAs($this->user)
        ->delete("/settings/canned-responses/{$response->id}")
        ->assertForbidden();

    expect(CannedResponse::find($response->id))->not->toBeNull();
});

// ── index ──────────────────────────────────────────────────────────────────────

test('agent can view the canned responses settings page', function () {
    $this->actingAs($this->user)
        ->get('/settings/canned-responses')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Settings/CannedResponses'));
});

// ── search ─────────────────────────────────────────────────────────────────────

test('search returns matching canned responses', function () {
    CannedResponse::create([
        'workspace_id' => $this->workspace->id,
        'name'         => 'Shipping delay',
        'content'      => 'We apologise for the delay.',
    ]);

    $this->actingAs($this->user)
        ->getJson('/canned-responses/search?q=shipping')
        ->assertOk()
        ->assertJsonCount(1);
});

test('search returns mailbox-specific and workspace-wide responses', function () {
    CannedResponse::create(['workspace_id' => $this->workspace->id, 'name' => 'Global reply', 'content' => 'Hi']);
    CannedResponse::create(['workspace_id' => $this->workspace->id, 'mailbox_id' => $this->mailbox->id, 'name' => 'Mailbox reply', 'content' => 'Hi from mailbox']);

    $response = $this->actingAs($this->user)
        ->getJson("/canned-responses/search?q=reply&mailbox_id={$this->mailbox->id}")
        ->assertOk();

    expect(count($response->json()))->toBe(2);
});

test('search results are scoped to workspace', function () {
    $other = Workspace::factory()->create();
    CannedResponse::create(['workspace_id' => $other->id, 'name' => 'Secret', 'content' => 'Top secret reply']);

    $this->actingAs($this->user)
        ->getJson('/canned-responses/search?q=secret')
        ->assertOk()
        ->assertJsonCount(0);
});
