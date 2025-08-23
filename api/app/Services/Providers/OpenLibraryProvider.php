<?php

namespace App\Services\Providers;

use App\Contracts\BookSearchProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenLibraryProvider implements BookSearchProvider
{
    private const API_BASE_URL = 'https://openlibrary.org/api/books';

    private const SEARCH_API_URL = 'https://openlibrary.org/search.json';

    private const COVERS_API_URL = 'https://covers.openlibrary.org/b';

    private const PRIORITY = 3; // Third priority (free, good coverage, after Amazon Books)

    public function search(string $query, array $options = []): array
    {
        try {
            // Try ISBN search first if it looks like an ISBN
            if ($this->looksLikeIsbn($query)) {
                $result = $this->searchByIsbn($query);
                if ($result['success'] && $result['total_found'] > 0) {
                    return $result;
                }
            }

            // Fall back to text search
            return $this->searchByText($query, $options);

        } catch (\Exception $e) {
            Log::error('OpenLibraryProvider: Search error', [
                'query' => $query,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->buildErrorResponse($e->getMessage());
        }
    }

    public function getName(): string
    {
        return 'Open Library';
    }

    public function isEnabled(): bool
    {
        return config('services.open_library.enabled', true);
    }

    public function getPriority(): int
    {
        return self::PRIORITY;
    }

    /**
     * Search by ISBN using Open Library Books API
     */
    private function searchByIsbn(string $query): array
    {
        $cleanIsbn = $this->normalizeIsbn($query);

        Log::info('OpenLibraryProvider: Searching by ISBN', [
            'original_query' => $query,
            'clean_isbn' => $cleanIsbn,
        ]);

        $response = Http::timeout(10)->get(self::API_BASE_URL, [
            'bibkeys' => "ISBN:{$cleanIsbn}",
            'format' => 'json',
            'jscmd' => 'data',
        ]);

        if (! $response->successful()) {
            Log::warning('OpenLibraryProvider: ISBN API request failed', [
                'status' => $response->status(),
                'isbn' => $cleanIsbn,
            ]);

            return $this->buildErrorResponse('ISBN API request failed');
        }

        $data = $response->json();
        $books = $this->processIsbnResults($data, $cleanIsbn);

        Log::info('OpenLibraryProvider: ISBN search completed', [
            'isbn' => $cleanIsbn,
            'found_items' => count($books),
        ]);

        // Only consider it success if we actually found books
        if (count($books) > 0) {
            return $this->buildSuccessResponse($books, count($books));
        } else {
            return $this->buildErrorResponse('No books found in Open Library for ISBN');
        }
    }

    /**
     * Search by text using Open Library Search API
     */
    private function searchByText(string $query, array $options = []): array
    {
        $searchParams = [
            'q' => $query,
            'format' => 'json',
            'limit' => $options['maxResults'] ?? 20,
        ];

        // Add specific search fields if provided
        if (isset($options['title'])) {
            $searchParams['title'] = $options['title'];
        }
        if (isset($options['author'])) {
            $searchParams['author'] = $options['author'];
        }

        Log::info('OpenLibraryProvider: Searching by text', [
            'query' => $query,
            'params' => $searchParams,
        ]);

        $response = Http::timeout(15)->get(self::SEARCH_API_URL, $searchParams);

        if (! $response->successful()) {
            Log::warning('OpenLibraryProvider: Search API request failed', [
                'status' => $response->status(),
                'query' => $query,
            ]);

            return $this->buildErrorResponse('Search API request failed');
        }

        $data = $response->json();
        $books = $this->processSearchResults($data);

        Log::info('OpenLibraryProvider: Text search completed', [
            'query' => $query,
            'total_found' => $data['numFound'] ?? 0,
            'returned_items' => count($books),
        ]);

        // Only consider it success if we actually found books
        if (count($books) > 0) {
            return $this->buildSuccessResponse($books, count($books));
        } else {
            return $this->buildErrorResponse('No books found in Open Library search');
        }
    }

    /**
     * Process ISBN search results
     */
    private function processIsbnResults(array $data, string $isbn): array
    {
        $books = [];
        $key = "ISBN:{$isbn}";

        if (isset($data[$key])) {
            $book = $this->transformOpenLibraryBook($data[$key], $isbn);
            if ($book) {
                $books[] = $book;
            }
        }

        return $books;
    }

    /**
     * Process text search results
     */
    private function processSearchResults(array $data): array
    {
        if (! isset($data['docs'])) {
            return [];
        }

        $books = [];
        foreach ($data['docs'] as $doc) {
            $book = $this->transformSearchResult($doc);
            if ($book) {
                $books[] = $book;
            }
        }

        return $books;
    }

    /**
     * Transform Open Library book data (from ISBN search)
     */
    private function transformOpenLibraryBook(array $bookData, string $isbn): ?array
    {
        if (empty($bookData['title'])) {
            return null;
        }

        // Extract ISBNs
        $isbns = $this->extractIsbns($bookData);

        return [
            'provider' => $this->getName(),
            'google_id' => null,
            'open_library_key' => $bookData['key'] ?? null,
            'title' => $bookData['title'],
            'subtitle' => $bookData['subtitle'] ?? null,
            'authors' => $this->formatAuthors($bookData['authors'] ?? []),
            'isbn' => $isbn,
            'isbn_10' => $isbns['isbn_10'] ?? null,
            'isbn_13' => $isbns['isbn_13'] ?? null,
            'thumbnail' => $this->buildCoverUrl($isbn),
            'description' => null, // Open Library doesn't provide description in this API
            'publisher' => $this->formatPublishers($bookData['publishers'] ?? []),
            'published_date' => $this->formatPublishDate($bookData['publish_date'] ?? null),
            'page_count' => $bookData['number_of_pages'] ?? null,
            'language' => $this->formatLanguages($bookData['languages'] ?? []),
            'categories' => $this->formatSubjects($bookData['subjects'] ?? []),
            'maturity_rating' => null,
            'preview_link' => $bookData['url'] ?? null,
            'info_link' => $bookData['url'] ?? null,
        ];
    }

    /**
     * Transform search result data
     */
    private function transformSearchResult(array $doc): ?array
    {
        if (empty($doc['title'])) {
            return null;
        }

        $isbn = $this->extractFirstIsbn($doc);

        return [
            'provider' => $this->getName(),
            'google_id' => null,
            'open_library_key' => $doc['key'] ?? null,
            'title' => $doc['title'],
            'subtitle' => $doc['subtitle'] ?? null,
            'authors' => $this->formatAuthorNames($doc['author_name'] ?? []),
            'isbn' => $isbn,
            'isbn_10' => $this->extractSpecificIsbn($doc, 10),
            'isbn_13' => $this->extractSpecificIsbn($doc, 13),
            'thumbnail' => $isbn ? $this->buildCoverUrl($isbn) : null,
            'description' => null,
            'publisher' => $this->formatPublisherNames($doc['publisher'] ?? []),
            'published_date' => $this->formatPublishYear($doc['first_publish_year'] ?? null),
            'page_count' => null,
            'language' => $this->formatLanguageNames($doc['language'] ?? []),
            'categories' => $this->formatSubjectNames($doc['subject'] ?? []),
            'maturity_rating' => null,
            'preview_link' => $doc['key'] ? "https://openlibrary.org{$doc['key']}" : null,
            'info_link' => $doc['key'] ? "https://openlibrary.org{$doc['key']}" : null,
        ];
    }

    /**
     * Extract ISBNs from book data
     */
    private function extractIsbns(array $bookData): array
    {
        $isbns = [
            'isbn_10' => null,
            'isbn_13' => null,
        ];

        if (isset($bookData['identifiers']['isbn_10'])) {
            $isbns['isbn_10'] = $bookData['identifiers']['isbn_10'][0] ?? null;
        }

        if (isset($bookData['identifiers']['isbn_13'])) {
            $isbns['isbn_13'] = $bookData['identifiers']['isbn_13'][0] ?? null;
        }

        return $isbns;
    }

    /**
     * Extract first available ISBN from search result
     */
    private function extractFirstIsbn(array $doc): ?string
    {
        // Try ISBN-13 first, then ISBN-10
        if (! empty($doc['isbn'])) {
            foreach ($doc['isbn'] as $isbn) {
                $clean = $this->normalizeIsbn($isbn);
                if (strlen($clean) === 13) {
                    return $clean;
                }
            }
            foreach ($doc['isbn'] as $isbn) {
                $clean = $this->normalizeIsbn($isbn);
                if (strlen($clean) === 10) {
                    return $clean;
                }
            }
        }

        return null;
    }

    /**
     * Extract specific ISBN length from search result
     */
    private function extractSpecificIsbn(array $doc, int $length): ?string
    {
        if (! empty($doc['isbn'])) {
            foreach ($doc['isbn'] as $isbn) {
                $clean = $this->normalizeIsbn($isbn);
                if (strlen($clean) === $length) {
                    return $clean;
                }
            }
        }

        return null;
    }

    /**
     * Build cover image URL from ISBN
     */
    private function buildCoverUrl(?string $isbn): ?string
    {
        if (! $isbn) {
            return null;
        }

        // Use medium size cover from Open Library
        return self::COVERS_API_URL."/isbn/{$isbn}-M.jpg";
    }

    /**
     * Format authors array
     */
    private function formatAuthors(array $authors): ?string
    {
        if (empty($authors)) {
            return null;
        }

        $names = [];
        foreach ($authors as $author) {
            if (is_array($author) && isset($author['name'])) {
                $names[] = $author['name'];
            } elseif (is_string($author)) {
                $names[] = $author;
            }
        }

        return implode(', ', $names) ?: null;
    }

    /**
     * Format author names from search results
     */
    private function formatAuthorNames(array $authorNames): ?string
    {
        return empty($authorNames) ? null : implode(', ', array_slice($authorNames, 0, 3));
    }

    /**
     * Format publishers array
     */
    private function formatPublishers(array $publishers): ?string
    {
        if (empty($publishers)) {
            return null;
        }

        // Handle both string and array formats
        $publisher = $publishers[0];
        if (is_array($publisher) && isset($publisher['name'])) {
            return $publisher['name'];
        }

        return is_string($publisher) ? $publisher : null;
    }

    /**
     * Format publisher names from search results
     */
    private function formatPublisherNames(array $publishers): ?string
    {
        return empty($publishers) ? null : $publishers[0];
    }

    /**
     * Format publish date
     */
    private function formatPublishDate(?string $date): ?string
    {
        return $date;
    }

    /**
     * Format publish year
     */
    private function formatPublishYear(?int $year): ?string
    {
        return $year ? (string) $year : null;
    }

    /**
     * Format languages array
     */
    private function formatLanguages(array $languages): ?string
    {
        if (empty($languages)) {
            return null; // No default language
        }

        foreach ($languages as $lang) {
            if (is_array($lang) && isset($lang['key'])) {
                return $lang['key'];
            } elseif (is_string($lang)) {
                return $lang;
            }
        }

        return null; // No fallback language
    }

    /**
     * Format language names from search results
     */
    private function formatLanguageNames(array $languages): ?string
    {
        return empty($languages) ? null : $languages[0];
    }

    /**
     * Format subjects array
     */
    private function formatSubjects(array $subjects): ?array
    {
        if (empty($subjects)) {
            return null;
        }

        $formatted = [];
        foreach ($subjects as $subject) {
            if (is_array($subject) && isset($subject['name'])) {
                $formatted[] = $subject['name'];
            } elseif (is_string($subject)) {
                $formatted[] = $subject;
            }
        }

        return empty($formatted) ? null : array_slice($formatted, 0, 5);
    }

    /**
     * Format subject names from search results
     */
    private function formatSubjectNames(array $subjects): ?array
    {
        return empty($subjects) ? null : array_slice($subjects, 0, 5);
    }

    /**
     * Check if query looks like an ISBN
     */
    private function looksLikeIsbn(string $query): bool
    {
        $cleaned = preg_replace('/[^0-9X]/i', '', $query);

        return strlen($cleaned) === 10 || strlen($cleaned) === 13;
    }

    /**
     * Normalize ISBN by removing hyphens and spaces
     */
    private function normalizeIsbn(string $isbn): string
    {
        return preg_replace('/[^0-9X]/i', '', $isbn);
    }

    /**
     * Build successful response
     */
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

    /**
     * Build error response
     */
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
