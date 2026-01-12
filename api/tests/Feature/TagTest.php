<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TagTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test user can get their tags list.
     */
    public function test_user_can_get_tags_list(): void
    {
        $user = User::factory()->create();
        Tag::factory()->count(3)->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/tags');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'color', 'books_count', 'created_at', 'updated_at'],
                ],
                'meta' => ['colors', 'suggestions'],
            ]);

        $this->assertCount(3, $response->json('data'));
    }

    /**
     * Test tags list returns meta with colors and suggestions.
     */
    public function test_tags_list_returns_meta_with_colors_and_suggestions(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->getJson('/tags');

        $response->assertStatus(200);

        $meta = $response->json('meta');
        $this->assertIsArray($meta['colors']);
        $this->assertNotEmpty($meta['colors']);
        $this->assertIsArray($meta['suggestions']);
        $this->assertNotEmpty($meta['suggestions']);
    }

    /**
     * Test unauthenticated user cannot access tags.
     */
    public function test_unauthenticated_user_cannot_access_tags(): void
    {
        $response = $this->getJson('/tags');

        $response->assertStatus(401);
    }

    /**
     * Test user can create a tag.
     */
    public function test_user_can_create_tag(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/tags', [
            'name' => 'Favoritos',
            'color' => '#EF4444',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => ['id', 'name', 'color'],
                'message',
            ]);

        $this->assertDatabaseHas('tags', [
            'user_id' => $user->id,
            'name' => 'Favoritos',
            'color' => '#EF4444',
        ]);
    }

    /**
     * Test tag ID is auto-generated with T- prefix.
     */
    public function test_tag_id_is_auto_generated_with_prefix(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/tags', [
            'name' => 'Test Tag',
            'color' => '#22C55E',
        ]);

        $response->assertStatus(201);

        $tagId = $response->json('data.id');
        $this->assertStringStartsWith('T-', $tagId);
    }

    /**
     * Test cannot create tag without name.
     */
    public function test_cannot_create_tag_without_name(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/tags', [
            'color' => '#EF4444',
        ]);

        $response->assertStatus(422);
    }

    /**
     * Test cannot create tag without color.
     */
    public function test_cannot_create_tag_without_color(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/tags', [
            'name' => 'Test',
        ]);

        $response->assertStatus(422);
    }

    /**
     * Test cannot create tag with invalid color format.
     */
    public function test_cannot_create_tag_with_invalid_color(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/tags', [
            'name' => 'Test',
            'color' => 'not-a-color',
        ]);

        $response->assertStatus(422);
    }

    /**
     * Test cannot create duplicate tag name (case-insensitive).
     */
    public function test_cannot_create_duplicate_tag_name(): void
    {
        $user = User::factory()->create();
        Tag::factory()->create(['user_id' => $user->id, 'name' => 'Favoritos']);

        Sanctum::actingAs($user);

        $response = $this->postJson('/tags', [
            'name' => 'favoritos',
            'color' => '#22C55E',
        ]);

        $response->assertStatus(422);
    }

    /**
     * Test different users can have tags with same name.
     */
    public function test_different_users_can_have_same_tag_name(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        Tag::factory()->create(['user_id' => $user1->id, 'name' => 'Favoritos']);

        Sanctum::actingAs($user2);

        $response = $this->postJson('/tags', [
            'name' => 'Favoritos',
            'color' => '#EF4444',
        ]);

        $response->assertStatus(201);
    }

    /**
     * Test user can update tag name.
     */
    public function test_user_can_update_tag_name(): void
    {
        $user = User::factory()->create();
        $tag = Tag::factory()->create(['user_id' => $user->id, 'name' => 'Old Name']);

        Sanctum::actingAs($user);

        $response = $this->putJson("/tags/{$tag->id}", [
            'name' => 'New Name',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('tags', [
            'id' => $tag->id,
            'name' => 'New Name',
        ]);
    }

    /**
     * Test user can update tag color.
     */
    public function test_user_can_update_tag_color(): void
    {
        $user = User::factory()->create();
        $tag = Tag::factory()->create(['user_id' => $user->id, 'color' => '#EF4444']);

        Sanctum::actingAs($user);

        $response = $this->putJson("/tags/{$tag->id}", [
            'color' => '#22C55E',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('tags', [
            'id' => $tag->id,
            'color' => '#22C55E',
        ]);
    }

    /**
     * Test user cannot update tag to duplicate name.
     */
    public function test_user_cannot_update_tag_to_duplicate_name(): void
    {
        $user = User::factory()->create();
        Tag::factory()->create(['user_id' => $user->id, 'name' => 'Existing']);
        $tag = Tag::factory()->create(['user_id' => $user->id, 'name' => 'Original']);

        Sanctum::actingAs($user);

        $response = $this->putJson("/tags/{$tag->id}", [
            'name' => 'existing',
        ]);

        $response->assertStatus(422);
    }

    /**
     * Test user cannot update another user's tag.
     */
    public function test_user_cannot_update_another_users_tag(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $tag = Tag::factory()->create(['user_id' => $user1->id]);

        Sanctum::actingAs($user2);

        $response = $this->putJson("/tags/{$tag->id}", [
            'name' => 'Hacked',
        ]);

        $response->assertStatus(404);
    }

    /**
     * Test user can delete tag.
     */
    public function test_user_can_delete_tag(): void
    {
        $user = User::factory()->create();
        $tag = Tag::factory()->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        $response = $this->deleteJson("/tags/{$tag->id}");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('tags', ['id' => $tag->id]);
    }

    /**
     * Test user cannot delete another user's tag.
     */
    public function test_user_cannot_delete_another_users_tag(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $tag = Tag::factory()->create(['user_id' => $user1->id]);

        Sanctum::actingAs($user2);

        $response = $this->deleteJson("/tags/{$tag->id}");

        $response->assertStatus(404);
    }

    /**
     * Test deleting tag removes it from all books (cascade).
     */
    public function test_deleting_tag_cascades_to_book_associations(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();
        $tag = Tag::factory()->create(['user_id' => $user->id]);

        // Add book to user's library
        $user->books()->attach($book->id, [
            'added_at' => now(),
            'reading_status' => 'read',
        ]);

        // Associate tag with book
        DB::table('user_book_tags')->insert([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'tag_id' => $tag->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $response = $this->deleteJson("/tags/{$tag->id}");

        $response->assertStatus(200);

        $this->assertDatabaseMissing('user_book_tags', [
            'tag_id' => $tag->id,
        ]);
    }

    /**
     * Test user can get tags for a specific book.
     */
    public function test_user_can_get_book_tags(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();
        $tag1 = Tag::factory()->create(['user_id' => $user->id, 'name' => 'Tag A']);
        $tag2 = Tag::factory()->create(['user_id' => $user->id, 'name' => 'Tag B']);

        // Add book to user's library
        $user->books()->attach($book->id, [
            'added_at' => now(),
            'reading_status' => 'read',
        ]);

        // Associate tags with book
        DB::table('user_book_tags')->insert([
            ['user_id' => $user->id, 'book_id' => $book->id, 'tag_id' => $tag1->id, 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => $user->id, 'book_id' => $book->id, 'tag_id' => $tag2->id, 'created_at' => now(), 'updated_at' => now()],
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/user/books/{$book->id}/tags");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    /**
     * Test cannot get tags for book not in library.
     */
    public function test_cannot_get_tags_for_book_not_in_library(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->getJson("/user/books/{$book->id}/tags");

        $response->assertStatus(404);
    }

    /**
     * Test user can add tag to book.
     */
    public function test_user_can_add_tag_to_book(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();
        $tag = Tag::factory()->create(['user_id' => $user->id]);

        // Add book to user's library
        $user->books()->attach($book->id, [
            'added_at' => now(),
            'reading_status' => 'read',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson("/user/books/{$book->id}/tags/{$tag->id}");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('user_book_tags', [
            'user_id' => $user->id,
            'book_id' => $book->id,
            'tag_id' => $tag->id,
        ]);
    }

    /**
     * Test adding same tag twice is idempotent.
     */
    public function test_adding_same_tag_twice_is_idempotent(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();
        $tag = Tag::factory()->create(['user_id' => $user->id]);

        // Add book to user's library
        $user->books()->attach($book->id, [
            'added_at' => now(),
            'reading_status' => 'read',
        ]);

        Sanctum::actingAs($user);

        // Add tag first time
        $this->postJson("/user/books/{$book->id}/tags/{$tag->id}");

        // Add same tag again
        $response = $this->postJson("/user/books/{$book->id}/tags/{$tag->id}");

        $response->assertStatus(200);

        // Should only have one entry
        $count = DB::table('user_book_tags')
            ->where('user_id', $user->id)
            ->where('book_id', $book->id)
            ->where('tag_id', $tag->id)
            ->count();

        $this->assertEquals(1, $count);
    }

    /**
     * Test cannot add tag to book not in library.
     */
    public function test_cannot_add_tag_to_book_not_in_library(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();
        $tag = Tag::factory()->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        $response = $this->postJson("/user/books/{$book->id}/tags/{$tag->id}");

        $response->assertStatus(404);
    }

    /**
     * Test cannot add another user's tag to book.
     */
    public function test_cannot_add_another_users_tag_to_book(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $book = Book::factory()->create();
        $tag = Tag::factory()->create(['user_id' => $user1->id]);

        // Add book to user2's library
        $user2->books()->attach($book->id, [
            'added_at' => now(),
            'reading_status' => 'read',
        ]);

        Sanctum::actingAs($user2);

        $response = $this->postJson("/user/books/{$book->id}/tags/{$tag->id}");

        $response->assertStatus(404);
    }

    /**
     * Test user can remove tag from book.
     */
    public function test_user_can_remove_tag_from_book(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();
        $tag = Tag::factory()->create(['user_id' => $user->id]);

        // Add book to user's library
        $user->books()->attach($book->id, [
            'added_at' => now(),
            'reading_status' => 'read',
        ]);

        // Associate tag with book
        DB::table('user_book_tags')->insert([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'tag_id' => $tag->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $response = $this->deleteJson("/user/books/{$book->id}/tags/{$tag->id}");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('user_book_tags', [
            'user_id' => $user->id,
            'book_id' => $book->id,
            'tag_id' => $tag->id,
        ]);
    }

    /**
     * Test removing non-existent tag association returns error.
     */
    public function test_removing_non_existent_tag_association_returns_error(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();
        $tag = Tag::factory()->create(['user_id' => $user->id]);

        // Add book to user's library (but don't associate tag)
        $user->books()->attach($book->id, [
            'added_at' => now(),
            'reading_status' => 'read',
        ]);

        Sanctum::actingAs($user);

        $response = $this->deleteJson("/user/books/{$book->id}/tags/{$tag->id}");

        $response->assertStatus(404);
    }

    /**
     * Test user can sync tags for a book.
     */
    public function test_user_can_sync_book_tags(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();
        $tag1 = Tag::factory()->create(['user_id' => $user->id]);
        $tag2 = Tag::factory()->create(['user_id' => $user->id]);
        $tag3 = Tag::factory()->create(['user_id' => $user->id]);

        // Add book to user's library
        $user->books()->attach($book->id, [
            'added_at' => now(),
            'reading_status' => 'read',
        ]);

        // Associate tag1 initially
        DB::table('user_book_tags')->insert([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'tag_id' => $tag1->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($user);

        // Sync to tag2 and tag3 (remove tag1)
        $response = $this->postJson("/user/books/{$book->id}/tags", [
            'tag_ids' => [$tag2->id, $tag3->id],
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonCount(2, 'data');

        // Verify tag1 was removed
        $this->assertDatabaseMissing('user_book_tags', [
            'book_id' => $book->id,
            'tag_id' => $tag1->id,
        ]);

        // Verify tag2 and tag3 are present
        $this->assertDatabaseHas('user_book_tags', [
            'book_id' => $book->id,
            'tag_id' => $tag2->id,
        ]);

        $this->assertDatabaseHas('user_book_tags', [
            'book_id' => $book->id,
            'tag_id' => $tag3->id,
        ]);
    }

    /**
     * Test sync with empty array removes all tags.
     */
    public function test_sync_with_empty_array_removes_all_tags(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();
        $tag = Tag::factory()->create(['user_id' => $user->id]);

        // Add book to user's library
        $user->books()->attach($book->id, [
            'added_at' => now(),
            'reading_status' => 'read',
        ]);

        // Associate tag
        DB::table('user_book_tags')->insert([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'tag_id' => $tag->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson("/user/books/{$book->id}/tags", [
            'tag_ids' => [],
        ]);

        $response->assertStatus(200)
            ->assertJsonCount(0, 'data');

        $this->assertDatabaseMissing('user_book_tags', [
            'book_id' => $book->id,
        ]);
    }

    /**
     * Test user books endpoint includes tags.
     */
    public function test_user_books_endpoint_includes_tags(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();
        $tag = Tag::factory()->create(['user_id' => $user->id, 'name' => 'Test Tag']);

        // Add book to user's library
        $user->books()->attach($book->id, [
            'added_at' => now(),
            'reading_status' => 'read',
        ]);

        // Associate tag
        DB::table('user_book_tags')->insert([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'tag_id' => $tag->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/user/books');

        $response->assertStatus(200);

        $books = $response->json();
        $this->assertNotEmpty($books);
        $this->assertArrayHasKey('tags', $books[0]);
        $this->assertNotEmpty($books[0]['tags']);
    }

    /**
     * Test tag name is trimmed on create.
     */
    public function test_tag_name_is_trimmed_on_create(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/tags', [
            'name' => '  Favoritos  ',
            'color' => '#EF4444',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('tags', [
            'user_id' => $user->id,
            'name' => 'Favoritos',
        ]);
    }

    /**
     * Test tag name cannot exceed 50 characters.
     */
    public function test_tag_name_cannot_exceed_50_characters(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/tags', [
            'name' => str_repeat('a', 51),
            'color' => '#EF4444',
        ]);

        $response->assertStatus(422);
    }

    /**
     * Test tags are sorted alphabetically.
     */
    public function test_tags_are_sorted_alphabetically(): void
    {
        $user = User::factory()->create();
        Tag::factory()->create(['user_id' => $user->id, 'name' => 'Zebra']);
        Tag::factory()->create(['user_id' => $user->id, 'name' => 'Apple']);
        Tag::factory()->create(['user_id' => $user->id, 'name' => 'Middle']);

        Sanctum::actingAs($user);

        $response = $this->getJson('/tags');

        $response->assertStatus(200);

        $names = array_column($response->json('data'), 'name');
        $this->assertEquals(['Apple', 'Middle', 'Zebra'], $names);
    }

    /**
     * Test book tags are sorted alphabetically.
     */
    public function test_book_tags_are_sorted_alphabetically(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();
        $tag1 = Tag::factory()->create(['user_id' => $user->id, 'name' => 'Zebra']);
        $tag2 = Tag::factory()->create(['user_id' => $user->id, 'name' => 'Apple']);

        // Add book to user's library
        $user->books()->attach($book->id, [
            'added_at' => now(),
            'reading_status' => 'read',
        ]);

        // Associate tags
        DB::table('user_book_tags')->insert([
            ['user_id' => $user->id, 'book_id' => $book->id, 'tag_id' => $tag1->id, 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => $user->id, 'book_id' => $book->id, 'tag_id' => $tag2->id, 'created_at' => now(), 'updated_at' => now()],
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/user/books/{$book->id}/tags");

        $response->assertStatus(200);

        $names = array_column($response->json('data'), 'name');
        $this->assertEquals(['Apple', 'Zebra'], $names);
    }

    /**
     * Test books_count is accurate.
     */
    public function test_books_count_is_accurate(): void
    {
        $user = User::factory()->create();
        $book1 = Book::factory()->create();
        $book2 = Book::factory()->create();
        $tag = Tag::factory()->create(['user_id' => $user->id]);

        // Add books to user's library
        $user->books()->attach($book1->id, ['added_at' => now(), 'reading_status' => 'read']);
        $user->books()->attach($book2->id, ['added_at' => now(), 'reading_status' => 'read']);

        // Associate tag with both books
        DB::table('user_book_tags')->insert([
            ['user_id' => $user->id, 'book_id' => $book1->id, 'tag_id' => $tag->id, 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => $user->id, 'book_id' => $book2->id, 'tag_id' => $tag->id, 'created_at' => now(), 'updated_at' => now()],
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/tags');

        $response->assertStatus(200);

        $tags = $response->json('data');
        $this->assertEquals(2, $tags[0]['books_count']);
    }
}
