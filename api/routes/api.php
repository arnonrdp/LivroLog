<?php

use App\Http\Controllers\Api\ActivityInteractionController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookController;
use App\Http\Controllers\Api\EmailVerificationController;
use App\Http\Controllers\Api\FeedController;
use App\Http\Controllers\Api\FollowController;
use App\Http\Controllers\Api\GoodReadsImportController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\ImageProxyController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\UserBookController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\ReviewController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Auth routes (public)
// Rate limit: 60/min for local/testing, 5/min for production
$authRateLimit = app()->environment('local', 'testing') ? 'throttle:60,1' : 'throttle:5,1';
Route::middleware([$authRateLimit])->group(function () {
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/auth/reset-password', [AuthController::class, 'resetPassword']);
});

// Google OAuth routes (public)
Route::middleware(['throttle:10,1'])->group(function () {
    Route::get('/auth/google', [AuthController::class, 'redirectToGoogle']);
    Route::get('/auth/google/callback', [AuthController::class, 'handleGoogleCallback']);
    Route::post('/auth/google', [AuthController::class, 'googleSignIn']);
});

// Email verification routes
Route::get('/auth/verify-email', [EmailVerificationController::class, 'verifyEmail'])->name('verification.verify');

// Logout route (public - idempotent, always succeeds)
Route::post('/auth/logout', [AuthController::class, 'logout']);

// Public routes
Route::get('/health', [HealthController::class, 'index']);

// Public book routes
Route::get('/books', [BookController::class, 'index']);
Route::post('/books', [BookController::class, 'store']);
Route::get('/books/{book}', [BookController::class, 'show']);
Route::get('/books/{book}/stats', [BookController::class, 'stats']);
Route::get('/books/{book}/reviews', [ReviewController::class, 'bookReviews']);

// Image proxy (public for CORS issues)
Route::get('/image-proxy', [ImageProxyController::class, 'proxy']);
Route::get('/image-proxy/stats', [ImageProxyController::class, 'stats']);

// User shelf images for social sharing (public)
Route::get('/users/{id}/shelf-image', [UserController::class, 'shelfImage']);

// Public user routes with optional authentication
// These routes are public but need user context to check profile ownership/following status
Route::middleware('auth.optional')->group(function () {
    Route::get('/users/{username}/stats', [UserController::class, 'stats']);
    Route::get('/users/{identifier}/activities', [FeedController::class, 'userActivities']);
    Route::get('/users/{identifier}', [UserController::class, 'show']);
});

// Protected routes (require authentication)
Route::middleware('auth:sanctum')->group(function () {
    // Admin-only routes
    Route::middleware('admin')->group(function () {
        // Admin dashboard
        Route::get('/admin/users', [AdminController::class, 'users']);
        Route::get('/admin/books', [AdminController::class, 'books']);
        Route::post('/admin/books/{book}/enrich-amazon', [AdminController::class, 'enrichBookWithAmazon']);

        // Merge authors
        Route::post('/authors/merge', [\App\Http\Controllers\Api\AuthorMergeController::class, 'merge']);

        // User management (restricted to admins)
        Route::post('/users', [UserController::class, 'store']);
        Route::put('/users/{id}', [UserController::class, 'update']);
        Route::delete('/users/{id}', [UserController::class, 'destroy']);
    });

    // Auth
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::put('/auth/me', [AuthController::class, 'updateMe']);
    Route::delete('/auth/me', [AuthController::class, 'deleteMe']);
    Route::get('/auth/check-username', [AuthController::class, 'checkUsername']);
    Route::put('/auth/password', [AuthController::class, 'updatePassword']);
    Route::post('/auth/verify-email', [EmailVerificationController::class, 'sendVerificationEmail']);
    Route::put('/auth/google', [AuthController::class, 'connectGoogle']);
    Route::delete('/auth/google', [AuthController::class, 'disconnectGoogle']);

    // Legacy password endpoint
    Route::put('/password', [AuthController::class, 'updatePassword']);

    // Users (read-only for authenticated users)
    Route::get('/users', [UserController::class, 'index']);

    // Books
    Route::post('/books/{book}/enrich', [BookController::class, 'enrichBook']);
    Route::get('/books/{book}/editions', [BookController::class, 'getEditions']);
    // @deprecated Use GET /books/{id}?with=details instead (amazon_links included automatically)
    Route::get('/books/{book}/amazon-links', [BookController::class, 'getAmazonLinks']);
    Route::apiResource('books', BookController::class)->except(['index', 'show', 'store']);

    // User's books (Personal Library Management)
    Route::get('/user/books', [UserBookController::class, 'index']);
    Route::get('/user/books/{book}', [UserBookController::class, 'show']);
    Route::post('/user/books', [UserBookController::class, 'store']);
    Route::patch('/user/books/{book}', [UserBookController::class, 'update']);
    Route::put('/user/books/{book}/replace', [UserBookController::class, 'replaceBook']);
    Route::delete('/user/books/{book}', [UserBookController::class, 'destroy']);

    // GoodReads Import
    Route::post('/user/goodreads-imports', [GoodReadsImportController::class, 'store']);

    // Follow system
    Route::post('/users/{user}/follow', [FollowController::class, 'follow']);
    Route::delete('/users/{user}/follow', [FollowController::class, 'unfollow']);
    Route::get('/users/{user}/followers', [FollowController::class, 'followers']);
    Route::get('/users/{user}/following', [FollowController::class, 'following']);

    // User's specific book (for viewing other people's shelves)
    Route::get('/users/{user}/books/{book}', [UserBookController::class, 'showUserBook']);

    // Follow requests
    Route::get('/follow-requests', [FollowController::class, 'getFollowRequests']);
    Route::post('/follow-requests/{followId}', [FollowController::class, 'acceptFollowRequest']);
    Route::delete('/follow-requests/{followId}', [FollowController::class, 'rejectFollowRequest']);

    // Reviews (authenticated routes)
    Route::get('/reviews', [ReviewController::class, 'index']);
    Route::post('/reviews', [ReviewController::class, 'store']);
    Route::put('/reviews/{review}', [ReviewController::class, 'update']);
    Route::delete('/reviews/{review}', [ReviewController::class, 'destroy']);
    Route::post('/reviews/{review}/helpful', [ReviewController::class, 'markAsHelpful']);

    // Feed (activity feed)
    Route::get('/feeds', [FeedController::class, 'index']);

    // Activity interactions (likes and comments)
    Route::post('/activities/{activity}/like', [ActivityInteractionController::class, 'like']);
    Route::delete('/activities/{activity}/like', [ActivityInteractionController::class, 'unlike']);
    Route::get('/activities/{activity}/likes', [ActivityInteractionController::class, 'getLikes']);
    Route::get('/activities/{activity}/comments', [ActivityInteractionController::class, 'getComments']);
    Route::post('/activities/{activity}/comments', [ActivityInteractionController::class, 'addComment']);

    // Comments
    Route::put('/comments/{comment}', [ActivityInteractionController::class, 'updateComment']);
    Route::delete('/comments/{comment}', [ActivityInteractionController::class, 'deleteComment']);

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markAsRead']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
});
