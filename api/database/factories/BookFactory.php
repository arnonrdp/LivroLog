<?php

namespace Database\Factories;

use App\Models\Book;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Book>
 */
class BookFactory extends Factory
{
    protected $model = Book::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => 'B-'.strtoupper(Str::random(4)).'-'.strtoupper(Str::random(4)),
            'title' => fake()->sentence(3),
            'authors' => fake()->name(),
            'isbn' => fake()->isbn13(),
            'description' => fake()->paragraphs(3, true),
            'thumbnail' => fake()->imageUrl(128, 192, 'books'),
            'language' => fake()->randomElement(['en', 'pt', 'es', 'fr']),
            'publisher' => fake()->company(),
            'published_date' => fake()->date(),
            'page_count' => fake()->numberBetween(50, 800),
            'format' => fake()->randomElement(['hardcover', 'paperback', 'ebook', 'audiobook']),
            'info_quality' => 'basic',
        ];
    }

    /**
     * Indicate that the book has an Amazon ASIN.
     */
    public function withAsin(): static
    {
        return $this->state(fn (array $attributes) => [
            'amazon_asin' => 'B'.strtoupper(Str::random(9)),
            'asin_status' => 'completed',
            'asin_processed_at' => now(),
        ]);
    }

    /**
     * Indicate that the book has pending ASIN processing.
     */
    public function pendingAsin(): static
    {
        return $this->state(fn (array $attributes) => [
            'asin_status' => 'pending',
        ]);
    }

    /**
     * Indicate that the book is fully enriched.
     */
    public function enriched(): static
    {
        return $this->state(fn (array $attributes) => [
            'google_id' => Str::random(12),
            'subtitle' => fake()->sentence(2),
            'categories' => [fake()->word(), fake()->word()],
            'industry_identifiers' => [
                ['type' => 'ISBN_13', 'identifier' => fake()->isbn13()],
                ['type' => 'ISBN_10', 'identifier' => fake()->isbn10()],
            ],
            'info_quality' => 'complete',
            'enriched_at' => now(),
        ]);
    }

    /**
     * Indicate that the book is in Portuguese.
     */
    public function portuguese(): static
    {
        return $this->state(fn (array $attributes) => [
            'language' => 'pt',
        ]);
    }
}
