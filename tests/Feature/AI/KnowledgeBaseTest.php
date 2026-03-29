<?php

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
