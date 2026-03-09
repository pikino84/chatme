<?php

namespace Database\Factories;

use App\Models\DripSequence;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DripSequenceStep>
 */
class DripSequenceStepFactory extends Factory
{
    public function definition(): array
    {
        return [
            'drip_sequence_id' => DripSequence::factory(),
            'position' => 1,
            'delay_minutes' => fake()->randomElement([60, 1440, 4320]),
            'message_template' => fake()->paragraph(),
            'channel_type' => 'whatsapp',
        ];
    }
}
