<?php

namespace App\Providers;

use App\Events\BookCreated;
use App\Listeners\EnrichBookWithAmazon;
use App\Models\Activity;
use App\Models\Book;
use App\Models\Comment;
use App\Models\Follow;
use App\Models\Review;
use App\Models\User;
use App\Models\UserBook;
use App\Observers\FollowObserver;
use App\Observers\ReviewObserver;
use App\Observers\UserBookObserver;
use App\Services\Amazon\AmazonProviderFactory;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use OpenApi\Analysers\AttributeAnnotationFactory;
use OpenApi\Analysers\DocBlockAnnotationFactory;
use OpenApi\Analysers\ReflectionAnalyser;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register Amazon Provider Factory as singleton
        $this->app->singleton(AmazonProviderFactory::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Disable data wrapping for all JSON resources
        JsonResource::withoutWrapping();

        // L5-Swagger 10+ defaults to an attributes-only analyser, which silently drops this API's
        // @OA\ docblocks. Registered here rather than in config/l5-swagger.php because the deploy
        // runs `config:cache`, which cannot serialize objects.
        config(['l5-swagger.documentations.default.scanOptions.analyser' => new ReflectionAnalyser([
            new AttributeAnnotationFactory,
            new DocBlockAnnotationFactory,
        ])]);

        // Configure morph map for polymorphic relations (Activity->subject, Notification->notifiable)
        Relation::enforceMorphMap([
            'Activity' => Activity::class,
            'Book' => Book::class,
            'Comment' => Comment::class,
            'Review' => Review::class,
            'User' => User::class,
        ]);

        // Override the reset password URL sent in email to point to frontend page
        ResetPassword::createUrlUsing(function ($user, string $token) {
            return config('app.frontend_url', config('app.url'))
                .'/reset-password?token='.$token
                .'&email='.urlencode($user->email);
        });

        // Register event listeners
        Event::listen(
            BookCreated::class,
            EnrichBookWithAmazon::class,
        );

        // Register observers for activity feed
        UserBook::observe(UserBookObserver::class);
        Review::observe(ReviewObserver::class);
        Follow::observe(FollowObserver::class);
    }
}
