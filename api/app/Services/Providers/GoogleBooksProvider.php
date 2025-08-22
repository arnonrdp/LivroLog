<?php

namespace App\Services\Providers;

use App\Contracts\BookSearchProvider;
use App\Models\Book;
use Illuminate\Support\Facades\Http;

class GoogleBooksProvider implements BookSearchProvider
{
    private const API_BASE_URL = 'https://www.googleapis.com/books/v1/volumes';

    private const PRIORITY = 1; // Highest priority (fastest, free)

    public function search(string $query, array $options = []): array
    {
        try {
            $searchQuery = $this->buildSearchQuery($query, $options);
            
            $params = [
                'q' => $searchQuery,
                'maxResults' => $options['maxResults'] ?? 20, // Reduced default from 40 to 20
                'printType' => 'books',
                'orderBy' => 'relevance', // Ensure consistent ordering
                'key' => config('services.google_books.api_key'),
            ];
            
            // Temporary debugging - log the actual request
            \Log::info('Google Books API Request', [
                'url' => self::API_BASE_URL,
                'params' => $params,
                'has_api_key' => !empty($params['key']),
                'original_query' => $query,
                'search_query' => $searchQuery,
            ]);
            
            $response = Http::timeout(10)->get(self::API_BASE_URL, $params);

            $result = $this->processApiResponse($response, $searchQuery, $query);
            
            // Temporary debugging - add request info to response
            if (in_array($query, ['TestDebugDev', 'Quarta Asa'])) {
                $result['debug_info'] = [
                    'request_url' => self::API_BASE_URL . '?' . http_build_query($params),
                    'api_key_used' => substr($params['key'] ?? 'none', 0, 10) . '...',
                    'search_query_sent' => $searchQuery,
                    'original_query' => $query,
                    'response_status' => $response->status(),
                    'response_items_count' => isset($response->json()['items']) ? count($response->json()['items']) : 0,
                    'first_result_title' => $response->json()['items'][0]['volumeInfo']['title'] ?? 'N/A'
                ];
            }

        } catch (\Exception $e) {
            // Search error - continue to next provider
            $result = $this->buildErrorResponse($e->getMessage());
        }

        return $result;
    }

    /**
     * Process the API response and determine the result
     */
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

        return $this->buildErrorResponse('No books found in Google Books');
    }

    public function getName(): string
    {
        return 'Google Books';
    }

    public function isEnabled(): bool
    {
        return true; // Always available, API key is optional
    }

    public function getPriority(): int
    {
        return self::PRIORITY;
    }

    /**
     * Build search query optimized for Google Books API
     */
    private function buildSearchQuery(string $query, array $options): string
    {
        // If it looks like an ISBN, search by ISBN
        if ($this->looksLikeIsbn($query)) {
            $cleanIsbn = $this->normalizeIsbn($query);

            return "isbn:{$cleanIsbn}";
        }

        // If title and author are provided separately
        if (isset($options['title']) && isset($options['author'])) {
            return "intitle:{$options['title']} inauthor:{$options['author']}";
        }

        // Default search
        return $query;
    }

    /**
     * Process Google Books API results into standardized format
     */
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

    /**
     * Transform single Google Books item to standardized format
     */
    private function transformGoogleBookItem(array $item): ?array
    {
        $volumeInfo = $item['volumeInfo'] ?? [];

        // Skip items without essential information
        if (empty($volumeInfo['title'])) {
            return null;
        }

        $isbn = $this->extractIsbnFromItem($volumeInfo);
        $googleId = $item['id'];

        // Check if this book already exists in our database
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
            'language' => $volumeInfo['language'] ?? null, // Let API determine language
            'categories' => $volumeInfo['categories'] ?? null,
            'maturity_rating' => $volumeInfo['maturityRating'] ?? null,
            'preview_link' => $volumeInfo['previewLink'] ?? null,
            'info_link' => $volumeInfo['infoLink'] ?? null,
        ];

        // If book exists in our database, include the internal ID
        if ($existingBook) {
            $bookData['id'] = $existingBook->id;
        }
        // If not, 'id' field will be absent and BookTransformer will handle it as null

        return $bookData;
    }

    /**
     * Extract ISBN from Google Books volume info
     */
    private function extractIsbnFromItem(array $volumeInfo): ?string
    {
        // Prefer ISBN-13, then ISBN-10
        return $this->extractSpecificIsbn($volumeInfo, 'ISBN_13')
            ?? $this->extractSpecificIsbn($volumeInfo, 'ISBN_10');
    }

    /**
     * Extract specific ISBN type
     */
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

    /**
     * Get secure thumbnail URL
     */
    private function getSecureThumbnailUrl(array $volumeInfo): ?string
    {
        $thumbnail = $volumeInfo['imageLinks']['thumbnail'] ?? null;

        if ($thumbnail) {
            // Ensure HTTPS and potentially higher resolution
            $secureUrl = str_replace('http:', 'https:', $thumbnail);
            // Increase image quality if possible
            $secureUrl = str_replace('&edge=curl', '', $secureUrl);

            return $secureUrl;
        }

        return null;
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
        return $this->buildResponse(true, $books, $totalFound, "Found {$totalFound} books");
    }

    /**
     * Build error response
     */
    private function buildErrorResponse(string $message): array
    {
        return $this->buildResponse(false, [], 0, $message);
    }

    /**
     * Build standardized response
     */
    private function buildResponse(bool $success, array $books, int $totalFound, string $message): array
    {
        return [
            'success' => $success,
            'provider' => $this->getName(),
            'books' => $books,
            'total_found' => $totalFound,
            'message' => $message,
        ];
    }
}
