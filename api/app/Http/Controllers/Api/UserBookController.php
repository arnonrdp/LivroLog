<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaginatedResource;
use App\Models\Book;
use App\Services\BookEnrichmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserBookController extends Controller
{
    // Validation constants to avoid duplication
    private const VALIDATION_NULLABLE_STRING = 'nullable|string';

    /**
     * @OA\Get(
     *     path="/user/books",
     *     operationId="getUserBooks",
     *     tags={"User Library"},
     *     summary="Get user's personal library",
     *     description="Returns paginated list of books in the authenticated user's personal library",
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         required=false,
     *
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Parameter(
     *         name="all",
     *         in="query",
     *         description="Return all books without pagination",
     *         required=false,
     *
     *         @OA\Schema(type="string", example="true")
     *     ),
     *
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Items per page",
     *         required=false,
     *
     *         @OA\Schema(type="integer", example=20)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="User's books library",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Book")),
     *             @OA\Property(property="current_page", type="integer"),
     *             @OA\Property(property="total", type="integer"),
     *             @OA\Property(property="per_page", type="integer")
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function index(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $query = $user->books()->withPivot('added_at', 'read_at', 'is_private', 'reading_status');

        // If 'all' parameter is present, return all books without pagination
        if ($request->has('all') && $request->get('all') === 'true') {
            $books = $query->get();

            return response()->json(['data' => $books]);
        }

        // Otherwise, paginate with configurable per_page parameter (default 20)
        $perPage = $request->get('per_page', 20);
        $books = $query->paginate($perPage);

        return new PaginatedResource($books);
    }

    /**
     * @OA\Post(
     *     path="/user/books",
     *     operationId="addBookToLibrary",
     *     tags={"User Library"},
     *     summary="Add book to user's personal library",
     *     description="Adds a book to the authenticated user's personal library using book identifiers. Priority: book_id > isbn > google_id. If book doesn't exist and google_id provided, creates and enriches automatically.",
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *         description="Book identifiers - provide at least one",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="book_id", type="string", example="B-1ABC-2DEF", description="Book ID if already exists in system"),
     *             @OA\Property(property="isbn", type="string", example="9781505108293", description="ISBN to search for existing book"),
     *             @OA\Property(property="google_id", type="string", example="HuKNDAAAQBAJ", description="Google Books ID for search/creation and enrichment"),
     *             @OA\Property(property="is_private", type="boolean", example=false, description="Whether to mark this book as private in user's library"),
     *             @OA\Property(property="reading_status", type="string", enum={"want_to_read", "reading", "read", "abandoned", "on_hold", "re_reading"}, example="read", description="Reading status for this book")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Book added to library successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="book", ref="#/components/schemas/Book"),
     *             @OA\Property(property="enriched", type="boolean", example=true),
     *             @OA\Property(property="already_in_library", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Book added to your library successfully")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Book already in library",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="book", ref="#/components/schemas/Book"),
     *             @OA\Property(property="enriched", type="boolean", example=false),
     *             @OA\Property(property="already_in_library", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Book is already in your library")
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request, BookEnrichmentService $enrichmentService)
    {
        $request->validate([
            'book_id' => 'nullable|string|exists:books,id',
            'isbn' => self::VALIDATION_NULLABLE_STRING.'|max:20',
            'google_id' => self::VALIDATION_NULLABLE_STRING,
            'is_private' => 'boolean',
            'reading_status' => 'nullable|string|in:want_to_read,reading,read,abandoned,on_hold,re_reading',
        ]);

        $user = $request->user();
        $bookId = $request->input('book_id');
        $isbn = $request->input('isbn');
        $googleId = $request->input('google_id');
        $isPrivate = $request->boolean('is_private', false);
        $readingStatus = $request->input('reading_status', 'read');

        // Try to find existing book by different identifiers
        $book = $this->findBookByIdentifiers($bookId, $isbn, $googleId);

        if ($book) {
            return $this->addBookToUserLibrary($book, $user, $enrichmentService, $googleId, $isPrivate, $readingStatus);
        }

        // If no book found and we have google_id, create new book
        if ($googleId) {
            return $this->createBookAndAddToLibrary($user, $enrichmentService, $googleId, $isPrivate, $readingStatus);
        }

        return response()->json(['message' => 'Book not found. Please provide book_id, isbn, or google_id.'], 404);
    }

    /**
     * @OA\Patch(
     *     path="/user/books/{book}",
     *     operationId="aUpdateUserBook",
     *     tags={"User Library"},
     *     summary="Update book in user's library",
     *     description="Updates book properties in the authenticated user's library (read date, privacy, etc.)",
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Parameter(
     *         name="book",
     *         in="path",
     *         description="Book ID",
     *         required=true,
     *
     *         @OA\Schema(type="string", example="B-1ABC-2DEF")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *         description="Book update data (provide any combination of fields)",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="read_at", type="string", format="date", example="2024-01-15", description="Date when the book was read (nullable to mark as unread)"),
     *             @OA\Property(property="is_private", type="boolean", example=true, description="Whether the book should be private"),
     *             @OA\Property(property="reading_status", type="string", enum={"want_to_read", "reading", "read", "abandoned", "on_hold", "re_reading"}, example="reading", description="Reading status for this book")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Book updated successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Book updated successfully"),
     *             @OA\Property(property="read_at", type="string", format="date", example="2024-01-15"),
     *             @OA\Property(property="is_private", type="boolean", example=true),
     *             @OA\Property(property="reading_status", type="string", example="reading")
     *         )
     *     ),
     *
     *     @OA\Response(response=404, description="Book not found in user's library"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function update(Request $request, Book $book)
    {
        $request->validate([
            'read_at' => 'nullable|date',
            'is_private' => 'nullable|boolean',
            'reading_status' => 'nullable|string|in:want_to_read,reading,read,abandoned,on_hold,re_reading',
        ]);

        $user = $request->user();

        // Check if book is in user's library
        if (! $user->books()->where('books.id', $book->id)->exists()) {
            return response()->json(['error' => 'Book not found in your library'], 404);
        }

        // Build update data based on provided fields
        $updateData = [];
        $responseData = ['message' => 'Book updated successfully'];

        if ($request->has('read_at')) {
            $readAt = $request->input('read_at');
            $updateData['read_at'] = $readAt ? \Carbon\Carbon::parse($readAt)->format('Y-m-d') : null;
            $responseData['read_at'] = $updateData['read_at'];
        }

        if ($request->has('is_private')) {
            $isPrivate = $request->boolean('is_private');
            $updateData['is_private'] = $isPrivate;
            $responseData['is_private'] = $isPrivate;
        }

        if ($request->has('reading_status')) {
            $readingStatus = $request->input('reading_status');
            $updateData['reading_status'] = $readingStatus;
            $responseData['reading_status'] = $readingStatus;

            // Auto-set read_at when status changes to 'read' and no read_at exists
            if ($readingStatus === 'read' && ! $request->has('read_at')) {
                $currentPivot = $user->books()->where('books.id', $book->id)->first()?->pivot;
                if ($currentPivot && ! $currentPivot->read_at) {
                    $updateData['read_at'] = now()->format('Y-m-d');
                    $responseData['read_at'] = $updateData['read_at'];
                }
            }
        }

        // Update the pivot table with provided data
        if (! empty($updateData)) {
            $user->books()->updateExistingPivot($book->id, $updateData);
        }

        return response()->json($responseData);
    }

    /**
     * @OA\Delete(
     *     path="/user/books/{book}",
     *     operationId="bRemoveBookFromLibrary",
     *     tags={"User Library"},
     *     summary="Remove book from user's library",
     *     description="Removes a book from the authenticated user's personal library",
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Parameter(
     *         name="book",
     *         in="path",
     *         description="Book ID",
     *         required=true,
     *
     *         @OA\Schema(type="string", example="B-1ABC-2DEF")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Book removed from library",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Book removed from your library")
     *         )
     *     ),
     *
     *     @OA\Response(response=404, description="Book not found"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function destroy(Request $request, Book $book)
    {
        $user = $request->user();
        $user->books()->detach($book->id);

        return response()->json(['message' => 'Book removed from your library']);
    }

    /**
     * Find existing book by different identifiers
     */
    private function findBookByIdentifiers(?string $bookId, ?string $isbn, ?string $googleId): ?Book
    {
        // Try book_id first (most direct)
        if ($bookId) {
            return Book::find($bookId);
        }

        // Try ISBN
        if ($isbn) {
            $book = Book::where('isbn', $isbn)->first();
            if ($book) {
                return $book;
            }
        }

        // Try Google ID
        if ($googleId) {
            return Book::where('google_id', $googleId)->first();
        }

        return null;
    }

    /**
     * Add existing book to user's library
     */
    private function addBookToUserLibrary(Book $book, $user, BookEnrichmentService $enrichmentService, ?string $googleId, bool $isPrivate = false, string $readingStatus = 'read'): JsonResponse
    {
        // Check if book is already in user's library
        if ($user->books()->where('books.id', $book->id)->exists()) {
            return response()->json([
                'book' => $book,
                'enriched' => false,
                'already_in_library' => true,
                'message' => 'Book is already in your library',
            ], 200);
        }

        $needsEnrichment = $this->shouldEnrichBook($book);

        if ($needsEnrichment) {
            $enrichmentResult = $enrichmentService->enrichBook($book, $googleId);
            if ($enrichmentResult['success']) {
                $book->refresh();
            }
        }

        // Add book to user's library
        $attachData = [
            'added_at' => now(),
            'is_private' => $isPrivate,
            'reading_status' => $readingStatus,
        ];

        // Auto-set read_at when status is 'read'
        if ($readingStatus === 'read') {
            $attachData['read_at'] = now()->format('Y-m-d');
        }

        $user->books()->attach($book->id, $attachData);

        // Reload book with pivot data
        $bookWithPivot = $user->books()->where('books.id', $book->id)->first();

        return response()->json([
            'book' => $bookWithPivot,
            'enriched' => $needsEnrichment,
            'already_in_library' => false,
            'message' => 'Book added to your library successfully',
        ], 201);
    }

    /**
     * Create new book and add to user's library
     */
    private function createBookAndAddToLibrary($user, BookEnrichmentService $enrichmentService, string $googleId, bool $isPrivate = false, string $readingStatus = 'read'): JsonResponse
    {
        // Create and enrich book from Google Books in one step
        $enrichmentResult = $enrichmentService->createEnrichedBookFromGoogle($googleId, $user->id, $isPrivate, $readingStatus);

        if (! $enrichmentResult['success']) {
            return response()->json([
                'message' => $enrichmentResult['message'] ?? 'Failed to create book from Google Books',
            ], 422);
        }

        $book = $enrichmentResult['book'];

        // Reload book with pivot data
        $bookWithPivot = $user->books()->where('books.id', $book->id)->first();

        return response()->json([
            'book' => $bookWithPivot,
            'enriched' => true,
            'already_in_library' => false,
            'message' => 'Book added to your library successfully',
        ], 201);
    }

    /**
     * Determines if a book should be enriched
     */
    private function shouldEnrichBook(Book $book): bool
    {
        // Enrich if:
        // 1. Never been enriched
        // 2. Has basic quality only
        // 3. Missing critical information like page count
        return $book->enriched_at === null ||
               $book->info_quality === 'basic' ||
               ($book->page_count === null && $book->google_id !== null);
    }

    /**
     * Parse published date from various formats
     */
    private function parsePublishedDate(string $dateString): ?\Carbon\Carbon
    {
        try {
            $result = null;

            if (preg_match('/^\d{4}$/', $dateString)) {
                // Year only (4 digits)
                $result = \Carbon\Carbon::createFromFormat('Y', $dateString)->startOfYear();
            } elseif (preg_match('/^\d{4}-\d{2}$/', $dateString)) {
                // Year and month (YYYY-MM)
                $result = \Carbon\Carbon::createFromFormat('Y-m', $dateString)->startOfMonth();
            } else {
                // Full date
                $result = \Carbon\Carbon::parse($dateString);
            }

            return $result;
        } catch (\Exception $e) {
            \Log::warning('Error parsing publication date', [
                'date_string' => $dateString,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
