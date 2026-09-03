<?php

use App\Models\Book;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Route;

// Healthcheck endpoint for Docker containers
// Comments in English only
Route::get('/healthz', function () {
    // Simple health check that doesn't depend on DB/Redis
    // This ensures deployment succeeds even if DB is not ready yet
    return response()->json([
        'status' => 'healthy',
        'timestamp' => now()->toIso8601String(),
        'app' => config('app.name'),
        'env' => config('app.env'),
    ], 200);
});

// Serve Swagger JSON directly to avoid environment path issues
Route::get('/docs/api-docs.json', function () {
    $jsonPath = storage_path('api-docs/api-docs.json');
    if (! file_exists($jsonPath)) {
        try {
            Artisan::call('l5-swagger:generate');
        } catch (Throwable $e) {
            // ignore and fall through
        }
    }
    if (! file_exists($jsonPath)) {
        return response()->json(['error' => 'spec_not_found'], 404);
    }

    return response()->file($jsonPath, [
        'Content-Type' => 'application/json',
        'Cache-Control' => 'no-cache',
    ]);
});

// Provide a named /docs route that L5 Swagger expects
Route::get('/docs', function () {
    $jsonPath = storage_path('api-docs/api-docs.json');
    if (! file_exists($jsonPath)) {
        try {
            Artisan::call('l5-swagger:generate');
        } catch (Throwable $e) {
            // ignore
        }
    }
    if (! file_exists($jsonPath)) {
        return response()->json(['error' => 'spec_not_found'], 404);
    }

    return response()->file($jsonPath, [
        'Content-Type' => 'application/json',
        'Cache-Control' => 'no-cache',
    ]);
})->name('l5-swagger.default.docs');

Route::get('/', function () {
    return redirect('/documentation');
})->middleware('social.crawler');

// Catch-all route for user profiles - must be last
// This will handle routes like /arnon, /wanderson, etc.
// Sitemap, split into an index plus paginated children from the outset.
//
// The catalogue grows as books are ingested, and re-pointing a sitemap URL that Google has
// already crawled costs a recrawl cycle; doing it before anything is indexed costs nothing.
// At a few hundred books the index has exactly one child, so behaviour today is unchanged.
//
// Google ignores <changefreq> and <priority> and uses <lastmod> only when it is consistently
// accurate, so only <lastmod> is emitted:
// https://developers.google.com/search/docs/crawling-indexing/sitemaps/build-sitemap
//
// Both routes are registered before the /{username} catch-all, whose pattern allows dots and
// hyphens and would otherwise swallow "sitemap.xml" and "sitemap-1.xml".
$sitemapPageSize = 10000;

Route::get('/sitemap.xml', function () use ($sitemapPageSize) {
    $xml = Cache::remember('sitemap.index', 3600, function () use ($sitemapPageSize) {
        $frontend = rtrim(config('app.frontend_url'), '/');
        $pages = max(1, (int) ceil(Book::count() / $sitemapPageSize));

        $entries = '';
        for ($page = 1; $page <= $pages; $page++) {
            // max(updated_at) over just this page's slice, so a child is only re-fetched when
            // one of its own books changed
            $slice = Book::query()->select('updated_at')->orderBy('id')
                ->skip(($page - 1) * $sitemapPageSize)->take($sitemapPageSize);
            $lastmod = DB::query()->fromSub($slice, 'slice')->max('updated_at');

            $entries .= '<sitemap><loc>'.htmlspecialchars($frontend.'/sitemap-'.$page.'.xml').'</loc>'
                .($lastmod ? '<lastmod>'.Carbon::parse($lastmod)->toAtomString().'</lastmod>' : '')
                .'</sitemap>'."\n";
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n"
            .$entries
            .'</sitemapindex>';
    });

    return response($xml, 200, ['Content-Type' => 'application/xml; charset=utf-8']);
});

Route::get('/sitemap-{page}.xml', function (int $page) use ($sitemapPageSize) {
    abort_if($page < 1, 404);

    $xml = Cache::remember("sitemap.page.{$page}", 3600, function () use ($page, $sitemapPageSize) {
        $frontend = rtrim(config('app.frontend_url'), '/');

        $books = Book::query()->select('id', 'updated_at')->orderBy('id')
            ->skip(($page - 1) * $sitemapPageSize)->take($sitemapPageSize)->get();

        if ($books->isEmpty()) {
            return '';
        }

        // Only pages a crawler can actually read are listed. The authenticated routes that used
        // to sit in the static file were dead weight, and so was the literal "/:username".
        $urls = $page === 1
            ? ['<url><loc>'.htmlspecialchars($frontend).'</loc></url>']
            : [];

        foreach ($books as $book) {
            $loc = htmlspecialchars($frontend.'/books/'.rawurlencode($book->id));
            $lastmod = $book->updated_at?->toAtomString();
            $urls[] = '<url><loc>'.$loc.'</loc>'.($lastmod ? '<lastmod>'.$lastmod.'</lastmod>' : '').'</url>';
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n"
            .implode("\n", $urls)."\n"
            .'</urlset>';
    });

    abort_if($xml === '', 404);

    return response($xml, 200, ['Content-Type' => 'application/xml; charset=utf-8']);
})->whereNumber('page');

Route::get('/{username}', function (string $username) {
    // This route is handled by SocialMediaCrawlerMiddleware
    // For regular users, it should redirect to frontend
    return redirect(config('app.frontend_url').'/'.$username);
})->where('username', '^(?!documentation$|docs$|api$|login$|register$|reset\-password$)[a-zA-Z0-9_\-\.]+$');
