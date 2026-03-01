<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Deal>
 */
class DealFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'pipeline_id' => Pipeline::factory(),
            'pipeline_stage_id' => PipelineStage::factory(),
            'conversation_id' => null,
            'assigned_user_id' => null,
            'contact_name' => fake()->name(),
            'contact_email' => fake()->safeEmail(),
            'contact_phone' => '+52' . fake()->numerify('##########'),
            'value' => fake()->randomFloat(2, 1000, 100000),
            'currency' => 'MXN',
            'stage_entered_at' => now(),
            'status' => 'open',
            'expected_close_date' => now()->addDays(30),
            'closed_at' => null,
        ];
    }

    public function assigned(User $user): static
    {
        return $this->state([
            'assigned_user_id' => $user->id,
            'organization_id' => $user->organization_id,
        ]);
    }

    public function won(): static
    {
        return $this->state([
            'status' => 'won',
            'closed_at' => now(),
        ]);
    }

    public function lost(): static
    {
        return $this->state([
            'status' => 'lost',
            'closed_at' => now(),
        ]);
    }

    public function highValue(): static
    {
        return $this->state([
            'value' => fake()->randomFloat(2, 500000, 5000000),
        ]);
    }

    public function stale(int $hoursAgo = 72): static
    {
        return $this->state([
            'stage_entered_at' => now()->subHours($hoursAgo),
        ]);
    }
}
