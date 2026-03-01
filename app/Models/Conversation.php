<?php

namespace App\Models;

use App\Models\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    /** @use HasFactory<\Database\Factories\ConversationFactory> */
    use HasFactory, BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'channel_id',
        'assigned_user_id',
        'branch_id',
        'status',
        'subject',
        'contact_name',
        'contact_identifier',
        'priority',
        'metadata',
        'closed_at',
        'last_message_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'closed_at' => 'datetime',
            'last_message_at' => 'datetime',
        ];
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ConversationAssignment::class);
    }

    public function transfers(): HasMany
    {
        return $this->hasMany(ConversationTransfer::class);
    }

    public function slaLogs(): HasMany
    {
        return $this->hasMany(ConversationSlaLog::class);
    }

    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class);
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    public function isAssignedTo(User $user): bool
    {
        return $this->assigned_user_id === $user->id;
    }
}
