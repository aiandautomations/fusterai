<?php

use App\Domains\Automation\Jobs\RunAutomationRulesJob;
use App\Domains\Automation\Models\AutomationRule;
use App\Domains\Conversation\Models\Conversation;
use App\Domains\Customer\Models\Customer;
use App\Domains\Mailbox\Models\Mailbox;
use App\Models\Workspace;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
    $this->workspace = Workspace::factory()->create();
    $this->user = managerUser($this->workspace);
    $this->mailbox = Mailbox::factory()->create(['workspace_id' => $this->workspace->id]);
});

test('admin can view automation rules list', function () {
    AutomationRule::factory()->count(2)->create(['workspace_id' => $this->workspace->id]);

    $this->actingAs($this->user)
        ->get('/automation')
        ->assertOk()
        ->assertInertia(fn ($p) => $p->component('Automation/Index')->has('rules', 2));
});

test('can create an automation rule', function () {
    $this->actingAs($this->user)
        ->post('/automation', [
            'name' => 'High priority on urgent subject',
            'trigger' => 'conversation.created',
            'conditions' => [['field' => 'subject', 'operator' => 'contains', 'value' => 'urgent']],
            'actions' => [['type' => 'set_priority', 'value' => 'urgent']],
            'active' => true,
        ])
        ->assertRedirect('/automation');

    expect(AutomationRule::where('workspace_id', $this->workspace->id)->count())->toBe(1);
});

test('can toggle automation rule active state', function () {
    $rule = AutomationRule::factory()->create([
        'workspace_id' => $this->workspace->id,
        'active' => true,
    ]);

    $this->actingAs($this->user)
        ->patch("/automation/{$rule->id}/toggle")
        ->assertRedirect();

    expect($rule->fresh()->active)->toBeFalse();
});

test('can delete an automation rule', function () {
    $rule = AutomationRule::factory()->create(['workspace_id' => $this->workspace->id]);

    $this->actingAs($this->user)
        ->delete("/automation/{$rule->id}")
        ->assertRedirect('/automation');

    expect(AutomationRule::find($rule->id))->toBeNull();
});

test('cannot access another workspace automation rule', function () {
    $other = Workspace::factory()->create();
    $rule = AutomationRule::factory()->create(['workspace_id' => $other->id]);

    $this->actingAs($this->user)
        ->delete("/automation/{$rule->id}")
        ->assertForbidden();
});

test('RunAutomationRulesJob applies matching actions', function () {
    Queue::fake([]);

    $customer = Customer::factory()->create(['workspace_id' => $this->workspace->id]);
    $conversation = Conversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'mailbox_id' => $this->mailbox->id,
        'customer_id' => $customer->id,
        'priority' => 'normal',
    ]);

    AutomationRule::factory()->create([
        'workspace_id' => $this->workspace->id,
        'trigger' => 'conversation.created',
        'conditions' => [],
        'actions' => [['type' => 'set_priority', 'value' => 'urgent']],
        'active' => true,
    ]);

    (new RunAutomationRulesJob('conversation.created', $conversation))->handle();

    expect($conversation->fresh()->priority->value)->toBe('urgent');
});

test('RunAutomationRulesJob skips rules that do not match conditions', function () {
    Queue::fake([]);

    $customer = Customer::factory()->create(['workspace_id' => $this->workspace->id]);
    $conversation = Conversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'mailbox_id' => $this->mailbox->id,
        'customer_id' => $customer->id,
        'priority' => 'normal',
        'status' => 'open',
    ]);

    AutomationRule::factory()->create([
        'workspace_id' => $this->workspace->id,
        'trigger' => 'conversation.created',
        'conditions' => [['field' => 'status', 'operator' => 'equals', 'value' => 'closed']],
        'actions' => [['type' => 'set_priority', 'value' => 'urgent']],
        'active' => true,
    ]);

    (new RunAutomationRulesJob('conversation.created', $conversation))->handle();

    expect($conversation->fresh()->priority->value)->toBe('normal');
});
