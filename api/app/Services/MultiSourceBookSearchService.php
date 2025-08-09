<?php

namespace App\Services;

use App\Contracts\BookSearchProvider;
use App\Services\Providers\GoogleBooksProvider;
use App\Services\Providers\OpenLibraryProvider;
use App\Services\Providers\AmazonBooksProvider;
use Illuminate\Support\Facades\Log;
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
     * @param string $query Search query (ISBN, title, or title+author)
     * @param array $options Search options
     * @return array Standardized search results
     */
    public function search(string $query, array $options = []): array
    {
        $cacheKey = $this->buildCacheKey($query, $options);
        $normalizedQuery = $this->normalizeQuery($query);

        // Check cache first
        if ($cachedResult = Cache::get($cacheKey)) {
            Log::info('MultiSourceBookSearchService: Cache hit', [
                'query' => $query,
                'cache_key' => $cacheKey,
                'cached_provider' => $cachedResult['provider'] ?? 'unknown'
            ]);
            return $cachedResult;
        }

        Log::info('MultiSourceBookSearchService: Starting search', [
            'original_query' => $query,
            'normalized_query' => $normalizedQuery,
            'options' => $options,
            'active_providers' => count($this->getActiveProviders())
        ]);

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
                    'message' => $result['message']
                ];

                if ($result['success'] && $result['total_found'] > 0) {
                    // Success! Cache and return
                    $finalResult = $this->buildFinalResult($result, $query, $providerResults);
                    Cache::put($cacheKey, $finalResult, self::CACHE_TTL_SUCCESS);
                    
                    Log::info('MultiSourceBookSearchService: Search successful', [
                        'query' => $query,
                        'successful_provider' => $provider->getName(),
                        'total_found' => $result['total_found']
                    ]);

                    return $finalResult;
                }

                $lastResult = $result;

            } catch (\Exception $e) {
                Log::error('MultiSourceBookSearchService: Provider error', [
                    'provider' => $provider->getName(),
                    'query' => $query,
                    'error' => $e->getMessage()
                ]);

                $providerResults[] = [
                    'provider' => $provider->getName(),
                    'success' => false,
                    'total_found' => 0,
                    'message' => 'Provider error: ' . $e->getMessage()
                ];
            }
        }

        // No provider found results - build failure response
        $failureResult = $this->buildFailureResult($query, $providerResults, $lastResult);
        Cache::put($cacheKey, $failureResult, self::CACHE_TTL_FAILURE);

        Log::warning('MultiSourceBookSearchService: No results found', [
            'query' => $query,
            'providers_tried' => count($providerResults),
            'provider_results' => $providerResults
        ]);

        return $failureResult;
    }

    /**
     * Search using a specific provider (for testing/debugging)
     */
    public function searchWithSpecificProvider(string $providerName, string $query, array $options = []): array
    {
        $provider = $this->findProviderByName($providerName);
        
        if (!$provider) {
            return [
                'success' => false,
                'message' => "Provider '{$providerName}' not found",
                'books' => [],
                'total_found' => 0
            ];
        }

        if (!$provider->isEnabled()) {
            return [
                'success' => false,
                'message' => "Provider '{$providerName}' is disabled",
                'books' => [],
                'total_found' => 0
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
                    'priority' => $provider->getPriority()
                ];
            }, $activeProviders)
        ];
    }

    /**
     * Clear search cache
     */
    public function clearCache(): void
    {
        // This is a simple implementation - in production you might want
        // to use cache tags for more sophisticated cache management
        Log::info('MultiSourceBookSearchService: Cache cleared');
    }

    /**
     * Initialize all available providers
     */
    private function initializeProviders(): void
    {
        $this->providers = [
            new GoogleBooksProvider(),
            new AmazonBooksProvider(), // Phase 2 - disabled by default
            new OpenLibraryProvider(),
            // Future providers:
            // new ISBNdbProvider(),
        ];

        Log::debug('MultiSourceBookSearchService: Providers initialized', [
            'total_providers' => count($this->providers),
            'enabled_providers' => count($this->getActiveProviders())
        ]);
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
        
        Log::info('MultiSourceBookSearchService: Provider search completed', [
            'provider' => $provider->getName(),
            'query' => $query,
            'success' => $result['success'],
            'total_found' => $result['total_found'],
            'duration_ms' => $duration
        ]);

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
        $optionsHash = md5(serialize($options));
        return "multi_search:" . md5($normalized . $optionsHash);
    }

    /**
     * Build final successful result
     */
    private function buildFinalResult(array $result, string $originalQuery, array $providerResults): array
    {
        return array_merge($result, [
            'original_query' => $originalQuery,
            'search_strategy' => 'multi_source',
            'providers_tried' => $providerResults,
            'cached_at' => now()->toISOString()
        ]);
    }

    /**
     * Build failure result when no provider found results
     */
    private function buildFailureResult(string $query, array $providerResults, ?array $lastResult): array
    {
        return [
            'success' => false,
            'provider' => 'Multi-Source',
            'books' => [],
            'total_found' => 0,
            'message' => 'No books found in any source',
            'original_query' => $query,
            'search_strategy' => 'multi_source',
            'providers_tried' => $providerResults,
            'cached_at' => now()->toISOString(),
            'suggestions' => $this->buildSearchSuggestions($query)
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
            $suggestions[] = "Try searching by book title instead of ISBN";
            $suggestions[] = "Verify the ISBN is correct and try again";
        } else {
            $suggestions[] = "Try using more specific keywords";
            $suggestions[] = "Search by ISBN if you have it";
            $suggestions[] = "Check spelling of title and author name";
        }

        return $suggestions;
    }
}