<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserBookTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test user can get their library.
     */
    public function test_user_can_get_library(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        // Add book to user's library
        $user->books()->attach($book->id, [
            'added_at' => now(),
            'reading_status' => 'want_to_read',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/user/books');

        $response->assertStatus(200);

        // Response should contain at least one book
        $this->assertNotEmpty($response->json());
    }

    /**
     * Test user can add book to library.
     */
    public function test_user_can_add_book_to_library(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/user/books', [
            'book_id' => $book->id,
            'reading_status' => 'want_to_read',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('users_books', [
            'user_id' => $user->id,
            'book_id' => $book->id,
            'reading_status' => 'want_to_read',
        ]);
    }

    /**
     * Test adding duplicate book returns already_in_library message.
     */
    public function test_adding_duplicate_book_returns_already_in_library(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        // Add book to library first
        $user->books()->attach($book->id, [
            'added_at' => now(),
            'reading_status' => 'want_to_read',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/user/books', [
            'book_id' => $book->id,
            'reading_status' => 'reading',
        ]);

        // Implementation returns 200 with already_in_library flag
        $response->assertStatus(200)
            ->assertJson([
                'already_in_library' => true,
            ]);
    }

    /**
     * Test user can remove book from library.
     */
    public function test_user_can_remove_book_from_library(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        // Add book to library
        $user->books()->attach($book->id, [
            'added_at' => now(),
            'reading_status' => 'read',
        ]);

        Sanctum::actingAs($user);

        $response = $this->deleteJson("/user/books/{$book->id}");

        $response->assertStatus(200);

        $this->assertDatabaseMissing('users_books', [
            'user_id' => $user->id,
            'book_id' => $book->id,
        ]);
    }

    /**
     * Test removing non-existent book returns error.
     */
    public function test_removing_nonexistent_book_returns_error(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->deleteJson('/user/books/B-FAKE-BOOK');

        $response->assertStatus(404);
    }

    /**
     * Test user can get specific book from library.
     */
    public function test_user_can_get_specific_book_from_library(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $user->books()->attach($book->id, [
            'added_at' => now(),
            'reading_status' => 'reading',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/user/books/{$book->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'id',
                'title',
            ]);
    }

    /**
     * Test unauthenticated user cannot access library.
     */
    public function test_unauthenticated_user_cannot_access_library(): void
    {
        $response = $this->getJson('/user/books');

        $response->assertStatus(401);
    }

    /**
     * Test user library is empty by default.
     */
    public function test_user_library_is_empty_by_default(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->getJson('/user/books');

        $response->assertStatus(200);

        // Empty library should return empty array
        $this->assertEmpty($response->json('data') ?? $response->json());
    }

    /**
     * Test user can add book with reading status.
     */
    public function test_user_can_add_book_with_reading_status(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/user/books', [
            'book_id' => $book->id,
            'reading_status' => 'reading',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('users_books', [
            'user_id' => $user->id,
            'book_id' => $book->id,
            'reading_status' => 'reading',
        ]);
    }

    /**
     * Test user can add private book.
     */
    public function test_user_can_add_private_book(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/user/books', [
            'book_id' => $book->id,
            'reading_status' => 'read',
            'is_private' => true,
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('users_books', [
            'user_id' => $user->id,
            'book_id' => $book->id,
            'is_private' => true,
        ]);
    }
}
