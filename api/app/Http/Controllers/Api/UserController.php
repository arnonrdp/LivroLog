<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\HandlesPagination;
use App\Http\Resources\PaginatedUserResource;
use App\Http\Resources\UserResource;
use App\Http\Resources\UserWithBooksResource;
use App\Models\Book;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    use HandlesPagination;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = User::withCount('books');

        // Global filter
        $filter = $request->input('filter');
        if (!empty($filter)) {
            $query->where(function ($q) use ($filter) {
                $q->where('id', 'like', "%{$filter}%")
                  ->orWhere('display_name', 'like', "%{$filter}%")
                  ->orWhere('username', 'like', "%{$filter}%")
                  ->orWhere('email', 'like', "%{$filter}%");
            });
        }

        $users = $this->applyPagination($query, $request);
        return new PaginatedUserResource($users);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'display_name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'username' => 'required|string|max:100|unique:users',
            'shelf_name' => 'nullable|string|max:255',
        ]);

        $user = User::create($request->all());
        return new UserResource($user);
    }

    /**
     * Display the specified resource by ID or username.
     */
    public function show(string $identifier)
    {
        $query = User::with(['books' => function($query) {
            $query->orderBy('pivot_added_at', 'desc');
        }]);

        // If identifier starts with "U-", it's an ID, otherwise it's a username
        $user = str_starts_with($identifier, 'U-')
            ? $query->findOrFail($identifier)
            : $query->where('username', $identifier)->firstOrFail();

        return new UserWithBooksResource($user);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $user = User::findOrFail($id);

        $request->validate([
            'display_name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'username' => 'required|string|max:100|unique:users,username,' . $user->id,
            'shelf_name' => 'nullable|string|max:255',
        ]);

        $user->update($request->all());
        return new UserResource($user);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $user = User::findOrFail($id);
        $user->delete();

        return response()->json(['message' => 'User deleted successfully']);
    }

    /**
     * Get user's books
     */
    public function books(User $user)
    {
        $books = $user->books()->withPivot('added_at', 'read_at')->get();
        return response()->json($books);
    }



    /**
     * Remove book from user's library
     */
    public function removeBook(User $user, Book $book)
    {
        $user->books()->detach($book->id);
        return response()->json(['message' => 'Book removed from library']);
    }

    /**
     * Update read date for a book
     */
    public function updateReadDate(Request $request, User $user, Book $book)
    {
        $request->validate([
            'read_at' => 'nullable|date',
        ]);

        $user->books()->updateExistingPivot($book->id, [
            'read_at' => $request->read_at,
        ]);

        return response()->json(['message' => 'Read date updated']);
    }

    /**
     * Update user profile
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'display_name' => 'sometimes|string|max:255',
            'username' => 'sometimes|string|max:100|unique:users,username,' . $user->id,
            'email' => 'sometimes|email|max:255|unique:users,email,' . $user->id,
            'shelf_name' => 'sometimes|string|max:255',
            'locale' => 'sometimes|string|max:10',
        ]);

        $user->update($request->only(['display_name', 'username', 'email', 'shelf_name', 'locale']));

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => $user->fresh()
        ]);
    }

    /**
     * Update user account (email and password)
     */
    public function updateAccount(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'email' => 'sometimes|email|max:255|unique:users,email,' . $user->id,
            'password' => 'sometimes|string|min:8|confirmed',
        ]);

        $updateData = [];

        if ($request->has('email')) {
            $updateData['email'] = $request->email;
        }

        if ($request->has('password')) {
            $updateData['password'] = bcrypt($request->password);
        }

        $user->update($updateData);

        return response()->json([
            'message' => 'Account updated successfully',
            'user' => $user->fresh()
        ]);
    }

    /**
     * Delete user account
     */
    public function deleteAccount(Request $request)
    {
        $user = $request->user();

        // Delete user's tokens
        $user->tokens()->delete();

        // Delete user
        $user->delete();

        return response()->json([
            'message' => 'Account deleted successfully'
        ]);
    }

    /**
     * Check if username is available
     */
    public function checkUsername(Request $request)
    {
        $request->validate([
            'username' => 'required|string|max:100',
        ]);

        $currentUserId = auth()->id();
        $exists = User::where('username', $request->username)
                     ->where('id', '!=', $currentUserId)
                     ->exists();

        return response()->json([
            'exists' => $exists,
            'available' => !$exists
        ]);
    }
}
