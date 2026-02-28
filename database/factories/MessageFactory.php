<?php

namespace Database\Factories;

use App\Models\Conversation;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Message>
 */
class MessageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'conversation_id' => Conversation::factory(),
            'user_id' => null,
            'body' => fake()->paragraph(),
            'type' => 'text',
            'direction' => 'inbound',
            'external_id' => null,
            'metadata' => [],
        ];
    }

    public function outbound(): static
    {
        return $this->state(['direction' => 'outbound']);
    }

    public function internalNote(): static
    {
        return $this->state(['type' => 'internal_note', 'direction' => 'outbound']);
    }
}
