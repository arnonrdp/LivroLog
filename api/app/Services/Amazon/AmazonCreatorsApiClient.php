<?php

namespace App\Services\Amazon;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AmazonCreatorsApiClient
{
    private const RATE_LIMIT_DELAY = 1; // 1 second between requests

    private const API_HOST = 'https://creatorsapi.amazon';

    private AmazonOAuthTokenManager $tokenManager;

    private array $regionConfig;

    public function __construct(AmazonOAuthTokenManager $tokenManager)
    {
        $this->tokenManager = $tokenManager;
        $this->regionConfig = $this->loadRegionConfig();
    }

    private function loadRegionConfig(): array
    {
        $amazonConfig = config('services.amazon.regions', []);
        $defaultTag = config('services.amazon.associate_tag', 'livrolog01-20');

        return [
            'BR' => [
                'marketplace' => $amazonConfig['BR']['marketplace'] ?? 'www.amazon.com.br',
                'partner_tag' => $amazonConfig['BR']['tag'] ?? $defaultTag,
            ],
            'US' => [
                'marketplace' => $amazonConfig['US']['marketplace'] ?? 'www.amazon.com',
                'partner_tag' => $amazonConfig['US']['tag'] ?? $defaultTag,
            ],
            'UK' => [
                'marketplace' => $amazonConfig['UK']['marketplace'] ?? 'www.amazon.co.uk',
                'partner_tag' => $amazonConfig['UK']['tag'] ?? $defaultTag,
            ],
            'CA' => [
                'marketplace' => $amazonConfig['CA']['marketplace'] ?? 'www.amazon.ca',
                'partner_tag' => $amazonConfig['CA']['tag'] ?? $defaultTag,
            ],
            'DE' => [
                'marketplace' => $amazonConfig['DE']['marketplace'] ?? 'www.amazon.de',
                'partner_tag' => $amazonConfig['DE']['tag'] ?? $defaultTag,
            ],
        ];
    }

    /**
     * Search for items using the Creators API
     *
     * @param  string  $keywords  Search keywords
     * @param  string  $region  Region code (BR, US, UK, CA, DE)
     * @param  array  $options  Additional options
     * @return array API response data
     *
     * @throws \Exception On API errors
     */
    public function searchItems(string $keywords, string $region = 'BR', array $options = []): array
    {
        $this->respectRateLimit();

        $regionSettings = $this->regionConfig[$region] ?? $this->regionConfig['BR'];
        $marketplace = $regionSettings['marketplace'];

        // Creators API endpoint
        $apiUrl = self::API_HOST.'/catalog/v1/searchItems';

        $payload = [
            'keywords' => $keywords,
            'searchIndex' => 'Books',
            'partnerTag' => $regionSettings['partner_tag'],
            'resources' => $this->getSearchResources(),
            'itemCount' => $options['itemCount'] ?? 10,
        ];

        if (isset($options['itemPage'])) {
            $payload['itemPage'] = $options['itemPage'];
        }

        return $this->makeRequest($apiUrl, $payload, $marketplace);
    }

    /**
     * Get items by ASINs
     *
     * @param  array  $asins  Array of ASINs (max 10)
     * @param  string  $region  Region code
     * @return array API response data
     *
     * @throws \Exception On API errors
     */
    public function getItems(array $asins, string $region = 'BR'): array
    {
        $this->respectRateLimit();

        // API allows max 10 ASINs per request
        $asins = array_slice($asins, 0, 10);

        $regionSettings = $this->regionConfig[$region] ?? $this->regionConfig['BR'];
        $marketplace = $regionSettings['marketplace'];

        $apiUrl = self::API_HOST.'/catalog/v1/getItems';

        $payload = [
            'itemIds' => $asins,
            'partnerTag' => $regionSettings['partner_tag'],
            'resources' => $this->getItemResources(),
        ];

        return $this->makeRequest($apiUrl, $payload, $marketplace);
    }

    /**
     * Get variations for an ASIN
     *
     * @param  string  $asin  Parent ASIN
     * @param  string  $region  Region code
     * @return array API response data
     *
     * @throws \Exception On API errors
     */
    public function getVariations(string $asin, string $region = 'BR'): array
    {
        $this->respectRateLimit();

        $regionSettings = $this->regionConfig[$region] ?? $this->regionConfig['BR'];
        $marketplace = $regionSettings['marketplace'];

        $apiUrl = self::API_HOST.'/catalog/v1/getVariations';

        $payload = [
            'asin' => $asin,
            'partnerTag' => $regionSettings['partner_tag'],
            'resources' => $this->getVariationResources(),
        ];

        return $this->makeRequest($apiUrl, $payload, $marketplace);
    }

    /**
     * Make an authenticated API request
     */
    private function makeRequest(string $url, array $payload, string $marketplace, int $retryCount = 0): array
    {
        $accessToken = $this->tokenManager->getAccessToken();
        $version = config('services.amazon.creators_api.api_version', '2.1');

        Log::info('Amazon Creators API Request', [
            'url' => $url,
            'marketplace' => $marketplace,
            'payload_keys' => array_keys($payload),
        ]);

        $response = Http::withHeaders([
            'User-Agent' => 'creatorsapi-php-sdk/1.0.0',
            'x-marketplace' => $marketplace,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Authorization' => "Bearer {$accessToken}, Version {$version}",
        ])
            ->timeout(30)
            ->post($url, $payload);

        // Handle 401 - token might be expired
        if ($response->status() === 401 && $retryCount < 1) {
            Log::warning('Amazon Creators API: 401 received, refreshing token');
            $this->tokenManager->invalidateToken();

            return $this->makeRequest($url, $payload, $marketplace, $retryCount + 1);
        }

        // Handle rate limiting
        if ($response->status() === 429) {
            Log::warning('Amazon Creators API: Rate limited');
            throw new \Exception('Amazon API rate limited - try again later');
        }

        if (! $response->successful()) {
            $error = $response->json('message') ?? $response->json('Errors.0.Message') ?? $response->body();
            Log::error('Amazon Creators API Error', [
                'status' => $response->status(),
                'error' => $error,
                'url' => $url,
            ]);
            throw new \Exception('Amazon API error: '.$error);
        }

        $data = $response->json();

        // Normalize camelCase keys to PascalCase (matching PA-API 5.0 format)
        $data = $this->normalizeResponseKeys($data);

        // Check for API-level errors
        if (isset($data['Errors']) && ! empty($data['Errors'])) {
            $errorMessage = $data['Errors'][0]['Message'] ?? 'Unknown API error';
            Log::error('Amazon Creators API returned errors', [
                'errors' => $data['Errors'],
            ]);
            throw new \Exception('Amazon API error: '.$errorMessage);
        }

        return $data;
    }

    /**
     * Get resources for SearchItems request
     * Creators API uses lowercase camelCase for resource names
     */
    private function getSearchResources(): array
    {
        return [
            'itemInfo.title',
            'itemInfo.features',
            'itemInfo.byLineInfo',
            'itemInfo.contentInfo',
            'itemInfo.contentRating',
            'itemInfo.classifications',
            'itemInfo.productInfo',
            'itemInfo.technicalInfo',
            'itemInfo.externalIds',
            'browseNodeInfo.browseNodes',
            'browseNodeInfo.browseNodes.ancestor',
            'images.primary.large',
            'images.primary.medium',
            'images.primary.small',
            'customerReviews.count',
            'customerReviews.starRating',
        ];
    }

    /**
     * Get resources for GetItems request
     */
    private function getItemResources(): array
    {
        return $this->getSearchResources();
    }

    /**
     * Get resources for GetVariations request
     */
    private function getVariationResources(): array
    {
        return $this->getSearchResources();
    }

    /**
     * Normalize Creators API response keys from camelCase to PascalCase
     * to maintain compatibility with PA-API 5.0 format expected by downstream code.
     */
    private function normalizeResponseKeys(array $data): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            if (is_int($key)) {
                $result[$key] = is_array($value) ? $this->normalizeResponseKeys($value) : $value;

                continue;
            }

            if (! is_array($value)) {
                $result[$this->normalizeKey($key)] = $value;

                continue;
            }

            // Split 'isbns' into 'ISBN13s' and 'ISBN10s' based on value length
            if ($key === 'isbns') {
                $normalizedValue = $this->normalizeResponseKeys($value);
                $displayValues = $normalizedValue['DisplayValues'] ?? [];
                $isbn13s = [];
                $isbn10s = [];

                foreach ($displayValues as $isbn) {
                    $clean = str_replace(['-', ' '], '', $isbn);
                    if (strlen($clean) === 13) {
                        $isbn13s[] = $isbn;
                    } elseif (strlen($clean) === 10) {
                        $isbn10s[] = $isbn;
                    }
                }

                $base = $normalizedValue;
                unset($base['DisplayValues']);

                if (! empty($isbn13s)) {
                    $result['ISBN13s'] = array_merge($base, ['DisplayValues' => $isbn13s]);
                }
                if (! empty($isbn10s)) {
                    $result['ISBN10s'] = array_merge($base, ['DisplayValues' => $isbn10s]);
                }

                continue;
            }

            $result[$this->normalizeKey($key)] = $this->normalizeResponseKeys($value);
        }

        return $result;
    }

    private function normalizeKey(string $key): string
    {
        static $exactMap = [
            'asin' => 'ASIN',
            'url' => 'URL',
            'ean' => 'EAN',
            'eans' => 'EANs',
            'isbn' => 'ISBN',
            'isbns' => 'ISBNs',
        ];

        if (isset($exactMap[$key])) {
            return $exactMap[$key];
        }

        return ucfirst($key);
    }

    /**
     * Respect rate limits to avoid 429 errors
     */
    private function respectRateLimit(): void
    {
        $cacheKey = 'amazon_creators_last_request';
        $lastRequestTime = Cache::get($cacheKey, 0);
        $currentTime = time();

        $timeSinceLastRequest = $currentTime - $lastRequestTime;

        if ($timeSinceLastRequest < self::RATE_LIMIT_DELAY) {
            $sleepTime = self::RATE_LIMIT_DELAY - $timeSinceLastRequest;
            usleep($sleepTime * 1000000);
        }

        Cache::put($cacheKey, time(), 60);
    }

    /**
     * Check if the client is properly configured
     */
    public function isConfigured(): bool
    {
        return $this->tokenManager->hasCredentials();
    }
}
