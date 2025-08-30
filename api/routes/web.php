<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/documentation');
})->middleware('social.crawler');

// Catch-all route for user profiles - must be last
// This will handle routes like /arnon, /wanderson, etc.
Route::get('/{username}', function (string $username) {
    // This route is handled by SocialMediaCrawlerMiddleware
    // For regular users, it should redirect to frontend
    return redirect(config('app.frontend_url') . '/' . $username);
})->where('username', '[a-zA-Z0-9_\-\.]+');
