<?php

use Illuminate\Support\Facades\Route;
use Modules\ConversationRouting\Http\Controllers\RoutingSettingsController;

Route::middleware(['auth', 'module.active:ConversationRouting'])->group(function () {
    Route::get('/settings/routing',  [RoutingSettingsController::class, 'index'])->name('settings.routing');
    Route::post('/settings/routing', [RoutingSettingsController::class, 'update'])->name('settings.routing.update');
});
