<?php

namespace App\Services;

use App\Models\Book;
use App\Services\AmazonLinkEnrichmentService;
use App\Services\MultiSourceBookSearchService;
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
     * @param string $query Search query
     * @param array $options Search options
     * @return array Combined search results
     */
    public function search(string $query, array $options = []): array
    {
        $includes = $options['includes'] ?? [];
        $maxResults = $options['maxResults'] ?? 20;
        $transformer = new BookTransformer();

        // Step 1: Search local database
        $localResults = $this->searchLocalDatabase($query, $maxResults);
        $localBooks = $transformer->transform($localResults, $includes);

        // Step 2: Search external APIs
        $externalResults = $this->multiSourceService->search($query, $options);
        $externalBooks = $externalResults['data'] ?? [];

        // Step 3: Remove duplicates by ISBN and merge results
        $combinedBooks = $this->mergeAndDeduplicateResults($localBooks, $externalBooks);

        // Step 4: Limit to requested maxResults
        $finalBooks = array_slice($combinedBooks, 0, $maxResults);

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
        $searchQuery->orderByRaw("
            CASE 
                WHEN title = ? THEN 1
                WHEN title LIKE ? THEN 2
                WHEN authors LIKE ? THEN 3
                ELSE 4
            END
        ", [$query, "{$query}%", "%{$query}%"]);

        return $searchQuery->limit($limit * 2)->get()->toArray(); // Get more to allow for deduplication
    }

    /**
     * Merge and deduplicate results from local and external sources
     */
    private function mergeAndDeduplicateResults(array $localBooks, array $externalBooks): array
    {
        $seenIsbns = [];
        $combinedBooks = [];

        // Add local books first (they have priority)
        foreach ($localBooks as $book) {
            $isbn = $this->extractPrimaryIsbn($book);
            
            if ($isbn && !in_array($isbn, $seenIsbns)) {
                $seenIsbns[] = $isbn;
                $book['source'] = 'local';
                $combinedBooks[] = $book;
            } elseif (!$isbn) {
                // Books without ISBN are always added (can't deduplicate)
                $book['source'] = 'local';
                $combinedBooks[] = $book;
            }
        }

        // Add external books, skipping duplicates
        foreach ($externalBooks as $book) {
            $isbn = $this->extractPrimaryIsbn($book);
            
            if ($isbn && !in_array($isbn, $seenIsbns)) {
                $seenIsbns[] = $isbn;
                $book['source'] = 'external';
                $combinedBooks[] = $book;
            } elseif (!$isbn) {
                // Books without ISBN are always added
                $book['source'] = 'external';
                $combinedBooks[] = $book;
            }
        }

        return $combinedBooks;
    }

    /**
     * Extract primary ISBN for deduplication
     */
    private function extractPrimaryIsbn(array $book): ?string
    {
        // First try the main ISBN field
        if (!empty($book['isbn'])) {
            return $book['isbn'];
        }
        
        // Then try external API ISBN fields (isbn_13, isbn_10)
        if (!empty($book['isbn_13'])) {
            return $book['isbn_13'];
        }
        
        if (!empty($book['isbn_10'])) {
            return $book['isbn_10'];
        }
        
        // Try to extract from industry_identifiers JSON (for local books)
        if (!empty($book['industry_identifiers'])) {
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
}