<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanFeatureValue extends Model
{
    use HasFactory;

    protected $fillable = [
        'plan_id',
        'plan_feature_id',
        'value',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function feature(): BelongsTo
    {
        return $this->belongsTo(PlanFeature::class, 'plan_feature_id');
    }

    public function isUnlimited(): bool
    {
        return $this->value === 'unlimited';
    }
}
