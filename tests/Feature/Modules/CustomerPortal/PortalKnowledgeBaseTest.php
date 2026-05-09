<?php

use App\Domains\AI\Models\KbDocument;
use App\Domains\AI\Models\KnowledgeBase;
use App\Domains\Customer\Models\Customer;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->workspace = Workspace::factory()->create([
        'settings' => ['portal' => ['enabled' => true]],
    ]);

    DB::table('modules')->updateOrInsert(
        ['alias' => 'CustomerPortal'],
        ['name' => 'Customer Portal', 'active' => true, 'version' => '1.0.0', 'config' => '[]', 'created_at' => now(), 'updated_at' => now()],
    );

    $this->customer = Customer::factory()->create(['workspace_id' => $this->workspace->id]);
});

test('customer can view knowledge base index', function () {
    $kb = KnowledgeBase::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'Help Center',
        'description' => 'Guides and articles',
        'active' => true,
    ]);

    KbDocument::create([
        'kb_id' => $kb->id,
        'title' => 'Getting started',
        'content' => 'Welcome to our platform. Here is how to get started...',
        'indexed_at' => now(),
    ]);

    $this->actingAs($this->customer, 'customer_portal')
        ->get(route('portal.kb.index', $this->workspace->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Portal/KnowledgeBase/Index')
            ->has('documents', 1)
            ->where('documents.0.title', 'Getting started')
        );
});

test('kb index shows empty state when no active kb', function () {
    $this->actingAs($this->customer, 'customer_portal')
        ->get(route('portal.kb.index', $this->workspace->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Portal/KnowledgeBase/Index')
            ->where('kb', null)
            ->has('documents', 0)
        );
});

test('inactive knowledge base is not shown in portal', function () {
    KnowledgeBase::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'Internal Docs',
        'active' => false,
    ]);

    $this->actingAs($this->customer, 'customer_portal')
        ->get(route('portal.kb.index', $this->workspace->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('kb', null));
});

test('non-indexed documents are excluded from kb index', function () {
    $kb = KnowledgeBase::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'Help Center',
        'active' => true,
    ]);

    KbDocument::create([
        'kb_id' => $kb->id,
        'title' => 'Draft article',
        'content' => 'Not published yet.',
        'indexed_at' => null,
    ]);

    $this->actingAs($this->customer, 'customer_portal')
        ->get(route('portal.kb.index', $this->workspace->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('documents', 0));
});

test('customer can view a kb document', function () {
    $kb = KnowledgeBase::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'Help Center',
        'active' => true,
    ]);

    $doc = KbDocument::create([
        'kb_id' => $kb->id,
        'title' => 'Password reset guide',
        'content' => 'To reset your password, click the Forgot Password link.',
        'indexed_at' => now(),
    ]);

    $this->actingAs($this->customer, 'customer_portal')
        ->get(route('portal.kb.show', [$this->workspace->slug, $doc->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Portal/KnowledgeBase/Show')
            ->where('document.title', 'Password reset guide')
        );
});

test('customer cannot view document from another workspaces kb', function () {
    $otherWorkspace = Workspace::factory()->create([
        'settings' => ['portal' => ['enabled' => true]],
    ]);
    $kb = KnowledgeBase::create([
        'workspace_id' => $otherWorkspace->id,
        'name' => 'Other KB',
        'active' => true,
    ]);
    $doc = KbDocument::create([
        'kb_id' => $kb->id,
        'title' => 'Private article',
        'content' => 'Confidential.',
        'indexed_at' => now(),
    ]);

    $this->actingAs($this->customer, 'customer_portal')
        ->get(route('portal.kb.show', [$this->workspace->slug, $doc->id]))
        ->assertNotFound();
});
