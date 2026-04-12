<?php

namespace App\Services;

use App\Models\Channel;
use App\Models\WhatsAppTemplate;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class WhatsAppTemplateService
{
    public function __construct(
        private WhatsAppService $whatsAppService,
    ) {}

    public function create(int $orgId, int $channelId, array $data): WhatsAppTemplate
    {
        $channel = Channel::findOrFail($channelId);

        // Build Meta components payload
        $components = $this->buildMetaComponents($data);

        $template = WhatsAppTemplate::create([
            'organization_id' => $orgId,
            'channel_id' => $channelId,
            'name' => $data['name'],
            'language' => $data['language'],
            'category' => $data['category'],
            'status' => 'PENDING',
            'components' => $components,
            'header_type' => $data['header_type'] ?? null,
            'header_media_path' => $data['header_media_path'] ?? null,
            'body_text' => $data['body_text'],
            'body_example_values' => $data['body_example_values'] ?? null,
            'footer_text' => $data['footer_text'] ?? null,
            'buttons' => $data['buttons'] ?? null,
        ]);

        // Submit to Meta
        $metaPayload = [
            'name' => $data['name'],
            'language' => $data['language'],
            'category' => strtoupper($data['category']),
            'components' => $components,
        ];

        $response = $this->whatsAppService->createTemplate($channel, $metaPayload);

        if ($response) {
            $template->update([
                'meta_template_id' => $response['id'] ?? null,
                'status' => $response['status'] ?? 'PENDING',
            ]);
        } else {
            $template->update(['status' => 'REJECTED', 'rejection_reason' => 'Error al enviar a Meta API']);
        }

        return $template->fresh();
    }

    public function update(WhatsAppTemplate $template, array $data): WhatsAppTemplate
    {
        $channel = $template->channel;
        $components = $this->buildMetaComponents($data);

        // Delete old template from Meta and recreate
        if ($template->meta_template_id) {
            $this->whatsAppService->deleteTemplate($channel, $template->name);
        }

        $template->update([
            'name' => $data['name'],
            'language' => $data['language'],
            'category' => $data['category'],
            'components' => $components,
            'header_type' => $data['header_type'] ?? $template->header_type,
            'header_media_path' => $data['header_media_path'] ?? $template->header_media_path,
            'body_text' => $data['body_text'],
            'body_example_values' => $data['body_example_values'] ?? null,
            'footer_text' => $data['footer_text'] ?? null,
            'buttons' => $data['buttons'] ?? null,
            'status' => 'PENDING',
            'rejection_reason' => null,
        ]);

        // Resubmit to Meta
        $metaPayload = [
            'name' => $data['name'],
            'language' => $data['language'],
            'category' => strtoupper($data['category']),
            'components' => $components,
        ];

        $response = $this->whatsAppService->createTemplate($channel, $metaPayload);

        if ($response) {
            $template->update([
                'meta_template_id' => $response['id'] ?? null,
                'status' => $response['status'] ?? 'PENDING',
            ]);
        }

        return $template->fresh();
    }

    public function delete(WhatsAppTemplate $template): void
    {
        $channel = $template->channel;

        // Delete from Meta
        if ($template->name) {
            $this->whatsAppService->deleteTemplate($channel, $template->name);
        }

        // Clean S3 media
        if ($template->header_media_path) {
            try {
                Storage::disk(\App\Models\MessageAttachment::mediaDisk())->delete($template->header_media_path);
            } catch (\Throwable $e) {
                Log::warning('Failed to delete template media', ['path' => $template->header_media_path]);
            }
        }

        $template->delete();
    }

    public function syncFromMeta(Channel $channel): int
    {
        $metaTemplates = $this->whatsAppService->listTemplates($channel);

        if ($metaTemplates === null) {
            return 0;
        }

        $synced = 0;

        foreach ($metaTemplates as $mt) {
            $name = $mt['name'] ?? null;
            $language = $mt['language'] ?? null;

            if (!$name || !$language) continue;

            // Extract component details
            $headerType = null;
            $bodyText = '';
            $footerText = null;
            $buttons = null;
            $bodyExamples = null;

            foreach ($mt['components'] ?? [] as $comp) {
                $type = strtoupper($comp['type'] ?? '');

                if ($type === 'HEADER') {
                    $format = strtolower($comp['format'] ?? 'text');
                    $headerType = $format === 'text' ? 'text' : $format;
                } elseif ($type === 'BODY') {
                    $bodyText = $comp['text'] ?? '';
                    if (!empty($comp['example']['body_text'][0])) {
                        $bodyExamples = $comp['example']['body_text'][0];
                    }
                } elseif ($type === 'FOOTER') {
                    $footerText = $comp['text'] ?? null;
                } elseif ($type === 'BUTTONS') {
                    $buttons = $comp['buttons'] ?? null;
                }
            }

            WhatsAppTemplate::withoutGlobalScopes()->updateOrCreate(
                [
                    'organization_id' => $channel->organization_id,
                    'channel_id' => $channel->id,
                    'name' => $name,
                    'language' => $language,
                ],
                [
                    'category' => strtoupper($mt['category'] ?? 'UTILITY'),
                    'status' => strtoupper($mt['status'] ?? 'PENDING'),
                    'components' => $mt['components'] ?? [],
                    'header_type' => $headerType,
                    'body_text' => $bodyText,
                    'body_example_values' => $bodyExamples,
                    'footer_text' => $footerText,
                    'buttons' => $buttons,
                    'meta_template_id' => $mt['id'] ?? null,
                    'rejection_reason' => $mt['rejected_reason'] ?? null,
                    'last_synced_at' => now(),
                ]
            );

            $synced++;
        }

        return $synced;
    }

    public function pollStatuses(Channel $channel): int
    {
        $metaTemplates = $this->whatsAppService->listTemplates($channel);

        if ($metaTemplates === null) {
            return 0;
        }

        $updated = 0;
        $metaIndex = [];
        foreach ($metaTemplates as $mt) {
            $key = ($mt['name'] ?? '') . ':' . ($mt['language'] ?? '');
            $metaIndex[$key] = $mt;
        }

        $pendingTemplates = WhatsAppTemplate::withoutGlobalScopes()
            ->where('organization_id', $channel->organization_id)
            ->where('channel_id', $channel->id)
            ->where('status', 'PENDING')
            ->get();

        foreach ($pendingTemplates as $template) {
            $key = $template->name . ':' . $template->language;
            $mt = $metaIndex[$key] ?? null;

            if (!$mt) continue;

            $newStatus = strtoupper($mt['status'] ?? 'PENDING');
            if ($newStatus !== $template->status) {
                $template->update([
                    'status' => $newStatus,
                    'rejection_reason' => $mt['rejected_reason'] ?? null,
                    'last_synced_at' => now(),
                ]);
                $updated++;
            }
        }

        return $updated;
    }

    public function uploadHeaderMedia(UploadedFile $file, Channel $channel): array
    {
        $orgId = $channel->organization_id;
        $ext = $file->getClientOriginalExtension() ?: 'bin';
        $s3Path = "chatme/{$orgId}/templates/" . uniqid() . ".{$ext}";

        $disk = \App\Models\MessageAttachment::mediaDisk();
        Storage::disk($disk)->put($s3Path, file_get_contents($file), 'private');

        // Upload to Meta via resumable upload
        $handle = null;
        $sessionId = $this->whatsAppService->createUploadSession($channel, $file->getSize(), $file->getMimeType());

        if ($sessionId) {
            $handle = $this->whatsAppService->uploadSessionChunk($channel, $sessionId, file_get_contents($file));
        }

        return [
            's3_path' => $s3Path,
            'handle' => $handle,
        ];
    }

    public function getApprovedForChannel(int $channelId): Collection
    {
        return WhatsAppTemplate::forChannel($channelId)->approved()->orderBy('name')->get();
    }

    public function buildSendPayload(WhatsAppTemplate $template, array $variables = [], ?string $mediaId = null): array
    {
        $components = [];

        // Header component
        if ($template->hasMediaHeader() && $mediaId) {
            $mediaType = $template->header_type === 'document' ? 'document' : $template->header_type;
            $components[] = [
                'type' => 'header',
                'parameters' => [
                    ['type' => $mediaType, $mediaType => ['id' => $mediaId]],
                ],
            ];
        }

        // Body component with variables
        if (!empty($variables)) {
            $params = [];
            foreach ($variables as $value) {
                $params[] = ['type' => 'text', 'text' => $value];
            }
            $components[] = [
                'type' => 'body',
                'parameters' => $params,
            ];
        }

        return $components;
    }

    private function buildMetaComponents(array $data): array
    {
        $components = [];

        // Header
        $headerType = $data['header_type'] ?? null;
        if ($headerType && $headerType !== 'none') {
            $header = ['type' => 'HEADER'];

            if ($headerType === 'text') {
                $header['format'] = 'TEXT';
                $header['text'] = $data['header_text'] ?? '';
            } else {
                $header['format'] = strtoupper($headerType);
                if (!empty($data['header_handle'])) {
                    $header['example'] = ['header_handle' => [$data['header_handle']]];
                }
            }

            $components[] = $header;
        }

        // Body
        $bodyComponent = [
            'type' => 'BODY',
            'text' => $data['body_text'],
        ];

        if (!empty($data['body_example_values'])) {
            $bodyComponent['example'] = [
                'body_text' => [$data['body_example_values']],
            ];
        }

        $components[] = $bodyComponent;

        // Footer
        if (!empty($data['footer_text'])) {
            $components[] = [
                'type' => 'FOOTER',
                'text' => $data['footer_text'],
            ];
        }

        // Buttons
        if (!empty($data['buttons'])) {
            $btnComponents = [];
            foreach ($data['buttons'] as $btn) {
                $type = strtoupper($btn['type'] ?? 'QUICK_REPLY');
                $button = ['type' => $type, 'text' => $btn['text'] ?? ''];

                if ($type === 'URL') {
                    $button['url'] = $btn['url'] ?? '';
                } elseif ($type === 'PHONE_NUMBER') {
                    $button['phone_number'] = $btn['phone_number'] ?? '';
                }

                $btnComponents[] = $button;
            }

            $components[] = [
                'type' => 'BUTTONS',
                'buttons' => $btnComponents,
            ];
        }

        return $components;
    }
}
