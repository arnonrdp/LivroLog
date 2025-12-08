<?php

namespace Database\Factories;

use App\Models\Book;
use App\Models\User;
use App\Models\UserBook;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UserBook>
 */
class UserBookFactory extends Factory
{
    protected $model = UserBook::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'book_id' => Book::factory(),
            'added_at' => now(),
            'read_at' => null,
            'is_private' => false,
            'reading_status' => 'want_to_read',
        ];
    }

    /**
     * Indicate that the book has been read.
     */
    public function read(): static
    {
        return $this->state(fn (array $attributes) => [
            'reading_status' => 'read',
            'read_at' => fake()->dateTimeBetween('-1 year', 'now'),
        ]);
    }

    /**
     * Indicate that the book is currently being read.
     */
    public function reading(): static
    {
        return $this->state(fn (array $attributes) => [
            'reading_status' => 'reading',
        ]);
    }

    /**
     * Indicate that the book is private.
     */
    public function private(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_private' => true,
        ]);
    }

    /**
     * Indicate that the user wants to read this book.
     */
    public function wantToRead(): static
    {
        return $this->state(fn (array $attributes) => [
            'reading_status' => 'want_to_read',
        ]);
    }

    /**
     * Indicate that the book was abandoned.
     */
    public function abandoned(): static
    {
        return $this->state(fn (array $attributes) => [
            'reading_status' => 'abandoned',
        ]);
    }

    /**
     * Indicate that the book is on hold.
     */
    public function onHold(): static
    {
        return $this->state(fn (array $attributes) => [
            'reading_status' => 'on_hold',
        ]);
    }

    /**
     * Indicate that the user is re-reading the book.
     */
    public function reReading(): static
    {
        return $this->state(fn (array $attributes) => [
            'reading_status' => 're_reading',
        ]);
    }
}
