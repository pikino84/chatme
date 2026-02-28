<?php

namespace Database\Factories;

use App\Models\Plan;
use App\Models\PlanFeature;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PlanFeatureValue>
 */
class PlanFeatureValueFactory extends Factory
{
    public function definition(): array
    {
        return [
            'plan_id' => Plan::factory(),
            'plan_feature_id' => PlanFeature::factory(),
            'value' => '100',
        ];
    }

    public function unlimited(): static
    {
        return $this->state(['value' => 'unlimited']);
    }
}
