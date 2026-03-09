<?php

namespace App\Models;

use App\Models\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DripEnrollment extends Model
{
    use HasFactory, BelongsToOrganization;

    protected $fillable = [
        'drip_sequence_id',
        'contact_id',
        'organization_id',
        'current_step',
        'status',
        'next_step_at',
    ];

    protected function casts(): array
    {
        return [
            'next_step_at' => 'datetime',
        ];
    }

    public function sequence(): BelongsTo
    {
        return $this->belongsTo(DripSequence::class, 'drip_sequence_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
