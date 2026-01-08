<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    private User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->create([
            'role' => 'admin',
        ]);

        $this->regularUser = User::factory()->create([
            'role' => 'user',
        ]);
    }

    // ==================== Access Control Tests ====================

    public function test_admin_can_access_admin_users_endpoint(): void
    {
        Sanctum::actingAs($this->adminUser);

        $response = $this->getJson('/admin/users');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'display_name',
                        'username',
                        'email',
                        'role',
                        'books_count',
                        'created_at',
                    ],
                ],
                'meta' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                ],
            ]);
    }

    public function test_regular_user_cannot_access_admin_users_endpoint(): void
    {
        Sanctum::actingAs($this->regularUser);

        $response = $this->getJson('/admin/users');

        $response->assertStatus(403);
    }

    public function test_unauthenticated_user_cannot_access_admin_users_endpoint(): void
    {
        $response = $this->getJson('/admin/users');

        $response->assertStatus(401);
    }

    public function test_admin_can_access_admin_books_endpoint(): void
    {
        Sanctum::actingAs($this->adminUser);

        $response = $this->getJson('/admin/books');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'title',
                        'authors',
                        'isbn',
                        'users_count',
                        'created_at',
                    ],
                ],
                'meta' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                ],
            ]);
    }

    public function test_regular_user_cannot_access_admin_books_endpoint(): void
    {
        Sanctum::actingAs($this->regularUser);

        $response = $this->getJson('/admin/books');

        $response->assertStatus(403);
    }

    public function test_unauthenticated_user_cannot_access_admin_books_endpoint(): void
    {
        $response = $this->getJson('/admin/books');

        $response->assertStatus(401);
    }

    // ==================== Users List Tests ====================

    public function test_admin_users_endpoint_returns_users_ordered_by_created_at_desc(): void
    {
        Sanctum::actingAs($this->adminUser);

        // Create users with different timestamps
        $olderUser = User::factory()->create([
            'created_at' => now()->subDays(5),
        ]);
        $newerUser = User::factory()->create([
            'created_at' => now()->subDays(1),
        ]);

        $response = $this->getJson('/admin/users');

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertCount(4, $data); // admin + regular + older + newer

        // Check that newer users appear first
        $createdDates = array_column($data, 'created_at');
        $sortedDates = $createdDates;
        usort($sortedDates, fn ($a, $b) => strtotime($b) - strtotime($a));
        $this->assertEquals($sortedDates, $createdDates);
    }

    public function test_admin_users_endpoint_supports_pagination(): void
    {
        Sanctum::actingAs($this->adminUser);

        // Create additional users
        User::factory()->count(25)->create();

        $response = $this->getJson('/admin/users?per_page=10&page=1');

        $response->assertStatus(200);
        // Default per_page is 20, so we check that pagination works
        $this->assertLessThanOrEqual(20, count($response->json('data')));
        $this->assertEquals(1, $response->json('meta.current_page'));
        $this->assertGreaterThan(1, $response->json('meta.last_page'));
    }

    public function test_admin_users_endpoint_supports_search_filter(): void
    {
        Sanctum::actingAs($this->adminUser);

        $searchableUser = User::factory()->create([
            'display_name' => 'UniqueSearchName123',
            'email' => 'unique123@example.com',
        ]);

        $response = $this->getJson('/admin/users?filter=UniqueSearchName123');

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals($searchableUser->id, $data[0]['id']);
    }

    public function test_admin_users_endpoint_search_works_with_email(): void
    {
        Sanctum::actingAs($this->adminUser);

        $searchableUser = User::factory()->create([
            'email' => 'veryrandomtestemail@example.com',
        ]);

        $response = $this->getJson('/admin/users?filter=veryrandomtestemail');

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals($searchableUser->email, $data[0]['email']);
    }

    public function test_admin_users_endpoint_search_works_with_username(): void
    {
        Sanctum::actingAs($this->adminUser);

        $searchableUser = User::factory()->create([
            'username' => 'veryrandomusername999',
        ]);

        $response = $this->getJson('/admin/users?filter=veryrandomusername999');

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals($searchableUser->username, $data[0]['username']);
    }

    public function test_admin_users_endpoint_supports_sorting_by_books_count(): void
    {
        Sanctum::actingAs($this->adminUser);

        // Add books to regularUser
        $books = Book::factory()->count(3)->create();
        foreach ($books as $book) {
            $this->regularUser->books()->attach($book->id, [
                'reading_status' => 'read',
                'added_at' => now(),
            ]);
        }

        $response = $this->getJson('/admin/users?sort_by=books_count&sort_desc=true');

        $response->assertStatus(200);

        $data = $response->json('data');
        // User with most books should be first
        $this->assertEquals($this->regularUser->id, $data[0]['id']);
        $this->assertEquals(3, $data[0]['books_count']);
    }

    public function test_admin_users_endpoint_includes_counts(): void
    {
        Sanctum::actingAs($this->adminUser);

        $response = $this->getJson('/admin/users');

        $response->assertStatus(200);

        $data = $response->json('data');
        foreach ($data as $user) {
            $this->assertArrayHasKey('books_count', $user);
            $this->assertArrayHasKey('followers_count', $user);
            $this->assertArrayHasKey('following_count', $user);
        }
    }

    public function test_admin_users_endpoint_supports_sorting_by_role_ascending(): void
    {
        Sanctum::actingAs($this->adminUser);

        $response = $this->getJson('/admin/users?sort_by=role&sort_desc=false');

        $response->assertStatus(200);

        $data = $response->json('data');
        $roles = array_column($data, 'role');
        $sortedRoles = $roles;
        sort($sortedRoles);
        $this->assertEquals($sortedRoles, $roles);
    }

    public function test_admin_users_endpoint_supports_sorting_by_role_descending(): void
    {
        Sanctum::actingAs($this->adminUser);

        $response = $this->getJson('/admin/users?sort_by=role&sort_desc=true');

        $response->assertStatus(200);

        $data = $response->json('data');
        $roles = array_column($data, 'role');
        $sortedRoles = $roles;
        rsort($sortedRoles);
        $this->assertEquals($sortedRoles, $roles);
    }

    public function test_admin_users_endpoint_supports_sorting_by_display_name(): void
    {
        Sanctum::actingAs($this->adminUser);

        $response = $this->getJson('/admin/users?sort_by=display_name&sort_desc=false');

        $response->assertStatus(200);

        $data = $response->json('data');
        $names = array_column($data, 'display_name');
        $sortedNames = $names;
        sort($sortedNames);
        $this->assertEquals($sortedNames, $names);
    }

    public function test_admin_users_endpoint_supports_sorting_by_last_activity(): void
    {
        Sanctum::actingAs($this->adminUser);

        // Create activities for users with distinct timestamps
        // Using new instance + save to preserve custom created_at
        $activity1 = new \App\Models\Activity;
        $activity1->user_id = $this->adminUser->id;
        $activity1->type = 'book_added';
        $activity1->subject_type = 'App\\Models\\Book';
        $activity1->subject_id = 'B-TEST-0001';
        $activity1->created_at = now()->subDays(5);
        $activity1->save();

        $activity2 = new \App\Models\Activity;
        $activity2->user_id = $this->regularUser->id;
        $activity2->type = 'book_read';
        $activity2->subject_type = 'App\\Models\\Book';
        $activity2->subject_id = 'B-TEST-0002';
        $activity2->created_at = now()->subDays(1);
        $activity2->save();

        $response = $this->getJson('/admin/users?sort_by=last_activity_at&sort_desc=true');

        $response->assertStatus(200);

        $data = $response->json('data');

        // Get only users with activities and check their order
        $usersWithActivities = array_filter($data, fn ($u) => $u['last_activity'] !== null);
        $usersWithActivities = array_values($usersWithActivities);

        $this->assertGreaterThanOrEqual(2, count($usersWithActivities));
        // Regular user has more recent activity (subDays(1)) so should appear before admin (subDays(5))
        $this->assertEquals($this->regularUser->id, $usersWithActivities[0]['id']);
        $this->assertEquals($this->adminUser->id, $usersWithActivities[1]['id']);
    }

    public function test_admin_users_endpoint_includes_last_activity(): void
    {
        Sanctum::actingAs($this->adminUser);

        $book = Book::factory()->create();

        $activity = new \App\Models\Activity;
        $activity->user_id = $this->regularUser->id;
        $activity->type = 'book_added';
        $activity->subject_type = 'App\\Models\\Book';
        $activity->subject_id = $book->id;
        $activity->save();

        $response = $this->getJson('/admin/users');

        $response->assertStatus(200);

        $data = $response->json('data');
        $userData = collect($data)->firstWhere('id', $this->regularUser->id);

        $this->assertNotNull($userData['last_activity']);
        $this->assertEquals('book_added', $userData['last_activity']['type']);
        $this->assertEquals($book->title, $userData['last_activity']['subject_name']);
    }

    // ==================== Books List Tests ====================

    public function test_admin_books_endpoint_returns_books_ordered_by_created_at_desc(): void
    {
        Sanctum::actingAs($this->adminUser);

        // Create books with different timestamps
        $olderBook = Book::factory()->create([
            'created_at' => now()->subDays(5),
        ]);
        $newerBook = Book::factory()->create([
            'created_at' => now()->subDays(1),
        ]);

        $response = $this->getJson('/admin/books');

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertCount(2, $data);

        // Check that newer books appear first
        $this->assertEquals($newerBook->id, $data[0]['id']);
        $this->assertEquals($olderBook->id, $data[1]['id']);
    }

    public function test_admin_books_endpoint_supports_pagination(): void
    {
        Sanctum::actingAs($this->adminUser);

        // Create books
        Book::factory()->count(25)->create();

        $response = $this->getJson('/admin/books?per_page=10&page=1');

        $response->assertStatus(200);
        // Default per_page is 20, so we check that pagination works
        $this->assertLessThanOrEqual(20, count($response->json('data')));
        $this->assertEquals(1, $response->json('meta.current_page'));
        $this->assertGreaterThan(1, $response->json('meta.last_page'));
    }

    public function test_admin_books_endpoint_supports_search_filter(): void
    {
        Sanctum::actingAs($this->adminUser);

        $searchableBook = Book::factory()->create([
            'title' => 'VeryUniqueBookTitle12345',
        ]);

        $response = $this->getJson('/admin/books?filter=VeryUniqueBookTitle12345');

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals($searchableBook->id, $data[0]['id']);
    }

    public function test_admin_books_endpoint_search_works_with_isbn(): void
    {
        Sanctum::actingAs($this->adminUser);

        $searchableBook = Book::factory()->create([
            'isbn' => '9781234567890',
        ]);

        $response = $this->getJson('/admin/books?filter=9781234567890');

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals($searchableBook->isbn, $data[0]['isbn']);
    }

    public function test_admin_books_endpoint_search_works_with_authors(): void
    {
        Sanctum::actingAs($this->adminUser);

        $searchableBook = Book::factory()->create([
            'authors' => 'VeryUniqueAuthorName999',
        ]);

        $response = $this->getJson('/admin/books?filter=VeryUniqueAuthorName999');

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals($searchableBook->authors, $data[0]['authors']);
    }

    public function test_admin_books_endpoint_search_works_with_amazon_asin(): void
    {
        Sanctum::actingAs($this->adminUser);

        $searchableBook = Book::factory()->create([
            'amazon_asin' => 'B08UNIQUEASIN',
        ]);

        $response = $this->getJson('/admin/books?filter=B08UNIQUEASIN');

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals($searchableBook->amazon_asin, $data[0]['amazon_asin']);
    }

    public function test_admin_books_endpoint_supports_sorting_by_users_count(): void
    {
        Sanctum::actingAs($this->adminUser);

        $popularBook = Book::factory()->create();
        $unpopularBook = Book::factory()->create();

        // Add users to popularBook
        $this->regularUser->books()->attach($popularBook->id, [
            'reading_status' => 'read',
            'added_at' => now(),
        ]);
        $this->adminUser->books()->attach($popularBook->id, [
            'reading_status' => 'reading',
            'added_at' => now(),
        ]);

        $response = $this->getJson('/admin/books?sort_by=users_count&sort_desc=true');

        $response->assertStatus(200);

        $data = $response->json('data');
        // Popular book should be first
        $this->assertEquals($popularBook->id, $data[0]['id']);
        $this->assertEquals(2, $data[0]['users_count']);
    }

    public function test_admin_books_endpoint_includes_users_count(): void
    {
        Sanctum::actingAs($this->adminUser);

        $book = Book::factory()->create();

        // Attach book to some users
        $this->regularUser->books()->attach($book->id, [
            'reading_status' => 'read',
            'added_at' => now(),
        ]);

        $response = $this->getJson('/admin/books');

        $response->assertStatus(200);

        $data = $response->json('data');
        $bookData = collect($data)->firstWhere('id', $book->id);
        $this->assertEquals(1, $bookData['users_count']);
    }

    public function test_admin_books_endpoint_supports_sorting_by_title_ascending(): void
    {
        Sanctum::actingAs($this->adminUser);

        Book::factory()->create(['title' => 'Zebra Book']);
        Book::factory()->create(['title' => 'Apple Book']);
        Book::factory()->create(['title' => 'Mango Book']);

        $response = $this->getJson('/admin/books?sort_by=title&sort_desc=false');

        $response->assertStatus(200);

        $data = $response->json('data');
        $titles = array_column($data, 'title');
        $sortedTitles = $titles;
        sort($sortedTitles);
        $this->assertEquals($sortedTitles, $titles);
    }

    public function test_admin_books_endpoint_supports_sorting_by_title_descending(): void
    {
        Sanctum::actingAs($this->adminUser);

        Book::factory()->create(['title' => 'Zebra Book']);
        Book::factory()->create(['title' => 'Apple Book']);
        Book::factory()->create(['title' => 'Mango Book']);

        $response = $this->getJson('/admin/books?sort_by=title&sort_desc=true');

        $response->assertStatus(200);

        $data = $response->json('data');
        $titles = array_column($data, 'title');
        $sortedTitles = $titles;
        rsort($sortedTitles);
        $this->assertEquals($sortedTitles, $titles);
    }

    // ==================== Book Create Tests ====================

    public function test_admin_can_create_book(): void
    {
        Sanctum::actingAs($this->adminUser);

        $response = $this->postJson('/books', [
            'title' => 'Admin Created Book',
            'authors' => 'Test Author',
            'isbn' => '9780123456789',
            'language' => 'en',
            'publisher' => 'Test Publisher',
            'page_count' => 300,
            'published_date' => '2024-01-15',
            'description' => 'A book created by admin for testing purposes.',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('books', [
            'title' => 'Admin Created Book',
            'authors' => 'Test Author',
            'isbn' => '9780123456789',
            'language' => 'en',
            'publisher' => 'Test Publisher',
            'page_count' => 300,
            'description' => 'A book created by admin for testing purposes.',
        ]);
    }

    public function test_admin_can_create_book_with_amazon_asin(): void
    {
        Sanctum::actingAs($this->adminUser);

        $response = $this->postJson('/books', [
            'title' => 'Book With Amazon ASIN',
            'authors' => 'ASIN Author',
            'amazon_asin' => 'B08TESTCODE',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('books', [
            'title' => 'Book With Amazon ASIN',
            'amazon_asin' => 'B08TESTCODE',
        ]);
    }

    public function test_admin_can_create_book_with_google_id(): void
    {
        Sanctum::actingAs($this->adminUser);

        $response = $this->postJson('/books', [
            'title' => 'Book With Google ID',
            'authors' => 'Google Author',
            'google_id' => 'google_test_id_123',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('books', [
            'title' => 'Book With Google ID',
            'google_id' => 'google_test_id_123',
        ]);
    }

    public function test_admin_can_create_book_with_minimal_data(): void
    {
        Sanctum::actingAs($this->adminUser);

        $response = $this->postJson('/books', [
            'title' => 'Minimal Book Title',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('books', [
            'title' => 'Minimal Book Title',
        ]);
    }

    public function test_create_book_requires_title(): void
    {
        Sanctum::actingAs($this->adminUser);

        $response = $this->postJson('/books', [
            'authors' => 'Some Author',
            'isbn' => '9780123456789',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['title']);
    }

    public function test_admin_can_create_book_with_year_only_published_date(): void
    {
        Sanctum::actingAs($this->adminUser);

        $response = $this->postJson('/books', [
            'title' => 'Book With Year Only',
            'published_date' => '2024',
        ]);

        $response->assertStatus(201);

        // Year-only dates are converted to YYYY-01-01 format in the database
        $this->assertDatabaseHas('books', [
            'title' => 'Book With Year Only',
        ]);

        $book = Book::where('title', 'Book With Year Only')->first();
        $this->assertNotNull($book);
        $this->assertStringStartsWith('2024', $book->published_date);
    }

    public function test_admin_can_create_book_with_year_month_published_date(): void
    {
        Sanctum::actingAs($this->adminUser);

        $response = $this->postJson('/books', [
            'title' => 'Book With Year Month',
            'published_date' => '2024-06',
        ]);

        $response->assertStatus(201);

        // Year-month dates are converted to YYYY-MM-01 format in the database
        $this->assertDatabaseHas('books', [
            'title' => 'Book With Year Month',
        ]);

        $book = Book::where('title', 'Book With Year Month')->first();
        $this->assertNotNull($book);
        $this->assertStringStartsWith('2024-06', $book->published_date);
    }

    public function test_admin_books_endpoint_returns_description(): void
    {
        Sanctum::actingAs($this->adminUser);

        $book = Book::factory()->create([
            'description' => 'This is a test description for the book.',
        ]);

        $response = $this->getJson('/admin/books');

        $response->assertStatus(200);

        $data = $response->json('data');
        $bookData = collect($data)->firstWhere('id', $book->id);
        $this->assertEquals('This is a test description for the book.', $bookData['description']);
    }

    // ==================== Book Update Tests ====================

    public function test_admin_can_update_book(): void
    {
        Sanctum::actingAs($this->adminUser);

        $book = Book::factory()->create([
            'title' => 'Original Title',
        ]);

        $response = $this->putJson("/books/{$book->id}", [
            'title' => 'Updated Title',
            'authors' => 'Updated Author',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'title' => 'Updated Title',
            'authors' => 'Updated Author',
        ]);
    }

    public function test_admin_can_update_book_amazon_asin(): void
    {
        Sanctum::actingAs($this->adminUser);

        $book = Book::factory()->create();

        $response = $this->putJson("/books/{$book->id}", [
            'title' => $book->title,
            'amazon_asin' => 'B08N5WRWNW',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'amazon_asin' => 'B08N5WRWNW',
        ]);
    }

    // ==================== Book Delete Tests ====================

    public function test_admin_can_delete_book(): void
    {
        Sanctum::actingAs($this->adminUser);

        $book = Book::factory()->create();

        $response = $this->deleteJson("/books/{$book->id}");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Book deleted from global catalog',
            ]);

        $this->assertDatabaseMissing('books', [
            'id' => $book->id,
        ]);
    }

    public function test_deleting_book_removes_from_user_libraries(): void
    {
        Sanctum::actingAs($this->adminUser);

        $book = Book::factory()->create();

        // Attach book to user
        $this->regularUser->books()->attach($book->id, [
            'reading_status' => 'read',
            'added_at' => now(),
        ]);

        $this->assertDatabaseHas('users_books', [
            'user_id' => $this->regularUser->id,
            'book_id' => $book->id,
        ]);

        $response = $this->deleteJson("/books/{$book->id}");

        $response->assertStatus(200);

        $this->assertDatabaseMissing('users_books', [
            'user_id' => $this->regularUser->id,
            'book_id' => $book->id,
        ]);
    }

    // ==================== Role Field Tests ====================

    public function test_auth_me_returns_user_role(): void
    {
        Sanctum::actingAs($this->adminUser);

        $response = $this->getJson('/auth/me');

        $response->assertStatus(200)
            ->assertJsonPath('role', 'admin');
    }

    public function test_regular_user_has_user_role(): void
    {
        Sanctum::actingAs($this->regularUser);

        $response = $this->getJson('/auth/me');

        $response->assertStatus(200)
            ->assertJsonPath('role', 'user');
    }

    // ==================== User CRUD Tests ====================

    public function test_admin_can_create_user(): void
    {
        Sanctum::actingAs($this->adminUser);

        $response = $this->postJson('/users', [
            'display_name' => 'New User',
            'email' => 'newuser@example.com',
            'username' => 'newuser123',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('users', [
            'display_name' => 'New User',
            'email' => 'newuser@example.com',
            'username' => 'newuser123',
        ]);
    }

    public function test_regular_user_cannot_create_user(): void
    {
        Sanctum::actingAs($this->regularUser);

        $response = $this->postJson('/users', [
            'display_name' => 'New User',
            'email' => 'newuser@example.com',
            'username' => 'newuser123',
        ]);

        $response->assertStatus(403);
    }

    public function test_admin_can_update_user(): void
    {
        Sanctum::actingAs($this->adminUser);

        $response = $this->putJson("/users/{$this->regularUser->id}", [
            'display_name' => 'Updated Name',
            'email' => $this->regularUser->email,
            'username' => $this->regularUser->username,
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('users', [
            'id' => $this->regularUser->id,
            'display_name' => 'Updated Name',
        ]);
    }

    public function test_admin_can_update_user_role(): void
    {
        Sanctum::actingAs($this->adminUser);

        $response = $this->putJson("/users/{$this->regularUser->id}", [
            'display_name' => $this->regularUser->display_name,
            'email' => $this->regularUser->email,
            'username' => $this->regularUser->username,
            'role' => 'admin',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('users', [
            'id' => $this->regularUser->id,
            'role' => 'admin',
        ]);
    }

    public function test_regular_user_cannot_update_other_user(): void
    {
        Sanctum::actingAs($this->regularUser);

        $response = $this->putJson("/users/{$this->adminUser->id}", [
            'display_name' => 'Hacked Name',
            'email' => $this->adminUser->email,
            'username' => $this->adminUser->username,
        ]);

        $response->assertStatus(403);
    }

    public function test_admin_can_delete_user(): void
    {
        Sanctum::actingAs($this->adminUser);

        $userToDelete = User::factory()->create();

        $response = $this->deleteJson("/users/{$userToDelete->id}");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'User deleted successfully',
            ]);

        $this->assertDatabaseMissing('users', [
            'id' => $userToDelete->id,
        ]);
    }

    public function test_regular_user_cannot_delete_other_user(): void
    {
        Sanctum::actingAs($this->regularUser);

        $response = $this->deleteJson("/users/{$this->adminUser->id}");

        $response->assertStatus(403);
    }

    // ==================== Book Permission Tests ====================

    public function test_regular_user_cannot_update_book(): void
    {
        Sanctum::actingAs($this->regularUser);

        $book = Book::factory()->create();

        $response = $this->putJson("/books/{$book->id}", [
            'title' => 'Hacked Title',
        ]);

        $response->assertStatus(403);
    }

    public function test_regular_user_cannot_delete_book(): void
    {
        Sanctum::actingAs($this->regularUser);

        $book = Book::factory()->create();

        $response = $this->deleteJson("/books/{$book->id}");

        $response->assertStatus(403);
    }

    // ==================== Edge Cases Tests ====================

    public function test_invalid_sort_column_falls_back_to_default(): void
    {
        Sanctum::actingAs($this->adminUser);

        $response = $this->getJson('/admin/users?sort_by=invalid_column&sort_desc=true');

        $response->assertStatus(200);

        // Should still return data ordered by created_at desc (default)
        $data = $response->json('data');
        $createdDates = array_column($data, 'created_at');
        $sortedDates = $createdDates;
        usort($sortedDates, fn ($a, $b) => strtotime($b) - strtotime($a));
        $this->assertEquals($sortedDates, $createdDates);
    }

    public function test_user_without_activity_has_null_last_activity(): void
    {
        Sanctum::actingAs($this->adminUser);

        $response = $this->getJson('/admin/users');

        $response->assertStatus(200);

        $data = $response->json('data');
        // Users created in setUp have no activities
        $adminData = collect($data)->firstWhere('id', $this->adminUser->id);
        $this->assertNull($adminData['last_activity']);
    }

    public function test_last_activity_with_user_followed_type(): void
    {
        Sanctum::actingAs($this->adminUser);

        $followedUser = User::factory()->create();

        $activity = new \App\Models\Activity;
        $activity->user_id = $this->regularUser->id;
        $activity->type = 'user_followed';
        $activity->subject_type = 'App\\Models\\User';
        $activity->subject_id = $followedUser->id;
        $activity->save();

        $response = $this->getJson('/admin/users');

        $response->assertStatus(200);

        $data = $response->json('data');
        $userData = collect($data)->firstWhere('id', $this->regularUser->id);

        $this->assertNotNull($userData['last_activity']);
        $this->assertEquals('user_followed', $userData['last_activity']['type']);
        $this->assertEquals($followedUser->display_name ?? $followedUser->username, $userData['last_activity']['subject_name']);
    }

    public function test_last_activity_with_review_written_type(): void
    {
        Sanctum::actingAs($this->adminUser);

        $book = Book::factory()->create();

        // Add book to user's library first
        $this->regularUser->books()->attach($book->id, [
            'reading_status' => 'read',
            'added_at' => now(),
        ]);

        // Create a review
        $review = \App\Models\Review::create([
            'user_id' => $this->regularUser->id,
            'book_id' => $book->id,
            'rating' => 5,
            'content' => 'Great book!',
        ]);

        // Ensure review_written activity is the most recent
        $activity = new \App\Models\Activity;
        $activity->user_id = $this->regularUser->id;
        $activity->type = 'review_written';
        $activity->subject_type = 'App\\Models\\Review';
        $activity->subject_id = $review->id;
        $activity->created_at = now()->addSecond();
        $activity->save();

        $response = $this->getJson('/admin/users');

        $response->assertStatus(200);

        $data = $response->json('data');
        $userData = collect($data)->firstWhere('id', $this->regularUser->id);

        $this->assertNotNull($userData['last_activity']);
        $this->assertEquals('review_written', $userData['last_activity']['type']);
        $this->assertEquals($book->title, $userData['last_activity']['subject_name']);
    }
}
