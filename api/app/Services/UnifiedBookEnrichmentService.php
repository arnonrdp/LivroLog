<?php

namespace App\Services;

use App\Jobs\EnrichBookWithAmazonJob;
use App\Models\Book;
use Illuminate\Support\Facades\Log;

class UnifiedBookEnrichmentService
{
    private BookEnrichmentService $googleEnrichmentService;

    private AmazonEnrichmentService $amazonEnrichmentService;

    public function __construct(
        BookEnrichmentService $googleEnrichmentService,
        AmazonEnrichmentService $amazonEnrichmentService
    ) {
        $this->googleEnrichmentService = $googleEnrichmentService;
        $this->amazonEnrichmentService = $amazonEnrichmentService;
    }

    /**
     * Enriches a book using multiple sources (Amazon + Google Books)
     * Strategy: Amazon first (synchronous), then Google Books to complement
     * Amazon: Synchronous (searches for ASIN and product data)
     * Google Books: Synchronous (only fills missing fields)
     */
    public function enrichBook(Book $book, ?string $googleId = null): array
    {
        $result = [
            'amazon_success' => false,
            'google_success' => false,
            'message' => '',
            'book_id' => $book->id,
        ];

        try {
            $amazonFilledFields = [];

            // 1. Amazon Enrichment (Synchronous)
            if ($this->shouldEnrichWithAmazon($book)) {
                Log::info("Starting Amazon enrichment for book: {$book->title} (ID: {$book->id})");

                $amazonResult = $this->amazonEnrichmentService->enrichBookWithAmazon($book);
                $result['amazon_result'] = $amazonResult;
                $result['amazon_success'] = $amazonResult['success'] ?? false;
                $amazonFilledFields = $amazonResult['fields_filled'] ?? [];

                if ($result['amazon_success']) {
                    Log::info("Amazon enrichment successful for book {$book->id}", [
                        'fields_filled' => $amazonFilledFields,
                    ]);
                    $book->refresh(); // Reload with updated data
                } else {
                    Log::warning("Amazon enrichment failed for book {$book->id}: ".($amazonResult['message'] ?? 'Unknown error'));
                }
            } else {
                Log::info("Skipping Amazon enrichment for book {$book->id} (already has ASIN or not enabled)");
                $result['amazon_success'] = true; // Consider as success if no enrichment needed
            }

            // 2. Check if book is fully enriched
            $missingFields = $this->getMissingFields($book);
            $isFullyEnriched = empty($missingFields);

            Log::info("Book {$book->id} enrichment status", [
                'is_fully_enriched' => $isFullyEnriched,
                'missing_fields' => $missingFields,
                'amazon_filled_fields' => $amazonFilledFields,
            ]);

            // 3. Google Books Enrichment (Synchronous, only if not fully enriched)
            if (! $isFullyEnriched && $this->shouldEnrichWithGoogle($book)) {
                Log::info("Starting Google Books enrichment for book: {$book->title} (ID: {$book->id}) to fill missing fields");

                // Pass Amazon-filled fields as skip fields to avoid overwriting
                $skipFields = $amazonFilledFields;

                $googleResult = $this->googleEnrichmentService->enrichBook($book, $googleId, $skipFields);
                $result['google_result'] = $googleResult;
                $result['google_success'] = $googleResult['success'] ?? false;

                if ($result['google_success']) {
                    Log::info("Google Books enrichment successful for book {$book->id}");
                    $book->refresh(); // Reload with updated data
                } else {
                    Log::warning("Google Books enrichment failed for book {$book->id}: ".($googleResult['message'] ?? 'Unknown error'));
                }
            } else {
                if ($isFullyEnriched) {
                    Log::info("Skipping Google Books enrichment for book {$book->id} (already fully enriched)");
                } else {
                    Log::info("Skipping Google Books enrichment for book {$book->id} (no Google ID or already enriched)");
                }
                $result['google_success'] = true; // Consider as success if no enrichment needed
            }

            // 4. Build success message
            $enrichmentActions = [];
            if ($result['amazon_success'] && ! empty($amazonFilledFields)) {
                $enrichmentActions[] = 'Amazon ('.count($amazonFilledFields).' fields)';
            }
            if ($result['google_success'] && ! $isFullyEnriched) {
                $enrichmentActions[] = 'Google Books (complementary)';
            }

            if (! empty($enrichmentActions)) {
                $result['message'] = 'Book enriched with: '.implode(' + ', $enrichmentActions);
            } else {
                $result['message'] = 'Book already fully enriched';
            }

            return $result;

        } catch (\Exception $e) {
            Log::error('Error in unified book enrichment', [
                'book_id' => $book->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'amazon_success' => false,
                'google_success' => false,
                'message' => 'Enrichment failed: '.$e->getMessage(),
                'book_id' => $book->id,
            ];
        }
    }

    /**
     * Creates a new enriched book directly from Google Books with automatic Amazon processing
     */
    public function createEnrichedBookFromGoogle(string $googleId, ?string $userId = null, bool $isPrivate = false, string $readingStatus = 'read'): array
    {
        try {
            // Create book with Google Books data
            $result = $this->googleEnrichmentService->createEnrichedBookFromGoogle($googleId, $userId, $isPrivate, $readingStatus);

            if ($result['success'] && isset($result['book'])) {
                $book = $result['book'];

                // Dispatch Amazon enrichment for the new book
                if ($this->shouldEnrichWithAmazon($book)) {
                    Log::info("Dispatching Amazon enrichment job for newly created book: {$book->title} (ID: {$book->id})");

                    $book->update(['asin_status' => 'processing']);
                    EnrichBookWithAmazonJob::dispatch($book);

                    $result['amazon_dispatched'] = true;
                    $result['message'] .= ' + Amazon enrichment queued';
                } else {
                    $result['amazon_dispatched'] = false;
                }
            }

            return $result;

        } catch (\Exception $e) {
            Log::error('Error creating enriched book from Google', [
                'google_id' => $googleId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to create enriched book: '.$e->getMessage(),
                'amazon_dispatched' => false,
            ];
        }
    }

    /**
     * Creates a new enriched book from ISBN using Google Books
     */
    public function createEnrichedBookFromIsbn(string $isbn, ?string $userId = null, bool $isPrivate = false, string $readingStatus = 'read'): array
    {
        try {
            // First, check if book already exists with this ISBN
            $existingBook = Book::where('isbn', $isbn)->first();
            if ($existingBook) {
                return [
                    'success' => true,
                    'book' => $existingBook,
                    'message' => 'Book already exists',
                    'created' => false,
                ];
            }

            // Try to find the book in Google Books by ISBN
            $googleBookData = $this->googleEnrichmentService->searchBookByIsbn($isbn);

            if (! $googleBookData) {
                return [
                    'success' => false,
                    'message' => 'Book not found in Google Books with ISBN: '.$isbn,
                ];
            }

            // Extract Google ID and create enriched book
            $googleId = $googleBookData['id'] ?? null;
            if (! $googleId) {
                return [
                    'success' => false,
                    'message' => 'No Google ID found for ISBN: '.$isbn,
                ];
            }

            return $this->createEnrichedBookFromGoogle($googleId, $userId, $isPrivate, $readingStatus);

        } catch (\Exception $e) {
            Log::error('Error creating enriched book from ISBN', [
                'isbn' => $isbn,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to create enriched book from ISBN: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Enriches multiple books in batch (maintains existing functionality)
     */
    public function enrichBooksInBatch(?array $bookIds = null): array
    {
        // For batch operations, we'll primarily use Google Books enrichment
        // and let the BookCreated event handle Amazon enrichment
        $googleResult = $this->googleEnrichmentService->enrichBooksInBatch($bookIds);

        // Count books that will get Amazon enrichment
        $amazonCandidates = 0;
        if ($bookIds) {
            $books = Book::whereIn('id', $bookIds)->get();
            foreach ($books as $book) {
                if ($this->shouldEnrichWithAmazon($book)) {
                    $amazonCandidates++;
                }
            }
        }

        $googleResult['amazon_candidates'] = $amazonCandidates;
        $googleResult['note'] = 'Amazon enrichment will be processed automatically via jobs for eligible books';

        return $googleResult;
    }

    /**
     * Determines if a book should be enriched with Google Books
     */
    private function shouldEnrichWithGoogle(Book $book): bool
    {
        // Cannot enrich from Google Books without google_id or ISBN
        if (empty($book->google_id) && empty($book->isbn)) {
            return false;
        }

        // Use the same logic as BookEnrichmentService
        return $book->enriched_at === null ||
               $book->info_quality === 'basic' ||
               ($book->page_count === null && $book->google_id !== null);
    }

    /**
     * Determines if a book should be enriched with Amazon
     */
    private function shouldEnrichWithAmazon(Book $book): bool
    {
        // Only enrich if:
        // 1. Amazon integration is enabled
        // 2. Book doesn't have ASIN yet
        // 3. Book is in pending status (or status is null - for backwards compatibility)
        // 4. Book has ISBN or title for searching

        if (! config('services.amazon.sitestripe_enabled', false)) {
            return false;
        }

        if ($book->amazon_asin) {
            return false; // Already has ASIN
        }

        $status = $book->asin_status ?? 'pending';
        if (! in_array($status, ['pending', null])) {
            return false; // Already processing, completed, or failed
        }

        // Must have either ISBN or title to search
        if (empty($book->isbn) && empty($book->title)) {
            return false;
        }

        return true;
    }

    /**
     * Check which important fields are missing from a book
     * Returns array of missing field names
     */
    private function getMissingFields(Book $book): array
    {
        $importantFields = [
            'description',
            'page_count',
            'publisher',
            'published_date',
            'authors',
            'thumbnail',
            'categories',
        ];

        $missingFields = [];

        foreach ($importantFields as $field) {
            $value = $book->$field;

            // Check if field is empty
            if (empty($value)) {
                $missingFields[] = $field;
            }
        }

        return $missingFields;
    }

}
