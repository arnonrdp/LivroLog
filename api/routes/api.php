<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookController;
use App\Http\Controllers\Api\FollowController;
use App\Http\Controllers\Api\HealthController;
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

// Public routes
Route::get('/health', [HealthController::class, 'index']);

// Protected routes (require authentication)
Route::middleware('auth:sanctum')->group(function () {
    // Admin-only routes
    Route::middleware('admin')->group(function () {
        // Merge authors
        Route::post('/authors/merge', [\App\Http\Controllers\Api\AuthorMergeController::class, 'merge']);
    });

    // Auth
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::put('/auth/me', [AuthController::class, 'updateMe']);
    Route::delete('/auth/me', [AuthController::class, 'deleteMe']);
    Route::get('/auth/check-username', [AuthController::class, 'checkUsername']);
    Route::put('/auth/password', [AuthController::class, 'updatePassword2']);

    // Legacy password endpoint
    Route::put('/password', [AuthController::class, 'updatePassword']);

    // Users
    Route::apiResource('users', UserController::class);

    // Books
    Route::post('/books/{book}/enrich', [BookController::class, 'enrichBook']);
    Route::apiResource('books', BookController::class);

    // User's books (Personal Library Management)
    Route::get('/user/books', [UserBookController::class, 'index']);
    Route::post('/user/books', [UserBookController::class, 'store']);
    Route::delete('/user/books/{book}', [UserBookController::class, 'destroy']);
    Route::patch('/user/books/{book}/read-date', [UserBookController::class, 'updateReadDate']);

    // Follow system
    Route::post('/users/{user}/follow', [FollowController::class, 'follow']);
    Route::delete('/users/{user}/follow', [FollowController::class, 'unfollow']);
    Route::get('/users/{user}/followers', [FollowController::class, 'followers']);
    Route::get('/users/{user}/following', [FollowController::class, 'following']);

    // Reviews (authenticated routes)
    Route::get('/reviews', [ReviewController::class, 'index']);
    Route::post('/reviews', [ReviewController::class, 'store']);
    Route::put('/reviews/{review}', [ReviewController::class, 'update']);
    Route::delete('/reviews/{review}', [ReviewController::class, 'destroy']);
    Route::post('/reviews/{review}/helpful', [ReviewController::class, 'markAsHelpful']);
});
