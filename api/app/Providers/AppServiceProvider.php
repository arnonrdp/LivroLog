<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Disable data wrapping for all JSON resources
        JsonResource::withoutWrapping();

        // Override the reset password URL sent in email to point to frontend page
        ResetPassword::createUrlUsing(function ($user, string $token) {
            return config('app.frontend_url', config('app.url'))
                .'/reset-password?token='.$token
                .'&email='.urlencode($user->email);
        });

        // Register event listeners
        Event::listen(
            \App\Events\BookCreated::class,
            \App\Listeners\EnrichBookWithAmazon::class,
        );
    }
}
