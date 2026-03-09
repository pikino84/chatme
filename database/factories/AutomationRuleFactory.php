<?php

namespace Database\Factories;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AutomationRule>
 */
class AutomationRuleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'type' => 'auto_assign',
            'name' => fake()->sentence(3),
            'config' => [],
            'is_active' => true,
            'channel_id' => null,
        ];
    }

    public function autoAssign(string $method = 'round_robin'): static
    {
        return $this->state([
            'type' => 'auto_assign',
            'config' => ['method' => $method],
        ]);
    }

    public function autoResponse(string $message = 'Gracias por contactarnos. Un agente le atenderá pronto.'): static
    {
        return $this->state([
            'type' => 'auto_response',
            'config' => ['message' => $message],
        ]);
    }

    public function staleAlert(int $thresholdMinutes = 30): static
    {
        return $this->state([
            'type' => 'stale_alert',
            'config' => ['threshold_minutes' => $thresholdMinutes],
        ]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
