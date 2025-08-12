<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Http\Resources\PaginatedResource;
use App\Services\BookEnrichmentService;
use App\Services\MultiSourceBookSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class BookController extends Controller
{
    // Validation constants to avoid duplication
    private const VALIDATION_NULLABLE_STRING = 'nullable|string';

    /**
     *     @OA\Get(
     *     path="/books",
     *     operationId="getBooks",
     *     tags={"Books"},
     *     summary="List books",
     *     description="Returns paginated list of books",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Books list",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Book")),
     *             @OA\Property(property="current_page", type="integer"),
     *             @OA\Property(property="total", type="integer"),
     *             @OA\Property(property="per_page", type="integer")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function index(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        
        $query = $user->books()->withPivot('added_at', 'read_at');
        
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
     * @OA\Get(
     *     path="/showcase",
     *     operationId="getShowcase",
     *     tags={"Books"},
     *     summary="List featured books for showcase",
     *     description="Returns 20 most popular books (most present in user libraries) for public display",
     *     @OA\Response(
     *         response=200,
     *         description="Featured books list",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(ref="#/components/schemas/Book")
     *         )
     *     )
     * )
     */
    public function showcase()
    {
        // Returns 20 most popular books (most present in user libraries)
        $showcaseBooks = Book::select('books.*', DB::raw('COUNT(users_books.book_id) as library_count'))
            ->leftJoin('users_books', 'books.id', '=', 'users_books.book_id')
            ->groupBy('books.id')
            ->orderByDesc('library_count')
            ->limit(20)
            ->get();

        return response()->json($showcaseBooks);
    }

    /**
     * @OA\Post(
     *     path="/books",
     *     operationId="storeBook",
     *     tags={"Books"},
     *     summary="Create new book with automatic enrichment",
     *     description="Creates a new book and automatically enriches it with additional information from Google Books API. If book already exists, it will be enriched if needed and associated with the user.",
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
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
     *     @OA\Response(
     *         response=201,
     *         description="Book created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="book", ref="#/components/schemas/Book"),
     *             @OA\Property(property="enriched", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Book created successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Book already exists",
     *         @OA\JsonContent(
     *             @OA\Property(property="book", ref="#/components/schemas/Book"),
     *             @OA\Property(property="enriched", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Book found in database")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     * Store a newly created resource in storage.
     */
    public function store(Request $request, BookEnrichmentService $enrichmentService)
    {
        $request->validate([
            'google_id' => self::VALIDATION_NULLABLE_STRING,
            'title' => 'required|string|max:255',
            'isbn' => self::VALIDATION_NULLABLE_STRING . '|max:20',
            'authors' => self::VALIDATION_NULLABLE_STRING,
            'thumbnail' => 'nullable|url|max:512',
            'description' => self::VALIDATION_NULLABLE_STRING,
            'language' => self::VALIDATION_NULLABLE_STRING . '|max:10',
            'publisher' => self::VALIDATION_NULLABLE_STRING . '|max:255',
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
                    $fail('The ' . $attribute . ' field must be a valid date, year (YYYY), or year-month (YYYY-MM).');
                }
            }],
            'page_count' => 'nullable|integer|min:1',
            'format' => 'nullable|string|in:hardcover,paperback,ebook,audiobook',
            'categories' => 'nullable|array',
            'edition' => 'nullable|string|max:50',
        ]);

        $user = $request->user();
        $isbn = $request->input('isbn');
        $googleId = $request->input('google_id');

        $book = $this->findExistingBook($isbn, $googleId);

        if ($book) {
            return $this->handleExistingBook($book, $user, $enrichmentService, $googleId);
        }

        return $this->handleNewBook($request, $user, $enrichmentService, $googleId);
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
     * Handle existing book processing
     */
    private function handleExistingBook(Book $book, $user, BookEnrichmentService $enrichmentService, ?string $googleId): JsonResponse
    {
        $needsEnrichment = $this->shouldEnrichBook($book);

        if ($needsEnrichment) {
            $enrichmentResult = $enrichmentService->enrichBook($book, $googleId);
            if ($enrichmentResult['success']) {
                $book->refresh();
            }
        }

        if (!$user->books()->where('books.id', $book->id)->exists()) {
            $user->books()->attach($book->id, [
                'added_at' => now(),
            ]);
        }

        return response()->json([
            'book' => $book,
            'enriched' => $needsEnrichment,
            'message' => $needsEnrichment ? 'Book found and enriched' : 'Book found in database'
        ], 200);
    }

    /**
     * Handle new book creation
     */
    private function handleNewBook(Request $request, $user, BookEnrichmentService $enrichmentService, ?string $googleId): JsonResponse
    {
        $bookData = $request->all();

        if (!empty($bookData['published_date'])) {
            $bookData['published_date'] = $this->parsePublishedDate($bookData['published_date']);
        }

        $book = Book::create($bookData);

        $enrichmentResult = null;
        if ($googleId) {
            $enrichmentResult = $enrichmentService->enrichBook($book, $googleId);
            if ($enrichmentResult['success']) {
                $book->refresh();
            }
        }

        $user->books()->attach($book->id, [
            'added_at' => now(),
        ]);

        return response()->json([
            'book' => $book,
            'enriched' => $enrichmentResult ? $enrichmentResult['success'] : false,
            'message' => 'Book created successfully'
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
     * Display the specified resource.
     *
     * @OA\Get(
     *     path="/books/{id}",
     *     operationId="getBookById",
     *     tags={"Books"},
     *     summary="Get book details",
     *     description="Returns a single book by its custom ID.",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Book ID (e.g. B-3D6Y-9IO8)",
     *         required=true,
     *         @OA\Schema(type="string", example="B-3D6Y-9IO8")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Book details",
     *         @OA\JsonContent(ref="#/components/schemas/Book")
     *     ),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function show(string $id)
    {
        $book = Book::with(['users', 'relatedBooks'])->findOrFail($id);
        return response()->json($book);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $book = Book::findOrFail($id);

        $request->validate([
            'title' => 'required|string|max:255',
            'isbn' => 'nullable|string|max:20|unique:books,isbn,' . $book->id,
            'authors' => 'nullable|string',
            'thumbnail' => 'nullable|url|max:512',
            'language' => 'nullable|string|max:10',
            'publisher' => 'nullable|string|max:255',
            'edition' => 'nullable|string|max:50',
        ]);

        $book->update($request->all());

        return response()->json($book);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $id)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        $book = Book::findOrFail($id);
        $user->books()->detach($book->id);
        return response()->json(['message' => 'Book removed from your library']);
    }

    /**
     * @OA\Get(
     *     path="/books/search",
     *     operationId="searchBooks",
     *     tags={"Books"},
     *     summary="Search books using multiple sources",
     *     description="Searches books using multiple APIs (Google Books, Open Library) with intelligent fallback",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="q",
     *         in="query",
     *         description="Search term (minimum 3 characters) - can be ISBN, title, or author",
     *         required=true,
     *         @OA\Schema(type="string", example="9786584956261")
     *     ),
     *     @OA\Parameter(
     *         name="provider",
     *         in="query",
     *         description="Force specific provider (optional) - for debugging",
     *         required=false,
     *         @OA\Schema(type="string", enum={"Google Books", "Open Library"})
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Search results",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="provider", type="string", example="Google Books"),
     *             @OA\Property(property="total_found", type="integer", example=5),
     *             @OA\Property(property="search_strategy", type="string", example="multi_source"),
     *             @OA\Property(
     *                 property="books",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="provider", type="string", example="Google Books"),
     *                     @OA\Property(property="google_id", type="string", example="YvkTEAAAQBAJ"),
     *                     @OA\Property(property="title", type="string", example="The Hobbit"),
     *                     @OA\Property(property="authors", type="string", example="J.R.R. Tolkien"),
     *                     @OA\Property(property="isbn", type="string", example="9788595084988"),
     *                     @OA\Property(property="thumbnail", type="string", example="https://books.google.com/..."),
     *                     @OA\Property(property="description", type="string"),
     *                     @OA\Property(property="publisher", type="string", example="HarperCollins"),
     *                     @OA\Property(property="language", type="string", example="pt-BR")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="providers_tried",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="provider", type="string", example="Google Books"),
     *                     @OA\Property(property="success", type="boolean", example=true),
     *                     @OA\Property(property="total_found", type="integer", example=5)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=422, description="Invalid search term")
     * )
     */
    public function search(Request $request, MultiSourceBookSearchService $multiSearchService)
    {
        $request->validate([
            'q' => 'required|string|min:3',
            'provider' => 'nullable|string|in:Google Books,Open Library'
        ]);

        $query = $request->input('q');
        $forcedProvider = $request->input('provider');

        // If specific provider is requested (for debugging)
        if ($forcedProvider) {
            $result = $multiSearchService->searchWithSpecificProvider($forcedProvider, $query);
        } else {
            // Use multi-source search with fallback
            $result = $multiSearchService->search($query, [
                'maxResults' => 40
            ]);
        }

        return response()->json($result);
    }

    /**
     * Fetch data from Google Books API
     */
    private function fetchGoogleBooksData(string $query)
    {
        return Http::get('https://www.googleapis.com/books/v1/volumes', [
            'q' => $query,
            'maxResults' => 40,
            'printType' => 'books'
        ]);
    }

    /**
     * Process Google Books API response data
     */
    private function processGoogleBooksResults(array $data): array
    {
        $books = [];

        if (isset($data['items'])) {
            foreach ($data['items'] as $item) {
                $books[] = $this->transformGoogleBookItem($item);
            }
        }

        return $books;
    }

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
            'language' => $volumeInfo['language'] ?? 'pt-BR',
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
     *     summary="Enrich book information using Google Books API",
     *     description="Fetches additional information about a book from Google Books API and updates the local record",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Book ID",
     *         required=true,
     *         @OA\Schema(type="string", example="B-3D6Y-9IO8")
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="google_id", type="string", example="8fcQEAAAQBAJ", description="Optional Google Books ID to use for enrichment")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Book enriched successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Book enriched successfully"),
     *             @OA\Property(property="book_id", type="string", example="B-3D6Y-9IO8"),
     *             @OA\Property(property="added_fields", type="array", @OA\Items(type="string"))
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Book not found"),
     *     @OA\Response(response=422, description="Enrichment failed")
     * )
     */
    public function enrichBook(Request $request, Book $book, BookEnrichmentService $enrichmentService)
    {
        $request->validate([
            'google_id' => 'nullable|string'
        ]);

        $result = $enrichmentService->enrichBook($book, $request->input('google_id'));

        if ($result['success']) {
            return response()->json($result);
        } else {
            return response()->json($result, 422);
        }
    }

    /**
     * @OA\Post(
     *     path="/books/enrich-batch",
     *     operationId="enrichBooksInBatch",
     *     tags={"Books"},
     *     summary="Enrich multiple books in batch",
     *     description="Enriches multiple books information using Google Books API",
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="book_ids", type="array", @OA\Items(type="string"), description="Specific book IDs to enrich (optional)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Batch enrichment completed",
     *         @OA\JsonContent(
     *             @OA\Property(property="processed", type="integer", example=10),
     *             @OA\Property(property="success_count", type="integer", example=8),
     *             @OA\Property(property="error_count", type="integer", example=2),
     *             @OA\Property(property="results", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function enrichBooksInBatch(Request $request, BookEnrichmentService $enrichmentService)
    {
        $request->validate([
            'book_ids' => 'nullable|array',
            'book_ids.*' => 'string|exists:books,id'
        ]);

        $result = $enrichmentService->enrichBooksInBatch($request->input('book_ids'));

        return response()->json($result);
    }

    /**
     * @OA\Post(
     *     path="/books/create-enriched",
     *     operationId="createEnrichedBook",
     *     tags={"Books"},
     *     summary="Create new book with enriched information",
     *     description="Creates a new book directly from Google Books API with all available information",
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="google_id", type="string", example="8fcQEAAAQBAJ", description="Google Books ID"),
     *             @OA\Property(property="add_to_library", type="boolean", example=true, description="Add book to user's library")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Book created and enriched successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Book created and enriched successfully"),
     *             @OA\Property(property="book", ref="#/components/schemas/Book"),
     *             @OA\Property(property="info_quality", type="string", example="complete")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=422, description="Creation failed")
     * )
     */
    public function createEnrichedBook(Request $request, BookEnrichmentService $enrichmentService)
    {
        $request->validate([
            'google_id' => 'required|string',
            'add_to_library' => 'boolean'
        ]);

        $userId = $request->boolean('add_to_library') ? $request->user()->id : null;

        $result = $enrichmentService->createEnrichedBookFromGoogle(
            $request->input('google_id'),
            $userId
        );

        if ($result['success']) {
            return response()->json($result, 201);
        } else {
            return response()->json($result, 422);
        }
    }

    /**
     * Parse published date from various formats
     */
    private function parsePublishedDate(string $dateString): ?\Carbon\Carbon
    {
        try {
            // Year only (4 digits)
            if (preg_match('/^\d{4}$/', $dateString)) {
                return \Carbon\Carbon::createFromFormat('Y', $dateString)->startOfYear();
            }
            // Year and month (YYYY-MM)
            elseif (preg_match('/^\d{4}-\d{2}$/', $dateString)) {
                return \Carbon\Carbon::createFromFormat('Y-m', $dateString)->startOfMonth();
            }
            // Full date
            else {
                return \Carbon\Carbon::parse($dateString);
            }
        } catch (\Exception $e) {
            \Log::warning('Error parsing publication date', [
                'date_string' => $dateString,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
}
