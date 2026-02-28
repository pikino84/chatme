<?php

namespace Database\Factories;

use App\Models\Channel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ChannelForm>
 */
class ChannelFormFactory extends Factory
{
    public function definition(): array
    {
        return [
            'channel_id' => Channel::factory()->webchat(),
            'template_key' => 'contacto_basico',
            'schema' => config('form_templates.contacto_basico'),
            'is_active' => true,
        ];
    }

    public function muebleria(): static
    {
        return $this->state([
            'template_key' => 'muebleria',
            'schema' => config('form_templates.muebleria'),
        ]);
    }

    public function agenciaViajes(): static
    {
        return $this->state([
            'template_key' => 'agencia_viajes',
            'schema' => config('form_templates.agencia_viajes'),
        ]);
    }

    public function customSchema(array $schema): static
    {
        return $this->state([
            'template_key' => null,
            'schema' => $schema,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
