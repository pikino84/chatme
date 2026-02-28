<?php

use App\Http\Controllers\Webchat\WebchatController;
use App\Http\Controllers\Webhooks\WhatsAppWebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// WhatsApp Cloud API webhooks (no auth middleware - validated by HMAC signature)
Route::prefix('webhooks/whatsapp/{channelUuid}')->group(function () {
    Route::get('/', [WhatsAppWebhookController::class, 'verify']);
    Route::post('/', [WhatsAppWebhookController::class, 'handle']);
});

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
