<?php

namespace Database\Factories;

use App\Models\Channel;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Conversation>
 */
class ConversationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'channel_id' => Channel::factory(),
            'assigned_user_id' => null,
            'branch_id' => null,
            'status' => 'open',
            'subject' => fake()->sentence(4),
            'contact_name' => fake()->name(),
            'contact_identifier' => '+52' . fake()->numerify('##########'),
            'priority' => 'normal',
        ];
    }

    public function assigned(User $user): static
    {
        return $this->state([
            'assigned_user_id' => $user->id,
            'organization_id' => $user->organization_id,
        ]);
    }

    public function closed(): static
    {
        return $this->state([
            'status' => 'closed',
            'closed_at' => now(),
        ]);
    }

    public function urgent(): static
    {
        return $this->state(['priority' => 'urgent']);
    }
}
