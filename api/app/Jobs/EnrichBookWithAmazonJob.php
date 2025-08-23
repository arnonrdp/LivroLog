<?php

namespace App\Jobs;

use App\Models\Book;
use App\Services\AmazonLinkEnrichmentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class EnrichBookWithAmazonJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

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
        $this->delay(now()->addSeconds(rand(5, 30)));
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
                    'asin_processed_at' => now()
                ]);
                Log::info("Book {$this->book->id} already has ASIN, marking as completed");
                return;
            }

            Log::info("Starting Amazon ASIN enrichment for book: {$this->book->title} (ID: {$this->book->id})");

            $enrichmentService = app(AmazonLinkEnrichmentService::class);
            
            // Try to enrich the book with Amazon ASIN
            $enrichedBooks = $enrichmentService->enrichBooksWithAmazonLinks([$this->book]);
            $asin = $enrichedBooks[0]['amazon_asin'] ?? null;
            
            if ($asin) {
                $this->book->update([
                    'amazon_asin' => $asin,
                    'asin_status' => 'completed',
                    'asin_processed_at' => now()
                ]);
                Log::info("Successfully enriched book {$this->book->id} with ASIN: {$asin}");
            } else {
                $this->book->update([
                    'asin_status' => 'failed',
                    'asin_processed_at' => now()
                ]);
                Log::warning("Could not find ASIN for book {$this->book->id}: {$this->book->title}");
            }

        } catch (\Exception $e) {
            $this->book->update([
                'asin_status' => 'failed',
                'asin_processed_at' => now()
            ]);
            Log::error("Failed to enrich book {$this->book->id} with Amazon ASIN: {$e->getMessage()}");
            throw $e; // Re-throw to trigger retry mechanism
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("Amazon ASIN enrichment failed permanently for book {$this->book->id}: {$exception->getMessage()}");
    }
}
