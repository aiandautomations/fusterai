<?php

namespace App\Models;

use App\Domains\Conversation\Models\Conversation;
use App\Domains\Mailbox\Models\Mailbox;
use App\Notifications\InviteUserNotification;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements \Laravel\Passport\Contracts\OAuthenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasApiTokens, HasRoles, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email', 'role'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('user');
    }

    protected $fillable = [
        'workspace_id',
        'name',
        'email',
        'password',
        'role',
        'avatar',
        'signature',
        'preferences',
        'last_active_at',
        'status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_active_at'    => 'datetime',
            'password'          => 'hashed',
            'preferences'       => 'array',
        ];
    }

    // ── Relations ────────────────────────────────────────────────

    /** @return BelongsToMany<Mailbox, $this> */
    public function mailboxes(): BelongsToMany
    {
        return $this->belongsToMany(Mailbox::class, 'mailbox_user')
            ->withPivot('permissions');
    }

    public function assignedConversations(): HasMany
    {
        return $this->hasMany(Conversation::class, 'assigned_user_id');
    }

    public function followedConversations(): BelongsToMany
    {
        return $this->belongsToMany(Conversation::class, 'followers');
    }

    // ── Role hierarchy ───────────────────────────────────────────

    /** Ordered from least to most privileged. */
    const ROLE_HIERARCHY = ['agent' => 0, 'manager' => 1, 'admin' => 2, 'super_admin' => 3];

    /** Returns true if this user's role is at least as privileged as $minRole. */
    public function hasMinRole(string $minRole): bool
    {
        $levels = self::ROLE_HIERARCHY;
        return ($levels[$this->role] ?? 0) >= ($levels[$minRole] ?? 0);
    }

    public function isAgent(): bool      { return $this->role === 'agent'; }
    public function isManager(): bool    { return $this->hasMinRole('manager'); }
    public function isAdmin(): bool      { return $this->hasMinRole('admin'); }
    public function isSuperAdmin(): bool { return $this->role === 'super_admin'; }

    // ── Notifications ────────────────────────────────────────────

    /**
     * Send the invite notification instead of the default password reset email,
     * so the link goes to /invite/accept/{token} rather than /reset-password/{token}.
     */
    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->notify(new InviteUserNotification($token));
    }

    // ── Helpers ──────────────────────────────────────────────────

    public function initials(): string
    {
        $words = explode(' ', $this->name);
        return implode('', array_map(fn($w) => strtoupper($w[0] ?? ''), array_slice($words, 0, 2)));
    }

    public function canAccessMailbox(Mailbox $mailbox): bool
    {
        if ($this->isAdmin()) return true; // admin+ bypass mailbox restriction
        return $this->mailboxes()->where('mailbox_id', $mailbox->id)->exists();
    }
}
