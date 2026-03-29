<?php

namespace App\Domains\AI\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KnowledgeBase extends Model
{
    protected $table = 'knowledge_bases';

    protected $fillable = ['workspace_id', 'name', 'description', 'active'];

    protected $casts = ['active' => 'boolean'];

    public function documents(): HasMany
    {
        return $this->hasMany(KbDocument::class, 'kb_id');
    }
}
