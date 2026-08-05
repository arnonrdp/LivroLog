<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Tests for SocialMediaCrawlerMiddleware, which serves server-rendered Open Graph
 * HTML to link-preview bots (they do not execute JS, so the SPA shell is useless
 * to them). Covers the book branch added for issue #1211 plus the profile and
 * homepage branches that share the same HTML builder.
 */
class SocialMediaCrawlerTest extends TestCase
{
    use RefreshDatabase;

    private const CRAWLER = ['User-Agent' => 'facebookexternalhit/1.1'];

    public function test_crawler_on_book_route_returns_og_html(): void
    {
        $book = Book::factory()->create([
            'title' => 'O Rei Leao',
            'authors' => 'Bicho Esperto',
        ]);

        $response = $this->get("/books/{$book->id}", self::CRAWLER);

        $response->assertStatus(200);
        $this->assertStringContainsString('text/html', $response->headers->get('Content-Type'));
        $response->assertSee('<meta property="og:type" content="book">', false);
        $response->assertSee('O Rei Leao', false);
        $response->assertSee('Bicho Esperto', false);
        $response->assertSee('<meta name="twitter:card" content="summary_large_image">', false);
    }

    public function test_crawler_on_book_route_includes_og_image_endpoint(): void
    {
        $book = Book::factory()->create();

        $response = $this->get("/books/{$book->id}", self::CRAWLER);

        $expected = rtrim(config('app.url'), '/')."/books/{$book->id}/og-image";
        $response->assertSee('<meta property="og:image" content="'.$expected, false);
        $response->assertSee('<meta property="og:image:width" content="1200">', false);
        $response->assertSee('<meta property="og:image:height" content="630">', false);
    }

    public function test_book_meta_uses_property_attribute_for_book_namespace(): void
    {
        $book = Book::factory()->create(['isbn' => '9788533939509']);

        $response = $this->get("/books/{$book->id}", self::CRAWLER);

        // book:* must be property=, not name=, or Facebook ignores it
        $response->assertSee('<meta property="book:isbn" content="9788533939509">', false);
    }

    public function test_og_query_param_forces_html_for_any_user_agent(): void
    {
        $book = Book::factory()->create();

        $response = $this->get("/books/{$book->id}?og=1");

        $response->assertStatus(200);
        $response->assertSee('<meta property="og:type" content="book">', false);
    }

    public function test_canonical_points_to_frontend_without_query_string(): void
    {
        $book = Book::factory()->create();

        $response = $this->get("/books/{$book->id}?og=1");

        $canonical = rtrim(config('app.frontend_url'), '/')."/books/{$book->id}";
        $response->assertSee('<link rel="canonical" href="'.$canonical.'">', false);
        $response->assertDontSee('canonical" href="'.$canonical.'?og=1', false);
    }

    public function test_json_client_still_gets_json_even_with_crawler_user_agent(): void
    {
        $book = Book::factory()->create();

        // Guards against a real in-app browser (WhatsApp, Telegram) matching the
        // crawler list and getting HTML where the webapp expects JSON
        $response = $this->getJson("/books/{$book->id}", self::CRAWLER);

        $response->assertStatus(200);
        $response->assertJsonPath('id', $book->id);
    }

    public function test_plain_request_without_crawler_user_agent_returns_json(): void
    {
        $book = Book::factory()->create();

        $response = $this->getJson("/books/{$book->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('id', $book->id);
    }

    public function test_unknown_book_with_crawler_user_agent_returns_404(): void
    {
        $this->get('/books/B-XXXX-XXXX', self::CRAWLER)->assertStatus(404);
    }

    public function test_book_without_cover_still_renders_og_html(): void
    {
        $book = Book::factory()->create(['thumbnail' => null]);

        $response = $this->get("/books/{$book->id}", self::CRAWLER);

        $response->assertStatus(200);
        $response->assertSee('<meta property="og:image" content="', false);
    }

    public function test_book_without_description_falls_back_to_generated_one(): void
    {
        $book = Book::factory()->create([
            'title' => 'Dom Casmurro',
            'authors' => 'Machado de Assis',
            'description' => null,
        ]);

        $response = $this->get("/books/{$book->id}", self::CRAWLER);

        $response->assertSee('Dom Casmurro by Machado de Assis. See it on LivroLog.', false);
    }

    public function test_profile_og_image_uses_configured_api_url(): void
    {
        $user = User::factory()->create(['username' => 'arnon']);

        $response = $this->get('/arnon?og=1');

        // The request may arrive proxied from the frontend host, so the image URL
        // must come from config, not from the request host
        $response->assertStatus(200);
        $response->assertSee('<meta property="og:image" content="'.rtrim(config('app.url'), '/')."/users/{$user->id}/shelf-image", false);
    }

    public function test_home_canonical_has_no_query_string(): void
    {
        $response = $this->get('/?og=1');

        $response->assertStatus(200);
        $response->assertSee('<link rel="canonical" href="'.rtrim(config('app.frontend_url'), '/').'">', false);
    }

    public function test_og_image_endpoint_returns_jpeg(): void
    {
        if (! function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD extension not available');
        }

        Storage::fake('public');
        $book = Book::factory()->create(['thumbnail' => null]);

        $response = $this->get("/books/{$book->id}/og-image");

        $response->assertStatus(200);
        $this->assertSame('image/jpeg', $response->headers->get('Content-Type'));

        $size = getimagesizefromstring($response->getContent());
        $this->assertSame([1200, 630], [$size[0], $size[1]]);
    }
}
