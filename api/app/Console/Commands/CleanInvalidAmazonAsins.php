<?php

namespace App\Console\Commands;

use App\Models\Book;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CleanInvalidAmazonAsins extends Command
{
    protected $signature = 'books:clean-amazon-asins
                           {--book-id=* : Specific book IDs to clean}
                           {--threshold=0.5 : Minimum similarity threshold (0-1)}
                           {--dry-run : Show what would be cleaned without making changes}
                           {--verbose-output : Show detailed information for each book}';

    protected $description = 'Automatically validate and clean invalid Amazon ASINs';

    private const RATE_LIMIT_SECONDS = 3;

    private const USER_AGENT = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36';

    private array $stats = [
        'total' => 0,
        'validated' => 0,
        'cleaned' => 0,
        'kept' => 0,
        'errors' => 0,
        'cleaned_books' => [],
        'error_books' => [],
    ];

    public function handle(): int
    {
        $this->info('🧹 Starting Amazon ASIN validation and cleaning...');

        if ($this->option('dry-run')) {
            $this->warn('🔍 DRY-RUN MODE - No changes will be made');
        }

        $threshold = (float) $this->option('threshold');
        $this->info('📏 Using similarity threshold: '.($threshold * 100).'%');

        $books = $this->getBooksToClean();
        $this->stats['total'] = $books->count();

        if ($this->stats['total'] === 0) {
            $this->info('✅ No books found with Amazon ASINs to clean.');

            return Command::SUCCESS;
        }

        $this->info("📚 Found {$this->stats['total']} books with Amazon ASINs to validate");

        $this->processBooks($books, $threshold);
        $this->showFinalReport();

        return Command::SUCCESS;
    }

    private function getBooksToClean()
    {
        $query = Book::whereNotNull('amazon_asin');

        if ($this->option('book-id')) {
            $bookIds = $this->option('book-id');
            $query->whereIn('id', $bookIds);
            $this->info('📚 Processing specific books: '.implode(', ', $bookIds));
        }

        return $query->orderBy('updated_at', 'desc')->get();
    }

    private function processBooks($books, float $threshold): void
    {
        $progressBar = $this->output->createProgressBar($this->stats['total']);
        $progressBar->start();

        foreach ($books as $index => $book) {
            try {
                $validation = $this->validateBookAsin($book);
                $this->stats['validated']++;

                if ($validation['error']) {
                    // Handle errors (usually invalid ASINs)
                    $this->stats['errors']++;
                    $this->stats['error_books'][] = [
                        'book' => $book,
                        'error' => $validation['error'],
                    ];

                    if (! $this->option('dry-run')) {
                        $book->update(['amazon_asin' => null]);
                        $this->stats['cleaned']++;
                    }

                    if ($this->option('verbose-output')) {
                        $this->line("\n💥 {$book->title}: {$validation['error']} - ASIN removed");
                    }

                } elseif ($validation['accurate']) {
                    // Keep accurate ASINs
                    $this->stats['kept']++;

                    if ($this->option('verbose-output')) {
                        $this->line("\n✅ {$book->title}: Similarity {$validation['title_similarity']}% - ASIN kept");
                    }

                } else {
                    // Remove inaccurate ASINs
                    $this->stats['cleaned']++;
                    $this->stats['cleaned_books'][] = [
                        'book' => $book,
                        'amazon_title' => $validation['amazon_title'],
                        'similarity' => $validation['title_similarity'],
                    ];

                    if (! $this->option('dry-run')) {
                        $book->update(['amazon_asin' => null]);
                    }

                    if ($this->option('verbose-output')) {
                        $this->line("\n❌ {$book->title}: Similarity {$validation['title_similarity']}% - ASIN removed");
                        $this->line("   Amazon product: {$validation['amazon_title']}");
                    }
                }

                $progressBar->advance();

                // Rate limiting (skip on last item)
                if ($index < $this->stats['total'] - 1) {
                    sleep(self::RATE_LIMIT_SECONDS);
                }

            } catch (\Exception $e) {
                $this->stats['errors']++;
                $this->stats['error_books'][] = [
                    'book' => $book,
                    'error' => $e->getMessage(),
                ];

                if (! $this->option('dry-run')) {
                    $book->update(['amazon_asin' => null]);
                    $this->stats['cleaned']++;
                }

                $progressBar->advance();

                if ($this->option('verbose-output')) {
                    $this->line("\n💥 Error processing {$book->title}: ".$e->getMessage());
                }
            }
        }

        $progressBar->finish();
    }

    private function validateBookAsin(Book $book): array
    {
        try {
            $region = $this->getAmazonRegion($book);
            $regionConfig = config('services.amazon.regions.'.$region);

            $url = "https://www.{$regionConfig['domain']}/dp/{$book->amazon_asin}";

            // Log validation attempt
            Log::info('Validating ASIN', [
                'book_id' => $book->id,
                'title' => $book->title,
                'asin' => $book->amazon_asin,
                'url' => $url,
            ]);

            $response = Http::timeout(15)
                ->retry(2, 1000) // Retry twice with 1 second delay
                ->withHeaders([
                    'User-Agent' => self::USER_AGENT,
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language' => $this->getAcceptLanguageHeader($regionConfig['language']),
                    'Accept-Encoding' => 'gzip, deflate',
                    'Connection' => 'keep-alive',
                    'Cache-Control' => 'no-cache',
                ])
                ->get($url);

            if (! $response->successful()) {
                Log::warning('Amazon request failed', [
                    'book_id' => $book->id,
                    'status' => $response->status(),
                    'asin' => $book->amazon_asin,
                ]);

                return [
                    'error' => "HTTP {$response->status()}: Invalid ASIN",
                    'accurate' => false,
                ];
            }

            $html = $response->body();
            $amazonData = $this->extractAmazonBookData($html);

            // Check if this is actually a book
            if (! $amazonData['isBook']) {
                Log::info('Product is not a book', [
                    'book_id' => $book->id,
                    'asin' => $book->amazon_asin,
                    'extracted_title' => $amazonData['title'],
                ]);

                return [
                    'error' => 'Product is not a book',
                    'accurate' => false,
                ];
            }

            if (! $amazonData['title']) {
                // Try to at least validate by ASIN presence in page
                if (strpos($html, $book->amazon_asin) !== false) {
                    // ASIN is present in page, likely valid but can't extract title
                    // Be conservative and keep it with lower threshold
                    return [
                        'error' => null,
                        'accurate' => true, // Keep it since ASIN is valid
                        'amazon_title' => '[Could not extract title]',
                        'amazon_isbn' => $amazonData['isbn'],
                        'title_similarity' => 51, // Just above 50% threshold
                        'isbn_match' => false,
                        'note' => 'ASIN found in page but title extraction failed',
                    ];
                }

                return [
                    'error' => 'Could not extract title from Amazon page',
                    'accurate' => false,
                ];
            }

            $titleMatch = $this->compareTitles($book->title, $amazonData['title']);
            $isbnMatch = $this->compareIsbns($book->isbn, $amazonData['isbn']);

            $threshold = (float) $this->option('threshold');
            $accurate = $titleMatch['score'] >= $threshold || $isbnMatch;

            return [
                'error' => null,
                'accurate' => $accurate,
                'amazon_title' => $amazonData['title'],
                'amazon_isbn' => $amazonData['isbn'],
                'title_similarity' => round($titleMatch['score'] * 100, 1),
                'isbn_match' => $isbnMatch,
            ];

        } catch (\Exception $e) {
            return [
                'error' => $e->getMessage(),
                'accurate' => false,
            ];
        }
    }

    private function extractAmazonBookData(string $html): array
    {
        $title = null;
        $isbn = null;
        $isBook = false;

        // Check if this is actually a book product
        $bookIndicators = [
            '/Books<\/a>/i',
            '/node=283155/i', // Amazon Books category
            '/"bookFormat":/i',
            '/Paperback|Hardcover|Kindle Edition|Mass Market/i',
            '/"@type"\s*:\s*"Book"/i',
            '/id="book_details"/i',
            '/Publisher\s*:/i',
            '/Publication date/i',
        ];

        foreach ($bookIndicators as $indicator) {
            if (preg_match($indicator, $html)) {
                $isBook = true;
                break;
            }
        }

        // Extract title - expanded patterns for better coverage
        $titlePatterns = [
            '/<span[^>]*id="productTitle"[^>]*>([^<]+)<\/span>/i',
            '/<h1[^>]*id="title"[^>]*>.*?<span[^>]*>([^<]+)<\/span>/is',
            '/<h1[^>]*class="[^"]*a-size-large[^"]*"[^>]*>([^<]+)<\/h1>/i',
            '/<span[^>]*class="[^"]*a-size-extra-large[^"]*"[^>]*>([^<]+)<\/span>/i',
            '/data-feature-name="title"[^>]*>([^<]+)</i',
            '/"title"\s*:\s*"([^"]+)"/i',
            '/<title>([^<]+)<\/title>/i',
        ];

        foreach ($titlePatterns as $pattern) {
            if (preg_match($pattern, $html, $matches)) {
                $title = html_entity_decode(trim($matches[1]), ENT_QUOTES, 'UTF-8');
                // Clean up Amazon suffixes
                $title = preg_replace('/\s*:\s*Amazon\..*$/i', '', $title);
                $title = preg_replace('/\s*-\s*Amazon\.com.*$/i', '', $title);
                $title = trim($title);

                // Skip if title is too generic or looks like an error
                if (strlen($title) > 5 && ! preg_match('/^(Amazon|Error|Page not found)/i', $title)) {
                    break;
                }
                $title = null; // Reset if invalid
            }
        }

        // If no title found in patterns, try to extract from JSON-LD
        if (! $title && preg_match('/<script[^>]*type="application\/ld\+json"[^>]*>(.*?)<\/script>/is', $html, $jsonMatch)) {
            $jsonData = json_decode($jsonMatch[1], true);
            if (isset($jsonData['name'])) {
                $title = $jsonData['name'];
            }
        }

        // Extract ISBN - expanded patterns
        $isbnPatterns = [
            '/ISBN-13[:\s]*(\d{13})/i',
            '/ISBN-10[:\s]*(\d{10})/i',
            '/ISBN[:\s]*(\d{10,13})/i',
            '/"isbn":"([^"]+)"/i',
            '/isbn13["\s:]+(\d{13})/i',
            '/isbn10["\s:]+(\d{10})/i',
        ];

        foreach ($isbnPatterns as $pattern) {
            if (preg_match($pattern, $html, $matches)) {
                $isbn = preg_replace('/[^0-9X]/i', '', $matches[1]);
                if (strlen($isbn) == 10 || strlen($isbn) == 13) {
                    break;
                }
                $isbn = null; // Reset if invalid length
            }
        }

        return [
            'title' => $title,
            'isbn' => $isbn,
            'isBook' => $isBook,
        ];
    }

    private function compareTitles(string $ourTitle, string $amazonTitle): array
    {
        $our = $this->normalizeTitle($ourTitle);
        $amazon = $this->normalizeTitle($amazonTitle);

        // Calculate similarity
        $similarity = 0.0;
        similar_text($our, $amazon, $similarity);
        $score = $similarity / 100;

        // Check substring matching (if one contains the other)
        $subMatch = 0;
        if (strlen($our) > 3 && strlen($amazon) > 3) {
            if (Str::contains($amazon, $our) || Str::contains($our, $amazon)) {
                $subMatch = 0.8;
            }
        }

        // Check if main words match (for titles with subtitles)
        $ourWords = explode(' ', $our);
        $amazonWords = explode(' ', $amazon);
        $mainWords = array_slice($ourWords, 0, 3); // First 3 words
        $amazonMain = implode(' ', array_slice($amazonWords, 0, 3));
        $ourMain = implode(' ', $mainWords);

        $mainSimilarity = 0;
        if (strlen($ourMain) > 3 && strlen($amazonMain) > 3) {
            similar_text($ourMain, $amazonMain, $mainSimilarity);
            $mainSimilarity = $mainSimilarity / 100;
        }

        // Take the best score from different methods
        $finalScore = max($score, $subMatch, $mainSimilarity * 0.9);

        return [
            'score' => $finalScore,
            'our_normalized' => $our,
            'amazon_normalized' => $amazon,
        ];
    }

    private function compareIsbns(?string $ourIsbn, ?string $amazonIsbn): bool
    {
        if (! $ourIsbn || ! $amazonIsbn) {
            return false;
        }

        $our = preg_replace('/[^0-9X]/i', '', $ourIsbn);
        $amazon = preg_replace('/[^0-9X]/i', '', $amazonIsbn);

        return $our === $amazon;
    }

    private function normalizeTitle(string $title): string
    {
        $normalized = strtolower(trim($title));
        $normalized = preg_replace('/[^\w\s]/u', ' ', $normalized);
        $normalized = preg_replace('/\s+/', ' ', $normalized);

        return trim($normalized);
    }

    private function showFinalReport(): void
    {
        $this->newLine(2);

        if ($this->option('dry-run')) {
            $this->warn('🔍 DRY-RUN RESULTS - No changes were made');
        } else {
            $this->info('🎉 Amazon ASIN cleaning completed!');
        }

        $this->info("📊 Processed: {$this->stats['total']}");
        $this->info("✅ Valid ASINs kept: {$this->stats['kept']}");
        $this->info("🧹 Invalid ASINs cleaned: {$this->stats['cleaned']}");

        if ($this->stats['errors'] > 0) {
            $this->info("💥 Errors (ASINs removed): {$this->stats['errors']}");
        }

        // Show success rate
        if ($this->stats['total'] > 0) {
            $keepRate = round(($this->stats['kept'] / $this->stats['total']) * 100, 1);
            $cleanRate = round((($this->stats['cleaned'] + $this->stats['errors']) / $this->stats['total']) * 100, 1);
            $this->newLine();
            $this->info("📈 Keep rate: {$keepRate}%");
            $this->info("🧹 Clean rate: {$cleanRate}%");
        }

        // Show cleaned books
        if (count($this->stats['cleaned_books']) > 0) {
            $this->newLine();
            $this->warn('📚 Books with ASINs removed due to mismatch:');
            foreach ($this->stats['cleaned_books'] as $item) {
                $book = $item['book'];
                $this->line("   • {$book->title} (ASIN: {$book->amazon_asin})");
                $this->line("     Similarity: {$item['similarity']}%");
                if ($this->option('verbose-output')) {
                    $this->line("     Amazon: {$item['amazon_title']}");
                }
            }
        }

        // Show error books
        if (count($this->stats['error_books']) > 0) {
            $this->newLine();
            $this->warn('💥 Books with ASINs removed due to errors:');
            foreach ($this->stats['error_books'] as $item) {
                $book = $item['book'];
                $this->line("   • {$book->title} (ASIN: {$book->amazon_asin})");
                $this->line("     Error: {$item['error']}");
            }
        }

        // Summary
        $this->newLine();
        if (! $this->option('dry-run')) {
            $this->info('✨ Database has been cleaned!');

            // Show new statistics
            $withAsin = Book::whereNotNull('amazon_asin')->count();
            $total = Book::count();
            $percentage = $total > 0 ? round(($withAsin / $total) * 100, 1) : 0;

            $this->info("📚 Books with valid ASINs: {$withAsin} / {$total} ({$percentage}%)");
        } else {
            $this->info('💡 Run without --dry-run to actually clean the database');
        }
    }

    private function getAmazonRegion(Book $book): string
    {
        return $this->getAmazonRegionFromLanguage($book->language);
    }

    private function getAmazonRegionFromLanguage(?string $language): string
    {
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
            'es' => 'US',
            'it' => 'US',
        ];

        if (! $language) {
            return 'US';
        }

        $normalizedLang = strtolower(trim($language));

        if (isset($languageToRegion[$normalizedLang])) {
            return $languageToRegion[$normalizedLang];
        }

        $langPrefix = explode('-', $normalizedLang)[0];
        $langPrefix = explode('_', $langPrefix)[0];

        if (isset($languageToRegion[$langPrefix])) {
            return $languageToRegion[$langPrefix];
        }

        return 'US';
    }

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
