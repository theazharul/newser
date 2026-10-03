<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Author;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Author>
 */
class AuthorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => null,
            'name' => fake()->unique()->name(),
            'bio' => fake()->paragraph(),
            'avatar_id' => null,
            'designation' => fake()->jobTitle(),
            'social_links' => [
                'x' => 'https://x.com/' . fake()->userName(),
            ],
        ];
    }
}
