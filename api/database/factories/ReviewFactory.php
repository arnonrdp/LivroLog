<?php

namespace Database\Factories;

use App\Models\Book;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Review>
 */
class ReviewFactory extends Factory
{
    protected $model = Review::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => 'R-'.strtoupper(Str::random(4)).'-'.strtoupper(Str::random(4)),
            'user_id' => User::factory(),
            'book_id' => Book::factory(),
            'title' => fake()->sentence(),
            'content' => fake()->paragraphs(2, true),
            'rating' => fake()->numberBetween(1, 5),
            'visibility_level' => 'public',
            'is_spoiler' => false,
            'helpful_count' => 0,
        ];
    }

    /**
     * Indicate that the review is public.
     */
    public function public(): static
    {
        return $this->state(fn (array $attributes) => [
            'visibility_level' => 'public',
        ]);
    }

    /**
     * Indicate that the review is private.
     */
    public function private(): static
    {
        return $this->state(fn (array $attributes) => [
            'visibility_level' => 'private',
        ]);
    }

    /**
     * Indicate that the review is visible to friends only.
     */
    public function friends(): static
    {
        return $this->state(fn (array $attributes) => [
            'visibility_level' => 'friends',
        ]);
    }

    /**
     * Indicate that the review contains spoilers.
     */
    public function withSpoilers(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_spoiler' => true,
        ]);
    }

    /**
     * Set a specific rating.
     */
    public function rating(int $rating): static
    {
        return $this->state(fn (array $attributes) => [
            'rating' => max(1, min(5, $rating)),
        ]);
    }

    /**
     * Indicate that the review has helpful votes.
     */
    public function withHelpfulVotes(int $count = 5): static
    {
        return $this->state(fn (array $attributes) => [
            'helpful_count' => $count,
        ]);
    }
}
