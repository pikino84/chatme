<?php

namespace Database\Factories;

use App\Models\Conversation;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ConversationTransfer>
 */
class ConversationTransferFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'conversation_id' => Conversation::factory(),
            'from_user_id' => User::factory(),
            'to_user_id' => User::factory(),
            'transferred_by' => User::factory(),
            'reason' => fake()->sentence(),
            'transferred_at' => now(),
        ];
    }

    public function withoutReason(): static
    {
        return $this->state(['reason' => null]);
    }
}
