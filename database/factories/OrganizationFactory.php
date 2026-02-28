<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Organization>
 */
class OrganizationFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'status' => 'active',
            'settings' => [],
        ];
    }

    public function suspended(): static
    {
        return $this->state(['status' => 'suspended']);
    }

    public function trial(): static
    {
        return $this->state(['status' => 'trial']);
    }
}
