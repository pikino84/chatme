<?php

namespace Database\Factories;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Contact>
 */
class ContactFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '+52' . fake()->unique()->numerify('##########'),
            'company' => fake()->optional()->company(),
        ];
    }

    public function withExternalId(string $channelType = 'whatsapp'): static
    {
        return $this->state([
            'external_id' => fake()->numerify('##############'),
            'channel_type' => $channelType,
        ]);
    }

    public function withNotes(): static
    {
        return $this->state([
            'notes' => fake()->paragraph(),
        ]);
    }
}
