<?php

namespace Database\Factories;

use App\Models\Channel;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Campaign>
 */
class CampaignFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'channel_id' => Channel::factory(),
            'created_by' => User::factory(),
            'name' => fake()->sentence(3),
            'type' => 'broadcast',
            'status' => 'draft',
            'message_template' => fake()->paragraph(),
        ];
    }

    public function scheduled(): static
    {
        return $this->state([
            'status' => 'scheduled',
            'scheduled_at' => now()->addHour(),
        ]);
    }

    public function running(): static
    {
        return $this->state(['status' => 'running']);
    }

    public function completed(): static
    {
        return $this->state([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }
}
