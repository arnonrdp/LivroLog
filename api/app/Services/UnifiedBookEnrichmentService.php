<?php

namespace App\Services;

use App\Jobs\EnrichBookWithAmazonJob;
use App\Models\Book;
use Illuminate\Support\Facades\Log;

class UnifiedBookEnrichmentService
{
    private BookEnrichmentService $googleEnrichmentService;

    public function __construct(BookEnrichmentService $googleEnrichmentService)
    {
        $this->googleEnrichmentService = $googleEnrichmentService;
    }

    /**
     * Enriches a book using multiple sources (Google Books + Amazon)
     * Google Books: Synchronous (immediate data)
     * Amazon: Asynchronous via job (background processing)
     */
    public function enrichBook(Book $book, ?string $googleId = null): array
    {
        $result = [
            'google_success' => false,
            'amazon_dispatched' => false,
            'message' => '',
            'book_id' => $book->id,
        ];

        try {
            // 1. Google Books Enrichment (Synchronous)
            if ($this->shouldEnrichWithGoogle($book)) {
                Log::info("Starting Google Books enrichment for book: {$book->title} (ID: {$book->id})");

                $googleResult = $this->googleEnrichmentService->enrichBook($book, $googleId);
                $result['google_result'] = $googleResult;
                $result['google_success'] = $googleResult['success'] ?? false;

                if ($result['google_success']) {
                    Log::info("Google Books enrichment successful for book {$book->id}");
                    $book->refresh(); // Reload with updated data
                } else {
                    Log::warning("Google Books enrichment failed for book {$book->id}: ".($googleResult['message'] ?? 'Unknown error'));
                }
            } else {
                Log::info("Skipping Google Books enrichment for book {$book->id} (already enriched or no Google ID)");
                $result['google_success'] = true; // Consider as success if no enrichment needed
            }

            // 2. Amazon Enrichment (Asynchronous)
            if ($this->shouldEnrichWithAmazon($book)) {
                Log::info("Dispatching Amazon enrichment job for book: {$book->title} (ID: {$book->id})");

                // Update status to processing before dispatching job
                $book->update(['asin_status' => 'processing']);

                EnrichBookWithAmazonJob::dispatch($book);
                $result['amazon_dispatched'] = true;
            } else {
                Log::info("Skipping Amazon enrichment for book {$book->id} (already has ASIN or not in pending status)");
            }

            // 3. Build success message
            $enrichmentActions = [];
            if ($result['google_success'] && $this->shouldEnrichWithGoogle($book)) {
                $enrichmentActions[] = 'Google Books';
            }
            if ($result['amazon_dispatched']) {
                $enrichmentActions[] = 'Amazon (processing)';
            }

            if (! empty($enrichmentActions)) {
                $result['message'] = 'Book enriched with: '.implode(' + ', $enrichmentActions);
            } else {
                $result['message'] = 'Book already enriched';
            }

            return $result;

        } catch (\Exception $e) {
            Log::error('Error in unified book enrichment', [
                'book_id' => $book->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'google_success' => false,
                'amazon_dispatched' => false,
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
     * Future method for Phase 2: Merge data from multiple sources intelligently
     * This will be implemented when Amazon PA-API is available
     */
    private function mergeBookData(array $googleData, array $amazonData): array
    {
        // TODO: Implement in Phase 2
        // Priority strategy:
        // - thumbnail: Amazon > Google (better quality)
        // - description: Google > Amazon (more complete)
        // - page_count: Google > Amazon (more reliable)
        // - dimensions: Amazon > Google (Amazon has physical measurements)
        // - asin: Amazon only

        return $googleData; // For now, just return Google data
    }
}
