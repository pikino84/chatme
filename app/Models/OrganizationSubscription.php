<?php

namespace App\Models;

use App\Models\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationSubscription extends Model
{
    use HasFactory, BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'plan_id',
        'status',
        'billing_cycle',
        'starts_at',
        'ends_at',
        'trial_ends_at',
        'canceled_at',
        'grace_period_ends_at',
        'stripe_customer_id',
        'stripe_subscription_id',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'canceled_at' => 'datetime',
            'grace_period_ends_at' => 'datetime',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isTrialing(): bool
    {
        return $this->status === 'trialing' && $this->trial_ends_at?->isFuture();
    }

    public function isCanceled(): bool
    {
        return $this->status === 'canceled';
    }

    public function isInGracePeriod(): bool
    {
        return $this->isCanceled()
            && $this->grace_period_ends_at
            && $this->grace_period_ends_at->isFuture();
    }

    public function hasAccess(): bool
    {
        return $this->isActive() || $this->isTrialing() || $this->isInGracePeriod();
    }

    public function isReadOnly(): bool
    {
        if ($this->isActive() || $this->isTrialing() || $this->isInGracePeriod()) {
            return false;
        }

        // Past due or expired grace period
        return true;
    }

    public function isManual(): bool
    {
        return is_null($this->stripe_subscription_id);
    }
}
