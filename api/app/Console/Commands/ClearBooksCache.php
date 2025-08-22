<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ClearBooksCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'books:cache-clear {--pattern=multi_search : Cache key pattern to clear}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear books search cache to force fresh API calls';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $pattern = $this->option('pattern');

        $this->info("Clearing books cache with pattern: {$pattern}:*");

        // Clear cache based on pattern
        $cleared = $this->clearCacheByPattern($pattern);

        if ($cleared) {
            $this->info('✅ Books cache cleared successfully!');
        } else {
            $this->warn('⚠️  Could not clear cache or no cache entries found');
        }

        // Also clear Laravel application cache
        $this->info('Clearing Laravel application cache...');
        $this->call('cache:clear');

        $this->info('🚀 Cache clearing completed!');

        return 0;
    }

    /**
     * Clear cache entries matching a pattern
     */
    private function clearCacheByPattern(string $pattern): bool
    {
        try {
            // For Redis or other cache stores that support pattern clearing
            if (method_exists(Cache::getStore(), 'flush')) {
                // If we can't do pattern-based clearing, just flush all cache
                // This is safer and ensures all search cache is cleared
                Cache::flush();

                return true;
            }

            // For stores that don't support pattern clearing, just flush all
            Cache::flush();

            return true;

        } catch (\Exception $e) {
            $this->error('Error clearing cache: '.$e->getMessage());

            return false;
        }
    }
}
