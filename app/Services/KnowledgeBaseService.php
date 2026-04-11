<?php

namespace App\Services;

use App\Domains\AI\Jobs\FetchUrlAndIndexJob;
use App\Domains\AI\Jobs\IndexKbDocumentJob;
use App\Domains\AI\Models\KbDocument;
use App\Domains\AI\Models\KnowledgeBase;

class KnowledgeBaseService
{
    public function createDocument(KnowledgeBase $kb, array $validated): KbDocument
    {
        /** @var KbDocument $document */
        $document = $kb->documents()->create($validated);

        IndexKbDocumentJob::dispatch($document);

        return $document;
    }

    public function updateDocument(KbDocument $document, array $validated): void
    {
        $document->update($validated);

        IndexKbDocumentJob::dispatch($document);
    }

    public function deleteDocument(KbDocument $document): void
    {
        $document->delete();
    }

    public function importUrl(KnowledgeBase $kb, string $url): void
    {
        FetchUrlAndIndexJob::dispatch($kb, $url);
    }
}
