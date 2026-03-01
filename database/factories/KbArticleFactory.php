<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\KbArticle>
 */
class KbArticleFactory extends Factory
{
    public function definition(): array
    {
        $title = fake()->sentence(4);

        return [
            'organization_id' => Organization::factory(),
            'kb_category_id' => null,
            'created_by' => User::factory(),
            'updated_by' => null,
            'title' => $title,
            'slug' => Str::slug($title) . '-' . fake()->unique()->randomNumber(5),
            'content' => fake()->paragraphs(3, true),
            'status' => 'draft',
            'priority' => 0,
            'visible_on_webchat' => false,
            'visible_on_whatsapp' => false,
            'visible_on_instagram' => false,
            'visible_on_facebook' => false,
            'published_at' => null,
        ];
    }

    public function published(): static
    {
        return $this->state([
            'status' => 'published',
            'published_at' => now(),
        ]);
    }

    public function archived(): static
    {
        return $this->state(['status' => 'archived']);
    }

    public function visibleOnWebchat(): static
    {
        return $this->state(['visible_on_webchat' => true]);
    }

    public function visibleOnWhatsApp(): static
    {
        return $this->state(['visible_on_whatsapp' => true]);
    }
}
