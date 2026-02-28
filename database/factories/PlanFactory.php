<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Plan>
 */
class PlanFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Starter',
            'slug' => 'starter',
            'description' => 'Basic plan',
            'price_monthly' => 49900,
            'price_yearly' => 499900,
            'is_active' => true,
            'sort_order' => 1,
            'trial_days' => 14,
        ];
    }

    public function professional(): static
    {
        return $this->state([
            'name' => 'Professional',
            'slug' => 'professional',
            'price_monthly' => 99900,
            'price_yearly' => 999900,
            'sort_order' => 2,
        ]);
    }

    public function enterprise(): static
    {
        return $this->state([
            'name' => 'Enterprise',
            'slug' => 'enterprise',
            'price_monthly' => 249900,
            'price_yearly' => 2499900,
            'sort_order' => 3,
            'trial_days' => 0,
        ]);
    }

    public function free(): static
    {
        return $this->state([
            'name' => 'Free',
            'slug' => 'free',
            'price_monthly' => 0,
            'price_yearly' => 0,
            'trial_days' => 0,
        ]);
    }
}
