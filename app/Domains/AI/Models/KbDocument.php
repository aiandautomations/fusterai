<?php

namespace App\Domains\AI\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property array<int, float>|null $embedding
 */
class KbDocument extends Model
{
    protected $table = 'kb_documents';

    protected $fillable = ['kb_id', 'title', 'content', 'embedding', 'source_url', 'meta', 'indexed_at'];

    protected $casts = [
        'meta' => 'array',
        'indexed_at' => 'datetime',
    ];

    /** @return BelongsTo<KnowledgeBase, $this> */
    public function knowledgeBase(): BelongsTo
    {
        return $this->belongsTo(KnowledgeBase::class, 'kb_id');
    }
}
