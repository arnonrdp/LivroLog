<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserBookSimplifiedResource;
use App\Models\Book;
use App\Models\Review;
use App\Models\User;
use App\Services\AmazonLinkEnrichmentService;
use App\Services\UnifiedBookEnrichmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
     *     description="Returns all books in the authenticated user's personal library",
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(
     *         response=200,
     *         description="User's books library",
     *
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/Book"))
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

        $books = $user->books()
            ->withPivot('added_at', 'read_at', 'is_private', 'reading_status')
            ->get();

        return UserBookSimplifiedResource::collection($books);
    }

    /**
     * @OA\Get(
     *     path="/user/books/{book}",
     *     operationId="getUserBook",
     *     tags={"User Library"},
     *     summary="Get specific book from user's library",
     *     description="Returns a specific book from the authenticated user's library with pivot data (read dates, status, etc.) and reviews",
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
     *         description="Book details with pivot data and reviews",
     *
     *         @OA\JsonContent(ref="#/components/schemas/Book")
     *     ),
     *
     *     @OA\Response(response=404, description="Book not found in user's library"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function show(Request $request, Book $book, AmazonLinkEnrichmentService $amazonService)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Get book with pivot data from user's library
        $userBook = $user->books()
            ->withPivot('added_at', 'read_at', 'is_private', 'reading_status')
            ->where('books.id', $book->id)
            ->with(['reviews' => function ($query) {
                $query->with('user:id,display_name,username,avatar');
            }])
            ->first();

        if (! $userBook) {
            return response()->json(['error' => 'Book not found in your library'], 404);
        }

        // Convert to array and enrich with Amazon links
        $bookData = $userBook->toArray();
        $enrichedBooks = $amazonService->enrichBooksWithAmazonLinks(
            [$bookData],
            ['locale' => $request->header('Accept-Language', 'en-US')]
        );

        return response()->json($enrichedBooks[0]);
    }

    /**
     * @OA\Post(
     *     path="/user/books",
     *     operationId="addBookToLibrary",
     *     tags={"User Library"},
     *     summary="Add book to user's personal library",
     *     description="Adds a book to the authenticated user's personal library using book identifiers or full book data. Priority: book_id > isbn > google_id > amazon_asin. If book doesn't exist: with google_id creates and enriches from Google Books; with title+isbn/amazon_asin creates from Amazon data.",
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *         description="Book identifiers or full book data - provide at least one identifier or title+isbn/amazon_asin",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="book_id", type="string", example="B-1ABC-2DEF", description="Book ID if already exists in system"),
     *             @OA\Property(property="isbn", type="string", example="9781505108293", description="ISBN to search for existing book or create new"),
     *             @OA\Property(property="google_id", type="string", example="HuKNDAAAQBAJ", description="Google Books ID for search/creation and enrichment"),
     *             @OA\Property(property="amazon_asin", type="string", example="B00EXAMPLE", description="Amazon ASIN to search for existing book or create new"),
     *             @OA\Property(property="title", type="string", example="Book Title", description="Book title (required when creating from Amazon data)"),
     *             @OA\Property(property="authors", type="string", example="Author Name", description="Book authors (optional, for Amazon data)"),
     *             @OA\Property(property="thumbnail", type="string", format="url", example="https://example.com/cover.jpg", description="Book cover URL (optional, for Amazon data)"),
     *             @OA\Property(property="description", type="string", example="Book description", description="Book description (optional, for Amazon data)"),
     *             @OA\Property(property="publisher", type="string", example="Publisher Name", description="Publisher name (optional, for Amazon data)"),
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
    public function store(Request $request, UnifiedBookEnrichmentService $unifiedEnrichmentService)
    {
        $request->validate([
            'book_id' => 'nullable|string|exists:books,id',
            'isbn' => self::VALIDATION_NULLABLE_STRING.'|max:20',
            'google_id' => self::VALIDATION_NULLABLE_STRING,
            'amazon_asin' => self::VALIDATION_NULLABLE_STRING.'|max:20',
            'title' => self::VALIDATION_NULLABLE_STRING.'|max:255',
            'authors' => self::VALIDATION_NULLABLE_STRING,
            'thumbnail' => 'nullable|url|max:512',
            'description' => self::VALIDATION_NULLABLE_STRING,
            'publisher' => self::VALIDATION_NULLABLE_STRING.'|max:255',
            'is_private' => 'boolean',
            'reading_status' => 'nullable|string|in:want_to_read,reading,read,abandoned,on_hold,re_reading',
        ]);

        $user = $request->user();
        $bookId = $request->input('book_id');
        $isbn = $request->input('isbn');
        $googleId = $request->input('google_id');
        $amazonAsin = $request->input('amazon_asin');
        $isPrivate = $request->boolean('is_private', false);
        $readingStatus = $request->input('reading_status', 'read');

        // Try to find existing book by different identifiers
        $book = $this->findBookByIdentifiers($bookId, $isbn, $googleId, $amazonAsin);

        if ($book) {
            return $this->addBookToUserLibrary($book, $user, $unifiedEnrichmentService, $googleId, $isPrivate, $readingStatus);
        }

        // If no book found and we have google_id, create new book with enrichment
        if ($googleId) {
            return $this->createBookAndAddToLibrary($user, $unifiedEnrichmentService, $googleId, $isPrivate, $readingStatus);
        }

        // If no book found but we have ISBN only, try to create a basic book
        if ($isbn && ! $request->has('title')) {
            return $this->createBookFromIsbnOnly($user, $isbn, $isPrivate, $readingStatus);
        }

        // If no book found but we have basic book data (from Amazon search), create book manually
        if ($request->has('title') && ($isbn || $amazonAsin)) {
            return $this->createBookFromBasicDataAndAddToLibrary($user, $request, $isPrivate, $readingStatus);
        }

        return response()->json(['message' => 'Book not found. Please provide book_id, isbn, google_id, or book details (title + isbn/amazon_asin).'], 404);
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
     * @OA\Put(
     *     path="/user/books/{book}/replace",
     *     operationId="replaceUserBook",
     *     tags={"User Library"},
     *     summary="Replace book in user's library",
     *     description="Replaces a book in the authenticated user's library with another book, preserving reading data and migrating reviews",
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Parameter(
     *         name="book",
     *         in="path",
     *         description="Current Book ID to be replaced",
     *         required=true,
     *
     *         @OA\Schema(type="string", example="B-1ABC-2DEF")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *         description="New book to replace with",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="new_book_id", type="string", example="B-3XYZ-4WVU", description="ID of the book to replace with")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Book replaced successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Livro substituído com sucesso"),
     *             @OA\Property(property="book", ref="#/components/schemas/Book")
     *         )
     *     ),
     *
     *     @OA\Response(response=404, description="Book not found in user's library or new book doesn't exist"),
     *     @OA\Response(response=409, description="New book already in user's library"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function replaceBook(Request $request, Book $book, AmazonLinkEnrichmentService $amazonService): JsonResponse
    {
        $request->validate([
            'new_book_id' => 'nullable|string|exists:books,id',
            'amazon_asin' => self::VALIDATION_NULLABLE_STRING.'|max:20',
            'title' => self::VALIDATION_NULLABLE_STRING.'|max:255',
            'authors' => self::VALIDATION_NULLABLE_STRING,
            'thumbnail' => 'nullable|url|max:512',
            'description' => self::VALIDATION_NULLABLE_STRING,
            'publisher' => self::VALIDATION_NULLABLE_STRING.'|max:255',
        ]);

        $user = $request->user();

        // Try to find or create the new book
        $newBookId = $request->input('new_book_id');

        // If no new_book_id, try to find/create by ASIN or create from Amazon data
        if (!$newBookId) {
            $amazonAsin = $request->input('amazon_asin');
            $title = $request->input('title');

            if (!$amazonAsin && !$title) {
                return response()->json([
                    'message' => 'Either new_book_id or amazon_asin/title is required',
                ], 422);
            }

            // Try to find existing book by ASIN
            if ($amazonAsin) {
                $existingBook = Book::where('amazon_asin', $amazonAsin)->first();
                if ($existingBook) {
                    $newBookId = $existingBook->id;
                }
            }

            // If still no book found, create from Amazon data
            if (!$newBookId && $title) {
                $newBook = Book::create([
                    'amazon_asin' => $amazonAsin,
                    'title' => $title,
                    'authors' => $request->input('authors'),
                    'thumbnail' => $request->input('thumbnail'),
                    'description' => $request->input('description'),
                    'publisher' => $request->input('publisher'),
                    'info_quality' => 'basic',
                    'asin_status' => $amazonAsin ? 'completed' : 'pending',
                ]);
                $newBookId = $newBook->id;

                Log::info('New book created during replacement', [
                    'book_id' => $newBookId,
                    'amazon_asin' => $amazonAsin,
                    'title' => $title,
                ]);
            }
        }

        DB::beginTransaction();
        try {
            // 1. Validate: original book is in library
            $userBook = DB::table('users_books')
                ->where('user_id', $user->id)
                ->where('book_id', $book->id)
                ->first();

            if (! $userBook) {
                DB::rollBack();

                return response()->json([
                    'message' => 'Livro não encontrado na sua estante',
                ], 404);
            }

            // 2. Validate: new book is NOT in library
            $existingNewBook = DB::table('users_books')
                ->where('user_id', $user->id)
                ->where('book_id', $newBookId)
                ->exists();

            if ($existingNewBook) {
                DB::rollBack();

                return response()->json([
                    'message' => 'Este livro já está na sua estante',
                ], 409);
            }

            // 3. Update users_books (swap book_id)
            DB::table('users_books')
                ->where('user_id', $user->id)
                ->where('book_id', $book->id)
                ->update([
                    'book_id' => $newBookId,
                    'updated_at' => now(),
                ]);

            // 4. Migrate review (if exists)
            $review = Review::where('user_id', $user->id)
                ->where('book_id', $book->id)
                ->first();

            if ($review) {
                // Check if review already exists for destination book
                $existingReview = Review::where('user_id', $user->id)
                    ->where('book_id', $newBookId)
                    ->exists();

                if ($existingReview) {
                    // Conflict: user already has review on destination
                    // Delete old review
                    $review->delete();
                    Log::info('Review deleted during book replacement', [
                        'user_id' => $user->id,
                        'old_book' => $book->id,
                        'new_book' => $newBookId,
                    ]);
                } else {
                    // Migrate review
                    $review->update(['book_id' => $newBookId]);
                    Log::info('Review migrated during book replacement', [
                        'user_id' => $user->id,
                        'review_id' => $review->id,
                        'from' => $book->id,
                        'to' => $newBookId,
                    ]);
                }
            }

            DB::commit();

            // 5. Reload book with pivot and reviews
            $newBook = Book::with(['reviews' => function ($query) {
                $query->with('user:id,display_name,username,avatar');
            }])
                ->where('id', $newBookId)
                ->first();

            // Add pivot data
            $userBookData = $user->books()
                ->withPivot('added_at', 'read_at', 'is_private', 'reading_status')
                ->where('books.id', $newBookId)
                ->first();

            if ($userBookData) {
                $newBook->pivot = $userBookData->pivot;
            }

            // Enrich with Amazon links
            $bookData = $newBook->toArray();
            $enrichedBooks = $amazonService->enrichBooksWithAmazonLinks(
                [$bookData],
                ['locale' => $request->header('Accept-Language', 'en-US')]
            );

            return response()->json([
                'success' => true,
                'message' => 'Livro substituído com sucesso',
                'book' => $enrichedBooks[0],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error replacing book', [
                'user_id' => $user->id,
                'old_book' => $book->id,
                'new_book' => $newBookId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Erro ao substituir livro',
            ], 500);
        }
    }

    /**
     * Find existing book by different identifiers
     */
    private function findBookByIdentifiers(?string $bookId, ?string $isbn, ?string $googleId, ?string $amazonAsin = null): ?Book
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
            $book = Book::where('google_id', $googleId)->first();
            if ($book) {
                return $book;
            }
        }

        // Try Amazon ASIN
        if ($amazonAsin) {
            return Book::where('amazon_asin', $amazonAsin)->first();
        }

        return null;
    }

    /**
     * Add existing book to user's library
     */
    private function addBookToUserLibrary(Book $book, $user, UnifiedBookEnrichmentService $unifiedEnrichmentService, ?string $googleId, bool $isPrivate = false, string $readingStatus = 'read'): JsonResponse
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
            $enrichmentResult = $unifiedEnrichmentService->enrichBook($book, $googleId);
            if ($enrichmentResult['google_success']) {
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
    private function createBookAndAddToLibrary($user, UnifiedBookEnrichmentService $unifiedEnrichmentService, string $googleId, bool $isPrivate = false, string $readingStatus = 'read'): JsonResponse
    {
        // Create and enrich book from Google Books in one step
        $enrichmentResult = $unifiedEnrichmentService->createEnrichedBookFromGoogle($googleId, $user->id, $isPrivate, $readingStatus);

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
     * Create new book from ISBN only and add to user's library
     */
    private function createBookFromIsbnOnly($user, string $isbn, bool $isPrivate = false, string $readingStatus = 'read'): JsonResponse
    {
        // First, try to get book data from external sources (Amazon/Google Books)
        $bookData = $this->fetchBookDataFromExternalSources($isbn);

        // Create book with available data
        $book = Book::create($bookData);

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

        // If we have minimal data, try to enrich asynchronously
        if ($book->info_quality === 'basic') {
            try {
                $unifiedEnrichmentService = app(UnifiedBookEnrichmentService::class);
                $unifiedEnrichmentService->enrichBook($book);
            } catch (\Exception $e) {
                \Log::warning('Failed to enrich book after creation', [
                    'book_id' => $book->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Reload book with pivot data
        $bookWithPivot = $user->books()->where('books.id', $book->id)->first();

        return response()->json([
            'book' => $bookWithPivot,
            'enriched' => $book->info_quality !== 'basic',
            'already_in_library' => false,
            'message' => 'Book added to your library successfully',
        ], 201);
    }

    /**
     * Fetch book data from external sources (Amazon/Google Books)
     */
    private function fetchBookDataFromExternalSources(string $isbn): array
    {
        try {
            // Use the HybridBookSearchService to search for the book
            $searchService = app(\App\Services\HybridBookSearchService::class);
            $results = $searchService->search('isbn:'.$isbn, ['per_page' => 1]);

            if (! empty($results['data'])) {
                $externalBook = $results['data'][0];

                // If we have rich data from external source, use it
                if (! empty($externalBook['title']) && $externalBook['title'] !== 'Book - '.$isbn) {
                    return [
                        'isbn' => $isbn,
                        'title' => $externalBook['title'],
                        'authors' => $externalBook['authors'] ?? null,
                        'thumbnail' => $externalBook['thumbnail'] ?? null,
                        'description' => $externalBook['description'] ?? null,
                        'amazon_asin' => $externalBook['amazon_asin'] ?? null,
                        'google_id' => $externalBook['google_id'] ?? null,
                        'publisher' => $externalBook['publisher'] ?? null,
                        'info_quality' => 'enhanced',
                        'asin_status' => ! empty($externalBook['amazon_asin']) ? 'completed' : 'pending',
                    ];
                }
            }
        } catch (\Exception $e) {
            \Log::warning('Failed to fetch book data from external sources', [
                'isbn' => $isbn,
                'error' => $e->getMessage(),
            ]);
        }

        // Fallback to minimal book data
        return [
            'isbn' => $isbn,
            'title' => 'Book - '.$isbn,
            'info_quality' => 'basic',
        ];
    }

    /**
     * @OA\Get(
     *     path="/users/{user}/books/{book}",
     *     operationId="getSpecificUserBook",
     *     tags={"User Library"},
     *     summary="Get specific book from a user's library",
     *     description="Returns a specific book from a user's library with their pivot data (read dates, status, etc.) and reviews. Respects privacy settings.",
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Parameter(
     *         name="user",
     *         in="path",
     *         description="User ID or username",
     *         required=true,
     *
     *         @OA\Schema(type="string", example="U-1ABC-2DEF")
     *     ),
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
     *         description="Book details with user's pivot data and reviews",
     *
     *         @OA\JsonContent(ref="#/components/schemas/Book")
     *     ),
     *
     *     @OA\Response(response=404, description="Book not found in user's library or user not found"),
     *     @OA\Response(response=403, description="Access denied - private profile"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function showUserBook(Request $request, string $userIdentifier, Book $book, AmazonLinkEnrichmentService $amazonService)
    {
        $currentUser = $request->user();

        // Find the target user by ID or username
        $targetUser = str_starts_with($userIdentifier, 'U-')
            ? User::findOrFail($userIdentifier)
            : User::where('username', $userIdentifier)->firstOrFail();

        // Check if current user can access this user's library
        $isOwner = $currentUser && $currentUser->id === $targetUser->id;
        $isFollowing = false;

        if ($currentUser && ! $isOwner) {
            $isFollowing = $currentUser->followingRelationships()
                ->where('followed_id', $targetUser->id)
                ->where('status', 'accepted')
                ->exists();
        }

        // Check privacy access
        if ($targetUser->is_private && ! $isOwner && ! $isFollowing) {
            return response()->json(['error' => 'Access denied to private profile'], 403);
        }

        // Get book with pivot data from target user's library
        $userBook = $targetUser->books()
            ->withPivot('added_at', 'read_at', 'is_private', 'reading_status')
            ->where('books.id', $book->id)
            ->with(['reviews' => function ($query) {
                $query->with('user:id,display_name,username,avatar');
            }])
            ->first();

        if (! $userBook) {
            return response()->json(['error' => 'Book not found in user\'s library'], 404);
        }

        // Filter private books (only owner can see their private books)
        if (! $isOwner && $userBook->pivot->is_private) {
            return response()->json(['error' => 'Book is private'], 403);
        }

        // Convert to array and enrich with Amazon links
        $bookData = $userBook->toArray();
        $enrichedBooks = $amazonService->enrichBooksWithAmazonLinks(
            [$bookData],
            ['locale' => $request->header('Accept-Language', 'en-US')]
        );

        return response()->json($enrichedBooks[0]);
    }

    /**
     * Create new book from basic data (Amazon search results) and add to user's library
     */
    private function createBookFromBasicDataAndAddToLibrary($user, Request $request, bool $isPrivate = false, string $readingStatus = 'read'): JsonResponse
    {
        // Create book with basic data from Amazon
        $bookData = [
            'title' => $request->input('title'),
            'authors' => $request->input('authors'),
            'isbn' => $request->input('isbn'),
            'amazon_asin' => $request->input('amazon_asin'),
            'thumbnail' => $request->input('thumbnail'),
            'description' => $request->input('description'),
            'publisher' => $request->input('publisher'),
            'info_quality' => 'basic', // Mark as basic since it's from Amazon without enrichment
            'asin_status' => 'completed', // Already have ASIN from Amazon
        ];

        // Remove null values
        $bookData = array_filter($bookData, fn ($value) => $value !== null);

        // Create the book
        $book = Book::create($bookData);

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
            'enriched' => false,
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
}
