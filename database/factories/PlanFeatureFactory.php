<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PlanFeature>
 */
class PlanFeatureFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->slug(2),
            'description' => fake()->sentence(),
            'type' => 'limit',
        ];
    }

    public function boolean(): static
    {
        return $this->state(['type' => 'boolean']);
    }
}
