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

    private const CACHE_TTL_SUCCESS = 86400; // 24 hours for successful results

    private const CACHE_TTL_FAILURE = 3600;  // 1 hour for failed results

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

        $lastResult = null;
        $providerResults = [];

        // Try each provider in priority order
        foreach ($this->getActiveProviders() as $provider) {
            try {
                $result = $this->searchWithProvider($provider, $normalizedQuery, $options);
                $providerResults[] = [
                    'provider' => $provider->getName(),
                    'success' => $result['success'],
                    'total_found' => $result['total_found'],
                    'message' => $result['message'],
                ];

                if ($result['success'] && $result['total_found'] > 0) {
                    // Transform books based on requested fields
                    if (isset($result['books'])) {
                        $result['books'] = $transformer->transform($result['books'], $includes);
                    }

                    // Success! Cache and return with pagination meta
                    $finalResult = $this->buildFinalResult($result, $query, $providerResults, $options);
                    Cache::put($cacheKey, $finalResult, self::CACHE_TTL_SUCCESS);

                    return $finalResult;
                }

                $lastResult = $result;

            } catch (\Exception $e) {
                // Provider error - continue to next provider
                $providerResults[] = [
                    'provider' => $provider->getName(),
                    'success' => false,
                    'total_found' => 0,
                    'message' => 'Provider error: '.$e->getMessage(),
                ];
            }
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
            new GoogleBooksProvider,
            new AmazonBooksProvider, // Phase 2 - disabled by default
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
            'success' => $result['success'],
            'provider' => $result['provider'],
            'original_query' => $originalQuery,
            'search_strategy' => 'multi_source',
            'providers_tried' => $providerResults,
            'cached_at' => now()->toISOString(),
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
            'success' => false,
            'provider' => 'Multi-Source',
            'original_query' => $query,
            'search_strategy' => 'multi_source',
            'providers_tried' => $providerResults,
            'cached_at' => now()->toISOString(),
            'suggestions' => $this->buildSearchSuggestions($query),
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
}
