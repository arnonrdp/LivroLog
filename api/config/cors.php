<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => [
        env('APP_FRONTEND_URL', 'http://localhost:8001'),
        env('APP_URL', 'http://localhost:8000'),
        'http://0.0.0.0:8001',
        'http://127.0.0.1:8001',
        'http://localhost:3000',
        'http://localhost:5173',
        'https://127.0.0.1:8001',
        'https://dev.livrolog.com',
        'https://livrolog.com',
        'https://www.livrolog.com',
        'https://localhost:8001',
    ],

    'allowed_origins_patterns' => [
        '#^https?://(localhost|127\.0\.0\.1|0\.0\.0\.0)(:[0-9]+)?$#',
    ],

    // Be permissive on headers in dev to avoid preflight/header mismatch
    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
