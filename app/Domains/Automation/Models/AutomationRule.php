<?php

namespace App\Domains\Automation\Models;

use App\Models\Workspace;
use Database\Factories\AutomationRuleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutomationRule extends Model
{
    /** @use HasFactory<AutomationRuleFactory> */
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'name',
        'description',
        'active',
        'trigger',
        'conditions',
        'actions',
        'run_count',
        'last_run_at',
        'order',
    ];

    protected $casts = [
        'active'      => 'boolean',
        'conditions'  => 'array',
        'actions'     => 'array',
        'last_run_at' => 'datetime',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    protected static function newFactory(): AutomationRuleFactory
    {
        return AutomationRuleFactory::new();
    }
}
