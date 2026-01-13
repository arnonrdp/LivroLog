<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\HandlesPagination;
use App\Models\Book;
use App\Models\User;
use App\Services\AmazonEnrichmentService;
use App\Services\AmazonScraperService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

class AdminController extends Controller
{
    use HandlesPagination;

    /**
     * List all users with optional sorting and filtering
     */
    public function users(Request $request)
    {
        $query = User::query()
            ->select('users.*')
            ->withCount(['books', 'followers', 'following'])
            ->with(['lastActivity.subject' => function ($morphTo) {
                $morphTo->morphWith([
                    \App\Models\Review::class => ['book'],
                ]);
            }])
            ->addSelect([
                'last_activity_at' => \App\Models\Activity::select('created_at')
                    ->whereColumn('user_id', 'users.id')
                    ->orderByDesc('created_at')
                    ->limit(1),
            ]);

        // Search filter
        $filter = $request->input('filter');
        if (! empty($filter)) {
            $query->where(function ($q) use ($filter) {
                $q->where('id', 'like', "%{$filter}%")
                    ->orWhere('display_name', 'like', "%{$filter}%")
                    ->orWhere('username', 'like', "%{$filter}%")
                    ->orWhere('email', 'like', "%{$filter}%");
            });
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDesc = $request->boolean('sort_desc', true);
        $allowedSortColumns = ['display_name', 'username', 'email', 'role', 'books_count', 'created_at', 'last_activity_at'];

        if (in_array($sortBy, $allowedSortColumns)) {
            $query->orderBy($sortBy, $sortDesc ? 'desc' : 'asc');
        } else {
            $query->orderByDesc('created_at');
        }

        $users = $this->applyPagination($query, $request);

        return response()->json([
            'data' => $users->map(fn ($user) => [
                'id' => $user->id,
                'display_name' => $user->display_name,
                'username' => $user->username,
                'email' => $user->email,
                'avatar' => $user->avatar,
                'role' => $user->role,
                'books_count' => $user->books_count,
                'followers_count' => $user->followers_count,
                'following_count' => $user->following_count,
                'created_at' => $user->created_at,
                'last_activity' => $user->lastActivity ? [
                    'type' => $user->lastActivity->type,
                    'subject_name' => $this->getActivitySubjectName($user->lastActivity),
                    'created_at' => $user->lastActivity->created_at,
                ] : null,
            ]),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
        ]);
    }

    /**
     * Get the subject name for an activity
     */
    private function getActivitySubjectName($activity): ?string
    {
        if (! $activity->subject) {
            return null;
        }

        return match ($activity->subject_type) {
            'App\\Models\\Book' => $activity->subject->title,
            'App\\Models\\User' => $activity->subject->display_name ?? $activity->subject->username,
            'App\\Models\\Review' => $activity->subject->load('book')->book?->title,
            default => null,
        };
    }

    /**
     * List all books with optional sorting and filtering
     */
    public function books(Request $request)
    {
        $query = Book::query()
            ->withCount('users');

        // Search filter
        $filter = $request->input('filter');
        if (! empty($filter)) {
            $query->where(function ($q) use ($filter) {
                $q->where('id', 'like', "%{$filter}%")
                    ->orWhere('title', 'like', "%{$filter}%")
                    ->orWhere('authors', 'like', "%{$filter}%")
                    ->orWhere('isbn', 'like', "%{$filter}%")
                    ->orWhere('google_id', 'like', "%{$filter}%")
                    ->orWhere('amazon_asin', 'like', "%{$filter}%");
            });
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDesc = $request->boolean('sort_desc', true);
        $allowedSortColumns = ['title', 'authors', 'isbn', 'amazon_asin', 'language', 'page_count', 'users_count', 'created_at'];

        if (in_array($sortBy, $allowedSortColumns)) {
            $query->orderBy($sortBy, $sortDesc ? 'desc' : 'asc');
        } else {
            $query->orderByDesc('created_at');
        }

        $books = $this->applyPagination($query, $request);

        return response()->json([
            'data' => $books->map(fn ($book) => [
                'id' => $book->id,
                'title' => $book->title,
                'authors' => $book->authors,
                'isbn' => $book->isbn,
                'google_id' => $book->google_id,
                'amazon_asin' => $book->amazon_asin,
                'language' => $book->language,
                'page_count' => $book->page_count,
                'publisher' => $book->publisher,
                'published_date' => $book->published_date,
                'thumbnail' => $book->thumbnail,
                'description' => $book->description,
                'users_count' => $book->users_count,
                'created_at' => $book->created_at,
            ]),
            'meta' => [
                'current_page' => $books->currentPage(),
                'last_page' => $books->lastPage(),
                'per_page' => $books->perPage(),
                'total' => $books->total(),
            ],
        ]);
    }

    /**
     * Enrich a single book with Amazon data (synchronous, admin only)
     *
     * If amazon_url is provided, extracts data directly from that URL.
     * Otherwise, tries PA-API only. If PA-API fails, returns needs_url=true
     * so frontend can show dialog for manual URL input.
     */
    public function enrichBookWithAmazon(Request $request, Book $book, AmazonEnrichmentService $enrichmentService): JsonResponse
    {
        $amazonUrl = $request->input('amazon_url');

        // If URL provided, use direct extraction (manual fallback)
        if ($amazonUrl) {
            return $this->enrichFromUrl($book, $amazonUrl);
        }

        // Try PA-API only (no automatic scraping)
        $result = $enrichmentService->enrichBookWithPaApiOnly($book);

        // Reload book to get fresh data
        $book->refresh();

        // If PA-API failed, indicate that manual URL is needed
        if (! $result['success']) {
            return response()->json([
                'success' => false,
                'needs_url' => true,
                'message' => $result['message'] ?? 'PA-API unavailable',
                'source' => null,
                'fields_filled' => [],
                'book' => $this->formatBookResponse($book),
            ]);
        }

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
            'source' => $result['source'] ?? 'pa-api',
            'fields_filled' => $result['fields_filled'],
            'book' => $this->formatBookResponse($book),
        ]);
    }

    /**
     * Enrich book from a specific Amazon URL
     */
    private function enrichFromUrl(Book $book, string $amazonUrl): JsonResponse
    {
        // Validate URL is from Amazon
        if (! $this->isValidAmazonUrl($amazonUrl)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid Amazon URL. Please provide a valid amazon.com or amazon.com.br product link.',
                'source' => null,
                'fields_filled' => [],
                'book' => $this->formatBookResponse($book),
            ], 422);
        }

        $scraper = app(AmazonScraperService::class);
        $amazonData = $scraper->extractFromUrl($amazonUrl);

        if (! $amazonData) {
            return response()->json([
                'success' => false,
                'message' => 'Could not extract data from the provided Amazon URL. Please check the URL and try again.',
                'source' => null,
                'fields_filled' => [],
                'book' => $this->formatBookResponse($book),
            ], 422);
        }

        // Check if ISBN already exists in another book
        if (! empty($amazonData['isbn'])) {
            $existingBook = Book::where('isbn', $amazonData['isbn'])
                ->where('id', '!=', $book->id)
                ->first();

            if ($existingBook) {
                return response()->json([
                    'success' => false,
                    'message' => "ISBN {$amazonData['isbn']} already exists in another book: {$existingBook->title}",
                    'source' => null,
                    'fields_filled' => [],
                    'book' => $this->formatBookResponse($book),
                ], 422);
            }
        }

        // Check if ASIN already exists in another book
        if (! empty($amazonData['amazon_asin'])) {
            $existingBook = Book::where('amazon_asin', $amazonData['amazon_asin'])
                ->where('id', '!=', $book->id)
                ->first();

            if ($existingBook) {
                return response()->json([
                    'success' => false,
                    'message' => "ASIN {$amazonData['amazon_asin']} already exists in another book: {$existingBook->title}",
                    'source' => null,
                    'fields_filled' => [],
                    'book' => $this->formatBookResponse($book),
                ], 422);
            }
        }

        // Update book with ALL available data from Amazon (admin chose this product)
        $filledFields = $this->updateBookFromAmazonData($book, $amazonData);

        $book->refresh();

        return response()->json([
            'success' => true,
            'message' => 'Book enriched from Amazon URL',
            'source' => 'url',
            'fields_filled' => $filledFields,
            'book' => $this->formatBookResponse($book),
        ]);
    }

    /**
     * Validate that URL is from Amazon
     */
    private function isValidAmazonUrl(string $url): bool
    {
        try {
            $host = parse_url($url, PHP_URL_HOST);
            if (! $host) {
                return false;
            }

            $host = strtolower($host);

            // Check if it's an Amazon domain (including short URLs)
            $amazonDomains = [
                'a.co',
                'amzn.to',
                'amzn.com',
                'amazon.com',
                'amazon.com.br',
                'amazon.co.uk',
                'amazon.ca',
                'amazon.de',
                'amazon.fr',
                'amazon.es',
                'amazon.it',
                'amazon.co.jp',
                'www.amazon.com',
                'www.amazon.com.br',
                'www.amazon.co.uk',
                'www.amazon.ca',
                'www.amazon.de',
                'www.amazon.fr',
                'www.amazon.es',
                'www.amazon.it',
                'www.amazon.co.jp',
            ];

            foreach ($amazonDomains as $domain) {
                if ($host === $domain || str_ends_with($host, '.'.$domain)) {
                    return true;
                }
            }

            return false;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Update book with Amazon data (overwrites all fields since admin chose the product)
     */
    private function updateBookFromAmazonData(Book $book, array $amazonData): array
    {
        $updateData = [
            'asin_status' => 'completed',
            'asin_processed_at' => now(),
        ];

        $filledFields = [];

        // ASIN (always update)
        if (! empty($amazonData['amazon_asin'])) {
            $updateData['amazon_asin'] = $amazonData['amazon_asin'];
            $filledFields[] = 'amazon_asin';
        }

        // Thumbnail (always update if available - admin chose this product)
        if (! empty($amazonData['thumbnail'])) {
            $updateData['thumbnail'] = $amazonData['thumbnail'];
            $filledFields[] = 'thumbnail';
        }

        // ISBN (update if available)
        if (! empty($amazonData['isbn'])) {
            $updateData['isbn'] = $amazonData['isbn'];
            $filledFields[] = 'isbn';
        }

        // Page count (update if available)
        if (! empty($amazonData['page_count'])) {
            $updateData['page_count'] = $amazonData['page_count'];
            $filledFields[] = 'page_count';
        }

        // Description (update if Amazon has a longer/better description)
        if (! empty($amazonData['description'])) {
            $existingLength = strlen($book->description ?? '');
            $newLength = strlen($amazonData['description']);

            // Update if: no existing description, OR Amazon description is significantly longer (>50% more)
            if ($existingLength === 0 || $newLength > $existingLength * 1.5) {
                $updateData['description'] = $amazonData['description'];
                $filledFields[] = 'description';
            }
        }

        // Publisher (update if available)
        if (! empty($amazonData['publisher'])) {
            $updateData['publisher'] = $amazonData['publisher'];
            $filledFields[] = 'publisher';
        }

        // Physical dimensions
        if (! empty($amazonData['height'])) {
            $updateData['height'] = $amazonData['height'];
            $filledFields[] = 'height';
        }
        if (! empty($amazonData['width'])) {
            $updateData['width'] = $amazonData['width'];
            $filledFields[] = 'width';
        }
        if (! empty($amazonData['thickness'])) {
            $updateData['thickness'] = $amazonData['thickness'];
            $filledFields[] = 'thickness';
        }

        $book->update($updateData);

        return $filledFields;
    }

    /**
     * Format book data for response
     */
    private function formatBookResponse(Book $book): array
    {
        return [
            'id' => $book->id,
            'title' => $book->title,
            'amazon_asin' => $book->amazon_asin,
            'asin_status' => $book->asin_status,
            'thumbnail' => $book->thumbnail,
            'isbn' => $book->isbn,
            'page_count' => $book->page_count,
            'description' => $book->description,
            'publisher' => $book->publisher,
            'authors' => $book->authors,
        ];
    }

    /**
     * Create a new book from an Amazon URL
     *
     * Extracts book data from the Amazon product page and creates a new book.
     */
    public function createBookFromAmazonUrl(Request $request): JsonResponse
    {
        $request->validate([
            'amazon_url' => 'required|string|url',
        ]);

        $amazonUrl = $request->input('amazon_url');

        // Validate URL is from Amazon
        if (! $this->isValidAmazonUrl($amazonUrl)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid Amazon URL. Please provide a valid Amazon product link.',
            ], 422);
        }

        $scraper = app(AmazonScraperService::class);
        $amazonData = $scraper->extractFromUrl($amazonUrl);

        if (! $amazonData) {
            return response()->json([
                'success' => false,
                'message' => 'Could not extract data from the provided Amazon URL. Please check the URL and try again.',
            ], 422);
        }

        // Check for required data (at least title or ASIN)
        if (empty($amazonData['extracted_title']) && empty($amazonData['amazon_asin'])) {
            return response()->json([
                'success' => false,
                'message' => 'Could not extract book information from the Amazon page.',
            ], 422);
        }

        // Check if ISBN already exists
        if (! empty($amazonData['isbn'])) {
            $existingBook = Book::where('isbn', $amazonData['isbn'])->first();
            if ($existingBook) {
                return response()->json([
                    'success' => false,
                    'message' => "A book with ISBN {$amazonData['isbn']} already exists: {$existingBook->title}",
                    'existing_book' => $this->formatBookResponse($existingBook),
                ], 422);
            }
        }

        // Check if ASIN already exists
        if (! empty($amazonData['amazon_asin'])) {
            $existingBook = Book::where('amazon_asin', $amazonData['amazon_asin'])->first();
            if ($existingBook) {
                return response()->json([
                    'success' => false,
                    'message' => "A book with ASIN {$amazonData['amazon_asin']} already exists: {$existingBook->title}",
                    'existing_book' => $this->formatBookResponse($existingBook),
                ], 422);
            }
        }

        // Create the book
        $book = Book::create([
            'title' => $amazonData['extracted_title'] ?? 'Untitled',
            'authors' => $amazonData['authors'] ?? null,
            'isbn' => $amazonData['isbn'] ?? null,
            'amazon_asin' => $amazonData['amazon_asin'] ?? null,
            'thumbnail' => $amazonData['thumbnail'] ?? null,
            'page_count' => $amazonData['page_count'] ?? null,
            'description' => $amazonData['description'] ?? null,
            'publisher' => $amazonData['publisher'] ?? null,
            'height' => $amazonData['height'] ?? null,
            'width' => $amazonData['width'] ?? null,
            'thickness' => $amazonData['thickness'] ?? null,
            'asin_status' => 'completed',
            'asin_processed_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Book created successfully from Amazon URL',
            'book' => $this->formatBookResponse($book),
        ]);
    }

    /**
     * Send password reset email to a user (admin only)
     */
    public function sendPasswordReset(string $id): JsonResponse
    {
        $user = User::findOrFail($id);

        $status = Password::sendResetLink(['email' => $user->email]);

        if ($status === Password::RESET_LINK_SENT) {
            return response()->json([
                'success' => true,
                'message' => 'Password reset email sent successfully',
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => __($status),
        ], 400);
    }
}
