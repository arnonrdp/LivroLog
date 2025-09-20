<?php

namespace Tests\Feature;

use App\Services\MultiSourceBookSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MultiSourceBookSearchTest extends TestCase
{
    use RefreshDatabase;

    private const GOOGLE_BOOKS_API_URL = 'https://www.googleapis.com/books/v1/volumes*';

    private const TEST_ISBN = '9781234567890';

    private const TEST_AUTHOR = 'Test Author';

    private const GOOGLE_BOOKS_PROVIDER = 'Google Books';

    protected MultiSourceBookSearchService $searchService;

    protected function setUp(): void
    {
        parent::setUp();

        // Disable Amazon provider for testing to avoid real API calls
        config(['services.amazon.enabled' => false]);

        $this->searchService = new MultiSourceBookSearchService;
    }

    public function test_search_service_initialization(): void
    {
        $stats = $this->searchService->getSearchStats();

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total_providers', $stats);
        $this->assertArrayHasKey('active_providers', $stats);
        $this->assertArrayHasKey('provider_details', $stats);

        $this->assertIsInt($stats['active_providers']);
        $this->assertGreaterThanOrEqual(2, $stats['active_providers']);
    }

    public function test_search_with_isbn_success(): void
    {
        Http::fake([
            self::GOOGLE_BOOKS_API_URL => Http::response($this->getGoogleBooksResponse()),
        ]);

        $result = $this->searchService->search(self::TEST_ISBN);

        $this->assertSuccessfulGoogleBooksResult($result);

        $this->assertArrayHasKey('data', $result);
        $this->assertNotEmpty($result['data']);

        $book = $result['data'][0];
        $this->assertEquals('Test Book', $book['title']);
        $this->assertEquals(self::TEST_AUTHOR, $book['authors']);
        $this->assertEquals(self::TEST_ISBN, $book['isbn']);
    }

    public function test_search_with_combined_amazon_and_google_books(): void
    {
        Http::fake([
            self::GOOGLE_BOOKS_API_URL => Http::response([
                'totalItems' => 5,
                'items' => [
                    [
                        'id' => 'google_test_id',
                        'volumeInfo' => [
                            'title' => 'Test Book from Google',
                            'authors' => [self::TEST_AUTHOR],
                            'publisher' => 'Test Publisher',
                            'publishedDate' => '2024',
                            'pageCount' => 200,
                            'industryIdentifiers' => [
                                ['type' => 'ISBN_13', 'identifier' => self::TEST_ISBN],
                            ],
                            'imageLinks' => ['thumbnail' => 'https://example.com/thumbnail.jpg'],
                            'description' => 'Test description from Google Books',
                            'categories' => ['Fiction'],
                            'maturityRating' => 'NOT_MATURE',
                            'previewLink' => 'https://books.google.com/books?id=test',
                            'infoLink' => 'https://books.google.com/books?id=test',
                        ],
                    ],
                ],
            ]),
        ]);

        // Mock Amazon to return less than 10 results so Google Books gets called
        $result = $this->searchService->search(self::TEST_ISBN);

        $this->assertArrayHasKey('search_info', $result);
        // Should show "Google Books" since Amazon is disabled in tests
        $this->assertEquals('Google Books', $result['search_info']['provider']);
        $this->assertGreaterThan(0, $result['meta']['total']);
        $this->assertNotEmpty($result['data']);

        $this->assertArrayHasKey('data', $result);
        $book = $result['data'][0];
        $this->assertEquals('Test Book from Google', $book['title']);
        $this->assertEquals(self::TEST_AUTHOR, $book['authors']);
        $this->assertEquals(self::TEST_ISBN, $book['isbn']);
    }

    public function test_search_with_no_results(): void
    {
        Http::fake([
            self::GOOGLE_BOOKS_API_URL => Http::response($this->getEmptyGoogleBooksResponse()),
            'https://openlibrary.org/api/books*' => Http::response([]),
            'https://openlibrary.org/search.json*' => Http::response([
                'numFound' => 0,
                'docs' => [],
            ]),
        ]);

        $testQuery = 'nonexistentbook123';
        $result = $this->searchService->search($testQuery);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('meta', $result);
        $this->assertArrayHasKey('search_info', $result);

        $this->assertEquals('Multi-Source', $result['search_info']['provider']);
        $this->assertEquals(0, $result['meta']['total']);
        $this->assertEmpty($result['data']);
        $this->assertArrayHasKey('suggestions', $result['search_info']);
        $this->assertArrayHasKey('providers_tried', $result['search_info']);
    }

    public function test_search_with_specific_provider(): void
    {
        Http::fake([
            self::GOOGLE_BOOKS_API_URL => Http::response([
                'totalItems' => 1,
                'items' => [
                    [
                        'id' => 'google-test-id',
                        'volumeInfo' => [
                            'title' => 'Google Books Result',
                            'authors' => ['Google Author'],
                        ],
                    ],
                ],
            ]),
        ]);

        $testQuery = 'test query';
        $result = $this->searchService->searchWithSpecificProvider(self::GOOGLE_BOOKS_PROVIDER, $testQuery);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('provider', $result);
        $this->assertArrayHasKey('books', $result);

        $this->assertTrue($result['success']);
        $this->assertEquals(self::GOOGLE_BOOKS_PROVIDER, $result['provider']);
        $this->assertNotEmpty($result['books']);
        $this->assertContains('Google Books Result', array_column($result['books'], 'title'));
    }

    public function test_isbn_normalization(): void
    {
        $normalizedIsbn = self::TEST_ISBN;
        $hyphenatedIsbn = '978-1-234-567-89-0';

        Http::fake([
            self::GOOGLE_BOOKS_API_URL => Http::response([
                'totalItems' => 1,
                'items' => [
                    [
                        'id' => 'normalized-test',
                        'volumeInfo' => [
                            'title' => 'Normalized ISBN Test',
                            'authors' => [self::TEST_AUTHOR],
                        ],
                    ],
                ],
            ]),
        ]);

        $result = $this->searchService->search($hyphenatedIsbn);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('search_info', $result);
        $this->assertArrayHasKey('provider', $result['search_info']);

        Http::assertSent(function ($request) use ($normalizedIsbn) {
            $url = $request->url();

            return str_contains($url, $normalizedIsbn);
        });
    }

    public function test_cache_functionality(): void
    {
        $testQuery = 'cache-test-query';

        Http::fake([
            self::GOOGLE_BOOKS_API_URL => Http::response([
                'totalItems' => 1,
                'items' => [
                    [
                        'id' => 'cache-test-id',
                        'volumeInfo' => [
                            'title' => 'Cached Book',
                            'authors' => ['Cache Author'],
                        ],
                    ],
                ],
            ]),
        ]);

        $result1 = $this->searchService->search($testQuery);

        $this->assertIsArray($result1);
        $this->assertArrayHasKey('data', $result1);
        $this->assertNotEmpty($result1['data']);

        Http::assertSentCount(1);

        $result2 = $this->searchService->search($testQuery);

        $this->assertIsArray($result2);
        $this->assertArrayHasKey('data', $result2);
        $this->assertNotEmpty($result2['data']);

        $this->assertArrayHasKey('data', $result1);
        $this->assertArrayHasKey('data', $result2);
        $this->assertEquals($result1['data'], $result2['data']);
    }

    private function getGoogleBooksResponse(): array
    {
        return [
            'totalItems' => 1,
            'items' => [
                [
                    'id' => 'test-id-123',
                    'volumeInfo' => [
                        'title' => 'Test Book',
                        'authors' => [self::TEST_AUTHOR],
                        'industryIdentifiers' => [
                            [
                                'type' => 'ISBN_13',
                                'identifier' => self::TEST_ISBN,
                            ],
                        ],
                        'imageLinks' => [
                            'thumbnail' => 'https://books.google.com/books/content/images/frontcover/test.jpg',
                        ],
                        'description' => 'Test description',
                        'publisher' => 'Test Publisher',
                        'publishedDate' => '2024',
                        'pageCount' => 200,
                        'language' => 'en',
                    ],
                ],
            ],
        ];
    }

    private function getEmptyGoogleBooksResponse(): array
    {
        return [
            'totalItems' => 0,
            'items' => [],
        ];
    }

    private function assertSuccessfulGoogleBooksResult(array $result): void
    {
        $this->assertArrayHasKey('search_info', $result);
        // Shows Google Books since Amazon is disabled in tests
        $this->assertEquals(self::GOOGLE_BOOKS_PROVIDER, $result['search_info']['provider']);
        $this->assertGreaterThan(0, $result['meta']['total']);
        $this->assertNotEmpty($result['data']);
    }
}
