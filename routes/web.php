<?php

use App\Http\Controllers\AgentStatusController;
use App\Http\Controllers\AI\AiController;
use App\Http\Controllers\AI\KnowledgeBaseController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\InviteController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Automation\AutomationController;
use App\Http\Controllers\Conversations\ConversationController;
use App\Http\Controllers\Conversations\FollowerController;
use App\Http\Controllers\Conversations\SearchController;
use App\Http\Controllers\Conversations\ThreadController;
use App\Http\Controllers\Customers\CustomerController;
use App\Http\Controllers\CustomViewController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LiveChat\LiveChatController;
use App\Http\Controllers\Mailboxes\MailboxController;
use App\Http\Controllers\Mailboxes\WhatsAppMailboxController;
use App\Http\Controllers\NotificationsController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Reports\ReportsController;
use App\Http\Controllers\Settings\ApiKeyController;
use App\Http\Controllers\Settings\CannedResponseController;
use App\Http\Controllers\Settings\FolderController;
use App\Http\Controllers\Settings\SettingsController;
use App\Http\Controllers\Settings\TagController;
use App\Http\Controllers\Settings\UserController;
use App\Http\Controllers\UnsubscribeController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect('/dashboard'));

// ── Unsubscribe (public, signed URL) ─────────────────────────────────────────
Route::get('/unsubscribe/{customer}', [UnsubscribeController::class, 'show'])->name('unsubscribe');
Route::delete('/unsubscribe/{customer}', [UnsubscribeController::class, 'destroy'])->name('unsubscribe.destroy');

// ── Auth ─────────────────────────────────────────────────────────────────────

// ── Accept invite ─────────────────────────────────────────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('/invite/accept/{token}', [InviteController::class, 'create'])->name('invite.accept');
    Route::post('/invite/accept', [InviteController::class, 'store'])->middleware('throttle:5,1')->name('invite.store');
});

// ── Registration (only when no users exist — fresh install) ──────────────────
Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:5,1');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:5,1');

    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])->middleware('throttle:5,1')->name('password.update');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    // ── Dashboard ─────────────────────────────────────────────────────────────
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // ── Profile ───────────────────────────────────────────────────────────────
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::patch('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
    Route::post('/profile/avatar', [ProfileController::class, 'updateAvatar'])->name('profile.avatar');
    Route::patch('/profile/status', [AgentStatusController::class, 'update'])->name('profile.status');

    // ── Conversations ─────────────────────────────────────────────────────────

    Route::resource('conversations', ConversationController::class)->only(['index', 'show', 'store']);
    Route::post('/conversations/bulk', [ConversationController::class, 'bulk'])->name('conversations.bulk');
    Route::patch('/conversations/{conversation}/status', [ConversationController::class, 'updateStatus'])->name('conversations.status');
    Route::patch('/conversations/{conversation}/assign', [ConversationController::class, 'assign'])->name('conversations.assign');
    Route::patch('/conversations/{conversation}/snooze', [ConversationController::class, 'snooze'])->name('conversations.snooze');
    Route::patch('/conversations/{conversation}/priority', [ConversationController::class, 'updatePriority'])->name('conversations.priority');
    Route::post('/conversations/{conversation}/merge', [ConversationController::class, 'merge'])->name('conversations.merge');
    Route::post('/conversations/{conversation}/tags', [ConversationController::class, 'syncTags'])->name('conversations.tags');
    Route::post('/conversations/{conversation}/read', [ConversationController::class, 'markRead'])->name('conversations.read');
    Route::post('/conversations/{conversation}/unread', [ConversationController::class, 'markUnread'])->name('conversations.unread');
    Route::post('/conversations/{conversation}/star', [ConversationController::class, 'toggleStar'])->name('conversations.star');

    // Threads
    Route::post('/conversations/{conversation}/threads', [ThreadController::class, 'store'])->name('threads.store');
    Route::delete('/conversations/{conversation}/threads/{thread}/schedule', [ThreadController::class, 'cancelSchedule'])->name('threads.schedule.cancel');

    // Followers
    Route::post('/conversations/{conversation}/follow', [FollowerController::class, 'store'])->name('conversations.follow');
    Route::delete('/conversations/{conversation}/follow', [FollowerController::class, 'destroy'])->name('conversations.unfollow');

    // Search
    Route::get('/search', [SearchController::class, 'index'])->name('search');

    // ── Mailboxes ─────────────────────────────────────────────────────────────

    Route::resource('mailboxes', MailboxController::class);
    Route::get('/mailboxes/{mailbox}/whatsapp', [WhatsAppMailboxController::class, 'show'])->name('mailboxes.whatsapp');
    Route::post('/mailboxes/{mailbox}/whatsapp', [WhatsAppMailboxController::class, 'update'])->name('mailboxes.whatsapp.update');

    // ── Customers ─────────────────────────────────────────────────────────────

    Route::get('/customers/search', [CustomerController::class, 'search'])->name('customers.search');
    Route::resource('customers', CustomerController::class)->only(['index', 'show', 'update']);

    // ── Tags ──────────────────────────────────────────────────────────────────

    Route::resource('tags', TagController::class)->only(['index', 'store', 'update', 'destroy']);

    // ── Folders (settings) ────────────────────────────────────────────────────

    Route::resource('settings/folders', FolderController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->names('settings.folders');

    // ── Custom Views ──────────────────────────────────────────────────────────────

    Route::post('/views', [CustomViewController::class, 'store'])->name('views.store');
    Route::patch('/views/{customView}', [CustomViewController::class, 'update'])->name('views.update');
    Route::delete('/views/{customView}', [CustomViewController::class, 'destroy'])->name('views.destroy');

    // ── Conversation folder sync + mailbox move ────────────────────────────────

    Route::post('/conversations/{conversation}/folders', [ConversationController::class, 'syncFolders'])->name('conversations.folders');
    Route::patch('/conversations/{conversation}/mailbox', [ConversationController::class, 'changeMailbox'])->name('conversations.mailbox');

    // ── Canned Responses ──────────────────────────────────────────────────────

    Route::get('/canned-responses/search', [CannedResponseController::class, 'search'])->name('canned-responses.search');
    Route::resource('settings/canned-responses', CannedResponseController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->names('settings.canned-responses');

    // ── Users ──────────────────────────────────────────────────────────────────
    Route::resource('settings/users', UserController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->names('settings.users');
    Route::patch('/settings/users/{user}/mailboxes', [UserController::class, 'updateMailboxes'])
        ->name('settings.users.mailboxes');

    // ── Live Chat (agent console) ─────────────────────────────────────────────

    Route::get('/live-chat', [LiveChatController::class, 'index'])->name('livechat.index');
    Route::post('/live-chat/{conversation}/typing', [LiveChatController::class, 'typing'])->name('livechat.typing');

    // ── Automation ────────────────────────────────────────────────────────────

    Route::resource('automation', AutomationController::class)
        ->except(['show']);
    Route::patch('/automation/{automation}/toggle', [AutomationController::class, 'toggle'])
        ->name('automation.toggle');

    // ── Notifications ─────────────────────────────────────────────────────────

    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/', [NotificationsController::class, 'index'])->name('index');
        Route::post('/read-all', [NotificationsController::class, 'readAll'])->name('read-all');
        Route::post('/{id}/read', [NotificationsController::class, 'read'])->name('read');
    });

    // ── Settings ──────────────────────────────────────────────────────────────

    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::get('/settings/general', [SettingsController::class, 'general'])->name('settings.general');
    Route::patch('/settings/general', [SettingsController::class, 'updateGeneral'])->name('settings.general.update');
    Route::post('/settings/branding', [SettingsController::class, 'updateBranding'])->name('settings.branding.update');
    Route::get('/settings/appearance', [SettingsController::class, 'appearance'])->name('settings.appearance');
    Route::patch('/settings/appearance', [SettingsController::class, 'updateAppearance'])->name('settings.appearance.update');
    Route::get('/settings/modules', [SettingsController::class, 'modules'])->name('settings.modules');
    Route::patch('/settings/modules/{alias}', [SettingsController::class, 'toggleModule'])->name('settings.modules.toggle');
    Route::get('/settings/ai', [SettingsController::class, 'ai'])->name('settings.ai');
    Route::patch('/settings/ai', [SettingsController::class, 'updateAi'])->name('settings.ai.update');
    Route::post('/settings/ai/test-connection', [SettingsController::class, 'testAiConnection'])->name('settings.ai.test');
    Route::get('/settings/email', [SettingsController::class, 'email'])->name('settings.email');
    Route::get('/settings/audit-log', [SettingsController::class, 'auditLog'])->name('settings.audit-log');
    Route::get('/settings/api-keys', [ApiKeyController::class, 'index'])->name('settings.api-keys');
    Route::post('/settings/api-keys', [ApiKeyController::class, 'store'])->name('settings.api-keys.store');
    Route::delete('/settings/api-keys/{id}', [ApiKeyController::class, 'destroy'])->name('settings.api-keys.destroy');
    Route::get('/settings/live-chat', [SettingsController::class, 'liveChat'])->name('settings.livechat');
    Route::patch('/settings/live-chat', [SettingsController::class, 'updateLiveChat'])->name('settings.livechat.update');

    // ── Reports ───────────────────────────────────────────────────────────────

    Route::get('/reports', [ReportsController::class, 'index'])->name('reports.index');

    // ── AI / Knowledge Base ───────────────────────────────────────────────────

    Route::prefix('ai')->name('ai.')->group(function () {
        Route::get('/knowledge-base', [KnowledgeBaseController::class, 'index'])->name('kb.index');
        Route::resource('knowledge-bases', KnowledgeBaseController::class)->except(['index']);

        // KB Document sub-routes
        Route::get('/knowledge-bases/{knowledgeBase}/documents/create', [KnowledgeBaseController::class, 'createDocument'])->name('kb.documents.create');
        Route::post('/knowledge-bases/{knowledgeBase}/documents', [KnowledgeBaseController::class, 'storeDocument'])->name('kb.documents.store');
        Route::get('/knowledge-bases/{knowledgeBase}/documents/{document}/edit', [KnowledgeBaseController::class, 'editDocument'])->name('kb.documents.edit');
        Route::patch('/knowledge-bases/{knowledgeBase}/documents/{document}', [KnowledgeBaseController::class, 'updateDocument'])->name('kb.documents.update');
        Route::delete('/knowledge-bases/{knowledgeBase}/documents/{document}', [KnowledgeBaseController::class, 'destroyDocument'])->name('kb.documents.destroy');
        Route::post('/knowledge-bases/{knowledgeBase}/documents/import-url', [KnowledgeBaseController::class, 'importUrl'])->name('kb.documents.import-url');

        Route::post('/conversations/{conversation}/suggest-reply', [AiController::class, 'suggestReply'])->name('suggest-reply');
        Route::post('/conversations/{conversation}/summarize', [AiController::class, 'summarize'])->name('summarize');
        Route::patch('/suggestions/{suggestion}/accept', [AiController::class, 'acceptSuggestion'])->name('suggestions.accept');
    });
});
