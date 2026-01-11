<?php

namespace Tests\Unit;

use App\Models\Book;
use App\Services\AmazonScraperService;
use ReflectionClass;
use Tests\TestCase;

class AmazonScraperServiceTest extends TestCase
{
    private AmazonScraperService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AmazonScraperService;
    }

    /**
     * Helper to call private methods for testing
     */
    private function invokeMethod(string $methodName, array $parameters = []): mixed
    {
        $reflection = new ReflectionClass(AmazonScraperService::class);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($this->service, $parameters);
    }

    // ============================================
    // TITLE NORMALIZATION TESTS
    // ============================================

    /** @test */
    public function it_normalizes_titles_by_removing_common_articles(): void
    {
        $normalized = $this->invokeMethod('normalizeTitle', ['The Lord of the Rings']);
        $this->assertStringNotContainsString('the', $normalized);
        $this->assertStringContainsString('lord', $normalized);
        $this->assertStringContainsString('rings', $normalized);
    }

    /** @test */
    public function it_normalizes_portuguese_titles(): void
    {
        $normalized = $this->invokeMethod('normalizeTitle', ['O Senhor dos Anéis']);
        $this->assertStringNotContainsString(' o ', ' '.$normalized.' ');
        $this->assertStringNotContainsString(' dos ', ' '.$normalized.' ');
        $this->assertStringContainsString('senhor', $normalized);
        // Accents are preserved (anéis not aneis), but similarity still works
        $this->assertStringContainsString('anéis', $normalized);
    }

    /** @test */
    public function it_removes_parentheses_and_brackets(): void
    {
        $normalized = $this->invokeMethod('normalizeTitle', ['Harry Potter (Book 7) [Special Edition]']);
        $this->assertStringNotContainsString('book', $normalized);
        $this->assertStringNotContainsString('special', $normalized);
        $this->assertStringContainsString('harry', $normalized);
        $this->assertStringContainsString('potter', $normalized);
    }

    /** @test */
    public function it_removes_punctuation(): void
    {
        $normalized = $this->invokeMethod('normalizeTitle', ["Harry Potter: The Philosopher's Stone!"]);
        $this->assertStringNotContainsString(':', $normalized);
        $this->assertStringNotContainsString("'", $normalized);
        $this->assertStringNotContainsString('!', $normalized);
    }

    // ============================================
    // TITLE SIMILARITY TESTS
    // ============================================

    /** @test */
    public function it_calculates_high_similarity_for_identical_titles(): void
    {
        $similarity = $this->invokeMethod('calculateTitleSimilarity', [
            'Harry Potter and the Deathly Hallows',
            'Harry Potter and the Deathly Hallows',
        ]);
        $this->assertGreaterThanOrEqual(0.99, $similarity);
    }

    /** @test */
    public function it_calculates_high_similarity_for_similar_titles(): void
    {
        $similarity = $this->invokeMethod('calculateTitleSimilarity', [
            'Harry Potter and the Deathly Hallows',
            'Harry Potter & the Deathly Hallows',
        ]);
        $this->assertGreaterThanOrEqual(0.80, $similarity);
    }

    /** @test */
    public function it_calculates_low_similarity_for_different_titles(): void
    {
        $similarity = $this->invokeMethod('calculateTitleSimilarity', [
            'Harry Potter and the Deathly Hallows',
            'The Art of War',
        ]);
        $this->assertLessThan(0.50, $similarity);
    }

    /** @test */
    public function it_handles_titles_with_subtitles(): void
    {
        $similarity = $this->invokeMethod('calculateTitleSimilarity', [
            'Harry Potter and the Deathly Hallows',
            'Harry Potter and the Deathly Hallows: A Fantasy Novel',
        ]);
        $this->assertGreaterThanOrEqual(0.60, $similarity);
    }

    /** @test */
    public function it_rejects_completely_different_books(): void
    {
        $similarity = $this->invokeMethod('calculateTitleSimilarity', [
            'Harry Potter and the Deathly Hallows',
            'iPhone 15 Pro Max Case',
        ]);
        $this->assertLessThan(0.30, $similarity);
    }

    // ============================================
    // AUTHOR VALIDATION TESTS
    // ============================================

    /** @test */
    public function it_validates_matching_authors(): void
    {
        // Full author names match well
        $match = $this->invokeMethod('checkAuthorWordsMatch', [
            'Joanne Rowling',
            'Joanne K. Rowling',
        ]);
        $this->assertTrue($match);
    }

    /** @test */
    public function it_validates_author_with_different_format(): void
    {
        $match = $this->invokeMethod('checkAuthorWordsMatch', [
            'Rowling, J.K.',
            'J.K. Rowling',
        ]);
        $this->assertTrue($match);
    }

    /** @test */
    public function it_rejects_completely_different_authors(): void
    {
        $match = $this->invokeMethod('checkAuthorWordsMatch', [
            'J.K. Rowling',
            'Stephen King',
        ]);
        $this->assertFalse($match);
    }

    /** @test */
    public function it_handles_brazilian_authors(): void
    {
        $match = $this->invokeMethod('checkAuthorWordsMatch', [
            'Machado de Assis',
            'Assis, Machado de',
        ]);
        $this->assertTrue($match);
    }

    // ============================================
    // QUICK TITLE MATCH TESTS
    // ============================================

    /** @test */
    public function quick_match_accepts_contained_titles(): void
    {
        $match = $this->invokeMethod('quickTitleMatch', [
            'Deathly Hallows',
            'Harry Potter and the Deathly Hallows',
        ]);
        $this->assertTrue($match);
    }

    /** @test */
    public function quick_match_rejects_unrelated_titles(): void
    {
        $match = $this->invokeMethod('quickTitleMatch', [
            'Harry Potter',
            'iPhone Case',
        ]);
        $this->assertFalse($match);
    }

    // ============================================
    // PRODUCT VALIDATION TESTS
    // ============================================

    /** @test */
    public function it_validates_product_with_matching_isbn(): void
    {
        $book = new Book([
            'id' => 'test-id',
            'title' => 'Harry Potter',
            'isbn' => '9780545010221',
        ]);

        $productData = [
            'isbn' => '9780545010221',
            'authors' => 'J.K. Rowling',
        ];

        $result = $this->invokeMethod('validateProductMatch', [
            $book,
            'Harry Potter and the Deathly Hallows',
            $productData,
        ]);

        $this->assertTrue($result);
    }

    /** @test */
    public function it_rejects_product_with_mismatched_isbn(): void
    {
        $book = new Book([
            'id' => 'test-id',
            'title' => 'Harry Potter and the Deathly Hallows',
            'isbn' => '9780545010221',
        ]);

        $productData = [
            'isbn' => '9780140449136', // Different ISBN (The Odyssey)
            'authors' => 'J.K. Rowling',
        ];

        $result = $this->invokeMethod('validateProductMatch', [
            $book,
            'The Odyssey',
            $productData,
        ]);

        $this->assertFalse($result);
    }

    /** @test */
    public function it_rejects_product_with_low_title_similarity(): void
    {
        $book = new Book([
            'id' => 'test-id',
            'title' => 'Harry Potter and the Deathly Hallows',
            'authors' => 'J.K. Rowling',
        ]);

        $productData = [
            'authors' => null,
        ];

        $result = $this->invokeMethod('validateProductMatch', [
            $book,
            'iPhone 15 Pro Max Protective Case',
            $productData,
        ]);

        $this->assertFalse($result);
    }

    /** @test */
    public function it_accepts_product_with_high_title_similarity_no_isbn(): void
    {
        $book = new Book([
            'id' => 'test-id',
            'title' => 'Harry Potter and the Deathly Hallows',
            'authors' => 'J.K. Rowling',
        ]);

        $productData = [
            'authors' => 'J. K. Rowling',
        ];

        $result = $this->invokeMethod('validateProductMatch', [
            $book,
            'Harry Potter and the Deathly Hallows',
            $productData,
        ]);

        $this->assertTrue($result);
    }

    /** @test */
    public function it_rejects_product_with_different_author(): void
    {
        $book = new Book([
            'id' => 'test-id',
            'title' => 'Harry Potter and the Deathly Hallows',
            'authors' => 'J.K. Rowling',
        ]);

        $productData = [
            'authors' => 'Stephen King',
        ];

        $result = $this->invokeMethod('validateProductMatch', [
            $book,
            'Harry Potter and the Deathly Hallows Unofficial Guide',
            $productData,
        ]);

        $this->assertFalse($result);
    }

    // ============================================
    // EDGE CASES
    // ============================================

    /** @test */
    public function it_handles_books_with_no_author(): void
    {
        $book = new Book([
            'id' => 'test-id',
            'title' => 'The Bible',
            'authors' => null,
        ]);

        $productData = [
            'authors' => null,
        ];

        $result = $this->invokeMethod('validateProductMatch', [
            $book,
            'The Holy Bible: King James Version',
            $productData,
        ]);

        // Should pass on title match alone when no authors to compare
        $this->assertTrue($result);
    }

    /** @test */
    public function it_handles_books_with_series_in_title(): void
    {
        $similarity = $this->invokeMethod('calculateTitleSimilarity', [
            'Harry Potter and the Deathly Hallows',
            'Harry Potter and the Deathly Hallows (Harry Potter, Book 7)',
        ]);
        $this->assertGreaterThanOrEqual(0.60, $similarity);
    }

    /** @test */
    public function it_handles_unicode_characters(): void
    {
        $normalized = $this->invokeMethod('normalizeTitle', ['José García: Una Historia']);
        // Unicode characters are preserved, similarity handles matching
        $this->assertStringContainsString('josé', $normalized);
        $this->assertStringContainsString('garcía', $normalized);
        // Colon is removed
        $this->assertStringNotContainsString(':', $normalized);
    }

    /** @test */
    public function it_handles_empty_strings(): void
    {
        $similarity = $this->invokeMethod('calculateTitleSimilarity', ['', '']);
        $this->assertEquals(0.0, $similarity);
    }

    // ============================================
    // REGION DETECTION TESTS
    // ============================================

    /** @test */
    public function it_detects_brazil_region_for_portuguese_books(): void
    {
        $book = new Book(['id' => 'test', 'title' => 'Test', 'language' => 'pt']);
        $region = $this->invokeMethod('getRegionForBook', [$book]);
        $this->assertEquals('BR', $region);
    }

    /** @test */
    public function it_detects_us_region_for_english_books(): void
    {
        $book = new Book(['id' => 'test', 'title' => 'Test', 'language' => 'en']);
        $region = $this->invokeMethod('getRegionForBook', [$book]);
        $this->assertEquals('US', $region);
    }

    /** @test */
    public function it_defaults_to_brazil_for_unknown_languages(): void
    {
        $book = new Book(['id' => 'test', 'title' => 'Test', 'language' => null]);
        $region = $this->invokeMethod('getRegionForBook', [$book]);
        $this->assertEquals('BR', $region);
    }

    // ============================================
    // SEARCH RESULT TITLE VALIDATION TESTS
    // ============================================

    /** @test */
    public function it_rejects_date_format_titles(): void
    {
        // English date formats
        $this->assertFalse($this->invokeMethod('isValidSearchResultTitle', ['Jun 23, 2020']));
        $this->assertFalse($this->invokeMethod('isValidSearchResultTitle', ['Jul 25, 2017']));
        $this->assertFalse($this->invokeMethod('isValidSearchResultTitle', ['Dec 31, 2024']));

        // ISO date format
        $this->assertFalse($this->invokeMethod('isValidSearchResultTitle', ['2023-01-15']));
        $this->assertFalse($this->invokeMethod('isValidSearchResultTitle', ['2020/06/23']));
    }

    /** @test */
    public function it_rejects_price_format_titles(): void
    {
        $this->assertFalse($this->invokeMethod('isValidSearchResultTitle', ['$19.99']));
        $this->assertFalse($this->invokeMethod('isValidSearchResultTitle', ['€29.90']));
        $this->assertFalse($this->invokeMethod('isValidSearchResultTitle', ['£15.00']));
        $this->assertFalse($this->invokeMethod('isValidSearchResultTitle', ['R$49,90']));
    }

    /** @test */
    public function it_rejects_format_labels(): void
    {
        $this->assertFalse($this->invokeMethod('isValidSearchResultTitle', ['Paperback']));
        $this->assertFalse($this->invokeMethod('isValidSearchResultTitle', ['Hardcover']));
        $this->assertFalse($this->invokeMethod('isValidSearchResultTitle', ['Kindle Edition']));
        $this->assertFalse($this->invokeMethod('isValidSearchResultTitle', ['Audiobook']));
    }

    /** @test */
    public function it_rejects_numeric_values(): void
    {
        $this->assertFalse($this->invokeMethod('isValidSearchResultTitle', ['123']));
        $this->assertFalse($this->invokeMethod('isValidSearchResultTitle', ['4.5']));
    }

    /** @test */
    public function it_accepts_valid_book_titles(): void
    {
        $this->assertTrue($this->invokeMethod('isValidSearchResultTitle', ['Harry Potter and the Deathly Hallows']));
        $this->assertTrue($this->invokeMethod('isValidSearchResultTitle', ['The Lord of the Rings']));
        $this->assertTrue($this->invokeMethod('isValidSearchResultTitle', ['1984'])); // Valid book title despite being numeric
        $this->assertTrue($this->invokeMethod('isValidSearchResultTitle', ['O Senhor dos Anéis']));
    }

    /** @test */
    public function it_rejects_too_short_titles(): void
    {
        $this->assertFalse($this->invokeMethod('isValidSearchResultTitle', ['AB']));
        $this->assertFalse($this->invokeMethod('isValidSearchResultTitle', ['']));
    }

    // ============================================
    // ASIN EXTRACTION FROM URL TESTS
    // ============================================

    /** @test */
    public function it_extracts_asin_from_dp_url(): void
    {
        $asin = $this->service->extractAsinFromUrl('https://www.amazon.com/dp/B0D5H67CK1/ref=sr_1_1');
        $this->assertEquals('B0D5H67CK1', $asin);

        $asin = $this->service->extractAsinFromUrl('https://www.amazon.com.br/dp/8550801488');
        $this->assertEquals('8550801488', $asin);
    }

    /** @test */
    public function it_extracts_asin_from_gp_product_url(): void
    {
        $asin = $this->service->extractAsinFromUrl('https://www.amazon.com/gp/product/0451524934');
        $this->assertEquals('0451524934', $asin);
    }

    /** @test */
    public function it_extracts_asin_from_query_string(): void
    {
        $asin = $this->service->extractAsinFromUrl('https://www.amazon.com/something?asin=B0D5H67CK1&ref=xyz');
        $this->assertEquals('B0D5H67CK1', $asin);
    }

    /** @test */
    public function it_returns_null_for_short_urls_without_asin(): void
    {
        // Short URLs don't contain ASIN - it's only in the redirected URL
        $asin = $this->service->extractAsinFromUrl('https://a.co/d/ehjgprI');
        $this->assertNull($asin);

        $asin = $this->service->extractAsinFromUrl('https://amzn.to/3xyz123');
        $this->assertNull($asin);
    }

    /** @test */
    public function it_returns_null_for_search_urls(): void
    {
        $asin = $this->service->extractAsinFromUrl('https://www.amazon.com/s?k=harry+potter');
        $this->assertNull($asin);
    }

    // ============================================
    // REGION CONFIG FROM URL TESTS
    // ============================================

    /** @test */
    public function it_detects_us_region_from_short_urls(): void
    {
        // Short URLs should default to US region
        $config = $this->invokeMethod('getRegionConfigFromUrl', ['https://a.co/d/ehjgprI']);
        $this->assertEquals('amazon.com', $config['domain']);

        $config = $this->invokeMethod('getRegionConfigFromUrl', ['https://amzn.to/3xyz123']);
        $this->assertEquals('amazon.com', $config['domain']);

        $config = $this->invokeMethod('getRegionConfigFromUrl', ['https://amzn.com/B0D5H67CK1']);
        $this->assertEquals('amazon.com', $config['domain']);
    }

    /** @test */
    public function it_detects_brazil_region_from_amazon_com_br(): void
    {
        $config = $this->invokeMethod('getRegionConfigFromUrl', ['https://www.amazon.com.br/dp/8550801488']);
        $this->assertEquals('amazon.com.br', $config['domain']);
    }

    /** @test */
    public function it_detects_us_region_from_amazon_com(): void
    {
        $config = $this->invokeMethod('getRegionConfigFromUrl', ['https://www.amazon.com/dp/B0D5H67CK1']);
        $this->assertEquals('amazon.com', $config['domain']);
    }
}
