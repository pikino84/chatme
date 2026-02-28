<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlanFeature extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'description',
        'type',
    ];

    public function values(): HasMany
    {
        return $this->hasMany(PlanFeatureValue::class);
    }

    public function isLimit(): bool
    {
        return $this->type === 'limit';
    }

    public function isBoolean(): bool
    {
        return $this->type === 'boolean';
    }
}
