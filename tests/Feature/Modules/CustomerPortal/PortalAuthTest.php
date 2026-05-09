<?php

use App\Domains\Customer\Models\Customer;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Modules\CustomerPortal\Notifications\MagicLinkNotification;

beforeEach(function () {
    $this->workspace = Workspace::factory()->create([
        'settings' => ['portal' => ['enabled' => true]],
    ]);

    DB::table('modules')->updateOrInsert(
        ['alias' => 'CustomerPortal'],
        ['name' => 'Customer Portal', 'active' => true, 'version' => '1.0.0', 'config' => '[]', 'created_at' => now(), 'updated_at' => now()],
    );
});

test('portal login page loads when portal is enabled', function () {
    $this->get(route('portal.login', $this->workspace->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Portal/Login'));
});

test('portal returns 404 when portal is disabled', function () {
    $this->workspace->settings = ['portal' => ['enabled' => false]];
    $this->workspace->save();

    $this->get(route('portal.login', $this->workspace->slug))->assertNotFound();
});

test('portal returns 404 when module is inactive', function () {
    DB::table('modules')->where('alias', 'CustomerPortal')->update(['active' => false]);

    $this->get(route('portal.login', $this->workspace->slug))->assertNotFound();
});

test('customer can request a magic link', function () {
    Notification::fake();

    $this->post(route('portal.magic-link', $this->workspace->slug), [
        'email' => 'customer@example.com',
    ])->assertRedirect(route('portal.check-email', $this->workspace->slug));

    Notification::assertSentTo(
        Customer::where('email', 'customer@example.com')->first(),
        MagicLinkNotification::class,
    );
});

test('magic link request creates customer if they do not exist', function () {
    Notification::fake();

    $this->post(route('portal.magic-link', $this->workspace->slug), [
        'email' => 'newcustomer@example.com',
    ]);

    expect(Customer::where('email', 'newcustomer@example.com')
        ->where('workspace_id', $this->workspace->id)
        ->exists()
    )->toBeTrue();
});

test('magic link request requires a valid email', function () {
    $this->post(route('portal.magic-link', $this->workspace->slug), [
        'email' => 'not-an-email',
    ])->assertSessionHasErrors('email');
});

test('magic link request is throttled', function () {
    Notification::fake();

    $email = 'throttle@example.com';

    for ($i = 0; $i < 5; $i++) {
        $this->post(route('portal.magic-link', $this->workspace->slug), ['email' => $email]);
    }

    $this->post(route('portal.magic-link', $this->workspace->slug), ['email' => $email])
        ->assertStatus(429);
});

test('customer can authenticate with a valid token', function () {
    $customer = Customer::factory()->create(['workspace_id' => $this->workspace->id]);

    // Manually insert a known plain token
    $plain = Str::random(64);
    DB::table('portal_login_tokens')->insert([
        'workspace_id' => $this->workspace->id,
        'customer_id' => $customer->id,
        'token' => hash('sha256', $plain),
        'expires_at' => now()->addMinutes(60),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->get(route('portal.auth', [$this->workspace->slug, $plain]))
        ->assertRedirect(route('portal.tickets.index', $this->workspace->slug));

    $this->assertAuthenticatedAs($customer, 'customer_portal');
});

test('expired or invalid token redirects to login', function () {
    $this->get(route('portal.auth', [$this->workspace->slug, 'invalid-token']))
        ->assertRedirect(route('portal.login', $this->workspace->slug));
});

test('token from another workspace is rejected', function () {
    $otherWorkspace = Workspace::factory()->create([
        'settings' => ['portal' => ['enabled' => true]],
    ]);
    $customer = Customer::factory()->create(['workspace_id' => $otherWorkspace->id]);

    // Insert token scoped to the other workspace
    $plain = Str::random(64);
    DB::table('portal_login_tokens')->insert([
        'workspace_id' => $otherWorkspace->id,
        'customer_id' => $customer->id,
        'token' => hash('sha256', $plain),
        'expires_at' => now()->addMinutes(60),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Try to use the other workspace's token on this workspace
    $this->get(route('portal.auth', [$this->workspace->slug, $plain]))
        ->assertRedirect(route('portal.login', $this->workspace->slug));
});

test('authenticated customer can log out', function () {
    $customer = Customer::factory()->create(['workspace_id' => $this->workspace->id]);

    $this->actingAs($customer, 'customer_portal')
        ->post(route('portal.logout', $this->workspace->slug))
        ->assertRedirect(route('portal.login', $this->workspace->slug));

    $this->assertGuest('customer_portal');
});

test('check email page loads', function () {
    $this->get(route('portal.check-email', $this->workspace->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Portal/CheckEmail'));
});

test('authenticated customer is redirected to tickets on login page visit', function () {
    $customer = Customer::factory()->create(['workspace_id' => $this->workspace->id]);

    $this->actingAs($customer, 'customer_portal')
        ->get(route('portal.login', $this->workspace->slug))
        ->assertRedirect(route('portal.tickets.index', $this->workspace->slug));
});
