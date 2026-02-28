<?php

namespace App\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class WebchatTokenService
{
    public function create(int $organizationId, int $channelId): array
    {
        $sessionId = (string) Str::uuid();

        $payload = [
            'sub' => 'webchat_session',
            'sid' => $sessionId,
            'org' => $organizationId,
            'chn' => $channelId,
            'cid' => null,
            'iat' => now()->timestamp,
            'exp' => now()->timestamp + 86400, // 24h
        ];

        return [
            'token' => Crypt::encryptString(json_encode($payload)),
            'session_id' => $sessionId,
        ];
    }

    public function decode(string $token): ?array
    {
        try {
            $payload = json_decode(Crypt::decryptString($token), true);
        } catch (\Throwable) {
            return null;
        }

        if (!$payload || ($payload['sub'] ?? '') !== 'webchat_session') {
            return null;
        }

        if (($payload['exp'] ?? 0) < now()->timestamp) {
            return null;
        }

        return $payload;
    }

    public function withConversation(string $token, int $conversationId): string
    {
        $payload = $this->decode($token);

        if (!$payload) {
            throw new \RuntimeException('Invalid token');
        }

        $payload['cid'] = $conversationId;

        return Crypt::encryptString(json_encode($payload));
    }
}
