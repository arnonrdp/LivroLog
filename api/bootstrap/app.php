<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: '', // Remove /api prefix since we're using api.domain.com subdomain
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // Load broadcast channels without registering default routes
            // We use a custom route in api.php with auth:sanctum middleware instead
            require __DIR__.'/../routes/channels.php';
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
            'auth.optional' => \App\Http\Middleware\OptionalAuth::class,
            'social.crawler' => \App\Http\Middleware\SocialMediaCrawlerMiddleware::class,
        ]);

        // Add security headers to all requests
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);

        // Add social media crawler middleware to web routes
        $middleware->web(append: [
            \App\Http\Middleware\SocialMediaCrawlerMiddleware::class,
        ]);
    })
    ->withSchedule(function (Schedule $schedule): void {
        // Daily database backup at 3:00 AM
        $schedule->command('backup:database')->dailyAt('03:00');

        // Run queue worker every minute to process jobs
        $schedule->command('queue:work --tries=3 --timeout=60 --sleep=3 --max-jobs=10 --stop-when-empty')
            ->everyMinute()
            ->withoutOverlapping()
            ->runInBackground();
    })
    ->withExceptions(function (): void {
        //
    })->create();
