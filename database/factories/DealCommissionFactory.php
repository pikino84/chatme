<?php

namespace Database\Factories;

use App\Models\Deal;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DealCommission>
 */
class DealCommissionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'deal_id' => Deal::factory(),
            'user_id' => User::factory(),
            'percentage' => fake()->randomFloat(2, 1, 15),
            'amount' => fake()->randomFloat(2, 100, 50000),
            'status' => 'pending',
        ];
    }

    public function approved(): static
    {
        return $this->state(['status' => 'approved']);
    }

    public function paid(): static
    {
        return $this->state(['status' => 'paid']);
    }

    public function cancelled(): static
    {
        return $this->state(['status' => 'cancelled']);
    }
}
