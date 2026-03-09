<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DripSequenceStep extends Model
{
    use HasFactory;

    protected $fillable = [
        'drip_sequence_id',
        'position',
        'delay_minutes',
        'message_template',
        'channel_type',
    ];

    public function sequence(): BelongsTo
    {
        return $this->belongsTo(DripSequence::class, 'drip_sequence_id');
    }
}
