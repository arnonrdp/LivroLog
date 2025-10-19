<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Services\AmazonLinkEnrichmentService;
use App\Services\HybridBookSearchService;
use App\Services\UnifiedBookEnrichmentService;
use App\Transformers\BookTransformer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BookController extends Controller
{
    // Validation constants to avoid duplication
    private const VALIDATION_NULLABLE_STRING = 'nullable|string';

    /**
     *     @OA\Get(
     *     path="/books",
     *     operationId="getBooks",
     *     tags={"Books"},
     *     summary="List books, search external APIs, or get showcase",
     *     description="Returns books from global catalog, external search results, or featured books based on parameters",
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
     *         name="per_page",
     *         in="query",
     *         description="Items per page (max 100 for catalog, max 40 for search)",
     *         required=false,
     *
     *         @OA\Schema(type="integer", example=20)
     *     ),
     *
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search term for Amazon Books",
     *         required=false,
     *
     *         @OA\Schema(type="string", example="Sidarta")
     *     ),
     *
     *     @OA\Parameter(
     *         name="sort_by",
     *         in="query",
     *         description="Sort order for catalog results (not applicable for search)",
     *         required=false,
     *
     *         @OA\Schema(type="string", enum={"recent", "rating", "popular"}, example="recent")
     *     ),
     *
     *     @OA\Parameter(
     *         name="with",
     *         in="query",
     *         description="Include additional fields in response (comma-separated)",
     *         required=false,
     *
     *         @OA\Schema(type="string", example="details,users,reviews")
     *     ),
     *
     *     @OA\Parameter(
     *         name="showcase",
     *         in="query",
     *         description="Return featured books (most popular)",
     *         required=false,
     *
     *         @OA\Schema(type="boolean", example=true)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Books list (catalog/search results/showcase)",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Book")),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="from", type="integer", example=1),
     *                 @OA\Property(property="last_page", type="integer", example=5),
     *                 @OA\Property(property="per_page", type="integer", example=20),
     *                 @OA\Property(property="to", type="integer", example=20),
     *                 @OA\Property(property="total", type="integer", example=100)
     *             )
     *         )
     *     )
     * )
     */
    public function index(Request $request, HybridBookSearchService $hybridSearchService)
    {
        // Parse 'with' parameter for field inclusion
        $includes = BookTransformer::parseIncludes($request->input('with'));
        $transformer = new BookTransformer;

        // Handle search parameter - hybrid search (local + external)
        if ($request->has('search')) {
            $searchQuery = $request->input('search');
            $perPage = min($request->get('per_page', 20), 40); // Max 40 for search

            // Get locale from Accept-Language header for proper Amazon region
            $locale = $this->getLocaleFromRequest($request);

            $result = $hybridSearchService->search($searchQuery, [
                'maxResults' => $perPage,
                'includes' => $includes,
                'locale' => $locale,
            ]);

            return response()->json($result);
        }

        // Default behavior - global catalog with sorting
        $query = Book::query();

        // Handle sorting
        $sortBy = $request->get('sort_by', 'recent');
        switch ($sortBy) {
            case 'rating':
                // Sort by average rating (requires reviews relationship)
                $query->withAvg('reviews', 'rating')
                    ->orderByDesc('reviews_avg_rating')
                    ->orderByDesc('created_at');
                break;

            case 'popular':
                // Sort by library count (how many users have this book)
                $query->withCount('users as library_count')
                    ->orderByDesc('library_count')
                    ->orderByDesc('created_at');
                break;

            case 'recent':
            default:
                // Sort by recently added
                $query->orderByDesc('created_at');
                break;
        }

        // Paginate with configurable per_page parameter (default 20, max 100)
        $perPage = min($request->get('per_page', 20), 100);
        $paginated = $query->paginate($perPage);

        // Transform the books data
        $transformedBooks = $transformer->transform($paginated->items(), $includes);

        // Build response with meta
        return response()->json([
            'data' => $transformedBooks,
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'from' => $paginated->firstItem(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'to' => $paginated->lastItem(),
                'total' => $paginated->total(),
            ],
        ]);
    }

    /**
     * @OA\Post(
     *     path="/books",
     *     operationId="storeBook",
     *     tags={"Books"},
     *     summary="Create new book in global catalog",
     *     description="Creates a new book in the global books table. This is for administrative purposes and does not add the book to any user's library. Use POST /user/books to add books to personal libraries.",
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="google_id", type="string", example="iO5pApw2JycC", description="Google Books ID for enrichment"),
     *             @OA\Property(property="title", type="string", example="The Ivory Tower and Harry Potter"),
     *             @OA\Property(property="authors", type="string", example="Lana A. Whited"),
     *             @OA\Property(property="isbn", type="string", example="0826215491"),
     *             @OA\Property(property="thumbnail", type="string", format="url"),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="publisher", type="string", example="University of Missouri Press"),
     *             @OA\Property(property="language", type="string", example="en"),
     *             @OA\Property(property="published_date", type="string", format="date"),
     *             @OA\Property(property="page_count", type="integer", example=324),
     *             @OA\Property(property="format", type="string", enum={"hardcover", "paperback", "ebook", "audiobook"}),
     *             @OA\Property(property="categories", type="array", @OA\Items(type="string")),
     *             @OA\Property(property="edition", type="string")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Book created successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="book", ref="#/components/schemas/Book"),
     *             @OA\Property(property="enriched", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Book created in global catalog")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Book already exists",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="book", ref="#/components/schemas/Book"),
     *             @OA\Property(property="enriched", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Book already exists in global catalog")
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     * Store a newly created resource in storage.
     */
    public function store(Request $request, UnifiedBookEnrichmentService $unifiedEnrichmentService)
    {
        $request->validate([
            'google_id' => self::VALIDATION_NULLABLE_STRING,
            'title' => 'required|string|max:255',
            'isbn' => self::VALIDATION_NULLABLE_STRING.'|max:20',
            'authors' => self::VALIDATION_NULLABLE_STRING,
            'thumbnail' => ['nullable', 'url', 'max:512', function ($attribute, $value, $fail) {
                if (! $this->isAllowedThumbnailDomain($value)) {
                    $fail('The thumbnail URL domain is not allowed.');
                }
            }],
            'description' => self::VALIDATION_NULLABLE_STRING,
            'language' => self::VALIDATION_NULLABLE_STRING.'|max:10',
            'publisher' => self::VALIDATION_NULLABLE_STRING.'|max:255',
            'published_date' => ['nullable', function ($attribute, $value, $fail) {
                if ($value === null) {
                    return;
                }

                // Accept year only (4 digits)
                if (preg_match('/^\d{4}$/', $value)) {
                    return;
                }

                // Accept year-month (YYYY-MM)
                if (preg_match('/^\d{4}-\d{2}$/', $value)) {
                    return;
                }

                // Accept full date formats
                try {
                    \Carbon\Carbon::parse($value);

                    return;
                } catch (\Exception $e) {
                    $fail('The '.$attribute.' field must be a valid date, year (YYYY), or year-month (YYYY-MM).');
                }
            }],
            'page_count' => 'nullable|integer|min:1',
            'format' => 'nullable|string|in:hardcover,paperback,ebook,audiobook',
            'categories' => 'nullable|array',
            'edition' => 'nullable|string|max:50',
        ]);

        $isbn = $request->input('isbn');
        $googleId = $request->input('google_id');

        // Check if book already exists
        $book = $this->findExistingBook($isbn, $googleId);

        if ($book) {
            // Book exists, enrich if needed
            $needsEnrichment = $this->shouldEnrichBook($book);

            if ($needsEnrichment && $googleId) {
                $enrichmentResult = $unifiedEnrichmentService->enrichBook($book, $googleId);
                if ($enrichmentResult['google_success']) {
                    $book->refresh();
                }
            }

            return response()->json([
                'book' => $book,
                'enriched' => $needsEnrichment,
                'message' => 'Book already exists in global catalog',
            ], 200);
        }

        // Create new book
        $bookData = $request->all();
        if (! empty($bookData['published_date'])) {
            $bookData['published_date'] = $this->parsePublishedDate($bookData['published_date']);
        }

        $book = Book::create($bookData);

        // Enrich book if Google ID is provided
        $enrichmentResult = null;
        if ($googleId) {
            $enrichmentResult = $unifiedEnrichmentService->enrichBook($book, $googleId);
            if ($enrichmentResult['google_success']) {
                $book->refresh();
            }
        }

        return response()->json([
            'book' => $book,
            'enriched' => $enrichmentResult ? $enrichmentResult['google_success'] : false,
            'message' => 'Book created in global catalog',
        ], 201);
    }

    /**
     * Find existing book by ISBN or Google ID
     */
    private function findExistingBook(?string $isbn, ?string $googleId): ?Book
    {
        if ($isbn) {
            $book = Book::where('isbn', $isbn)->first();
            if ($book) {
                return $book;
            }
        }

        if ($googleId) {
            return Book::where('google_id', $googleId)->first();
        }

        return null;
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
     * @OA\Get(
     *     path="/books/{id}",
     *     operationId="getBook",
     *     tags={"Books"},
     *     summary="Get book by ID with contextual data",
     *     description="Returns detailed information about a specific book with optional user-specific data using with[] pattern",
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Book ID",
     *         required=true,
     *
     *         @OA\Schema(type="string", example="B-1ABC-2DEF")
     *     ),
     *
     *     @OA\Parameter(
     *         name="with[]",
     *         in="query",
     *         description="Include additional data: pivot (user library data), reviews, details",
     *         required=false,
     *         style="form",
     *         explode=true,
     *
     *         @OA\Schema(type="array", @OA\Items(type="string", enum={"pivot", "reviews", "details"}))
     *     ),
     *
     *     @OA\Parameter(
     *         name="user_id",
     *         in="query",
     *         description="User ID to get pivot data for (used with with[]=pivot)",
     *         required=false,
     *
     *         @OA\Schema(type="string", example="U-1ABC-2DEF")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Book information with contextual data",
     *
     *         @OA\JsonContent(
     *             allOf={
     *
     *                 @OA\Schema(ref="#/components/schemas/Book"),
     *                 @OA\Schema(
     *
     *                     @OA\Property(property="pivot", type="object", nullable=true,
     *                         @OA\Property(property="added_at", type="string", format="date-time"),
     *                         @OA\Property(property="read_at", type="string", format="date", nullable=true),
     *                         @OA\Property(property="is_private", type="boolean"),
     *                         @OA\Property(property="reading_status", type="string")
     *                     ),
     *                     @OA\Property(property="reviews", type="array", @OA\Items(type="object"))
     *                 )
     *             }
     *         )
     *     ),
     *
     *     @OA\Response(response=404, description="Book not found"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function show(Request $request, string $id)
    {
        $includes = $this->parseWithParameter($request->input('with', []));
        $transformer = new BookTransformer;

        // Build dynamic relationships based on includes
        $with = [];

        if (in_array('reviews', $includes)) {
            $with['reviews'] = function ($query) {
                $query->with('user')->latest()->limit(20);
            };
        }

        // Load related books for details
        if (in_array('details', $includes)) {
            $with['relatedBooks'] = function ($query) {
                $query->limit(10);
            };
        }

        $book = Book::with($with)->findOrFail($id);

        // Always include details for single book view
        if (! in_array('details', $includes)) {
            $includes[] = 'details';
        }

        $transformedBook = $transformer->transform($book, $includes);

        // Include reviews in the response if requested
        if (in_array('reviews', $includes)) {
            $transformedBook['reviews'] = $this->transformReviews($book->reviews ?? collect());
        }

        // Include pivot data if requested
        if (in_array('pivot', $includes)) {
            $pivotData = $this->getPivotData($book, $request);
            $transformedBook['pivot'] = $pivotData;
        }

        return response()->json($transformedBook);
    }

    /**
     * @OA\Put(
     *     path="/books/{id}",
     *     operationId="updateBook",
     *     tags={"Books"},
     *     summary="Update book information",
     *     description="Updates an existing book's information",
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Book ID",
     *         required=true,
     *
     *         @OA\Schema(type="string", example="B-1ABC-2DEF")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *         description="Book data to update",
     *
     *         @OA\JsonContent(
     *             required={"title"},
     *
     *             @OA\Property(property="title", type="string", example="Updated Book Title"),
     *             @OA\Property(property="isbn", type="string", example="1234567890"),
     *             @OA\Property(property="authors", type="string", example="Author Name"),
     *             @OA\Property(property="thumbnail", type="string", format="url"),
     *             @OA\Property(property="language", type="string", example="en"),
     *             @OA\Property(property="publisher", type="string", example="Publisher Name"),
     *             @OA\Property(property="edition", type="string", example="1st Edition")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Book updated successfully",
     *
     *         @OA\JsonContent(ref="#/components/schemas/Book")
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *
     *     @OA\Response(response=404, description="Book not found"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function update(Request $request, string $id)
    {
        $book = Book::findOrFail($id);

        $request->validate([
            'title' => 'required|string|max:255',
            'isbn' => 'nullable|string|max:20|unique:books,isbn,'.$book->id,
            'authors' => self::VALIDATION_NULLABLE_STRING,
            'thumbnail' => ['nullable', 'url', 'max:512', function ($attribute, $value, $fail) {
                if (! $this->isAllowedThumbnailDomain($value)) {
                    $fail('The thumbnail URL domain is not allowed.');
                }
            }],
            'language' => self::VALIDATION_NULLABLE_STRING.'|max:10',
            'publisher' => self::VALIDATION_NULLABLE_STRING.'|max:255',
            'edition' => 'nullable|string|max:50',
        ]);

        $book->update($request->all());

        return response()->json($book);
    }

    /**
     * Validate thumbnail URL is on allowed domains to reduce SSRF risk
     */
    private function isAllowedThumbnailDomain(string $url): bool
    {
        $allowed = [
            'books.google.com',
            'books.googleapis.com',
            'lh3.googleusercontent.com',
            'ssl.gstatic.com',
            'covers.openlibrary.org',
        ];

        $parsed = parse_url($url);
        if (! isset($parsed['host']) || ! isset($parsed['scheme'])) {
            return false;
        }

        $host = strtolower($parsed['host']);
        $scheme = strtolower($parsed['scheme']);
        if (! in_array($scheme, ['http', 'https'], true)) {
            return false;
        }

        foreach ($allowed as $domain) {
            if ($host === $domain || str_ends_with($host, '.'.$domain)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @OA\Delete(
     *     path="/books/{id}",
     *     operationId="deleteBook",
     *     tags={"Books"},
     *     summary="Delete book from global catalog",
     *     description="Permanently deletes a book from the global books table. This will also remove it from all users' libraries. Use DELETE /user/books/{book} to remove from personal library only.",
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Book ID",
     *         required=true,
     *
     *         @OA\Schema(type="string", example="B-1ABC-2DEF")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Book deleted from global catalog",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Book deleted from global catalog")
     *         )
     *     ),
     *
     *     @OA\Response(response=404, description="Book not found"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function destroy(string $id)
    {
        $book = Book::findOrFail($id);

        // This will also cascade delete from users_books pivot table
        $book->delete();

        return response()->json(['message' => 'Book deleted from global catalog']);
    }

    /**
     * Fetch data from Google Books API
     */

    /**
     * Transform a single Google Book item to our format
     */
    private function transformGoogleBookItem(array $item): array
    {
        $volumeInfo = $item['volumeInfo'] ?? [];
        $isbn = $this->extractIsbnFromItem($volumeInfo);

        return [
            'google_id' => $item['id'],
            'title' => $volumeInfo['title'] ?? '',
            'subtitle' => $volumeInfo['subtitle'] ?? null,
            'authors' => isset($volumeInfo['authors']) ? implode(', ', $volumeInfo['authors']) : '',
            'isbn' => $isbn ?: $item['id'],
            'thumbnail' => $this->getSecureThumbnailUrl($volumeInfo),
            'description' => $volumeInfo['description'] ?? '',
            'publisher' => $volumeInfo['publisher'] ?? '',
            'published_date' => $volumeInfo['publishedDate'] ?? null,
            'page_count' => $volumeInfo['pageCount'] ?? null,
            'language' => $volumeInfo['language'] ?? null, // Let API determine language
            'categories' => $volumeInfo['categories'] ?? null,
        ];
    }

    /**
     * Extract ISBN from Google Book volume info
     */
    private function extractIsbnFromItem(array $volumeInfo): string
    {
        $isbn = '';

        if (isset($volumeInfo['industryIdentifiers'])) {
            foreach ($volumeInfo['industryIdentifiers'] as $identifier) {
                if (in_array($identifier['type'], ['ISBN_13', 'ISBN_10'])) {
                    $isbn = $identifier['identifier'];
                    break;
                }
            }
        }

        return $isbn;
    }

    /**
     * Get secure thumbnail URL
     */
    private function getSecureThumbnailUrl(array $volumeInfo): ?string
    {
        $thumbnail = $volumeInfo['imageLinks']['thumbnail'] ?? null;

        if ($thumbnail) {
            return str_replace('http:', 'https:', $thumbnail);
        }

        return null;
    }

    /**
     * @OA\Post(
     *     path="/books/{id}/enrich",
     *     operationId="enrichBook",
     *     tags={"Books"},
     *     summary="Enrich book(s) information using Google Books API",
     *     description="Fetches additional information about a book (or multiple books when batch=true) from Google Books API and updates the local record(s)",
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Book ID (not used when batch=true)",
     *         required=false,
     *
     *         @OA\Schema(type="string", example="B-3D6Y-9IO8")
     *     ),
     *
     *     @OA\Parameter(
     *         name="batch",
     *         in="query",
     *         description="Enable batch enrichment mode",
     *         required=false,
     *
     *         @OA\Schema(type="boolean", example=true)
     *     ),
     *
     *     @OA\RequestBody(
     *         required=false,
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="google_id", type="string", example="8fcQEAAAQBAJ", description="Optional Google Books ID to use for enrichment (single book)"),
     *             @OA\Property(property="book_ids", type="array", @OA\Items(type="string"), description="Array of book IDs for batch enrichment (when batch=true)")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Book enriched successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Book enriched successfully"),
     *             @OA\Property(property="book_id", type="string", example="B-3D6Y-9IO8"),
     *             @OA\Property(property="added_fields", type="array", @OA\Items(type="string"))
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Book not found"),
     *     @OA\Response(response=422, description="Enrichment failed")
     * )
     */
    public function enrichBook(Request $request, ?Book $book, UnifiedBookEnrichmentService $unifiedEnrichmentService)
    {
        // Handle batch enrichment
        if ($request->boolean('batch')) {
            $request->validate([
                'book_ids' => 'nullable|array',
                'book_ids.*' => 'string|exists:books,id',
            ]);

            $result = $unifiedEnrichmentService->enrichBooksInBatch($request->input('book_ids'));

            return response()->json($result);
        }

        // Handle single book enrichment
        $request->validate([
            'google_id' => self::VALIDATION_NULLABLE_STRING,
        ]);

        $result = $unifiedEnrichmentService->enrichBook($book, $request->input('google_id'));

        if ($result['google_success']) {
            return response()->json($result);
        } else {
            return response()->json($result, 422);
        }
    }

    /**
     * Transform reviews data for API response
     */
    private function transformReviews($reviews): array
    {
        return $reviews->map(function ($review) {
            return [
                'id' => $review->id,
                'user_id' => $review->user_id,
                'rating' => $review->rating,
                'content' => $review->content,
                'helpful_count' => $review->helpful_count ?? 0,
                'created_at' => $review->created_at?->toISOString(),
                'updated_at' => $review->updated_at?->toISOString(),
                'user' => [
                    'id' => $review->user->id,
                    'display_name' => $review->user->display_name,
                    'username' => $review->user->username,
                    'avatar' => $review->user->avatar,
                ],
            ];
        })->toArray();
    }

    /**
     * Parse 'with' parameter from request
     */
    private function parseWithParameter($withParam): array
    {
        if (is_string($withParam)) {
            return explode(',', $withParam);
        }

        if (is_array($withParam)) {
            return $withParam;
        }

        return [];
    }

    /**
     * Get pivot data for a book and user
     */
    private function getPivotData(Book $book, Request $request): ?array
    {
        // Determine which user's pivot data to get
        $targetUserId = $request->input('user_id');
        $currentUser = $request->user();

        if ($targetUserId) {
            // Getting pivot for specific user
            $targetUser = \App\Models\User::find($targetUserId);
            if (! $targetUser) {
                return null;
            }

            // Check privacy permissions
            if ($currentUser && $targetUser->id !== $currentUser->id) {
                // Check if target user is private and current user is not following
                if ($targetUser->is_private) {
                    $isFollowing = $currentUser->followingRelationships()
                        ->where('followed_id', $targetUser->id)
                        ->where('status', 'accepted')
                        ->exists();

                    if (! $isFollowing) {
                        return null; // Private profile, no access
                    }
                }
            }

            $userBook = $targetUser->books()
                ->withPivot('added_at', 'read_at', 'is_private', 'reading_status')
                ->where('books.id', $book->id)
                ->first();

        } elseif ($currentUser) {
            // Getting pivot for authenticated user
            $userBook = $currentUser->books()
                ->withPivot('added_at', 'read_at', 'is_private', 'reading_status')
                ->where('books.id', $book->id)
                ->first();
        } else {
            // No user context
            return null;
        }

        if (! $userBook || ! $userBook->pivot) {
            return null;
        }

        $pivot = $userBook->pivot;

        // Check if book is private (only owner can see private books)
        if ($pivot->is_private && (! $currentUser || $currentUser->id !== $pivot->user_id)) {
            return null;
        }

        return [
            'added_at' => $pivot->added_at ? (is_string($pivot->added_at) ? $pivot->added_at : $pivot->added_at->toISOString()) : null,
            'read_at' => $pivot->read_at ? (is_string($pivot->read_at) ? $pivot->read_at : $pivot->read_at->format('Y-m-d')) : null,
            'is_private' => $pivot->is_private,
            'reading_status' => $pivot->reading_status,
        ];
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
            Log::warning('Error parsing publication date', [
                'date_string' => $dateString,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Get locale from request headers for proper Amazon region detection
     */
    private function getLocaleFromRequest(Request $request): string
    {
        // Priority: authenticated user's locale > Accept-Language header > default
        $user = $request->user();
        if ($user && $user->locale) {
            return $user->locale;
        }

        $acceptLanguage = $request->header('Accept-Language', 'en-US,en;q=0.9');

        // Parse Accept-Language header to get primary locale
        $languages = explode(',', $acceptLanguage);
        $primaryLanguage = trim(explode(';', $languages[0])[0]);

        return $primaryLanguage ?: 'en-US';
    }

    /**
     * @OA\Get(
     *     path="/books/{book}/amazon-links",
     *     operationId="getBookAmazonLinks",
     *     tags={"Books"},
     *     summary="Get Amazon purchase links for all regions",
     *     description="Returns Amazon purchase links for the book across different regions (BR, US, UK, CA) with proper affiliate tags",
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
     *         description="Amazon links for all regions",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="links", type="array", @OA\Items(
     *                 @OA\Property(property="region", type="string", example="BR"),
     *                 @OA\Property(property="label", type="string", example="Amazon Brazil"),
     *                 @OA\Property(property="url", type="string", example="https://www.amazon.com.br/dp/123456789?tag=livrolog01-20"),
     *                 @OA\Property(property="domain", type="string", example="amazon.com.br")
     *             ))
     *         )
     *     ),
     *
     *     @OA\Response(response=404, description="Book not found"),
     *     @OA\Response(response=503, description="Amazon integration disabled")
     * )
     */
    public function getAmazonLinks(Book $book, AmazonLinkEnrichmentService $amazonService): JsonResponse
    {
        $bookData = $book->toArray();
        $links = $amazonService->generateAllRegionLinks($bookData);

        if (empty($links)) {
            return response()->json([
                'success' => false,
                'message' => 'Amazon integration is currently disabled',
                'links' => [],
            ], 503);
        }

        return response()->json([
            'success' => true,
            'links' => $links,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/books/{book}/editions",
     *     operationId="getBookEditions",
     *     tags={"Books"},
     *     summary="Get different editions of a book",
     *     description="Returns different editions/formats of the same book (Kindle, Hardcover, Paperback, etc.) using Amazon PA-API GetVariations or local database fallback",
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
     *         description="Book editions found",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="source", type="string", example="amazon_variations", description="Source of editions: amazon_variations or local_database"),
     *             @OA\Property(property="total_found", type="integer", example=5),
     *             @OA\Property(property="current_book_id", type="string", example="B-1ABC-2DEF"),
     *             @OA\Property(property="editions", type="array", @OA\Items(ref="#/components/schemas/Book"))
     *         )
     *     ),
     *
     *     @OA\Response(response=404, description="Book not found"),
     *     @OA\Response(response=200, description="No editions found (single edition only)")
     * )
     */
    public function getEditions(Book $book): JsonResponse
    {
        $editions = [];
        $source = 'none';

        // Try Amazon PA-API GetVariations first (if book has ASIN)
        if ($book->amazon_asin) {
            $amazonProvider = app(\App\Services\Providers\AmazonBooksProvider::class);

            if ($amazonProvider->isEnabled()) {
                $result = $amazonProvider->getVariations($book->amazon_asin);

                if ($result['success'] && ! empty($result['books'])) {
                    $editions = $result['books'];
                    $source = 'amazon_variations';

                    Log::info('Amazon GetVariations success', [
                        'book_id' => $book->id,
                        'asin' => $book->amazon_asin,
                        'variations_found' => count($editions),
                    ]);
                } else {
                    Log::info('Amazon GetVariations found no results', [
                        'book_id' => $book->id,
                        'asin' => $book->amazon_asin,
                        'message' => $result['message'] ?? 'Unknown error',
                    ]);
                }
            }
        }

        // Fallback to local database search by title + author
        if (empty($editions) && $book->title && $book->authors) {
            $query = Book::query()
                ->where('id', '!=', $book->id) // Exclude current book
                ->where('title', 'LIKE', '%'.$book->title.'%')
                ->where('authors', 'LIKE', '%'.$book->authors.'%')
                ->limit(10);

            $localEditions = $query->get();

            if ($localEditions->isNotEmpty()) {
                $transformer = new BookTransformer;
                $editions = $transformer->transform($localEditions->toArray(), ['details']);
                $source = 'local_database';

                Log::info('Local database editions found', [
                    'book_id' => $book->id,
                    'title' => $book->title,
                    'editions_found' => count($editions),
                ]);
            }
        }

        // Ensure editions is always an array
        if (! is_array($editions)) {
            $editions = [];
        }

        // Filter out the current book from editions (in case Amazon or local DB returned it)
        $editions = array_filter($editions, function ($edition) use ($book) {
            // Remove if same internal ID (with null/empty check)
            if (! empty($edition['id']) && ! empty($book->id) && $edition['id'] === $book->id) {
                return false;
            }

            // Remove if same ASIN (with null/empty check)
            if (! empty($edition['amazon_asin']) && ! empty($book->amazon_asin)
                && $edition['amazon_asin'] === $book->amazon_asin) {
                return false;
            }

            // Remove if same Google ID (with null/empty check)
            if (! empty($edition['google_id']) && ! empty($book->google_id)
                && $edition['google_id'] === $book->google_id) {
                return false;
            }

            return true;
        });

        // Re-index array after filtering
        $editions = array_values($editions);

        // Always include the current book at the beginning
        $transformer = new BookTransformer;
        $currentBookData = $transformer->transform($book, ['details']);

        // Add current book at the start
        array_unshift($editions, $currentBookData);

        return response()->json([
            'success' => true,
            'source' => $source,
            'total_found' => count($editions),
            'current_book_id' => $book->id,
            'editions' => $editions,
        ]);
    }
}
