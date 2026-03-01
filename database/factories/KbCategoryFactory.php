<?php

namespace Database\Factories;

use App\Models\KbCategory;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\KbCategory>
 */
class KbCategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->words(2, true),
            'description' => fake()->sentence(),
            'position' => fake()->numberBetween(0, 10),
            'parent_id' => null,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function withParent(KbCategory $parent): static
    {
        return $this->state([
            'parent_id' => $parent->id,
            'organization_id' => $parent->organization_id,
        ]);
    }
}
