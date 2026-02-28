<?php

namespace Database\Factories;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Channel>
 */
class ChannelFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'uuid' => (string) Str::uuid(),
            'type' => 'whatsapp',
            'name' => fake()->company() . ' WhatsApp',
            'configuration' => [],
            'is_active' => true,
        ];
    }

    public function whatsappConfigured(): static
    {
        return $this->state([
            'type' => 'whatsapp',
            'configuration' => [
                'phone_number_id' => (string) fake()->numerify('##########'),
                'waba_id' => (string) fake()->numerify('##########'),
                'access_token' => 'EAA' . Str::random(40),
                'verify_token' => Str::random(32),
                'app_secret' => Str::random(32),
                'display_phone' => fake()->e164PhoneNumber(),
            ],
        ]);
    }

    public function webchat(): static
    {
        return $this->state([
            'type' => 'webchat',
            'name' => fake()->company() . ' Webchat',
        ]);
    }

    public function email(): static
    {
        return $this->state([
            'type' => 'email',
            'name' => fake()->company() . ' Email',
        ]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
