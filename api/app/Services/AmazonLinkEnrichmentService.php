<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class AmazonLinkEnrichmentService
{
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

    public function enrichBooksWithAmazonLinks(array $books, array $options = []): array
    {
        if (!$this->isEnabled()) {
            return $books;
        }

        $region = $this->detectOptimalRegion($options);
        $associateTag = config('services.amazon.associate_tag');

        if (!$associateTag) {
            return $books;
        }

        foreach ($books as &$book) {
            $book['amazon_buy_link'] = $this->generateAmazonLink($book, $region, $associateTag);
            $book['amazon_region'] = $region;
        }

        return $books;
    }

    private function generateAmazonLink(array $book, string $region, string $associateTag): string
    {
        $domain = $this->regionConfig[$region]['domain'];
        
        // Se tiver ASIN, gera link direto para o produto
        if (!empty($book['amazon_asin'])) {
            return "https://www.{$domain}/dp/{$book['amazon_asin']}?tag={$associateTag}";
        }
        
        // Fallback: gera link de busca
        $searchUrl = $this->regionConfig[$region]['search_url'];
        
        // Prioridade: ISBN > Título + Autor > Título
        $searchTerm = '';
        
        if (!empty($book['isbn'])) {
            $searchTerm = $book['isbn'];
        } elseif (!empty($book['title']) && !empty($book['authors'])) {
            $searchTerm = $book['title'] . ' ' . $book['authors'];
        } elseif (!empty($book['title'])) {
            $searchTerm = $book['title'];
        } else {
            $searchTerm = 'book';
        }

        return $searchUrl . '?' . http_build_query([
            'k' => $searchTerm,
            'i' => 'stripbooks',
            'tag' => $associateTag,
            'ref' => 'nb_sb_noss'
        ]);
    }

    private function detectOptimalRegion(array $options): string
    {
        if (isset($options['region']) && isset($this->regionConfig[$options['region']])) {
            return $options['region'];
        }
        
        if (isset($options['locale'])) {
            $locale = strtolower($options['locale']);
            if (str_starts_with($locale, 'pt-br') || str_starts_with($locale, 'pt_br')) {
                return 'BR';
            } elseif (str_starts_with($locale, 'en-gb') || str_starts_with($locale, 'en_gb')) {
                return 'UK';
            } elseif (str_starts_with($locale, 'en-ca') || str_starts_with($locale, 'en_ca')) {
                return 'CA';
            }
        }
        
        return 'US';
    }

    private function isEnabled(): bool
    {
        return config('services.amazon.sitestripe_enabled', false) 
            && !empty(config('services.amazon.associate_tag'));
    }
}