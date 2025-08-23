<?php

namespace App\Listeners;

use App\Events\BookCreated;
use App\Jobs\EnrichBookWithAmazonJob;
use Illuminate\Support\Facades\Log;

class EnrichBookWithAmazon
{
    /**
     * Handle the event.
     */
    public function handle(BookCreated $event): void
    {
        // Only enrich if Amazon integration is enabled
        if (!config('services.amazon.sitestripe_enabled', false)) {
            Log::info("Amazon integration disabled, skipping ASIN enrichment for book {$event->book->id}");
            return;
        }

        // Skip if book already has ASIN or is not in pending status
        if ($event->book->amazon_asin || $event->book->asin_status !== 'pending') {
            Log::info("Book {$event->book->id} already has ASIN or not in pending status (current: {$event->book->asin_status}), skipping enrichment");
            return;
        }

        // Update status to processing
        $event->book->update(['asin_status' => 'processing']);

        Log::info("Dispatching Amazon ASIN enrichment job for book: {$event->book->title} (ID: {$event->book->id})");

        // Dispatch the enrichment job to the queue
        EnrichBookWithAmazonJob::dispatch($event->book);
    }
}
