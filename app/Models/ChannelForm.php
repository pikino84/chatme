<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChannelForm extends Model
{
    use HasFactory;

    protected $fillable = [
        'channel_id',
        'template_key',
        'schema',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'schema' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    public function isFromTemplate(): bool
    {
        return !is_null($this->template_key);
    }

    public function getPublicSchema(): array
    {
        $schema = $this->schema;

        return [
            'fields' => $schema['fields'] ?? [],
            'template' => $this->template_key,
        ];
    }
}
