<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', env('APP_URL').'/auth/google/callback'),
    ],

    'google_books' => [
        'api_key' => env('GOOGLE_BOOKS_API_KEY'),
        'location_ip' => env('GOOGLE_BOOKS_LOCATION_IP', '177.37.0.0'),
    ],

    'open_library' => [
        'enabled' => env('OPEN_LIBRARY_ENABLED', true),
    ],

    // Amazon integration
    'amazon' => [
        // Legacy PA-API 5.0 configuration
        'enabled' => env('AMAZON_PA_API_ENABLED', false),
        'associate_tag' => env('AMAZON_ASSOCIATE_TAG', 'livrolog01-20'),
        'pa_api_key' => env('AMAZON_PA_API_KEY'),
        'pa_secret_key' => env('AMAZON_PA_SECRET_KEY'),
        'sitestripe_enabled' => env('AMAZON_SITESTRIPE_ENABLED', false),

        // Creators API configuration (OAuth 2.0)
        'creators_api' => [
            'enabled' => env('AMAZON_CREATORS_API_ENABLED', true),
            'credential_id' => env('AMAZON_CREATORS_CREDENTIAL_ID'),
            'credential_secret' => env('AMAZON_CREATORS_CREDENTIAL_SECRET'),
            'application_id' => env('AMAZON_CREATORS_APPLICATION_ID'),
            'api_version' => '2.1',
        ],

        // Provider selection: 'creators' (OAuth 2.0) or 'pa-api' (AWS Signature)
        'provider' => env('AMAZON_API_PROVIDER', 'creators'),

        // Regional configurations with hardcoded associate tags
        'regions' => [
            'BR' => [
                'domain' => 'amazon.com.br',
                'search_url' => 'https://www.amazon.com.br/s',
                'host' => 'webservices.amazon.com.br',
                'marketplace' => 'www.amazon.com.br',
                'tag' => 'livrolog01-20', // Official Brazil Associates tag
                'language' => 'pt-BR',
            ],
            'US' => [
                'domain' => 'amazon.com',
                'search_url' => 'https://www.amazon.com/s',
                'host' => 'webservices.amazon.com',
                'marketplace' => 'www.amazon.com',
                'tag' => 'livrolog-20', // US Associates tag
                'language' => 'en-US',
            ],
            'UK' => [
                'domain' => 'amazon.co.uk',
                'search_url' => 'https://www.amazon.co.uk/s',
                'host' => 'webservices.amazon.co.uk',
                'marketplace' => 'www.amazon.co.uk',
                'tag' => 'livrolog-20', // Use existing tag until regional one is created
                'language' => 'en-GB',
            ],
            'DE' => [
                'domain' => 'amazon.de',
                'search_url' => 'https://www.amazon.de/s',
                'host' => 'webservices.amazon.de',
                'marketplace' => 'www.amazon.de',
                'tag' => 'livrolog-20', // Use existing tag until regional one is created
                'language' => 'de-DE',
            ],
            'CA' => [
                'domain' => 'amazon.ca',
                'search_url' => 'https://www.amazon.ca/s',
                'host' => 'webservices.amazon.ca',
                'marketplace' => 'www.amazon.ca',
                'tag' => 'livrolog-20', // Use existing tag until regional one is created
                'language' => 'en-CA',
            ],
            'FR' => [
                'domain' => 'amazon.fr',
                'search_url' => 'https://www.amazon.fr/s',
                'host' => 'webservices.amazon.fr',
                'marketplace' => 'www.amazon.fr',
                'tag' => 'livrolog-20', // Use existing tag until regional one is created
                'language' => 'fr-FR',
            ],
        ],
    ],

];
