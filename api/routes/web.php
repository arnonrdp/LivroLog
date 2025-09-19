<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

// Healthcheck endpoint for Docker containers
// Comments in English only
Route::get('/healthz', function () {
    try {
        // Check database connection
        DB::connection()->getPdo();
        
        // Check Redis connection if configured
        if (config('database.redis.default.host')) {
            Redis::ping();
        }
        
        return response('healthy', 200);
    } catch (Exception $e) {
        return response('unhealthy: ' . $e->getMessage(), 500);
    }
});

// Serve Swagger JSON directly to avoid environment path issues
Route::get('/docs/api-docs.json', function () {
    $jsonPath = storage_path('api-docs/api-docs.json');
    if (!file_exists($jsonPath)) {
        try {
            Artisan::call('l5-swagger:generate');
        } catch (\Throwable $e) {
            // ignore and fall through
        }
    }
    if (!file_exists($jsonPath)) {
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
    if (!file_exists($jsonPath)) {
        try {
            Artisan::call('l5-swagger:generate');
        } catch (\Throwable $e) {
            // ignore
        }
    }
    if (!file_exists($jsonPath)) {
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
Route::get('/{username}', function (string $username) {
    // This route is handled by SocialMediaCrawlerMiddleware
    // For regular users, it should redirect to frontend
    return redirect(config('app.frontend_url') . '/' . $username);
})->where('username', '^(?!documentation$|docs$|api$|login$|register$|reset\-password$)[a-zA-Z0-9_\-\.]+$');
