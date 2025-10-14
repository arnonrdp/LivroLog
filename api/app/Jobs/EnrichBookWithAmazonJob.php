<?php

namespace App\Jobs;

use App\Models\Book;
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
        $service = app(\App\Services\AmazonEnrichmentService::class);
        $service->enrichBookWithAmazon($this->book);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("Amazon ASIN enrichment failed permanently for book {$this->book->id}: {$exception->getMessage()}");
    }
}
