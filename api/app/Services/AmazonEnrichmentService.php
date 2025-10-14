<?php

namespace App\Services;

use App\Models\Book;
use App\Services\Providers\AmazonBooksProvider;
use Illuminate\Support\Facades\Log;

class AmazonEnrichmentService
{
    public function __construct(
        private AmazonLinkEnrichmentService $amazonLinkService
    ) {}

    /**
     * Enrich book with Amazon data synchronously
     * Returns array with success status and filled fields
     */
    public function enrichBookWithAmazon(Book $book): array
    {
        try {
            // Skip if book already has ASIN
            if ($book->amazon_asin) {
                $book->update([
                    'asin_status' => 'completed',
                    'asin_processed_at' => now(),
                ]);
                Log::info("Book {$book->id} already has ASIN, marking as completed");

                return [
                    'success' => true,
                    'message' => 'Book already has ASIN',
                    'fields_filled' => [],
                ];
            }

            Log::info("Starting Amazon enrichment for book: {$book->title} (ID: {$book->id})");

            $amazonData = $this->searchAmazonBook($book);

            if ($amazonData) {
                $filledFields = $this->updateBookWithAmazonData($book, $amazonData);
                Log::info("Successfully enriched book {$book->id} with Amazon data");

                return [
                    'success' => true,
                    'message' => 'Book enriched with Amazon data',
                    'fields_filled' => $filledFields,
                ];
            } else {
                $book->update([
                    'asin_status' => 'failed',
                    'asin_processed_at' => now(),
                ]);
                Log::warning("Could not find Amazon data for book {$book->id}: {$book->title}");

                return [
                    'success' => false,
                    'message' => 'Could not find Amazon data',
                    'fields_filled' => [],
                ];
            }

        } catch (\Exception $e) {
            $book->update([
                'asin_status' => 'failed',
                'asin_processed_at' => now(),
            ]);
            Log::error("Failed to enrich book {$book->id} with Amazon data: {$e->getMessage()}");

            return [
                'success' => false,
                'message' => 'Error enriching with Amazon: '.$e->getMessage(),
                'fields_filled' => [],
            ];
        }
    }

    /**
     * Search for book data on Amazon using available providers
     */
    private function searchAmazonBook(Book $book): ?array
    {
        // Phase 2: Try Amazon PA-API first (when available)
        if (config('services.amazon.enabled', false)) {
            try {
                $amazonProvider = app(AmazonBooksProvider::class);

                if ($amazonProvider->isEnabled()) {
                    Log::info("Using Amazon PA-API for book {$book->id}");

                    // Build search query - prefer ISBN, fallback to title + author
                    $searchQuery = $this->buildSearchQuery($book);

                    $result = $amazonProvider->search($searchQuery);

                    if ($result['success'] && ! empty($result['books'])) {
                        $amazonBook = $result['books'][0]; // Take first result

                        // Validate this is likely the same book (ISBN match or high title similarity)
                        if ($this->validateBookMatch($book, $amazonBook)) {
                            return $amazonBook;
                        } else {
                            Log::warning("Amazon result doesn't match our book {$book->id}");
                        }
                    }
                }
            } catch (\Exception $e) {
                Log::warning("Amazon PA-API search failed for book {$book->id}: {$e->getMessage()}");
            }
        }

        // Phase 1: Simple Amazon search using basic method
        Log::info("Using basic Amazon search for book {$book->id}");

        $asinData = $this->searchAmazonBasic($book);

        if ($asinData) {
            Log::info("Found Amazon data for book {$book->id}", [
                'asin' => $asinData['asin'],
                'source' => $asinData['source'] ?? 'basic_search',
            ]);

            // Generate proper Amazon link with found ASIN
            $bookWithAsin = array_merge($book->toArray(), ['amazon_asin' => $asinData['asin']]);
            $enrichedBooks = $this->amazonLinkService->enrichBooksWithAmazonLinks([$bookWithAsin]);

            return [
                'amazon_asin' => $asinData['asin'],
                'amazon_buy_link' => $enrichedBooks[0]['amazon_buy_link'] ?? null,
                'amazon_region' => $enrichedBooks[0]['amazon_region'] ?? 'BR',
                'thumbnail' => $asinData['thumbnail'] ?? null,
            ];
        }

        // Fallback: Generate search link without specific ASIN (still provides affiliate value)
        Log::info("Could not find specific ASIN, generating fallback Amazon link for book {$book->id}");

        $enrichedBooks = $this->amazonLinkService->enrichBooksWithAmazonLinks([$book->toArray()]);

        return [
            'amazon_asin' => null,
            'amazon_buy_link' => $enrichedBooks[0]['amazon_buy_link'] ?? null,
            'amazon_region' => $enrichedBooks[0]['amazon_region'] ?? 'BR',
            'source' => 'search_fallback',
        ];
    }

    /**
     * Basic Amazon search using simple HTTP requests
     */
    private function searchAmazonBasic(Book $book): ?array
    {
        try {
            // Try ISBN first (most reliable)
            if (! empty($book->isbn)) {
                $result = $this->searchAmazonByIsbn($book->isbn);
                if ($result) {
                    return $result;
                }
            }

            // Fallback to title + author search
            if (! empty($book->title)) {
                $query = $book->title;
                if (! empty($book->authors)) {
                    $query .= ' '.$book->authors;
                }

                $result = $this->searchAmazonByQuery($query);
                if ($result) {
                    return $result;
                }
            }

            return null;

        } catch (\Exception $e) {
            Log::warning("Basic Amazon search failed for book {$book->id}: {$e->getMessage()}");

            return null;
        }
    }

    /**
     * Search Amazon by ISBN
     */
    private function searchAmazonByIsbn(string $isbn): ?array
    {
        $cleanIsbn = preg_replace('/[^0-9X]/i', '', $isbn);

        // Search Amazon Brazil first (most likely to have Portuguese books)
        $searchUrl = "https://www.amazon.com.br/s?k={$cleanIsbn}&i=stripbooks&ref=nb_sb_noss";

        Log::info("Searching Amazon BR for ISBN: {$cleanIsbn}");

        return $this->parseAmazonSearchPage($searchUrl, $cleanIsbn);
    }

    /**
     * Search Amazon by query
     */
    private function searchAmazonByQuery(string $query): ?array
    {
        $encodedQuery = urlencode(trim($query));

        // Search Amazon Brazil first
        $searchUrl = "https://www.amazon.com.br/s?k={$encodedQuery}&i=stripbooks&ref=nb_sb_noss";

        Log::info("Searching Amazon BR for query: {$query}");

        return $this->parseAmazonSearchPage($searchUrl, $query);
    }

    /**
     * Parse Amazon search page to extract ASIN
     */
    private function parseAmazonSearchPage(string $url, string $searchTerm): ?array
    {
        try {
            $userAgent = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => [
                        "User-Agent: {$userAgent}",
                        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8',
                        'Accept-Language: pt-BR,pt;q=0.9,en;q=0.8',
                        'Accept-Encoding: gzip, deflate, br',
                        'DNT: 1',
                        'Connection: keep-alive',
                        'Upgrade-Insecure-Requests: 1',
                        'Sec-Fetch-Dest: document',
                        'Sec-Fetch-Mode: navigate',
                        'Sec-Fetch-Site: none',
                        'Cache-Control: max-age=0',
                    ],
                    'timeout' => 15,
                ],
            ]);

            // Add delay to be more respectful
            sleep(1);

            // Try with cURL first, fallback to file_get_contents
            $html = $this->fetchUrlWithCurl($url) ?: @file_get_contents($url, false, $context);

            if (! $html) {
                Log::warning("Failed to fetch Amazon search page: {$url}");

                return null;
            }

            // Look for ASIN patterns in search results
            // Amazon search results contain data-asin attributes
            if (preg_match_all('/data-asin="([A-Z0-9]{10})"/', $html, $matches)) {
                $asins = array_unique($matches[1]);

                // Return first valid ASIN
                foreach ($asins as $asin) {
                    if ($this->isValidAsin($asin)) {
                        Log::info("Found ASIN {$asin} for search term: {$searchTerm}");

                        return [
                            'asin' => $asin,
                            'source' => 'search_page_parsing',
                            'search_term' => $searchTerm,
                        ];
                    }
                }
            }

            Log::info("No valid ASIN found in Amazon search for: {$searchTerm}");

            return null;

        } catch (\Exception $e) {
            Log::warning("Error parsing Amazon search page: {$e->getMessage()}");

            return null;
        }
    }

    /**
     * Validate ASIN format
     */
    private function isValidAsin(string $asin): bool
    {
        // Accept both traditional ASINs (B123456789) and numeric ISBNs (1234567890)
        // Amazon Brazil often uses ISBNs directly as ASINs for books
        return preg_match('/^[A-Z0-9]{10}$/', $asin);
    }

    /**
     * Fetch URL with cURL (more robust than file_get_contents)
     */
    private function fetchUrlWithCurl(string $url): ?string
    {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8',
                'Accept-Language: pt-BR,pt;q=0.9,en;q=0.8',
                'Accept-Encoding: gzip, deflate, br',
                'DNT: 1',
                'Connection: keep-alive',
                'Upgrade-Insecure-Requests: 1',
                'Sec-Fetch-Dest: document',
                'Sec-Fetch-Mode: navigate',
                'Sec-Fetch-Site: none',
                'Cache-Control: max-age=0',
            ],
            CURLOPT_ENCODING => '', // Support gzip
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        if ($response === false || $httpCode !== 200) {
            return null;
        }

        return $response;
    }

    /**
     * Build search query for Amazon search
     */
    private function buildSearchQuery(Book $book): string
    {
        if (! empty($book->isbn)) {
            return $book->isbn;
        }

        $query = $book->title;
        if (! empty($book->authors)) {
            $query .= ' '.$book->authors;
        }

        return trim($query);
    }

    /**
     * Validate if Amazon result matches our book
     */
    private function validateBookMatch(Book $book, array $amazonBook): bool
    {
        // If both have ISBN, they should match
        if (! empty($book->isbn) && ! empty($amazonBook['isbn'])) {
            return $book->isbn === $amazonBook['isbn'];
        }

        // TODO: Implement title similarity check for Phase 2
        // For now, assume it's a match if we reach here
        return true;
    }

    /**
     * Update book with Amazon data
     * Returns list of fields that were filled
     */
    private function updateBookWithAmazonData(Book $book, array $amazonData): array
    {
        $updateData = [
            'asin_status' => 'completed',
            'asin_processed_at' => now(),
        ];

        $filledFields = [];

        // Phase 2: Full data update (when PA-API is available)
        if (isset($amazonData['amazon_asin']) && ! empty($amazonData['amazon_asin'])) {
            $updateData['amazon_asin'] = $amazonData['amazon_asin'];
            $filledFields[] = 'amazon_asin';

            // Update thumbnail if Amazon has one and book doesn't
            if (! empty($amazonData['thumbnail']) && empty($book->thumbnail)) {
                $updateData['thumbnail'] = $amazonData['thumbnail'];
                $filledFields[] = 'thumbnail';
            }

            // Add physical dimensions if available
            if (! empty($amazonData['height']) && empty($book->height)) {
                $updateData['height'] = $amazonData['height'];
                $filledFields[] = 'height';
            }
            if (! empty($amazonData['width']) && empty($book->width)) {
                $updateData['width'] = $amazonData['width'];
                $filledFields[] = 'width';
            }
            if (! empty($amazonData['thickness']) && empty($book->thickness)) {
                $updateData['thickness'] = $amazonData['thickness'];
                $filledFields[] = 'thickness';
            }

            // Update other fields if missing
            if (! empty($amazonData['page_count']) && empty($book->page_count)) {
                $updateData['page_count'] = $amazonData['page_count'];
                $filledFields[] = 'page_count';
            }

            if (! empty($amazonData['description']) && empty($book->description)) {
                $updateData['description'] = $amazonData['description'];
                $filledFields[] = 'description';
            }

            if (! empty($amazonData['publisher']) && empty($book->publisher)) {
                $updateData['publisher'] = $amazonData['publisher'];
                $filledFields[] = 'publisher';
            }

            if (! empty($amazonData['authors']) && empty($book->authors)) {
                $updateData['authors'] = $amazonData['authors'];
                $filledFields[] = 'authors';
            }
        }

        $book->update($updateData);

        Log::info("Updated book {$book->id} with Amazon data", [
            'fields_updated' => array_keys($updateData),
            'has_asin' => isset($updateData['amazon_asin']),
        ]);

        return $filledFields;
    }
}
