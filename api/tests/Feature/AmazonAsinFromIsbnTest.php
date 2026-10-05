<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Services\AmazonEnrichmentService;
use App\Services\AmazonLinkEnrichmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AmazonAsinFromIsbnTest extends TestCase
{
    use RefreshDatabase;

    private const KNOWN = ['8576831953', '855070363X'];

    /**
     * Amazon's image CDN: a cover (JPEG) for ASINs it knows, a 43-byte GIF for the rest
     */
    private function fakeCdn(array $known = self::KNOWN): void
    {
        Http::fake(function ($request) use ($known) {
            if (! str_starts_with($request->url(), 'https://m.media-amazon.com/images/P/')) {
                return null;
            }

            return in_array(substr($request->url(), 36, 10), $known, true)
                ? Http::response('jpeg-bytes', 200, ['Content-Type' => 'image/jpeg'])
                : Http::response(str_repeat('x', 43), 200, ['Content-Type' => 'image/gif']);
        });
    }

    /**
     * A book saved before ASINs were derived on save: no hooks, no listener, no ASIN
     */
    private function legacyBook(array $attributes): Book
    {
        return Book::withoutEvents(fn () => Book::factory()->create(['amazon_asin' => null, ...$attributes]));
    }

    private function resolve(?string $isbn): ?string
    {
        return app(AmazonEnrichmentService::class)->resolveAsinFromIsbn($isbn);
    }

    public function test_isbn10_is_the_asin_when_amazon_knows_it(): void
    {
        $this->fakeCdn();

        $this->assertSame('8576831953', $this->resolve('8576831953'));
        $this->assertSame('8576831953', $this->resolve('85-7683-195-3'));
    }

    public function test_isbn13_converts_to_isbn10_including_x_check_digit(): void
    {
        $this->fakeCdn();

        $this->assertSame('8576831953', $this->resolve('9788576831952'));
        $this->assertSame('855070363X', $this->resolve('9788550703633'));
    }

    public function test_unknown_asin_is_rejected_by_the_cdn(): void
    {
        $this->fakeCdn([]);

        $this->assertNull($this->resolve('8576831953'));
    }

    public function test_non_isbns_are_rejected_without_calling_amazon(): void
    {
        Http::fake();

        $this->assertNull($this->resolve(null));
        $this->assertNull($this->resolve('9791234567896'));      // 979 has no ISBN-10
        $this->assertNull($this->resolve('9788576831953'));      // bad ISBN-13 checksum
        $this->assertNull($this->resolve('8576831954'));         // bad ISBN-10 checksum
        $this->assertNull($this->resolve('eGEiEQAAQBAJ'));       // Google volume id
        $this->assertNull($this->resolve('UOM:39015012345678')); // library id whose digits look numeric
        Http::assertNothingSent();
    }

    public function test_saving_a_book_with_isbn_fills_its_asin(): void
    {
        $this->fakeCdn();

        $book = Book::factory()->create(['isbn' => '9788576831952', 'amazon_asin' => null, 'asin_status' => 'pending']);

        $this->assertSame('8576831953', $book->fresh()->amazon_asin);
        $this->assertSame('completed', $book->fresh()->asin_status);
    }

    public function test_saving_never_replaces_an_existing_asin(): void
    {
        $this->fakeCdn();

        $book = Book::factory()->create(['isbn' => '9788576831952', 'amazon_asin' => 'B00A7YSKY2']);

        $this->assertSame('B00A7YSKY2', $book->fresh()->amazon_asin);
    }

    public function test_backfill_dry_run_changes_nothing(): void
    {
        $book = $this->legacyBook(['isbn' => '9788576831952', 'asin_status' => 'pending']);
        $this->fakeCdn();

        $this->artisan('books:backfill-asins', ['--dry-run' => true])->assertSuccessful();

        $this->assertNull($book->fresh()->amazon_asin);
        $this->assertSame('pending', $book->fresh()->asin_status);
    }

    public function test_backfill_resolves_from_isbn_then_google_and_fails_the_rest(): void
    {
        $fromIsbn = $this->legacyBook(['isbn' => '9788576831952', 'asin_status' => 'processing']);
        $fromGoogle = $this->legacyBook(['isbn' => 'eGEiEQAAQBAJ', 'google_id' => 'gid-1', 'asin_status' => 'pending']);
        $hopeless = $this->legacyBook(['isbn' => null, 'google_id' => 'gid-2', 'asin_status' => 'pending']);

        Http::fake([
            'm.media-amazon.com/images/P/8576831953*' => Http::response('jpeg', 200, ['Content-Type' => 'image/jpeg']),
            'm.media-amazon.com/images/P/855070363X*' => Http::response('jpeg', 200, ['Content-Type' => 'image/jpeg']),
            'www.googleapis.com/books/v1/volumes/gid-1*' => Http::response(['volumeInfo' => ['industryIdentifiers' => [
                ['type' => 'ISBN_13', 'identifier' => '9788550703633'],
            ]]]),
            'www.googleapis.com/books/v1/volumes/gid-2*' => Http::response(['volumeInfo' => []]),
        ]);

        $this->artisan('books:backfill-asins')->assertSuccessful();

        $this->assertSame('8576831953', $fromIsbn->fresh()->amazon_asin);
        $this->assertSame('completed', $fromIsbn->fresh()->asin_status);
        $this->assertSame('855070363X', $fromGoogle->fresh()->amazon_asin);
        $this->assertSame('9788550703633', $fromGoogle->fresh()->isbn); // the Google id was not an ISBN
        $this->assertNull($hopeless->fresh()->amazon_asin);
        $this->assertSame('failed', $hopeless->fresh()->asin_status);
    }

    public function test_us_links_carry_the_us_affiliate_tag(): void
    {
        config(['services.amazon.sitestripe_enabled' => true]);

        $links = collect(app(AmazonLinkEnrichmentService::class)->generateAllRegionLinks(['amazon_asin' => '0316129062']))
            ->keyBy('region');

        $this->assertSame('https://www.amazon.com/dp/0316129062?tag=livrolog-20', $links['US']['url']);
        $this->assertSame('https://www.amazon.com.br/dp/0316129062?tag=livrolog01-20', $links['BR']['url']);
    }
}
