<?php

use App\Domains\AI\Jobs\FetchUrlAndIndexJob;
use App\Domains\AI\Jobs\IndexKbDocumentJob;
use App\Domains\AI\Models\KbDocument;
use App\Domains\AI\Models\KnowledgeBase;
use App\Models\Workspace;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->workspace = Workspace::factory()->create();
    $this->user      = managerUser($this->workspace);
});

test('agent can view knowledge bases', function () {
    KnowledgeBase::create([
        'workspace_id' => $this->workspace->id,
        'name'         => 'Product Docs',
        'active'       => true,
    ]);

    $this->actingAs($this->user)
        ->get('/ai/knowledge-base')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('AI/KnowledgeBase/Index')
            ->has('knowledgeBases', 1)
        );
});

test('agent can create a knowledge base', function () {
    $this->actingAs($this->user)
        ->post('/ai/knowledge-bases', [
            'name'        => 'Help Center',
            'description' => 'Customer FAQs',
        ])
        ->assertRedirect();

    expect(KnowledgeBase::where('name', 'Help Center')->exists())->toBeTrue();
});

test('knowledge base is scoped to workspace', function () {
    $other = Workspace::factory()->create();
    KnowledgeBase::create(['workspace_id' => $other->id, 'name' => 'Other KB', 'active' => true]);

    $this->actingAs($this->user)
        ->get('/ai/knowledge-base')
        ->assertInertia(fn ($page) => $page->has('knowledgeBases', 0));
});

test('creating a document dispatches IndexKbDocumentJob', function () {
    Queue::fake();

    $kb = KnowledgeBase::create([
        'workspace_id' => $this->workspace->id,
        'name'         => 'Docs',
        'active'       => true,
    ]);

    $this->actingAs($this->user)
        ->post("/ai/knowledge-bases/{$kb->id}/documents", [
            'title'   => 'Getting Started',
            'content' => 'This is the content.',
        ])
        ->assertRedirect();

    Queue::assertPushed(IndexKbDocumentJob::class);
    expect(KbDocument::where('kb_id', $kb->id)->exists())->toBeTrue();
});

test('agent cannot access another workspace knowledge base', function () {
    $other = Workspace::factory()->create();
    $kb    = KnowledgeBase::create(['workspace_id' => $other->id, 'name' => 'Other', 'active' => true]);

    $this->actingAs($this->user)
        ->get("/ai/knowledge-bases/{$kb->id}")
        ->assertForbidden();
});

// ── edit / update / destroy KB ─────────────────────────────────────────────────

test('manager can view the edit knowledge base page', function () {
    $kb = KnowledgeBase::create(['workspace_id' => $this->workspace->id, 'name' => 'Docs', 'active' => true]);

    $this->actingAs($this->user)
        ->get("/ai/knowledge-bases/{$kb->id}/edit")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('AI/KnowledgeBase/EditKb'));
});

test('manager can update a knowledge base', function () {
    $kb = KnowledgeBase::create(['workspace_id' => $this->workspace->id, 'name' => 'Old Name', 'active' => true]);

    $this->actingAs($this->user)
        ->patch("/ai/knowledge-bases/{$kb->id}", [
            'name'        => 'New Name',
            'description' => 'Updated description',
            'active'      => true,
        ])
        ->assertRedirect();

    expect($kb->fresh()->name)->toBe('New Name');
});

test('manager can delete a knowledge base', function () {
    $kb = KnowledgeBase::create(['workspace_id' => $this->workspace->id, 'name' => 'To Delete', 'active' => true]);

    $this->actingAs($this->user)
        ->delete("/ai/knowledge-bases/{$kb->id}")
        ->assertRedirect();

    expect(KnowledgeBase::find($kb->id))->toBeNull();
});

// ── edit / update / destroy document ──────────────────────────────────────────

test('manager can view the edit document page', function () {
    Queue::fake();

    $kb  = KnowledgeBase::create(['workspace_id' => $this->workspace->id, 'name' => 'Docs', 'active' => true]);
    $doc = $kb->documents()->create(['title' => 'Setup Guide', 'content' => 'Step 1...']);

    $this->actingAs($this->user)
        ->get("/ai/knowledge-bases/{$kb->id}/documents/{$doc->id}/edit")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('AI/KnowledgeBase/EditDocument'));
});

test('updating a document dispatches IndexKbDocumentJob', function () {
    Queue::fake();

    $kb  = KnowledgeBase::create(['workspace_id' => $this->workspace->id, 'name' => 'Docs', 'active' => true]);
    $doc = $kb->documents()->create(['title' => 'Old Title', 'content' => 'Old content']);

    $this->actingAs($this->user)
        ->patch("/ai/knowledge-bases/{$kb->id}/documents/{$doc->id}", [
            'title'   => 'New Title',
            'content' => 'New content here.',
        ])
        ->assertRedirect();

    Queue::assertPushed(\App\Domains\AI\Jobs\IndexKbDocumentJob::class);
    expect($doc->fresh()->title)->toBe('New Title');
});

test('manager can delete a document', function () {
    Queue::fake();

    $kb  = KnowledgeBase::create(['workspace_id' => $this->workspace->id, 'name' => 'Docs', 'active' => true]);
    $doc = $kb->documents()->create(['title' => 'Removable', 'content' => 'Content']);

    $this->actingAs($this->user)
        ->delete("/ai/knowledge-bases/{$kb->id}/documents/{$doc->id}")
        ->assertRedirect();

    expect(KbDocument::find($doc->id))->toBeNull();
});

// ── URL import ─────────────────────────────────────────────────────────────────

test('import url queues FetchUrlAndIndexJob for valid public URL', function () {
    Queue::fake();

    $kb = KnowledgeBase::create(['workspace_id' => $this->workspace->id, 'name' => 'Docs', 'active' => true]);

    $this->actingAs($this->user)
        ->postJson("/ai/knowledge-bases/{$kb->id}/documents/import-url", [
            'url' => 'https://example.com/article',
        ])
        ->assertOk()
        ->assertJson(['status' => 'queued']);

    Queue::assertPushed(FetchUrlAndIndexJob::class, fn ($job) => $job->url === 'https://example.com/article');
});

test('import url rejects private IP addresses with 422', function () {
    Queue::fake();

    $kb = KnowledgeBase::create(['workspace_id' => $this->workspace->id, 'name' => 'Docs', 'active' => true]);

    $this->actingAs($this->user)
        ->postJson("/ai/knowledge-bases/{$kb->id}/documents/import-url", [
            'url' => 'http://192.168.1.1/admin',
        ])
        ->assertStatus(422);

    Queue::assertNotPushed(FetchUrlAndIndexJob::class);
});

test('import url rejects invalid URL format', function () {
    Queue::fake();

    $kb = KnowledgeBase::create(['workspace_id' => $this->workspace->id, 'name' => 'Docs', 'active' => true]);

    $this->actingAs($this->user)
        ->postJson("/ai/knowledge-bases/{$kb->id}/documents/import-url", [
            'url' => 'not-a-url',
        ])
        ->assertStatus(422);

    Queue::assertNotPushed(FetchUrlAndIndexJob::class);
});

test('import url requires manager permissions', function () {
    $other = \App\Models\User::factory()->create(['workspace_id' => $this->workspace->id, 'role' => 'agent']);
    $kb    = KnowledgeBase::create(['workspace_id' => $this->workspace->id, 'name' => 'Docs', 'active' => true]);

    $this->actingAs($other)
        ->postJson("/ai/knowledge-bases/{$kb->id}/documents/import-url", [
            'url' => 'https://example.com/article',
        ])
        ->assertForbidden();
});

test('document from a different KB returns 403', function () {
    Queue::fake();

    $kb1 = KnowledgeBase::create(['workspace_id' => $this->workspace->id, 'name' => 'KB1', 'active' => true]);
    $kb2 = KnowledgeBase::create(['workspace_id' => $this->workspace->id, 'name' => 'KB2', 'active' => true]);
    $doc = $kb2->documents()->create(['title' => 'Wrong KB doc', 'content' => 'Content']);

    $this->actingAs($this->user)
        ->patch("/ai/knowledge-bases/{$kb1->id}/documents/{$doc->id}", [
            'title'   => 'Hijacked',
            'content' => 'Content',
        ])
        ->assertForbidden();
});
