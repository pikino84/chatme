<?php

namespace App\Models;

use App\Models\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PipelineStage extends Model
{
    use HasFactory, BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'pipeline_id',
        'name',
        'position',
        'color',
        'is_won',
        'is_lost',
        'max_duration_hours',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'is_won' => 'boolean',
            'is_lost' => 'boolean',
            'max_duration_hours' => 'integer',
        ];
    }

    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class);
    }

    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class, 'pipeline_stage_id');
    }

    public function isTerminal(): bool
    {
        return $this->is_won || $this->is_lost;
    }

    public function hasMaxDuration(): bool
    {
        return !is_null($this->max_duration_hours);
    }

    public function maxDurationInSeconds(): ?int
    {
        return $this->max_duration_hours ? $this->max_duration_hours * 3600 : null;
    }
}
