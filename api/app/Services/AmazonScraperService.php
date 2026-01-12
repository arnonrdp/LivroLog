<?php

namespace App\Services;

use App\Models\Book;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AmazonScraperService
{
    private const USER_AGENT = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36';

    private const TIMEOUT = 15;

    private const MIN_TITLE_SIMILARITY = 0.60;

    private const MIN_AUTHOR_SIMILARITY = 0.70;

    /**
     * Extract book data from a specific Amazon product URL
     * Used when admin provides direct Amazon link
     * Supports short URLs (a.co, amzn.to) which redirect to product pages
     *
     * @return array|null Book data array or null if extraction failed
     */
    public function extractFromUrl(string $amazonUrl): ?array
    {
        try {
            // Try to extract ASIN from URL first (works for regular product URLs)
            $asin = $this->extractAsinFromUrl($amazonUrl);

            // For short URLs, we need to determine region from input URL first
            $regionConfig = $this->getRegionConfigFromUrl($amazonUrl);

            Log::info('Amazon scraper: Extracting data from URL', [
                'url' => $amazonUrl,
                'asin_from_url' => $asin,
            ]);

            // Fetch the product page (HTTP client follows redirects automatically)
            $response = Http::timeout(self::TIMEOUT)
                ->withHeaders($this->getRequestHeaders($regionConfig))
                ->get($amazonUrl);

            if (! $response->successful()) {
                Log::warning('Amazon scraper: Failed to fetch URL', [
                    'url' => $amazonUrl,
                    'status' => $response->status(),
                ]);

                return null;
            }

            // If we didn't extract ASIN from original URL (short URL case),
            // try to get it from the effective URL after redirects
            if (! $asin) {
                $effectiveUrl = $response->effectiveUri()?->__toString();
                if ($effectiveUrl) {
                    $asin = $this->extractAsinFromUrl($effectiveUrl);
                    // Also update region config from effective URL
                    $regionConfig = $this->getRegionConfigFromUrl($effectiveUrl);

                    Log::info('Amazon scraper: Extracted ASIN from redirected URL', [
                        'original_url' => $amazonUrl,
                        'effective_url' => $effectiveUrl,
                        'asin' => $asin,
                    ]);
                }
            }

            if (! $asin) {
                Log::warning('Amazon scraper: Could not extract ASIN from URL or redirected URL', [
                    'url' => $amazonUrl,
                ]);

                return null;
            }

            $html = $response->body();

            // Extract product data
            $productData = $this->extractProductData($html);
            $productData['amazon_asin'] = $asin;

            // Extract title for logging
            $title = $this->extractProductTitle($html);
            if ($title) {
                $productData['extracted_title'] = $title;
            }

            Log::info('Amazon scraper: Successfully extracted data from URL', [
                'asin' => $asin,
                'title' => $title,
                'has_thumbnail' => ! empty($productData['thumbnail']),
            ]);

            return $productData;

        } catch (\Exception $e) {
            Log::error('Amazon scraper: Error extracting from URL', [
                'url' => $amazonUrl,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Extract ASIN from Amazon URL
     * Supports formats: /dp/ASIN, /gp/product/ASIN, /ASIN/, etc.
     */
    public function extractAsinFromUrl(string $url): ?string
    {
        // Pattern for /dp/ASIN or /gp/product/ASIN
        if (preg_match('/\/(?:dp|gp\/product)\/([A-Z0-9]{10})/i', $url, $matches)) {
            return strtoupper($matches[1]);
        }

        // Pattern for ASIN in query string (?asin=XXXX)
        if (preg_match('/[?&]asin=([A-Z0-9]{10})/i', $url, $matches)) {
            return strtoupper($matches[1]);
        }

        return null;
    }

    /**
     * Get region config from Amazon URL domain
     */
    private function getRegionConfigFromUrl(string $url): array
    {
        $defaultConfig = config('services.amazon.regions.BR');

        try {
            $host = parse_url($url, PHP_URL_HOST);
            if (! $host) {
                return $defaultConfig;
            }

            $host = strtolower($host);

            // Short URL domains default to US
            $shortUrlDomains = ['a.co', 'amzn.to', 'amzn.com'];
            foreach ($shortUrlDomains as $shortDomain) {
                if ($host === $shortDomain || str_ends_with($host, '.'.$shortDomain)) {
                    $regionConfig = config('services.amazon.regions.US');

                    return $regionConfig ?: $defaultConfig;
                }
            }

            // Map domains to regions
            $domainToRegion = [
                'amazon.com.br' => 'BR',
                'amazon.com' => 'US',
                'amazon.co.uk' => 'UK',
                'amazon.ca' => 'CA',
                'amazon.de' => 'DE',
                'amazon.fr' => 'FR',
                'amazon.es' => 'ES',
                'amazon.it' => 'IT',
                'amazon.co.jp' => 'JP',
            ];

            foreach ($domainToRegion as $domain => $region) {
                if (str_contains($host, $domain)) {
                    $regionConfig = config("services.amazon.regions.{$region}");
                    if ($regionConfig) {
                        return $regionConfig;
                    }
                }
            }
        } catch (\Exception $e) {
            Log::warning('Amazon scraper: Error parsing URL for region', ['error' => $e->getMessage()]);
        }

        return $defaultConfig;
    }

    /**
     * Search Amazon and extract book data via web scraping
     * This is a fallback when PA-API is unavailable
     *
     * @return array|null Book data array or null if not found
     */
    public function searchAndExtract(Book $book): ?array
    {
        try {
            $region = $this->getRegionForBook($book);
            $regionConfig = config('services.amazon.regions.'.$region);

            if (! $regionConfig) {
                Log::warning("Amazon scraper: No region config for {$region}, using US");
                $regionConfig = config('services.amazon.regions.US');
            }

            // Get multiple search URLs to try
            $searchUrls = $this->buildSearchUrls($book, $regionConfig);

            foreach ($searchUrls as $searchUrl) {
                Log::info("Amazon scraper: Searching for book {$book->id}", [
                    'url' => $searchUrl,
                    'title' => $book->title,
                    'authors' => $book->authors,
                ]);

                // Fetch search results
                $response = Http::timeout(self::TIMEOUT)
                    ->withHeaders($this->getRequestHeaders($regionConfig))
                    ->get($searchUrl);

                if (! $response->successful()) {
                    Log::warning("Amazon scraper: Search request failed with status {$response->status()}");

                    continue;
                }

                $html = $response->body();

                // Extract ALL ASINs from search results (not just the first one)
                $candidates = $this->extractCandidatesFromSearchResults($html);

                if (empty($candidates)) {
                    Log::info('Amazon scraper: No candidates found in search results');

                    continue;
                }

                Log::info('Amazon scraper: Found '.count($candidates)." candidates for book {$book->id}");

                // Validate each candidate until we find a match
                foreach ($candidates as $candidate) {
                    $asin = $candidate['asin'];
                    $searchTitle = $candidate['title'] ?? null;

                    // Quick pre-validation using search result title
                    if ($searchTitle && ! $this->quickTitleMatch($book->title, $searchTitle)) {
                        Log::debug("Amazon scraper: Skipping ASIN {$asin} - title mismatch in search results", [
                            'our_title' => $book->title,
                            'search_title' => $searchTitle,
                        ]);

                        continue;
                    }

                    // Fetch product page to get full details and validate
                    $productData = $this->fetchAndValidateProduct($asin, $book, $regionConfig);

                    if ($productData) {
                        Log::info("Amazon scraper: Validated ASIN {$asin} for book {$book->id}");
                        $productData['amazon_asin'] = $asin;

                        return $productData;
                    }
                }
            }

            Log::warning("Amazon scraper: No valid match found for book {$book->id}: {$book->title}");

            return null;

        } catch (\Exception $e) {
            Log::error("Amazon scraper error for book {$book->id}: {$e->getMessage()}");

            return null;
        }
    }

    /**
     * Extract candidate books from search results with their titles
     * Returns array of ['asin' => string, 'title' => string|null]
     */
    private function extractCandidatesFromSearchResults(string $html): array
    {
        $candidates = [];
        $seenAsins = [];

        // First, extract all search result blocks with their ASINs
        // Split HTML by search result containers to process each independently
        preg_match_all('/data-asin="([A-Z0-9]{10})"[^>]*>(.*?)(?=data-asin="|$)/s', $html, $blocks, PREG_SET_ORDER);

        foreach ($blocks as $block) {
            $asin = $block[1];
            $blockHtml = $block[2];

            if (empty($asin) || isset($seenAsins[$asin])) {
                continue;
            }

            $title = null;

            // Pattern 1: Book title with specific Amazon class combination (most reliable)
            // The class "a-size-medium a-spacing-none a-color-base a-text-normal" is used for product titles
            if (preg_match('/<span[^>]*class="[^"]*a-size-medium[^"]*a-color-base[^"]*a-text-normal[^"]*"[^>]*>([^<]+)</i', $blockHtml, $titleMatch)) {
                $title = html_entity_decode(trim($titleMatch[1]), ENT_QUOTES, 'UTF-8');
            }
            // Pattern 2: Alternative class order
            elseif (preg_match('/<span[^>]*class="[^"]*a-size-base-plus[^"]*a-color-base[^"]*a-text-normal[^"]*"[^>]*>([^<]+)</i', $blockHtml, $titleMatch)) {
                $title = html_entity_decode(trim($titleMatch[1]), ENT_QUOTES, 'UTF-8');
            }
            // Pattern 3: Title in anchor with product link
            elseif (preg_match('/<a[^>]*href="[^"]*\/dp\/'.preg_quote($asin, '/').'[^"]*"[^>]*>.*?<span[^>]*>([^<]{10,})<\/span>/is', $blockHtml, $titleMatch)) {
                $title = html_entity_decode(trim($titleMatch[1]), ENT_QUOTES, 'UTF-8');
            }
            // Pattern 4: h2 with a-size-mini class (search result title)
            elseif (preg_match('/<h2[^>]*class="[^"]*a-size-mini[^"]*"[^>]*>.*?<span[^>]*>([^<]+)<\/span>/is', $blockHtml, $titleMatch)) {
                $title = html_entity_decode(trim($titleMatch[1]), ENT_QUOTES, 'UTF-8');
            }

            // Validate extracted title is not a date or other non-title text
            if ($title && $this->isValidSearchResultTitle($title)) {
                $seenAsins[$asin] = true;
                $candidates[] = ['asin' => $asin, 'title' => $title];
            } elseif (! isset($seenAsins[$asin])) {
                // Add ASIN without title - will be validated on product page
                $seenAsins[$asin] = true;
                $candidates[] = ['asin' => $asin, 'title' => null];
            }
        }

        // Limit to first 5 candidates to avoid too many requests
        return array_slice($candidates, 0, 5);
    }

    /**
     * Check if extracted text is a valid book title (not a date, price, etc.)
     */
    private function isValidSearchResultTitle(string $text): bool
    {
        // Too short to be a valid title
        if (strlen($text) < 3) {
            return false;
        }

        // Check if it looks like a date (e.g., "Jun 23, 2020", "2023-01-15")
        if (preg_match('/^(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)\s+\d{1,2},?\s*\d{4}$/i', $text)) {
            return false;
        }
        if (preg_match('/^\d{1,4}[-\/]\d{1,2}[-\/]\d{1,4}$/', $text)) {
            return false;
        }

        // Check if it looks like a price (currency symbols followed by number)
        if (preg_match('/^[\$\€\£]\s*\d+[.,]?\d*$/u', $text)) {
            return false;
        }
        // Brazilian Real format
        if (preg_match('/^R\$\s*\d+[.,]?\d*$/', $text)) {
            return false;
        }

        // Check if it's just a short number (rating, page count, etc.)
        // Allow 4-digit numbers like "1984" as they could be book titles
        if (preg_match('/^\d{1,3}(\.\d+)?$/', trim($text))) {
            return false;
        }

        // Check if it's a common Amazon label
        $invalidLabels = ['Paperback', 'Hardcover', 'Kindle Edition', 'Audiobook', 'Audio CD', 'Spiral-bound', 'Board book'];
        if (in_array(trim($text), $invalidLabels, true)) {
            return false;
        }

        return true;
    }

    /**
     * Quick title match for pre-filtering (less strict than full validation)
     */
    private function quickTitleMatch(string $ourTitle, string $amazonTitle): bool
    {
        $normalized1 = $this->normalizeTitle($ourTitle);
        $normalized2 = $this->normalizeTitle($amazonTitle);

        // Check if one contains the other (for subtitles)
        if (str_contains($normalized2, $normalized1) || str_contains($normalized1, $normalized2)) {
            return true;
        }

        // Calculate similarity
        similar_text($normalized1, $normalized2, $percent);

        return $percent >= 50; // Lower threshold for pre-filtering
    }

    /**
     * Fetch product page and validate it matches our book
     */
    private function fetchAndValidateProduct(string $asin, Book $book, array $regionConfig): ?array
    {
        try {
            $domain = $regionConfig['domain'] ?? 'amazon.com';
            $url = "https://www.{$domain}/dp/{$asin}";

            $response = Http::timeout(self::TIMEOUT)
                ->withHeaders($this->getRequestHeaders($regionConfig))
                ->get($url);

            if (! $response->successful()) {
                Log::warning("Amazon scraper: Product page request failed for ASIN {$asin}");

                return null;
            }

            $html = $response->body();
            $productData = $this->extractProductData($html);

            // Extract title for validation
            $amazonTitle = $this->extractProductTitle($html);

            if (! $amazonTitle) {
                Log::warning("Amazon scraper: Could not extract title from product page for ASIN {$asin}");

                return null;
            }

            $productData['extracted_title'] = $amazonTitle;

            // Validate the product matches our book
            if (! $this->validateProductMatch($book, $amazonTitle, $productData)) {
                Log::warning("Amazon scraper: Product validation failed for ASIN {$asin}", [
                    'our_title' => $book->title,
                    'amazon_title' => $amazonTitle,
                ]);

                return null;
            }

            return $productData;

        } catch (\Exception $e) {
            Log::warning("Amazon scraper: Error fetching product page for ASIN {$asin}: {$e->getMessage()}");

            return null;
        }
    }

    /**
     * Extract product title from Amazon product page
     */
    private function extractProductTitle(string $html): ?string
    {
        // Pattern 1: productTitle span (most common)
        if (preg_match('/<span[^>]*id="productTitle"[^>]*>([^<]+)<\/span>/i', $html, $matches)) {
            return html_entity_decode(trim($matches[1]), ENT_QUOTES, 'UTF-8');
        }

        // Pattern 2: ebooksProductTitle
        if (preg_match('/<span[^>]*id="ebooksProductTitle"[^>]*>([^<]+)<\/span>/i', $html, $matches)) {
            return html_entity_decode(trim($matches[1]), ENT_QUOTES, 'UTF-8');
        }

        // Pattern 3: Title in h1 tag
        if (preg_match('/<h1[^>]*class="[^"]*a-size-large[^"]*"[^>]*>([^<]+)<\/h1>/i', $html, $matches)) {
            return html_entity_decode(trim($matches[1]), ENT_QUOTES, 'UTF-8');
        }

        // Pattern 4: From page title (last resort)
        if (preg_match('/<title>([^<]+)<\/title>/i', $html, $matches)) {
            $title = html_entity_decode(trim($matches[1]), ENT_QUOTES, 'UTF-8');
            // Remove Amazon suffix
            $title = preg_replace('/\s*[:\-|]\s*Amazon\..*$/i', '', $title);

            return $title;
        }

        return null;
    }

    /**
     * Validate that the Amazon product matches our book
     */
    private function validateProductMatch(Book $book, string $amazonTitle, array $productData): bool
    {
        // If both have ISBN, they MUST match
        if (! empty($book->isbn) && ! empty($productData['isbn'])) {
            $normalizedBookIsbn = preg_replace('/[^0-9X]/i', '', $book->isbn);
            $normalizedAmazonIsbn = preg_replace('/[^0-9X]/i', '', $productData['isbn']);

            if ($normalizedBookIsbn === $normalizedAmazonIsbn) {
                Log::info('Amazon scraper: ISBN match confirmed');

                return true;
            }

            // If ISBNs exist but don't match, this is NOT the right book
            Log::warning('Amazon scraper: ISBN mismatch', [
                'our_isbn' => $normalizedBookIsbn,
                'amazon_isbn' => $normalizedAmazonIsbn,
            ]);

            return false;
        }

        // Title validation (required)
        $titleSimilarity = $this->calculateTitleSimilarity($book->title, $amazonTitle);

        // Check if our title is contained in Amazon's title (handles subtitles)
        $normalized1 = $this->normalizeTitle($book->title);
        $normalized2 = $this->normalizeTitle($amazonTitle);
        $isContained = (str_contains($normalized2, $normalized1) || str_contains($normalized1, $normalized2))
            && strlen($normalized1) >= 3;

        Log::info('Amazon scraper: Title similarity check', [
            'our_title' => $book->title,
            'amazon_title' => $amazonTitle,
            'similarity' => $titleSimilarity,
            'is_contained' => $isContained,
        ]);

        if (! $isContained && $titleSimilarity < self::MIN_TITLE_SIMILARITY) {
            Log::warning('Amazon scraper: Title similarity too low', [
                'required' => self::MIN_TITLE_SIMILARITY,
                'actual' => $titleSimilarity,
            ]);

            return false;
        }

        // Author validation (if available)
        if (! empty($book->authors) && ! empty($productData['authors'])) {
            $authorSimilarity = $this->calculateAuthorSimilarity($book->authors, $productData['authors']);
            $authorWordsMatch = $this->checkAuthorWordsMatch($book->authors, $productData['authors']);

            Log::info('Amazon scraper: Author similarity check', [
                'our_authors' => $book->authors,
                'amazon_authors' => $productData['authors'],
                'similarity' => $authorSimilarity,
                'words_match' => $authorWordsMatch,
            ]);

            if (! $authorWordsMatch && $authorSimilarity < self::MIN_AUTHOR_SIMILARITY) {
                Log::warning('Amazon scraper: Author similarity too low');

                return false;
            }
        }

        return true;
    }

    /**
     * Calculate similarity between two titles
     */
    private function calculateTitleSimilarity(string $title1, string $title2): float
    {
        $normalized1 = $this->normalizeTitle($title1);
        $normalized2 = $this->normalizeTitle($title2);

        similar_text($normalized1, $normalized2, $percent);

        return $percent / 100;
    }

    /**
     * Calculate similarity between author strings
     */
    private function calculateAuthorSimilarity(string $authors1, string $authors2): float
    {
        $normalized1 = $this->normalizeAuthors($authors1);
        $normalized2 = $this->normalizeAuthors($authors2);

        similar_text($normalized1, $normalized2, $percent);

        return $percent / 100;
    }

    /**
     * Check if all significant words from author1 are present in author2
     */
    private function checkAuthorWordsMatch(string $author1, string $author2): bool
    {
        $normalized1 = $this->normalizeAuthors($author1);
        $normalized2 = $this->normalizeAuthors($author2);

        $words1 = array_filter(explode(' ', $normalized1), fn ($w) => strlen($w) >= 2);
        $words2 = array_filter(explode(' ', $normalized2), fn ($w) => strlen($w) >= 2);

        if (empty($words1)) {
            return false;
        }

        foreach ($words1 as $word) {
            $found = false;
            foreach ($words2 as $word2) {
                if (strlen($word) <= 3 || strlen($word2) <= 3) {
                    if ($word === $word2 || str_contains($word2, $word) || str_contains($word, $word2)) {
                        $found = true;
                        break;
                    }
                } else {
                    similar_text($word, $word2, $percent);
                    if ($percent >= 80) {
                        $found = true;
                        break;
                    }
                }
            }
            if (! $found) {
                return false;
            }
        }

        return true;
    }

    /**
     * Normalize title for comparison
     */
    private function normalizeTitle(string $title): string
    {
        $title = mb_strtolower($title, 'UTF-8');

        // Remove content in parentheses and brackets
        $title = preg_replace('/\([^)]*\)/', '', $title);
        $title = preg_replace('/\[[^\]]*\]/', '', $title);

        // Remove punctuation except spaces
        $title = preg_replace('/[^\p{L}\p{N}\s]/u', '', $title);

        // Normalize whitespace
        $title = preg_replace('/\s+/', ' ', $title);

        // Remove common articles
        $commonWords = ['the', 'a', 'an', 'o', 'os', 'as', 'um', 'uma', 'uns', 'umas', 'de', 'da', 'do', 'das', 'dos'];
        $words = explode(' ', $title);
        $words = array_filter($words, fn ($word) => ! in_array(trim($word), $commonWords));

        return trim(implode(' ', $words));
    }

    /**
     * Normalize author names for comparison
     */
    private function normalizeAuthors(string $authors): string
    {
        $authors = mb_strtolower($authors, 'UTF-8');
        $authors = preg_replace('/[^\p{L}\s,]/u', '', $authors);
        $authors = preg_replace('/\s+/', ' ', $authors);
        $authorList = array_map('trim', explode(',', $authors));

        return trim(implode(' ', $authorList));
    }

    /**
     * Determine the best Amazon region based on book language
     */
    private function getRegionForBook(Book $book): string
    {
        $languageToRegion = [
            'pt' => 'BR',
            'pt-BR' => 'BR',
            'pt_BR' => 'BR',
            'en' => 'US',
            'en-US' => 'US',
            'en_US' => 'US',
            'en-GB' => 'UK',
            'en_GB' => 'UK',
            'en-CA' => 'CA',
            'en_CA' => 'CA',
        ];

        $language = $book->language;

        if (! $language) {
            return 'BR';
        }

        $normalizedLang = strtolower(trim($language));

        if (isset($languageToRegion[$normalizedLang])) {
            return $languageToRegion[$normalizedLang];
        }

        $langPrefix = explode('-', $normalizedLang)[0];
        $langPrefix = explode('_', $langPrefix)[0];

        if (isset($languageToRegion[$langPrefix])) {
            return $languageToRegion[$langPrefix];
        }

        return 'BR';
    }

    /**
     * Build Amazon search URLs (may return multiple to try in order)
     * First tries ISBN if available, then falls back to title + author
     */
    private function buildSearchUrls(Book $book, array $regionConfig): array
    {
        $domain = $regionConfig['domain'] ?? 'amazon.com';
        $urls = [];

        // First: try title + author (more reliable)
        $searchTerm = $book->title;
        if (! empty($book->authors)) {
            $searchTerm .= ' '.$book->authors;
        }
        $urls[] = "https://www.{$domain}/s?k=".urlencode($searchTerm).'&i=stripbooks';

        // Second: try ISBN if available (as backup)
        if (! empty($book->isbn)) {
            $isbnTerm = preg_replace('/[^0-9X]/i', '', $book->isbn);
            $urls[] = "https://www.{$domain}/s?k=".urlencode($isbnTerm).'&i=stripbooks';
        }

        return $urls;
    }

    /**
     * Build Amazon search URL (deprecated - use buildSearchUrls)
     */
    private function buildSearchUrl(Book $book, array $regionConfig): string
    {
        return $this->buildSearchUrls($book, $regionConfig)[0];
    }

    /**
     * Get request headers for Amazon requests
     */
    private function getRequestHeaders(array $regionConfig): array
    {
        return [
            'User-Agent' => self::USER_AGENT,
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language' => $this->getAcceptLanguageHeader($regionConfig['language'] ?? 'en-US'),
            'Accept-Encoding' => 'gzip, deflate',
            'Connection' => 'keep-alive',
        ];
    }

    /**
     * Extract book data from Amazon product page HTML
     */
    private function extractProductData(string $html): array
    {
        $data = [
            'thumbnail' => null,
            'isbn' => null,
            'page_count' => null,
            'description' => null,
            'publisher' => null,
            'authors' => null,
            'height' => null,
            'width' => null,
            'thickness' => null,
        ];

        // Extract thumbnail - high resolution image
        if (preg_match('/data-a-dynamic-image="\{&quot;([^&]+)/', $html, $matches)) {
            $data['thumbnail'] = html_entity_decode($matches[1]);
        } elseif (preg_match('/<img[^>]*id="landingImage"[^>]*src="([^"]+)"/', $html, $matches)) {
            $data['thumbnail'] = $this->convertToHighResolution($matches[1]);
        } elseif (preg_match('/<img[^>]*id="imgBlkFront"[^>]*src="([^"]+)"/', $html, $matches)) {
            $data['thumbnail'] = $this->convertToHighResolution($matches[1]);
        }

        // Extract ISBN-13 - try multiple patterns
        // Pattern 1: New Amazon format with data attribute
        if (preg_match('/data-rpi-attribute-name="book_details-isbn13"[^>]*>.*?<span[^>]*>\s*(978[-\s]?\d[-\s]?\d{2}[-\s]?\d{6}[-\s]?\d)/is', $html, $matches)) {
            $data['isbn'] = preg_replace('/[^0-9]/', '', $matches[1]);
        }
        // Pattern 2: ISBN-13 followed by formatted number
        elseif (preg_match('/ISBN-13.*?(978[-\s]?\d[-\s]?\d{2}[-\s]?\d{6}[-\s]?\d)/is', $html, $matches)) {
            $data['isbn'] = preg_replace('/[^0-9]/', '', $matches[1]);
        }
        // Pattern 3: Old format with colon
        elseif (preg_match('/ISBN-13[:\s]*<[^>]*>[\s]*(\d{3}[-\s]?\d[-\s]?\d{2}[-\s]?\d{6}[-\s]?\d)/i', $html, $matches)) {
            $data['isbn'] = preg_replace('/[^0-9]/', '', $matches[1]);
        }
        // Pattern 4: Plain 13-digit ISBN
        elseif (preg_match('/ISBN-13[:\s]*(\d{13})/i', $html, $matches)) {
            $data['isbn'] = $matches[1];
        }

        // Extract page count
        if (preg_match('/(\d+)\s*(?:pages|páginas)/i', $html, $matches)) {
            $data['page_count'] = (int) $matches[1];
        }

        // Extract publisher
        if (preg_match('/(?:Publisher|Editora)[:\s]*<[^>]*>([^<]+)/i', $html, $matches)) {
            $data['publisher'] = trim(html_entity_decode($matches[1]));
        }

        // Extract authors from byline
        if (preg_match('/<span[^>]*class="author[^"]*"[^>]*>.*?<a[^>]*>([^<]+)/is', $html, $matches)) {
            $data['authors'] = trim(html_entity_decode($matches[1]));
        }

        // Extract dimensions
        if (preg_match('/(?:Dimens(?:ões|ions)|Dimensions)[:\s]*([0-9.,]+)\s*x\s*([0-9.,]+)\s*x\s*([0-9.,]+)\s*(cm|in)/i', $html, $matches)) {
            $multiplier = strtolower($matches[4]) === 'cm' ? 10 : 25.4;
            $data['width'] = round((float) str_replace(',', '.', $matches[1]) * $multiplier, 2);
            $data['thickness'] = round((float) str_replace(',', '.', $matches[2]) * $multiplier, 2);
            $data['height'] = round((float) str_replace(',', '.', $matches[3]) * $multiplier, 2);
        }

        // Extract description - try multiple patterns
        $description = null;

        // Pattern 1: bookDescription div with nested content (most common)
        if (preg_match('/<div[^>]*id="bookDescription[^"]*"[^>]*>(.*?)<\/div>\s*<\/div>/is', $html, $matches)) {
            $description = $matches[1];
        }
        // Pattern 2: bookDescription_feature_div containing the description
        elseif (preg_match('/<div[^>]*id="bookDescription_feature_div"[^>]*>.*?<div[^>]*data-a-expander-content[^>]*>(.*?)<\/div>/is', $html, $matches)) {
            $description = $matches[1];
        }
        // Pattern 3: iframeContent for some book pages
        elseif (preg_match('/<div[^>]*id="iframeContent"[^>]*>(.*?)<\/div>/is', $html, $matches)) {
            $description = $matches[1];
        }
        // Pattern 4: Simple span inside bookDescription
        elseif (preg_match('/<div[^>]*id="bookDescription[^"]*"[^>]*>.*?<span[^>]*>(.*?)<\/span>/is', $html, $matches)) {
            $description = $matches[1];
        }

        if ($description) {
            // Clean up the description
            // Convert Amazon CSS classes to HTML tags (before stripping other tags)
            // Bold + Italic
            $description = preg_replace('/<span[^>]*class="[^"]*a-text-bold[^"]*a-text-italic[^"]*"[^>]*>(.*?)<\/span>/is', '<strong><em>$1</em></strong>', $description);
            $description = preg_replace('/<span[^>]*class="[^"]*a-text-italic[^"]*a-text-bold[^"]*"[^>]*>(.*?)<\/span>/is', '<strong><em>$1</em></strong>', $description);
            // Bold only
            $description = preg_replace('/<span[^>]*class="[^"]*a-text-bold[^"]*"[^>]*>(.*?)<\/span>/is', '<strong>$1</strong>', $description);
            // Italic only
            $description = preg_replace('/<span[^>]*class="[^"]*a-text-italic[^"]*"[^>]*>(.*?)<\/span>/is', '<em>$1</em>', $description);

            // Replace <br> and <br/> with newlines
            $description = preg_replace('/<br\s*\/?>/i', "\n", $description);
            // Replace </p> with double newlines
            $description = preg_replace('/<\/p>/i', "\n\n", $description);
            // Strip HTML tags but keep formatting tags (bold, italic)
            $description = strip_tags($description, '<b><strong><i><em>');
            // Decode HTML entities
            $description = html_entity_decode($description, ENT_QUOTES, 'UTF-8');
            // Remove Amazon UI text
            $description = preg_replace('/\s*(Leia mais|Read more|Ver mais|See more)\s*$/i', '', $description);
            // Normalize whitespace (but preserve paragraph breaks)
            $description = preg_replace('/[ \t]+/', ' ', $description);
            $description = preg_replace('/\n{3,}/', "\n\n", $description);
            $description = trim($description);

            if (strlen($description) > 50) {
                $data['description'] = $description;
            }
        }

        return $data;
    }

    /**
     * Convert Amazon image URL to high resolution version
     */
    private function convertToHighResolution(string $url): string
    {
        $pattern = '/(\._[A-Z]{2}\d+_|\._AC_[A-Z]{2}\d+_)/';

        if (preg_match($pattern, $url)) {
            return preg_replace($pattern, '._SL1500_', $url);
        }

        $patternAlt = '/\._[A-Z]{2}\d+_\./';
        if (preg_match($patternAlt, $url)) {
            return preg_replace($patternAlt, '._SL1500_.', $url);
        }

        return $url;
    }

    /**
     * Get Accept-Language header based on region language
     */
    private function getAcceptLanguageHeader(string $language): string
    {
        $headers = [
            'pt-BR' => 'pt-BR,pt;q=0.8,en;q=0.5,en-US;q=0.3',
            'en-US' => 'en-US,en;q=0.8',
            'en-GB' => 'en-GB,en;q=0.8',
            'en-CA' => 'en-CA,en;q=0.8,fr;q=0.5',
        ];

        return $headers[$language] ?? 'en-US,en;q=0.8';
    }
}
