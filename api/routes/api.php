<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookController;
use App\Http\Controllers\Api\EmailVerificationController;
use App\Http\Controllers\Api\FollowController;
use App\Http\Controllers\Api\GoodReadsImportController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\ImageProxyController;
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
Route::middleware(['throttle:5,1'])->group(function () {
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

// Public routes
Route::get('/health', [HealthController::class, 'index']);
Route::get('/books', [BookController::class, 'index']);

// Image proxy (public for CORS issues)
Route::get('/image-proxy', [ImageProxyController::class, 'proxy']);
Route::get('/image-proxy/stats', [ImageProxyController::class, 'stats']);

// User shelf images for social sharing (public)
Route::get('/users/{id}/shelf-image', [UserController::class, 'shelfImage']);

// Public user profiles
Route::get('/users/{identifier}', [UserController::class, 'show']);

// Protected routes (require authentication)
Route::middleware('auth:sanctum')->group(function () {
    // Admin-only routes
    Route::middleware('admin')->group(function () {
        // Merge authors
        Route::post('/authors/merge', [\App\Http\Controllers\Api\AuthorMergeController::class, 'merge']);

        // User management (restricted to admins)
        Route::post('/users', [UserController::class, 'store']);
        Route::put('/users/{id}', [UserController::class, 'update']);
        Route::delete('/users/{id}', [UserController::class, 'destroy']);
    });

    // Auth
    Route::post('/auth/logout', [AuthController::class, 'logout']);
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
    Route::apiResource('books', BookController::class)->except(['index']);

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
});
