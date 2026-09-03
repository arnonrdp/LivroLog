<?php

namespace Tests\Feature;

use App\Models\Book;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards how search engines see the catalogue.
 *
 * A mistake here is expensive and silent: serving noindex on a page that ranks removes it from
 * Google, and serving an indexable page with no content buys a penalty instead of traffic. Both
 * failures look fine in a browser, so they are only ever caught by assertions like these.
 */
class SeoCrawlerTest extends TestCase
{
    use RefreshDatabase;

    private const GOOGLEBOT = 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)';

    public function test_sitemap_index_points_at_paginated_children(): void
    {
        Book::factory()->count(3)->create();

        $xml = $this->get('/sitemap.xml')->assertOk()->getContent();

        $this->assertNotFalse(simplexml_load_string($xml), 'sitemap index is not well-formed XML');
        $this->assertStringContainsString('<sitemapindex', $xml);

        // a few hundred books fit in one child; the split exists so growth never re-points a
        // URL Google already crawled
        preg_match_all('#<loc>(.*?)</loc>#', $xml, $matches);
        $this->assertSame([config('app.frontend_url').'/sitemap-1.xml'], $matches[1]);
    }

    public function test_sitemap_page_lists_every_book_and_nothing_private(): void
    {
        Book::factory()->count(3)->create();

        $response = $this->get('/sitemap-1.xml');

        $response->assertOk();
        $this->assertStringContainsString('xml', (string) $response->headers->get('Content-Type'));

        $xml = $response->getContent();
        $this->assertNotFalse(simplexml_load_string($xml), 'sitemap page is not well-formed XML');

        preg_match_all('#<loc>(.*?)</loc>#', $xml, $matches);
        $locs = $matches[1];

        // one entry per book, plus the homepage on the first page
        $this->assertCount(4, $locs);

        foreach (Book::pluck('id') as $id) {
            $this->assertContains(config('app.frontend_url').'/books/'.$id, $locs);
        }

        // the static file this replaced shipped an unsubstituted route pattern and four
        // authenticated routes; none of them may come back
        $this->assertEmpty(preg_grep('#:username#', $locs));
        $this->assertEmpty(preg_grep('#/(home|add|people|settings)/?$#', $locs));

        // Google ignores both, so emitting them is noise
        $this->assertStringNotContainsString('<changefreq>', $xml);
        $this->assertStringNotContainsString('<priority>', $xml);
    }

    public function test_sitemap_page_beyond_the_catalogue_is_404(): void
    {
        Book::factory()->count(3)->create();

        $this->get('/sitemap-2.xml')->assertNotFound();
    }

    public function test_book_page_is_indexable_and_carries_the_book_content(): void
    {
        $book = Book::factory()->create([
            'title' => 'O Nome do Vento',
            'authors' => 'Patrick Rothfuss',
            'description' => 'Um relato em primeira pessoa sobre um jovem que se torna lendario.',
        ]);

        $html = $this->withHeader('User-Agent', self::GOOGLEBOT)
            ->get('/books/'.$book->id)
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('noindex', $html);
        $this->assertStringNotContainsString('Redirecionando...', $html);

        $this->assertStringContainsString('<article>', $html);
        $this->assertStringContainsString('<h1>O Nome do Vento</h1>', $html);
        $this->assertStringContainsString('Patrick Rothfuss', $html);
        $this->assertStringContainsString('jovem que se torna lendario', $html);
        $this->assertStringContainsString('rel="canonical"', $html);
    }

    public function test_labels_follow_the_book_language_not_the_crawler(): void
    {
        // Googlebot sends no Accept-Language, so without this the whole Brazilian catalogue
        // would be labelled in English on pages that declare lang="pt-BR".
        $book = Book::factory()->create(['language' => 'pt', 'authors' => 'Harlan Coben']);

        $html = $this->withHeader('User-Agent', self::GOOGLEBOT)
            ->get('/books/'.$book->id)
            ->getContent();

        $this->assertStringContainsString('por Harlan Coben', $html);
        $this->assertStringNotContainsString('by Harlan Coben', $html);
    }

    public function test_book_page_emits_valid_book_json_ld_without_borrowed_ratings(): void
    {
        $book = Book::factory()->create([
            'title' => 'O Nome do Vento',
            'authors' => 'Patrick Rothfuss',
            'isbn' => '9788599296554',
            'publisher' => 'Arqueiro',
            'page_count' => 656,
            'language' => 'pt',
            'amazon_rating' => 4.8,
            'amazon_rating_count' => 12000,
        ]);

        $html = $this->withHeader('User-Agent', self::GOOGLEBOT)
            ->get('/books/'.$book->id)
            ->getContent();

        preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $matches);
        $this->assertNotEmpty($matches, 'no JSON-LD block emitted');

        $data = json_decode($matches[1], true);
        $this->assertIsArray($data, 'JSON-LD is not valid JSON');

        $this->assertSame('https://schema.org', $data['@context']);
        $this->assertSame('Book', $data['@type']);
        $this->assertSame('O Nome do Vento', $data['name']);
        $this->assertSame(['@type' => 'Person', 'name' => 'Patrick Rothfuss'], $data['author']);
        $this->assertSame('9788599296554', $data['isbn']);
        $this->assertSame(656, $data['numberOfPages']);
        $this->assertSame('Arqueiro', $data['publisher']['name']);

        // Google: "Don't aggregate reviews or ratings from other websites." The Amazon rating is
        // on this record and must never reach the markup.
        $this->assertArrayNotHasKey('aggregateRating', $data);
        $this->assertStringNotContainsString('4.8', $matches[1]);
    }

    public function test_book_title_is_escaped_in_the_rendered_body(): void
    {
        $book = Book::factory()->create(['title' => 'Livro <script>alert(1)</script>']);

        $html = $this->withHeader('User-Agent', self::GOOGLEBOT)
            ->get('/books/'.$book->id)
            ->getContent();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
    }

    public function test_pages_without_a_rendered_body_stay_out_of_the_index(): void
    {
        // The homepage and profile renders are still meta-only stubs. They are indexed today
        // through the SPA shell, so the API copy must keep telling crawlers to ignore it —
        // dropping noindex here would deindex pages that currently rank.
        $html = $this->withHeader('User-Agent', self::GOOGLEBOT)
            ->get('/')
            ->getContent();

        $this->assertStringContainsString('noindex', $html);
    }
}
