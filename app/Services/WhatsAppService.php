<?php

namespace App\Services;

use App\Models\Channel;
use App\Models\Message;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    private const API_VERSION = 'v21.0';
    private const BASE_URL = 'https://graph.facebook.com';

    public function sendTextMessage(Channel $channel, string $to, string $body): ?array
    {
        $phoneNumberId = $channel->getWhatsAppConfig('phone_number_id');
        $accessToken = $channel->getWhatsAppConfig('access_token');

        if (!$phoneNumberId || !$accessToken) {
            Log::error('WhatsApp send: missing configuration', [
                'channel_id' => $channel->id,
            ]);
            return null;
        }

        $response = $this->post($phoneNumberId, $accessToken, [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => 'text',
            'text' => ['body' => $body],
        ]);

        if (!$response->successful()) {
            Log::error('WhatsApp send failed', [
                'channel_id' => $channel->id,
                'status' => $response->status(),
                'body' => $response->json(),
            ]);
            return null;
        }

        return $response->json();
    }

    public function markAsRead(Channel $channel, string $messageId): void
    {
        $phoneNumberId = $channel->getWhatsAppConfig('phone_number_id');
        $accessToken = $channel->getWhatsAppConfig('access_token');

        if (!$phoneNumberId || !$accessToken) {
            return;
        }

        $this->post($phoneNumberId, $accessToken, [
            'messaging_product' => 'whatsapp',
            'status' => 'read',
            'message_id' => $messageId,
        ]);
    }

    private function post(string $phoneNumberId, string $accessToken, array $data): Response
    {
        $url = self::BASE_URL . '/' . self::API_VERSION . '/' . $phoneNumberId . '/messages';

        return Http::withToken($accessToken)
            ->timeout(30)
            ->post($url, $data);
    }
}
