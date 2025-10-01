<?php

namespace App\Services;

use App\Contracts\BookSearchProvider;
use App\Services\Providers\AmazonBooksProvider;
use App\Services\Providers\GoogleBooksProvider;
use App\Services\Providers\OpenLibraryProvider;
use App\Transformers\BookTransformer;
use Illuminate\Support\Facades\Cache;

class MultiSourceBookSearchService
{
    private array $providers = [];

    private const CACHE_TTL_SUCCESS = 604800; // 7 days for successful results (more stable)

    private const CACHE_TTL_FAILURE = 86400;  // 24 hours for failed results

    private const CACHE_TTL_AMAZON_FAILURE = 3600; // 1 hour for Amazon rate limit failures

    public function __construct()
    {
        $this->initializeProviders();
    }

    /**
     * Search for books using multiple sources with fallback strategy
     *
     * @param  string  $query  Search query (ISBN, title, or title+author)
     * @param  array  $options  Search options
     * @return array Standardized search results
     */
    public function search(string $query, array $options = []): array
    {
        $cacheKey = $this->buildCacheKey($query, $options);
        $normalizedQuery = $this->normalizeQuery($query);

        // Check cache first
        if ($cachedResult = Cache::get($cacheKey)) {
            return $cachedResult;
        }

        // Extract includes from options
        $includes = $options['includes'] ?? [];
        $transformer = new BookTransformer;

        $providerResults = [];
        $allBooks = [];
        $usedIsbns = [];

        // Strategy: Always try Amazon first, then supplement with Google Books if needed
        $amazon = $this->findProviderByName('Amazon Books');
        $googleBooks = $this->findProviderByName('Google Books');

        $amazonResult = null;
        $googleResult = null;

        // Try Amazon Books first
        if ($amazon && $amazon->isEnabled()) {
            try {
                $amazonResult = $this->searchWithProvider($amazon, $normalizedQuery, $options);
                $providerResults[] = [
                    'provider' => $amazon->getName(),
                    'success' => $amazonResult['success'],
                    'total_found' => $amazonResult['total_found'],
                    'message' => $amazonResult['message'],
                ];

                if ($amazonResult['success'] && ! empty($amazonResult['books'])) {
                    $transformedBooks = $transformer->transform($amazonResult['books'], $includes);

                    // Enrich Amazon results with Google IDs for easier book creation
                    $transformedBooks = $this->enrichAmazonBooksWithGoogleIds($transformedBooks);

                    $allBooks = array_merge($allBooks, $transformedBooks);

                    // Track ISBNs to avoid duplicates
                    foreach ($transformedBooks as $book) {
                        if (! empty($book['isbn_13'])) {
                            $usedIsbns[] = $book['isbn_13'];
                        }
                        if (! empty($book['isbn_10'])) {
                            $usedIsbns[] = $book['isbn_10'];
                        }
                        if (! empty($book['isbn'])) {
                            $usedIsbns[] = $book['isbn'];
                        }
                    }
                }
            } catch (\Exception $e) {
                $providerResults[] = [
                    'provider' => $amazon->getName(),
                    'success' => false,
                    'total_found' => 0,
                    'message' => 'Provider error: '.$e->getMessage(),
                ];

                // Special handling for Amazon rate limiting
                if (str_contains($e->getMessage(), '429')) {
                    $amazonCacheKey = 'amazon_rate_limited_'.md5($query);
                    Cache::put($amazonCacheKey, true, self::CACHE_TTL_AMAZON_FAILURE);
                }
            }
        }

        // If Amazon returned less than 10 results, supplement with Google Books
        if (count($allBooks) < 10 && $googleBooks && $googleBooks->isEnabled()) {
            try {
                $googleResult = $this->searchWithProvider($googleBooks, $normalizedQuery, $options);
                $providerResults[] = [
                    'provider' => $googleBooks->getName(),
                    'success' => $googleResult['success'],
                    'total_found' => $googleResult['total_found'],
                    'message' => $googleResult['message'],
                ];

                if ($googleResult['success'] && ! empty($googleResult['books'])) {
                    $transformedBooks = $transformer->transform($googleResult['books'], $includes);

                    // Filter out books with duplicate ISBNs
                    foreach ($transformedBooks as $book) {
                        $isDuplicate = false;

                        // Check if any ISBN already exists
                        $bookIsbns = array_filter([
                            $book['isbn_13'] ?? null,
                            $book['isbn_10'] ?? null,
                            $book['isbn'] ?? null,
                        ]);

                        foreach ($bookIsbns as $isbn) {
                            if (in_array($isbn, $usedIsbns)) {
                                $isDuplicate = true;
                                break;
                            }
                        }

                        if (! $isDuplicate) {
                            $allBooks[] = $book;

                            // Track new ISBNs
                            foreach ($bookIsbns as $isbn) {
                                $usedIsbns[] = $isbn;
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                $providerResults[] = [
                    'provider' => $googleBooks->getName(),
                    'success' => false,
                    'total_found' => 0,
                    'message' => 'Provider error: '.$e->getMessage(),
                ];
            }
        }

        // If we have books from either source, return success
        if (! empty($allBooks)) {
            $finalResult = $this->buildCombinedResult($allBooks, $query, $providerResults, $options);

            // Pass through debug info if available
            if ($amazonResult && isset($amazonResult['debug_info'])) {
                $finalResult['debug_info'] = $amazonResult['debug_info'];
            }

            Cache::put($cacheKey, $finalResult, self::CACHE_TTL_SUCCESS);

            return $finalResult;
        }

        // No provider found results - build failure response
        $failureResult = $this->buildFailureResult($query, $providerResults);
        Cache::put($cacheKey, $failureResult, self::CACHE_TTL_FAILURE);

        return $failureResult;
    }

    /**
     * Search using a specific provider (for testing/debugging)
     */
    public function searchWithSpecificProvider(string $providerName, string $query, array $options = []): array
    {
        $provider = $this->findProviderByName($providerName);

        if (! $provider) {
            return [
                'success' => false,
                'message' => "Provider '{$providerName}' not found",
                'books' => [],
                'total_found' => 0,
            ];
        }

        if (! $provider->isEnabled()) {
            return [
                'success' => false,
                'message' => "Provider '{$providerName}' is disabled",
                'books' => [],
                'total_found' => 0,
            ];
        }

        return $this->searchWithProvider($provider, $this->normalizeQuery($query), $options);
    }

    /**
     * Get search statistics
     */
    public function getSearchStats(): array
    {
        $activeProviders = $this->getActiveProviders();

        return [
            'total_providers' => count($this->providers),
            'active_providers' => count($activeProviders),
            'provider_details' => array_map(function ($provider) {
                return [
                    'name' => $provider->getName(),
                    'enabled' => $provider->isEnabled(),
                    'priority' => $provider->getPriority(),
                ];
            }, $activeProviders),
        ];
    }

    /**
     * Clear search cache
     */
    public function clearCache(): void
    {
        // This is a simple implementation - in production you might want
        // to use cache tags for more sophisticated cache management
        // Cache cleared
    }

    /**
     * Initialize all available providers
     */
    private function initializeProviders(): void
    {
        $this->providers = [
            new AmazonBooksProvider,
            new GoogleBooksProvider,
            new OpenLibraryProvider,
            // Future providers:
            // new ISBNdbProvider(),
        ];

        // Providers initialized
    }

    /**
     * Get active providers sorted by priority
     */
    private function getActiveProviders(): array
    {
        $activeProviders = array_filter($this->providers, function ($provider) {
            return $provider->isEnabled();
        });

        // Sort by priority (lower number = higher priority)
        usort($activeProviders, function ($a, $b) {
            return $a->getPriority() - $b->getPriority();
        });

        return $activeProviders;
    }

    /**
     * Find provider by name
     */
    private function findProviderByName(string $name): ?BookSearchProvider
    {
        foreach ($this->providers as $provider) {
            if ($provider->getName() === $name) {
                return $provider;
            }
        }

        return null;
    }

    /**
     * Search using a specific provider
     */
    private function searchWithProvider(BookSearchProvider $provider, string $query, array $options): array
    {
        $startTime = microtime(true);

        $result = $provider->search($query, $options);

        $duration = round((microtime(true) - $startTime) * 1000, 2);

        // Provider search completed

        return $result;
    }

    /**
     * Normalize search query
     */
    private function normalizeQuery(string $query): string
    {
        // Remove excessive whitespace
        $normalized = trim(preg_replace('/\s+/', ' ', $query));

        // If it looks like ISBN, clean it
        if ($this->looksLikeIsbn($normalized)) {
            $normalized = $this->normalizeIsbn($normalized);
        }

        return $normalized;
    }

    /**
     * Check if query looks like an ISBN
     */
    private function looksLikeIsbn(string $query): bool
    {
        $cleaned = preg_replace('/[^0-9X]/i', '', $query);

        return strlen($cleaned) === 10 || strlen($cleaned) === 13;
    }

    /**
     * Normalize ISBN by removing hyphens and spaces
     */
    private function normalizeIsbn(string $isbn): string
    {
        return preg_replace('/[^0-9X]/i', '', $isbn);
    }

    /**
     * Build cache key for query and options
     */
    private function buildCacheKey(string $query, array $options): string
    {
        $normalized = $this->normalizeQuery($query);
        $optionsHash = hash('sha256', serialize($options));

        return 'multi_search:'.hash('sha256', $normalized.$optionsHash);
    }

    /**
     * Build final successful result
     */
    private function buildFinalResult(array $result, string $originalQuery, array $providerResults, array $options = []): array
    {
        $perPage = $options['maxResults'] ?? 20;
        $totalFound = $result['total_found'];

        // Structure response with pagination meta similar to database queries
        return [
            'data' => $result['books'] ?? [],
            'meta' => [
                'current_page' => 1, // External API always returns page 1
                'from' => 1,
                'last_page' => 1, // External search is single page
                'per_page' => $perPage,
                'to' => count($result['books'] ?? []),
                'total' => $totalFound,
            ],
            'search_info' => [
                'provider' => $result['provider'],
                'original_query' => $originalQuery,
                'search_strategy' => 'multi_source',
                'providers_tried' => $providerResults,
                'cached_at' => now()->toISOString(),
            ],
        ];
    }

    /**
     * Build combined result from multiple providers
     */
    private function buildCombinedResult(array $books, string $originalQuery, array $providerResults, array $options = []): array
    {
        $perPage = $options['maxResults'] ?? 20;
        $totalFound = count($books);

        // Determine providers used
        $providersUsed = array_filter($providerResults, function ($provider) {
            return $provider['success'] && $provider['total_found'] > 0;
        });

        $providerNames = array_column($providersUsed, 'provider');
        $providerString = implode(' + ', $providerNames);

        // Structure response with pagination meta similar to database queries
        return [
            'data' => $books,
            'meta' => [
                'current_page' => 1,
                'from' => 1,
                'last_page' => 1,
                'per_page' => $perPage,
                'to' => $totalFound,
                'total' => $totalFound,
            ],
            'search_info' => [
                'provider' => $providerString,
                'original_query' => $originalQuery,
                'search_strategy' => 'amazon_plus_google',
                'providers_tried' => $providerResults,
                'cached_at' => now()->toISOString(),
            ],
        ];
    }

    /**
     * Build failure result when no provider found results
     */
    private function buildFailureResult(string $query, array $providerResults): array
    {
        return [
            'data' => [],
            'meta' => [
                'current_page' => 1,
                'from' => null,
                'last_page' => 1,
                'per_page' => 20,
                'to' => null,
                'total' => 0,
            ],
            'search_info' => [
                'provider' => 'Multi-Source',
                'original_query' => $query,
                'search_strategy' => 'multi_source',
                'providers_tried' => $providerResults,
                'cached_at' => now()->toISOString(),
                'suggestions' => $this->buildSearchSuggestions($query),
            ],
        ];
    }

    /**
     * Build search suggestions for failed queries
     */
    private function buildSearchSuggestions(string $query): array
    {
        $suggestions = [];

        // If looks like ISBN but failed, suggest title search
        if ($this->looksLikeIsbn($query)) {
            $suggestions[] = 'Try searching by book title instead of ISBN';
            $suggestions[] = 'Verify the ISBN is correct and try again';
        } else {
            $suggestions[] = 'Try using more specific keywords';
            $suggestions[] = 'Search by ISBN if you have it';
            $suggestions[] = 'Check spelling of title and author name';
        }

        return $suggestions;
    }

    /**
     * Enrich Amazon book results with Google IDs for easier book creation
     * This allows users to add books from Amazon search results to their library
     */
    private function enrichAmazonBooksWithGoogleIds(array $books): array
    {
        $enrichedBooks = [];

        foreach ($books as $book) {
            $enrichedBook = $book;

            // Only try to find Google ID if book doesn't already have one and has an ISBN
            if (empty($book['google_id']) && ! empty($book['isbn'])) {
                $googleId = $this->findGoogleIdByIsbn($book['isbn']);
                if ($googleId) {
                    $enrichedBook['google_id'] = $googleId;
                }
            }

            $enrichedBooks[] = $enrichedBook;
        }

        return $enrichedBooks;
    }

    /**
     * Find Google Books ID by ISBN using a quick API lookup
     */
    private function findGoogleIdByIsbn(string $isbn): ?string
    {
        try {
            // Use Google Books API to find the book by ISBN
            $response = \Illuminate\Support\Facades\Http::timeout(3)->get('https://www.googleapis.com/books/v1/volumes', [
                'q' => "isbn:{$isbn}",
                'maxResults' => 1,
                'key' => config('services.google_books.api_key'),
            ]);

            if ($response->successful()) {
                $data = $response->json();
                if (! empty($data['items'][0]['id'])) {
                    return $data['items'][0]['id'];
                }
            }
        } catch (\Exception $e) {
            // Silently fail - we don't want to break the search if Google Books lookup fails
            \Illuminate\Support\Facades\Log::info('Failed to find Google ID for ISBN', [
                'isbn' => $isbn,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }
}
