<?php

namespace Database\Factories;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\OrganizationUsageMonthly>
 */
class OrganizationUsageMonthlyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'feature_code' => 'max_conversations_monthly',
            'period' => now()->format('Y-m'),
            'usage' => 0,
        ];
    }
}
