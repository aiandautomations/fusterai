<?php

namespace App\Providers;

use App\Domains\AI\Models\KnowledgeBase;
use App\Domains\Automation\Models\AutomationRule;
use App\Domains\Conversation\Models\Conversation;
use App\Domains\Conversation\Models\Folder;
use App\Domains\Conversation\Models\Tag;
use App\Domains\Mailbox\Models\Mailbox;
use App\Models\CannedResponse;
use App\Models\User;
use App\Policies\AutomationRulePolicy;
use App\Policies\CannedResponsePolicy;
use App\Policies\ConversationPolicy;
use App\Policies\FolderPolicy;
use App\Policies\KnowledgeBasePolicy;
use App\Policies\MailboxPolicy;
use App\Policies\TagPolicy;
use App\Policies\UserPolicy;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // ── Force HTTPS in production ────────────────────────────────────────────
        if (app()->environment('production')) {
            URL::forceScheme('https');
        }

        // ── Policies ─────────────────────────────────────────────────────────────
        Gate::policy(Conversation::class,   ConversationPolicy::class);
        Gate::policy(Folder::class,         FolderPolicy::class);
        Gate::policy(Mailbox::class,        MailboxPolicy::class);
        Gate::policy(KnowledgeBase::class,  KnowledgeBasePolicy::class);
        Gate::policy(AutomationRule::class, AutomationRulePolicy::class);
        Gate::policy(Tag::class,            TagPolicy::class);
        Gate::policy(User::class,           UserPolicy::class);
        Gate::policy(CannedResponse::class, CannedResponsePolicy::class);

        // ── Gates for non-model authorization ────────────────────────────────────
        // Reports has no model — gate is the right tool here
        Gate::define('access-reports',  fn (User $u) => $u->isManager());
        // admin+ can change workspace settings
        Gate::define('manage-settings', fn (User $u) => $u->isAdmin());

        // ── Use MCP's Passport authorization view for the OAuth consent screen ───
        Passport::authorizationView(function (array $parameters): \Symfony\Component\HttpFoundation\Response {
            return response()->view('mcp.authorize', $parameters);
        });

        // Scramble: document bearer token auth on all API endpoints
        Scramble::configure()
            ->withDocumentTransformers(function (OpenApi $openApi) {
                $openApi->secure(SecurityScheme::http('bearer'));
            });

        // Share data with all Inertia pages
        // Note: auth, flash, mailboxes, tags, branding, appearance are shared via HandleInertiaRequests
        Inertia::share([
            'flash' => fn () => [
                'success' => session('success'),
                'error'   => session('error'),
            ],
            'folders' => fn () => request()->user()
                ? Folder::where('workspace_id', request()->user()->workspace_id)
                    ->withCount(['conversations as open_count' => fn ($q) => $q->where('status', 'open')])
                    ->orderBy('order')
                    ->get(['id', 'name', 'color', 'icon', 'order'])
                : [],
            // Cached for 5 min; flushed immediately when a module is toggled.
            'activeModules' => fn () => Cache::remember(
                'active_modules',
                now()->addMinutes(5),
                fn () => \App\Domains\AI\Models\Module::where('active', true)->pluck('alias')->values()
            ),
        ]);
    }
}
