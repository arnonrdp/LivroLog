<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Http\Request;
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
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);
// Google OAuth routes (public)
Route::get('/auth/google', [AuthController::class, 'redirectToGoogle']);
Route::get('/auth/google/callback', [AuthController::class, 'handleGoogleCallback']);
Route::post('/auth/google', [AuthController::class, 'googleSignIn']);
// Password reset routes (public)
Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/auth/reset-password', [AuthController::class, 'resetPassword']);

// Public showcase route
Route::get('/showcase', [BookController::class, 'showcase']);

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

    // Profile management
    Route::put('/profile', [UserController::class, 'updateProfile']);
    Route::put('/account', [UserController::class, 'updateAccount']);
    Route::delete('/account', [UserController::class, 'deleteAccount']);
    Route::put('/password', [AuthController::class, 'updatePassword']);
    Route::get('/check-username', [UserController::class, 'checkUsername']);

    // Users
    Route::apiResource('users', UserController::class);

    // Books
    Route::get('/books/search', [BookController::class, 'search']);
    Route::patch('/books/read-dates', [BookController::class, 'updateReadDates']);
    Route::post('/books/enrich-batch', [BookController::class, 'enrichBooksInBatch']);
    Route::post('/books/create-enriched', [BookController::class, 'createEnrichedBook']);
    Route::post('/books/{id}/enrich', [BookController::class, 'enrichBook']);
    Route::apiResource('books', BookController::class);

    // User's books
    Route::get('/users/{user}/books', [UserController::class, 'books']);
    Route::delete('/users/{user}/books/{book}', [UserController::class, 'removeBook']);
    Route::patch('/users/{user}/books/{book}/read-date', [UserController::class, 'updateReadDate']);
});
