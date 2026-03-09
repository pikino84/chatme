<?php

namespace Database\Factories;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DripSequence>
 */
class DripSequenceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->sentence(3),
            'status' => 'paused',
            'trigger_event' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(['status' => 'active']);
    }
}
