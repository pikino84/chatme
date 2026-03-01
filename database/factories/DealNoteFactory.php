<?php

namespace Database\Factories;

use App\Models\Deal;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DealNote>
 */
class DealNoteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'deal_id' => Deal::factory(),
            'user_id' => User::factory(),
            'body' => fake()->paragraphs(2, true),
        ];
    }
}
