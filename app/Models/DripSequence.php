<?php

namespace App\Models;

use App\Models\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DripSequence extends Model
{
    use HasFactory, BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'name',
        'status',
        'trigger_event',
    ];

    public function steps(): HasMany
    {
        return $this->hasMany(DripSequenceStep::class)->orderBy('position');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(DripEnrollment::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
