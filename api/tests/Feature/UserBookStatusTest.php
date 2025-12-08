<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserBookStatusTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test user can update reading status.
     */
    public function test_user_can_update_reading_status(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $user->books()->attach($book->id, [
            'added_at' => now(),
            'reading_status' => 'want_to_read',
        ]);

        Sanctum::actingAs($user);

        $response = $this->patchJson("/user/books/{$book->id}", [
            'reading_status' => 'reading',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('users_books', [
            'user_id' => $user->id,
            'book_id' => $book->id,
            'reading_status' => 'reading',
        ]);
    }

    /**
     * Test all valid reading statuses are accepted.
     */
    public function test_valid_reading_statuses_are_accepted(): void
    {
        $user = User::factory()->create();
        $validStatuses = ['want_to_read', 'reading', 'read', 'abandoned', 'on_hold', 're_reading'];

        Sanctum::actingAs($user);

        foreach ($validStatuses as $status) {
            $book = Book::factory()->create();
            $user->books()->attach($book->id, [
                'added_at' => now(),
                'reading_status' => 'want_to_read',
            ]);

            $response = $this->patchJson("/user/books/{$book->id}", [
                'reading_status' => $status,
            ]);

            $response->assertStatus(200);

            $this->assertDatabaseHas('users_books', [
                'user_id' => $user->id,
                'book_id' => $book->id,
                'reading_status' => $status,
            ]);
        }
    }

    /**
     * Test invalid reading status returns error.
     */
    public function test_invalid_reading_status_returns_error(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $user->books()->attach($book->id, [
            'added_at' => now(),
            'reading_status' => 'want_to_read',
        ]);

        Sanctum::actingAs($user);

        $response = $this->patchJson("/user/books/{$book->id}", [
            'reading_status' => 'invalid_status',
        ]);

        $response->assertStatus(422);
    }

    /**
     * Test user can update read date.
     */
    public function test_user_can_update_read_date(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $user->books()->attach($book->id, [
            'added_at' => now(),
            'reading_status' => 'read',
        ]);

        Sanctum::actingAs($user);

        $readDate = '2024-01-15';

        $response = $this->patchJson("/user/books/{$book->id}", [
            'read_at' => $readDate,
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('users_books', [
            'user_id' => $user->id,
            'book_id' => $book->id,
            'read_at' => $readDate,
        ]);
    }

    /**
     * Test user can update book privacy.
     */
    public function test_user_can_update_book_privacy(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $user->books()->attach($book->id, [
            'added_at' => now(),
            'reading_status' => 'read',
            'is_private' => false,
        ]);

        Sanctum::actingAs($user);

        $response = $this->patchJson("/user/books/{$book->id}", [
            'is_private' => true,
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('users_books', [
            'user_id' => $user->id,
            'book_id' => $book->id,
            'is_private' => true,
        ]);
    }

    /**
     * Test user can update multiple fields at once.
     */
    public function test_user_can_update_multiple_fields(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $user->books()->attach($book->id, [
            'added_at' => now(),
            'reading_status' => 'want_to_read',
            'is_private' => false,
        ]);

        Sanctum::actingAs($user);

        $response = $this->patchJson("/user/books/{$book->id}", [
            'reading_status' => 'read',
            'read_at' => '2024-06-01',
            'is_private' => true,
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('users_books', [
            'user_id' => $user->id,
            'book_id' => $book->id,
            'reading_status' => 'read',
            'read_at' => '2024-06-01',
            'is_private' => true,
        ]);
    }

    /**
     * Test user cannot update book not in library.
     */
    public function test_user_cannot_update_book_not_in_library(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->patchJson("/user/books/{$book->id}", [
            'reading_status' => 'reading',
        ]);

        $response->assertStatus(404);
    }

    /**
     * Test marking book as read sets reading status.
     */
    public function test_marking_book_as_read(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $user->books()->attach($book->id, [
            'added_at' => now(),
            'reading_status' => 'reading',
        ]);

        Sanctum::actingAs($user);

        $response = $this->patchJson("/user/books/{$book->id}", [
            'reading_status' => 'read',
            'read_at' => now()->format('Y-m-d'),
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('users_books', [
            'user_id' => $user->id,
            'book_id' => $book->id,
            'reading_status' => 'read',
        ]);
    }

    /**
     * Test re-reading book.
     */
    public function test_re_reading_book(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $user->books()->attach($book->id, [
            'added_at' => now(),
            'reading_status' => 'read',
            'read_at' => '2023-01-01',
        ]);

        Sanctum::actingAs($user);

        $response = $this->patchJson("/user/books/{$book->id}", [
            'reading_status' => 're_reading',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('users_books', [
            'user_id' => $user->id,
            'book_id' => $book->id,
            'reading_status' => 're_reading',
        ]);
    }
}
