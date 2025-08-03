<?php

namespace App\Console\Commands;

use App\Models\Book;
use App\Services\BookEnrichmentService;
use Illuminate\Console\Command;

class EnrichBooksCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'books:enrich
                           {--batch-size=10 : Number of books to process in each batch}
                           {--only-basic : Only enrich books with basic info quality}
                           {--book-id=* : Specific book IDs to enrich}
                           {--max-books=100 : Maximum number of books to process}
                           {--dry-run : Show what would be processed without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Enrich books with additional information from Google Books API';

    /**
     * Execute the console command.
     */
    public function handle(BookEnrichmentService $enrichmentService)
    {
        $this->info('🔍 Starting book enrichment...');

        // Determines which books to process
        $query = Book::query();

        if ($this->option('book-id')) {
            $bookIds = $this->option('book-id');
            $query->whereIn('id', $bookIds);
            $this->info("📚 Processing specific books: " . implode(', ', $bookIds));
        } elseif ($this->option('only-basic')) {
            $query->where('info_quality', 'basic');
            $this->info("📊 Processing only books with basic info quality...");
        } else {
            $query->where(function($q) {
                $q->whereIn('info_quality', ['basic', 'enhanced'])
                  ->orWhereNull('enriched_at');
            });
            $this->info("📊 Processing books with basic/enhanced quality or never enriched...");
        }

        if (!$this->option('book-id')) {
            $query->orderBy('created_at', 'desc')
                  ->limit($this->option('max-books'));
        }

        $books = $query->get();
        $totalBooks = $books->count();

        if ($totalBooks === 0) {
            $this->info('✅ No books found for enrichment.');
            return Command::SUCCESS;
        }

        $this->info("📖 Found {$totalBooks} books to process");

        // Shows current statistics
        $this->showCurrentStats();

        if ($this->option('dry-run')) {
            $this->warn('🔍 DRY-RUN MODE - No changes will be made');
            $this->table(
                ['ID', 'Title', 'Current Quality', 'Last Update'],
                $books->map(function($book) {
                    return [
                        $book->id,
                        substr($book->title, 0, 40) . (strlen($book->title) > 40 ? '...' : ''),
                        $book->info_quality ?? 'basic',
                        $book->enriched_at ? $book->enriched_at->format('d/m/Y H:i') : 'Never'
                    ];
                })->toArray()
            );
            return Command::SUCCESS;
        }

        // Confirms if should proceed
        if (!$this->confirm("Do you want to enrich {$totalBooks} books?", true)) {
            $this->info('❌ Operation cancelled.');
            return Command::SUCCESS;
        }

        $batchSize = (int) $this->option('batch-size');
        $processed = 0;
        $successful = 0;
        $errors = 0;

        $progressBar = $this->output->createProgressBar($totalBooks);
        $progressBar->start();

        // Processes in batches
        foreach ($books->chunk($batchSize) as $batch) {
            foreach ($batch as $book) {
                try {
                    $result = $enrichmentService->enrichBook($book);

                    if ($result['success']) {
                        $successful++;
                        $this->line("\n✅ {$book->title}: " . $result['message']);
                        if (isset($result['added_fields']) && count($result['added_fields']) > 0) {
                            $this->line("   📝 Added fields: " . implode(', ', $result['added_fields']));
                        }
                    } else {
                        $errors++;
                        $this->line("\n❌ {$book->title}: " . $result['message']);
                    }

                } catch (\Exception $e) {
                    $errors++;
                    $this->line("\n💥 Error processing {$book->title}: " . $e->getMessage());
                }

                $processed++;
                $progressBar->advance();

                // Rate limiting is handled by the service
            }

            // Brief pause between batches for system stability
            if ($books->count() > $batchSize) {
                $this->line("\n⏸️  Brief pause between batches...");
                usleep(500000); // 500ms - reduced from 2 seconds
            }
        }

        $progressBar->finish();

        // Shows final results
        $this->newLine(2);
        $this->info('🎉 Enrichment completed!');
        $this->info("📊 Processed: {$processed}");
        $this->info("✅ Successful: {$successful}");
        $this->info("❌ Errors: {$errors}");

        // Shows final statistics
        $this->newLine();
        $this->info('📈 Updated statistics:');
        $this->showCurrentStats();

        return Command::SUCCESS;
    }

    /**
     * Shows current book statistics
     */
    private function showCurrentStats(): void
    {
        $stats = [
            'basic' => Book::where('info_quality', 'basic')->count(),
            'enhanced' => Book::where('info_quality', 'enhanced')->count(),
            'complete' => Book::where('info_quality', 'complete')->count(),
            'total' => Book::count(),
            'never_enriched' => Book::whereNull('enriched_at')->count(),
        ];

        $this->table(
            ['Quality', 'Quantity', 'Percentage'],
            [
                ['Basic', $stats['basic'], $this->percentage($stats['basic'], $stats['total'])],
                ['Enhanced', $stats['enhanced'], $this->percentage($stats['enhanced'], $stats['total'])],
                ['Complete', $stats['complete'], $this->percentage($stats['complete'], $stats['total'])],
                ['---', '---', '---'],
                ['Total', $stats['total'], '100%'],
                ['Never enriched', $stats['never_enriched'], $this->percentage($stats['never_enriched'], $stats['total'])],
            ]
        );
    }

    /**
     * Calculates percentage
     */
    private function percentage(int $part, int $total): string
    {
        if ($total === 0) return '0%';
        return round(($part / $total) * 100, 1) . '%';
    }
}
