<?php

namespace App\Http\Controllers\AI;

use App\Domains\AI\Models\KbDocument;
use App\Domains\AI\Models\KnowledgeBase;
use App\Http\Controllers\Controller;
use App\Services\KnowledgeBaseService;
use App\Support\SsrfGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class KnowledgeBaseController extends Controller
{
    public function __construct(private KnowledgeBaseService $service) {}
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', KnowledgeBase::class);

        $kbs = KnowledgeBase::where('workspace_id', $request->user()->workspace_id)
            ->withCount('documents')
            ->get();

        return Inertia::render('AI/KnowledgeBase/Index', [
            'knowledgeBases' => $kbs,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', KnowledgeBase::class);

        return Inertia::render('AI/KnowledgeBase/CreateKb');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', KnowledgeBase::class);

        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
        ]);

        $kb = KnowledgeBase::create([
            'workspace_id' => $request->user()->workspace_id,
            'name'         => $validated['name'],
            'description'  => $validated['description'] ?? null,
            'active'       => true,
        ]);

        return redirect()->route('ai.knowledge-bases.show', $kb)
            ->with('success', 'Knowledge base created.');
    }

    public function show(Request $request, KnowledgeBase $knowledgeBase): Response
    {
        $this->authorize('view', $knowledgeBase);

        $documents = $knowledgeBase->documents()
            ->orderBy('title')
            ->get(['id', 'title', 'indexed_at', 'meta', 'created_at', 'updated_at']);

        return Inertia::render('AI/KnowledgeBase/Show', [
            'kb'        => $knowledgeBase,
            'documents' => $documents,
        ]);
    }

    public function edit(Request $request, KnowledgeBase $knowledgeBase): Response
    {
        $this->authorize('update', $knowledgeBase);

        return Inertia::render('AI/KnowledgeBase/EditKb', [
            'kb' => $knowledgeBase,
        ]);
    }

    public function update(Request $request, KnowledgeBase $knowledgeBase): RedirectResponse
    {
        $this->authorize('update', $knowledgeBase);

        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'active'      => 'boolean',
        ]);

        $knowledgeBase->update($validated);

        return back()->with('success', 'Knowledge base updated.');
    }

    public function destroy(Request $request, KnowledgeBase $knowledgeBase): RedirectResponse
    {
        $this->authorize('delete', $knowledgeBase);

        $knowledgeBase->delete();

        return redirect()->route('ai.kb.index')->with('success', 'Knowledge base deleted.');
    }

    // ── Document sub-resource ─────────────────────────────────────────────────

    public function createDocument(Request $request, KnowledgeBase $knowledgeBase): Response
    {
        $this->authorize('update', $knowledgeBase);

        return Inertia::render('AI/KnowledgeBase/EditDocument', [
            'kb'       => $knowledgeBase,
            'document' => null,
        ]);
    }

    public function storeDocument(Request $request, KnowledgeBase $knowledgeBase): RedirectResponse
    {
        $this->authorize('update', $knowledgeBase);

        $validated = $request->validate([
            'title'   => 'required|string|max:255',
            'content' => 'required|string',
        ]);

        $this->service->createDocument($knowledgeBase, $validated);

        return redirect()->route('ai.knowledge-bases.show', $knowledgeBase)
            ->with('success', 'Document saved.');
    }

    public function editDocument(Request $request, KnowledgeBase $knowledgeBase, KbDocument $document): Response
    {
        $this->authorize('update', $knowledgeBase);
        abort_unless($document->kb_id === $knowledgeBase->id, 403);

        return Inertia::render('AI/KnowledgeBase/EditDocument', [
            'kb'       => $knowledgeBase,
            'document' => $document,
        ]);
    }

    public function updateDocument(Request $request, KnowledgeBase $knowledgeBase, KbDocument $document): RedirectResponse
    {
        $this->authorize('update', $knowledgeBase);
        abort_unless($document->kb_id === $knowledgeBase->id, 403);

        $validated = $request->validate([
            'title'   => 'required|string|max:255',
            'content' => 'required|string',
        ]);

        $this->service->updateDocument($document, $validated);

        return redirect()->route('ai.knowledge-bases.show', $knowledgeBase)
            ->with('success', 'Document updated.');
    }

    public function importUrl(Request $request, KnowledgeBase $knowledgeBase): \Illuminate\Http\JsonResponse
    {
        $this->authorize('update', $knowledgeBase);

        $validated = $request->validate([
            'url' => ['required', 'url', 'max:2048'],
        ]);

        try {
            SsrfGuard::validate($validated['url']);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $this->service->importUrl($knowledgeBase, $validated['url']);

        return response()->json(['status' => 'queued']);
    }

    public function destroyDocument(Request $request, KnowledgeBase $knowledgeBase, KbDocument $document): RedirectResponse
    {
        $this->authorize('update', $knowledgeBase);
        abort_unless($document->kb_id === $knowledgeBase->id, 403);

        $this->service->deleteDocument($document);

        return redirect()->route('ai.knowledge-bases.show', $knowledgeBase)
            ->with('success', 'Document deleted.');
    }
}
