<?php

namespace App\Models;

use App\Models\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversationSlaLog extends Model
{
    use HasFactory, BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'conversation_id',
        'metric',
        'target_seconds',
        'actual_seconds',
        'breached',
        'breached_at',
    ];

    protected function casts(): array
    {
        return [
            'target_seconds' => 'integer',
            'actual_seconds' => 'integer',
            'breached' => 'boolean',
            'breached_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function isBreached(): bool
    {
        return $this->breached;
    }
}
