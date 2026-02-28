<?php

namespace App\Http\Controllers\Webchat;

use App\Events\ConversationCreated;
use App\Events\MessageReceivedEvent;
use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\WebchatTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class WebchatController extends Controller
{
    public function __construct(
        private readonly WebchatTokenService $tokenService,
    ) {}

    public function formSchema(Request $request, string $channelUuid): JsonResponse
    {
        $channel = Channel::withoutGlobalScopes()
            ->where('uuid', $channelUuid)
            ->where('type', 'webchat')
            ->where('is_active', true)
            ->first();

        if (!$channel) {
            return response()->json(['error' => 'Channel not found'], 404);
        }

        if (!$this->validateOrigin($request, $channel)) {
            return response()->json(['error' => 'Origin not allowed'], 403);
        }

        $form = $channel->form;

        if (!$form || !$form->is_active) {
            return response()->json(['form' => null]);
        }

        return response()->json(['form' => $form->getPublicSchema()]);
    }

    public function createSession(Request $request, string $channelUuid): JsonResponse
    {
        $channel = Channel::where('uuid', $channelUuid)
            ->where('type', 'webchat')
            ->where('is_active', true)
            ->first();

        if (!$channel) {
            return response()->json(['error' => 'Channel not found'], 404);
        }

        if (!$this->validateOrigin($request, $channel)) {
            return response()->json(['error' => 'Origin not allowed'], 403);
        }

        $session = $this->tokenService->create($channel->organization_id, $channel->id);

        return response()->json([
            'token' => $session['token'],
            'session_id' => $session['session_id'],
        ]);
    }

    public function sendMessage(Request $request, string $channelUuid): JsonResponse
    {
        $channel = Channel::where('uuid', $channelUuid)
            ->where('type', 'webchat')
            ->where('is_active', true)
            ->first();

        if (!$channel) {
            return response()->json(['error' => 'Channel not found'], 404);
        }

        $token = $request->header('X-Webchat-Token');
        if (!$token) {
            return response()->json(['error' => 'Token required'], 401);
        }

        $payload = $this->tokenService->decode($token);
        if (!$payload) {
            return response()->json(['error' => 'Invalid or expired token'], 401);
        }

        if ($payload['chn'] !== $channel->id || $payload['org'] !== $channel->organization_id) {
            return response()->json(['error' => 'Token does not match channel'], 403);
        }

        $request->validate([
            'body' => 'required|string|min:1|max:2000',
            'form_data' => 'nullable|array',
            'form_data.*' => 'nullable|string|max:1000',
        ]);

        // Per-session burst rate limit: 1 msg every 3 seconds
        $burstKey = 'webchat:burst:' . $payload['sid'];
        if (RateLimiter::tooManyAttempts($burstKey, 1)) {
            return response()->json(['error' => 'Too many messages, slow down'], 429);
        }
        RateLimiter::hit($burstKey, 3);

        $formData = $request->input('form_data');
        $conversation = $this->findOrCreateConversation($channel, $payload, $formData);
        $updatedToken = null;

        if (!$payload['cid']) {
            $updatedToken = $this->tokenService->withConversation($token, $conversation->id);
        }

        $message = Message::create([
            'organization_id' => $channel->organization_id,
            'conversation_id' => $conversation->id,
            'body' => $request->input('body'),
            'type' => 'text',
            'direction' => 'inbound',
            'metadata' => [
                'source' => 'webchat',
                'session_id' => $payload['sid'],
            ],
        ]);

        $conversation->update(['last_message_at' => now()]);

        MessageReceivedEvent::dispatch($message);

        $response = [
            'message_id' => $message->id,
        ];

        if ($updatedToken) {
            $response['token'] = $updatedToken;
        }

        return response()->json($response, 201);
    }

    public function getMessages(Request $request, string $channelUuid): JsonResponse
    {
        $channel = Channel::where('uuid', $channelUuid)
            ->where('type', 'webchat')
            ->where('is_active', true)
            ->first();

        if (!$channel) {
            return response()->json(['error' => 'Channel not found'], 404);
        }

        $token = $request->header('X-Webchat-Token');
        if (!$token) {
            return response()->json(['error' => 'Token required'], 401);
        }

        $payload = $this->tokenService->decode($token);
        if (!$payload || !$payload['cid']) {
            return response()->json(['error' => 'Invalid token or no conversation'], 401);
        }

        if ($payload['chn'] !== $channel->id) {
            return response()->json(['error' => 'Token does not match channel'], 403);
        }

        $messages = Message::withoutGlobalScopes()
            ->where('organization_id', $payload['org'])
            ->where('conversation_id', $payload['cid'])
            ->where('type', '!=', 'internal_note')
            ->orderBy('created_at', 'asc')
            ->select(['id', 'body', 'type', 'direction', 'created_at'])
            ->limit(100)
            ->get();

        return response()->json(['messages' => $messages]);
    }

    public function broadcastAuth(Request $request, string $channelUuid): JsonResponse
    {
        $token = $request->header('X-Webchat-Token');
        if (!$token) {
            return response()->json(['error' => 'Token required'], 401);
        }

        $payload = $this->tokenService->decode($token);
        if (!$payload || !$payload['cid']) {
            return response()->json(['error' => 'Invalid token'], 401);
        }

        $channelName = $request->input('channel_name', '');
        $expectedChannel = "private-conversation.{$payload['org']}.{$payload['cid']}";

        if ($channelName !== $expectedChannel) {
            return response()->json(['error' => 'Channel not allowed'], 403);
        }

        // Generate Pusher-compatible auth response
        $socketId = $request->input('socket_id', '');
        $stringToSign = $socketId . ':' . $channelName;
        $key = config('broadcasting.connections.reverb.key');
        $secret = config('broadcasting.connections.reverb.secret');
        $signature = hash_hmac('sha256', $stringToSign, $secret);

        return response()->json([
            'auth' => $key . ':' . $signature,
        ]);
    }

    private function findOrCreateConversation(Channel $channel, array $tokenPayload, ?array $formData = null): Conversation
    {
        if ($tokenPayload['cid']) {
            $conversation = Conversation::withoutGlobalScopes()
                ->where('id', $tokenPayload['cid'])
                ->where('organization_id', $channel->organization_id)
                ->where('channel_id', $channel->id)
                ->first();

            if ($conversation && $conversation->status !== 'closed') {
                return $conversation;
            }
        }

        // Find existing open conversation for this session
        $conversation = Conversation::withoutGlobalScopes()
            ->where('organization_id', $channel->organization_id)
            ->where('channel_id', $channel->id)
            ->where('contact_identifier', $tokenPayload['sid'])
            ->where('status', '!=', 'closed')
            ->first();

        if ($conversation) {
            return $conversation;
        }

        $contactName = 'Visitante Web';
        $metadata = ['source' => 'widget_form'];

        if ($formData) {
            $metadata['form_data'] = $formData;
            if (!empty($formData['name'])) {
                $contactName = $formData['name'];
            }
        }

        $conversation = Conversation::create([
            'organization_id' => $channel->organization_id,
            'channel_id' => $channel->id,
            'contact_name' => $contactName,
            'contact_identifier' => $tokenPayload['sid'],
            'status' => 'open',
            'priority' => 'normal',
            'metadata' => $metadata,
            'last_message_at' => now(),
        ]);

        ConversationCreated::dispatch($conversation);

        return $conversation;
    }

    private function validateOrigin(Request $request, Channel $channel): bool
    {
        $allowedOrigins = $channel->getWhatsAppConfig('allowed_origins');

        if (empty($allowedOrigins)) {
            return true; // No restriction configured (dev mode)
        }

        $origin = $request->header('Origin') ?? $request->header('Referer');

        if (!$origin) {
            return false;
        }

        $originHost = parse_url($origin, PHP_URL_SCHEME) . '://' . parse_url($origin, PHP_URL_HOST);

        return in_array($originHost, $allowedOrigins, true);
    }
}
