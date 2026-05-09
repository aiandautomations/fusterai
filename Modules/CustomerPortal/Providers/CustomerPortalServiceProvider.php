<?php

namespace Modules\CustomerPortal\Providers;

use Illuminate\Support\ServiceProvider;

class CustomerPortalServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Migrations and routes are handled by ModuleServiceProvider for all modules.
    }
}
