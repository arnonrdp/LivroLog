<?php

use App\Models\Book;
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
// Sitemap for search engines. Generated from the catalogue, so adding a book adds a URL and
// nothing here is ever maintained by hand. Registered before the /{username} catch-all, whose
// pattern allows dots and would otherwise swallow "sitemap.xml".
Route::get('/sitemap.xml', function () {
    $xml = Cache::remember('sitemap.xml', 3600, function () {
        $frontend = rtrim(config('app.frontend_url'), '/');

        // Only pages a crawler can actually read are listed. The authenticated routes that
        // used to sit in the static file were dead weight, and so was the literal "/:username".
        $urls = ['<url><loc>'.htmlspecialchars($frontend).'</loc><changefreq>daily</changefreq><priority>1.0</priority></url>'];

        Book::query()
            ->select('id', 'updated_at')
            ->orderBy('id')
            ->chunk(500, function ($books) use (&$urls, $frontend) {
                foreach ($books as $book) {
                    $loc = htmlspecialchars($frontend.'/books/'.rawurlencode($book->id));
                    $lastmod = $book->updated_at?->toAtomString();
                    $urls[] = '<url><loc>'.$loc.'</loc>'.($lastmod ? '<lastmod>'.$lastmod.'</lastmod>' : '').'<changefreq>weekly</changefreq></url>';
                }
            });

        return '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n"
            .implode("\n", $urls)."\n"
            .'</urlset>';
    });

    return response($xml, 200, ['Content-Type' => 'application/xml; charset=utf-8']);
});

Route::get('/{username}', function (string $username) {
    // This route is handled by SocialMediaCrawlerMiddleware
    // For regular users, it should redirect to frontend
    return redirect(config('app.frontend_url').'/'.$username);
})->where('username', '^(?!documentation$|docs$|api$|login$|register$|reset\-password$)[a-zA-Z0-9_\-\.]+$');
