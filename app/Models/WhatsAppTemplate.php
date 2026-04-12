<?php

namespace App\Models;

use App\Models\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppTemplate extends Model
{
    use HasFactory, BelongsToOrganization;

    protected $table = 'whatsapp_templates';

    protected $fillable = [
        'organization_id',
        'channel_id',
        'name',
        'language',
        'category',
        'status',
        'components',
        'header_type',
        'header_media_path',
        'body_text',
        'body_example_values',
        'footer_text',
        'buttons',
        'meta_template_id',
        'rejection_reason',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'components' => 'array',
            'body_example_values' => 'array',
            'buttons' => 'array',
            'last_synced_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    public function isApproved(): bool
    {
        return $this->status === 'APPROVED';
    }

    public function isPending(): bool
    {
        return $this->status === 'PENDING';
    }

    public function isRejected(): bool
    {
        return $this->status === 'REJECTED';
    }

    public function hasMediaHeader(): bool
    {
        return in_array($this->header_type, ['image', 'video', 'document']);
    }

    public function getVariableCount(): int
    {
        preg_match_all('/\{\{(\d+)\}\}/', $this->body_text, $matches);
        return count(array_unique($matches[1] ?? []));
    }

    public function renderPreview(array $values = []): string
    {
        $text = $this->body_text;
        $examples = $values ?: ($this->body_example_values ?? []);

        foreach ($examples as $i => $value) {
            $text = str_replace('{{' . ($i + 1) . '}}', $value, $text);
        }

        return $text;
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'APPROVED');
    }

    public function scopeForChannel($query, int $channelId)
    {
        return $query->where('channel_id', $channelId);
    }
}
