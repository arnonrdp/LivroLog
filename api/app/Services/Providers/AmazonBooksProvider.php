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
    private const PRIORITY = 2;

    private const MAX_RESULTS = 10; // Amazon PA-API allows max 10 per request

    private array $regionConfig = [
        'US' => [
            'host' => 'webservices.amazon.com',
            'region' => 'us-east-1',
            'marketplace' => 'www.amazon.com',
        ],
        'BR' => [
            'host' => 'webservices.amazon.com.br',
            'region' => 'us-east-1',
            'marketplace' => 'www.amazon.com.br',
        ],
        'UK' => [
            'host' => 'webservices.amazon.co.uk',
            'region' => 'eu-west-1',
            'marketplace' => 'www.amazon.co.uk',
        ],
        'CA' => [
            'host' => 'webservices.amazon.ca',
            'region' => 'us-east-1',
            'marketplace' => 'www.amazon.ca',
        ],
    ];

    public function search(string $query, array $options = []): array
    {
        if (! $this->isEnabled()) {
            return $this->buildErrorResponse('Amazon Books provider is disabled');
        }

        try {
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
        // Priority order: user preference > browser locale > default
        if (isset($options['region']) && isset($this->regionConfig[$options['region']])) {
            return $options['region'];
        }

        if (isset($options['locale'])) {
            $locale = strtolower($options['locale']);
            if (str_starts_with($locale, 'pt-br') || str_starts_with($locale, 'pt_br') || $locale === 'pt') {
                return 'BR';
            } elseif (str_starts_with($locale, 'en-gb') || str_starts_with($locale, 'en_gb')) {
                return 'UK';
            } elseif (str_starts_with($locale, 'en-ca') || str_starts_with($locale, 'en_ca')) {
                return 'CA';
            }
        }

        return 'US'; // Default fallback
    }

    private function performSearch(string $searchQuery, string $region, array $options): array
    {
        $api = $this->createApiClient($region);
        $searchItemsRequest = $this->createSearchRequest($searchQuery, $region, $options);

        $response = $api->searchItems($searchItemsRequest);

        if ($response->getSearchResult() && $response->getSearchResult()->getItems()) {
            return $response->getSearchResult()->getItems();
        }

        return [];
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
        $searchItemsRequest->setPartnerTag(config('services.amazon.associate_tag'));
        $searchItemsRequest->setPartnerType(PartnerType::ASSOCIATES);
        $searchItemsRequest->setMarketplace($this->regionConfig[$region]['marketplace']);

        // Request comprehensive book information (constant names per thewirecutter/paapi5-php-sdk)
        $searchItemsRequest->setResources([
            SearchItemsResource::ITEM_INFOTITLE,
            SearchItemsResource::ITEM_INFOFEATURES,
            SearchItemsResource::ITEM_INFOBY_LINE_INFO,
            SearchItemsResource::ITEM_INFOCONTENT_INFO,
            SearchItemsResource::ITEM_INFOCONTENT_RATING,
            SearchItemsResource::ITEM_INFOPRODUCT_INFO,
            SearchItemsResource::ITEM_INFOTECHNICAL_INFO,
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
            'isbn' => $isbn ?: $asin,
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
            if ($contributor->getName() && $contributor->getName()->getDisplayValue()) {
                $authors[] = $contributor->getName()->getDisplayValue();
            }
        }

        return ! empty($authors) ? implode(', ', $authors) : null;
    }

    private function extractIsbn($itemInfo): ?string
    {
        // Try ISBN-13 first, then ISBN-10
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
                    return $isbn->getDisplayValue();
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
        return config('services.amazon.enabled', false)
            && ! empty(config('services.amazon.pa_api_key'))
            && ! empty(config('services.amazon.pa_secret_key'))
            && ! empty(config('services.amazon.associate_tag'));
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
}
