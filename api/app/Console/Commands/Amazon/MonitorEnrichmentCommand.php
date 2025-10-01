<?php

namespace App\Console\Commands\Amazon;

use App\Models\Book;
use Illuminate\Console\Command;

class MonitorEnrichmentCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'amazon:monitor {--refresh=5 : Refresh interval in seconds}';

    /**
     * The console description of the command.
     */
    protected $description = 'Monitor Amazon enrichment progress in real-time';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $refreshInterval = (int) $this->option('refresh');

        $this->info("🔍 Monitoring Amazon enrichment progress (updates every {$refreshInterval}s)");
        $this->info("Press Ctrl+C to exit\n");

        while (true) {
            $this->displayStats();
            sleep($refreshInterval);

            // Clear screen (works on most terminals)
            if ($refreshInterval > 1) {
                system('clear');
                $this->info("🔍 Monitoring Amazon enrichment progress (updates every {$refreshInterval}s)");
                $this->info("Press Ctrl+C to exit\n");
            }
        }

        return Command::SUCCESS;
    }

    private function displayStats(): void
    {
        $stats = [
            'total' => Book::count(),
            'with_isbn' => Book::whereNotNull('isbn')->count(),
            'with_asin' => Book::whereNotNull('amazon_asin')->count(),
            'pending' => Book::where('asin_status', 'pending')->count(),
            'processing' => Book::where('asin_status', 'processing')->count(),
            'completed' => Book::where('asin_status', 'completed')->count(),
            'failed' => Book::where('asin_status', 'failed')->count(),
        ];

        $successRate = $stats['completed'] > 0 ?
            round(($stats['with_asin'] / $stats['completed']) * 100, 1) : 0;

        $completionRate = $stats['with_isbn'] > 0 ?
            round(($stats['completed'] / $stats['with_isbn']) * 100, 1) : 0;

        // Statistics table
        $this->table(
            ['Metric', 'Value', 'Percentage'],
            [
                ['Total books', $stats['total'], '100%'],
                ['With ISBN', $stats['with_isbn'], round(($stats['with_isbn'] / $stats['total']) * 100, 1).'%'],
                ['With ASIN', $stats['with_asin'], round(($stats['with_asin'] / $stats['total']) * 100, 1).'%'],
                ['', '', ''],
                ['✅ Completed', $stats['completed'], $completionRate.'%'],
                ['⏳ Pending', $stats['pending'], ''],
                ['🔄 Processing', $stats['processing'], ''],
                ['❌ Failed', $stats['failed'], ''],
            ]
        );

        // Visual progress bar
        $progressWidth = 50;
        $progress = $stats['with_isbn'] > 0 ? ($stats['completed'] / $stats['with_isbn']) : 0;
        $filledWidth = (int) ($progress * $progressWidth);
        $emptyWidth = $progressWidth - $filledWidth;

        $progressBar = str_repeat('█', $filledWidth).str_repeat('░', $emptyWidth);

        $this->info("📊 Progress: [{$progressBar}] {$completionRate}%");
        $this->info("🎯 Success rate: {$successRate}%");

        if ($stats['pending'] > 0 || $stats['processing'] > 0) {
            $this->warn('⚡ There are pending or processing jobs. Run the queue worker:');
            $this->warn('   docker exec livrolog-api php artisan queue:work');
        }

        $this->info("\n⏰ Last update: ".now()->format('H:i:s'));
    }
}
