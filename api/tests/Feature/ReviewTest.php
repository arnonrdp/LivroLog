<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReviewTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test user can create a review.
     */
    public function test_user_can_create_review(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        // Add book to user's library first
        $user->books()->attach($book->id, [
            'added_at' => now(),
            'reading_status' => 'read',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/reviews', [
            'book_id' => $book->id,
            'rating' => 4,
            'content' => 'Great book, highly recommend!',
            'visibility_level' => 'public',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('reviews', [
            'user_id' => $user->id,
            'book_id' => $book->id,
            'rating' => 4,
        ]);
    }

    /**
     * Test review requires authentication.
     */
    public function test_review_requires_authentication(): void
    {
        $book = Book::factory()->create();

        $response = $this->postJson('/reviews', [
            'book_id' => $book->id,
            'rating' => 4,
            'content' => 'Great book!',
        ]);

        $response->assertStatus(401);
    }

    /**
     * Test user cannot create duplicate review for same book.
     */
    public function test_user_cannot_create_duplicate_review(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        // Add book to library
        $user->books()->attach($book->id, [
            'added_at' => now(),
            'reading_status' => 'read',
        ]);

        // Create first review
        Review::factory()->create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'rating' => 4,
        ]);

        Sanctum::actingAs($user);

        // Try to create another review for the same book
        $response = $this->postJson('/reviews', [
            'book_id' => $book->id,
            'rating' => 5,
            'content' => 'Changed my mind, even better!',
            'visibility_level' => 'public',
        ]);

        $response->assertStatus(409);
    }

    /**
     * Test review requires rating.
     */
    public function test_review_requires_rating(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $user->books()->attach($book->id, [
            'added_at' => now(),
            'reading_status' => 'read',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/reviews', [
            'book_id' => $book->id,
            'content' => 'Great book!',
            'visibility_level' => 'public',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['rating']);
    }

    /**
     * Test rating must be between 1 and 5.
     */
    public function test_rating_must_be_between_1_and_5(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $user->books()->attach($book->id, [
            'added_at' => now(),
            'reading_status' => 'read',
        ]);

        Sanctum::actingAs($user);

        // Test rating below 1
        $response = $this->postJson('/reviews', [
            'book_id' => $book->id,
            'rating' => 0,
            'content' => 'Bad rating',
            'visibility_level' => 'public',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['rating']);

        // Test rating above 5
        $response = $this->postJson('/reviews', [
            'book_id' => $book->id,
            'rating' => 6,
            'content' => 'Invalid rating',
            'visibility_level' => 'public',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['rating']);
    }

    /**
     * Test user can update own review.
     */
    public function test_user_can_update_own_review(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $review = Review::factory()->create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'rating' => 3,
            'content' => 'Original review',
        ]);

        Sanctum::actingAs($user);

        $response = $this->putJson("/reviews/{$review->id}", [
            'rating' => 5,
            'content' => 'Updated review - even better now!',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('reviews', [
            'id' => $review->id,
            'rating' => 5,
            'content' => 'Updated review - even better now!',
        ]);
    }

    /**
     * Test user cannot update others review.
     */
    public function test_user_cannot_update_others_review(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $book = Book::factory()->create();

        $review = Review::factory()->create([
            'user_id' => $owner->id,
            'book_id' => $book->id,
            'rating' => 4,
        ]);

        Sanctum::actingAs($otherUser);

        $response = $this->putJson("/reviews/{$review->id}", [
            'rating' => 1,
            'content' => 'Trying to change someone elses review',
        ]);

        $response->assertStatus(403);
    }

    /**
     * Test user can delete own review.
     */
    public function test_user_can_delete_own_review(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $review = Review::factory()->create([
            'user_id' => $user->id,
            'book_id' => $book->id,
        ]);

        Sanctum::actingAs($user);

        $response = $this->deleteJson("/reviews/{$review->id}");

        // Delete returns 204 No Content
        $response->assertStatus(204);

        $this->assertDatabaseMissing('reviews', [
            'id' => $review->id,
        ]);
    }

    /**
     * Test user cannot delete others review.
     */
    public function test_user_cannot_delete_others_review(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $book = Book::factory()->create();

        $review = Review::factory()->create([
            'user_id' => $owner->id,
            'book_id' => $book->id,
        ]);

        Sanctum::actingAs($otherUser);

        $response = $this->deleteJson("/reviews/{$review->id}");

        $response->assertStatus(403);

        $this->assertDatabaseHas('reviews', [
            'id' => $review->id,
        ]);
    }

    /**
     * Test review visibility levels.
     */
    public function test_review_visibility_levels(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $user->books()->attach($book->id, [
            'added_at' => now(),
            'reading_status' => 'read',
        ]);

        Sanctum::actingAs($user);

        // Create public review
        $response = $this->postJson('/reviews', [
            'book_id' => $book->id,
            'rating' => 4,
            'content' => 'Public review',
            'visibility_level' => 'public',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('reviews', [
            'user_id' => $user->id,
            'book_id' => $book->id,
            'visibility_level' => 'public',
        ]);
    }

    /**
     * Test user can create review with title.
     */
    public function test_user_can_create_review_with_title(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $user->books()->attach($book->id, [
            'added_at' => now(),
            'reading_status' => 'read',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/reviews', [
            'book_id' => $book->id,
            'rating' => 5,
            'title' => 'A Masterpiece!',
            'content' => 'This book changed my life.',
            'visibility_level' => 'public',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('reviews', [
            'user_id' => $user->id,
            'book_id' => $book->id,
            'title' => 'A Masterpiece!',
        ]);
    }

    /**
     * Test user can mark review as containing spoilers.
     */
    public function test_user_can_mark_review_as_spoiler(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $user->books()->attach($book->id, [
            'added_at' => now(),
            'reading_status' => 'read',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/reviews', [
            'book_id' => $book->id,
            'rating' => 4,
            'content' => 'Spoiler alert: the ending is...',
            'is_spoiler' => true,
            'visibility_level' => 'public',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('reviews', [
            'user_id' => $user->id,
            'book_id' => $book->id,
            'is_spoiler' => true,
        ]);
    }
}
