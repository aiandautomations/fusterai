<?php

use App\Models\Workspace;

beforeEach(function () {
    $this->workspace = Workspace::factory()->create();
});

test('reports require manager role', function () {
    $agent = agentUser($this->workspace);

    $this->actingAs($agent)->get('/reports')->assertForbidden();
});

test('manager can view reports page', function () {
    $manager = managerUser($this->workspace);

    $this->actingAs($manager)
        ->get('/reports')
        ->assertOk()
        ->assertInertia(fn ($p) => $p
            ->component('Reports/Index')
            ->has('stats')
            ->has('days')
        );
});

test('admin can also view reports page', function () {
    $admin = adminUser($this->workspace);

    $this->actingAs($admin)
        ->get('/reports')
        ->assertOk();
});

test('reports page accepts days filter', function () {
    $manager = managerUser($this->workspace);

    $this->actingAs($manager)
        ->get('/reports?days=7')
        ->assertOk()
        ->assertInertia(fn ($p) => $p->where('days', 7));
});

test('invalid days value falls back to 30', function () {
    $manager = managerUser($this->workspace);

    $this->actingAs($manager)
        ->get('/reports?days=999')
        ->assertOk()
        ->assertInertia(fn ($p) => $p->where('days', 30));
});

test('stats response contains all expected keys', function () {
    $manager = managerUser($this->workspace);

    $this->actingAs($manager)
        ->get('/reports')
        ->assertOk()
        ->assertInertia(fn ($p) => $p
            ->has('stats.total')
            ->has('stats.open')
            ->has('stats.pending')
            ->has('stats.closed')
            ->has('stats.trend')
            ->has('stats.by_channel')
            ->has('stats.by_mailbox')
            ->has('stats.by_priority')
            ->has('stats.top_agents')
            ->has('stats.avg_resolution_hours')
        );
});

test('allowed days values are 7, 14, 30, 90', function () {
    $manager = managerUser($this->workspace);

    foreach ([7, 14, 30, 90] as $days) {
        $this->actingAs($manager)
            ->get("/reports?days={$days}")
            ->assertOk()
            ->assertInertia(fn ($p) => $p->where('days', $days));
    }
});
