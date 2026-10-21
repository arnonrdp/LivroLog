<?php

namespace App\Console\Commands;

use App\Models\Book;
use App\Services\AmazonEnrichmentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class BackfillAmazonAsins extends Command
{
    protected $signature = 'books:backfill-asins {--dry-run : Show what would change without saving} {--limit= : Process at most this many books}';

    protected $description = 'Fill missing Amazon ASINs from each book\'s ISBN (print ASIN = ISBN-10), falling back to the ISBNs Google Books lists';

    public function handle(AmazonEnrichmentService $enrichment): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $books = Book::whereNull('amazon_asin')->orderBy('created_at')
            ->when($this->option('limit'), fn ($query, $limit) => $query->limit((int) $limit))
            ->get();

        $counts = ['isbn' => 0, 'google' => 0, 'unresolved' => 0];

        foreach ($books as $book) {
            $isbn = $book->isbn;
            $asin = $enrichment->resolveAsinFromIsbn($isbn);
            $source = 'isbn';

            if (! $asin && $book->google_id) {
                foreach ($this->isbnsFromGoogle($book->google_id) as $googleIsbn) {
                    if ($asin = $enrichment->resolveAsinFromIsbn($googleIsbn)) {
                        $isbn = $googleIsbn;
                        $source = 'google';
                        break;
                    }
                }
            }

            if (! $asin) {
                $counts['unresolved']++;
                $this->line("  -  {$book->id}  {$book->title}  (isbn: ".($book->isbn ?: '—').')');
                if (! $dryRun) {
                    // Out of pending/processing so it isn't mistaken for queued work; the daily run retries it
                    $book->update(['asin_status' => 'failed', 'asin_processed_at' => now()]);
                }

                continue;
            }

            $counts[$source]++;
            $this->line("  ✓  {$book->id}  {$book->title}  → {$asin}".($source === 'google' ? '  (ISBN via Google Books)' : ''));

            if (! $dryRun) {
                $book->amazon_asin = $asin;
                $book->asin_status = 'completed';
                $book->asin_processed_at = now();
                // Keep the ISBN that produced the ASIN when the stored one is empty or not an ISBN at all
                if (! $book->isbn || ! ctype_digit(str_replace(['-', ' ', 'X', 'x'], '', $book->isbn))) {
                    $book->isbn = $isbn;
                }
                $book->save();
            }
        }

        $this->newLine();
        $this->info(($dryRun ? '[dry-run] ' : '')."Books without ASIN: {$books->count()}");
        $this->info("  resolved from ISBN: {$counts['isbn']}");
        $this->info("  resolved via Google Books: {$counts['google']}");
        $this->info("  unresolved: {$counts['unresolved']}");

        return Command::SUCCESS;
    }

    /**
     * ISBN-10/13 that Google Books lists for a volume, ISBN-10 first since it is the ASIN itself
     */
    private function isbnsFromGoogle(string $googleId): array
    {
        try {
            $response = Http::timeout(10)->get("https://www.googleapis.com/books/v1/volumes/{$googleId}", array_filter([
                'key' => config('services.google_books.api_key'),
            ]));
        } catch (\Throwable $e) {
            return [];
        }

        $identifiers = collect($response->json('volumeInfo.industryIdentifiers', []));

        return $identifiers->where('type', 'ISBN_10')->pluck('identifier')
            ->merge($identifiers->where('type', 'ISBN_13')->pluck('identifier'))
            ->all();
    }
}
