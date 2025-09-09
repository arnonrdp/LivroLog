<?php

namespace App\Services\Providers;

use App\Contracts\BookSearchProvider;
use App\Models\Book;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AmazonSiteStripeProvider implements BookSearchProvider
{
    private const PRIORITY = 2;

    private array $regionConfig = [
        'US' => [
            'domain' => 'amazon.com',
            'search_url' => 'https://www.amazon.com/s'
        ],
        'BR' => [
            'domain' => 'amazon.com.br',
            'search_url' => 'https://www.amazon.com.br/s'
        ],
        'UK' => [
            'domain' => 'amazon.co.uk',
            'search_url' => 'https://www.amazon.co.uk/s'
        ],
        'CA' => [
            'domain' => 'amazon.ca',
            'search_url' => 'https://www.amazon.ca/s'
        ]
    ];

    public function search(string $query, array $options = []): array
    {
        if (!$this->isEnabled()) {
            return $this->buildErrorResponse('Amazon SiteStripe provider is disabled');
        }

        try {
            $region = $this->detectOptimalRegion($options);
            $searchQuery = $this->buildSearchQuery($query, $options);

            // Para SiteStripe, criamos links diretos baseados na busca
            $books = $this->createSiteStripeResults($searchQuery, $region, $options);

            if (empty($books)) {
                return $this->buildErrorResponse('No books found');
            }

            return $this->buildSuccessResponse($books, count($books));

        } catch (\Exception $e) {
            Log::error('Amazon SiteStripe Provider error', [
                'query' => $query,
                'error' => $e->getMessage()
            ]);

            return $this->buildErrorResponse('Search failed: ' . $e->getMessage());
        }
    }

    private function buildSearchQuery(string $query, array $options): string
    {
        if ($this->looksLikeIsbn($query)) {
            return $this->normalizeIsbn($query);
        }

        if (isset($options['title']) && isset($options['author'])) {
            return $options['title'] . ' ' . $options['author'];
        }

        return trim($query);
    }

    private function detectOptimalRegion(array $options): string
    {
        if (isset($options['region']) && isset($this->regionConfig[$options['region']])) {
            return $options['region'];
        }

        if (isset($options['locale'])) {
            $locale = strtolower($options['locale']);
            if (str_starts_with($locale, 'pt-br') || str_starts_with($locale, 'pt_br') || $locale === 'pt') {
                return 'BR';
            } elseif (str_starts_with($locale, 'en-gb') || str_starts_with($locale, 'en_gb')) {
                return 'UK';
            } elseif (str_starts_with($locale, 'en-ca') || str_starts_with($locale, 'en_ca')) {
                return 'CA';
            }
        }

        return 'US';
    }

    private function createSiteStripeResults(string $searchQuery, string $region, array $options): array
    {
        // SiteStripe não retorna resultados de busca - apenas adiciona links de compra
        // aos resultados já encontrados por outros providers
        return [];
    }

    private function createSiteStripeBook(string $searchQuery, string $region, int $index): ?array
    {
        $associateTag = config('services.amazon.associate_tag');
        if (!$associateTag) {
            return null;
        }

        // Gera um ID único baseado na busca e região
        $uniqueId = 'AMZ-' . strtoupper(substr(md5($searchQuery . $region . $index), 0, 8));

        // Verifica se já existe um livro similar no banco
        $existingBook = Book::where('title', 'like', '%' . $searchQuery . '%')->first();

        $domain = $this->regionConfig[$region]['domain'];
        $searchUrl = $this->regionConfig[$region]['search_url'];

        // Cria URL de busca com associate tag
        $amazonSearchUrl = $searchUrl . '?' . http_build_query([
            'k' => $searchQuery,
            'i' => 'stripbooks',
            'tag' => $associateTag,
            'ref' => 'nb_sb_noss'
        ]);

        $bookData = [
            'provider' => $this->getName(),
            'amazon_search_url' => $amazonSearchUrl,
            'title' => $searchQuery . ' (Search Result)',
            'subtitle' => 'Available on Amazon ' . strtoupper($region),
            'authors' => 'Various Authors',
            'isbn' => $uniqueId,
            'isbn_10' => null,
            'isbn_13' => null,
            'thumbnail' => null,
            'description' => 'Search for "' . $searchQuery . '" on Amazon ' . strtoupper($region) . ' marketplace. Click to browse available books.',
            'publisher' => 'Amazon ' . strtoupper($region),
            'published_date' => null,
            'page_count' => null,
            'language' => $this->getRegionLanguage($region),
            'categories' => ['Books', 'Search Results'],
            'maturity_rating' => null,
            'preview_link' => null,
            'info_link' => $amazonSearchUrl,
        ];

        if ($existingBook) {
            $bookData['id'] = $existingBook->id;
        }

        return $bookData;
    }

    private function getRegionLanguage(string $region): string
    {
        return match($region) {
            'BR' => 'pt-BR',
            'UK' => 'en-GB',
            'CA' => 'en-CA',
            default => 'en-US'
        };
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

    public function getName(): string
    {
        return 'Amazon SiteStripe';
    }

    public function isEnabled(): bool
    {
        return config('services.amazon.sitestripe_enabled', false)
            && !empty(config('services.amazon.associate_tag'));
    }

    public function getPriority(): int
    {
        return self::PRIORITY;
    }

    private function buildSuccessResponse(array $books, int $totalFound): array
    {
        return [
            'success' => true,
            'provider' => $this->getName(),
            'books' => $books,
            'total_found' => $totalFound,
            'message' => "Found {$totalFound} search results with SiteStripe links",
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
