<?php

namespace App\Models;

use App\Models\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Deal extends Model
{
    use HasFactory, BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'pipeline_id',
        'pipeline_stage_id',
        'conversation_id',
        'assigned_user_id',
        'contact_name',
        'contact_email',
        'contact_phone',
        'value',
        'currency',
        'stage_entered_at',
        'status',
        'expected_close_date',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'stage_entered_at' => 'datetime',
            'expected_close_date' => 'date',
            'closed_at' => 'datetime',
        ];
    }

    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class);
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(PipelineStage::class, 'pipeline_stage_id');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function stageHistory(): HasMany
    {
        return $this->hasMany(DealStageHistory::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(DealNote::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(DealAttachment::class);
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(DealCommission::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class)->withTimestamps();
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function isWon(): bool
    {
        return $this->status === 'won';
    }

    public function isLost(): bool
    {
        return $this->status === 'lost';
    }

    public function isClosed(): bool
    {
        return $this->isWon() || $this->isLost();
    }

    public function isAssignedTo(User $user): bool
    {
        return $this->assigned_user_id === $user->id;
    }

    public function timeInCurrentStage(): ?int
    {
        if (!$this->stage_entered_at) {
            return null;
        }

        return (int) $this->stage_entered_at->diffInSeconds(now(), false);
    }
}
