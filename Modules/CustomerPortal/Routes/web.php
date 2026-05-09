<?php

use Illuminate\Support\Facades\Route;
use Modules\CustomerPortal\Http\Controllers\PortalAuthController;
use Modules\CustomerPortal\Http\Controllers\PortalKnowledgeBaseController;
use Modules\CustomerPortal\Http\Controllers\PortalSettingsController;
use Modules\CustomerPortal\Http\Controllers\PortalTicketController;
use Modules\CustomerPortal\Http\Middleware\AuthenticatePortalCustomer;
use Modules\CustomerPortal\Http\Middleware\EnsurePortalIsEnabled;

// ── Admin settings (agent-facing) ────────────────────────────────────────────
Route::middleware(['auth', 'module.active:CustomerPortal'])->group(function () {
    Route::get('/settings/portal', [PortalSettingsController::class, 'index'])->name('settings.portal');
    Route::post('/settings/portal', [PortalSettingsController::class, 'update'])->name('settings.portal.update');
});

// ── Customer portal (public-facing) ──────────────────────────────────────────
Route::prefix('/portal/{workspace:slug}')
    ->middleware(['module.active:CustomerPortal', EnsurePortalIsEnabled::class])
    ->group(function () {
        // Auth (guest)
        Route::get('/', [PortalAuthController::class, 'show'])->name('portal.login');
        Route::post('/magic-link', [PortalAuthController::class, 'sendLink'])
            ->middleware('throttle:5,1')
            ->name('portal.magic-link');
        Route::get('/check-email', [PortalAuthController::class, 'checkEmail'])->name('portal.check-email');
        Route::get('/auth/{token}', [PortalAuthController::class, 'authenticate'])->name('portal.auth');
        Route::post('/logout', [PortalAuthController::class, 'logout'])->name('portal.logout');

        // Protected (authenticated customer)
        Route::middleware(AuthenticatePortalCustomer::class)->group(function () {
            Route::get('/tickets', [PortalTicketController::class, 'index'])->name('portal.tickets.index');
            Route::get('/tickets/new', [PortalTicketController::class, 'create'])->name('portal.tickets.create');
            Route::post('/tickets', [PortalTicketController::class, 'store'])->name('portal.tickets.store');
            Route::get('/tickets/{conversation}', [PortalTicketController::class, 'show'])->name('portal.tickets.show');
            Route::post('/tickets/{conversation}/reply', [PortalTicketController::class, 'reply'])->name('portal.tickets.reply');

            Route::get('/kb', [PortalKnowledgeBaseController::class, 'index'])->name('portal.kb.index');
            Route::get('/kb/{document}', [PortalKnowledgeBaseController::class, 'show'])->name('portal.kb.show');
        });
    });
