<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: '', // Remove /api prefix since we're using api.domain.com subdomain
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
        ]);

        // Add security headers to all requests
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);
    })
    ->withSchedule(function (Schedule $schedule): void {
        // Daily database backup at 3:00 AM
        $schedule->command('backup:database')->dailyAt('03:00');
    })
    ->withExceptions(function (): void {
        //
    })->create();
