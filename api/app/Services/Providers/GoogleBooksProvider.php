<?php

namespace App\Services\Providers;

use App\Contracts\BookSearchProvider;
use App\Models\Book;
use Illuminate\Support\Facades\Http;

class GoogleBooksProvider implements BookSearchProvider
{
    private const API_BASE_URL = 'https://www.googleapis.com/books/v1/volumes';

    private const PRIORITY = 1;

    public function search(string $query, array $options = []): array
    {
        try {
            $searchQuery = $this->buildSearchQuery($query, $options);

            $params = [
                'q' => $searchQuery,
                'maxResults' => $options['maxResults'] ?? 20,
                'printType' => 'books', // Only books, exclude magazines
                'orderBy' => 'newest', // Prioritize recent publications
                'key' => config('services.google_books.api_key'),
            ];

            // Apply ebook filter only for very short single words to reduce noise
            if (str_word_count($query) == 1 && strlen($query) <= 3) {
                $params['filter'] = 'ebooks';
            }

            // Try to get more consistent results across different server locations
            $headers = [
                'Accept-Language' => 'pt-BR,pt;q=0.9,en;q=0.8',
            ];

            // Add location IP hint if configured
            $locationIp = config('services.google_books.location_ip');
            if ($locationIp) {
                $headers['X-Forwarded-For'] = $locationIp;
            }

            $response = Http::timeout(10)
                ->withHeaders($headers)
                ->get(self::API_BASE_URL, $params);
            $result = $this->processApiResponse($response, $searchQuery, $query);

            // Add debug info only in local/development environment
            if (config('app.debug', false)) {
                $result['debug_info'] = [
                    'request_url' => self::API_BASE_URL.'?'.http_build_query($params),
                    'search_query_sent' => $searchQuery,
                    'original_query' => $query,
                    'environment' => config('app.env'),
                    'first_result_title' => $response->json()['items'][0]['volumeInfo']['title'] ?? 'N/A',
                ];
            }

        } catch (\Exception $e) {
            $result = $this->buildErrorResponse($e->getMessage());
        }

        return $result;
    }

    private function buildSearchQuery(string $query, array $options): string
    {
        if ($this->looksLikeIsbn($query)) {
            $cleanIsbn = $this->normalizeIsbn($query);

            return "isbn:{$cleanIsbn}";
        }

        if (isset($options['title']) && isset($options['author'])) {
            return "intitle:{$options['title']} inauthor:{$options['author']}";
        }

        $cleanQuery = $this->removeArticles($query);
        $wordCount = str_word_count($cleanQuery);

        // Check if it looks like an author name (2+ words, likely proper names)
        if ($this->looksLikeAuthorName($cleanQuery)) {
            return "inauthor:$cleanQuery";
        }

        // For single words or title-like phrases, use intitle
        if ($wordCount >= 1) {
            return "intitle:$cleanQuery";
        }

        // Fallback
        return $cleanQuery;
    }

    private function looksLikeAuthorName(string $query): bool
    {
        $words = explode(' ', trim($query));
        $wordCount = count($words);

        // Must have exactly 2 words (first name + last name)
        if ($wordCount !== 2) {
            return false;
        }

        // Check if both words are capitalized (proper nouns)
        $capitalizedCount = 0;
        foreach ($words as $word) {
            if (ctype_upper($word[0]) && ctype_lower(substr($word, 1))) {
                $capitalizedCount++;
            }
        }

        // Only consider it an author if BOTH words are properly capitalized
        // This excludes things like "Quarta Asa" which could be a book title
        return $capitalizedCount === 2 && $this->hasCommonNamePatterns($words);
    }

    private function hasCommonNamePatterns(array $words): bool
    {
        // Common first names patterns
        $firstNames = ['rebecca', 'john', 'jane', 'michael', 'sarah', 'david', 'maria', 'carlos', 'ana', 'pedro', 'jose', 'antonio'];

        // Common last name suffixes
        $lastNameSuffixes = ['son', 'sen', 'ez', 'oz', 'sson', 'ros', 'rez'];

        $firstWord = strtolower($words[0]);
        $lastWord = strtolower($words[1]);

        // Check if first word is a common first name
        if (in_array($firstWord, $firstNames)) {
            return true;
        }

        // Check if last word has common surname patterns
        foreach ($lastNameSuffixes as $suffix) {
            if (str_ends_with($lastWord, $suffix)) {
                return true;
            }
        }

        return false;
    }

    private function removeArticles(string $query): string
    {
        $words = explode(' ', trim($query));

        // Remove common articles and short words that don't add search value
        $commonArticles = ['a', 'an', 'the', 'o', 'os', 'as', 'um', 'uma', 'de', 'da', 'do', 'e', 'and', 'or', 'of', 'in', 'on', 'at', 'to', 'for'];

        // Only remove if we have multiple words and first word is an article
        if (count($words) > 1 && in_array(strtolower($words[0]), $commonArticles)) {
            array_shift($words);
        }

        // Filter out very short words (≤ 2 characters) except for meaningful ones
        $meaningfulShortWords = ['ai', 'io', 'it', 'is', 'if', 'or', 'no', 'so', 'go', 'do', 'be'];
        $words = array_filter($words, function ($word) use ($meaningfulShortWords) {
            return strlen($word) > 2 || in_array(strtolower($word), $meaningfulShortWords);
        });

        return implode(' ', $words) ?: $query; // Return original if nothing left
    }

    public function getName(): string
    {
        return 'Google Books';
    }

    public function isEnabled(): bool
    {
        return true;
    }

    public function getPriority(): int
    {
        return self::PRIORITY;
    }

    private function looksLikeIsbn(string $query): bool
    {
        $cleaned = preg_replace('/[^0-9X]/i', '', $query);

        return strlen($cleaned) === 10 || strlen($cleaned) === 13;
    }

    private function normalizeIsbn(string $isbn): string
    {
        return preg_replace('/[^0-9X]/i', '', $isbn);
    }

    private function processApiResponse($response, string $searchQuery, string $originalQuery): array
    {
        if (! $response->successful()) {
            return $this->buildErrorResponse('API request failed');
        }

        $data = $response->json();
        $books = $this->processResults($data);

        if (count($books) > 0) {
            return $this->buildSuccessResponse($books, count($books));
        }

        return $this->buildErrorResponse('No books found');
    }

    private function processResults(array $data): array
    {
        if (! isset($data['items'])) {
            return [];
        }

        $books = [];
        foreach ($data['items'] as $item) {
            $book = $this->transformGoogleBookItem($item);
            if ($book) {
                $books[] = $book;
            }
        }

        return $books;
    }

    protected function transformGoogleBookItem(array $item): ?array
    {
        $volumeInfo = $item['volumeInfo'] ?? [];
        if (empty($volumeInfo['title'])) {
            return null;
        }

        // Filter out unwanted content types (temporarily disabled - was too aggressive)
        if (false && $this->shouldExcludeBook($volumeInfo)) {
            return null;
        }

        $isbn = $this->extractIsbnFromItem($volumeInfo);
        $googleId = $item['id'];
        $existingBook = Book::where('google_id', $googleId)->first();

        $bookData = [
            'provider' => $this->getName(),
            'google_id' => $googleId,
            'title' => $volumeInfo['title'] ?? '',
            'subtitle' => $volumeInfo['subtitle'] ?? null,
            'authors' => isset($volumeInfo['authors']) ? implode(', ', $volumeInfo['authors']) : null,
            'isbn' => $isbn ?: $googleId,
            'isbn_10' => $this->extractSpecificIsbn($volumeInfo, 'ISBN_10'),
            'isbn_13' => $this->extractSpecificIsbn($volumeInfo, 'ISBN_13'),
            'thumbnail' => $this->getSecureThumbnailUrl($volumeInfo),
            'description' => $volumeInfo['description'] ?? null,
            'publisher' => $volumeInfo['publisher'] ?? null,
            'published_date' => $volumeInfo['publishedDate'] ?? null,
            'page_count' => $volumeInfo['pageCount'] ?? null,
            'language' => $volumeInfo['language'] ?? null,
            'categories' => $volumeInfo['categories'] ?? null,
            'maturity_rating' => $volumeInfo['maturityRating'] ?? null,
            'preview_link' => $volumeInfo['previewLink'] ?? null,
            'info_link' => $volumeInfo['infoLink'] ?? null,
        ];

        if ($existingBook) {
            $bookData['id'] = $existingBook->id;
        }

        return $bookData;
    }

    protected function shouldExcludeBook(array $volumeInfo): bool
    {
        $title = strtolower($volumeInfo['title'] ?? '');
        $subtitle = strtolower($volumeInfo['subtitle'] ?? '');
        $description = strtolower($volumeInfo['description'] ?? '');
        $categories = array_map('strtolower', $volumeInfo['categories'] ?? []);
        $publisher = strtolower($volumeInfo['publisher'] ?? '');

        // Exclude old books (before 1950)
        if (isset($volumeInfo['publishedDate'])) {
            $year = (int) substr($volumeInfo['publishedDate'], 0, 4);
            if ($year > 0 && $year < 1950) {
                return true;
            }
        }

        // Academic and technical content indicators
        $academicKeywords = [
            'proceedings', 'journal', 'thesis', 'dissertation', 'conference', 'symposium',
            'abstract', 'research', 'study', 'analysis', 'technical report', 'working paper',
            'monograph', 'handbook', 'manual', 'guide', 'reference', 'encyclopedia',
            'volume i', 'volume ii', 'volume iii', 'vol.', 'part i', 'part ii',
            'collected works', 'selected papers', 'annual review', 'survey',
        ];

        // Academic publishers
        $academicPublishers = [
            'springer', 'elsevier', 'wiley', 'academic press', 'cambridge university press',
            'oxford university press', 'mit press', 'university press', 'ieee', 'acm',
        ];

        // Academic categories
        $academicCategories = [
            'computers / programming', 'computers / software development', 'science / general',
            'technology & engineering', 'medical', 'law', 'philosophy', 'mathematics',
            'study aids', 'reference',
        ];

        $fullText = $title.' '.$subtitle.' '.$description;

        // Check for academic keywords in title/subtitle/description
        foreach ($academicKeywords as $keyword) {
            if (str_contains($fullText, $keyword)) {
                return true;
            }
        }

        // Check publisher
        foreach ($academicPublishers as $academicPublisher) {
            if (str_contains($publisher, $academicPublisher)) {
                return true;
            }
        }

        // Check categories
        foreach ($categories as $category) {
            foreach ($academicCategories as $academicCategory) {
                if (str_contains($category, $academicCategory)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function extractIsbnFromItem(array $volumeInfo): ?string
    {
        return $this->extractSpecificIsbn($volumeInfo, 'ISBN_13')
            ?? $this->extractSpecificIsbn($volumeInfo, 'ISBN_10');
    }

    private function extractSpecificIsbn(array $volumeInfo, string $type): ?string
    {
        if (! isset($volumeInfo['industryIdentifiers'])) {
            return null;
        }

        foreach ($volumeInfo['industryIdentifiers'] as $identifier) {
            if ($identifier['type'] === $type) {
                return $identifier['identifier'];
            }
        }

        return null;
    }

    private function getSecureThumbnailUrl(array $volumeInfo): ?string
    {
        $thumbnail = $volumeInfo['imageLinks']['thumbnail'] ?? null;

        if ($thumbnail) {
            $secureUrl = str_replace('http:', 'https:', $thumbnail);

            return str_replace('&edge=curl', '', $secureUrl);
        }

        return null;
    }

    private function buildSuccessResponse(array $books, int $totalFound): array
    {
        return [
            'success' => true,
            'provider' => $this->getName(),
            'books' => $books,
            'total_found' => $totalFound,
            'message' => "Found {$totalFound} books",
        ];
    }

    private function buildErrorResponse(string $message): array
    {
        return [
            'success' => false,
            'provider' => $this->getName(),
            'books' => [],
            'total_found' => 0,
            'message' => $message,
        ];
    }
}
