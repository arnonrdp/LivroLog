<?php

namespace Tests\Traits;

use App\Services\Providers\AmazonBooksProvider;
use Mockery;

/**
 * Trait for mocking Amazon PA-API in tests
 */
trait MocksAmazonApi
{
    /**
     * Mock the AmazonBooksProvider with customizable responses
     *
     * @param  array  $searchResults  Results to return from search()
     * @param  bool  $isEnabled  Whether the provider is enabled
     */
    protected function mockAmazonProvider(array $searchResults = [], bool $isEnabled = true): void
    {
        $mock = Mockery::mock(AmazonBooksProvider::class);

        $mock->shouldReceive('isEnabled')->andReturn($isEnabled);
        $mock->shouldReceive('getName')->andReturn('Amazon Books');
        $mock->shouldReceive('getPriority')->andReturn(1);

        if (empty($searchResults)) {
            $searchResults = $this->getDefaultMockBooks();
        }

        $mock->shouldReceive('search')->andReturn([
            'success' => true,
            'provider' => 'Amazon Books',
            'books' => $searchResults,
            'total_found' => count($searchResults),
            'message' => 'Found '.count($searchResults).' books',
        ]);

        $mock->shouldReceive('getItems')->andReturn([
            'success' => true,
            'provider' => 'Amazon Books',
            'books' => $searchResults,
            'total_found' => count($searchResults),
            'message' => 'Found '.count($searchResults).' books',
        ]);

        $mock->shouldReceive('getVariations')->andReturn([
            'success' => true,
            'provider' => 'Amazon Books',
            'books' => $searchResults,
            'total_found' => count($searchResults),
            'message' => 'Found '.count($searchResults).' books',
        ]);

        $this->app->instance(AmazonBooksProvider::class, $mock);
    }

    /**
     * Mock the AmazonBooksProvider to return an error
     */
    protected function mockAmazonProviderError(string $errorMessage = 'API error'): void
    {
        $mock = Mockery::mock(AmazonBooksProvider::class);

        $mock->shouldReceive('isEnabled')->andReturn(true);
        $mock->shouldReceive('getName')->andReturn('Amazon Books');
        $mock->shouldReceive('getPriority')->andReturn(1);

        $errorResponse = [
            'success' => false,
            'provider' => 'Amazon Books',
            'books' => [],
            'total_found' => 0,
            'message' => $errorMessage,
        ];

        $mock->shouldReceive('search')->andReturn($errorResponse);
        $mock->shouldReceive('getItems')->andReturn($errorResponse);
        $mock->shouldReceive('getVariations')->andReturn($errorResponse);

        $this->app->instance(AmazonBooksProvider::class, $mock);
    }

    /**
     * Mock the AmazonBooksProvider as disabled
     */
    protected function mockAmazonProviderDisabled(): void
    {
        $this->mockAmazonProvider([], false);
    }

    /**
     * Check if real Amazon API tests should be run
     */
    protected function shouldUseRealAmazonApi(): bool
    {
        return env('USE_REAL_AMAZON_API', false) === true;
    }

    /**
     * Skip test if real Amazon API is not enabled
     */
    protected function skipIfRealAmazonApiNotEnabled(): void
    {
        if (! $this->shouldUseRealAmazonApi()) {
            $this->markTestSkipped('Real Amazon API tests are disabled. Set USE_REAL_AMAZON_API=true to run.');
        }
    }

    /**
     * Get default mock book data
     */
    protected function getDefaultMockBooks(): array
    {
        return [
            [
                'provider' => 'Amazon Books',
                'amazon_asin' => 'B08N5WRWNW',
                'title' => 'O Senhor dos Aneis: A Sociedade do Anel',
                'subtitle' => null,
                'authors' => 'J.R.R. Tolkien',
                'isbn' => '9788595084742',
                'isbn_10' => '8595084742',
                'isbn_13' => '9788595084742',
                'thumbnail' => 'https://m.media-amazon.com/images/I/91dSMhdIzTL._SY466_.jpg',
                'description' => 'A primeira parte da trilogia O Senhor dos Aneis.',
                'publisher' => 'HarperCollins',
                'published_date' => '2019-11-25',
                'page_count' => 576,
                'language' => 'Portuguese',
                'categories' => ['Fantasy', 'Fiction'],
                'maturity_rating' => null,
                'preview_link' => null,
                'info_link' => 'https://www.amazon.com.br/dp/B08N5WRWNW',
            ],
            [
                'provider' => 'Amazon Books',
                'amazon_asin' => 'B0CXYZ1234',
                'title' => '1984',
                'subtitle' => null,
                'authors' => 'George Orwell',
                'isbn' => '9780451524935',
                'isbn_10' => '0451524934',
                'isbn_13' => '9780451524935',
                'thumbnail' => 'https://m.media-amazon.com/images/I/71kxa1-0mfL._SY466_.jpg',
                'description' => 'A dystopian novel by George Orwell.',
                'publisher' => 'Signet Classic',
                'published_date' => '1961-01-01',
                'page_count' => 328,
                'language' => 'English',
                'categories' => ['Science Fiction', 'Dystopian'],
                'maturity_rating' => null,
                'preview_link' => null,
                'info_link' => 'https://www.amazon.com/dp/B0CXYZ1234',
            ],
        ];
    }

    /**
     * Get a single mock book
     */
    protected function getSingleMockBook(array $overrides = []): array
    {
        $defaultBook = $this->getDefaultMockBooks()[0];

        return array_merge($defaultBook, $overrides);
    }

    /**
     * Create mock books with specific attributes
     */
    protected function createMockBooksWithAttributes(int $count, array $baseAttributes = []): array
    {
        $books = [];

        for ($i = 0; $i < $count; $i++) {
            $books[] = array_merge([
                'provider' => 'Amazon Books',
                'amazon_asin' => 'B'.strtoupper(substr(md5((string) $i), 0, 9)),
                'title' => "Mock Book Title {$i}",
                'subtitle' => null,
                'authors' => "Mock Author {$i}",
                'isbn' => '978'.str_pad((string) ($i + 1000000000), 10, '0', STR_PAD_LEFT),
                'isbn_10' => null,
                'isbn_13' => '978'.str_pad((string) ($i + 1000000000), 10, '0', STR_PAD_LEFT),
                'thumbnail' => "https://example.com/book-{$i}.jpg",
                'description' => "Description for book {$i}",
                'publisher' => "Publisher {$i}",
                'published_date' => date('Y-m-d', strtotime("-{$i} months")),
                'page_count' => 100 + ($i * 50),
                'language' => 'en',
                'categories' => ['Fiction'],
                'maturity_rating' => null,
                'preview_link' => null,
                'info_link' => 'https://www.amazon.com/dp/B'.strtoupper(substr(md5((string) $i), 0, 9)),
            ], $baseAttributes);
        }

        return $books;
    }
}
