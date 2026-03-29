<?php

namespace App\Domains\Customer\Models;

use App\Domains\Conversation\Models\Conversation;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    protected static function newFactory(): CustomerFactory
    {
        return CustomerFactory::new();
    }
    protected $fillable = [
        'workspace_id',
        'name',
        'email',
        'phone',
        'avatar',
        'company',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    // ── Relations ────────────────────────────────────────────────

    /** @return HasMany<Conversation, $this> */
    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    // ── Scopes ───────────────────────────────────────────────────

    public function scopeSearch(Builder $query, string $term): Builder
    {
        return $query->where(function (Builder $q) use ($term) {
            $q->where('name', 'ilike', "%{$term}%")
              ->orWhere('email', 'ilike', "%{$term}%")
              ->orWhere('company', 'ilike', "%{$term}%");
        });
    }

    // ── Static helpers ────────────────────────────────────────────

    /**
     * Find or create a customer by workspace + email (email-based channels).
     * Centralises the firstOrCreate pattern used across inbound email, API, and webhook jobs.
     */
    public static function resolveOrCreate(int $workspaceId, string $email, string $name = ''): static
    {
        /** @var static $instance */
        $instance = static::firstOrCreate(
            ['workspace_id' => $workspaceId, 'email' => strtolower($email)],
            ['name' => $name ?: explode('@', $email)[0]],
        );

        return $instance;
    }

    /**
     * Find or create a customer by workspace + phone (WhatsApp / SMS channels).
     */
    public static function resolveOrCreateByPhone(int $workspaceId, string $phone, string $name = ''): static
    {
        /** @var static $instance */
        $instance = static::firstOrCreate(
            ['workspace_id' => $workspaceId, 'phone' => $phone],
            ['name' => $name ?: $phone],
        );

        return $instance;
    }

    // ── Helpers ──────────────────────────────────────────────────

    public function initials(): string
    {
        $words = explode(' ', $this->name);
        return implode('', array_map(fn($w) => strtoupper($w[0] ?? ''), array_slice($words, 0, 2)));
    }
}
