<?php

namespace App\Console\Commands;

use App\Models\Book;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ValidateAmazonAsins extends Command
{
    protected $signature = 'books:validate-amazon-asins
                           {--book-id=* : Specific book IDs to validate}
                           {--max-books=10 : Maximum number of books to validate}
                           {--show-details : Show detailed comparison for each book}';

    protected $description = 'Validate that Amazon ASINs match book titles and ISBNs';

    private const RATE_LIMIT_SECONDS = 3;
    private const USER_AGENT = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36';

    public function handle(): int
    {
        $this->info('🔍 Starting Amazon ASIN validation...');

        $books = $this->getBooksToValidate();
        $totalBooks = $books->count();

        if ($totalBooks === 0) {
            $this->info('✅ No books found with Amazon ASINs to validate.');
            return Command::SUCCESS;
        }

        $this->info("📚 Found {$totalBooks} books with Amazon ASINs");

        return $this->validateBooks($books);
    }

    private function getBooksToValidate()
    {
        $query = Book::whereNotNull('amazon_asin');

        if ($this->option('book-id')) {
            $bookIds = $this->option('book-id');
            $query->whereIn('id', $bookIds);
            $this->info('📚 Validating specific books: ' . implode(', ', $bookIds));
        } else {
            $query->orderBy('updated_at', 'desc')
                ->limit($this->option('max-books'));
            $this->info('🔍 Validating most recently updated books with ASINs...');
        }

        return $query->get();
    }

    private function validateBooks($books): int
    {
        $totalBooks = $books->count();
        $processed = 0;
        $accurate = 0;
        $inaccurate = 0;
        $errors = 0;
        $results = [];

        $progressBar = $this->output->createProgressBar($totalBooks);
        $progressBar->start();

        foreach ($books as $book) {
            try {
                $this->line("\n🔍 Validating: {$book->title} (ASIN: {$book->amazon_asin})");
                
                $validation = $this->validateBookAsin($book);
                
                if ($validation['error']) {
                    $errors++;
                    $this->line("💥 Error: {$validation['error']}");
                } elseif ($validation['accurate']) {
                    $accurate++;
                    $this->line("✅ Match confirmed");
                } else {
                    $inaccurate++;
                    $this->line("❌ Mismatch detected");
                }

                $results[] = array_merge($validation, ['book' => $book]);
                $processed++;
                $progressBar->advance();

                if ($processed < $totalBooks) {
                    $this->line("⏸️  Waiting " . self::RATE_LIMIT_SECONDS . " seconds...");
                    sleep(self::RATE_LIMIT_SECONDS);
                }

            } catch (\Exception $e) {
                $errors++;
                $processed++;
                $progressBar->advance();
                $this->line("💥 Error validating {$book->title}: " . $e->getMessage());
                $results[] = [
                    'book' => $book,
                    'error' => $e->getMessage(),
                    'accurate' => false
                ];
            }
        }

        $progressBar->finish();
        $this->showValidationResults($processed, $accurate, $inaccurate, $errors, $results);

        return Command::SUCCESS;
    }

    private function validateBookAsin(Book $book): array
    {
        try {
            $region = $this->getAmazonRegion($book);
            $regionConfig = config('services.amazon.regions.' . $region);
            
            $url = "https://www.{$regionConfig['domain']}/dp/{$book->amazon_asin}";

            $response = Http::timeout(15)
                ->withHeaders([
                    'User-Agent' => self::USER_AGENT,
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language' => $this->getAcceptLanguageHeader($regionConfig['language']),
                    'Accept-Encoding' => 'gzip, deflate',
                    'Connection' => 'keep-alive',
                ])
                ->get($url);

            if (!$response->successful()) {
                return [
                    'error' => "HTTP {$response->status()}: Failed to fetch Amazon page",
                    'accurate' => false
                ];
            }

            $html = $response->body();
            $amazonData = $this->extractAmazonBookData($html);

            if (!$amazonData['title']) {
                return [
                    'error' => 'Could not extract title from Amazon page',
                    'accurate' => false
                ];
            }

            $titleMatch = $this->compareTitles($book->title, $amazonData['title']);
            $isbnMatch = $this->compareIsbns($book->isbn, $amazonData['isbn']);

            $accurate = $titleMatch['score'] >= 0.7 || $isbnMatch;

            return [
                'error' => null,
                'accurate' => $accurate,
                'amazon_title' => $amazonData['title'],
                'amazon_isbn' => $amazonData['isbn'],
                'title_similarity' => $titleMatch['score'],
                'isbn_match' => $isbnMatch,
                'url' => $url
            ];

        } catch (\Exception $e) {
            return [
                'error' => $e->getMessage(),
                'accurate' => false
            ];
        }
    }

    private function extractAmazonBookData(string $html): array
    {
        $title = null;
        $isbn = null;

        // Extract title - try multiple selectors
        $titlePatterns = [
            '/<span[^>]*id="productTitle"[^>]*>([^<]+)<\/span>/i',
            '/<h1[^>]*class="[^"]*a-size-large[^"]*"[^>]*>([^<]+)<\/h1>/i',
            '/<title>([^<]+)<\/title>/i'
        ];

        foreach ($titlePatterns as $pattern) {
            if (preg_match($pattern, $html, $matches)) {
                $title = html_entity_decode(trim($matches[1]), ENT_QUOTES, 'UTF-8');
                // Remove Amazon suffix from title tag
                $title = preg_replace('/\s*:\s*Amazon\..*$/i', '', $title);
                break;
            }
        }

        // Extract ISBN - look in multiple places
        $isbnPatterns = [
            '/ISBN-13[:\s]*(\d{13})/i',
            '/ISBN-10[:\s]*(\d{10})/i',
            '/ISBN[:\s]*(\d{10,13})/i',
            '/"isbn":"([^"]+)"/i'
        ];

        foreach ($isbnPatterns as $pattern) {
            if (preg_match($pattern, $html, $matches)) {
                $isbn = preg_replace('/[^0-9X]/i', '', $matches[1]);
                break;
            }
        }

        return [
            'title' => $title,
            'isbn' => $isbn
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

        // Also try substring matching
        $subMatch = (Str::contains($amazon, $our) || Str::contains($our, $amazon)) ? 0.8 : 0;

        return [
            'score' => max($score, $subMatch),
            'our_normalized' => $our,
            'amazon_normalized' => $amazon
        ];
    }

    private function compareIsbns(?string $ourIsbn, ?string $amazonIsbn): bool
    {
        if (!$ourIsbn || !$amazonIsbn) {
            return false;
        }

        $our = preg_replace('/[^0-9X]/i', '', $ourIsbn);
        $amazon = preg_replace('/[^0-9X]/i', '', $amazonIsbn);

        return $our === $amazon;
    }

    private function normalizeTitle(string $title): string
    {
        $normalized = strtolower(trim($title));
        
        // Remove common noise
        $normalized = preg_replace('/[^\w\s]/u', ' ', $normalized);
        $normalized = preg_replace('/\s+/', ' ', $normalized);
        $normalized = trim($normalized);
        
        return $normalized;
    }

    private function showValidationResults(int $processed, int $accurate, int $inaccurate, int $errors, array $results): void
    {
        $this->newLine(2);
        $this->info('🎉 Amazon ASIN validation completed!');
        $this->info("📊 Processed: {$processed}");
        $this->info("✅ Accurate: {$accurate}");
        $this->info("❌ Inaccurate: {$inaccurate}");
        $this->info("💥 Errors: {$errors}");

        if ($processed > 0) {
            $accuracyRate = round(($accurate / $processed) * 100, 1);
            $this->info("📈 Accuracy rate: {$accuracyRate}%");
        }

        if ($this->option('show-details')) {
            $this->showDetailedResults($results);
        } else {
            $this->newLine();
            $this->info('💡 Use --show-details flag to see detailed comparison for each book');
        }

        // Show inaccurate books summary
        $inaccurateBooks = array_filter($results, fn($r) => !$r['error'] && !$r['accurate']);
        if (count($inaccurateBooks) > 0) {
            $this->newLine();
            $this->warn('⚠️  Books with inaccurate ASINs:');
            foreach ($inaccurateBooks as $result) {
                $book = $result['book'];
                $this->line("   • {$book->title} (ASIN: {$book->amazon_asin})");
                $this->line("     Similarity: {$result['title_similarity']}, ISBN Match: " . ($result['isbn_match'] ? 'Yes' : 'No'));
            }
        }
    }

    private function showDetailedResults(array $results): void
    {
        $this->newLine();
        $this->info('📋 Detailed Results:');

        foreach ($results as $result) {
            $book = $result['book'];
            $this->newLine();
            $this->line("📚 {$book->title}");
            $this->line("   ASIN: {$book->amazon_asin}");
            
            if ($result['error']) {
                $this->line("   ❌ Error: {$result['error']}");
            } else {
                $this->line("   🏷️  Our title: {$book->title}");
                $this->line("   🏷️  Amazon: {$result['amazon_title']}");
                $this->line("   📖 Our ISBN: " . ($book->isbn ?: 'N/A'));
                $this->line("   📖 Amazon ISBN: " . ($result['amazon_isbn'] ?: 'N/A'));
                $this->line("   📊 Title similarity: " . round($result['title_similarity'] * 100, 1) . "%");
                $this->line("   🔢 ISBN match: " . ($result['isbn_match'] ? 'Yes' : 'No'));
                $this->line("   ✅ Status: " . ($result['accurate'] ? 'ACCURATE' : 'INACCURATE'));
                $this->line("   🔗 URL: {$result['url']}");
            }
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

        if (!$language) {
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