<?php

namespace App\Services;

use App\Models\Book;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BookEnrichmentService
{
    private const GOOGLE_BOOKS_API = 'https://www.googleapis.com/books/v1/volumes';

    private const BATCH_SIZE = 10;

    /**
     * Enriquece um livro específico usando Google Books API
     */
    public function enrichBook(Book $book, ?string $googleId = null): array
    {
        try {
            $googleBookData = $this->fetchBookFromGoogle($googleId ?? $book->google_id ?? $book->isbn);

            if (! $googleBookData) {
                return [
                    'success' => false,
                    'message' => 'Book not found in Google Books API',
                    'book_id' => $book->id,
                ];
            }

            $enrichedData = $this->extractEnrichedData($googleBookData, $book);
            $book->update($enrichedData);

            return [
                'success' => true,
                'message' => 'Book enriched successfully',
                'book_id' => $book->id,
                'added_fields' => array_keys($enrichedData),
            ];

        } catch (\Exception $e) {
            Log::error('Error enriching book', [
                'book_id' => $book->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Internal error: '.$e->getMessage(),
                'book_id' => $book->id,
            ];
        }
    }

    /**
     * Enriches multiple books in batch
     */
    public function enrichBooksInBatch(?array $bookIds = null): array
    {
        $query = Book::where('info_quality', 'basic')
            ->whereNull('enriched_at');

        if ($bookIds) {
            $query->whereIn('id', $bookIds);
        }

        $books = $query->limit(self::BATCH_SIZE)->get();
        $results = [];

        foreach ($books as $book) {
            $results[] = $this->enrichBook($book);

            // Rate limiting to respect API limits (5 requests per second max)
            $this->enforceRateLimit();
        }

        return [
            'processed' => count($results),
            'results' => $results,
            'success_count' => collect($results)->where('success', true)->count(),
            'error_count' => collect($results)->where('success', false)->count(),
        ];
    }

    /**
     * Searches for book in Google Books API
     */
    private function fetchBookFromGoogle(string $identifier): ?array
    {
        // First tries to search by specific Google Books ID
        if (strlen($identifier) > 10 && ! is_numeric($identifier)) {
            $response = Http::get(self::GOOGLE_BOOKS_API.'/'.$identifier);

            if ($response->successful()) {
                return $response->json();
            }
        }

        // If not found, tries to search as ISBN
        $response = Http::get(self::GOOGLE_BOOKS_API, [
            'q' => 'isbn:'.$identifier,
            'maxResults' => 1,
        ]);

        if ($response->successful()) {
            $data = $response->json();

            return $data['items'][0] ?? null;
        }

        return null;
    }

    /**
     * Extracts enriched data from Google Books API response
     */
    private function extractEnrichedData(array $googleBookData, ?Book $book = null): array
    {
        $volumeInfo = $googleBookData['volumeInfo'] ?? [];
        $data = [];

        $data = array_merge($data, $this->extractBasicInfo($volumeInfo));
        $data = array_merge($data, $this->extractThumbnail($volumeInfo));
        $data = array_merge($data, $this->extractPublicationDate($volumeInfo, $book));
        $data = array_merge($data, $this->extractAuthorInfo($volumeInfo));
        $data = array_merge($data, $this->extractPublisherInfo($volumeInfo));
        $data = array_merge($data, $this->extractPageInfo($volumeInfo));
        $data = array_merge($data, $this->extractCategoriesAndIdentifiers($volumeInfo));
        $data = array_merge($data, $this->extractPhysicalDimensions($volumeInfo));
        $data = array_merge($data, $this->extractMaturityAndRating($volumeInfo));

        // Google ID
        if (isset($googleBookData['id'])) {
            $data['google_id'] = $googleBookData['id'];
        }

        // Determine format based on available information
        $data['format'] = $this->determineFormat($googleBookData);

        // Information quality
        $data['info_quality'] = $this->determineInfoQuality(
            $data,
            $data['height'] ?? null,
            $data['width'] ?? null,
            $data['thickness'] ?? null
        );

        // Mark as enriched
        $data['enriched_at'] = now();

        return array_filter($data, function ($value) {
            return ! is_null($value) && ! (is_string($value) && $value === '');
        });
    }

    /**
     * Extract basic information (title, subtitle, description)
     */
    private function extractBasicInfo(array $volumeInfo): array
    {
        $data = [];

        if (isset($volumeInfo['title'])) {
            $data['title'] = $volumeInfo['title'];
        }

        if (isset($volumeInfo['subtitle'])) {
            $data['subtitle'] = $volumeInfo['subtitle'];
        }

        if (isset($volumeInfo['description'])) {
            $data['description'] = $volumeInfo['description'];
        }

        return $data;
    }

    /**
     * Extract thumbnail image
     */
    private function extractThumbnail(array $volumeInfo): array
    {
        $data = [];

        if (isset($volumeInfo['imageLinks']['thumbnail'])) {
            $data['thumbnail'] = str_replace('http:', 'https:', $volumeInfo['imageLinks']['thumbnail']);
        }

        return $data;
    }

    /**
     * Extract publication date with smart updating logic
     */
    private function extractPublicationDate(array $volumeInfo, ?Book $book): array
    {
        $data = [];

        if (! isset($volumeInfo['publishedDate'])) {
            return $data;
        }

        $googleDate = $volumeInfo['publishedDate'];
        $currentDate = $book ? $book->published_date : null;

        $shouldUpdateDate = $this->shouldUpdatePublishedDate($currentDate, $googleDate);

        if ($shouldUpdateDate) {
            $data['published_date'] = $this->parsePublishedDate($googleDate);
        }

        return $data;
    }

    /**
     * Determine if we should update the published date
     */
    private function shouldUpdatePublishedDate($currentDate, string $googleDate): bool
    {
        if (! $currentDate) {
            return true; // No existing date, use Google's data
        }

        $hasYearOnlyPrecision = $currentDate->format('m-d') === '01-01';
        $googlePrecision = $this->getDatePrecision($googleDate);

        return $this->shouldReplaceBasedOnPrecision($hasYearOnlyPrecision, $googlePrecision);
    }

    /**
     * Get the precision level of a date string
     */
    private function getDatePrecision(string $dateString): string
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $dateString)) {
            return 'full';
        }
        if (preg_match('/^\d{4}-\d{2}$/', $dateString)) {
            return 'year_month';
        }
        if (preg_match('/^\d{4}$/', $dateString)) {
            return 'year_only';
        }

        return 'unknown';
    }

    /**
     * Determine if we should replace current date based on precision comparison
     */
    private function shouldReplaceBasedOnPrecision(bool $hasYearOnlyPrecision, string $googlePrecision): bool
    {
        if ($hasYearOnlyPrecision && in_array($googlePrecision, ['full', 'year_month'])) {
            return true; // We have year-only, Google has better precision
        }

        if (! $hasYearOnlyPrecision && $googlePrecision === 'year_only') {
            return false; // We have better precision than Google
        }

        return $googlePrecision === 'full'; // Use Google if it has full date
    }

    /**
     * Extract author information
     */
    private function extractAuthorInfo(array $volumeInfo): array
    {
        // This service focuses on book-level data
        // Author extraction is handled separately
        return [];
    }

    /**
     * Extract publisher information
     */
    private function extractPublisherInfo(array $volumeInfo): array
    {
        $data = [];

        if (isset($volumeInfo['publisher'])) {
            $data['publisher'] = $volumeInfo['publisher'];
        }

        return $data;
    }

    /**
     * Extract page and format information
     */
    private function extractPageInfo(array $volumeInfo): array
    {
        $data = [];

        if (isset($volumeInfo['pageCount'])) {
            $data['page_count'] = (int) $volumeInfo['pageCount'];
        }

        if (isset($volumeInfo['printType'])) {
            $data['print_type'] = $volumeInfo['printType'];
        }

        return $data;
    }

    /**
     * Extract categories and industry identifiers
     */
    private function extractCategoriesAndIdentifiers(array $volumeInfo): array
    {
        $data = [];

        // Categories - normalize to always be an array
        if (isset($volumeInfo['categories'])) {
            if (is_array($volumeInfo['categories'])) {
                $data['categories'] = $volumeInfo['categories'];
            } else {
                $data['categories'] = [$volumeInfo['categories']];
            }
        }

        // Industry identifiers (all ISBNs)
        if (isset($volumeInfo['industryIdentifiers'])) {
            $data['industry_identifiers'] = $volumeInfo['industryIdentifiers'];

            // Updates main ISBN if not available
            if (empty($data['isbn'])) {
                foreach ($volumeInfo['industryIdentifiers'] as $identifier) {
                    if (in_array($identifier['type'], ['ISBN_13', 'ISBN_10'])) {
                        $data['isbn'] = $identifier['identifier'];
                        break;
                    }
                }
            }
        }

        return $data;
    }

    /**
     * Extract physical dimensions
     */
    private function extractPhysicalDimensions(array $volumeInfo): array
    {
        $data = [];

        if (isset($volumeInfo['dimensions'])) {
            $dimensions = $volumeInfo['dimensions'];
            if (isset($dimensions['height'])) {
                $data['height'] = $this->convertToMillimeters($dimensions['height']);
            }
            if (isset($dimensions['width'])) {
                $data['width'] = $this->convertToMillimeters($dimensions['width']);
            }
            if (isset($dimensions['thickness'])) {
                $data['thickness'] = $this->convertToMillimeters($dimensions['thickness']);
            }
        }

        return $data;
    }

    /**
     * Extract maturity rating and other metadata
     */
    private function extractMaturityAndRating(array $volumeInfo): array
    {
        $data = [];

        if (isset($volumeInfo['maturityRating'])) {
            $data['maturity_rating'] = $volumeInfo['maturityRating'];
        }

        return $data;
    }

    /**
     * Converts publication date to Carbon format
     */
    private function parsePublishedDate(string $dateString): ?Carbon
    {
        try {
            $result = null;

            if (preg_match('/^\d{4}$/', $dateString)) {
                // Year only - start of year but mark as year-only precision
                $result = Carbon::createFromFormat('Y', $dateString)->startOfYear();
            } elseif (preg_match('/^\d{4}-\d{2}$/', $dateString)) {
                // Year and month - start of month
                $result = Carbon::createFromFormat('Y-m', $dateString)->startOfMonth();
            } else {
                // Full date or other format
                $result = Carbon::parse($dateString);
            }

            return $result;
        } catch (\Exception $e) {
            Log::warning('Error parsing publication date', [
                'date_string' => $dateString,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Determines book format based on available information
     */
    private function determineFormat(array $googleBookData): ?string
    {
        $saleInfo = $googleBookData['saleInfo'] ?? [];
        $accessInfo = $googleBookData['accessInfo'] ?? [];

        // If there's sale information, checks if it's an ebook
        if (isset($saleInfo['isEbook']) && $saleInfo['isEbook']) {
            return 'ebook';
        }

        // If there's epub/pdf information
        if (isset($accessInfo['epub']['isAvailable']) &&
            $accessInfo['epub']['isAvailable']) {
            return 'ebook';
        }

        // For now, assumes paperback for physical books
        // Can be improved with more specific logic
        return 'paperback';
    }

    /**
     * Converts dimensions to millimeters
     */
    private function convertToMillimeters(string $dimension): ?float
    {
        // Remove spaces and convert to lowercase
        $dimension = strtolower(trim($dimension));

        // Extract the number
        if (preg_match('/(\d+\.?\d*)\s*(cm|mm|in|inch)/', $dimension, $matches)) {
            $value = (float) $matches[1];
            $unit = $matches[2];

            switch ($unit) {
                case 'cm':
                    return $value * 10; // cm to mm
                case 'mm':
                    return $value;
                case 'in':
                case 'inch':
                    return $value * 25.4; // inches to mm
                default:
                    return $value; // assumes mm if not specified
            }
        }

        return null;
    }

    /**
     * Determines information quality based on filled fields
     */
    private function determineInfoQuality(array $data, $height = null, $width = null, $thickness = null): string
    {
        $enhancedFields = ['description', 'published_date', 'page_count', 'publisher'];
        $completeFields = ['format', 'categories', 'google_id'];

        $enhancedCount = 0;
        $completeCount = 0;

        foreach ($enhancedFields as $field) {
            if (isset($data[$field]) && $data[$field] !== null) {
                $enhancedCount++;
            }
        }

        foreach ($completeFields as $field) {
            if (isset($data[$field]) && $data[$field] !== null) {
                $completeCount++;
            }
        }

        // Check for dimensions separately
        if (! empty($height) && ! empty($width) && ! empty($thickness)) {
            $completeCount++;
        }

        if ($completeCount >= 3) {
            return 'complete';
        } elseif ($enhancedCount >= 2) {
            return 'enhanced';
        } else {
            return 'basic';
        }
    }

    /**
     * Creates a new enriched book directly from Google Books
     */
    public function createEnrichedBookFromGoogle(string $googleId, ?string $userId = null, bool $isPrivate = false, string $readingStatus = 'read'): array
    {
        try {
            $googleBookData = $this->fetchBookFromGoogle($googleId);

            if (! $googleBookData) {
                return [
                    'success' => false,
                    'message' => 'Book not found in Google Books API',
                ];
            }

            $volumeInfo = $googleBookData['volumeInfo'] ?? [];
            $enrichedData = $this->extractEnrichedData($googleBookData, null);

            // Adds mandatory basic fields
            $bookData = array_merge([
                'title' => $volumeInfo['title'] ?? 'Untitled',
                'authors' => isset($volumeInfo['authors']) ? implode(', ', $volumeInfo['authors']) : null,
                'language' => $volumeInfo['language'] ?? 'pt-BR',
                'thumbnail' => isset($volumeInfo['imageLinks']['thumbnail'])
                    ? str_replace('http:', 'https:', $volumeInfo['imageLinks']['thumbnail'])
                    : null,
            ], $enrichedData);

            $book = Book::create($bookData);

            // If a user was specified, adds the book to their library
            if ($userId) {
                $attachData = [
                    'added_at' => now(),
                    'is_private' => $isPrivate,
                    'reading_status' => $readingStatus,
                ];

                // Auto-set read_at when status is 'read'
                if ($readingStatus === 'read') {
                    $attachData['read_at'] = now()->format('Y-m-d');
                }

                $book->users()->attach($userId, $attachData);
            }

            return [
                'success' => true,
                'message' => 'Book created and enriched successfully',
                'book' => $book,
                'info_quality' => $book->info_quality,
            ];

        } catch (\Exception $e) {
            Log::error('Error creating enriched book', [
                'google_id' => $googleId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Internal error: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Enforce rate limiting to respect Google Books API limits
     */
    private function enforceRateLimit(): void
    {
        static $lastRequestTime = 0;
        $minInterval = 200000; // 200ms in microseconds (5 requests per second)

        $currentTime = microtime(true) * 1000000; // Convert to microseconds
        $timeSinceLastRequest = $currentTime - $lastRequestTime;

        if ($timeSinceLastRequest < $minInterval) {
            $sleepTime = $minInterval - $timeSinceLastRequest;
            usleep((int) $sleepTime);
        }

        $lastRequestTime = microtime(true) * 1000000;
    }
}
