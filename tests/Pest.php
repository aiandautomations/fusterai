<?php

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()
    ->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

// ── Custom helpers ────────────────────────────────────────────────────────────

function workspace(): Workspace
{
    return Workspace::factory()->create();
}

function agentUser(?Workspace $workspace = null): User
{
    $workspace ??= workspace();

    return User::factory()->create([
        'workspace_id' => $workspace->id,
        'role' => 'agent',
    ]);
}

function managerUser(?Workspace $workspace = null): User
{
    $workspace ??= workspace();

    return User::factory()->create([
        'workspace_id' => $workspace->id,
        'role' => 'manager',
    ]);
}

function adminUser(?Workspace $workspace = null): User
{
    $workspace ??= workspace();

    return User::factory()->create([
        'workspace_id' => $workspace->id,
        'role' => 'admin',
    ]);
}
