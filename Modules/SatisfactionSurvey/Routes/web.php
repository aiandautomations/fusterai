<?php

use Illuminate\Support\Facades\Route;
use Modules\SatisfactionSurvey\Http\Controllers\SurveyController;

// Public, signature-protected endpoint — no auth required
Route::get('/survey/respond', [SurveyController::class, 'respond'])->name('survey.respond');
