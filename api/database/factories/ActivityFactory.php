<?php

namespace Database\Factories;

use App\Models\Book;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Activity>
 */
class ActivityFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => 'A-'.strtoupper(Str::random(4)).'-'.strtoupper(Str::random(4)),
            'user_id' => User::factory(),
            'type' => fake()->randomElement(['book_added', 'book_started', 'book_read', 'review_written']),
            'subject_type' => 'Book',
            'subject_id' => Book::factory(),
            'metadata' => null,
            'likes_count' => 0,
            'comments_count' => 0,
            'created_at' => now(),
        ];
    }

    /**
     * Indicate that the activity is a book_added type.
     */
    public function bookAdded(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'book_added',
        ]);
    }

    /**
     * Indicate that the activity is a user_followed type.
     */
    public function userFollowed(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'user_followed',
            'subject_type' => 'User',
            'subject_id' => User::factory(),
        ]);
    }
}
