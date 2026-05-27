<?php

use App\Http\Controllers\Api\LeadApiController;
use App\Http\Controllers\Webchat\WebchatController;
use App\Http\Controllers\Webhooks\EvolutionWebhookController;
use App\Http\Controllers\Webhooks\StripeWebhookController;
use App\Http\Controllers\Webhooks\WhatsAppWebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Public lead intake API (validated by X-API-Token header)
Route::post('/leads', [LeadApiController::class, 'store'])
    ->middleware('throttle:10,1');

// WhatsApp Cloud API webhooks (no auth middleware - validated by HMAC signature)
Route::prefix('webhooks/whatsapp/{channelUuid}')->group(function () {
    Route::get('/', [WhatsAppWebhookController::class, 'verify']);
    Route::post('/', [WhatsAppWebhookController::class, 'handle']);
});

// Stripe webhooks (no auth - validated by signature)
Route::post('/webhooks/stripe', [StripeWebhookController::class, 'handle']);

// Evolution API webhooks — WhatsApp Directo (no auth middleware - validated by header token)
Route::post('/webhooks/evolution', [EvolutionWebhookController::class, 'handle']);

// Webchat public API (no auth middleware - validated by encrypted session token)
Route::prefix('webchat/{channelUuid}')->group(function () {
    Route::get('/form-schema', [WebchatController::class, 'formSchema'])
        ->middleware('throttle:webchat-poll');
    Route::post('/session', [WebchatController::class, 'createSession'])
        ->middleware('throttle:webchat-session');
    Route::post('/messages', [WebchatController::class, 'sendMessage'])
        ->middleware('throttle:webchat-message');
    Route::get('/messages', [WebchatController::class, 'getMessages'])
        ->middleware('throttle:webchat-poll');
    Route::post('/broadcasting/auth', [WebchatController::class, 'broadcastAuth'])
        ->middleware('throttle:webchat-poll');
});
