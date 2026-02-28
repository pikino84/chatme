<?php

namespace Database\Factories;

use App\Models\Conversation;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ConversationAssignment>
 */
class ConversationAssignmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'conversation_id' => Conversation::factory(),
            'user_id' => User::factory(),
            'assigned_by' => null,
            'assigned_at' => now(),
            'unassigned_at' => null,
        ];
    }

    public function unassigned(): static
    {
        return $this->state(['unassigned_at' => now()]);
    }

    public function assignedBy(User $user): static
    {
        return $this->state(['assigned_by' => $user->id]);
    }
}
