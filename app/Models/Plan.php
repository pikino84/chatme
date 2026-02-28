<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'price_monthly',
        'price_yearly',
        'is_active',
        'sort_order',
        'trial_days',
    ];

    protected function casts(): array
    {
        return [
            'price_monthly' => 'integer',
            'price_yearly' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'trial_days' => 'integer',
        ];
    }

    public function featureValues(): HasMany
    {
        return $this->hasMany(PlanFeatureValue::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(OrganizationSubscription::class);
    }

    public function getFeatureValue(string $featureCode): ?string
    {
        $value = $this->featureValues()
            ->whereHas('feature', fn ($q) => $q->where('code', $featureCode))
            ->first();

        return $value?->value;
    }

    public function isFree(): bool
    {
        return $this->price_monthly === 0 && $this->price_yearly === 0;
    }
}
