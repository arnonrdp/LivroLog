<?php

namespace App\Jobs;

use App\Models\Book;
use App\Services\AmazonLinkEnrichmentService;
use App\Services\Providers\AmazonBooksProvider;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class EnrichBookWithAmazonJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var array
     */
    public $backoff = [60, 300, 900]; // 1 min, 5 min, 15 min

    /**
     * The book to enrich with Amazon ASIN.
     */
    public function __construct(
        public Book $book
    ) {
        // Add delay to avoid rate limiting
        $this->delay(now()->addSeconds(random_int(5, 30)));
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Skip if book already has ASIN
            if ($this->book->amazon_asin) {
                $this->book->update([
                    'asin_status' => 'completed',
                    'asin_processed_at' => now(),
                ]);
                Log::info("Book {$this->book->id} already has ASIN, marking as completed");

                return;
            }

            Log::info("Starting Amazon enrichment for book: {$this->book->title} (ID: {$this->book->id})");

            $amazonData = $this->searchAmazonBook();

            if ($amazonData) {
                $this->updateBookWithAmazonData($amazonData);
                Log::info("Successfully enriched book {$this->book->id} with Amazon data");
            } else {
                $this->book->update([
                    'asin_status' => 'failed',
                    'asin_processed_at' => now(),
                ]);
                Log::warning("Could not find Amazon data for book {$this->book->id}: {$this->book->title}");
            }

        } catch (\Exception $e) {
            $this->book->update([
                'asin_status' => 'failed',
                'asin_processed_at' => now(),
            ]);
            Log::error("Failed to enrich book {$this->book->id} with Amazon data: {$e->getMessage()}");
            throw $e; // Re-throw to trigger retry mechanism
        }
    }

    /**
     * Search for book data on Amazon using available providers
     */
    private function searchAmazonBook(): ?array
    {
        // Phase 2: Try Amazon PA-API first (when available)
        if (config('services.amazon.enabled', false)) {
            try {
                $amazonProvider = app(AmazonBooksProvider::class);

                if ($amazonProvider->isEnabled()) {
                    Log::info("Using Amazon PA-API for book {$this->book->id}");

                    // Build search query - prefer ISBN, fallback to title + author
                    $searchQuery = $this->buildSearchQuery();

                    $result = $amazonProvider->search($searchQuery);

                    if ($result['success'] && ! empty($result['books'])) {
                        $amazonBook = $result['books'][0]; // Take first result

                        // Validate this is likely the same book (ISBN match or high title similarity)
                        if ($this->validateBookMatch($amazonBook)) {
                            return $amazonBook;
                        } else {
                            Log::warning("Amazon result doesn't match our book {$this->book->id}");
                        }
                    }
                }
            } catch (\Exception $e) {
                Log::warning("Amazon PA-API search failed for book {$this->book->id}: {$e->getMessage()}");
            }
        }

        // Phase 1: Simple Amazon search using basic method
        Log::info("Using basic Amazon search for book {$this->book->id}");

        $asinData = $this->searchAmazonBasic();

        if ($asinData) {
            Log::info("Found Amazon data for book {$this->book->id}", [
                'asin' => $asinData['asin'],
                'source' => $asinData['source'] ?? 'basic_search',
            ]);

            // Generate proper Amazon link with found ASIN
            $enrichmentService = app(AmazonLinkEnrichmentService::class);
            $bookWithAsin = array_merge($this->book->toArray(), ['amazon_asin' => $asinData['asin']]);
            $enrichedBooks = $enrichmentService->enrichBooksWithAmazonLinks([$bookWithAsin]);

            return [
                'amazon_asin' => $asinData['asin'],
                'amazon_buy_link' => $enrichedBooks[0]['amazon_buy_link'] ?? null,
                'amazon_region' => $enrichedBooks[0]['amazon_region'] ?? 'BR',
                'thumbnail' => $asinData['thumbnail'] ?? null,
            ];
        }

        // Fallback: Generate search link without specific ASIN (still provides affiliate value)
        Log::info("Could not find specific ASIN, generating fallback Amazon link for book {$this->book->id}");

        $enrichmentService = app(AmazonLinkEnrichmentService::class);
        $enrichedBooks = $enrichmentService->enrichBooksWithAmazonLinks([$this->book->toArray()]);

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
    private function searchAmazonBasic(): ?array
    {
        try {
            // Try ISBN first (most reliable)
            if (! empty($this->book->isbn)) {
                $result = $this->searchAmazonByIsbn($this->book->isbn);
                if ($result) {
                    return $result;
                }
            }

            // Fallback to title + author search
            if (! empty($this->book->title)) {
                $query = $this->book->title;
                if (! empty($this->book->authors)) {
                    $query .= ' '.$this->book->authors;
                }

                $result = $this->searchAmazonByQuery($query);
                if ($result) {
                    return $result;
                }
            }

            return null;

        } catch (\Exception $e) {
            Log::warning("Basic Amazon search failed for book {$this->book->id}: {$e->getMessage()}");

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
        return preg_match('/^[A-Z0-9]{10}$/', $asin) && ! preg_match('/^[0-9]{10}$/', $asin);
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
    private function buildSearchQuery(): string
    {
        if (! empty($this->book->isbn)) {
            return $this->book->isbn;
        }

        $query = $this->book->title;
        if (! empty($this->book->authors)) {
            $query .= ' '.$this->book->authors;
        }

        return trim($query);
    }

    /**
     * Validate if Amazon result matches our book
     */
    private function validateBookMatch(array $amazonBook): bool
    {
        // If both have ISBN, they should match
        if (! empty($this->book->isbn) && ! empty($amazonBook['isbn'])) {
            return $this->book->isbn === $amazonBook['isbn'];
        }

        // TODO: Implement title similarity check for Phase 2
        // For now, assume it's a match if we reach here
        return true;
    }

    /**
     * Update book with Amazon data
     */
    private function updateBookWithAmazonData(array $amazonData): void
    {
        $updateData = [
            'asin_status' => 'completed',
            'asin_processed_at' => now(),
        ];

        // Phase 2: Full data update (when PA-API is available)
        if (isset($amazonData['amazon_asin']) && ! empty($amazonData['amazon_asin'])) {
            $updateData['amazon_asin'] = $amazonData['amazon_asin'];

            // Update thumbnail if Amazon has a better one
            if (! empty($amazonData['thumbnail']) && empty($this->book->thumbnail)) {
                $updateData['thumbnail'] = $amazonData['thumbnail'];
            }

            // Add physical dimensions if available
            if (! empty($amazonData['height'])) {
                $updateData['height'] = $amazonData['height'];
            }
            if (! empty($amazonData['width'])) {
                $updateData['width'] = $amazonData['width'];
            }
            if (! empty($amazonData['thickness'])) {
                $updateData['thickness'] = $amazonData['thickness'];
            }

            // Update other fields if missing
            if (! empty($amazonData['page_count']) && empty($this->book->page_count)) {
                $updateData['page_count'] = $amazonData['page_count'];
            }

            if (! empty($amazonData['description']) && empty($this->book->description)) {
                $updateData['description'] = $amazonData['description'];
            }
        }

        $this->book->update($updateData);

        Log::info("Updated book {$this->book->id} with Amazon data", [
            'fields_updated' => array_keys($updateData),
            'has_asin' => isset($updateData['amazon_asin']),
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("Amazon ASIN enrichment failed permanently for book {$this->book->id}: {$exception->getMessage()}");
    }
}
