<?php

namespace Modules\SlaManager\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SlaPolicy extends Model
{
    protected $fillable = [
        'workspace_id',
        'name',
        'priority',
        'first_response_minutes',
        'resolution_minutes',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function slaStatuses(): HasMany
    {
        return $this->hasMany(SlaStatus::class);
    }

    public function getFirstResponseLabelAttribute(): string
    {
        return self::minutesToLabel($this->first_response_minutes);
    }

    public function getResolutionLabelAttribute(): string
    {
        return self::minutesToLabel($this->resolution_minutes);
    }

    public static function minutesToLabel(int $minutes): string
    {
        if ($minutes < 60) {
            return "{$minutes}m";
        }
        $hours = intdiv($minutes, 60);
        $remaining = $minutes % 60;

        return $remaining > 0 ? "{$hours}h {$remaining}m" : "{$hours}h";
    }

    /**
     * Default SLA targets by priority (in minutes).
     */
    public static function defaults(): array
    {
        return [
            'urgent' => ['first_response_minutes' => 60,   'resolution_minutes' => 240],
            'high' => ['first_response_minutes' => 240,  'resolution_minutes' => 1440],
            'normal' => ['first_response_minutes' => 480,  'resolution_minutes' => 2880],
            'low' => ['first_response_minutes' => 1440, 'resolution_minutes' => 4320],
        ];
    }
}
