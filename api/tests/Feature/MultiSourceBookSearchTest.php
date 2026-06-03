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

    protected MultiSourceBookSearchService $searchService;

    protected function setUp(): void
    {
        parent::setUp();

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
        // System may have 1-2 providers depending on configuration
        $this->assertGreaterThanOrEqual(1, $stats['total_providers']);
        $this->assertLessThanOrEqual(2, $stats['total_providers']);
        // Active providers depends on credentials
        $this->assertGreaterThanOrEqual(0, $stats['active_providers']);
        $this->assertLessThanOrEqual($stats['total_providers'], $stats['active_providers']);
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

    private function getEmptyGoogleBooksResponse(): array
    {
        return [
            'totalItems' => 0,
            'items' => [],
        ];
    }
}
