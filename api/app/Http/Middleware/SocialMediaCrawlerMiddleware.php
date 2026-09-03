<?php

namespace App\Http\Middleware;

use App\Models\Book;
use App\Models\User;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class SocialMediaCrawlerMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response|RedirectResponse)  $next
     * @return Response|RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        // Detect social media crawlers or forced OG via query (?og=1)
        $forceOg = $request->boolean('og');
        $isCrawler = $this->isSocialMediaCrawler($request);
        if ($forceOg || $isCrawler) {
            $path = $request->path();

            // Handle homepage
            if ($path === '/') {
                return $this->renderHomePage($request, $forceOg);
            }

            // Book detail pages: /books/{id}
            // Checked before the profile regex so "books" is never read as a username
            if (preg_match('#^books/([A-Za-z0-9_-]+)/?$#', $path, $matches)) {
                // Never hijack real API clients: the webapp sends Accept: application/json
                if ($request->expectsJson() && ! $forceOg) {
                    return $next($request);
                }

                $book = (new Book)->resolveRouteBinding($matches[1]);

                // Unknown book falls through to the API's 404, which nginx turns
                // into the SPA shell for the crawler
                return $book
                    ? $this->renderBookPage($book, $request, $forceOg)
                    : $next($request);
            }

            // Check if this is a user profile route
            // Match patterns like /username or /username/
            if (preg_match('/^([a-zA-Z0-9_\-\.]+)\/?$/', $path, $matches)) {
                $username = $matches[1];

                // Skip API routes and common frontend routes
                if (in_array($username, ['api', 'login', 'register', 'reset-password', 'documentation'])) {
                    return $next($request);
                }

                // Try to find the user
                $user = User::where('username', $username)->first();

                if ($user) {
                    return $this->renderUserProfilePage($user, $request, $forceOg);
                }
            }
        }

        return $next($request);
    }

    /**
     * Check if the request is from a social media crawler
     */
    private function isSocialMediaCrawler(Request $request): bool
    {
        $userAgent = strtolower($request->header('User-Agent', ''));

        $crawlers = [
            'facebookexternalhit', 'facebookcatalog',
            'twitterbot',
            'linkedinbot',
            'whatsapp',
            'telegrambot', 'telegram',
            'slackbot', 'slack-imgproxy', 'slackbot-linkexpanding',
            'discordbot',
            'skypeuripreview',
            'applebot',
            'googlebot', 'google-inspectiontool', 'apis-google',
            'bingbot',
            'yahoo',
            'pinterestbot', 'pinterest',
            'redditbot',
            'embedly', 'iframely', 'opengraph',
            'vkshare', 'qwantify', 'bitlybot', 'bufferbot',
            'duckduckbot', 'baiduspider', 'yandexbot',
            'lighthouse', 'pagespeed',
        ];

        foreach ($crawlers as $crawler) {
            if (strpos($userAgent, $crawler) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Render homepage with dynamic meta tags
     */
    private function renderHomePage(Request $request, bool $forceOg): Response
    {
        $frontend = rtrim(config('app.frontend_url'), '/');
        $currentUrl = $frontend; // canonical to frontend
        $imageUrl = $frontend.'/screenshot-web.jpg';

        // Basic i18n based on Accept-Language
        $lang = strtolower($request->header('Accept-Language', ''));
        $isPt = str_contains($lang, 'pt');
        $title = 'LivroLog';
        $description = $isPt
            ? 'O lugar perfeito para catalogar seus livros. Adicione sua estante e veja o que seus amigos estão lendo.'
            : "A place for you to organize everything you've read. Add your books and see what your friends are reading.";

        $html = $this->generateHtmlWithMetaTags([
            'title' => $title,
            'description' => $description,
            'og:type' => 'website',
            'og:url' => $currentUrl,
            'og:title' => $title,
            'og:description' => $description,
            'og:image' => $imageUrl,
            'og:image:alt' => 'Homepage of the LivroLog website with a bookcase with several book covers',
            'og:site_name' => 'LivroLog',
            'og:locale' => $isPt ? 'pt_BR' : 'en_US',
            'og:image:width' => '1200',
            'og:image:height' => '630',
            'twitter:card' => 'summary_large_image',
            'twitter:title' => $title,
            'twitter:description' => $description,
            'twitter:image' => $imageUrl,
        ], $forceOg);

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=utf-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    /**
     * Render user profile page with dynamic meta tags
     */
    private function renderUserProfilePage(User $user, Request $request, bool $forceOg): Response
    {
        // Load user's books for count and shelf image
        $user->load(['books' => function ($query) {
            $query->orderBy('pivot_added_at', 'desc')->withPivot('is_private');
            // Only count public books for meta description
            $query->wherePivot('is_private', false);
        }]);

        $booksCount = $user->books->count();
        // Sanitize user-controlled fields to prevent XSS in reflected HTML
        $rawShelfName = $user->shelf_name ?: $user->display_name;
        $shelfName = trim(strip_tags((string) $rawShelfName));
        $safeDisplayName = trim(strip_tags((string) $user->display_name));
        // Canonical URL to frontend profile
        $frontend = rtrim(config('app.frontend_url'), '/');
        $currentUrl = $frontend.'/'.rawurlencode($user->username);
        // Version for cache-busting
        $versionTs = DB::table('users_books')
            ->where('user_id', $user->id)
            ->max('updated_at');
        $version = $versionTs ? (is_string($versionTs) ? (string) strtotime($versionTs) : (string) strtotime((string) $versionTs)) : (string) time();
        // Always build image URLs from the API host: this request may have been
        // proxied from the frontend host, so the request host is not usable here.
        $imageUrl = rtrim(config('app.url'), '/')."/users/{$user->id}/shelf-image?v={$version}";

        // i18n based on Accept-Language
        $lang = strtolower($request->header('Accept-Language', ''));
        $isPt = str_contains($lang, 'pt');

        $description = $booksCount > 0
            ? ($isPt ? "Veja os {$booksCount} livros favoritos de {$safeDisplayName}" : "See {$safeDisplayName}'s top {$booksCount} books")
            : ($isPt ? "Estante de {$safeDisplayName} no LivroLog" : "{$safeDisplayName}'s bookshelf on LivroLog");

        $title = "{$shelfName} - LivroLog";

        $html = $this->generateHtmlWithMetaTags([
            'title' => $title,
            'description' => $description,
            'og:type' => 'profile',
            'og:url' => $currentUrl,
            'og:title' => $title,
            'og:description' => $description,
            'og:image' => $imageUrl,
            'og:image:alt' => $isPt ? "Estante de livros de {$safeDisplayName} no LivroLog" : "{$safeDisplayName}'s bookshelf on LivroLog",
            'og:site_name' => 'LivroLog',
            'og:locale' => $isPt ? 'pt_BR' : 'en_US',
            'og:image:width' => '1200',
            'og:image:height' => '630',
            'twitter:card' => 'summary_large_image',
            'twitter:title' => $title,
            'twitter:description' => $description,
            'twitter:image' => $imageUrl,
            'profile:first_name' => $safeDisplayName,
            'profile:username' => $user->username,
        ], $forceOg);

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=utf-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    /**
     * Render book detail page with dynamic meta tags
     */
    private function renderBookPage(Book $book, Request $request, bool $forceOg): Response
    {
        $frontend = rtrim(config('app.frontend_url'), '/');
        $currentUrl = $frontend.'/books/'.rawurlencode($book->id);

        // Image comes from the API host and is busted whenever the book changes
        $version = $book->updated_at ? $book->updated_at->timestamp : time();
        $imageUrl = rtrim(config('app.url'), '/').'/books/'.rawurlencode($book->id)."/og-image?v={$version}";

        // i18n based on Accept-Language
        $lang = strtolower($request->header('Accept-Language', ''));
        $isPt = str_contains($lang, 'pt');

        // Sanitize catalog fields to prevent XSS in reflected HTML
        $bookTitle = trim(strip_tags((string) $book->title));
        $subtitle = trim(strip_tags((string) $book->subtitle));
        $author = trim(strip_tags((string) $book->authors));
        $fullTitle = $subtitle !== '' ? "{$bookTitle}: {$subtitle}" : $bookTitle;
        $title = $author !== '' ? "{$fullTitle} - {$author} | LivroLog" : "{$fullTitle} | LivroLog";

        // sanitized_description still carries markdown emphasis markers; meta tags want plain text
        $description = trim(preg_replace('/\s+/', ' ', str_replace(['**', '__', '~~'], '', (string) $book->sanitized_description)));
        if ($description === '') {
            $description = $isPt
                ? ($author !== '' ? "{$fullTitle}, de {$author}. Veja no LivroLog." : "{$fullTitle} no LivroLog.")
                : ($author !== '' ? "{$fullTitle} by {$author}. See it on LivroLog." : "{$fullTitle} on LivroLog.");
        } elseif (mb_strlen($description) > 200) {
            $description = mb_substr($description, 0, 197).'...';
        }

        $metaData = [
            'title' => $title,
            'description' => $description,
            'og:type' => 'book',
            'og:url' => $currentUrl,
            'og:title' => $title,
            'og:description' => $description,
            'og:image' => $imageUrl,
            'og:image:alt' => $isPt ? "Capa de {$fullTitle}" : "Cover of {$fullTitle}",
            'og:site_name' => 'LivroLog',
            'og:locale' => $isPt ? 'pt_BR' : 'en_US',
            'og:image:width' => '1200',
            'og:image:height' => '630',
            'twitter:card' => 'summary_large_image',
            'twitter:title' => $title,
            'twitter:description' => $description,
            'twitter:image' => $imageUrl,
        ];

        if ($author !== '') {
            $metaData['book:author'] = $author;
        }
        if ($book->isbn) {
            $metaData['book:isbn'] = $book->isbn;
        }
        if ($book->published_date) {
            $metaData['book:release_date'] = $book->published_date->format('Y-m-d');
        }

        // Labels follow the book, not the crawler. Googlebot sends no Accept-Language, so a
        // Portuguese book would otherwise be labelled in English on a page declaring lang="pt-BR".
        $bodyIsPt = $book->language ? str_starts_with(strtolower((string) $book->language), 'pt') : $isPt;

        $body = $this->renderBookBody($book, $fullTitle, $author, $currentUrl, $imageUrl, $bodyIsPt);

        return response($this->generateHtmlWithMetaTags($metaData, $forceOg, $body), 200, [
            'Content-Type' => 'text/html; charset=utf-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    /**
     * Readers' own reviews, rendered on the page.
     *
     * This is not decoration. Google requires that a rating marked up with AggregateRating be
     * visible to the user on that page, so this is what makes the JSON-LD rating legitimate.
     * It is also what separates the page from a thin affiliate page: without it the content is
     * a publisher blurb plus a buy button, which the spam policy names explicitly.
     */
    private function renderBookReviews(Book $book, bool $isPt, callable $e): string
    {
        $count = $book->reviews_count;

        if ($count < 1) {
            return '';
        }

        $average = round((float) $book->average_rating, 1);
        $heading = $isPt ? 'Avaliações dos leitores' : 'Reader reviews';
        $summary = $isPt
            ? "Nota média {$average} de 5, com {$count} ".($count === 1 ? 'avaliação' : 'avaliações')
            : "Rated {$average} out of 5 by {$count} ".($count === 1 ? 'reader' : 'readers');

        $items = '';
        foreach ($book->publicReviews()->with('user')->latest()->limit(5)->get() as $review) {
            $body = trim(strip_tags((string) $review->content));
            if ($body === '') {
                continue;
            }
            if (mb_strlen($body) > 400) {
                $body = mb_substr($body, 0, 397).'...';
            }

            $author = trim(strip_tags((string) ($review->user->display_name ?? $review->user->username ?? '')));
            $title = trim(strip_tags((string) $review->title));

            $items .= '<li>'
                .($title !== '' ? '<strong>'.$e($title).'</strong> ' : '')
                .'<span>'.$e(($isPt ? 'Nota ' : 'Rated ').$review->rating.'/5').'</span> '
                .($author !== '' ? '<cite>'.$e($author).'</cite> ' : '')
                .'<p>'.$e($body).'</p>'
                .'</li>';
        }

        return '<h2>'.$e($heading).'</h2><p class="rating-summary">'.$e($summary).'</p>'
            .($items !== '' ? '<ul class="reviews">'.$items.'</ul>' : '');
    }

    /**
     * Links to other books by the same author.
     *
     * Every book page was a dead end in the link graph: the only anchor in the body pointed at
     * the page itself. books.authors is a plain string column, so this is a LIKE away — the
     * Author model exists but nothing in the codebase ever writes to it.
     */
    private function renderSameAuthorLinks(Book $book, string $author, bool $isPt, callable $e): string
    {
        if ($author === '') {
            return '';
        }

        $primary = trim(explode(',', $author)[0]);
        if ($primary === '') {
            return '';
        }

        $frontend = rtrim(config('app.frontend_url'), '/');

        $others = Book::query()
            ->select('id', 'title')
            ->where('id', '!=', $book->id)
            ->where('authors', 'like', '%'.$primary.'%')
            ->orderBy('title')
            ->limit(10)
            ->get();

        if ($others->isEmpty()) {
            return '';
        }

        $items = '';
        foreach ($others as $other) {
            $href = $e($frontend.'/books/'.rawurlencode($other->id));
            $items .= '<li><a href="'.$href.'">'.$e(trim(strip_tags((string) $other->title))).'</a></li>';
        }

        $heading = $isPt ? "Outros livros de {$primary}" : "More by {$primary}";

        return '<h2>'.$e($heading).'</h2><ul class="also-by">'.$items.'</ul>';
    }

    /**
     * schema.org/Book as JSON-LD, which is what search engines read for rich results.
     *
     * Deliberately carries no aggregateRating. The ratings on hand are amazon_rating and
     * amazon_rating_count, scraped from another site, and Google is explicit: "Don't aggregate
     * reviews or ratings from other websites."
     * https://developers.google.com/search/docs/appearance/structured-data/review-snippet
     * LivroLog's own Review data could be marked up, but only once the reviews are rendered on
     * the page itself, which they are not yet.
     */
    private function bookJsonLd(Book $book, string $fullTitle, string $author, string $canonical, string $cover): string
    {
        $data = array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Book',
            'name' => $fullTitle,
            'url' => $canonical,
            'image' => $cover !== '' ? html_entity_decode($cover, ENT_QUOTES, 'UTF-8') : null,
            'isbn' => $book->isbn ?: null,
            'numberOfPages' => $book->page_count ? (int) $book->page_count : null,
            'inLanguage' => $book->language ?: null,
            'datePublished' => $book->published_date?->format('Y-m-d'),
            'description' => trim(str_replace(['**', '__', '~~'], '', (string) $book->sanitized_description)) ?: null,
        ], static fn ($value) => $value !== null && $value !== '');

        if ($author !== '') {
            $names = array_values(array_filter(array_map('trim', explode(',', $author))));
            $people = array_map(static fn (string $name) => ['@type' => 'Person', 'name' => $name], $names);
            $data['author'] = count($people) === 1 ? $people[0] : $people;
        }

        if ($book->publisher) {
            $data['publisher'] = ['@type' => 'Organization', 'name' => $book->publisher];
        }

        // LivroLog's own readers' ratings, and only these. They are rendered on the page by
        // renderBookReviews(), which is Google's condition for marking them up. Emitted only
        // when reviews exist, so a book nobody rated never carries a zero-star node.
        $reviewsCount = $book->reviews_count;

        if ($reviewsCount > 0) {
            $data['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => round((float) $book->average_rating, 1),
                'reviewCount' => $reviewsCount,
                'bestRating' => 5,
                'worstRating' => 1,
            ];
        }

        // JSON_HEX_TAG keeps a "</script>" inside any field from closing the block early
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG);

        return '<script type="application/ld+json">'.$json.'</script>';
    }

    /**
     * Build the visible page for a book.
     *
     * A crawler must be served the same thing a reader sees. Everything here is already on
     * screen in the SPA; the difference is only that this copy survives without JavaScript.
     */
    private function renderBookBody(Book $book, string $fullTitle, string $author, string $canonical, string $fallbackImage, bool $isPt): string
    {
        $e = static fn (?string $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

        $cover = $book->thumbnail ? $e($book->thumbnail) : $e($fallbackImage);
        $coverAlt = $e(($isPt ? 'Capa de ' : 'Cover of ').$fullTitle);

        $heading = $e($fullTitle);
        $byline = $author !== '' ? '<p class="author">'.$e(($isPt ? 'por ' : 'by ').$author).'</p>' : '';

        $facts = '';
        foreach ([
            ($isPt ? 'Editora' : 'Publisher') => $book->publisher,
            'ISBN' => $book->isbn,
            ($isPt ? 'Publicado em' : 'Published') => $book->published_date?->format('Y'),
            ($isPt ? 'Páginas' : 'Pages') => $book->page_count,
        ] as $label => $value) {
            if ($value !== null && $value !== '') {
                $facts .= '<dt>'.$e($label).'</dt><dd>'.$e((string) $value).'</dd>';
            }
        }
        $facts = $facts !== '' ? '<dl class="facts">'.$facts.'</dl>' : '';

        // sanitized_description carries markdown emphasis markers the SPA renders away
        $plain = trim(str_replace(['**', '__', '~~'], '', (string) $book->sanitized_description));
        $description = '';
        foreach (preg_split('/\n\s*\n/', $plain) ?: [] as $paragraph) {
            $paragraph = trim((string) $paragraph);
            if ($paragraph !== '') {
                $description .= '<p>'.$e($paragraph).'</p>';
            }
        }

        $cta = $e($isPt ? 'Ver no LivroLog' : 'See it on LivroLog');
        $safeCanonical = $e($canonical);

        $categories = '';
        foreach ((array) $book->categories as $category) {
            $category = trim(strip_tags((string) $category));
            if ($category !== '') {
                $categories .= '<li>'.$e($category).'</li>';
            }
        }
        $categories = $categories !== ''
            ? '<h2>'.$e($isPt ? 'Assuntos' : 'Subjects').'</h2><ul class="categories">'.$categories.'</ul>'
            : '';

        $reviews = $this->renderBookReviews($book, $isPt, $e);
        $alsoBy = $this->renderSameAuthorLinks($book, $author, $isPt, $e);
        $jsonLd = $this->bookJsonLd($book, $fullTitle, $author, $canonical, $cover);

        return <<<BODY
    {$jsonLd}
    <article>
        <img src="{$cover}" alt="{$coverAlt}" width="240" />
        <h1>{$heading}</h1>
        {$byline}
        {$facts}
        {$description}
        {$categories}
        {$reviews}
        {$alsoBy}
        <p><a href="{$safeCanonical}">{$cta}</a></p>
    </article>
BODY;
    }

    /**
     * Generate HTML with dynamic meta tags
     */
    private function generateHtmlWithMetaTags(array $metaData, bool $forceOg = false, ?string $bodyHtml = null): string
    {
        $metaTags = '';

        foreach ($metaData as $property => $content) {
            if ($property === 'title') {
                $metaTags .= '<title>'.htmlspecialchars($content).'</title>'."\n    ";

                continue;
            }

            if ($property === 'description') {
                $metaTags .= '<meta name="description" content="'.htmlspecialchars($content).'">'."\n    ";

                continue;
            }

            if (str_starts_with($property, 'og:') || str_starts_with($property, 'profile:') || str_starts_with($property, 'book:')) {
                $metaTags .= '<meta property="'.$property.'" content="'.htmlspecialchars($content).'">'."\n    ";
            } else {
                $metaTags .= '<meta name="'.$property.'" content="'.htmlspecialchars($content).'">'."\n    ";
            }
        }

        // Canonical and redirect target: the frontend URL each render method already
        // built for og:url, so query strings such as ?og=1 never leak into it
        $canonicalUrl = $metaData['og:url'] ?? rtrim(config('app.frontend_url'), '/');
        $safeCanonical = htmlspecialchars($canonicalUrl, ENT_QUOTES);
        $metaTags .= '<link rel="canonical" href="'.$safeCanonical.'">'."\n    ";

        // A page is indexable exactly when it carries real content. The redirect stub below
        // must never reach the index, so the two decisions are deliberately tied together:
        // give a render method a body and it becomes indexable, forget one and it cannot.
        if ($bodyHtml === null) {
            $metaTags .= '<meta name="robots" content="noindex, follow">'."\n    ";
        }

        $includeClientRedirect = ! $forceOg; // do not include client-side redirect when forcing OG

        $redirectScript = '';
        if ($includeClientRedirect) {
            $redirectScript = <<<JS
    <script>
        // Redirect to frontend for regular users
        if (!navigator.userAgent.match(/facebookexternalhit|twitterbot|linkedinbot|whatsapp|telegrambot|slackbot|discordbot|skypeuripreview|applebot|googlebot|bingbot|yahoo|pinterest|redditbot/i)) {
            window.location.href = '{$safeCanonical}';
        }
    </script>
JS;
        }

        $pageBody = $bodyHtml ?? <<<STUB
    <div style="text-align: center; padding: 50px; font-family: Arial, sans-serif;">
        <h1>LivroLog</h1>
        <p>Redirecionando...</p>
        <p><a href="{$safeCanonical}">Clique aqui se não for redirecionado automaticamente</a></p>
    </div>
STUB;

        return <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Dynamic Meta Tags -->
    {$metaTags}

    <!-- Redirect to frontend -->
    {$redirectScript}

    <!-- Fallback redirect -->
    <noscript>
        <meta http-equiv="refresh" content="0;url={$safeCanonical}">
    </noscript>
</head>
<body>
{$pageBody}
</body>
</html>
HTML;
    }
}
