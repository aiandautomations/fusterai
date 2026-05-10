<?php

use App\Domains\Conversation\Models\Conversation;
use App\Domains\Customer\Models\Customer;
use App\Domains\Mailbox\Models\Mailbox;
use App\Models\User;
use App\Models\Workspace;
use App\Support\Hooks;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Modules\SatisfactionSurvey\Jobs\SendSurveyJob;
use Modules\SatisfactionSurvey\Mail\SurveyMail;
use Modules\SatisfactionSurvey\Models\SurveyResponse;
use Modules\SatisfactionSurvey\Providers\SatisfactionSurveyServiceProvider;

beforeEach(function () {
    $this->workspace = Workspace::factory()->create();
    $this->mailbox = Mailbox::factory()->create(['workspace_id' => $this->workspace->id]);
    $this->customer = Customer::factory()->create([
        'workspace_id' => $this->workspace->id,
        'email' => 'customer@example.com',
    ]);
    $this->agent = User::factory()->create([
        'workspace_id' => $this->workspace->id,
        'role' => 'admin',
    ]);

    DB::table('modules')->updateOrInsert(
        ['alias' => 'SatisfactionSurvey'],
        ['name' => 'Satisfaction Survey', 'active' => true, 'version' => '1.0.0', 'config' => '[]', 'created_at' => now(), 'updated_at' => now()],
    );

    app()->register(SatisfactionSurveyServiceProvider::class);
});

afterEach(function () {
    Hooks::reset();
});

// ── Hook ─────────────────────────────────────────────────────────────────────

test('SendSurveyJob is dispatched when conversation is closed', function () {
    Queue::fake();

    $conversation = Conversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'mailbox_id' => $this->mailbox->id,
        'customer_id' => $this->customer->id,
    ]);

    Hooks::doAction('conversation.closed', $conversation);

    Queue::assertPushed(SendSurveyJob::class, fn ($job) => $job->conversation->id === $conversation->id);
});

// ── SendSurveyJob ─────────────────────────────────────────────────────────────

test('survey email is sent to customer', function () {
    Mail::fake();

    $conversation = Conversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'mailbox_id' => $this->mailbox->id,
        'customer_id' => $this->customer->id,
    ]);

    (new SendSurveyJob($conversation))->handle();

    Mail::assertSent(SurveyMail::class, fn ($mail) => $mail->hasTo($this->customer->email));
});

test('survey is not sent when customer has no email', function () {
    Mail::fake();

    $customer = Customer::factory()->create(['workspace_id' => $this->workspace->id, 'email' => null]);
    $conversation = Conversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'mailbox_id' => $this->mailbox->id,
        'customer_id' => $customer->id,
    ]);

    (new SendSurveyJob($conversation))->handle();

    Mail::assertNotSent(SurveyMail::class);
});

test('survey is not sent when a response already exists', function () {
    Mail::fake();

    $conversation = Conversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'mailbox_id' => $this->mailbox->id,
        'customer_id' => $this->customer->id,
    ]);

    SurveyResponse::create([
        'conversation_id' => $conversation->id,
        'customer_id' => $this->customer->id,
        'rating' => 'good',
        'responded_at' => now(),
    ]);

    (new SendSurveyJob($conversation))->handle();

    Mail::assertNotSent(SurveyMail::class);
});

test('survey is not sent when surveys are disabled in workspace settings', function () {
    Mail::fake();

    $this->workspace->settings = ['survey' => ['enabled' => false, 'delay_minutes' => 5]];
    $this->workspace->save();

    $conversation = Conversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'mailbox_id' => $this->mailbox->id,
        'customer_id' => $this->customer->id,
    ]);

    (new SendSurveyJob($conversation))->handle();

    Mail::assertNotSent(SurveyMail::class);
});

// ── SurveyController (public respond endpoint) ────────────────────────────────

test('customer can submit a good rating via signed URL', function () {
    $conversation = Conversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'mailbox_id' => $this->mailbox->id,
        'customer_id' => $this->customer->id,
    ]);

    $url = URL::temporarySignedRoute(
        'survey.respond',
        now()->addDays(7),
        ['conversation' => $conversation->id, 'rating' => 'good'],
    );

    $this->get($url)->assertOk()->assertViewIs('satisfaction-survey::responded');

    expect(SurveyResponse::where('conversation_id', $conversation->id)->where('rating', 'good')->exists())->toBeTrue();
});

test('customer can submit a bad rating via signed URL', function () {
    $conversation = Conversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'mailbox_id' => $this->mailbox->id,
        'customer_id' => $this->customer->id,
    ]);

    $url = URL::temporarySignedRoute(
        'survey.respond',
        now()->addDays(7),
        ['conversation' => $conversation->id, 'rating' => 'bad'],
    );

    $this->get($url)->assertOk();

    expect(SurveyResponse::where('conversation_id', $conversation->id)->where('rating', 'bad')->exists())->toBeTrue();
});

test('survey respond rejects an invalid signature', function () {
    $conversation = Conversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'mailbox_id' => $this->mailbox->id,
        'customer_id' => $this->customer->id,
    ]);

    $this->get("/survey/respond?conversation={$conversation->id}&rating=good")
        ->assertForbidden();
});

test('duplicate survey response is idempotent', function () {
    $conversation = Conversation::factory()->create([
        'workspace_id' => $this->workspace->id,
        'mailbox_id' => $this->mailbox->id,
        'customer_id' => $this->customer->id,
    ]);

    $url = URL::temporarySignedRoute(
        'survey.respond',
        now()->addDays(7),
        ['conversation' => $conversation->id, 'rating' => 'good'],
    );

    $this->get($url)->assertOk();
    $this->get($url)->assertOk();

    expect(SurveyResponse::where('conversation_id', $conversation->id)->count())->toBe(1);
});

// ── Settings ──────────────────────────────────────────────────────────────────

test('admin can view survey settings page', function () {
    $this->actingAs($this->agent)
        ->get(route('settings.survey'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Settings/Survey'));
});

test('admin can update survey settings', function () {
    $this->actingAs($this->agent)
        ->post(route('settings.survey.update'), ['enabled' => false, 'delay_minutes' => 10])
        ->assertRedirect();

    $this->workspace->refresh();
    expect($this->workspace->settings['survey']['enabled'])->toBeFalse();
    expect($this->workspace->settings['survey']['delay_minutes'])->toBe(10);
});

test('agent cannot access survey settings', function () {
    $agent = User::factory()->create(['workspace_id' => $this->workspace->id, 'role' => 'agent']);

    $this->actingAs($agent)
        ->get(route('settings.survey'))
        ->assertForbidden();
});

// ── Report ────────────────────────────────────────────────────────────────────

test('admin can view CSAT report', function () {
    $this->actingAs($this->agent)
        ->get(route('settings.survey.report'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Settings/SurveyReport'));
});

test('CSAT report only shows data from the current workspace', function () {
    $otherWorkspace = Workspace::factory()->create();
    $otherMailbox = Mailbox::factory()->create(['workspace_id' => $otherWorkspace->id]);
    $otherCustomer = Customer::factory()->create(['workspace_id' => $otherWorkspace->id, 'email' => 'other@example.com']);
    $otherConversation = Conversation::factory()->create([
        'workspace_id' => $otherWorkspace->id,
        'mailbox_id' => $otherMailbox->id,
        'customer_id' => $otherCustomer->id,
    ]);

    SurveyResponse::create([
        'conversation_id' => $otherConversation->id,
        'customer_id' => $otherCustomer->id,
        'rating' => 'good',
        'responded_at' => now(),
    ]);

    $this->actingAs($this->agent)
        ->get(route('settings.survey.report'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('stats.total', 0));
});

test('CSAT report calculates score correctly', function () {
    $conversations = Conversation::factory()->count(4)->create([
        'workspace_id' => $this->workspace->id,
        'mailbox_id' => $this->mailbox->id,
        'customer_id' => $this->customer->id,
    ]);

    SurveyResponse::create(['conversation_id' => $conversations[0]->id, 'customer_id' => $this->customer->id, 'rating' => 'good', 'responded_at' => now()]);
    SurveyResponse::create(['conversation_id' => $conversations[1]->id, 'customer_id' => $this->customer->id, 'rating' => 'good', 'responded_at' => now()]);
    SurveyResponse::create(['conversation_id' => $conversations[2]->id, 'customer_id' => $this->customer->id, 'rating' => 'good', 'responded_at' => now()]);
    SurveyResponse::create(['conversation_id' => $conversations[3]->id, 'customer_id' => $this->customer->id, 'rating' => 'bad', 'responded_at' => now()]);

    $this->actingAs($this->agent)
        ->get(route('settings.survey.report'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('stats.total', 4)
            ->where('stats.good', 3)
            ->where('stats.bad', 1)
            ->where('stats.score', 75)
        );
});
