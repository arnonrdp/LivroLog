<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Services\AmazonScraperService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BookCoverPaddingTest extends TestCase
{
    use RefreshDatabase;

    private const COVER = 'https://m.media-amazon.com/images/I/61sj7GyrUqL._SL1500_.jpg';

    /**
     * White canvas with a solid "cover" rectangle at [left, top, right, bottom)
     */
    private function image(int $width, int $height, array $box): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
        imagefilledrectangle($image, $box[0], $box[1], $box[2] - 1, $box[3] - 1, imagecolorallocate($image, 40, 80, 160));
        ob_start();
        imagepng($image);

        return ob_get_clean();
    }

    private function trim(string $url, string $image): string
    {
        Http::fake(['m.media-amazon.com/*' => Http::response($image)]);

        return app(AmazonScraperService::class)->removeWhitePadding($url);
    }

    public function test_crops_cover_centered_on_square_white_canvas(): void
    {
        // Amazon's usual padded image: a 1000x1000 canvas around a portrait cover
        $this->assertSame(
            'https://m.media-amazon.com/images/I/61sj7GyrUqL._CR209,61,583,878_.jpg',
            $this->trim(self::COVER, $this->image(1000, 1000, [209, 61, 792, 939]))
        );
        Http::assertSent(fn ($request) => $request->url() === 'https://m.media-amazon.com/images/I/61sj7GyrUqL.jpg');
    }

    public function test_crops_even_frame_around_book_shaped_image(): void
    {
        $this->assertSame(
            'https://m.media-amazon.com/images/I/61sj7GyrUqL._CR77,77,844,1286_.jpg',
            $this->trim(self::COVER, $this->image(1000, 1440, [77, 77, 921, 1363]))
        );
    }

    public function test_keeps_full_bleed_cover(): void
    {
        $this->assertSame(self::COVER, $this->trim(self::COVER, $this->image(1000, 1500, [0, 0, 1000, 1500])));
    }

    public function test_keeps_cover_whose_own_art_is_white_at_the_edges(): void
    {
        // A white cover: uneven white bands that belong to the design, not a frame (e.g. "Jantar secreto")
        $this->assertSame(self::COVER, $this->trim(self::COVER, $this->image(600, 900, [0, 60, 600, 860])));
    }

    public function test_keeps_padding_that_would_leave_a_non_book_shape(): void
    {
        // Boxed sets and 3D renders: what's left after trimming isn't a cover
        $this->assertSame(self::COVER, $this->trim(self::COVER, $this->image(1000, 1000, [100, 300, 900, 700])));
    }

    public function test_ignores_non_amazon_and_already_cropped_urls(): void
    {
        Http::fake();
        $scraper = app(AmazonScraperService::class);

        $google = 'https://books.google.com/books/content?id=abc&printsec=frontcover&img=1';
        $cropped = 'https://m.media-amazon.com/images/I/61sj7GyrUqL._CR209,61,583,878_.jpg';

        $this->assertSame($google, $scraper->removeWhitePadding($google));
        $this->assertSame($cropped, $scraper->removeWhitePadding($cropped));
        Http::assertNothingSent();
    }

    public function test_keeps_url_when_image_cannot_be_read(): void
    {
        $this->assertSame(self::COVER, $this->trim(self::COVER, '<html>not an image</html>'));
    }

    public function test_saving_a_book_trims_its_cover(): void
    {
        Http::fake(['m.media-amazon.com/*' => Http::response($this->image(1000, 1000, [209, 61, 792, 939]))]);

        $book = Book::factory()->create(['thumbnail' => self::COVER]);

        $this->assertSame('https://m.media-amazon.com/images/I/61sj7GyrUqL._CR209,61,583,878_.jpg', $book->fresh()->thumbnail);
    }
}
