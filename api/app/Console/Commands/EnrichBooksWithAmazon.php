<?php

namespace App\Console\Commands;

use App\Models\Book;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class EnrichBooksWithAmazon extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'books:enrich-amazon
                           {--book-id=* : Specific book IDs to enrich}
                           {--max-books=20 : Maximum number of books to process}
                           {--dry-run : Show what would be processed without making changes}';

    /**
     * The console command description.
     */
    protected $description = 'Enrich books with Amazon ASIN codes for better affiliate links';

    /**
     * Rate limiting between requests (seconds)
     */
    private const RATE_LIMIT_SECONDS = 4;

    /**
     * User agent to avoid being blocked
     */
    private const USER_AGENT = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🛒 Starting Amazon ASIN enrichment...');

        $books = $this->getBooksToProcess();
        $totalBooks = $books->count();

        if ($totalBooks === 0) {
            $this->info('✅ No books found without Amazon ASIN.');

            return Command::SUCCESS;
        }

        $this->info("📚 Found {$totalBooks} books without Amazon ASIN");
        $this->showCurrentStats();

        if ($this->option('dry-run')) {
            return $this->handleDryRun($books);
        }

        if (! $this->confirm("Do you want to enrich {$totalBooks} books with Amazon ASINs?", true)) {
            $this->info('❌ Operation cancelled.');

            return Command::SUCCESS;
        }

        return $this->processBooks($books);
    }

    /**
     * Get books to process based on options
     */
    private function getBooksToProcess()
    {
        $query = Book::query();

        if ($this->option('book-id')) {
            $bookIds = $this->option('book-id');
            $query->whereIn('id', $bookIds);
            $this->info('📚 Processing specific books: '.implode(', ', $bookIds));
        } else {
            // Only books without amazon_asin
            $query->whereNull('amazon_asin');
            $this->info('🔍 Processing books without Amazon ASIN...');
        }

        if (! $this->option('book-id')) {
            $query->orderBy('created_at', 'desc')
                ->limit($this->option('max-books'));
        }

        return $query->get();
    }

    /**
     * Handle dry run mode
     */
    private function handleDryRun($books): int
    {
        $this->warn('🔍 DRY-RUN MODE - No changes will be made');

        $this->table(
            ['ID', 'Title', 'ISBN', 'Authors'],
            $books->map(function ($book) {
                return [
                    $book->id,
                    substr($book->title, 0, 40).(strlen($book->title) > 40 ? '...' : ''),
                    $book->isbn ?: 'N/A',
                    substr($book->authors ?: 'Unknown', 0, 30),
                ];
            })->toArray()
        );

        return Command::SUCCESS;
    }

    /**
     * Process books to find Amazon ASINs
     */
    private function processBooks($books): int
    {
        $totalBooks = $books->count();
        $processed = 0;
        $successful = 0;
        $errors = 0;

        $progressBar = $this->output->createProgressBar($totalBooks);
        $progressBar->start();

        foreach ($books as $book) {
            try {
                $this->line("\n🔍 Processing: {$book->title}");

                $asin = $this->findAmazonAsin($book);

                if ($asin) {
                    $book->update(['amazon_asin' => $asin]);
                    $successful++;
                    $this->line("✅ Found ASIN: {$asin}");
                } else {
                    $errors++;
                    $this->line('❌ ASIN not found');
                }

                $processed++;
                $progressBar->advance();

                // Rate limiting
                if ($processed < $totalBooks) {
                    $this->line('⏸️  Waiting '.self::RATE_LIMIT_SECONDS.' seconds...');
                    sleep(self::RATE_LIMIT_SECONDS);
                }

            } catch (\Exception $e) {
                $errors++;
                $processed++;
                $progressBar->advance();
                $this->line("💥 Error processing {$book->title}: ".$e->getMessage());
            }
        }

        $progressBar->finish();
        $this->showResults($processed, $successful, $errors);

        return Command::SUCCESS;
    }

    /**
     * Find Amazon ASIN for a book using multiple strategies
     */
    private function findAmazonAsin(Book $book): ?string
    {
        $searchStrategies = $this->buildSearchStrategies($book);

        foreach ($searchStrategies as $strategy) {
            $this->line("   {$strategy['icon']} Trying {$strategy['name']}: ".substr($strategy['term'], 0, 50).'...');

            $asin = $this->searchAmazonByTerm($strategy['term'], $book);
            if ($asin) {
                return $asin;
            }

            // Small delay between different strategies
            usleep(500000); // 0.5 seconds
        }

        return null;
    }

    /**
     * Build search strategies for a book
     */
    private function buildSearchStrategies(Book $book): array
    {
        $strategies = [];

        // Strategy 1: ISBN (most accurate)
        if ($book->isbn) {
            $strategies[] = [
                'name' => 'ISBN',
                'term' => $book->isbn,
                'icon' => '📖',
            ];
        }

        // Strategy 2: Title + First Author (very reliable)
        if ($book->title && $book->authors) {
            $firstAuthor = $this->extractFirstAuthor($book->authors);
            $strategies[] = [
                'name' => 'title+first_author',
                'term' => trim($book->title.' '.$firstAuthor),
                'icon' => '👤',
            ];
        }

        // Strategy 3: Clean title + author (remove subtitles, special chars)
        if ($book->title && $book->authors) {
            $cleanTitle = $this->cleanTitle($book->title);
            $firstAuthor = $this->extractFirstAuthor($book->authors);
            if ($cleanTitle !== $book->title) { // Only if different from original
                $strategies[] = [
                    'name' => 'clean_title+author',
                    'term' => trim($cleanTitle.' '.$firstAuthor),
                    'icon' => '🧹',
                ];
            }
        }

        // Strategy 4: Title + all authors
        if ($book->title && $book->authors) {
            $strategies[] = [
                'name' => 'title+all_authors',
                'term' => trim($book->title.' '.$book->authors),
                'icon' => '🔤',
            ];
        }

        // Strategy 5: Clean title only
        if ($book->title) {
            $cleanTitle = $this->cleanTitle($book->title);
            $strategies[] = [
                'name' => 'clean_title_only',
                'term' => $cleanTitle,
                'icon' => '📚',
            ];
        }

        // Strategy 6: Title without series/volume info
        if ($book->title) {
            $titleWithoutSeries = $this->removeSeries($book->title);
            if ($titleWithoutSeries !== $book->title) { // Only if different
                $strategies[] = [
                    'name' => 'title_no_series',
                    'term' => $titleWithoutSeries,
                    'icon' => '📖',
                ];
            }
        }

        // Strategy 7: First author only (last resort)
        if ($book->authors) {
            $firstAuthor = $this->extractFirstAuthor($book->authors);
            if (strlen($firstAuthor) > 3) { // Avoid too short author names
                $strategies[] = [
                    'name' => 'author_only',
                    'term' => $firstAuthor,
                    'icon' => '✍️',
                ];
            }
        }

        return $strategies;
    }

    /**
     * Search Amazon for ASIN by search term
     */
    private function searchAmazonByTerm(string $term, Book $book): ?string
    {
        try {
            $region = $this->getAmazonRegion($book);
            $regionConfig = config('services.amazon.regions.'.$region);

            $this->line("      🌍 Using Amazon {$region} for language: ".($book->language ?: 'unknown'));

            $url = $regionConfig['search_url'].'?'.http_build_query([
                'k' => $term,
                'i' => 'stripbooks',
            ]);

            $response = Http::timeout(10)
                ->withHeaders([
                    'User-Agent' => self::USER_AGENT,
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language' => $this->getAcceptLanguageHeader($regionConfig['language']),
                    'Accept-Encoding' => 'gzip, deflate',
                    'Connection' => 'keep-alive',
                ])
                ->get($url);

            if (! $response->successful()) {
                return null;
            }

            $html = $response->body();

            // Look for ASIN in the HTML
            // Pattern: data-asin="B01LPMFDQC" or similar
            if (preg_match('/data-asin="([A-Z0-9]{10})"/', $html, $matches)) {
                return $matches[1];
            }

            // Alternative pattern: /dp/ASIN/
            if (preg_match('/\/dp\/([A-Z0-9]{10})\//', $html, $matches)) {
                return $matches[1];
            }

            return null;

        } catch (\Exception $e) {
            $this->line('   ⚠️  Search error: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Show final results
     */
    private function showResults(int $processed, int $successful, int $errors): void
    {
        $this->newLine(2);
        $this->info('🎉 Amazon enrichment completed!');
        $this->info("📊 Processed: {$processed}");
        $this->info("✅ Found ASINs: {$successful}");
        $this->info("❌ Not found: {$errors}");

        if ($successful > 0) {
            $successRate = round(($successful / $processed) * 100, 1);
            $this->info("📈 Success rate: {$successRate}%");
        }

        $this->newLine();
        $this->info('📈 Updated statistics:');
        $this->showCurrentStats();
    }

    /**
     * Show current Amazon ASIN statistics
     */
    private function showCurrentStats(): void
    {
        $stats = [
            'with_asin' => Book::whereNotNull('amazon_asin')->count(),
            'without_asin' => Book::whereNull('amazon_asin')->count(),
            'total' => Book::count(),
        ];

        $this->table(
            ['Status', 'Quantity', 'Percentage'],
            [
                ['With ASIN', $stats['with_asin'], $this->percentage($stats['with_asin'], $stats['total'])],
                ['Without ASIN', $stats['without_asin'], $this->percentage($stats['without_asin'], $stats['total'])],
                ['---', '---', '---'],
                ['Total', $stats['total'], '100%'],
            ]
        );
    }

    /**
     * Calculate percentage
     */
    private function percentage(int $part, int $total): string
    {
        if ($total === 0) {
            return '0%';
        }

        return round(($part / $total) * 100, 1).'%';
    }

    /**
     * Extract first author from authors string
     */
    private function extractFirstAuthor(string $authors): string
    {
        // Split by common delimiters and get first author
        $authorList = preg_split('/[,;&|]/', $authors);

        return trim($authorList[0] ?? '');
    }

    /**
     * Clean title by removing subtitles and special characters
     */
    private function cleanTitle(string $title): string
    {
        // Remove subtitles (after : or -)
        $cleaned = preg_replace('/[:\-–—].+$/', '', $title);

        // Remove series info in parentheses or brackets
        $cleaned = preg_replace('/[\(\[].*?[\)\]]/', '', $cleaned);

        // Remove special characters but keep spaces and accents
        $cleaned = preg_replace('/[^\w\s\p{L}]/u', ' ', $cleaned);

        // Clean up multiple spaces
        $cleaned = preg_replace('/\s+/', ' ', trim($cleaned));

        return $cleaned;
    }

    /**
     * Remove series/volume information from title
     */
    private function removeSeries(string $title): string
    {
        // Patterns to match series/volume info
        $patterns = [
            '/\s*\(.*?(volume|vol|book|livro|série|series).*?\)/i',
            '/\s*\-\s*(volume|vol|book|livro|série|series).*$/i',
            '/\s*:\s*(volume|vol|book|livro|série|series).*$/i',
            '/\s*#\d+.*$/i', // Remove #1, #2, etc.
            '/\s*\d+º?\s*(volume|vol|livro).*$/i', // Remove "1º volume", etc.
        ];

        $cleaned = $title;
        foreach ($patterns as $pattern) {
            $cleaned = preg_replace($pattern, '', $cleaned);
        }

        return trim($cleaned);
    }

    /**
     * Get Amazon region based on book language
     */
    private function getAmazonRegion(Book $book): string
    {
        return $this->getAmazonRegionFromLanguage($book->language);
    }

    /**
     * Map book language to Amazon region
     */
    private function getAmazonRegionFromLanguage(?string $language): string
    {
        // Language to region mapping
        $languageToRegion = [
            'pt' => 'BR',
            'pt-BR' => 'BR',
            'pt_BR' => 'BR',
            'en' => 'US',
            'en-US' => 'US',
            'en_US' => 'US',
            'en-GB' => 'UK',
            'en_GB' => 'UK',
            'en-CA' => 'CA',
            'en_CA' => 'CA',
            'de' => 'DE',
            'de-DE' => 'DE',
            'de_DE' => 'DE',
            'fr' => 'FR',
            'fr-FR' => 'FR',
            'fr_FR' => 'FR',
            'es' => 'US', // No Amazon Spain in our config yet, use US
            'it' => 'US', // No Amazon Italy in our config yet, use US
        ];

        if (! $language) {
            return 'US'; // Fixed fallback
        }

        $normalizedLang = strtolower(trim($language));

        // Direct match
        if (isset($languageToRegion[$normalizedLang])) {
            return $languageToRegion[$normalizedLang];
        }

        // Try prefix match (e.g., pt-BR -> pt)
        $langPrefix = explode('-', $normalizedLang)[0];
        $langPrefix = explode('_', $langPrefix)[0];

        if (isset($languageToRegion[$langPrefix])) {
            return $languageToRegion[$langPrefix];
        }

        // Fixed fallback
        return 'US';
    }

    /**
     * Get Accept-Language header for region
     */
    private function getAcceptLanguageHeader(string $language): string
    {
        $headers = [
            'pt-BR' => 'pt-BR,pt;q=0.8,en;q=0.5,en-US;q=0.3',
            'en-US' => 'en-US,en;q=0.8',
            'en-GB' => 'en-GB,en;q=0.8',
            'de-DE' => 'de-DE,de;q=0.8,en;q=0.5',
            'en-CA' => 'en-CA,en;q=0.8,fr;q=0.5',
            'fr-FR' => 'fr-FR,fr;q=0.8,en;q=0.5',
        ];

        return $headers[$language] ?? 'en-US,en;q=0.8';
    }
}
