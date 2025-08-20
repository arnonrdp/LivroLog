<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaginatedResource;
use App\Models\Book;
use App\Services\BookEnrichmentService;
use App\Services\MultiSourceBookSearchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Items per page",
     *         required=false,
     *
     *         @OA\Schema(type="integer", example=20)
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search term for external APIs (Google Books, Open Library)",
     *         required=false,
     *
     *         @OA\Schema(type="string", example="Sidarta")
     *     ),
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
     *             @OA\Property(property="current_page", type="integer"),
     *             @OA\Property(property="total", type="integer"),
     *             @OA\Property(property="per_page", type="integer")
     *         )
     *     )
     * )
     */
    public function index(Request $request, MultiSourceBookSearchService $multiSearchService)
    {
        // Handle search parameter - external API search
        if ($request->has('search')) {
            $query = $request->input('search');
            $result = $multiSearchService->search($query, [
                'maxResults' => 40,
            ]);
            return response()->json($result);
        }

        // Handle showcase parameter - featured books
        if ($request->boolean('showcase')) {
            return $this->getShowcaseBooks();
        }

        // Default behavior - global catalog
        $query = Book::query();

        // Paginate with configurable per_page parameter (default 20)
        $perPage = $request->get('per_page', 20);
        $books = $query->paginate($perPage);

        return new PaginatedResource($books);
    }

    /**
     * Get showcase books (featured/most popular books)
     */
    private function getShowcaseBooks()
    {
        try {
            // Check if users_books table exists first
            if (!Schema::hasTable('users_books')) {
                // If table doesn't exist, fallback to recent books
                $fallbackBooks = DB::table('books')
                    ->orderBy('created_at', 'desc')
                    ->limit(20)
                    ->get();
                
                return response()->json($fallbackBooks);
            }

            // Try a simpler approach first - get books with counts using Eloquent
            $showcaseBooks = Book::withCount(['users as library_count'])
                ->orderByDesc('library_count')
                ->limit(20)
                ->get();

            // If the Eloquent approach fails, fall back to raw SQL
            if ($showcaseBooks->isEmpty()) {
                $showcaseBooks = DB::table('books')
                    ->selectRaw('books.*, COALESCE(book_counts.library_count, 0) as library_count')
                    ->leftJoin(
                        DB::raw('(SELECT book_id, COUNT(*) as library_count FROM users_books WHERE book_id IS NOT NULL GROUP BY book_id) as book_counts'),
                        'books.id',
                        '=',
                        'book_counts.book_id'
                    )
                    ->orderByDesc('library_count')
                    ->limit(20)
                    ->get();
            }

            return response()->json($showcaseBooks);
        } catch (\Exception $e) {
            // Log the error for debugging
            \Log::error('Showcase books query failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            // Fallback: return recent books if the showcase query fails
            $fallbackBooks = DB::table('books')
                ->orderBy('created_at', 'desc')
                ->limit(20)
                ->get();

            return response()->json($fallbackBooks);
        }
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
    public function store(Request $request, BookEnrichmentService $enrichmentService)
    {
        $request->validate([
            'google_id' => self::VALIDATION_NULLABLE_STRING,
            'title' => 'required|string|max:255',
            'isbn' => self::VALIDATION_NULLABLE_STRING.'|max:20',
            'authors' => self::VALIDATION_NULLABLE_STRING,
            'thumbnail' => 'nullable|url|max:512',
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
                $enrichmentResult = $enrichmentService->enrichBook($book, $googleId);
                if ($enrichmentResult['success']) {
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
            $enrichmentResult = $enrichmentService->enrichBook($book, $googleId);
            if ($enrichmentResult['success']) {
                $book->refresh();
            }
        }

        return response()->json([
            'book' => $book,
            'enriched' => $enrichmentResult ? $enrichmentResult['success'] : false,
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
     *     summary="Get book by ID",
     *     description="Returns detailed information about a specific book",
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
     *         description="Book information",
     *
     *         @OA\JsonContent(ref="#/components/schemas/Book")
     *     ),
     *
     *     @OA\Response(response=404, description="Book not found"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function show(string $id)
    {
        $book = Book::with(['users', 'relatedBooks'])->findOrFail($id);

        return response()->json($book);
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
            'thumbnail' => 'nullable|url|max:512',
            'language' => self::VALIDATION_NULLABLE_STRING.'|max:10',
            'publisher' => self::VALIDATION_NULLABLE_STRING.'|max:255',
            'edition' => 'nullable|string|max:50',
        ]);

        $book->update($request->all());

        return response()->json($book);
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
    public function enrichBook(Request $request, Book $book = null, BookEnrichmentService $enrichmentService)
    {
        // Handle batch enrichment
        if ($request->boolean('batch')) {
            $request->validate([
                'book_ids' => 'nullable|array',
                'book_ids.*' => 'string|exists:books,id',
            ]);

            $result = $enrichmentService->enrichBooksInBatch($request->input('book_ids'));
            return response()->json($result);
        }

        // Handle single book enrichment
        $request->validate([
            'google_id' => self::VALIDATION_NULLABLE_STRING,
        ]);

        $result = $enrichmentService->enrichBook($book, $request->input('google_id'));

        if ($result['success']) {
            return response()->json($result);
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
