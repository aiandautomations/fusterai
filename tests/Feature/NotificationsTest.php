<?php

use App\Domains\Conversation\Models\Conversation;
use App\Domains\Conversation\Models\Thread;
use App\Domains\Customer\Models\Customer;
use App\Domains\Mailbox\Models\Mailbox;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\ConversationAssignedNotification;
use App\Notifications\NewCustomerReplyNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

beforeEach(function () {
    Notification::fake();
    Queue::fake();

    $this->workspace = Workspace::factory()->create();
    $this->agent = User::factory()->create(['workspace_id' => $this->workspace->id]);
    $this->mailbox = Mailbox::factory()->create(['workspace_id' => $this->workspace->id]);
    $this->customer = Customer::factory()->create(['workspace_id' => $this->workspace->id]);
    $this->conversation = Conversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'mailbox_id' => $this->mailbox->id,
        'customer_id' => $this->customer->id,
    ]);
});

test('assigning conversation sends ConversationAssignedNotification', function () {
    $this->actingAs($this->agent)
        ->patch("/conversations/{$this->conversation->id}/assign", [
            'user_id' => $this->agent->id,
        ])
        ->assertRedirect();

    Notification::assertSentTo($this->agent, ConversationAssignedNotification::class);
});

test('assigning to null does not send notification', function () {
    $this->actingAs($this->agent)
        ->patch("/conversations/{$this->conversation->id}/assign", [
            'user_id' => null,
        ])
        ->assertRedirect();

    Notification::assertNothingSent();
});

test('customer reply notifies the assigned agent', function () {
    $assigner = User::factory()->create(['workspace_id' => $this->workspace->id]);
    $this->conversation->update(['assigned_user_id' => $assigner->id]);

    // Simulate an inbound customer thread directly (no HTTP route — customer messages arrive via email/webhook)
    $thread = $this->conversation->threads()->create([
        'customer_id' => $this->customer->id,
        'user_id' => null,
        'type' => 'message',
        'body' => '<p>Need help</p>',
        'source' => 'email',
    ]);

    $assignee = User::find($assigner->id);
    $assignee->notify(new NewCustomerReplyNotification($this->conversation, $thread));

    Notification::assertSentTo($assigner, NewCustomerReplyNotification::class);
});

test('agent reply does not trigger customer reply notification', function () {
    $assigner = User::factory()->create(['workspace_id' => $this->workspace->id]);
    $this->conversation->update(['assigned_user_id' => $assigner->id]);

    // Agent replies via HTTP — should NOT send NewCustomerReplyNotification
    $this->actingAs($this->agent)
        ->post("/conversations/{$this->conversation->id}/threads", [
            'body' => '<p>Hello customer</p>',
            'type' => 'message',
        ])
        ->assertRedirect();

    Notification::assertNotSentTo($assigner, NewCustomerReplyNotification::class);
});

test('sender does not receive their own reply notification', function () {
    // Assign conversation to the same agent who is replying
    $this->conversation->update(['assigned_user_id' => $this->agent->id]);

    $this->actingAs($this->agent)
        ->post("/conversations/{$this->conversation->id}/threads", [
            'body' => '<p>My own reply</p>',
            'type' => 'message',
        ])
        ->assertRedirect();

    Notification::assertNotSentTo($this->agent, NewCustomerReplyNotification::class);
});

test('notifications index returns paginated list', function () {
    // Insert a real DB notification (bypass Notification::fake())
    $this->agent->notifications()->create([
        'id' => Str::uuid(),
        'type' => ConversationAssignedNotification::class,
        'data' => json_encode(['type' => 'assigned', 'conversation_id' => $this->conversation->id]),
    ]);

    $this->actingAs($this->agent)
        ->getJson('/notifications')
        ->assertOk()
        ->assertJsonStructure(['data', 'total']);
});

test('read-all marks all notifications as read', function () {
    $this->agent->notifications()->create([
        'id' => Str::uuid(),
        'type' => ConversationAssignedNotification::class,
        'data' => json_encode(['type' => 'assigned', 'conversation_id' => $this->conversation->id]),
    ]);

    expect($this->agent->fresh()->unreadNotifications()->count())->toBe(1);

    $this->actingAs($this->agent)
        ->postJson('/notifications/read-all')
        ->assertOk();

    expect($this->agent->fresh()->unreadNotifications()->count())->toBe(0);
});

test('individual notification can be marked as read', function () {
    $this->agent->notifications()->create([
        'id' => Str::uuid(),
        'type' => ConversationAssignedNotification::class,
        'data' => json_encode(['type' => 'assigned', 'conversation_id' => $this->conversation->id]),
    ]);

    $notification = $this->agent->fresh()->notifications()->first();

    $this->actingAs($this->agent)
        ->postJson("/notifications/{$notification->id}/read")
        ->assertOk();

    expect($notification->fresh()->read_at)->not->toBeNull();
});
