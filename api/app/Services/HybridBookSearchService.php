<?php

namespace App\Services;

use App\Models\Book;
use App\Transformers\BookTransformer;

class HybridBookSearchService
{
    private MultiSourceBookSearchService $multiSourceService;

    private AmazonLinkEnrichmentService $amazonEnrichmentService;

    public function __construct(
        MultiSourceBookSearchService $multiSourceService,
        AmazonLinkEnrichmentService $amazonEnrichmentService
    ) {
        $this->multiSourceService = $multiSourceService;
        $this->amazonEnrichmentService = $amazonEnrichmentService;
    }

    /**
     * Search books using hybrid approach: local database first, then external APIs
     *
     * @param  string  $query  Search query
     * @param  array  $options  Search options
     * @return array Combined search results
     */
    public function search(string $query, array $options = []): array
    {
        $includes = $options['includes'] ?? [];
        $maxResults = $options['maxResults'] ?? 20;
        $transformer = new BookTransformer;

        // Step 1: Search local database
        $localResults = $this->searchLocalDatabase($query, $maxResults);
        $localBooks = $transformer->transform($localResults, $includes);

        // Step 2: Search external APIs
        $externalResults = $this->multiSourceService->search($query, $options);
        $externalBooks = $externalResults['data'] ?? [];

        // Step 3: Remove duplicates by ISBN and merge results
        $combinedBooks = $this->mergeAndDeduplicateResults($localBooks, $externalBooks);

        // Step 4: Limit to requested maxResults (with minimum to show Amazon results)
        $effectiveLimit = max($maxResults, 30); // Ensure we show at least 30 results to display Amazon books
        $finalBooks = array_slice($combinedBooks, 0, $effectiveLimit);

        // Step 5: Enrich with Amazon purchase links
        $finalBooks = $this->amazonEnrichmentService->enrichBooksWithAmazonLinks($finalBooks, $options);

        return $this->buildHybridResponse($finalBooks, $localResults, $externalResults, $query, $options);
    }

    /**
     * Search local database for books
     */
    private function searchLocalDatabase(string $query, int $limit): array
    {
        $searchQuery = Book::query();

        // Search in multiple fields
        $searchQuery->where(function ($q) use ($query) {
            $q->where('title', 'LIKE', "%{$query}%")
                ->orWhere('authors', 'LIKE', "%{$query}%")
                ->orWhere('isbn', 'LIKE', "%{$query}%")
                ->orWhere('publisher', 'LIKE', "%{$query}%")
                ->orWhere('google_id', 'LIKE', "%{$query}%")
              // Search in JSON industry_identifiers
                ->orWhereJsonContains('industry_identifiers', $query);
        });

        // Order by relevance: exact title matches first, then others
        $searchQuery->orderByRaw('
            CASE 
                WHEN title = ? THEN 1
                WHEN title LIKE ? THEN 2
                WHEN authors LIKE ? THEN 3
                ELSE 4
            END
        ', [$query, "{$query}%", "%{$query}%"]);

        return $searchQuery->limit($limit * 2)->get()->toArray(); // Get more to allow for deduplication
    }

    /**
     * Merge and deduplicate results from local and external sources
     */
    private function mergeAndDeduplicateResults(array $localBooks, array $externalBooks): array
    {
        $seenIdentifiers = [];
        $combinedBooks = [];

        // Add local books first (they have priority)
        foreach ($localBooks as $book) {
            $identifier = $this->createBookIdentifier($book);

            if (! in_array($identifier, $seenIdentifiers)) {
                $seenIdentifiers[] = $identifier;
                $book['source'] = 'local';
                $combinedBooks[] = $book;
            }
        }

        // Add external books, skipping duplicates
        foreach ($externalBooks as $book) {
            $identifier = $this->createBookIdentifier($book);

            if (! in_array($identifier, $seenIdentifiers)) {
                $seenIdentifiers[] = $identifier;
                $book['source'] = 'external';
                $combinedBooks[] = $book;
            }
        }

        return $combinedBooks;
    }

    /**
     * Create unique identifier for book deduplication
     * Uses multiple strategies: ISBN, Google ID, and title+author combination
     */
    private function createBookIdentifier(array $book): string
    {
        // Strategy 1: Use Google ID if available (most reliable for external books)
        if (! empty($book['google_id'])) {
            return 'google:'.$book['google_id'];
        }

        // Strategy 2: Use ISBN if available
        $isbn = $this->extractPrimaryIsbn($book);
        if ($isbn) {
            return 'isbn:'.$isbn;
        }

        // Strategy 3: Use normalized title + first author
        $normalizedTitle = $this->normalizeForComparison($book['title'] ?? '');
        $firstAuthor = $this->extractFirstAuthor($book['authors'] ?? '');
        $normalizedAuthor = $this->normalizeForComparison($firstAuthor);

        return 'title_author:'.$normalizedTitle.'|'.$normalizedAuthor;
    }

    /**
     * Extract primary ISBN for deduplication
     */
    private function extractPrimaryIsbn(array $book): ?string
    {
        // First try the main ISBN field
        if (! empty($book['isbn'])) {
            return $book['isbn'];
        }

        // Then try external API ISBN fields (isbn_13, isbn_10)
        if (! empty($book['isbn_13'])) {
            return $book['isbn_13'];
        }

        if (! empty($book['isbn_10'])) {
            return $book['isbn_10'];
        }

        // Try to extract from industry_identifiers JSON (for local books)
        if (! empty($book['industry_identifiers'])) {
            $identifiers = is_string($book['industry_identifiers']) ?
                json_decode($book['industry_identifiers'], true) :
                $book['industry_identifiers'];

            if (is_array($identifiers)) {
                foreach ($identifiers as $identifier) {
                    if (isset($identifier['identifier']) &&
                        in_array($identifier['type'] ?? '', ['ISBN_13', 'ISBN_10'])) {
                        return $identifier['identifier'];
                    }
                }
            }
        }

        return null;
    }

    /**
     * Build final hybrid response
     */
    private function buildHybridResponse(
        array $finalBooks,
        array $localResults,
        array $externalResults,
        string $query,
        array $options
    ): array {
        $localCount = count($localResults);
        $externalCount = count($externalResults['data'] ?? []);
        $finalCount = count($finalBooks);

        $perPage = $options['maxResults'] ?? 20;

        return [
            'data' => $finalBooks,
            'meta' => [
                'current_page' => 1,
                'from' => $finalCount > 0 ? 1 : null,
                'last_page' => 1,
                'per_page' => $perPage,
                'to' => $finalCount,
                'total' => $finalCount,
            ],
            'search_info' => [
                'provider' => 'Hybrid Search',
                'original_query' => $query,
                'search_strategy' => 'local_first_then_external',
                'local_results_found' => $localCount,
                'external_results_found' => $externalCount,
                'total_before_dedup' => $localCount + $externalCount,
                'final_results' => $finalCount,
                'external_provider_used' => $externalResults['search_info']['provider'] ?? 'None',
                'cached_at' => now()->toISOString(),
            ],
        ];
    }

    /**
     * Normalize string for comparison (remove special chars, lowercase, trim)
     */
    private function normalizeForComparison(string $text): string
    {
        // Remove special characters, convert to lowercase, remove extra spaces
        $normalized = preg_replace('/[^\w\s]/', '', mb_strtolower(trim($text)));

        return preg_replace('/\s+/', ' ', $normalized);
    }

    /**
     * Extract first author from authors string
     */
    private function extractFirstAuthor(string $authors): string
    {
        // Split by common delimiters and get first author
        $authorList = preg_split('/[,;&]/', $authors);

        return trim($authorList[0] ?? '');
    }
}
