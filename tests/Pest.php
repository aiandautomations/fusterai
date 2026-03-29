<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()
    ->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

// ── Custom helpers ────────────────────────────────────────────────────────────

function workspace(): \App\Models\Workspace
{
    return \App\Models\Workspace::factory()->create();
}

function agentUser(?\App\Models\Workspace $workspace = null): \App\Models\User
{
    $workspace ??= workspace();
    return \App\Models\User::factory()->create([
        'workspace_id' => $workspace->id,
        'role'         => 'agent',
    ]);
}

function managerUser(?\App\Models\Workspace $workspace = null): \App\Models\User
{
    $workspace ??= workspace();
    return \App\Models\User::factory()->create([
        'workspace_id' => $workspace->id,
        'role'         => 'manager',
    ]);
}

function adminUser(?\App\Models\Workspace $workspace = null): \App\Models\User
{
    $workspace ??= workspace();
    return \App\Models\User::factory()->create([
        'workspace_id' => $workspace->id,
        'role'         => 'admin',
    ]);
}
