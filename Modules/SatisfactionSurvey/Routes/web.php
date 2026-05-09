<?php

use Illuminate\Support\Facades\Route;
use Modules\SatisfactionSurvey\Http\Controllers\SurveyController;
use Modules\SatisfactionSurvey\Http\Controllers\SurveyReportController;
use Modules\SatisfactionSurvey\Http\Controllers\SurveySettingsController;

// Public, signature-protected endpoint — no auth required
Route::get('/survey/respond', [SurveyController::class, 'respond'])->name('survey.respond');

Route::middleware(['auth', 'module.active:SatisfactionSurvey'])->group(function () {
    Route::get('/settings/survey', [SurveySettingsController::class, 'index'])->name('settings.survey');
    Route::post('/settings/survey', [SurveySettingsController::class, 'update'])->name('settings.survey.update');
    Route::get('/settings/survey/report', [SurveyReportController::class, 'index'])->name('settings.survey.report');
});
