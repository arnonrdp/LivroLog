<?php

namespace App\Services\Providers;

use Amazon\ProductAdvertisingAPI\v1\ApiException;
use Amazon\ProductAdvertisingAPI\v1\com\amazon\paapi5\v1\api\DefaultApi;
use Amazon\ProductAdvertisingAPI\v1\com\amazon\paapi5\v1\PartnerType;
use Amazon\ProductAdvertisingAPI\v1\com\amazon\paapi5\v1\SearchItemsRequest;
use Amazon\ProductAdvertisingAPI\v1\com\amazon\paapi5\v1\SearchItemsResource;
use Amazon\ProductAdvertisingAPI\v1\Configuration;
use App\Contracts\BookSearchProvider;
use App\Models\Book;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class AmazonBooksProvider implements BookSearchProvider
{
    private const PRIORITY = 1;

    private const MAX_RESULTS = 10; // Amazon PA-API allows max 10 per request
    private const MAX_PAGES = 2; // Reduce to 2 pages (20 results total) for faster response

    private const RATE_LIMIT_DELAY = 1; // Reduce to 1 second between requests for faster response

    private array $regionConfig = [
        'US' => [
            'host' => 'webservices.amazon.com',
            'region' => 'us-east-1',
            'marketplace' => 'www.amazon.com',
            'associate_tag' => 'livrolog-20',
        ],
        'BR' => [
            'host' => 'webservices.amazon.com.br',
            'region' => 'us-east-1',
            'marketplace' => 'www.amazon.com.br',
            'associate_tag' => 'livrolog01-20',
        ],
        'UK' => [
            'host' => 'webservices.amazon.co.uk',
            'region' => 'eu-west-1',
            'marketplace' => 'www.amazon.co.uk',
            'associate_tag' => 'livrolog-20', // Use same tag as US for now
        ],
        'CA' => [
            'host' => 'webservices.amazon.ca',
            'region' => 'us-east-1',
            'marketplace' => 'www.amazon.ca',
            'associate_tag' => 'livrolog-20', // Use same tag as US for now
        ],
    ];

    public function search(string $query, array $options = []): array
    {
        if (! $this->isEnabled()) {
            return $this->buildErrorResponse('Amazon Books provider is disabled');
        }

        try {
            // Check if we're currently rate limited for this query
            if ($this->isRateLimited($query)) {
                return $this->buildErrorResponse('Amazon API rate limited - try again later');
            }

            // Implement rate limiting to avoid 429 errors
            $this->respectRateLimit();

            $searchQuery = $this->buildSearchQuery($query, $options);
            $region = $this->detectOptimalRegion($options);

            $searchResults = $this->performSearch($searchQuery, $region, $options);

            if (empty($searchResults)) {
                return $this->buildErrorResponse('No books found');
            }

            $books = $this->transformSearchResults($searchResults);

            return $this->buildSuccessResponse($books, count($books));

        } catch (ApiException $e) {
            Log::error('Amazon PA-API error', [
                'query' => $query,
                'error' => $e->getMessage(),
                'response_body' => $e->getResponseBody(),
            ]);

            return $this->buildErrorResponse('Amazon API error: '.$e->getMessage());

        } catch (\Exception $e) {
            Log::error('Amazon Books Provider error', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);

            return $this->buildErrorResponse('Search failed: '.$e->getMessage());
        }
    }

    private function buildSearchQuery(string $query, array $options): string
    {
        if ($this->looksLikeIsbn($query)) {
            return $this->normalizeIsbn($query);
        }

        if (isset($options['title']) && isset($options['author'])) {
            return $options['title'].' '.$options['author'];
        }

        return trim($query);
    }

    private function detectOptimalRegion(array $options): string
    {
        // Priority order: user preference > Brazil (most reliable) > locale-based fallback
        if (isset($options['region']) && isset($this->regionConfig[$options['region']])) {
            return $options['region'];
        }

        // Always prefer Brazil region first as it has the most reliable credentials
        // This ensures Amazon Books provider works consistently regardless of locale
        return 'BR';
    }

    private function performSearch(string $searchQuery, string $region, array $options): array
    {
        $api = $this->createApiClient($region);
        $allItems = [];
        $maxPages = min($options['pages'] ?? self::MAX_PAGES, 10); // API limit is 10 pages

        for ($page = 1; $page <= $maxPages; $page++) {
            $searchItemsRequest = $this->createSearchRequest($searchQuery, $region, $options);
            $searchItemsRequest->setItemPage($page);

            Log::info('Amazon Books API Request', [
                'query' => $searchQuery,
                'region' => $region,
                'page' => $page,
                'max_pages' => $maxPages,
            ]);

            try {
                $response = $api->searchItems($searchItemsRequest);

                if ($response->getErrors()) {
                    $error = $response->getErrors()[0];
                    // If we get an error on page 2+, just return what we have
                    if ($page > 1) {
                        break;
                    }
                    throw new \Exception($error->getMessage());
                }

                if ($response->getSearchResult() && $response->getSearchResult()->getItems()) {
                    $items = $response->getSearchResult()->getItems();
                    $allItems = array_merge($allItems, $items);

                    // If we got fewer items than requested, no more pages available
                    if (count($items) < self::MAX_RESULTS) {
                        break;
                    }
                } else {
                    // No more results
                    break;
                }

                // Reduce delay for faster response
                if ($page < $maxPages) {
                    usleep(500000); // 0.5 seconds instead of 1 second
                }
            } catch (\Exception $e) {
                // If we fail on page 2+, just return what we have
                if ($page > 1) {
                    Log::warning('Amazon API pagination failed', [
                        'page' => $page,
                        'error' => $e->getMessage()
                    ]);
                    break;
                }
                throw $e;
            }
        }

        return $allItems;
    }

    private function createApiClient(string $region): DefaultApi
    {
        $config = Configuration::getDefaultConfiguration();
        $config->setAccessKey(config('services.amazon.pa_api_key'));
        $config->setSecretKey(config('services.amazon.pa_secret_key'));
        $config->setHost($this->regionConfig[$region]['host']);
        $config->setRegion($this->regionConfig[$region]['region']);

        return new DefaultApi(new Client, $config);
    }

    private function createSearchRequest(string $searchQuery, string $region, array $options): SearchItemsRequest
    {
        $searchItemsRequest = new SearchItemsRequest;
        $searchItemsRequest->setSearchIndex('Books');
        $searchItemsRequest->setKeywords($searchQuery);
        $searchItemsRequest->setItemCount($options['maxResults'] ?? self::MAX_RESULTS);
        $searchItemsRequest->setPartnerTag($this->regionConfig[$region]['associate_tag']);
        $searchItemsRequest->setPartnerType(PartnerType::ASSOCIATES);
        $searchItemsRequest->setMarketplace($this->regionConfig[$region]['marketplace']);

        // Request comprehensive book information (constant names per thewirecutter/paapi5-php-sdk)
        $searchItemsRequest->setResources([
            SearchItemsResource::ITEM_INFOTITLE,
            SearchItemsResource::ITEM_INFOFEATURES,
            SearchItemsResource::ITEM_INFOBY_LINE_INFO,
            SearchItemsResource::ITEM_INFOCONTENT_INFO,
            SearchItemsResource::ITEM_INFOCONTENT_RATING,
            SearchItemsResource::ITEM_INFOCLASSIFICATIONS,  // To better identify product type
            SearchItemsResource::ITEM_INFOPRODUCT_INFO,
            SearchItemsResource::ITEM_INFOTECHNICAL_INFO,
            SearchItemsResource::ITEM_INFOEXTERNAL_IDS,  // For ISBN data
            SearchItemsResource::IMAGESPRIMARYLARGE,
            SearchItemsResource::IMAGESPRIMARYMEDIUM,
            SearchItemsResource::IMAGESPRIMARYSMALL,
            SearchItemsResource::OFFERSLISTINGSPRICE,
            // Availability constants vary by SDK fork; omit to avoid undefined constant issues
        ]);

        return $searchItemsRequest;
    }

    private function transformSearchResults(array $items): array
    {
        $books = [];

        foreach ($items as $item) {
            $book = $this->transformAmazonItem($item);
            if ($book) {
                $books[] = $book;
            }
        }

        return $books;
    }

    private function transformAmazonItem($item): ?array
    {
        $itemInfo = $item->getItemInfo();
        if (! $itemInfo || ! $itemInfo->getTitle() || ! $itemInfo->getTitle()->getDisplayValue()) {
            return null;
        }

        // Extract basic info
        $title = $itemInfo->getTitle()->getDisplayValue();
        $authors = $this->extractAuthors($itemInfo);
        $isbn = $this->extractIsbn($itemInfo);
        $asin = $item->getASIN();

        // Debug logging removed for performance

        // Never use ASIN as ISBN
        if ($isbn && preg_match('/^B[0-9A-Z]{9}$/i', $isbn)) {
            Log::warning('ASIN detected in ISBN field', [
                'title' => $title,
                'asin' => $asin,
                'isbn_before' => $isbn,
            ]);
            $isbn = null;
        }

        // Filter out non-book items using classifications
        if (!$this->isActualBook($item, $itemInfo)) {
            return null;
        }

        // Check if book already exists
        $existingBook = null;
        if ($isbn) {
            $existingBook = Book::where('isbn', $isbn)->first();
        }
        if (! $existingBook && $asin) {
            $existingBook = Book::where('amazon_asin', $asin)->first();
        }

        $bookData = [
            'provider' => $this->getName(),
            'amazon_asin' => $asin,
            'title' => $title,
            'subtitle' => null, // Amazon doesn't typically separate subtitle
            'authors' => $authors,
            'isbn' => $isbn, // Never use ASIN as ISBN - they are different identifiers
            'isbn_10' => $this->extractSpecificIsbn($itemInfo, 'ISBN10'),
            'isbn_13' => $this->extractSpecificIsbn($itemInfo, 'ISBN13'),
            'thumbnail' => $this->extractThumbnail($item),
            'description' => $this->extractDescription($itemInfo),
            'publisher' => $this->extractPublisher($itemInfo),
            'published_date' => $this->extractPublishedDate($itemInfo),
            'page_count' => $this->extractPageCount($itemInfo),
            'language' => $this->extractLanguage($itemInfo),
            'categories' => $this->extractCategories($itemInfo),
            'maturity_rating' => $this->extractMaturityRating($itemInfo),
            'preview_link' => null, // Amazon doesn't provide preview links via PA-API
            'info_link' => $item->getDetailPageURL(),
        ];

        if ($existingBook) {
            $bookData['id'] = $existingBook->id;
        }

        return $bookData;
    }

    private function extractAuthors($itemInfo): ?string
    {
        $byLineInfo = $itemInfo->getByLineInfo();
        if (! $byLineInfo || ! $byLineInfo->getContributors()) {
            return null;
        }

        $authors = [];
        foreach ($byLineInfo->getContributors() as $contributor) {
            if ($contributor->getName()) {
                $authors[] = $contributor->getName();
            }
        }

        return ! empty($authors) ? implode(', ', $authors) : null;
    }

    private function extractIsbn($itemInfo): ?string
    {
        // First try ExternalIds (the proper way)
        $externalIds = $itemInfo->getExternalIds();
        if ($externalIds) {
            // Try ISBN-13 first from ExternalIds
            if (method_exists($externalIds, 'getISBN13s')) {
                $isbn13s = $externalIds->getISBN13s();
                if ($isbn13s && $isbn13s->getDisplayValues()) {
                    $values = $isbn13s->getDisplayValues();
                    if (!empty($values) && $this->isValidIsbn($values[0])) {
                        return $values[0];
                    }
                }
            }

            // Try ISBN-10 from ExternalIds
            if (method_exists($externalIds, 'getISBN10s')) {
                $isbn10s = $externalIds->getISBN10s();
                if ($isbn10s && $isbn10s->getDisplayValues()) {
                    $values = $isbn10s->getDisplayValues();
                    if (!empty($values) && $this->isValidIsbn($values[0])) {
                        return $values[0];
                    }
                }
            }

            // Try EAN codes (which may contain ISBN-13)
            if (method_exists($externalIds, 'getEANs')) {
                $eans = $externalIds->getEANs();
                if ($eans && $eans->getDisplayValues()) {
                    $values = $eans->getDisplayValues();
                    foreach ($values as $ean) {
                        // EAN-13 codes starting with 978 or 979 are often ISBN-13
                        if (strlen($ean) === 13 && (str_starts_with($ean, '978') || str_starts_with($ean, '979'))) {
                            if ($this->isValidIsbn($ean)) {
                                return $ean;
                            }
                        }
                    }
                }
            }
        }

        // Fallback to old method (ProductInfo)
        return $this->extractSpecificIsbn($itemInfo, 'ISBN13')
            ?? $this->extractSpecificIsbn($itemInfo, 'ISBN10');
    }

    private function extractSpecificIsbn($itemInfo, string $type): ?string
    {
        $productInfo = $itemInfo->getProductInfo();
        if (! $productInfo) {
            return null;
        }

        // Check different possible locations for ISBN
        $isbnSources = [
            $productInfo->getItemDimensions(),
            $productInfo->getSize(),
            $productInfo->getUnitCount(),
        ];

        foreach ($isbnSources as $source) {
            if ($source && method_exists($source, 'get'.$type)) {
                $isbn = call_user_func([$source, 'get'.$type]);
                if ($isbn && $isbn->getDisplayValue()) {
                    $value = $isbn->getDisplayValue();

                    // Validate that it's actually an ISBN format
                    if ($this->isValidIsbn($value)) {
                        return $value;
                    }
                }
            }
        }

        return null;
    }

    private function extractThumbnail($item): ?string
    {
        $images = $item->getImages();
        if (! $images) {
            return null;
        }

        $primary = $images->getPrimary();
        if (! $primary) {
            return null;
        }

        // Prefer medium, fallback to large, then small
        if ($primary->getMedium() && $primary->getMedium()->getURL()) {
            return $primary->getMedium()->getURL();
        }

        if ($primary->getLarge() && $primary->getLarge()->getURL()) {
            return $primary->getLarge()->getURL();
        }

        if ($primary->getSmall() && $primary->getSmall()->getURL()) {
            return $primary->getSmall()->getURL();
        }

        return null;
    }

    private function extractDescription($itemInfo): ?string
    {
        $features = $itemInfo->getFeatures();
        if ($features && $features->getDisplayValues()) {
            return implode(' ', $features->getDisplayValues());
        }

        return null;
    }

    private function extractPublisher($itemInfo): ?string
    {
        $byLineInfo = $itemInfo->getByLineInfo();
        if ($byLineInfo && $byLineInfo->getManufacturer() && $byLineInfo->getManufacturer()->getDisplayValue()) {
            return $byLineInfo->getManufacturer()->getDisplayValue();
        }

        return null;
    }

    private function extractPublishedDate($itemInfo): ?string
    {
        $contentInfo = $itemInfo->getContentInfo();
        if ($contentInfo && $contentInfo->getPublicationDate() && $contentInfo->getPublicationDate()->getDisplayValue()) {
            return $contentInfo->getPublicationDate()->getDisplayValue();
        }

        return null;
    }

    private function extractPageCount($itemInfo): ?int
    {
        $contentInfo = $itemInfo->getContentInfo();
        if ($contentInfo && $contentInfo->getPagesCount() && $contentInfo->getPagesCount()->getDisplayValue()) {
            return (int) $contentInfo->getPagesCount()->getDisplayValue();
        }

        return null;
    }

    private function extractLanguage($itemInfo): ?string
    {
        $contentInfo = $itemInfo->getContentInfo();
        if ($contentInfo && $contentInfo->getLanguages()) {
            $languages = $contentInfo->getLanguages();
            if ($languages->getDisplayValues()) {
                return $languages->getDisplayValues()[0]; // Return first language
            }
        }

        return null;
    }

    private function extractCategories($itemInfo): ?array
    {
        $classifications = $itemInfo->getClassifications();
        if (! $classifications) {
            return null;
        }

        $categories = [];
        if ($classifications->getBinding() && $classifications->getBinding()->getDisplayValue()) {
            $categories[] = $classifications->getBinding()->getDisplayValue();
        }

        if ($classifications->getProductGroup() && $classifications->getProductGroup()->getDisplayValue()) {
            $categories[] = $classifications->getProductGroup()->getDisplayValue();
        }

        return ! empty($categories) ? $categories : null;
    }

    private function extractMaturityRating($itemInfo): ?string
    {
        $contentRating = $itemInfo->getContentRating();
        if ($contentRating && $contentRating->getAudienceRating() && $contentRating->getAudienceRating()->getDisplayValue()) {
            return $contentRating->getAudienceRating()->getDisplayValue();
        }

        return null;
    }

    private function looksLikeIsbn(string $query): bool
    {
        $cleaned = preg_replace('/[^0-9X]/i', '', $query);

        return strlen($cleaned) === 10 || strlen($cleaned) === 13;
    }

    private function normalizeIsbn(string $isbn): string
    {
        return preg_replace('/[^0-9X]/i', '', $isbn);
    }

    public function getName(): string
    {
        return 'Amazon Books';
    }

    public function isEnabled(): bool
    {
        // Auto-enable if all credentials are present
        return ! empty(config('services.amazon.pa_api_key'))
            && ! empty(config('services.amazon.pa_secret_key'));
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

    /**
     * Implement rate limiting to avoid 429 Too Many Requests errors
     * Amazon PA-API allows 1 request per second for new accounts
     */
    private function respectRateLimit(): void
    {
        $cacheKey = 'amazon_paapi_last_request';
        $lastRequestTime = \Illuminate\Support\Facades\Cache::get($cacheKey, 0);
        $currentTime = time();

        $timeSinceLastRequest = $currentTime - $lastRequestTime;

        if ($timeSinceLastRequest < self::RATE_LIMIT_DELAY) {
            $sleepTime = self::RATE_LIMIT_DELAY - $timeSinceLastRequest;
            usleep($sleepTime * 1000000); // Use microseconds for more precision
        }

        \Illuminate\Support\Facades\Cache::put($cacheKey, time(), 60); // Cache for 1 minute
    }

    /**
     * Check if Amazon API is currently rate limited for this query
     */
    private function isRateLimited(string $query): bool
    {
        $amazonCacheKey = 'amazon_rate_limited_' . md5($query);
        return \Illuminate\Support\Facades\Cache::has($amazonCacheKey);
    }

    /**
     * Check if the item is actually a book and not a toy, game, or other non-book item
     */
    private function isActualBook($item, $itemInfo): bool
    {
        // Check classifications if available
        $classifications = $itemInfo->getClassifications();
        if ($classifications) {
            $binding = $classifications->getBinding();
            if ($binding && $binding->getDisplayValue()) {
                $bindingType = strtolower($binding->getDisplayValue());

                // These are actual book bindings
                $bookBindings = [
                    'paperback', 'hardcover', 'kindle', 'ebook',
                    'mass market', 'library binding', 'board book',
                    'spiral', 'loose leaf', 'audio'
                ];

                // Check if it's a book binding
                foreach ($bookBindings as $bookBinding) {
                    if (strpos($bindingType, $bookBinding) !== false) {
                        return true;
                    }
                }

                // These are definitely not books
                $nonBookBindings = [
                    'toy', 'game', 'accessory', 'electronics',
                    'video game', 'calendar', 'cards', 'dvd'
                ];

                foreach ($nonBookBindings as $nonBookBinding) {
                    if (strpos($bindingType, $nonBookBinding) !== false) {
                        return false;
                    }
                }
            }

            // Check ProductGroup classification
            $productGroup = $classifications->getProductGroup();
            if ($productGroup && $productGroup->getDisplayValue()) {
                $group = strtolower($productGroup->getDisplayValue());
                if ($group === 'book' || $group === 'ebook' || $group === 'audible') {
                    return true;
                }
                if ($group === 'toy' || $group === 'home' || $group === 'sports') {
                    return false;
                }
            }
        }

        // Check if it has page count (strong indicator of a book)
        $contentInfo = $itemInfo->getContentInfo();
        if ($contentInfo && $contentInfo->getPagesCount()) {
            $pageCount = $contentInfo->getPagesCount()->getDisplayValue();
            if ($pageCount && intval($pageCount) > 0) {
                return true;
            }
        }

        // Check for ISBN (definitive book indicator)
        if ($this->extractIsbn($itemInfo)) {
            return true;
        }

        // If we can't determine, default to false to be safe
        return false;
    }

    /**
     * Validate if a string is a valid ISBN-10 or ISBN-13
     */
    private function isValidIsbn(string $value): bool
    {
        // Remove any hyphens or spaces
        $cleanValue = str_replace(['-', ' '], '', $value);

        // First check: ASINs typically start with B followed by alphanumeric
        if (preg_match('/^B[0-9A-Z]{9}$/i', $cleanValue)) {
            return false; // This is an ASIN, not an ISBN
        }

        // Second check: If it contains ANY letters (except X at position 10), it's not an ISBN
        // This includes Google Books IDs and other non-ISBN identifiers
        if (preg_match('/[A-Z]/i', substr($cleanValue, 0, 9))) {
            return false; // ISBNs don't have letters in the first 9 positions
        }
        if (strlen($cleanValue) > 10 && preg_match('/[A-Z]/i', substr($cleanValue, 10))) {
            return false; // ISBN-13 has no letters at all
        }

        // Check if it's a valid ISBN-10
        if (strlen($cleanValue) === 10) {
            // ISBN-10: 9 digits + optional X at the end
            // Must start with a digit (not B or other letter)
            return preg_match('/^[0-9]{9}[0-9X]$/i', $cleanValue);
        }

        // Check if it's a valid ISBN-13
        if (strlen($cleanValue) === 13) {
            // ISBN-13 contains only digits and typically starts with 978 or 979
            return preg_match('/^[0-9]{13}$/', $cleanValue);
        }

        return false;
    }

}
