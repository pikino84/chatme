<?php

namespace Database\Factories;

use App\Models\Conversation;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ConversationSlaLog>
 */
class ConversationSlaLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'conversation_id' => Conversation::factory(),
            'metric' => 'first_response',
            'target_seconds' => 300,
            'actual_seconds' => null,
            'breached' => false,
            'breached_at' => null,
        ];
    }

    public function resolution(): static
    {
        return $this->state(['metric' => 'resolution', 'target_seconds' => 3600]);
    }

    public function breached(): static
    {
        return $this->state([
            'breached' => true,
            'breached_at' => now(),
            'actual_seconds' => 600,
        ]);
    }
}
