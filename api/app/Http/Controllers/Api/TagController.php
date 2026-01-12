<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TagResource;
use App\Models\Book;
use App\Models\Tag;
use App\Services\TagService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TagController extends Controller
{
    protected TagService $service;

    public function __construct(TagService $service)
    {
        $this->service = $service;
    }

    /**
     * Get all tags for the authenticated user.
     *
     * @OA\Get(
     *     path="/tags",
     *     summary="Get all tags for the authenticated user",
     *     tags={"Tags"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(
     *         response=200,
     *         description="List of tags",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Tag")),
     *             @OA\Property(property="meta", type="object",
     *                 @OA\Property(property="colors", type="array", @OA\Items(type="string", example="#EF4444")),
     *                 @OA\Property(property="suggestions", type="array", @OA\Items(type="string", example="Doação"))
     *             )
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $tags = $this->service->getUserTags($request->user());

        return response()->json([
            'data' => TagResource::collection($tags),
            'meta' => [
                'colors' => $this->service->getAvailableColors(),
                'suggestions' => $this->service->getSuggestions(),
            ],
        ]);
    }

    /**
     * Create a new tag.
     *
     * @OA\Post(
     *     path="/tags",
     *     summary="Create a new tag",
     *     tags={"Tags"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="name", type="string", example="Doação"),
     *             @OA\Property(property="color", type="string", example="#EF4444")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Tag created",
     *
     *         @OA\JsonContent(ref="#/components/schemas/Tag")
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|min:1|max:50',
            'color' => 'required|string|regex:/^#[A-Fa-f0-9]{6}$/',
        ]);

        // Check for duplicate name
        $exists = $request->user()->tags()
            ->whereRaw('LOWER(name) = ?', [strtolower(trim($validated['name']))])
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'Tag with this name already exists',
                'errors' => ['name' => ['Tag with this name already exists']],
            ], 422);
        }

        $tag = $this->service->createTag($request->user(), $validated);

        return response()->json([
            'data' => new TagResource($tag),
            'message' => 'Tag created successfully',
        ], 201);
    }

    /**
     * Get a specific tag.
     *
     * @OA\Get(
     *     path="/tags/{tag}",
     *     summary="Get a specific tag",
     *     tags={"Tags"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="tag",
     *         in="path",
     *         required=true,
     *
     *         @OA\Schema(type="string", example="T-1ABC-2DEF")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Tag details",
     *
     *         @OA\JsonContent(ref="#/components/schemas/Tag")
     *     ),
     *     @OA\Response(response=404, description="Tag not found")
     * )
     */
    public function show(Request $request, Tag $tag): JsonResponse
    {
        // Ensure the tag belongs to the authenticated user
        if ($tag->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Tag not found'], 404);
        }

        $tag->loadCount('books');

        return response()->json([
            'data' => new TagResource($tag),
        ]);
    }

    /**
     * Update a tag.
     *
     * @OA\Put(
     *     path="/tags/{tag}",
     *     summary="Update a tag",
     *     tags={"Tags"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="tag",
     *         in="path",
     *         required=true,
     *
     *         @OA\Schema(type="string", example="T-1ABC-2DEF")
     *     ),
     *
     *     @OA\RequestBody(
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="name", type="string", example="Favoritos"),
     *             @OA\Property(property="color", type="string", example="#22C55E")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Tag updated",
     *
     *         @OA\JsonContent(ref="#/components/schemas/Tag")
     *     ),
     *     @OA\Response(response=404, description="Tag not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update(Request $request, Tag $tag): JsonResponse
    {
        // Ensure the tag belongs to the authenticated user
        if ($tag->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Tag not found'], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|min:1|max:50',
            'color' => 'sometimes|string|regex:/^#[A-Fa-f0-9]{6}$/',
        ]);

        // Check for duplicate name if changing
        if (isset($validated['name'])) {
            $exists = $request->user()->tags()
                ->where('id', '!=', $tag->id)
                ->whereRaw('LOWER(name) = ?', [strtolower(trim($validated['name']))])
                ->exists();

            if ($exists) {
                return response()->json([
                    'message' => 'Tag with this name already exists',
                    'errors' => ['name' => ['Tag with this name already exists']],
                ], 422);
            }
        }

        $tag = $this->service->updateTag($tag, $validated);
        $tag->loadCount('books');

        return response()->json([
            'data' => new TagResource($tag),
            'message' => 'Tag updated successfully',
        ]);
    }

    /**
     * Delete a tag.
     *
     * @OA\Delete(
     *     path="/tags/{tag}",
     *     summary="Delete a tag",
     *     tags={"Tags"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="tag",
     *         in="path",
     *         required=true,
     *
     *         @OA\Schema(type="string", example="T-1ABC-2DEF")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Tag deleted",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Tag deleted successfully")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Tag not found")
     * )
     */
    public function destroy(Request $request, Tag $tag): JsonResponse
    {
        // Ensure the tag belongs to the authenticated user
        if ($tag->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Tag not found'], 404);
        }

        $this->service->deleteTag($tag);

        return response()->json([
            'success' => true,
            'message' => 'Tag deleted successfully',
        ]);
    }

    /**
     * Get all tags for a specific book in user's library.
     *
     * @OA\Get(
     *     path="/user/books/{book}/tags",
     *     summary="Get tags for a book in user's library",
     *     tags={"Tags"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="book",
     *         in="path",
     *         required=true,
     *
     *         @OA\Schema(type="string", example="B-1ABC-2DEF")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="List of tags for the book",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Tag"))
     *         )
     *     ),
     *     @OA\Response(response=404, description="Book not found in library")
     * )
     */
    public function getBookTags(Request $request, Book $book): JsonResponse
    {
        $user = $request->user();

        // Verify the book is in user's library
        if (! $user->books()->where('books.id', $book->id)->exists()) {
            return response()->json(['message' => 'Book not found in your library'], 404);
        }

        $tags = $this->service->getBookTags($user, $book->id);

        return response()->json([
            'data' => TagResource::collection($tags),
        ]);
    }

    /**
     * Sync tags for a book (replace all existing tags).
     *
     * @OA\Post(
     *     path="/user/books/{book}/tags",
     *     summary="Sync tags for a book (replace all)",
     *     tags={"Tags"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="book",
     *         in="path",
     *         required=true,
     *
     *         @OA\Schema(type="string", example="B-1ABC-2DEF")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="tag_ids", type="array", @OA\Items(type="string"), example={"T-1ABC-2DEF", "T-3XYZ-4UVW"})
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Tags synced successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Tag"))
     *         )
     *     ),
     *     @OA\Response(response=404, description="Book not found in library")
     * )
     */
    public function syncBookTags(Request $request, Book $book): JsonResponse
    {
        $validated = $request->validate([
            'tag_ids' => 'present|array',
            'tag_ids.*' => 'string',
        ]);

        $user = $request->user();

        $success = $this->service->syncBookTags($user, $book->id, $validated['tag_ids']);

        if (! $success) {
            return response()->json(['message' => 'Book not found in your library'], 404);
        }

        $tags = $this->service->getBookTags($user, $book->id);

        return response()->json([
            'success' => true,
            'message' => 'Tags synced successfully',
            'data' => TagResource::collection($tags),
        ]);
    }

    /**
     * Add a tag to a book.
     *
     * @OA\Post(
     *     path="/user/books/{book}/tags/{tag}",
     *     summary="Add a tag to a book",
     *     tags={"Tags"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="book",
     *         in="path",
     *         required=true,
     *
     *         @OA\Schema(type="string", example="B-1ABC-2DEF")
     *     ),
     *     @OA\Parameter(
     *         name="tag",
     *         in="path",
     *         required=true,
     *
     *         @OA\Schema(type="string", example="T-1ABC-2DEF")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Tag added to book",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Book or tag not found")
     * )
     */
    public function addTagToBook(Request $request, Book $book, Tag $tag): JsonResponse
    {
        $user = $request->user();

        // Ensure the tag belongs to the authenticated user
        if ($tag->user_id !== $user->id) {
            return response()->json(['message' => 'Tag not found'], 404);
        }

        $success = $this->service->addTagToBook($user, $book->id, $tag->id);

        if (! $success) {
            return response()->json(['message' => 'Book not found in your library'], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Tag added to book',
        ]);
    }

    /**
     * Remove a tag from a book.
     *
     * @OA\Delete(
     *     path="/user/books/{book}/tags/{tag}",
     *     summary="Remove a tag from a book",
     *     tags={"Tags"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="book",
     *         in="path",
     *         required=true,
     *
     *         @OA\Schema(type="string", example="B-1ABC-2DEF")
     *     ),
     *     @OA\Parameter(
     *         name="tag",
     *         in="path",
     *         required=true,
     *
     *         @OA\Schema(type="string", example="T-1ABC-2DEF")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Tag removed from book",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Tag not associated with this book")
     * )
     */
    public function removeTagFromBook(Request $request, Book $book, Tag $tag): JsonResponse
    {
        $user = $request->user();

        // Ensure the tag belongs to the authenticated user
        if ($tag->user_id !== $user->id) {
            return response()->json(['message' => 'Tag not found'], 404);
        }

        $success = $this->service->removeTagFromBook($user, $book->id, $tag->id);

        if (! $success) {
            return response()->json(['message' => 'Tag not associated with this book'], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Tag removed from book',
        ]);
    }
}
