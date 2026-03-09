<?php

namespace Database\Factories;

use App\Models\Contact;
use App\Models\DripSequence;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DripEnrollment>
 */
class DripEnrollmentFactory extends Factory
{
    public function definition(): array
    {
        $org = Organization::factory();

        return [
            'drip_sequence_id' => DripSequence::factory(),
            'contact_id' => Contact::factory(),
            'organization_id' => $org,
            'current_step' => 1,
            'status' => 'active',
            'next_step_at' => now()->addHours(1),
        ];
    }

    public function completed(): static
    {
        return $this->state([
            'status' => 'completed',
            'next_step_at' => null,
        ]);
    }

    public function paused(): static
    {
        return $this->state(['status' => 'paused']);
    }
}
