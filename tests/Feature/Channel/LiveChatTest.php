<?php

use App\Domains\Channel\Jobs\HandleLiveChatMessageJob;
use App\Domains\Conversation\Models\Conversation;
use App\Domains\Conversation\Models\Thread;
use App\Domains\Customer\Models\Customer;
use App\Models\Workspace;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
    Event::fake();
    $this->workspace = Workspace::factory()->create();
});

test('live chat message endpoint requires workspace_id, visitor_id and message', function () {
    $this->postJson('/api/livechat/message', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['workspace_id', 'visitor_id', 'message']);
});

test('valid live chat message dispatches HandleLiveChatMessageJob', function () {
    $this->postJson('/api/livechat/message', [
        'workspace_id' => $this->workspace->id,
        'visitor_id'   => 'visitor-abc-123',
        'visitor_name' => 'Jane',
        'message'      => 'Hello, I need help',
    ])->assertOk()->assertJson(['status' => 'sent']);

    Queue::assertPushed(HandleLiveChatMessageJob::class);
});

test('visitor email is stored on the customer record', function () {
    Queue::fake([]);

    $this->postJson('/api/livechat/message', [
        'workspace_id'  => $this->workspace->id,
        'visitor_id'    => 'visitor-email-test',
        'visitor_name'  => 'Jane',
        'visitor_email' => 'jane@example.com',
        'message'       => 'Hi there',
    ])->assertOk();

    expect(Customer::where('email', 'jane@example.com')->exists())->toBeTrue();
});

test('visitor without email falls back to virtual email', function () {
    Queue::fake([]);

    $this->postJson('/api/livechat/message', [
        'workspace_id' => $this->workspace->id,
        'visitor_id'   => 'anon-visitor-99',
        'message'      => 'Hi there',
    ])->assertOk();

    expect(Customer::where('email', 'visitor_anon-visitor-99@livechat.local')->exists())->toBeTrue();
});

test('live chat agent console requires auth', function () {
    $this->get('/live-chat')->assertRedirect('/login');
});

test('authenticated agent can view live chat console', function () {
    $user = agentUser($this->workspace);

    $this->actingAs($user)
        ->get('/live-chat')
        ->assertOk()
        ->assertInertia(fn ($p) => $p->component('LiveChat/Index')->has('conversations'));
});

test('HandleLiveChatMessageJob creates a thread on the conversation', function () {
    Queue::fake([]);

    $customer = Customer::factory()->create(['workspace_id' => $this->workspace->id]);
    $conversation = Conversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'customer_id'  => $customer->id,
        'channel_type' => 'chat',
    ]);

    $job = new HandleLiveChatMessageJob($conversation, $customer, 'My order is late');
    $job->handle();

    expect(Thread::where('conversation_id', $conversation->id)->exists())->toBeTrue();
});

test('subsequent messages from same visitor append threads to the same conversation', function () {
    Queue::fake([]);

    $customer = Customer::factory()->create(['workspace_id' => $this->workspace->id]);
    $conversation = Conversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'customer_id'  => $customer->id,
        'channel_type' => 'chat',
    ]);

    (new HandleLiveChatMessageJob($conversation, $customer, 'First message'))->handle();
    (new HandleLiveChatMessageJob($conversation, $customer, 'Second message'))->handle();

    expect(Conversation::where('workspace_id', $this->workspace->id)->count())->toBe(1);
    expect(Thread::where('conversation_id', $conversation->id)->count())->toBe(2);
});
