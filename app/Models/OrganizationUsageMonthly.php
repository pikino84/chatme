<?php

namespace App\Models;

use App\Models\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrganizationUsageMonthly extends Model
{
    use HasFactory, BelongsToOrganization;

    protected $table = 'organization_usage_monthly';

    protected $fillable = [
        'organization_id',
        'feature_code',
        'period',
        'usage',
    ];

    protected function casts(): array
    {
        return [
            'usage' => 'integer',
        ];
    }
}
