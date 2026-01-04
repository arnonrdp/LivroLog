<?php

namespace App\Providers;

use App\Models\Book;
use App\Models\Follow;
use App\Models\Review;
use App\Models\User;
use App\Models\UserBook;
use App\Observers\FollowObserver;
use App\Observers\ReviewObserver;
use App\Observers\UserBookObserver;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

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

        // Configure morph map for polymorphic relations (Activity->subject)
        Relation::enforceMorphMap([
            'Book' => Book::class,
            'User' => User::class,
            'Review' => Review::class,
        ]);

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

        // Register observers for activity feed
        UserBook::observe(UserBookObserver::class);
        Review::observe(ReviewObserver::class);
        Follow::observe(FollowObserver::class);
    }
}
