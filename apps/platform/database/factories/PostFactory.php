<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Author;
use App\Models\Category;
use App\Models\Post;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->sentence(6);

        return [
            'title' => $title,
            'slug' => Str::slug($title),
            'excerpt' => fake()->sentence(12),
            'body' => fake()->paragraphs(3, true),
            'status' => 'draft',
            'author_id' => Author::factory(),
            'category_id' => Category::factory(),
            'featured_image_id' => null,
            'published_at' => null,
            'seo_title' => null,
            'seo_description' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'published',
            'published_at' => now(),
        ]);
    }
}
