<?php

namespace Modules\CustomerPortal\Http\Controllers;

use App\Domains\AI\Models\KbDocument;
use App\Domains\AI\Models\KnowledgeBase;
use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Inertia\Inertia;
use Inertia\Response;

class PortalKnowledgeBaseController extends Controller
{
    public function index(Workspace $workspace): Response
    {
        $kb = KnowledgeBase::where('workspace_id', $workspace->id)
            ->where('active', true)
            ->first();

        $documents = $kb
            ? KbDocument::where('kb_id', $kb->id)
                ->whereNotNull('indexed_at')
                ->orderBy('title')
                ->get(['id', 'title', 'content', 'indexed_at'])
                ->map(fn ($d) => [
                    'id' => $d->id,
                    'title' => $d->title,
                    'excerpt' => str($d->content)->limit(160)->toString(),
                    'indexed_at' => $d->indexed_at?->toISOString(),
                ])
            : [];

        return Inertia::render('Portal/KnowledgeBase/Index', [
            'workspace' => $this->workspaceProps($workspace),
            'kb' => $kb ? ['name' => $kb->name, 'description' => $kb->description] : null,
            'documents' => $documents,
        ]);
    }

    public function show(Workspace $workspace, KbDocument $document): Response
    {
        $kb = KnowledgeBase::where('workspace_id', $workspace->id)
            ->where('active', true)
            ->where('id', $document->kb_id)
            ->firstOrFail();

        return Inertia::render('Portal/KnowledgeBase/Show', [
            'workspace' => $this->workspaceProps($workspace),
            'kb' => ['name' => $kb->name],
            'document' => [
                'id' => $document->id,
                'title' => $document->title,
                'content' => $document->content,
                'source_url' => $document->source_url,
                'indexed_at' => $document->indexed_at?->toISOString(),
            ],
        ]);
    }

    private function workspaceProps(Workspace $workspace): array
    {
        $portal = $workspace->settings['portal'] ?? [];

        return [
            'name' => $portal['name'] ?? $workspace->name.' Support',
            'slug' => $workspace->slug,
        ];
    }
}
