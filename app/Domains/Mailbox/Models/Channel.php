<?php

namespace App\Domains\Mailbox\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

/**
 * @property int $id
 * @property int $mailbox_id
 * @property string $type
 * @property string|null $name
 * @property array|null $config
 * @property bool $active
 */
class Channel extends Model
{
    protected $fillable = ['mailbox_id', 'type', 'name', 'config', 'active'];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function mailbox(): BelongsTo
    {
        return $this->belongsTo(Mailbox::class);
    }

    public function getConfigAttribute(?string $value): ?array
    {
        if (! $value) {
            return null;
        }
        try {
            return json_decode(Crypt::decryptString($value), true);
        } catch (\Exception) {
            return null;
        }
    }

    public function setConfigAttribute(?array $value): void
    {
        $this->attributes['config'] = $value
            ? Crypt::encryptString(json_encode($value))
            : null;
    }
}
