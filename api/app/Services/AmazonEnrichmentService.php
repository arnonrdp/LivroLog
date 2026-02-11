<?php

namespace App\Services;

use App\Models\Book;
use App\Services\Amazon\AmazonProviderFactory;
use Illuminate\Support\Facades\Log;

class AmazonEnrichmentService
{
    /**
     * Enrich book with Amazon data (admin version with scraper fallback)
     * Returns detailed result for admin UI feedback
     *
     * @param  bool  $forceRefresh  If true, will re-fetch even if book has ASIN
     */
    public function enrichBookWithAmazonAdmin(Book $book, bool $forceRefresh = false): array
    {
        try {
            // Skip if book already has ASIN and not forcing refresh
            if ($book->amazon_asin && ! $forceRefresh) {
                return [
                    'success' => true,
                    'message' => 'Book already has ASIN',
                    'source' => null,
                    'fields_filled' => [],
                ];
            }

            Log::info("Starting admin Amazon enrichment for book: {$book->title} (ID: {$book->id})", [
                'force_refresh' => $forceRefresh,
            ]);

            // Step 1: Try Amazon API first (Creators API or PA-API)
            $amazonData = $this->searchAmazonBook($book);
            $source = 'api';

            // Step 2: If API failed, try web scraping
            if (! $amazonData) {
                Log::info("Amazon API failed for book {$book->id}, trying web scraper");
                $scraperService = app(AmazonScraperService::class);
                $amazonData = $scraperService->searchAndExtract($book);
                $source = 'scraper';
            }

            if ($amazonData) {
                $filledFields = $this->updateBookWithAmazonDataAdmin($book, $amazonData, $forceRefresh);
                Log::info("Successfully enriched book {$book->id} with Amazon data via {$source}");

                return [
                    'success' => true,
                    'message' => "Book enriched via {$source}",
                    'source' => $source,
                    'fields_filled' => $filledFields,
                ];
            }

            // Neither PA-API nor scraper found data
            $book->update([
                'asin_status' => 'completed',
                'asin_processed_at' => now(),
            ]);

            Log::warning("Could not find Amazon data for book {$book->id}: {$book->title}");

            return [
                'success' => false,
                'message' => 'Could not find Amazon data',
                'source' => null,
                'fields_filled' => [],
            ];

        } catch (\Exception $e) {
            $book->update([
                'asin_status' => 'failed',
                'asin_processed_at' => now(),
            ]);
            Log::error("Admin Amazon enrichment failed for book {$book->id}: {$e->getMessage()}");

            return [
                'success' => false,
                'message' => 'Error enriching with Amazon: '.$e->getMessage(),
                'source' => null,
                'fields_filled' => [],
            ];
        }
    }

    /**
     * Try to enrich book using Amazon API only (no scraper fallback)
     * Used by admin endpoint - if this fails, frontend will show URL input dialog
     */
    public function enrichBookWithPaApiOnly(Book $book): array
    {
        try {
            Log::info("Trying Amazon API only for book: {$book->title} (ID: {$book->id})");

            $amazonData = $this->searchAmazonBook($book);

            if ($amazonData) {
                $filledFields = $this->updateBookWithAmazonDataAdmin($book, $amazonData, true);
                Log::info("Successfully enriched book {$book->id} with Amazon API");

                return [
                    'success' => true,
                    'message' => 'Book enriched via Amazon API',
                    'source' => 'api',
                    'fields_filled' => $filledFields,
                ];
            }

            Log::info("Amazon API returned no data for book {$book->id}, manual URL needed");

            return [
                'success' => false,
                'message' => 'Amazon API could not find this book. Please provide Amazon URL manually.',
                'source' => null,
                'fields_filled' => [],
            ];

        } catch (\Exception $e) {
            Log::error("Amazon API failed for book {$book->id}: {$e->getMessage()}");

            return [
                'success' => false,
                'message' => 'Amazon API error: '.$e->getMessage(),
                'source' => null,
                'fields_filled' => [],
            ];
        }
    }

    /**
     * Update book with Amazon data (admin version - can force overwrite)
     * Returns list of fields that were filled
     */
    private function updateBookWithAmazonDataAdmin(Book $book, array $amazonData, bool $forceUpdate = false): array
    {
        $updateData = [
            'asin_status' => 'completed',
            'asin_processed_at' => now(),
        ];

        $filledFields = [];

        // Always update ASIN if available
        if (isset($amazonData['amazon_asin']) && ! empty($amazonData['amazon_asin'])) {
            $updateData['amazon_asin'] = $amazonData['amazon_asin'];
            $filledFields[] = 'amazon_asin';
        }

        // Update thumbnail if Amazon has one and (book doesn't have one OR current is placeholder OR force update)
        $needsThumbnail = empty($book->thumbnail) || $this->isPlaceholderThumbnail($book->thumbnail);
        if (! empty($amazonData['thumbnail']) && ($forceUpdate || $needsThumbnail)) {
            $updateData['thumbnail'] = $amazonData['thumbnail'];
            $filledFields[] = 'thumbnail';
        }

        // Update ISBN if Amazon has one and book doesn't
        if (! empty($amazonData['isbn']) && empty($book->isbn)) {
            $updateData['isbn'] = $amazonData['isbn'];
            $filledFields[] = 'isbn';
        }

        // Add physical dimensions if available
        if (! empty($amazonData['height']) && ($forceUpdate || empty($book->height))) {
            $updateData['height'] = $amazonData['height'];
            $filledFields[] = 'height';
        }
        if (! empty($amazonData['width']) && ($forceUpdate || empty($book->width))) {
            $updateData['width'] = $amazonData['width'];
            $filledFields[] = 'width';
        }
        if (! empty($amazonData['thickness']) && ($forceUpdate || empty($book->thickness))) {
            $updateData['thickness'] = $amazonData['thickness'];
            $filledFields[] = 'thickness';
        }

        // Update other fields if missing (or force update)
        if (! empty($amazonData['page_count']) && ($forceUpdate || empty($book->page_count))) {
            $updateData['page_count'] = $amazonData['page_count'];
            $filledFields[] = 'page_count';
        }

        if (! empty($amazonData['description'])) {
            $existingLength = strlen($book->description ?? '');
            $newLength = strlen($amazonData['description']);
            // Update if: forced, no existing description, OR Amazon description is significantly longer (>50% more)
            if ($forceUpdate || $existingLength === 0 || $newLength > $existingLength * 1.5) {
                $updateData['description'] = $amazonData['description'];
                $filledFields[] = 'description';
            }
        }

        if (! empty($amazonData['publisher']) && ($forceUpdate || empty($book->publisher))) {
            $updateData['publisher'] = $amazonData['publisher'];
            $filledFields[] = 'publisher';
        }

        if (! empty($amazonData['authors']) && ($forceUpdate || empty($book->authors))) {
            $updateData['authors'] = $amazonData['authors'];
            $filledFields[] = 'authors';
        }

        $book->update($updateData);

        Log::info("Updated book {$book->id} with Amazon data (admin)", [
            'fields_updated' => $filledFields,
            'force_update' => $forceUpdate,
        ]);

        return $filledFields;
    }

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
     * Search for book data on Amazon using the configured provider (Creators API or PA-API)
     */
    private function searchAmazonBook(Book $book): ?array
    {
        try {
            $factory = app(AmazonProviderFactory::class);

            if (! $factory->isAnyProviderEnabled()) {
                Log::warning("No Amazon provider is enabled for book {$book->id}");

                return null;
            }

            $amazonProvider = $factory->create();

            Log::info("Using {$amazonProvider->getName()} for book {$book->id}");

            // Build search query - prefer ISBN, fallback to title + author
            $searchQuery = $this->buildSearchQuery($book);

            $result = $amazonProvider->search($searchQuery);

            if ($result['success'] && ! empty($result['books'])) {
                $amazonBook = $result['books'][0]; // Take first result

                // Validate this is likely the same book (ISBN match or high title similarity)
                if ($this->validateBookMatch($book, $amazonBook)) {
                    Log::info("Found Amazon data for book {$book->id}", [
                        'asin' => $amazonBook['amazon_asin'],
                        'source' => $amazonProvider->getName(),
                    ]);

                    return $amazonBook;
                }

                Log::warning("Amazon result doesn't match our book {$book->id}");
            } else {
                Log::info("No Amazon results found for book {$book->id}");
            }
        } catch (\Exception $e) {
            Log::error("Amazon search failed for book {$book->id}: {$e->getMessage()}");
        }

        return null;
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
        // If both have ISBN, they should match (handles ISBN-10 vs ISBN-13)
        if (! empty($book->isbn) && ! empty($amazonBook['isbn'])) {
            return $this->isbnMatch($book->isbn, $amazonBook['isbn']);
        }

        // Title similarity check (when no ISBN available)
        if (! empty($book->title) && ! empty($amazonBook['title'])) {
            // First check: is our title contained in Amazon's title?
            // This handles cases like "Sapiens" vs "Sapiens (Nova edição): Uma breve história"
            $normalized1 = $this->normalizeTitle($book->title);
            $normalized2 = $this->normalizeTitle($amazonBook['title']);

            $isContained = str_contains($normalized2, $normalized1) && strlen($normalized1) >= 3;

            $similarity = $this->calculateTitleSimilarity($book->title, $amazonBook['title']);

            Log::info("Title similarity check for book {$book->id}", [
                'our_title' => $book->title,
                'amazon_title' => $amazonBook['title'],
                'similarity' => $similarity,
                'is_contained' => $isContained,
            ]);

            // Match if: contained OR high similarity
            if ($isContained || $similarity >= 0.60) {
                // If we have author info, validate that too for extra confidence
                if (! empty($book->authors) && ! empty($amazonBook['authors'])) {
                    // Check if our author's words are all present in Amazon's author list
                    // (Amazon often uses "Last, First" format and includes translators)
                    $authorWordsMatch = $this->checkAuthorWordsMatch($book->authors, $amazonBook['authors']);

                    $authorSimilarity = $this->calculateAuthorSimilarity(
                        $book->authors,
                        $amazonBook['authors']
                    );

                    Log::info("Author similarity check for book {$book->id}", [
                        'our_authors' => $book->authors,
                        'amazon_authors' => $amazonBook['authors'],
                        'similarity' => $authorSimilarity,
                        'words_match' => $authorWordsMatch,
                    ]);

                    // Match if: all author words present OR high similarity (70%+)
                    return $authorWordsMatch || $authorSimilarity >= 0.70;
                }

                // If no author to compare, title similarity is enough
                return true;
            }

            Log::warning("Title similarity too low for book {$book->id}", [
                'required' => 0.60,
                'actual' => $similarity,
            ]);

            return false;
        }

        // If we can't validate, don't match
        Log::warning("Cannot validate match for book {$book->id}: missing title information");

        return false;
    }

    /**
     * Calculate similarity between two titles
     * Returns a value between 0.0 (completely different) and 1.0 (identical)
     */
    private function calculateTitleSimilarity(string $title1, string $title2): float
    {
        $normalized1 = $this->normalizeTitle($title1);
        $normalized2 = $this->normalizeTitle($title2);

        // Use similar_text for percentage similarity
        similar_text($normalized1, $normalized2, $percent);

        return $percent / 100;
    }

    /**
     * Calculate similarity between author strings
     * Returns a value between 0.0 (completely different) and 1.0 (identical)
     */
    private function calculateAuthorSimilarity(string $authors1, string $authors2): float
    {
        $normalized1 = $this->normalizeAuthors($authors1);
        $normalized2 = $this->normalizeAuthors($authors2);

        similar_text($normalized1, $normalized2, $percent);

        return $percent / 100;
    }

    /**
     * Normalize title for comparison
     * - Lowercase
     * - Remove punctuation and special characters
     * - Normalize whitespace
     * - Remove common words (the, a, an, etc.)
     */
    private function normalizeTitle(string $title): string
    {
        // Lowercase
        $title = mb_strtolower($title, 'UTF-8');

        // Remove content in parentheses (often edition info, language, etc.)
        $title = preg_replace('/\([^)]*\)/', '', $title);

        // Remove content in brackets
        $title = preg_replace('/\[[^\]]*\]/', '', $title);

        // Remove punctuation except spaces
        $title = preg_replace('/[^\p{L}\p{N}\s]/u', '', $title);

        // Normalize whitespace
        $title = preg_replace('/\s+/', ' ', $title);

        // Remove common articles and words (multilingual)
        $commonWords = ['the', 'a', 'an', 'o', 'a', 'os', 'as', 'um', 'uma', 'uns', 'umas', 'de', 'da', 'do', 'das', 'dos'];
        $words = explode(' ', $title);
        $words = array_filter($words, function ($word) use ($commonWords) {
            return ! in_array(trim($word), $commonWords);
        });

        return trim(implode(' ', $words));
    }

    /**
     * Check if all significant words from author1 are present in author2
     * Handles different formats like "J.R.R. Tolkien" vs "Tolkien, J.R.R."
     */
    private function checkAuthorWordsMatch(string $author1, string $author2): bool
    {
        $normalized1 = $this->normalizeAuthors($author1);
        $normalized2 = $this->normalizeAuthors($author2);

        // Split into words
        $words1 = array_filter(explode(' ', $normalized1), fn ($w) => strlen($w) >= 2);
        $words2 = array_filter(explode(' ', $normalized2), fn ($w) => strlen($w) >= 2);

        if (empty($words1)) {
            return false;
        }

        // Check if all words from author1 are present in author2
        foreach ($words1 as $word) {
            $found = false;
            foreach ($words2 as $word2) {
                // For very short words (initials), use contains or exact match
                if (strlen($word) <= 3 || strlen($word2) <= 3) {
                    if ($word === $word2 || str_contains($word2, $word) || str_contains($word, $word2)) {
                        $found = true;
                        break;
                    }
                } else {
                    // For regular words, use similarity (80%+)
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
     * Normalize author names for comparison
     * - Lowercase
     * - Remove punctuation
     * - Normalize whitespace
     */
    private function normalizeAuthors(string $authors): string
    {
        // Lowercase
        $authors = mb_strtolower($authors, 'UTF-8');

        // Remove punctuation except spaces and commas
        $authors = preg_replace('/[^\p{L}\s,]/u', '', $authors);

        // Normalize whitespace
        $authors = preg_replace('/\s+/', ' ', $authors);

        // Split into individual authors and join with spaces
        $authorList = array_map('trim', explode(',', $authors));

        return trim(implode(' ', $authorList));
    }

    /**
     * Normalize ISBN by removing hyphens, spaces and other formatting
     */
    private function normalizeIsbn(string $isbn): string
    {
        return preg_replace('/[^0-9X]/i', '', $isbn);
    }

    /**
     * Check if two ISBNs refer to the same book (handles ISBN-10 vs ISBN-13)
     */
    private function isbnMatch(string $isbn1, string $isbn2): bool
    {
        $a = $this->normalizeIsbn($isbn1);
        $b = $this->normalizeIsbn($isbn2);

        if ($a === $b) {
            return true;
        }

        // Convert both to ISBN-13 for comparison
        return $this->toIsbn13($a) === $this->toIsbn13($b);
    }

    /**
     * Convert an ISBN-10 to ISBN-13 format. Returns as-is if already ISBN-13.
     */
    private function toIsbn13(string $isbn): string
    {
        if (strlen($isbn) === 13) {
            return $isbn;
        }

        if (strlen($isbn) !== 10) {
            return $isbn;
        }

        // Take first 9 digits, prepend 978
        $base = '978'.substr($isbn, 0, 9);

        // Calculate ISBN-13 check digit
        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $sum += intval($base[$i]) * ($i % 2 === 0 ? 1 : 3);
        }
        $check = (10 - ($sum % 10)) % 10;

        return $base.$check;
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

            if (! empty($amazonData['description'])) {
                $existingLength = strlen($book->description ?? '');
                $newLength = strlen($amazonData['description']);
                // Update if: no existing description, OR Amazon description is significantly longer (>50% more)
                if ($existingLength === 0 || $newLength > $existingLength * 1.5) {
                    $updateData['description'] = $amazonData['description'];
                    $filledFields[] = 'description';
                }
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

    /**
     * Check if a thumbnail URL is a placeholder or likely invalid
     */
    private function isPlaceholderThumbnail(?string $url): bool
    {
        if (empty($url)) {
            return true;
        }

        // Check for placeholder IDs in Google Books URLs
        if (str_contains($url, 'books.google.com')) {
            // id=test or similar placeholder IDs
            if (preg_match('/[?&]id=(test|placeholder|dummy|example|sample|xxx)/i', $url)) {
                return true;
            }
        }

        // Check for known placeholder image URLs
        $placeholderPatterns = [
            'placeholder',
            'no-image',
            'noimage',
            'no_image',
            'default-cover',
            'default_cover',
            'missing-cover',
        ];

        $urlLower = strtolower($url);
        foreach ($placeholderPatterns as $pattern) {
            if (str_contains($urlLower, $pattern)) {
                return true;
            }
        }

        return false;
    }
}
