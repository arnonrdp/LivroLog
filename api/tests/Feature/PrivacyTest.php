<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Follow;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PrivacyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test public profile shows books to everyone.
     */
    public function test_public_profile_books_visible_to_all(): void
    {
        $publicUser = User::factory()->create(['is_private' => false]);
        $viewer = User::factory()->create();
        $book = Book::factory()->create();

        $publicUser->books()->attach($book->id, [
            'added_at' => now(),
            'reading_status' => 'read',
            'is_private' => false,
        ]);

        Sanctum::actingAs($viewer);

        $response = $this->getJson("/users/{$publicUser->username}");

        $response->assertStatus(200);

        // Public user's books should be visible
        $responseData = $response->json();
        $this->assertArrayHasKey('books', $responseData);
        $this->assertNotEmpty($responseData['books']);
    }

    /**
     * Test private profile hides books from non-followers.
     */
    public function test_private_profile_hides_books_from_non_followers(): void
    {
        $privateUser = User::factory()->create(['is_private' => true]);
        $stranger = User::factory()->create();
        $book = Book::factory()->create();

        $privateUser->books()->attach($book->id, [
            'added_at' => now(),
            'reading_status' => 'read',
            'is_private' => false,
        ]);

        Sanctum::actingAs($stranger);

        $response = $this->getJson("/users/{$privateUser->username}");

        $response->assertStatus(200);

        // Private user's books should NOT be visible to stranger
        $responseData = $response->json();
        $this->assertFalse(isset($responseData['books']) && count($responseData['books']) > 0);
    }

    /**
     * Test private profile shows books to accepted followers.
     */
    public function test_private_profile_shows_books_to_followers(): void
    {
        $privateUser = User::factory()->create([
            'is_private' => true,
            'followers_count' => 1,
        ]);
        $follower = User::factory()->create(['following_count' => 1]);
        $book = Book::factory()->create();

        // Create accepted follow relationship
        Follow::create([
            'follower_id' => $follower->id,
            'followed_id' => $privateUser->id,
            'status' => 'accepted',
        ]);

        $privateUser->books()->attach($book->id, [
            'added_at' => now(),
            'reading_status' => 'read',
            'is_private' => false,
        ]);

        Sanctum::actingAs($follower);

        $response = $this->getJson("/users/{$privateUser->username}");

        $response->assertStatus(200);

        // Books should be visible to accepted follower
        $responseData = $response->json();
        $this->assertArrayHasKey('books', $responseData);
        $this->assertNotEmpty($responseData['books']);
    }

    /**
     * Test owner can always see their own books.
     */
    public function test_private_profile_shows_books_to_owner(): void
    {
        $user = User::factory()->create(['is_private' => true]);
        $book = Book::factory()->create();

        $user->books()->attach($book->id, [
            'added_at' => now(),
            'reading_status' => 'read',
            'is_private' => false,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/users/{$user->username}");

        $response->assertStatus(200);

        // Owner should always see their books
        $responseData = $response->json();
        $this->assertArrayHasKey('books', $responseData);
        $this->assertNotEmpty($responseData['books']);
    }

    /**
     * Test private book is only visible to owner.
     */
    public function test_private_book_only_visible_to_owner(): void
    {
        $user = User::factory()->create(['is_private' => false]);
        $viewer = User::factory()->create();
        $publicBook = Book::factory()->create();
        $privateBook = Book::factory()->create();

        // Add public book
        $user->books()->attach($publicBook->id, [
            'added_at' => now(),
            'reading_status' => 'read',
            'is_private' => false,
        ]);

        // Add private book
        $user->books()->attach($privateBook->id, [
            'added_at' => now(),
            'reading_status' => 'reading',
            'is_private' => true,
        ]);

        Sanctum::actingAs($viewer);

        $response = $this->getJson("/users/{$user->username}");

        $response->assertStatus(200);

        $books = $response->json('books') ?? [];
        $bookIds = array_column($books, 'id');

        // Viewer should only see the public book
        $this->assertContains($publicBook->id, $bookIds);
        $this->assertNotContains($privateBook->id, $bookIds);
    }

    /**
     * Test owner can see their private books.
     */
    public function test_owner_can_see_private_books(): void
    {
        $user = User::factory()->create();
        $privateBook = Book::factory()->create();

        $user->books()->attach($privateBook->id, [
            'added_at' => now(),
            'reading_status' => 'reading',
            'is_private' => true,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/users/{$user->username}");

        $response->assertStatus(200);

        $books = $response->json('books') ?? [];
        $bookIds = array_column($books, 'id');

        // Owner should see their private book
        $this->assertContains($privateBook->id, $bookIds);
    }

    /**
     * Test follow request required for private profile.
     */
    public function test_follow_request_required_for_private_profile(): void
    {
        $privateUser = User::factory()->create(['is_private' => true]);
        $requester = User::factory()->create();

        Sanctum::actingAs($requester);

        $response = $this->postJson("/users/{$privateUser->id}/follow");

        $response->assertStatus(200);

        // Should create a pending follow request
        $this->assertDatabaseHas('follows', [
            'follower_id' => $requester->id,
            'followed_id' => $privateUser->id,
            'status' => 'pending',
        ]);
    }

    /**
     * Test accept follow request grants access.
     */
    public function test_accept_follow_request_grants_access(): void
    {
        $privateUser = User::factory()->create(['is_private' => true]);
        $requester = User::factory()->create();
        $book = Book::factory()->create();

        // Create pending follow request
        $follow = Follow::create([
            'follower_id' => $requester->id,
            'followed_id' => $privateUser->id,
            'status' => 'pending',
        ]);

        $privateUser->books()->attach($book->id, [
            'added_at' => now(),
            'reading_status' => 'read',
            'is_private' => false,
        ]);

        // Accept the follow request
        Sanctum::actingAs($privateUser);
        $this->postJson("/follow-requests/{$follow->id}");

        // Now requester should see books
        Sanctum::actingAs($requester);
        $response = $this->getJson("/users/{$privateUser->username}");

        $response->assertStatus(200);

        $responseData = $response->json();
        $this->assertArrayHasKey('books', $responseData);
        $this->assertNotEmpty($responseData['books']);
    }

    /**
     * Test reject follow request denies access.
     */
    public function test_reject_follow_request_denies_access(): void
    {
        $privateUser = User::factory()->create(['is_private' => true]);
        $requester = User::factory()->create();
        $book = Book::factory()->create();

        // Create pending follow request
        $follow = Follow::create([
            'follower_id' => $requester->id,
            'followed_id' => $privateUser->id,
            'status' => 'pending',
        ]);

        $privateUser->books()->attach($book->id, [
            'added_at' => now(),
            'reading_status' => 'read',
            'is_private' => false,
        ]);

        // Reject the follow request
        Sanctum::actingAs($privateUser);
        $this->deleteJson("/follow-requests/{$follow->id}");

        // Requester should still not see books
        Sanctum::actingAs($requester);
        $response = $this->getJson("/users/{$privateUser->username}");

        $response->assertStatus(200);

        $responseData = $response->json();
        $this->assertFalse(isset($responseData['books']) && count($responseData['books']) > 0);
    }

    /**
     * Test pending follow request does not grant access.
     */
    public function test_pending_follow_request_does_not_grant_access(): void
    {
        $privateUser = User::factory()->create(['is_private' => true]);
        $requester = User::factory()->create();
        $book = Book::factory()->create();

        // Create pending follow request (not accepted)
        Follow::create([
            'follower_id' => $requester->id,
            'followed_id' => $privateUser->id,
            'status' => 'pending',
        ]);

        $privateUser->books()->attach($book->id, [
            'added_at' => now(),
            'reading_status' => 'read',
            'is_private' => false,
        ]);

        Sanctum::actingAs($requester);

        $response = $this->getJson("/users/{$privateUser->username}");

        $response->assertStatus(200);

        // Pending request should NOT grant access
        $responseData = $response->json();
        $this->assertFalse(isset($responseData['books']) && count($responseData['books']) > 0);
    }
}
