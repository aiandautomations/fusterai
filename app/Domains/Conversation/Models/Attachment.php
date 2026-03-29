<?php

namespace App\Domains\Conversation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Attachment extends Model
{
    protected $fillable = ['thread_id', 'filename', 'path', 'mime_type', 'size'];

    protected $appends = ['url'];

    public function thread(): BelongsTo
    {
        return $this->belongsTo(Thread::class);
    }

    public function getUrlAttribute(): string
    {
        return Storage::url($this->path);
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    public function formattedSize(): string
    {
        $bytes = $this->size;
        if ($bytes < 1024) return "{$bytes} B";
        if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
        return round($bytes / 1048576, 1) . ' MB';
    }
}
