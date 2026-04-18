<?php

use App\Http\Controllers\Api\BounceWebhookController;
use App\Http\Controllers\Api\ConversationApiController;
use App\Http\Controllers\Api\InboundWebhookController;
use App\Http\Controllers\Api\LiveChatMessageController;
use App\Http\Controllers\Api\WhatsAppWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public live chat endpoints (no auth, rate limited)
Route::middleware('throttle:120,1')->group(function () {
    Route::get('/livechat/messages', [LiveChatMessageController::class, 'messages']);
    Route::get('/livechat/config', [LiveChatMessageController::class, 'config']);
});
// Tighter limit on message sending to prevent inbox spam
Route::middleware('throttle:10,1')->group(function () {
    Route::post('/livechat/message', [LiveChatMessageController::class, 'store']);
});

// Webhooks (no auth — validated by token in URL, rate limited to prevent abuse)
Route::middleware('throttle:30,1')->group(function () {
    Route::post('/webhooks/inbound/{token}', [InboundWebhookController::class, 'receive']);
    Route::post('/webhooks/bounce/{token}', [BounceWebhookController::class, 'receive']);
});

// WhatsApp verification (GET) + inbound (POST)
Route::get('/webhooks/whatsapp/{token}', [WhatsAppWebhookController::class, 'verify']);
Route::middleware('throttle:60,1')->post('/webhooks/whatsapp/{token}', [WhatsAppWebhookController::class, 'receive']);

// Authenticated API (Passport personal access token or OAuth)
Route::middleware(['auth:api', 'throttle:60,1'])->name('api.')->group(function () {
    Route::apiResource('conversations', ConversationApiController::class)
        ->only(['index', 'show', 'store', 'update']);

    Route::post('/conversations/{conversation}/reply', [ConversationApiController::class, 'reply'])
        ->name('conversations.reply');
});
