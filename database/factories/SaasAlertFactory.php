<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SaasAlert>
 */
class SaasAlertFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => null,
            'type' => 'info',
            'title' => fake()->sentence(4),
            'message' => fake()->paragraph(),
            'is_active' => true,
            'created_by' => User::factory(),
        ];
    }

    public function warning(): static
    {
        return $this->state(['type' => 'warning']);
    }

    public function critical(): static
    {
        return $this->state(['type' => 'critical']);
    }

    public function maintenance(): static
    {
        return $this->state(['type' => 'maintenance']);
    }

    public function resolved(): static
    {
        return $this->state([
            'resolved_at' => now(),
            'resolved_by' => User::factory(),
        ]);
    }
}
