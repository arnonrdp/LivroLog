<?php

namespace App\Services\Providers;

use Amazon\ProductAdvertisingAPI\v1\ApiException;
use Amazon\ProductAdvertisingAPI\v1\com\amazon\paapi5\v1\api\DefaultApi;
use Amazon\ProductAdvertisingAPI\v1\com\amazon\paapi5\v1\GetItemsRequest;
use Amazon\ProductAdvertisingAPI\v1\com\amazon\paapi5\v1\GetItemsResource;
use Amazon\ProductAdvertisingAPI\v1\com\amazon\paapi5\v1\GetVariationsRequest;
use Amazon\ProductAdvertisingAPI\v1\com\amazon\paapi5\v1\GetVariationsResource;
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

    private function getRegionConfig(): array
    {
        $amazonConfig = config('services.amazon.regions', []);
        $defaultTag = config('services.amazon.associate_tag', 'livrolog01-20');

        return [
            'US' => [
                'host' => 'webservices.amazon.com',
                'region' => 'us-east-1',
                'marketplace' => $amazonConfig['US']['marketplace'] ?? 'www.amazon.com',
                'associate_tag' => $amazonConfig['US']['tag'] ?? $defaultTag,
            ],
            'BR' => [
                'host' => 'webservices.amazon.com.br',
                'region' => 'us-east-1',
                'marketplace' => $amazonConfig['BR']['marketplace'] ?? 'www.amazon.com.br',
                'associate_tag' => $amazonConfig['BR']['tag'] ?? $defaultTag,
            ],
            'UK' => [
                'host' => 'webservices.amazon.co.uk',
                'region' => 'eu-west-1',
                'marketplace' => $amazonConfig['UK']['marketplace'] ?? 'www.amazon.co.uk',
                'associate_tag' => $amazonConfig['UK']['tag'] ?? $defaultTag,
            ],
            'CA' => [
                'host' => 'webservices.amazon.ca',
                'region' => 'us-east-1',
                'marketplace' => $amazonConfig['CA']['marketplace'] ?? 'www.amazon.ca',
                'associate_tag' => $amazonConfig['CA']['tag'] ?? $defaultTag,
            ],
        ];
    }

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

    /**
     * Get items by ASINs - useful for batch updates and price refreshes
     * GetItems is more efficient than SearchItems for updating existing books
     *
     * @param  array  $asins  Array of ASINs (max 10 per request)
     * @param  array  $options  Optional parameters (region, etc.)
     * @return array Response with success status and book data
     */
    public function getItems(array $asins, array $options = []): array
    {
        if (! $this->isEnabled()) {
            return $this->buildErrorResponse('Amazon Books provider is disabled');
        }

        if (empty($asins)) {
            return $this->buildErrorResponse('No ASINs provided');
        }

        // PA-API GetItems allows max 10 ASINs per request
        if (count($asins) > 10) {
            $asins = array_slice($asins, 0, 10);
            Log::warning('GetItems limited to first 10 ASINs', ['provided' => count($asins)]);
        }

        try {
            $this->respectRateLimit();

            $region = $this->detectOptimalRegion($options);
            $api = $this->createApiClient($region);

            $getItemsRequest = $this->createGetItemsRequest($asins, $region);

            Log::info('Amazon GetItems API Request', [
                'asins' => $asins,
                'region' => $region,
                'count' => count($asins),
            ]);

            $response = $api->getItems($getItemsRequest);

            if ($response->getErrors()) {
                $errors = $response->getErrors();
                $errorMessages = array_map(fn ($e) => $e->getMessage(), $errors);
                Log::error('Amazon GetItems errors', ['errors' => $errorMessages]);

                return $this->buildErrorResponse('GetItems failed: '.implode(', ', $errorMessages));
            }

            $items = [];
            if ($response->getItemsResult() && $response->getItemsResult()->getItems()) {
                $items = $response->getItemsResult()->getItems();
            }

            if (empty($items)) {
                return $this->buildErrorResponse('No items found for provided ASINs');
            }

            $books = $this->transformSearchResults($items);

            return $this->buildSuccessResponse($books, count($books));

        } catch (ApiException $e) {
            Log::error('Amazon GetItems PA-API error', [
                'asins' => $asins,
                'error' => $e->getMessage(),
                'response_body' => $e->getResponseBody(),
            ]);

            return $this->buildErrorResponse('Amazon API error: '.$e->getMessage());

        } catch (\Exception $e) {
            Log::error('Amazon GetItems error', [
                'asins' => $asins,
                'error' => $e->getMessage(),
            ]);

            return $this->buildErrorResponse('GetItems failed: '.$e->getMessage());
        }
    }

    /**
     * Get variations (different editions) of a book by its ASIN
     * Returns different formats/editions of the same book (Kindle, Hardcover, Paperback, etc.)
     *
     * @param  string  $asin  The parent ASIN to get variations for
     * @param  array  $options  Optional parameters (region, etc.)
     * @return array Response with success status and variations data
     */
    public function getVariations(string $asin, array $options = []): array
    {
        if (! $this->isEnabled()) {
            return $this->buildErrorResponse('Amazon Books provider is disabled');
        }

        if (empty($asin)) {
            return $this->buildErrorResponse('No ASIN provided');
        }

        try {
            $this->respectRateLimit();

            $region = $this->detectOptimalRegion($options);
            $api = $this->createApiClient($region);

            $getVariationsRequest = $this->createGetVariationsRequest($asin, $region);

            Log::info('Amazon GetVariations API Request', [
                'asin' => $asin,
                'region' => $region,
            ]);

            $response = $api->getVariations($getVariationsRequest);

            if ($response->getErrors()) {
                $errors = $response->getErrors();
                $errorMessages = array_map(fn ($e) => $e->getMessage(), $errors);
                Log::error('Amazon GetVariations errors', ['errors' => $errorMessages]);

                return $this->buildErrorResponse('GetVariations failed: '.implode(', ', $errorMessages));
            }

            $variations = [];
            if ($response->getVariationsResult() && $response->getVariationsResult()->getItems()) {
                $variations = $response->getVariationsResult()->getItems();
            }

            if (empty($variations)) {
                return $this->buildErrorResponse('No variations found for this ASIN');
            }

            $books = $this->transformSearchResults($variations);

            return $this->buildSuccessResponse($books, count($books));

        } catch (ApiException $e) {
            Log::error('Amazon GetVariations PA-API error', [
                'asin' => $asin,
                'error' => $e->getMessage(),
                'response_body' => $e->getResponseBody(),
            ]);

            return $this->buildErrorResponse('Amazon API error: '.$e->getMessage());

        } catch (\Exception $e) {
            Log::error('Amazon GetVariations error', [
                'asin' => $asin,
                'error' => $e->getMessage(),
            ]);

            return $this->buildErrorResponse('GetVariations failed: '.$e->getMessage());
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
        $regionConfig = $this->getRegionConfig();
        if (isset($options['region']) && isset($regionConfig[$options['region']])) {
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
                        'error' => $e->getMessage(),
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
        $regionConfig = $this->getRegionConfig();
        $config->setHost($regionConfig[$region]['host']);
        $config->setRegion($regionConfig[$region]['region']);

        return new DefaultApi(new Client, $config);
    }

    private function createSearchRequest(string $searchQuery, string $region, array $options): SearchItemsRequest
    {
        $searchItemsRequest = new SearchItemsRequest;
        $searchItemsRequest->setSearchIndex('Books');
        $searchItemsRequest->setKeywords($searchQuery);
        $searchItemsRequest->setItemCount($options['maxResults'] ?? self::MAX_RESULTS);
        $regionConfig = $this->getRegionConfig();
        $searchItemsRequest->setPartnerTag($regionConfig[$region]['associate_tag']);
        $searchItemsRequest->setPartnerType(PartnerType::ASSOCIATES);
        $searchItemsRequest->setMarketplace($regionConfig[$region]['marketplace']);

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
            SearchItemsResource::BROWSE_NODE_INFOBROWSE_NODES,  // For category hierarchy
            SearchItemsResource::IMAGESPRIMARYLARGE,
            SearchItemsResource::IMAGESPRIMARYMEDIUM,
            SearchItemsResource::IMAGESPRIMARYSMALL,
        ]);

        return $searchItemsRequest;
    }

    /**
     * Create GetItems request with same resources as SearchItems
     */
    private function createGetItemsRequest(array $asins, string $region): GetItemsRequest
    {
        $getItemsRequest = new GetItemsRequest;
        $getItemsRequest->setItemIds($asins);
        $regionConfig = $this->getRegionConfig();
        $getItemsRequest->setPartnerTag($regionConfig[$region]['associate_tag']);
        $getItemsRequest->setPartnerType(PartnerType::ASSOCIATES);
        $getItemsRequest->setMarketplace($regionConfig[$region]['marketplace']);

        // Request same comprehensive book information as SearchItems
        $getItemsRequest->setResources([
            GetItemsResource::ITEM_INFOTITLE,
            GetItemsResource::ITEM_INFOFEATURES,
            GetItemsResource::ITEM_INFOBY_LINE_INFO,
            GetItemsResource::ITEM_INFOCONTENT_INFO,
            GetItemsResource::ITEM_INFOCONTENT_RATING,
            GetItemsResource::ITEM_INFOCLASSIFICATIONS,
            GetItemsResource::ITEM_INFOPRODUCT_INFO,
            GetItemsResource::ITEM_INFOTECHNICAL_INFO,
            GetItemsResource::ITEM_INFOEXTERNAL_IDS,
            GetItemsResource::BROWSE_NODE_INFOBROWSE_NODES,  // For category hierarchy
            GetItemsResource::IMAGESPRIMARYLARGE,
            GetItemsResource::IMAGESPRIMARYMEDIUM,
            GetItemsResource::IMAGESPRIMARYSMALL,
        ]);

        return $getItemsRequest;
    }

    /**
     * Create GetVariations request with same resources as GetItems
     */
    private function createGetVariationsRequest(string $asin, string $region): GetVariationsRequest
    {
        $getVariationsRequest = new GetVariationsRequest;
        $getVariationsRequest->setASIN($asin);
        $regionConfig = $this->getRegionConfig();
        $getVariationsRequest->setPartnerTag($regionConfig[$region]['associate_tag']);
        $getVariationsRequest->setPartnerType(PartnerType::ASSOCIATES);
        $getVariationsRequest->setMarketplace($regionConfig[$region]['marketplace']);

        // Request same comprehensive book information as GetItems
        $getVariationsRequest->setResources([
            GetVariationsResource::ITEM_INFOTITLE,
            GetVariationsResource::ITEM_INFOFEATURES,
            GetVariationsResource::ITEM_INFOBY_LINE_INFO,
            GetVariationsResource::ITEM_INFOCONTENT_INFO,
            GetVariationsResource::ITEM_INFOCONTENT_RATING,
            GetVariationsResource::ITEM_INFOCLASSIFICATIONS,
            GetVariationsResource::ITEM_INFOPRODUCT_INFO,
            GetVariationsResource::ITEM_INFOTECHNICAL_INFO,
            GetVariationsResource::ITEM_INFOEXTERNAL_IDS,
            GetVariationsResource::BROWSE_NODE_INFOBROWSE_NODES,  // For category hierarchy
            GetVariationsResource::IMAGESPRIMARYLARGE,
            GetVariationsResource::IMAGESPRIMARYMEDIUM,
            GetVariationsResource::IMAGESPRIMARYSMALL,
        ]);

        return $getVariationsRequest;
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
        if (! $this->isActualBook($item, $itemInfo)) {
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

        // Extract categories from both Classifications and Browse Nodes
        $classificationCategories = $this->extractCategories($itemInfo);
        $browseNodeCategories = $this->extractBrowseNodes($item);

        // Merge categories: Browse Nodes (more detailed) + Classifications (binding/format)
        $allCategories = [];
        if ($browseNodeCategories) {
            $allCategories = array_merge($allCategories, $browseNodeCategories);
        }
        if ($classificationCategories) {
            $allCategories = array_merge($allCategories, $classificationCategories);
        }

        // Remove duplicates and filter for relevant categories
        $allCategories = ! empty($allCategories) ? array_values(array_unique($allCategories)) : null;
        $allCategories = $this->filterRelevantCategories($allCategories);

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
            'categories' => $allCategories,
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
                    if (! empty($values) && $this->isValidIsbn($values[0])) {
                        return $values[0];
                    }
                }
            }

            // Try ISBN-10 from ExternalIds
            if (method_exists($externalIds, 'getISBN10s')) {
                $isbn10s = $externalIds->getISBN10s();
                if ($isbn10s && $isbn10s->getDisplayValues()) {
                    $values = $isbn10s->getDisplayValues();
                    if (! empty($values) && $this->isValidIsbn($values[0])) {
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
                $lang = $languages->getDisplayValues()[0];

                // Handle case where language is an object with DisplayValue property
                if (is_object($lang) && isset($lang->DisplayValue)) {
                    return $lang->DisplayValue;
                }

                // Handle case where language is an array
                if (is_array($lang) && isset($lang['DisplayValue'])) {
                    return $lang['DisplayValue'];
                }

                // Return as string if it's already a simple value
                return is_string($lang) ? $lang : null;
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

    /**
     * Extract Browse Nodes - Amazon's category hierarchy
     * Returns array of category names from most general to most specific
     * Example: ['Books', 'Science Fiction & Fantasy', 'Science Fiction', 'Space Opera']
     */
    private function extractBrowseNodes($item): ?array
    {
        $browseNodeInfo = $item->getBrowseNodeInfo();
        if (! $browseNodeInfo) {
            return null;
        }

        $browseNodes = $browseNodeInfo->getBrowseNodes();
        if (! $browseNodes || empty($browseNodes)) {
            return null;
        }

        $categories = [];

        // Process each browse node (usually books have just one primary node)
        foreach ($browseNodes as $node) {
            // Extract this node's name
            if ($node->getDisplayName()) {
                $nodeName = $node->getDisplayName();

                // Build hierarchy by following ancestors
                $hierarchy = [$nodeName];

                // Walk up the ancestor chain
                $ancestor = $node->getAncestor();
                while ($ancestor) {
                    if ($ancestor->getDisplayName()) {
                        // Prepend ancestor (so we go from general to specific)
                        array_unshift($hierarchy, $ancestor->getDisplayName());
                    }

                    // Move to next ancestor
                    $ancestor = $ancestor->getAncestor();
                }

                // Add this complete hierarchy
                $categories = array_merge($categories, $hierarchy);
            }
        }

        // Remove duplicates while preserving order
        $categories = array_values(array_unique($categories));

        return ! empty($categories) ? $categories : null;
    }

    /**
     * Filter out marketing/navigation categories and keep only relevant literary categories
     */
    private function filterRelevantCategories(?array $categories): ?array
    {
        if (! $categories || empty($categories)) {
            return null;
        }

        // Patterns to exclude (marketing, navigation, price-based, promotions)
        $excludePatterns = [
            '/livros? até r\$/i',
            '/ebooks? até r\$/i',
            '/top asins?/i',
            '/guia de compras/i',
            '/promo(ção|cao)?/i',
            '/cupom/i',
            '/off (no|em)/i',
            '/termos e condi(ções|coes)/i',
            '/n(ão|ao) aplicad/i',
            '/lan(ç|c)amentos/i',
            '/ofertas?/i',
            '/mais amados/i',
            '/mais lidos/i',
            '/preferidos/i',
            '/p(á|a)gina do autor/i',
            '/comece a sua leitura/i',
            '/em oferta/i',
            '/kindle unlimited/i',
            '/catalogo kindle/i',
            '/cashback/i',
            '/pr(é|e)-venda/i',
            '/aniversa(á|a)rio/i',
            '/editora /i',
            '/obrigado por/i',
            '/sele(ç|c)(ã|a)o participante/i',
            '/categorias$/i',
            '/compra de ebook/i',
            '/^[a-f0-9]{8}-[a-f0-9]{4}/i', // UUIDs
            '/^trade$/i',
            '/^book$/i',
            '/^capa (comum|dura)$/i',
            '/ebook kindle$/i',
            '/kindle unlimited$/i',
        ];

        // Also exclude publisher names (they're not categories)
        $publisherNames = [
            'ciranda cultural',
            'alta books',
            'companhia das letras',
            'rocco',
            'darkside',
            'intrínseca',
            'nova fronteira',
            'globo livros',
        ];

        $filtered = [];
        foreach ($categories as $category) {
            $shouldExclude = false;

            // Check against exclude patterns
            foreach ($excludePatterns as $pattern) {
                if (preg_match($pattern, $category)) {
                    $shouldExclude = true;
                    break;
                }
            }

            // Check against publisher names
            $lowerCategory = mb_strtolower($category, 'UTF-8');
            if (in_array($lowerCategory, $publisherNames)) {
                $shouldExclude = true;
            }

            // Keep it if not excluded
            if (! $shouldExclude) {
                $filtered[] = $category;
            }
        }

        return ! empty($filtered) ? array_values($filtered) : null;
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
        // Amazon provider is always enabled if credentials are present
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
        $amazonCacheKey = 'amazon_rate_limited_'.md5($query);

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
                    'spiral', 'loose leaf', 'audio',
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
                    'video game', 'calendar', 'cards', 'dvd',
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
