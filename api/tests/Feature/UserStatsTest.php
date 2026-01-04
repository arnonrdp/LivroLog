<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Follow;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserStatsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test user can view their own stats.
     */
    public function test_user_can_view_own_stats(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->getJson("/users/{$user->username}/stats");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'by_status' => [
                    'want_to_read',
                    'reading',
                    'read',
                    'abandoned',
                    'on_hold',
                    're_reading',
                ],
                'by_month',
                'by_category',
            ]);
    }

    /**
     * Test user with private profile can view their own stats.
     */
    public function test_user_with_private_profile_can_view_own_stats(): void
    {
        $user = User::factory()->create(['is_private' => true]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/users/{$user->username}/stats");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'by_status',
                'by_month',
                'by_category',
            ]);
    }

    /**
     * Test user can view stats of public profile.
     */
    public function test_user_can_view_public_profile_stats(): void
    {
        $publicUser = User::factory()->create(['is_private' => false]);
        $viewer = User::factory()->create();

        Sanctum::actingAs($viewer);

        $response = $this->getJson("/users/{$publicUser->username}/stats");

        $response->assertStatus(200);
    }

    /**
     * Test unauthenticated user can view stats of public profile.
     */
    public function test_unauthenticated_user_can_view_public_profile_stats(): void
    {
        $publicUser = User::factory()->create(['is_private' => false]);

        $response = $this->getJson("/users/{$publicUser->username}/stats");

        $response->assertStatus(200);
    }

    /**
     * Test user cannot view stats of private profile without following.
     */
    public function test_user_cannot_view_private_profile_stats_without_following(): void
    {
        $privateUser = User::factory()->create(['is_private' => true]);
        $viewer = User::factory()->create();

        Sanctum::actingAs($viewer);

        $response = $this->getJson("/users/{$privateUser->username}/stats");

        $response->assertStatus(403);
    }

    /**
     * Test user can view stats of private profile when following.
     */
    public function test_user_can_view_private_profile_stats_when_following(): void
    {
        $privateUser = User::factory()->create(['is_private' => true]);
        $follower = User::factory()->create();

        // Create accepted follow relationship
        Follow::create([
            'follower_id' => $follower->id,
            'followed_id' => $privateUser->id,
            'status' => 'accepted',
        ]);

        Sanctum::actingAs($follower);

        $response = $this->getJson("/users/{$privateUser->username}/stats");

        $response->assertStatus(200);
    }

    /**
     * Test pending follow request does not grant access to private stats.
     */
    public function test_pending_follow_does_not_grant_access_to_private_stats(): void
    {
        $privateUser = User::factory()->create(['is_private' => true]);
        $requester = User::factory()->create();

        // Create pending follow relationship
        Follow::create([
            'follower_id' => $requester->id,
            'followed_id' => $privateUser->id,
            'status' => 'pending',
        ]);

        Sanctum::actingAs($requester);

        $response = $this->getJson("/users/{$privateUser->username}/stats");

        $response->assertStatus(403);
    }

    /**
     * Test stats correctly count books by status.
     */
    public function test_stats_correctly_count_by_status(): void
    {
        $user = User::factory()->create();

        // Add books with different statuses
        $statuses = ['want_to_read', 'reading', 'read', 'abandoned', 'on_hold', 're_reading'];
        $counts = [2, 1, 5, 1, 0, 1]; // Expected counts

        foreach ($statuses as $index => $status) {
            for ($i = 0; $i < $counts[$index]; $i++) {
                $book = Book::factory()->create();
                $user->books()->attach($book->id, [
                    'added_at' => now(),
                    'reading_status' => $status,
                ]);
            }
        }

        Sanctum::actingAs($user);

        $response = $this->getJson("/users/{$user->username}/stats");

        $response->assertStatus(200)
            ->assertJsonPath('by_status.want_to_read', 2)
            ->assertJsonPath('by_status.reading', 1)
            ->assertJsonPath('by_status.read', 5)
            ->assertJsonPath('by_status.abandoned', 1)
            ->assertJsonPath('by_status.on_hold', 0)
            ->assertJsonPath('by_status.re_reading', 1);
    }

    /**
     * Test stats correctly count books by month.
     */
    public function test_stats_correctly_count_by_month(): void
    {
        $user = User::factory()->create();

        // Add books with different read dates
        $dates = [
            '2024-01-15',
            '2024-01-20',
            '2024-02-10',
            '2024-03-05',
            '2024-03-15',
            '2024-03-25',
        ];

        foreach ($dates as $date) {
            $book = Book::factory()->create();
            $user->books()->attach($book->id, [
                'added_at' => now(),
                'read_at' => $date,
                'reading_status' => 'read',
            ]);
        }

        Sanctum::actingAs($user);

        $response = $this->getJson("/users/{$user->username}/stats");

        $response->assertStatus(200);

        $byMonth = $response->json('by_month');

        // Find counts for specific months
        $jan = collect($byMonth)->first(fn ($m) => $m['year'] === 2024 && $m['month'] === 1);
        $feb = collect($byMonth)->first(fn ($m) => $m['year'] === 2024 && $m['month'] === 2);
        $mar = collect($byMonth)->first(fn ($m) => $m['year'] === 2024 && $m['month'] === 3);

        $this->assertEquals(2, $jan['count']);
        $this->assertEquals(1, $feb['count']);
        $this->assertEquals(3, $mar['count']);
    }

    /**
     * Test stats include books with read_at date regardless of status.
     */
    public function test_stats_include_books_with_read_at_regardless_of_status(): void
    {
        $user = User::factory()->create();

        // Add a book with read_at and want_to_read status (not 'read')
        $book = Book::factory()->create();
        $user->books()->attach($book->id, [
            'added_at' => now(),
            'read_at' => '2024-05-15',
            'reading_status' => 'want_to_read', // Not 'read' but has read_at
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/users/{$user->username}/stats");

        $response->assertStatus(200);

        $byMonth = $response->json('by_month');
        $may = collect($byMonth)->first(fn ($m) => $m['year'] === 2024 && $m['month'] === 5);

        // Should still appear in by_month since read_at is set
        $this->assertNotNull($may);
        $this->assertEquals(1, $may['count']);
    }

    /**
     * Test stats correctly group books by category.
     */
    public function test_stats_correctly_count_by_category(): void
    {
        $user = User::factory()->create();

        // Add books with categories (model auto-converts array to JSON)
        $book1 = Book::factory()->create([
            'categories' => ['Fiction / Fantasy', 'Fiction / Adventure'],
        ]);
        $book2 = Book::factory()->create([
            'categories' => ['Fiction / Fantasy'],
        ]);
        $book3 = Book::factory()->create([
            'categories' => ['Non-Fiction / Biography'],
        ]);

        $user->books()->attach($book1->id, ['added_at' => now()]);
        $user->books()->attach($book2->id, ['added_at' => now()]);
        $user->books()->attach($book3->id, ['added_at' => now()]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/users/{$user->username}/stats");

        $response->assertStatus(200);

        $byCategory = $response->json('by_category');

        // Fiction should have 2 unique books
        $fiction = collect($byCategory)->first(fn ($c) => $c['main_category'] === 'Fiction');
        $nonFiction = collect($byCategory)->first(fn ($c) => $c['main_category'] === 'Non-Fiction');

        $this->assertNotNull($fiction);
        $this->assertEquals(2, $fiction['total']);

        $this->assertNotNull($nonFiction);
        $this->assertEquals(1, $nonFiction['total']);
    }

    /**
     * Test stats count unique books per main category, not sum of subcategories.
     */
    public function test_stats_count_unique_books_per_main_category(): void
    {
        $user = User::factory()->create();

        // Add a book with multiple subcategories in same main category
        $book = Book::factory()->create([
            'categories' => [
                'Fiction / Fantasy',
                'Fiction / Adventure',
                'Fiction / Young Adult',
            ],
        ]);

        $user->books()->attach($book->id, ['added_at' => now()]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/users/{$user->username}/stats");

        $response->assertStatus(200);

        $byCategory = $response->json('by_category');
        $fiction = collect($byCategory)->first(fn ($c) => $c['main_category'] === 'Fiction');

        // Should count as 1 book, not 3
        $this->assertEquals(1, $fiction['total']);

        // But should have 3 subcategories
        $this->assertCount(3, $fiction['subcategories']);
    }

    /**
     * Test private books are only included in owner's stats.
     */
    public function test_private_books_only_in_owner_stats(): void
    {
        $user = User::factory()->create(['is_private' => false]);
        $viewer = User::factory()->create();

        // Add a private book
        $privateBook = Book::factory()->create();
        $user->books()->attach($privateBook->id, [
            'added_at' => now(),
            'is_private' => true,
            'reading_status' => 'read',
        ]);

        // Add a public book
        $publicBook = Book::factory()->create();
        $user->books()->attach($publicBook->id, [
            'added_at' => now(),
            'is_private' => false,
            'reading_status' => 'read',
        ]);

        // Owner should see both books
        Sanctum::actingAs($user);
        $ownerResponse = $this->getJson("/users/{$user->username}/stats");
        $ownerResponse->assertStatus(200)
            ->assertJsonPath('by_status.read', 2);

        // Viewer should only see public book
        Sanctum::actingAs($viewer);
        $viewerResponse = $this->getJson("/users/{$user->username}/stats");
        $viewerResponse->assertStatus(200)
            ->assertJsonPath('by_status.read', 1);
    }

    /**
     * Test stats return empty data for user with no books.
     */
    public function test_stats_return_empty_for_user_without_books(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->getJson("/users/{$user->username}/stats");

        $response->assertStatus(200)
            ->assertJsonPath('by_status.want_to_read', 0)
            ->assertJsonPath('by_status.reading', 0)
            ->assertJsonPath('by_status.read', 0)
            ->assertJsonPath('by_status.abandoned', 0)
            ->assertJsonPath('by_status.on_hold', 0)
            ->assertJsonPath('by_status.re_reading', 0)
            ->assertJsonPath('by_month', [])
            ->assertJsonPath('by_category', []);
    }

    /**
     * Test stats endpoint returns 404 for non-existent user.
     */
    public function test_stats_return_404_for_nonexistent_user(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->getJson('/users/nonexistent_user_12345/stats');

        $response->assertStatus(404);
    }

    /**
     * Test categories are sorted by total count descending.
     */
    public function test_categories_sorted_by_count_descending(): void
    {
        $user = User::factory()->create();

        // Add books to create different category counts
        for ($i = 0; $i < 5; $i++) {
            $book = Book::factory()->create([
                'categories' => ['Popular Category / Sub'],
            ]);
            $user->books()->attach($book->id, ['added_at' => now()]);
        }

        for ($i = 0; $i < 2; $i++) {
            $book = Book::factory()->create([
                'categories' => ['Less Popular / Sub'],
            ]);
            $user->books()->attach($book->id, ['added_at' => now()]);
        }

        Sanctum::actingAs($user);

        $response = $this->getJson("/users/{$user->username}/stats");

        $response->assertStatus(200);

        $byCategory = $response->json('by_category');

        // First category should have more books than second
        $this->assertGreaterThan($byCategory[1]['total'], $byCategory[0]['total']);
    }

    /**
     * Test by_month is sorted chronologically.
     */
    public function test_by_month_sorted_chronologically(): void
    {
        $user = User::factory()->create();

        // Add books in non-chronological order
        $dates = ['2024-12-01', '2024-01-01', '2024-06-01'];

        foreach ($dates as $date) {
            $book = Book::factory()->create();
            $user->books()->attach($book->id, [
                'added_at' => now(),
                'read_at' => $date,
            ]);
        }

        Sanctum::actingAs($user);

        $response = $this->getJson("/users/{$user->username}/stats");

        $response->assertStatus(200);

        $byMonth = $response->json('by_month');

        // Should be sorted chronologically
        $this->assertEquals(1, $byMonth[0]['month']); // January first
        $this->assertEquals(6, $byMonth[1]['month']); // June second
        $this->assertEquals(12, $byMonth[2]['month']); // December third
    }

    /**
     * Test top 10 categories limit.
     */
    public function test_categories_limited_to_top_10(): void
    {
        $user = User::factory()->create();

        // Add books with 15 different categories
        for ($i = 1; $i <= 15; $i++) {
            $book = Book::factory()->create([
                'categories' => ["Category{$i} / Sub"],
            ]);
            $user->books()->attach($book->id, ['added_at' => now()]);
        }

        Sanctum::actingAs($user);

        $response = $this->getJson("/users/{$user->username}/stats");

        $response->assertStatus(200);

        $byCategory = $response->json('by_category');

        $this->assertLessThanOrEqual(10, count($byCategory));
    }
}
