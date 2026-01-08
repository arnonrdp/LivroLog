<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\HandlesPagination;
use App\Models\Book;
use App\Models\User;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    use HandlesPagination;

    /**
     * List all users with optional sorting and filtering
     */
    public function users(Request $request)
    {
        $query = User::query()
            ->select('users.*')
            ->withCount(['books', 'followers', 'following'])
            ->with(['lastActivity.subject' => function ($morphTo) {
                $morphTo->morphWith([
                    \App\Models\Review::class => ['book'],
                ]);
            }])
            ->addSelect([
                'last_activity_at' => \App\Models\Activity::select('created_at')
                    ->whereColumn('user_id', 'users.id')
                    ->orderByDesc('created_at')
                    ->limit(1),
            ]);

        // Search filter
        $filter = $request->input('filter');
        if (! empty($filter)) {
            $query->where(function ($q) use ($filter) {
                $q->where('id', 'like', "%{$filter}%")
                    ->orWhere('display_name', 'like', "%{$filter}%")
                    ->orWhere('username', 'like', "%{$filter}%")
                    ->orWhere('email', 'like', "%{$filter}%");
            });
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDesc = $request->boolean('sort_desc', true);
        $allowedSortColumns = ['display_name', 'username', 'email', 'role', 'books_count', 'created_at', 'last_activity_at'];

        if (in_array($sortBy, $allowedSortColumns)) {
            $query->orderBy($sortBy, $sortDesc ? 'desc' : 'asc');
        } else {
            $query->orderByDesc('created_at');
        }

        $users = $this->applyPagination($query, $request);

        return response()->json([
            'data' => $users->map(fn ($user) => [
                'id' => $user->id,
                'display_name' => $user->display_name,
                'username' => $user->username,
                'email' => $user->email,
                'avatar' => $user->avatar,
                'role' => $user->role,
                'books_count' => $user->books_count,
                'followers_count' => $user->followers_count,
                'following_count' => $user->following_count,
                'created_at' => $user->created_at,
                'last_activity' => $user->lastActivity ? [
                    'type' => $user->lastActivity->type,
                    'subject_name' => $this->getActivitySubjectName($user->lastActivity),
                    'created_at' => $user->lastActivity->created_at,
                ] : null,
            ]),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
        ]);
    }

    /**
     * Get the subject name for an activity
     */
    private function getActivitySubjectName($activity): ?string
    {
        if (! $activity->subject) {
            return null;
        }

        return match ($activity->subject_type) {
            'App\\Models\\Book' => $activity->subject->title,
            'App\\Models\\User' => $activity->subject->display_name ?? $activity->subject->username,
            'App\\Models\\Review' => $activity->subject->load('book')->book?->title,
            default => null,
        };
    }

    /**
     * List all books with optional sorting and filtering
     */
    public function books(Request $request)
    {
        $query = Book::query()
            ->withCount('users');

        // Search filter
        $filter = $request->input('filter');
        if (! empty($filter)) {
            $query->where(function ($q) use ($filter) {
                $q->where('id', 'like', "%{$filter}%")
                    ->orWhere('title', 'like', "%{$filter}%")
                    ->orWhere('authors', 'like', "%{$filter}%")
                    ->orWhere('isbn', 'like', "%{$filter}%")
                    ->orWhere('google_id', 'like', "%{$filter}%")
                    ->orWhere('amazon_asin', 'like', "%{$filter}%");
            });
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDesc = $request->boolean('sort_desc', true);
        $allowedSortColumns = ['title', 'authors', 'isbn', 'language', 'page_count', 'users_count', 'created_at'];

        if (in_array($sortBy, $allowedSortColumns)) {
            $query->orderBy($sortBy, $sortDesc ? 'desc' : 'asc');
        } else {
            $query->orderByDesc('created_at');
        }

        $books = $this->applyPagination($query, $request);

        return response()->json([
            'data' => $books->map(fn ($book) => [
                'id' => $book->id,
                'title' => $book->title,
                'authors' => $book->authors,
                'isbn' => $book->isbn,
                'google_id' => $book->google_id,
                'amazon_asin' => $book->amazon_asin,
                'language' => $book->language,
                'page_count' => $book->page_count,
                'publisher' => $book->publisher,
                'published_date' => $book->published_date,
                'thumbnail' => $book->thumbnail,
                'description' => $book->description,
                'users_count' => $book->users_count,
                'created_at' => $book->created_at,
            ]),
            'meta' => [
                'current_page' => $books->currentPage(),
                'last_page' => $books->lastPage(),
                'per_page' => $books->perPage(),
                'total' => $books->total(),
            ],
        ]);
    }
}
