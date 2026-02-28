<?php

namespace App\Models;

use App\Models\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Channel extends Model
{
    /** @use HasFactory<\Database\Factories\ChannelFactory> */
    use HasFactory, BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'uuid',
        'type',
        'name',
        'configuration',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'configuration' => 'encrypted:array',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Channel $channel) {
            if (empty($channel->uuid)) {
                $channel->uuid = (string) Str::uuid();
            }
        });
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function form(): HasOne
    {
        return $this->hasOne(ChannelForm::class);
    }

    public function isWhatsApp(): bool
    {
        return $this->type === 'whatsapp';
    }

    public function getWhatsAppConfig(string $key, mixed $default = null): mixed
    {
        return $this->configuration[$key] ?? $default;
    }
}
