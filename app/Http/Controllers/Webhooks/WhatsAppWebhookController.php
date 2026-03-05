<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Services\WhatsAppWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class WhatsAppWebhookController extends Controller
{
    public function __construct(
        private readonly WhatsAppWebhookService $webhookService,
    ) {}

    /**
     * Meta webhook verification (GET).
     * Meta sends hub.mode, hub.verify_token, hub.challenge.
     */
    public function verify(Request $request, string $channelUuid): Response
    {
        $channel = Channel::where('uuid', $channelUuid)
            ->where('type', 'whatsapp')
            ->where('is_active', true)
            ->first();

        if (!$channel) {
            Log::warning('WhatsApp webhook verify: channel not found', ['uuid' => $channelUuid]);
            return response('Channel not found', 404);
        }

        $mode = $request->query('hub_mode') ?? $request->query('hub.mode');
        $token = $request->query('hub_verify_token') ?? $request->query('hub.verify_token');
        $challenge = $request->query('hub_challenge') ?? $request->query('hub.challenge');

        if ($mode !== 'subscribe') {
            return response('Invalid mode', 403);
        }

        $expectedToken = $channel->getWhatsAppConfig('verify_token');

        if (!$expectedToken || $token !== $expectedToken) {
            Log::warning('WhatsApp webhook verify: token mismatch', [
                'channel_id' => $channel->id,
                'organization_id' => $channel->organization_id,
            ]);
            return response('Invalid verify token', 403);
        }

        return response($challenge, 200);
    }

    /**
     * Meta webhook payload (POST).
     * Validates HMAC-SHA256 signature, then processes messages.
     */
    public function handle(Request $request, string $channelUuid): JsonResponse
    {
        $channel = Channel::where('uuid', $channelUuid)
            ->where('type', 'whatsapp')
            ->where('is_active', true)
            ->first();

        if (!$channel) {
            Log::warning('WhatsApp webhook handle: channel not found', ['uuid' => $channelUuid]);
            return response()->json(['error' => 'Channel not found'], 404);
        }

        if (!$this->validateSignature($request, $channel)) {
            Log::warning('WhatsApp webhook handle: invalid signature', [
                'channel_id' => $channel->id,
                'organization_id' => $channel->organization_id,
            ]);
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $this->webhookService->process($channel, $request->all());

        return response()->json(['status' => 'ok']);
    }

    private function validateSignature(Request $request, Channel $channel): bool
    {
        $appSecret = $channel->getWhatsAppConfig('app_secret');

        if (!$appSecret) {
            return false;
        }

        $signature = $request->header('X-Hub-Signature-256');

        if (!$signature) {
            return false;
        }

        $expectedSignature = 'sha256=' . hash_hmac('sha256', $request->getContent(), $appSecret);

        return hash_equals($expectedSignature, $signature);
    }
}
