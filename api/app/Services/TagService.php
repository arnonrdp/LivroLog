<?php

namespace App\Services;

use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class TagService
{
    /**
     * Available colors for tags.
     */
    public const COLORS = [
        '#EF4444', // Red
        '#F97316', // Orange
        '#EAB308', // Yellow
        '#22C55E', // Green
        '#3B82F6', // Blue
        '#A855F7', // Purple
        '#EC4899', // Pink
        '#6B7280', // Gray
    ];

    /**
     * Suggested tag names for new users.
     */
    public const SUGGESTIONS = [
        'Doação',
        'Emprestado',
        'Favoritos',
        'Para reler',
        'Presente',
        'Wishlist',
    ];

    /**
     * Get all tags for a user with books count.
     */
    public function getUserTags(User $user): Collection
    {
        return $user->tags()
            ->withCount('books')
            ->orderBy('name')
            ->get();
    }

    /**
     * Get a single tag by ID if it belongs to the user.
     */
    public function getTag(User $user, string $tagId): ?Tag
    {
        return $user->tags()->find($tagId);
    }

    /**
     * Create a new tag for the user.
     */
    public function createTag(User $user, array $data): Tag
    {
        return $user->tags()->create([
            'name' => trim($data['name']),
            'color' => $data['color'],
        ]);
    }

    /**
     * Update an existing tag.
     */
    public function updateTag(Tag $tag, array $data): Tag
    {
        $updateData = [];

        if (isset($data['name'])) {
            $updateData['name'] = trim($data['name']);
        }

        if (isset($data['color'])) {
            $updateData['color'] = $data['color'];
        }

        $tag->update($updateData);

        return $tag->fresh();
    }

    /**
     * Delete a tag (cascades to user_book_tags).
     */
    public function deleteTag(Tag $tag): bool
    {
        return $tag->delete();
    }

    /**
     * Add a tag to a user's book.
     */
    public function addTagToBook(User $user, string $bookId, string $tagId): bool
    {
        // Verify the tag belongs to the user
        $tag = $user->tags()->find($tagId);
        if (! $tag) {
            return false;
        }

        // Verify the book is in user's library
        $hasBook = $user->books()->where('books.id', $bookId)->exists();
        if (! $hasBook) {
            return false;
        }

        // Check if already exists
        $exists = DB::table('user_book_tags')
            ->where('user_id', $user->id)
            ->where('book_id', $bookId)
            ->where('tag_id', $tagId)
            ->exists();

        if ($exists) {
            return true; // Already tagged
        }

        DB::table('user_book_tags')->insert([
            'user_id' => $user->id,
            'book_id' => $bookId,
            'tag_id' => $tagId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return true;
    }

    /**
     * Remove a tag from a user's book.
     */
    public function removeTagFromBook(User $user, string $bookId, string $tagId): bool
    {
        return DB::table('user_book_tags')
            ->where('user_id', $user->id)
            ->where('book_id', $bookId)
            ->where('tag_id', $tagId)
            ->delete() > 0;
    }

    /**
     * Get all tags for a specific book in user's library.
     */
    public function getBookTags(User $user, string $bookId): Collection
    {
        $tagIds = DB::table('user_book_tags')
            ->where('user_id', $user->id)
            ->where('book_id', $bookId)
            ->pluck('tag_id');

        return Tag::whereIn('id', $tagIds)->orderBy('name')->get();
    }

    /**
     * Sync tags for a user's book (replaces all existing tags).
     */
    public function syncBookTags(User $user, string $bookId, array $tagIds): bool
    {
        // Verify the book is in user's library
        $hasBook = $user->books()->where('books.id', $bookId)->exists();
        if (! $hasBook) {
            return false;
        }

        // Verify all tags belong to the user
        $validTagIds = $user->tags()->whereIn('id', $tagIds)->pluck('id')->toArray();

        DB::transaction(function () use ($user, $bookId, $validTagIds) {
            // Remove all existing tags for this book
            DB::table('user_book_tags')
                ->where('user_id', $user->id)
                ->where('book_id', $bookId)
                ->delete();

            // Add new tags
            $now = now();
            $inserts = array_map(fn ($tagId) => [
                'user_id' => $user->id,
                'book_id' => $bookId,
                'tag_id' => $tagId,
                'created_at' => $now,
                'updated_at' => $now,
            ], $validTagIds);

            if (! empty($inserts)) {
                DB::table('user_book_tags')->insert($inserts);
            }
        });

        return true;
    }

    /**
     * Get tag colors available.
     */
    public function getAvailableColors(): array
    {
        return self::COLORS;
    }

    /**
     * Get tag suggestions for new users.
     */
    public function getSuggestions(): array
    {
        return self::SUGGESTIONS;
    }

    /**
     * Check if a tag name is valid (not empty, reasonable length).
     */
    public function isValidTagName(string $name): bool
    {
        $trimmed = trim($name);

        return strlen($trimmed) >= 1 && strlen($trimmed) <= 50;
    }

    /**
     * Check if a color is valid (from our palette).
     */
    public function isValidColor(string $color): bool
    {
        return in_array(strtoupper($color), array_map('strtoupper', self::COLORS));
    }
}
