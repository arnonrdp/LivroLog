<?php

namespace Tests\Unit\Services\Amazon;

use App\Services\Amazon\AmazonCreatorsApiClient;
use App\Services\Amazon\AmazonOAuthTokenManager;
use App\Services\Providers\AmazonCreatorsProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Mockery;
use Tests\TestCase;

class AmazonCreatorsProviderTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_provider_returns_correct_name(): void
    {
        $tokenManager = Mockery::mock(AmazonOAuthTokenManager::class);
        $tokenManager->shouldReceive('hasCredentials')->andReturn(true);

        $apiClient = Mockery::mock(AmazonCreatorsApiClient::class);
        $apiClient->shouldReceive('isConfigured')->andReturn(true);

        $provider = new AmazonCreatorsProvider($apiClient);

        $this->assertEquals('Amazon Creators', $provider->getName());
    }

    public function test_provider_returns_correct_priority(): void
    {
        $apiClient = Mockery::mock(AmazonCreatorsApiClient::class);
        $apiClient->shouldReceive('isConfigured')->andReturn(true);

        $provider = new AmazonCreatorsProvider($apiClient);

        $this->assertEquals(1, $provider->getPriority());
    }

    public function test_provider_is_disabled_when_not_configured(): void
    {
        Config::set('services.amazon.creators_api.enabled', false);

        $apiClient = Mockery::mock(AmazonCreatorsApiClient::class);
        $apiClient->shouldReceive('isConfigured')->andReturn(false);

        $provider = new AmazonCreatorsProvider($apiClient);

        $this->assertFalse($provider->isEnabled());
    }

    public function test_provider_is_enabled_when_configured(): void
    {
        Config::set('services.amazon.creators_api.enabled', true);

        $apiClient = Mockery::mock(AmazonCreatorsApiClient::class);
        $apiClient->shouldReceive('isConfigured')->andReturn(true);

        $provider = new AmazonCreatorsProvider($apiClient);

        $this->assertTrue($provider->isEnabled());
    }

    public function test_search_returns_error_when_disabled(): void
    {
        Config::set('services.amazon.creators_api.enabled', false);

        $apiClient = Mockery::mock(AmazonCreatorsApiClient::class);
        $apiClient->shouldReceive('isConfigured')->andReturn(false);

        $provider = new AmazonCreatorsProvider($apiClient);
        $result = $provider->search('test query');

        $this->assertFalse($result['success']);
        $this->assertEquals('Amazon Creators provider is disabled', $result['message']);
    }

    public function test_search_transforms_api_response(): void
    {
        Config::set('services.amazon.creators_api.enabled', true);
        Config::set('services.amazon.regions.BR.tag', 'livrolog01-20');

        $mockApiResponse = [
            'SearchResult' => [
                'Items' => [
                    [
                        'ASIN' => 'B001234567',
                        'DetailPageURL' => 'https://www.amazon.com.br/dp/B001234567',
                        'ItemInfo' => [
                            'Title' => ['DisplayValue' => 'Test Book Title'],
                            'ByLineInfo' => [
                                'Contributors' => [
                                    ['Name' => 'Test Author'],
                                ],
                            ],
                            'ExternalIds' => [
                                'ISBN13s' => ['DisplayValues' => ['9781234567890']],
                            ],
                            'Classifications' => [
                                'Binding' => ['DisplayValue' => 'Paperback'],
                                'ProductGroup' => ['DisplayValue' => 'Book'],
                            ],
                            'ContentInfo' => [
                                'PagesCount' => ['DisplayValue' => '300'],
                            ],
                        ],
                        'Images' => [
                            'Primary' => [
                                'Large' => ['URL' => 'https://m.media-amazon.com/images/I/test.jpg'],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $apiClient = Mockery::mock(AmazonCreatorsApiClient::class);
        $apiClient->shouldReceive('isConfigured')->andReturn(true);
        $apiClient->shouldReceive('searchItems')
            ->withAnyArgs()
            ->andReturn($mockApiResponse);

        $provider = new AmazonCreatorsProvider($apiClient);
        $result = $provider->search('test query');

        $this->assertTrue($result['success'], 'Search should succeed: '.$result['message']);
        $this->assertCount(1, $result['books']);
        $this->assertEquals('Test Book Title', $result['books'][0]['title']);
        $this->assertEquals('Test Author', $result['books'][0]['authors']);
        $this->assertEquals('9781234567890', $result['books'][0]['isbn']);
        $this->assertEquals('B001234567', $result['books'][0]['amazon_asin']);
    }

    public function test_get_items_returns_error_when_no_asins_provided(): void
    {
        Config::set('services.amazon.creators_api.enabled', true);

        $apiClient = Mockery::mock(AmazonCreatorsApiClient::class);
        $apiClient->shouldReceive('isConfigured')->andReturn(true);

        $provider = new AmazonCreatorsProvider($apiClient);
        $result = $provider->getItems([]);

        $this->assertFalse($result['success']);
        $this->assertEquals('No ASINs provided', $result['message']);
    }

    public function test_get_variations_returns_error_when_no_asin_provided(): void
    {
        Config::set('services.amazon.creators_api.enabled', true);

        $apiClient = Mockery::mock(AmazonCreatorsApiClient::class);
        $apiClient->shouldReceive('isConfigured')->andReturn(true);

        $provider = new AmazonCreatorsProvider($apiClient);
        $result = $provider->getVariations('');

        $this->assertFalse($result['success']);
        $this->assertEquals('No ASIN provided', $result['message']);
    }

    public function test_filters_non_book_items(): void
    {
        Config::set('services.amazon.creators_api.enabled', true);
        Config::set('services.amazon.regions.BR.tag', 'livrolog01-20');

        $mockApiResponse = [
            'SearchResult' => [
                'Items' => [
                    // This should be filtered out (toy)
                    [
                        'ASIN' => 'B001234568',
                        'ItemInfo' => [
                            'Title' => ['DisplayValue' => 'Not A Book - Toy'],
                            'Classifications' => [
                                'Binding' => ['DisplayValue' => 'Toy'],
                                'ProductGroup' => ['DisplayValue' => 'Toy'],
                            ],
                        ],
                    ],
                    // This should be included (book)
                    [
                        'ASIN' => 'B001234567',
                        'DetailPageURL' => 'https://www.amazon.com.br/dp/B001234567',
                        'ItemInfo' => [
                            'Title' => ['DisplayValue' => 'Real Book'],
                            'Classifications' => [
                                'Binding' => ['DisplayValue' => 'Hardcover'],
                                'ProductGroup' => ['DisplayValue' => 'Book'],
                            ],
                            'ContentInfo' => [
                                'PagesCount' => ['DisplayValue' => '200'],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $apiClient = Mockery::mock(AmazonCreatorsApiClient::class);
        $apiClient->shouldReceive('isConfigured')->andReturn(true);
        $apiClient->shouldReceive('searchItems')
            ->withAnyArgs()
            ->andReturn($mockApiResponse);

        $provider = new AmazonCreatorsProvider($apiClient);
        $result = $provider->search('test');

        $this->assertTrue($result['success'], 'Search should succeed: '.$result['message']);
        $this->assertCount(1, $result['books']);
        $this->assertEquals('Real Book', $result['books'][0]['title']);
    }
}
