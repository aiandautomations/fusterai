<?php

namespace App\Http\Controllers\Settings;

use App\Ai\Agents\SummarizationAgent;
use App\Http\Controllers\Controller;
use App\Models\Workspace;
use App\Services\AiSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function index(): RedirectResponse
    {
        return redirect()->route('settings.general');
    }

    public function general(Request $request): Response
    {
        $this->authorize('manage-settings');
        $workspace = Workspace::findOrFail($request->user()->workspace_id);
        $branding  = $workspace->settings['branding'] ?? [];

        return Inertia::render('Settings/General', [
            'workspace' => $workspace,
            'branding'  => [
                'name'     => $branding['name'] ?? '',
                'logo_url' => $branding['logo_url'] ?? null,
                'website'  => $branding['website'] ?? '',
            ],
        ]);
    }

    public function updateGeneral(Request $request): RedirectResponse
    {
        $this->authorize('manage-settings');
        $validated = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'timezone' => ['nullable', 'string', 'max:100'],
        ]);

        $workspace = Workspace::findOrFail($request->user()->workspace_id);
        $workspace->name = $validated['name'];

        $settings = $workspace->settings ?? [];

        if (isset($validated['timezone'])) {
            $settings['timezone'] = $validated['timezone'];
        }

        $workspace->settings = $settings;
        $workspace->save();

        return redirect()->back()->with('success', 'Workspace settings saved.');
    }

    public function updateBranding(Request $request): RedirectResponse
    {
        $this->authorize('manage-settings');
        $validated = $request->validate([
            'branding_name'    => ['nullable', 'string', 'max:255'],
            'branding_website' => ['nullable', 'url', 'max:255'],
            'branding_logo'    => ['nullable', 'file', 'max:2048', 'mimes:jpg,jpeg,png,gif,webp,svg'],
        ]);

        $workspace = Workspace::findOrFail($request->user()->workspace_id);
        $settings  = $workspace->settings ?? [];
        $branding  = $settings['branding'] ?? [];

        $branding['name']    = $validated['branding_name'] ?? '';
        $branding['website'] = $validated['branding_website'] ?? '';

        if ($request->hasFile('branding_logo')) {
            if (!empty($branding['logo_path'])) {
                Storage::disk('public')->delete($branding['logo_path']);
            }
            $path = $request->file('branding_logo')->store('workspace/logos', 'public');
            $branding['logo_path'] = $path;
            $branding['logo_url']  = Storage::disk('public')->url($path);
        }

        $settings['branding'] = $branding;
        $workspace->settings  = $settings;
        $workspace->save();

        return redirect()->back()->with('success', 'Branding saved.');
    }

    public function ai(Request $request): Response
    {
        $this->authorize('manage-settings');
        $aiConfig = app(AiSettingsService::class)->getForWorkspace($request->user()->workspace_id);

        return Inertia::render('Settings/AIConfig', [
            'aiConfig' => $aiConfig,
        ]);
    }

    public function updateAi(Request $request): RedirectResponse
    {
        $this->authorize('manage-settings');
        $validated = $request->validate([
            'provider'                    => ['required', 'in:anthropic,openai,openai-compatible,openrouter'],
            'api_key'                     => ['nullable', 'string', 'max:500'],
            'model'                       => ['nullable', 'string', 'max:100'],
            'base_url'                    => ['nullable', 'url', 'max:255'],
            'feature_reply_suggestions'   => ['boolean'],
            'feature_auto_categorization' => ['boolean'],
            'feature_summarization'       => ['boolean'],
            'rag_top_k'                   => ['integer', 'min:1', 'max:10'],
            'rag_min_score'               => ['numeric', 'min:0', 'max:1'],
        ]);

        app(AiSettingsService::class)->saveForWorkspace($request->user()->workspace_id, $validated);

        return redirect()->back()->with('success', 'AI settings saved.');
    }

    public function testAiConnection(Request $request): JsonResponse
    {
        $this->authorize('manage-settings');

        $workspaceId = $request->user()->workspace_id;
        $aiService   = app(AiSettingsService::class);
        $config      = $aiService->getForWorkspace($workspaceId);

        if (! $config['key_set']) {
            return response()->json(['ok' => false, 'message' => 'No API key saved. Save your settings first.']);
        }

        try {
            ['lab' => $lab, 'model' => $model] = $aiService->configureForWorkspace($workspaceId);

            // Make the smallest possible real API call — one sentence in, one word out.
            $agent = new SummarizationAgent();
            $agent->prompt('Reply with the single word: ok', provider: $lab, model: $model);

            return response()->json(['ok' => true, 'message' => 'Connection successful — API key is valid.']);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => 'Connection failed: ' . $e->getMessage()]);
        }
    }

    public function modules(): Response
    {
        $this->authorize('manage-settings');

        // Scan Modules/ directory to discover installed modules from the filesystem.
        // DB records only exist for modules the user has explicitly enabled/disabled.
        $discovered = [];
        $modulesPath = base_path('Modules');
        if (is_dir($modulesPath)) {
            foreach (scandir($modulesPath) as $alias) {
                if ($alias === '.' || $alias === '..') continue;
                $providerPath = "{$modulesPath}/{$alias}/Providers/{$alias}ServiceProvider.php";
                if (!is_dir("{$modulesPath}/{$alias}") || !file_exists($providerPath)) continue;
                $discovered[] = $alias;
            }
        }

        // Load any DB records for discovered modules
        $dbModules = \App\Domains\AI\Models\Module::whereIn('alias', $discovered)->get()->keyBy('alias');

        // Merge: filesystem wins for discovery, DB provides active state
        $modules = collect($discovered)->map(function (string $alias) use ($dbModules) {
            /** @var \App\Domains\AI\Models\Module|null $db */
            $db = $dbModules->get($alias);
            return [
                'id'      => $db?->id,
                'alias'   => $alias,
                'name'    => $db !== null ? ($db->name ?? trim((string) preg_replace('/([A-Z])/', ' $1', $alias))) : trim((string) preg_replace('/([A-Z])/', ' $1', $alias)),
                'active'  => $db !== null ? ($db->active ?? false) : false,
                'version' => $db !== null ? ($db->version ?? '1.0.0') : '1.0.0',
            ];
        })->values();

        return Inertia::render('Settings/Modules', [
            'modules' => $modules,
        ]);
    }

    public function toggleModule(Request $request, string $alias): RedirectResponse
    {
        $this->authorize('manage-settings');
        $request->validate(['active' => ['required', 'boolean']]);

        $active = $request->boolean('active');
        $name   = trim(preg_replace('/([A-Z])/', ' $1', $alias));

        $module = \App\Domains\AI\Models\Module::updateOrCreate(
            ['alias' => $alias],
            ['name' => $name, 'active' => $active, 'version' => '1.0.0', 'config' => []]
        );

        // When enabling, run any pending migrations for this module automatically.
        if ($active) {
            $migrationPath = base_path("Modules/{$alias}/Database/Migrations");
            if (is_dir($migrationPath)) {
                \Illuminate\Support\Facades\Artisan::call('migrate', [
                    '--path'  => "Modules/{$alias}/Database/Migrations",
                    '--force' => true,
                ]);
            }
        }

        // Flush caches so the change takes effect on the very next request —
        // no server restart required.
        Cache::forget('active_modules');
        Cache::forget("module.active.{$alias}");

        return back()->with('success', $active ? "{$module->name} enabled." : "{$module->name} disabled.");
    }

    public function appearance(Request $request): Response
    {
        $workspace = Workspace::findOrFail($request->user()->workspace_id);
        $appearance = $workspace->settings['appearance'] ?? [];

        return Inertia::render('Settings/Appearance', [
            'appearance' => [
                'mode'     => $appearance['mode'] ?? 'system',
                'color'    => $appearance['color'] ?? 'neutral',
                'font'     => $appearance['font'] ?? 'inter',
                'radius'   => $appearance['radius'] ?? 'lg',
                'contrast' => $appearance['contrast'] ?? 'balanced',
            ],
        ]);
    }

    public function updateAppearance(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'mode'  => ['required', 'in:light,dark,system'],
            'color' => ['required', 'in:neutral,amber,blue,cyan,emerald,fuchsia,green,indigo,lime,orange,pink,purple,red,rose,sky,teal,violet,yellow'],
            'font'  => ['required', 'in:inter,figtree,manrope,system'],
            'radius' => ['required', 'in:sm,md,lg,xl'],
            'contrast' => ['required', 'in:soft,balanced,strong'],
        ]);

        $workspace = Workspace::findOrFail($request->user()->workspace_id);
        $settings = $workspace->settings ?? [];
        $settings['appearance'] = [
            'mode'     => $validated['mode'],
            'color'    => $validated['color'],
            'font'     => $validated['font'],
            'radius'   => $validated['radius'],
            'contrast' => $validated['contrast'],
        ];

        $workspace->settings = $settings;
        $workspace->save();

        return redirect()->back()->with('success', 'Appearance settings saved.');
    }

    public function liveChat(Request $request): Response
    {
        $this->authorize('manage-settings');
        $workspace = Workspace::findOrFail($request->user()->workspace_id);
        $chatConfig   = $workspace->settings['live_chat'] ?? [];
        $appearance   = $workspace->settings['appearance'] ?? [];
        $themeColor   = $appearance['color'] ?? 'violet';

        // Map appearance color names → hex so the widget can adopt the theme colour
        $themeColorMap = [
            'violet'  => '#7c3aed', 'indigo' => '#6366f1', 'blue'   => '#3b82f6',
            'cyan'    => '#06b6d4', 'sky'    => '#0ea5e9', 'teal'   => '#14b8a6',
            'emerald' => '#10b981', 'green'  => '#22c55e', 'lime'   => '#84cc16',
            'yellow'  => '#eab308', 'amber'  => '#d97706', 'orange' => '#f97316',
            'red'     => '#ef4444', 'rose'   => '#f43f5e', 'pink'   => '#ec4899',
            'fuchsia' => '#d946ef', 'purple' => '#a855f7', 'neutral'=> '#6b7280',
        ];
        $themeColorHex = $themeColorMap[$themeColor] ?? '#7c3aed';

        return Inertia::render('Settings/LiveChat', [
            'workspaceId'    => $workspace->id,
            'themeColorHex'  => $themeColorHex,
            'config'         => [
                'greeting'      => $chatConfig['greeting']      ?? 'Hi there! How can we help?',
                'color'         => $chatConfig['color']         ?? $themeColorHex,
                'position'      => $chatConfig['position']      ?? 'bottom-right',
                'launcher_text' => $chatConfig['launcher_text'] ?? 'Chat with us',
            ],
            'snippet' => [
                'wsKey'    => config('broadcasting.connections.reverb.key'),
                'wsHost'   => config('broadcasting.connections.reverb.options.host'),
                'wsPort'   => config('broadcasting.connections.reverb.options.port'),
                'wsScheme' => config('broadcasting.connections.reverb.options.scheme', 'http'),
                'apiBase'  => config('app.url'),
            ],
        ]);
    }

    public function updateLiveChat(Request $request): RedirectResponse
    {
        $this->authorize('manage-settings');
        $validated = $request->validate([
            'greeting'      => ['required', 'string', 'max:200'],
            'color'         => ['required', 'string', 'max:20'],
            'position'      => ['required', 'in:bottom-right,bottom-left'],
            'launcher_text' => ['required', 'string', 'max:60'],
        ]);

        $workspace = Workspace::findOrFail($request->user()->workspace_id);
        $settings = $workspace->settings ?? [];
        $settings['live_chat'] = $validated;
        $workspace->settings = $settings;
        $workspace->save();

        return redirect()->back()->with('success', 'Live Chat settings saved.');
    }

    public function email(Request $request): Response
    {
        $mailboxes = \App\Domains\Mailbox\Models\Mailbox::where('workspace_id', $request->user()->workspace_id)
            ->get(['id', 'name', 'email']);

        return Inertia::render('Settings/Email', [
            'mailboxes' => $mailboxes,
            'guidance'  => [
                'spf'   => 'v=spf1 include:_spf.yourmailprovider.com ~all',
                'dkim'  => 'Configure DKIM in your SMTP provider (Postmark, SES, Mailgun) and add their CNAME/TXT record to DNS.',
                'dmarc' => 'v=DMARC1; p=quarantine; rua=mailto:dmarc@yourdomain.com',
            ],
        ]);
    }

    public function auditLog(Request $request): Response
    {
        $this->authorize('manage-settings');

        $workspaceId = $request->user()->workspace_id;
        $days   = (int) $request->get('days', 30);
        $days   = in_array($days, [7, 30, 90]) ? $days : 30;
        $search = (string) $request->get('search', '');

        $userIds = \App\Models\User::where('workspace_id', $workspaceId)->pluck('id');

        $query = \Spatie\Activitylog\Models\Activity::with('causer')
            ->where('causer_type', \App\Models\User::class)
            ->whereIn('causer_id', $userIds)
            ->where('created_at', '>=', now()->subDays($days));

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('description', 'ilike', "%{$search}%")
                  ->orWhere('log_name', 'ilike', "%{$search}%")
                  ->orWhere('subject_type', 'ilike', "%{$search}%");
            });
        }

        return Inertia::render('Settings/AuditLog', [
            'logs'   => $query->latest()->paginate(50)->withQueryString(),
            'days'   => $days,
            'search' => $search,
        ]);
    }
}
