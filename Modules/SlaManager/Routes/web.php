<?php

use Illuminate\Support\Facades\Route;
use Modules\SlaManager\Http\Controllers\SlaSettingsController;

Route::middleware(['auth', 'module.active:SlaManager'])->group(function () {
    Route::get('/settings/sla',  [SlaSettingsController::class, 'index'])->name('settings.sla');
    Route::post('/settings/sla', [SlaSettingsController::class, 'update'])->name('settings.sla.update');
});
