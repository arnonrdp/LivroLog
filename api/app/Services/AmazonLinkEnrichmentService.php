<?php

namespace App\Services;

class AmazonLinkEnrichmentService
{
    private array $regionConfig = [
        'US' => [
            'domain' => 'amazon.com',
            'search_url' => 'https://www.amazon.com/s',
            'associate_tag' => 'livrolog-20',
        ],
        'BR' => [
            'domain' => 'amazon.com.br',
            'search_url' => 'https://www.amazon.com.br/s',
            'associate_tag' => 'livrolog01-20',
        ],
        'UK' => [
            'domain' => 'amazon.co.uk',
            'search_url' => 'https://www.amazon.co.uk/s',
            'associate_tag' => 'livrolog-20',
        ],
        'CA' => [
            'domain' => 'amazon.ca',
            'search_url' => 'https://www.amazon.ca/s',
            'associate_tag' => 'livrolog-20',
        ],
    ];

    public function enrichBooksWithAmazonLinks(array $books, array $options = []): array
    {
        if (! $this->isEnabled()) {
            return $books;
        }

        $region = $this->detectOptimalRegion($options);
        $associateTag = $this->regionConfig[$region]['associate_tag'];

        if (! $associateTag) {
            return $books;
        }

        foreach ($books as &$book) {
            // Detect region based on book language, fallback to user preference
            $bookRegion = $this->detectBookRegion($book, $region);
            $bookAssociateTag = $this->regionConfig[$bookRegion]['associate_tag'];

            $book['amazon_buy_link'] = $this->generateAmazonLink($book, $bookRegion, $bookAssociateTag);
            $book['amazon_region'] = $bookRegion;
        }

        return $books;
    }

    private function generateAmazonLink(array $book, string $region, string $associateTag): string
    {
        $domain = $this->regionConfig[$region]['domain'];

        // Se tiver ASIN, gera link direto para o produto
        if (! empty($book['amazon_asin'])) {
            return "https://www.{$domain}/dp/{$book['amazon_asin']}?tag={$associateTag}";
        }

        // Fallback: gera link de busca
        $searchUrl = $this->regionConfig[$region]['search_url'];

        // Prioridade: ISBN > Título + Autor > Título
        $searchTerm = '';

        if (! empty($book['isbn'])) {
            $searchTerm = $book['isbn'];
        } elseif (! empty($book['title']) && ! empty($book['authors'])) {
            $searchTerm = $book['title'].' '.$book['authors'];
        } elseif (! empty($book['title'])) {
            $searchTerm = $book['title'];
        } else {
            $searchTerm = 'book';
        }

        return $searchUrl.'?'.http_build_query([
            'k' => $searchTerm,
            'i' => 'stripbooks',
            'tag' => $associateTag,
            'ref' => 'nb_sb_noss',
        ]);
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

        return 'US'; // Default to US for English content
    }

    /**
     * Detect the best Amazon region for a book based on its language/content
     */
    private function detectBookRegion(array $book, string $fallbackRegion): string
    {
        $title = $book['title'] ?? '';
        $description = $book['description'] ?? '';
        $language = $book['language'] ?? '';

        // Check explicit language field first
        if (! empty($language)) {
            if (str_starts_with(strtolower($language), 'pt')) {
                return 'BR';
            }
            if (str_starts_with(strtolower($language), 'en')) {
                return 'US';
            }
        }

        // Analyze title and description for language indicators
        $content = strtolower($title.' '.$description);

        // Portuguese indicators
        $portugueseWords = [
            'livro', 'edição', 'história', 'português', 'brasil', 'brazilian',
            'coleção', 'volume', 'capítulo', 'página', 'páginas',
            'português', 'brasileira', 'nacional',
        ];

        $portugueseCount = 0;
        foreach ($portugueseWords as $word) {
            if (strpos($content, $word) !== false) {
                $portugueseCount++;
            }
        }

        // English indicators
        $englishWords = [
            'edition', 'english', 'book', 'story', 'novel', 'collection',
            'volume', 'chapter', 'page', 'pages', 'american', 'british',
        ];

        $englishCount = 0;
        foreach ($englishWords as $word) {
            if (strpos($content, $word) !== false) {
                $englishCount++;
            }
        }

        // If Portuguese indicators are stronger, use Brazil
        if ($portugueseCount > $englishCount && $portugueseCount >= 1) {
            return 'BR';
        }

        // If English indicators are stronger, use US
        if ($englishCount > $portugueseCount && $englishCount >= 1) {
            return 'US';
        }

        // Fallback to user's preferred region
        return $fallbackRegion;
    }

    /**
     * Generate Amazon links for all supported regions
     */
    public function generateAllRegionLinks(array $book): array
    {
        if (! $this->isEnabled()) {
            return [];
        }

        $links = [];

        foreach ($this->regionConfig as $region => $config) {
            $associateTag = $config['associate_tag'];
            $link = $this->generateAmazonLink($book, $region, $associateTag);

            $links[] = [
                'region' => $region,
                'label' => $this->getRegionLabel($region),
                'url' => $link,
                'domain' => $config['domain'],
            ];
        }

        return $links;
    }

    /**
     * Get human-readable label for region
     */
    private function getRegionLabel(string $region): string
    {
        $labels = [
            'BR' => 'Amazon Brazil',
            'US' => 'Amazon United States',
            'UK' => 'Amazon United Kingdom',
            'CA' => 'Amazon Canada',
        ];

        return $labels[$region] ?? "Amazon {$region}";
    }

    private function isEnabled(): bool
    {
        return config('services.amazon.sitestripe_enabled', false);
    }
}
