<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tests for GET /books/{id}?with[]=pivot endpoint.
 *
 * This endpoint is PUBLIC but should return pivot data for authenticated users.
 * The bug was that $request->user() returns null on public routes even with valid token,
 * so we need to use auth('sanctum')->user() instead.
 */
class BookPivotTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test public book endpoint returns pivot data for authenticated user.
     *
     * This is the critical test that would have caught the bug where
     * GET /books/{id}?with[]=pivot returned pivot: null for authenticated users.
     */
    public function test_public_book_endpoint_returns_pivot_for_authenticated_user(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        // Add book to user's library with specific data
        $readAt = '2024-06-15';
        $user->books()->attach($book->id, [
            'added_at' => now(),
            'read_at' => $readAt,
            'reading_status' => 'read',
            'is_private' => false,
        ]);

        // Authenticate the user
        Sanctum::actingAs($user);

        // Call the PUBLIC endpoint with pivot parameter
        $response = $this->getJson("/books/{$book->id}?with[]=pivot");

        $response->assertStatus(200);

        // Assert pivot data is returned
        $response->assertJsonStructure([
            'id',
            'title',
            'pivot' => [
                'added_at',
                'read_at',
                'is_private',
                'reading_status',
            ],
        ]);

        // Assert pivot contains correct read_at value
        $response->assertJsonPath('pivot.read_at', $readAt);
        $response->assertJsonPath('pivot.reading_status', 'read');
    }

    /**
     * Test pivot data persists after update (full cycle).
     *
     * This tests the complete flow: update via PATCH, then read via GET.
     */
    public function test_pivot_data_persists_after_update(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        // Add book to library without read_at
        $user->books()->attach($book->id, [
            'added_at' => now(),
            'reading_status' => 'reading',
        ]);

        Sanctum::actingAs($user);

        // Update read_at via PATCH
        $readAt = '2024-06-15';
        $patchResponse = $this->patchJson("/user/books/{$book->id}", [
            'read_at' => $readAt,
        ]);

        $patchResponse->assertStatus(200);

        // Now read it back via GET /books/{id}?with[]=pivot
        $getResponse = $this->getJson("/books/{$book->id}?with[]=pivot");

        $getResponse->assertStatus(200);
        $getResponse->assertJsonPath('pivot.read_at', $readAt);
    }

    /**
     * Test public book endpoint returns null pivot for unauthenticated user.
     */
    public function test_public_book_endpoint_returns_null_pivot_for_unauthenticated_user(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        // Add book to user's library
        $user->books()->attach($book->id, [
            'added_at' => now(),
            'read_at' => '2024-06-15',
            'reading_status' => 'read',
        ]);

        // Call WITHOUT authentication
        $response = $this->getJson("/books/{$book->id}?with[]=pivot");

        $response->assertStatus(200);

        // Pivot should be null for unauthenticated users
        $response->assertJsonPath('pivot', null);
    }

    /**
     * Test public book endpoint returns null pivot when book not in user's library.
     */
    public function test_public_book_endpoint_returns_null_pivot_when_book_not_in_library(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        // Book is NOT in user's library

        Sanctum::actingAs($user);

        $response = $this->getJson("/books/{$book->id}?with[]=pivot");

        $response->assertStatus(200);

        // Pivot should be null since book is not in user's library
        $response->assertJsonPath('pivot', null);
    }
}
