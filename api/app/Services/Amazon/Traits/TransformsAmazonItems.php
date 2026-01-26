<?php

namespace App\Services\Amazon\Traits;

use App\Models\Book;
use Illuminate\Support\Facades\Log;

/**
 * Shared transformation logic for Amazon API responses
 * Works with both PA-API SDK objects and Creators API arrays
 */
trait TransformsAmazonItems
{
    /**
     * Transform an Amazon item (array format from Creators API) into standardized book data
     */
    protected function transformAmazonItemArray(array $item): ?array
    {
        $itemInfo = $item['ItemInfo'] ?? null;
        if (! $itemInfo || ! isset($itemInfo['Title']['DisplayValue'])) {
            return null;
        }

        $title = $itemInfo['Title']['DisplayValue'];
        $authors = $this->extractAuthorsFromArray($itemInfo);
        $isbn = $this->extractIsbnFromArray($itemInfo);
        $asin = $item['ASIN'] ?? null;

        // Never use ASIN as ISBN
        if ($isbn && preg_match('/^B[0-9A-Z]{9}$/i', $isbn)) {
            Log::warning('ASIN detected in ISBN field', [
                'title' => $title,
                'asin' => $asin,
                'isbn_before' => $isbn,
            ]);
            $isbn = null;
        }

        // Filter out non-book items
        if (! $this->isActualBookFromArray($item, $itemInfo)) {
            return null;
        }

        // Check for existing book
        $existingBook = null;
        if ($isbn) {
            $existingBook = Book::where('isbn', $isbn)->first();
        }
        if (! $existingBook && $asin) {
            $existingBook = Book::where('amazon_asin', $asin)->first();
        }

        // Extract categories
        $classificationCategories = $this->extractCategoriesFromArray($itemInfo);
        $browseNodeCategories = $this->extractBrowseNodesFromArray($item);

        $allCategories = [];
        if ($browseNodeCategories) {
            $allCategories = array_merge($allCategories, $browseNodeCategories);
        }
        if ($classificationCategories) {
            $allCategories = array_merge($allCategories, $classificationCategories);
        }
        $allCategories = ! empty($allCategories) ? array_values(array_unique($allCategories)) : null;
        $allCategories = $this->filterRelevantCategoriesArray($allCategories);

        // Extract Amazon rating
        $amazonRating = $this->extractAmazonRatingFromArray($item);

        $bookData = [
            'provider' => $this->getName(),
            'amazon_asin' => $asin,
            'title' => $title,
            'subtitle' => null,
            'authors' => $authors,
            'isbn' => $isbn,
            'isbn_10' => $this->extractSpecificIsbnFromArray($itemInfo, 'ISBN10'),
            'isbn_13' => $this->extractSpecificIsbnFromArray($itemInfo, 'ISBN13'),
            'thumbnail' => $this->extractThumbnailFromArray($item),
            'description' => $this->extractDescriptionFromArray($itemInfo),
            'publisher' => $this->extractPublisherFromArray($itemInfo),
            'published_date' => $this->extractPublishedDateFromArray($itemInfo),
            'page_count' => $this->extractPageCountFromArray($itemInfo),
            'language' => $this->extractLanguageFromArray($itemInfo),
            'categories' => $allCategories,
            'maturity_rating' => $this->extractMaturityRatingFromArray($itemInfo),
            'preview_link' => null,
            'info_link' => $item['DetailPageURL'] ?? null,
            'amazon_rating' => $amazonRating['rating'],
            'amazon_rating_count' => $amazonRating['count'],
        ];

        if ($existingBook) {
            $bookData['id'] = $existingBook->id;
        }

        return $bookData;
    }

    protected function extractAuthorsFromArray(array $itemInfo): ?string
    {
        $byLineInfo = $itemInfo['ByLineInfo'] ?? null;
        if (! $byLineInfo || ! isset($byLineInfo['Contributors'])) {
            return null;
        }

        $authors = [];
        foreach ($byLineInfo['Contributors'] as $contributor) {
            if (isset($contributor['Name'])) {
                $authors[] = $contributor['Name'];
            }
        }

        return ! empty($authors) ? implode(', ', $authors) : null;
    }

    protected function extractIsbnFromArray(array $itemInfo): ?string
    {
        $externalIds = $itemInfo['ExternalIds'] ?? null;
        if ($externalIds) {
            // Try ISBN-13 first
            if (isset($externalIds['ISBN13s']['DisplayValues'][0])) {
                $value = $externalIds['ISBN13s']['DisplayValues'][0];
                if ($this->isValidIsbnValue($value)) {
                    return $value;
                }
            }

            // Try ISBN-10
            if (isset($externalIds['ISBN10s']['DisplayValues'][0])) {
                $value = $externalIds['ISBN10s']['DisplayValues'][0];
                if ($this->isValidIsbnValue($value)) {
                    return $value;
                }
            }

            // Try EANs
            if (isset($externalIds['EANs']['DisplayValues'])) {
                foreach ($externalIds['EANs']['DisplayValues'] as $ean) {
                    if (strlen($ean) === 13 && (str_starts_with($ean, '978') || str_starts_with($ean, '979'))) {
                        if ($this->isValidIsbnValue($ean)) {
                            return $ean;
                        }
                    }
                }
            }
        }

        return $this->extractSpecificIsbnFromArray($itemInfo, 'ISBN13')
            ?? $this->extractSpecificIsbnFromArray($itemInfo, 'ISBN10');
    }

    protected function extractSpecificIsbnFromArray(array $itemInfo, string $type): ?string
    {
        $externalIds = $itemInfo['ExternalIds'] ?? null;
        if (! $externalIds) {
            return null;
        }

        $key = $type === 'ISBN13' ? 'ISBN13s' : 'ISBN10s';
        if (isset($externalIds[$key]['DisplayValues'][0])) {
            $value = $externalIds[$key]['DisplayValues'][0];
            if ($this->isValidIsbnValue($value)) {
                return $value;
            }
        }

        return null;
    }

    protected function extractThumbnailFromArray(array $item): ?string
    {
        $images = $item['Images'] ?? null;
        if (! $images || ! isset($images['Primary'])) {
            return null;
        }

        $primary = $images['Primary'];
        $imageUrl = $primary['Large']['URL']
            ?? $primary['Medium']['URL']
            ?? $primary['Small']['URL']
            ?? null;

        if (! $imageUrl) {
            return null;
        }

        return $this->convertToHighResolutionUrl($imageUrl);
    }

    /**
     * Convert Amazon image URL to high resolution version
     */
    protected function convertToHighResolutionUrl(string $url): string
    {
        $pattern = '/(\._[A-Z]{2}\d+_|\._AC_[A-Z]{2}\d+_)/';

        if (preg_match($pattern, $url)) {
            return preg_replace($pattern, '._SL1500_', $url);
        }

        $patternAlt = '/\._[A-Z]{2}\d+_\./';
        if (preg_match($patternAlt, $url)) {
            return preg_replace($patternAlt, '._SL1500_.', $url);
        }

        if (preg_match('/\/images\/I\/([A-Za-z0-9+%-]+)\.([a-z]{3,4})$/i', $url, $matches)) {
            $imageId = $matches[1];
            $extension = $matches[2];

            return preg_replace('/\/images\/I\/[A-Za-z0-9+%-]+\.[a-z]{3,4}$/i', "/images/I/{$imageId}._SL1500_.{$extension}", $url);
        }

        return $url;
    }

    protected function extractDescriptionFromArray(array $itemInfo): ?string
    {
        if (isset($itemInfo['Features']['DisplayValues'])) {
            return implode(' ', $itemInfo['Features']['DisplayValues']);
        }

        return null;
    }

    protected function extractPublisherFromArray(array $itemInfo): ?string
    {
        return $itemInfo['ByLineInfo']['Manufacturer']['DisplayValue'] ?? null;
    }

    protected function extractPublishedDateFromArray(array $itemInfo): ?string
    {
        return $itemInfo['ContentInfo']['PublicationDate']['DisplayValue'] ?? null;
    }

    protected function extractPageCountFromArray(array $itemInfo): ?int
    {
        $pageCount = $itemInfo['ContentInfo']['PagesCount']['DisplayValue'] ?? null;

        return $pageCount ? (int) $pageCount : null;
    }

    protected function extractLanguageFromArray(array $itemInfo): ?string
    {
        $languages = $itemInfo['ContentInfo']['Languages']['DisplayValues'] ?? null;
        if ($languages && ! empty($languages)) {
            $lang = $languages[0];
            if (is_array($lang) && isset($lang['DisplayValue'])) {
                return $lang['DisplayValue'];
            }

            return is_string($lang) ? $lang : null;
        }

        return null;
    }

    protected function extractCategoriesFromArray(array $itemInfo): ?array
    {
        $classifications = $itemInfo['Classifications'] ?? null;
        if (! $classifications) {
            return null;
        }

        $categories = [];
        if (isset($classifications['Binding']['DisplayValue'])) {
            $categories[] = $classifications['Binding']['DisplayValue'];
        }
        if (isset($classifications['ProductGroup']['DisplayValue'])) {
            $categories[] = $classifications['ProductGroup']['DisplayValue'];
        }

        return ! empty($categories) ? $categories : null;
    }

    protected function extractMaturityRatingFromArray(array $itemInfo): ?string
    {
        return $itemInfo['ContentRating']['AudienceRating']['DisplayValue'] ?? null;
    }

    protected function extractAmazonRatingFromArray(array $item): array
    {
        $result = [
            'rating' => null,
            'count' => null,
        ];

        $customerReviews = $item['CustomerReviews'] ?? null;
        if (! $customerReviews) {
            return $result;
        }

        if (isset($customerReviews['StarRating']['Value'])) {
            $result['rating'] = (float) $customerReviews['StarRating']['Value'];
        }

        if (isset($customerReviews['Count'])) {
            $result['count'] = (int) $customerReviews['Count'];
        }

        return $result;
    }

    protected function extractBrowseNodesFromArray(array $item): ?array
    {
        $browseNodeInfo = $item['BrowseNodeInfo'] ?? null;
        if (! $browseNodeInfo || ! isset($browseNodeInfo['BrowseNodes'])) {
            return null;
        }

        $categories = [];

        foreach ($browseNodeInfo['BrowseNodes'] as $node) {
            if (isset($node['DisplayName'])) {
                $hierarchy = [$node['DisplayName']];

                $ancestor = $node['Ancestor'] ?? null;
                while ($ancestor) {
                    if (isset($ancestor['DisplayName'])) {
                        array_unshift($hierarchy, $ancestor['DisplayName']);
                    }
                    $ancestor = $ancestor['Ancestor'] ?? null;
                }

                $categories = array_merge($categories, $hierarchy);
            }
        }

        $categories = array_values(array_unique($categories));

        return ! empty($categories) ? $categories : null;
    }

    protected function filterRelevantCategoriesArray(?array $categories): ?array
    {
        if (! $categories || empty($categories)) {
            return null;
        }

        $excludePatterns = [
            '/livros? até r\$/i',
            '/ebooks? até r\$/i',
            '/top asins?/i',
            '/guia de compras/i',
            '/promo(ção|cao)?/i',
            '/cupom/i',
            '/off (no|em)/i',
            '/termos e condi(ções|coes)/i',
            '/n(ão|ao) aplicad/i',
            '/lan(ç|c)amentos/i',
            '/ofertas?/i',
            '/mais amados/i',
            '/mais lidos/i',
            '/preferidos/i',
            '/p(á|a)gina do autor/i',
            '/comece a sua leitura/i',
            '/em oferta/i',
            '/kindle unlimited/i',
            '/catalogo kindle/i',
            '/cashback/i',
            '/pr(é|e)-venda/i',
            '/aniversa(á|a)rio/i',
            '/editora /i',
            '/obrigado por/i',
            '/sele(ç|c)(ã|a)o participante/i',
            '/categorias$/i',
            '/compra de ebook/i',
            '/^[a-f0-9]{8}-[a-f0-9]{4}/i',
            '/^trade$/i',
            '/^book$/i',
            '/^capa (comum|dura)$/i',
            '/ebook kindle$/i',
            '/kindle unlimited$/i',
        ];

        $publisherNames = [
            'ciranda cultural',
            'alta books',
            'companhia das letras',
            'rocco',
            'darkside',
            'intrínseca',
            'nova fronteira',
            'globo livros',
        ];

        $filtered = [];
        foreach ($categories as $category) {
            $shouldExclude = false;

            foreach ($excludePatterns as $pattern) {
                if (preg_match($pattern, $category)) {
                    $shouldExclude = true;
                    break;
                }
            }

            $lowerCategory = mb_strtolower($category, 'UTF-8');
            if (in_array($lowerCategory, $publisherNames)) {
                $shouldExclude = true;
            }

            if (! $shouldExclude) {
                $filtered[] = $category;
            }
        }

        return ! empty($filtered) ? array_values($filtered) : null;
    }

    protected function isActualBookFromArray(array $item, array $itemInfo): bool
    {
        $classifications = $itemInfo['Classifications'] ?? null;
        if ($classifications) {
            $bindingValue = $classifications['Binding']['DisplayValue'] ?? null;
            if ($bindingValue) {
                $bindingType = strtolower($bindingValue);

                $bookBindings = [
                    'paperback', 'hardcover', 'kindle', 'ebook',
                    'mass market', 'library binding', 'board book',
                    'spiral', 'loose leaf', 'audio',
                ];

                foreach ($bookBindings as $bookBinding) {
                    if (strpos($bindingType, $bookBinding) !== false) {
                        return true;
                    }
                }

                $nonBookBindings = [
                    'toy', 'game', 'accessory', 'electronics',
                    'video game', 'calendar', 'cards', 'dvd',
                ];

                foreach ($nonBookBindings as $nonBookBinding) {
                    if (strpos($bindingType, $nonBookBinding) !== false) {
                        return false;
                    }
                }
            }

            $productGroup = $classifications['ProductGroup']['DisplayValue'] ?? null;
            if ($productGroup) {
                $group = strtolower($productGroup);
                if ($group === 'book' || $group === 'ebook' || $group === 'audible') {
                    return true;
                }
                if ($group === 'toy' || $group === 'home' || $group === 'sports') {
                    return false;
                }
            }
        }

        // Check for page count
        $pageCount = $itemInfo['ContentInfo']['PagesCount']['DisplayValue'] ?? null;
        if ($pageCount && intval($pageCount) > 0) {
            return true;
        }

        // Check for ISBN
        if ($this->extractIsbnFromArray($itemInfo)) {
            return true;
        }

        return false;
    }

    protected function isValidIsbnValue(string $value): bool
    {
        $cleanValue = str_replace(['-', ' '], '', $value);

        // ASINs start with B
        if (preg_match('/^B[0-9A-Z]{9}$/i', $cleanValue)) {
            return false;
        }

        // ISBNs don't have letters except X at position 10
        if (preg_match('/[A-Z]/i', substr($cleanValue, 0, 9))) {
            return false;
        }
        if (strlen($cleanValue) > 10 && preg_match('/[A-Z]/i', substr($cleanValue, 10))) {
            return false;
        }

        // ISBN-10
        if (strlen($cleanValue) === 10) {
            return preg_match('/^[0-9]{9}[0-9X]$/i', $cleanValue);
        }

        // ISBN-13
        if (strlen($cleanValue) === 13) {
            return preg_match('/^[0-9]{13}$/', $cleanValue);
        }

        return false;
    }

    protected function looksLikeIsbnQuery(string $query): bool
    {
        $cleaned = preg_replace('/[^0-9X]/i', '', $query);

        return strlen($cleaned) === 10 || strlen($cleaned) === 13;
    }

    protected function normalizeIsbnQuery(string $isbn): string
    {
        return preg_replace('/[^0-9X]/i', '', $isbn);
    }
}
