<?php
namespace App\Providers;

use App\Support\Hooks;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Hooks::class);
    }

    public function boot(): void
    {
        $modulesPath = base_path('Modules');
        if (! is_dir($modulesPath)) {
            return;
        }

        // ── Migrations ────────────────────────────────────────────────────────
        // Always register from ALL module directories so `php artisan migrate`
        // picks them up regardless of active flag.
        foreach (glob($modulesPath . '/*/Database/Migrations') as $path) {
            $this->loadMigrationsFrom($path);
        }

        // ── Routes ────────────────────────────────────────────────────────────
        // Load routes from ALL installed modules inside the `web` middleware
        // group so sessions, CSRF, Inertia, and auth work correctly.
        // Each module's routes should additionally use `module.active:{Alias}`
        // to gate access for inactive modules.
        foreach (glob($modulesPath . '/*/Routes/web.php') as $routeFile) {
            Route::middleware('web')->group($routeFile);
        }

        // ── Service Providers (hooks, schedulers, etc.) ───────────────────────
        // Boot only for active modules. Each module is isolated in its own
        // try-catch so a bad module never prevents the others from loading.
        try {
            $modules = \App\Domains\AI\Models\Module::where('active', true)->get();
        } catch (\Throwable $e) {
            Log::warning('ModuleServiceProvider: could not query modules table — ' . $e->getMessage());
            return;
        }

        foreach ($modules as $module) {
            $providerClass = "Modules\\{$module->alias}\\Providers\\{$module->alias}ServiceProvider";
            if (! class_exists($providerClass)) {
                continue;
            }

            try {
                $this->app->register($providerClass);
            } catch (\Throwable $e) {
                Log::error("ModuleServiceProvider: failed to boot [{$module->alias}] — " . $e->getMessage(), [
                    'module'    => $module->alias,
                    'exception' => $e,
                ]);
            }
        }
    }
}
