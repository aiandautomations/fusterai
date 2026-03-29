<?php

use App\Ai\Tools\SearchKnowledgeBase;
use App\Domains\AI\Models\KbDocument;
use App\Domains\AI\Models\KnowledgeBase;
use App\Models\Workspace;
use Laravel\Ai\Tools\Request;

test('returns no knowledge base message when workspace has none', function () {
    $workspace = Workspace::factory()->create();
    $tool      = new SearchKnowledgeBase($workspace->id);
    $request   = new Request(['query' => 'billing']);

    $result = $tool->handle($request);

    expect($result)->toContain('No knowledge base found');
});

test('falls back to ilike search when no embeddings exist', function () {
    $workspace = Workspace::factory()->create();
    $kb        = KnowledgeBase::create(['workspace_id' => $workspace->id, 'name' => 'Docs', 'active' => true]);
    KbDocument::create(['kb_id' => $kb->id, 'title' => 'Billing FAQ', 'content' => 'You can pay by card.']);

    $tool    = new SearchKnowledgeBase($workspace->id);
    $request = new Request(['query' => 'billing']);

    $result = $tool->handle($request);

    expect($result)->toContain('Billing FAQ');
});

test('returns no results message when nothing matches', function () {
    $workspace = Workspace::factory()->create();
    $kb        = KnowledgeBase::create(['workspace_id' => $workspace->id, 'name' => 'Docs', 'active' => true]);
    KbDocument::create(['kb_id' => $kb->id, 'title' => 'Shipping', 'content' => 'We ship worldwide.']);

    $tool    = new SearchKnowledgeBase($workspace->id);
    $request = new Request(['query' => 'zzznomatch']);

    $result = $tool->handle($request);

    expect($result)->toContain('No relevant articles found');
});
