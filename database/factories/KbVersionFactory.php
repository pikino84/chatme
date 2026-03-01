<?php

namespace Database\Factories;

use App\Models\KbArticle;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\KbVersion>
 */
class KbVersionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'kb_article_id' => KbArticle::factory(),
            'version_number' => 1,
            'title' => fake()->sentence(4),
            'content' => fake()->paragraphs(3, true),
            'changed_by' => User::factory(),
            'change_summary' => fake()->sentence(),
        ];
    }
}
