<?php

namespace Database\Factories;

use App\Models\Deal;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DealAttachment>
 */
class DealAttachmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'deal_id' => Deal::factory(),
            'user_id' => User::factory(),
            'file_name' => fake()->word() . '.pdf',
            'file_path' => 'deal-attachments/1/1/' . fake()->uuid() . '.pdf',
            'file_size' => fake()->numberBetween(1024, 10485760),
            'mime_type' => 'application/pdf',
        ];
    }
}
