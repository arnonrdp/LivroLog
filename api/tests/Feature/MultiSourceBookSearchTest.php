<?php

namespace Tests\Feature;

use App\Services\MultiSourceBookSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MultiSourceBookSearchTest extends TestCase
{
    use RefreshDatabase;

    protected MultiSourceBookSearchService $searchService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->searchService = new MultiSourceBookSearchService;
    }

    public function test_search_service_initialization()
    {
        $stats = $this->searchService->getSearchStats();

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total_providers', $stats);
        $this->assertArrayHasKey('active_providers', $stats);
        $this->assertArrayHasKey('provider_details', $stats);

        // Should have at least Google Books and Open Library enabled by default
        $this->assertGreaterThanOrEqual(2, $stats['active_providers']);
    }

    public function test_search_with_isbn_success()
    {
        Http::fake([
            'https://www.googleapis.com/books/v1/volumes*' => Http::response($this->getGoogleBooksResponse()),
        ]);

        $result = $this->searchService->search('9781234567890');

        $this->assertSuccessfulGoogleBooksResult($result);

        $book = $result['books'][0];
        $this->assertEquals('Test Book', $book['title']);
        $this->assertEquals('Test Author', $book['authors']);
        $this->assertEquals('9781234567890', $book['isbn']);
    }

    public function test_search_with_fallback_to_open_library()
    {
        Http::fake([
            'https://www.googleapis.com/books/v1/volumes*' => Http::response($this->getEmptyGoogleBooksResponse()),
            'https://openlibrary.org/api/books*' => Http::response([
                'ISBN:9781234567890' => [
                    'title' => 'Test Book from Open Library',
                    'authors' => [
                        ['name' => 'Test Author'],
                    ],
                    'publishers' => ['Test Publisher'],
                    'publish_date' => '2024',
                    'number_of_pages' => 150,
                    'url' => 'https://openlibrary.org/books/test',
                ],
            ]),
        ]);

        $result = $this->searchService->search('9781234567890');

        $this->assertTrue($result['success']);
        $this->assertEquals('Open Library', $result['provider']);
        $this->assertGreaterThan(0, $result['total_found']);
        $this->assertNotEmpty($result['books']);

        $book = $result['books'][0];
        $this->assertEquals('Test Book from Open Library', $book['title']);
        $this->assertEquals('Test Author', $book['authors']);
        $this->assertEquals('9781234567890', $book['isbn']);
    }

    public function test_search_with_no_results()
    {
        Http::fake([
            'https://www.googleapis.com/books/v1/volumes*' => Http::response($this->getEmptyGoogleBooksResponse()),
            'https://openlibrary.org/api/books*' => Http::response([]),
            'https://openlibrary.org/search.json*' => Http::response([
                'numFound' => 0,
                'docs' => [],
            ]),
        ]);

        $result = $this->searchService->search('nonexistentbook123');

        $this->assertFalse($result['success']);
        $this->assertEquals('Multi-Source', $result['provider']);
        $this->assertEquals(0, $result['total_found']);
        $this->assertEmpty($result['books']);
        $this->assertArrayHasKey('suggestions', $result);
        $this->assertArrayHasKey('providers_tried', $result);
    }

    public function test_search_with_specific_provider()
    {
        Http::fake([
            'https://www.googleapis.com/books/v1/volumes*' => Http::response([
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

        $result = $this->searchService->searchWithSpecificProvider('Google Books', 'test query');

        $this->assertTrue($result['success']);
        $this->assertEquals('Google Books', $result['provider']);
        $this->assertContains('Google Books Result', array_column($result['books'], 'title'));
    }

    public function test_isbn_normalization()
    {
        Http::fake([
            'https://www.googleapis.com/books/v1/volumes*' => function ($request) {
                $query = $request->data()['q'] ?? '';
                // Should receive normalized ISBN without hyphens
                $this->assertStringContainsString('isbn:9781234567890', $query);

                return Http::response([
                    'totalItems' => 1,
                    'items' => [
                        [
                            'id' => 'normalized-test',
                            'volumeInfo' => [
                                'title' => 'Normalized ISBN Test',
                                'authors' => ['Test Author'],
                            ],
                        ],
                    ],
                ]);
            },
        ]);

        // Test with hyphenated ISBN
        $result = $this->searchService->search('978-1-234-567-89-0');
        $this->assertTrue($result['success']);
    }

    public function test_cache_functionality()
    {
        Http::fake([
            'https://www.googleapis.com/books/v1/volumes*' => Http::response([
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

        // First search - should hit API
        $result1 = $this->searchService->search('cache-test-query');
        $this->assertTrue($result1['success']);

        // Second search - should hit cache (no HTTP call expected)
        Http::assertSentCount(1); // Only one HTTP request should have been made

        $result2 = $this->searchService->search('cache-test-query');
        $this->assertTrue($result2['success']);
        $this->assertEquals($result1['books'], $result2['books']);
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
                        'authors' => ['Test Author'],
                        'industryIdentifiers' => [
                            [
                                'type' => 'ISBN_13',
                                'identifier' => '9781234567890',
                            ],
                        ],
                        'imageLinks' => [
                            'thumbnail' => 'https://example.com/cover.jpg',
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
        $this->assertTrue($result['success']);
        $this->assertEquals('Google Books', $result['provider']);
        $this->assertGreaterThan(0, $result['total_found']);
        $this->assertNotEmpty($result['books']);
    }
}
