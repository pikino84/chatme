<?php

namespace Database\Factories;

use App\Models\Pipeline;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PipelineStage>
 */
class PipelineStageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'pipeline_id' => Pipeline::factory(),
            'name' => fake()->randomElement(['New', 'Contacted', 'Qualified', 'Proposal', 'Negotiation']),
            'position' => fake()->numberBetween(1, 10),
            'color' => fake()->hexColor(),
            'is_won' => false,
            'is_lost' => false,
            'max_duration_hours' => null,
        ];
    }

    public function won(): static
    {
        return $this->state([
            'name' => 'Won',
            'is_won' => true,
            'is_lost' => false,
        ]);
    }

    public function lost(): static
    {
        return $this->state([
            'name' => 'Lost',
            'is_won' => false,
            'is_lost' => true,
        ]);
    }

    public function withMaxDuration(int $hours): static
    {
        return $this->state(['max_duration_hours' => $hours]);
    }
}
