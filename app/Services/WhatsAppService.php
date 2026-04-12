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

    /**
     * Get media URL from WhatsApp media ID.
     * Step 1: GET /{media_id} → returns url field
     */
    public function getMediaUrl(string $accessToken, string $mediaId): ?string
    {
        $url = self::BASE_URL . '/' . self::API_VERSION . '/' . $mediaId;

        $response = Http::withToken($accessToken)
            ->timeout(15)
            ->get($url);

        if (!$response->successful()) {
            Log::error('WhatsApp getMediaUrl failed', [
                'media_id' => $mediaId,
                'status' => $response->status(),
                'body' => $response->json(),
            ]);
            return null;
        }

        return $response->json('url');
    }

    /**
     * Download media binary from WhatsApp CDN URL.
     * Step 2: GET {media_url} → returns binary content
     */
    public function downloadMedia(string $accessToken, string $mediaUrl): ?string
    {
        $response = Http::withToken($accessToken)
            ->timeout(60)
            ->get($mediaUrl);

        if (!$response->successful()) {
            Log::error('WhatsApp downloadMedia failed', [
                'url' => $mediaUrl,
                'status' => $response->status(),
            ]);
            return null;
        }

        return $response->body();
    }

    /**
     * Upload media to WhatsApp CDN and return the media ID.
     */
    public function uploadMedia(Channel $channel, string $filePath, string $mimeType): ?string
    {
        $phoneNumberId = $channel->getWhatsAppConfig('phone_number_id');
        $accessToken = $channel->getWhatsAppConfig('access_token');

        if (!$phoneNumberId || !$accessToken) {
            return null;
        }

        $url = self::BASE_URL . '/' . self::API_VERSION . '/' . $phoneNumberId . '/media';

        $response = Http::withToken($accessToken)
            ->timeout(60)
            ->attach('file', file_get_contents($filePath), basename($filePath), ['Content-Type' => $mimeType])
            ->post($url, [
                'messaging_product' => 'whatsapp',
                'type' => $mimeType,
            ]);

        if (!$response->successful()) {
            Log::error('WhatsApp uploadMedia failed', [
                'channel_id' => $channel->id,
                'status' => $response->status(),
                'body' => $response->json(),
            ]);
            return null;
        }

        return $response->json('id');
    }

    /**
     * Send a media message (image, video, audio, document) via WhatsApp.
     */
    public function sendMediaMessage(Channel $channel, string $to, string $mediaType, string $mediaId, ?string $caption = null): ?array
    {
        $phoneNumberId = $channel->getWhatsAppConfig('phone_number_id');
        $accessToken = $channel->getWhatsAppConfig('access_token');

        if (!$phoneNumberId || !$accessToken) {
            return null;
        }

        $mediaPayload = ['id' => $mediaId];
        if ($caption && in_array($mediaType, ['image', 'video', 'document'])) {
            $mediaPayload['caption'] = $caption;
        }
        if ($mediaType === 'document') {
            $mediaPayload['filename'] = $caption ?? 'document';
        }

        $response = $this->post($phoneNumberId, $accessToken, [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => $mediaType,
            $mediaType => $mediaPayload,
        ]);

        if (!$response->successful()) {
            Log::error('WhatsApp sendMediaMessage failed', [
                'channel_id' => $channel->id,
                'media_type' => $mediaType,
                'status' => $response->status(),
                'body' => $response->json(),
            ]);
            return null;
        }

        return $response->json();
    }

    // ========== TEMPLATE API METHODS ==========

    /**
     * Create a message template on Meta.
     * POST /v21.0/{waba_id}/message_templates
     */
    public function createTemplate(Channel $channel, array $payload): ?array
    {
        $wabaId = $channel->getWhatsAppConfig('waba_id');
        $accessToken = $channel->getWhatsAppConfig('access_token');

        if (!$wabaId || !$accessToken) {
            Log::error('WhatsApp createTemplate: missing waba_id or access_token', ['channel_id' => $channel->id]);
            return null;
        }

        $url = self::BASE_URL . '/' . self::API_VERSION . '/' . $wabaId . '/message_templates';

        $response = Http::withToken($accessToken)->timeout(30)->post($url, $payload);

        if (!$response->successful()) {
            Log::error('WhatsApp createTemplate failed', [
                'channel_id' => $channel->id,
                'status' => $response->status(),
                'body' => $response->json(),
            ]);
            return null;
        }

        return $response->json();
    }

    /**
     * List all message templates from Meta.
     * GET /v21.0/{waba_id}/message_templates
     */
    public function listTemplates(Channel $channel): ?array
    {
        $wabaId = $channel->getWhatsAppConfig('waba_id');
        $accessToken = $channel->getWhatsAppConfig('access_token');

        if (!$wabaId || !$accessToken) {
            return null;
        }

        $url = self::BASE_URL . '/' . self::API_VERSION . '/' . $wabaId . '/message_templates?limit=100';

        $allData = [];
        while ($url) {
            $response = Http::withToken($accessToken)->timeout(30)->get($url);

            if (!$response->successful()) {
                Log::error('WhatsApp listTemplates failed', [
                    'channel_id' => $channel->id,
                    'status' => $response->status(),
                    'body' => $response->json(),
                ]);
                return null;
            }

            $json = $response->json();
            $allData = array_merge($allData, $json['data'] ?? []);
            $url = $json['paging']['next'] ?? null;
        }

        return $allData;
    }

    /**
     * Delete a message template from Meta.
     * DELETE /v21.0/{waba_id}/message_templates?name={name}
     */
    public function deleteTemplate(Channel $channel, string $templateName): ?array
    {
        $wabaId = $channel->getWhatsAppConfig('waba_id');
        $accessToken = $channel->getWhatsAppConfig('access_token');

        if (!$wabaId || !$accessToken) {
            return null;
        }

        $url = self::BASE_URL . '/' . self::API_VERSION . '/' . $wabaId . '/message_templates?name=' . urlencode($templateName);

        $response = Http::withToken($accessToken)->timeout(30)->delete($url);

        if (!$response->successful()) {
            Log::error('WhatsApp deleteTemplate failed', [
                'channel_id' => $channel->id,
                'name' => $templateName,
                'status' => $response->status(),
                'body' => $response->json(),
            ]);
            return null;
        }

        return $response->json();
    }

    /**
     * Send a template message to a recipient.
     * POST /v21.0/{phone_number_id}/messages (type=template)
     */
    public function sendTemplate(Channel $channel, string $to, string $templateName, string $language, array $components = []): ?array
    {
        $phoneNumberId = $channel->getWhatsAppConfig('phone_number_id');
        $accessToken = $channel->getWhatsAppConfig('access_token');

        if (!$phoneNumberId || !$accessToken) {
            return null;
        }

        $templatePayload = [
            'name' => $templateName,
            'language' => ['code' => $language],
        ];

        if (!empty($components)) {
            $templatePayload['components'] = $components;
        }

        $response = $this->post($phoneNumberId, $accessToken, [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => 'template',
            'template' => $templatePayload,
        ]);

        if (!$response->successful()) {
            Log::error('WhatsApp sendTemplate failed', [
                'channel_id' => $channel->id,
                'template' => $templateName,
                'to' => $to,
                'status' => $response->status(),
                'body' => $response->json(),
            ]);
            return null;
        }

        return $response->json();
    }

    /**
     * Create a resumable upload session for media (used in template creation).
     * POST /v21.0/{app_id}/uploads
     */
    public function createUploadSession(Channel $channel, int $fileSize, string $mimeType): ?string
    {
        $accessToken = $channel->getWhatsAppConfig('access_token');
        $appId = $channel->getWhatsAppConfig('app_id');

        if (!$accessToken || !$appId) {
            Log::error('WhatsApp createUploadSession: missing app_id', ['channel_id' => $channel->id]);
            return null;
        }

        $url = self::BASE_URL . '/' . self::API_VERSION . '/' . $appId . '/uploads';

        $response = Http::withToken($accessToken)->timeout(30)->post($url, [
            'file_length' => $fileSize,
            'file_type' => $mimeType,
        ]);

        if (!$response->successful()) {
            Log::error('WhatsApp createUploadSession failed', [
                'channel_id' => $channel->id,
                'status' => $response->status(),
                'body' => $response->json(),
            ]);
            return null;
        }

        return $response->json('id');
    }

    /**
     * Upload file content to a resumable upload session.
     * Returns the h: handle used in template creation.
     */
    public function uploadSessionChunk(Channel $channel, string $sessionId, string $fileContent): ?string
    {
        $accessToken = $channel->getWhatsAppConfig('access_token');

        if (!$accessToken) {
            return null;
        }

        $url = self::BASE_URL . '/' . self::API_VERSION . '/' . $sessionId;

        $response = Http::withToken($accessToken)
            ->timeout(60)
            ->withHeaders(['file_offset' => 0])
            ->withBody($fileContent, 'application/octet-stream')
            ->post($url);

        if (!$response->successful()) {
            Log::error('WhatsApp uploadSessionChunk failed', [
                'channel_id' => $channel->id,
                'session_id' => $sessionId,
                'status' => $response->status(),
                'body' => $response->json(),
            ]);
            return null;
        }

        return $response->json('h');
    }

    private function post(string $phoneNumberId, string $accessToken, array $data): Response
    {
        $url = self::BASE_URL . '/' . self::API_VERSION . '/' . $phoneNumberId . '/messages';

        return Http::withToken($accessToken)
            ->timeout(30)
            ->post($url, $data);
    }
}
