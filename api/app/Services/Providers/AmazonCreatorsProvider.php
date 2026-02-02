<?php

namespace App\Services\Providers;

use App\Contracts\BookSearchProvider;
use App\Services\Amazon\AmazonCreatorsApiClient;
use App\Services\Amazon\Traits\TransformsAmazonItems;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AmazonCreatorsProvider implements BookSearchProvider
{
    use TransformsAmazonItems;

    private const PRIORITY = 1;

    private const MAX_RESULTS = 10;

    private const MAX_PAGES = 2;

    private AmazonCreatorsApiClient $apiClient;

    public function __construct(AmazonCreatorsApiClient $apiClient)
    {
        $this->apiClient = $apiClient;
    }

    public function search(string $query, array $options = []): array
    {
        if (! $this->isEnabled()) {
            return $this->buildErrorResponse('Amazon Creators provider is disabled');
        }

        try {
            if ($this->isRateLimited($query)) {
                return $this->buildErrorResponse('Amazon API rate limited - try again later');
            }

            $searchQuery = $this->buildSearchQuery($query, $options);
            $region = $this->detectOptimalRegion($options);

            $searchResults = $this->performSearch($searchQuery, $region, $options);

            if (empty($searchResults)) {
                return $this->buildErrorResponse('No books found');
            }

            $books = $this->transformSearchResults($searchResults);

            return $this->buildSuccessResponse($books, count($books));

        } catch (\Exception $e) {
            Log::error('Amazon Creators Provider error', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);

            return $this->buildErrorResponse('Search failed: '.$e->getMessage());
        }
    }

    /**
     * Get items by ASINs
     *
     * @param  array  $asins  Array of ASINs (max 10 per request)
     * @param  array  $options  Optional parameters (region, etc.)
     * @return array Response with success status and book data
     */
    public function getItems(array $asins, array $options = []): array
    {
        if (! $this->isEnabled()) {
            return $this->buildErrorResponse('Amazon Creators provider is disabled');
        }

        if (empty($asins)) {
            return $this->buildErrorResponse('No ASINs provided');
        }

        $asins = array_slice($asins, 0, 10);

        try {
            $region = $this->detectOptimalRegion($options);

            Log::info('Amazon Creators GetItems Request', [
                'asins' => $asins,
                'region' => $region,
                'count' => count($asins),
            ]);

            $response = $this->apiClient->getItems($asins, $region);

            $items = $response['ItemsResult']['Items'] ?? [];

            if (empty($items)) {
                return $this->buildErrorResponse('No items found for provided ASINs');
            }

            $books = $this->transformSearchResults($items);

            return $this->buildSuccessResponse($books, count($books));

        } catch (\Exception $e) {
            Log::error('Amazon Creators GetItems error', [
                'asins' => $asins,
                'error' => $e->getMessage(),
            ]);

            return $this->buildErrorResponse('GetItems failed: '.$e->getMessage());
        }
    }

    /**
     * Get variations (different editions) of a book by its ASIN
     *
     * @param  string  $asin  The parent ASIN to get variations for
     * @param  array  $options  Optional parameters (region, etc.)
     * @return array Response with success status and variations data
     */
    public function getVariations(string $asin, array $options = []): array
    {
        if (! $this->isEnabled()) {
            return $this->buildErrorResponse('Amazon Creators provider is disabled');
        }

        if (empty($asin)) {
            return $this->buildErrorResponse('No ASIN provided');
        }

        try {
            $region = $this->detectOptimalRegion($options);

            Log::info('Amazon Creators GetVariations Request', [
                'asin' => $asin,
                'region' => $region,
            ]);

            $response = $this->apiClient->getVariations($asin, $region);

            $variations = $response['VariationsResult']['Items'] ?? [];

            if (empty($variations)) {
                return $this->buildErrorResponse('No variations found for this ASIN');
            }

            $books = $this->transformSearchResults($variations);

            return $this->buildSuccessResponse($books, count($books));

        } catch (\Exception $e) {
            Log::error('Amazon Creators GetVariations error', [
                'asin' => $asin,
                'error' => $e->getMessage(),
            ]);

            return $this->buildErrorResponse('GetVariations failed: '.$e->getMessage());
        }
    }

    private function buildSearchQuery(string $query, array $options): string
    {
        if ($this->looksLikeIsbnQuery($query)) {
            return $this->normalizeIsbnQuery($query);
        }

        if (isset($options['title']) && isset($options['author'])) {
            return $options['title'].' '.$options['author'];
        }

        return trim($query);
    }

    private function detectOptimalRegion(array $options): string
    {
        if (isset($options['region'])) {
            return $options['region'];
        }

        // Default to Brazil
        return 'BR';
    }

    private function performSearch(string $searchQuery, string $region, array $options): array
    {
        $allItems = [];
        $maxPages = min($options['pages'] ?? self::MAX_PAGES, 10);

        for ($page = 1; $page <= $maxPages; $page++) {
            Log::info('Amazon Creators API Request', [
                'query' => $searchQuery,
                'region' => $region,
                'page' => $page,
                'max_pages' => $maxPages,
            ]);

            try {
                $response = $this->apiClient->searchItems($searchQuery, $region, [
                    'itemCount' => $options['maxResults'] ?? self::MAX_RESULTS,
                    'itemPage' => $page,
                ]);

                $items = $response['SearchResult']['Items'] ?? [];

                if (! empty($items)) {
                    $allItems = array_merge($allItems, $items);

                    // If we got fewer items than requested, no more pages available
                    if (count($items) < self::MAX_RESULTS) {
                        break;
                    }
                } else {
                    break;
                }

                // Small delay between pages
                if ($page < $maxPages) {
                    usleep(500000);
                }

            } catch (\Exception $e) {
                // If we fail on page 2+, return what we have
                if ($page > 1) {
                    Log::warning('Amazon Creators API pagination failed', [
                        'page' => $page,
                        'error' => $e->getMessage(),
                    ]);
                    break;
                }
                throw $e;
            }
        }

        return $allItems;
    }

    private function transformSearchResults(array $items): array
    {
        $books = [];

        foreach ($items as $item) {
            $book = $this->transformAmazonItemArray($item);
            if ($book) {
                $books[] = $book;
            }
        }

        return $books;
    }

    public function getName(): string
    {
        return 'Amazon Creators';
    }

    public function isEnabled(): bool
    {
        return config('services.amazon.creators_api.enabled', false)
            && $this->apiClient->isConfigured();
    }

    public function getPriority(): int
    {
        return self::PRIORITY;
    }

    private function buildSuccessResponse(array $books, int $totalFound): array
    {
        return [
            'success' => true,
            'provider' => $this->getName(),
            'books' => $books,
            'total_found' => $totalFound,
            'message' => "Found {$totalFound} books",
        ];
    }

    private function buildErrorResponse(string $message): array
    {
        return [
            'success' => false,
            'provider' => $this->getName(),
            'books' => [],
            'total_found' => 0,
            'message' => $message,
        ];
    }

    private function isRateLimited(string $query): bool
    {
        $cacheKey = 'amazon_rate_limited_'.md5($query);

        return Cache::has($cacheKey);
    }
}
