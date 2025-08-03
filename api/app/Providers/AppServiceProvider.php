<?php

namespace App\Providers;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\ServiceProvider;
use Illuminate\Auth\Notifications\ResetPassword;

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
            return env('APP_FRONTEND_URL', config('app.url'))
                . '/reset-password?token=' . $token
                . '&email=' . urlencode($user->email);
        });
    }
}
