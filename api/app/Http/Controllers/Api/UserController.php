<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\HandlesPagination;
use App\Http\Resources\PaginatedUserResource;
use App\Http\Resources\UserResource;
use App\Http\Resources\UserWithBooksResource;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    use HandlesPagination;

    /**
     * @OA\Get(
     *     path="/users",
     *     operationId="getUsers",
     *     tags={"Users"},
     *     summary="List users",
     *     description="Returns paginated list of users with followers/following counts",
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         required=false,
     *
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Items per page",
     *         required=false,
     *
     *         @OA\Schema(type="integer", example=15, minimum=1, maximum=100)
     *     ),
     *
     *     @OA\Parameter(
     *         name="filter",
     *         in="query",
     *         description="Search filter for name, username, or email",
     *         required=false,
     *
     *         @OA\Schema(type="string", example="john")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Users list",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/User")),
     *             @OA\Property(property="current_page", type="integer"),
     *             @OA\Property(property="total", type="integer"),
     *             @OA\Property(property="per_page", type="integer")
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function index(Request $request)
    {
        $query = User::withCount(['books', 'followers', 'following']);

        // Add is_following and has_pending_follow_request status for authenticated users
        if ($authUser = $request->user()) {
            $query->withExists(['followers as is_following' => function ($q) use ($authUser) {
                $q->where('follower_id', $authUser->id)
                    ->where('status', 'accepted');
            }])
                ->addSelect(['has_pending_follow_request' => function ($subQuery) use ($authUser) {
                    $subQuery->selectRaw('CASE WHEN COUNT(*) > 0 THEN 1 ELSE 0 END')
                        ->from('follows')
                        ->where('follower_id', $authUser->id)
                        ->where('status', 'pending')
                        ->whereColumn('followed_id', 'users.id');
                }]);
        }

        // Global filter
        $filter = $request->input('filter');
        if (! empty($filter)) {
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
     * @OA\Post(
     *     path="/users",
     *     operationId="createUser",
     *     tags={"Users"},
     *     summary="Create new user",
     *     description="Creates a new user account",
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *         description="User data",
     *
     *         @OA\JsonContent(
     *             required={"display_name","email","username"},
     *
     *             @OA\Property(property="display_name", type="string", example="John Doe", description="User display name"),
     *             @OA\Property(property="email", type="string", format="email", example="john@example.com", description="User email"),
     *             @OA\Property(property="username", type="string", example="john_doe", description="Unique username"),
     *             @OA\Property(property="shelf_name", type="string", example="John's Library", description="Shelf name (optional)")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="User created successfully",
     *
     *         @OA\JsonContent(ref="#/components/schemas/User")
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
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
     * @OA\Get(
     *     path="/users/{identifier}",
     *     operationId="getUser",
     *     tags={"Users"},
     *     summary="Get user by ID or username",
     *     description="Returns user information with books if profile is public or user is following",
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Parameter(
     *         name="identifier",
     *         in="path",
     *         description="User ID (starts with U-) or username",
     *         required=true,
     *
     *         @OA\Schema(type="string", example="john_doe")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="User information with privacy-aware book visibility",
     *
     *         @OA\JsonContent(ref="#/components/schemas/User")
     *     ),
     *
     *     @OA\Response(
     *         response=403,
     *         description="Profile is private and user is not following",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="This profile is private")
     *         )
     *     ),
     *
     *     @OA\Response(response=404, description="User not found"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function show(Request $request, string $identifier)
    {
        $currentUser = $request->user();

        // If identifier starts with "U-", it's an ID, otherwise it's a username
        $user = str_starts_with($identifier, 'U-')
            ? User::withCount(['followers', 'following'])->findOrFail($identifier)
            : User::withCount(['followers', 'following'])->where('username', $identifier)->firstOrFail();

        // Check if profile is private and user is not the owner or following
        $isOwner = $currentUser && $currentUser->id === $user->id;
        // Only consider as following if the follow status is 'accepted'
        $isFollowing = false;
        if ($currentUser) {
            $isFollowing = $currentUser->followingRelationships()
                ->where('followed_id', $user->id)
                ->where('status', 'accepted')
                ->exists();

        }

        // Load books only if profile is public, user is owner, or user is following
        if (! $user->is_private || $isOwner || $isFollowing) {
            $user->load(['books' => function ($query) use ($isOwner) {
                $query->orderBy('pivot_added_at', 'desc')->withPivot('is_private', 'reading_status');
                // Only show private books to the owner
                if (! $isOwner) {
                    $query->wherePivot('is_private', false);
                }
            }]);
        }

        $resource = new UserWithBooksResource($user);
        $userData = $resource->toArray(request());

        // Add follow status and request status if user is authenticated
        if ($currentUser && $currentUser->id !== $user->id) {
            $userData['is_following'] = $isFollowing;

            $hasPendingRequest = $currentUser->followingRelationships()
                ->where('followed_id', $user->id)
                ->where('status', 'pending')
                ->exists();
            $userData['has_pending_follow_request'] = $hasPendingRequest;
        }

        return response()->json($userData);
    }

    /**
     * @OA\Put(
     *     path="/users/{id}",
     *     operationId="updateUser",
     *     tags={"Users"},
     *     summary="Update user",
     *     description="Updates user information",
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="User ID",
     *         required=true,
     *
     *         @OA\Schema(type="string", example="U-1ABC-2DEF")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *         description="User data to update",
     *
     *         @OA\JsonContent(
     *             required={"display_name","email","username"},
     *
     *             @OA\Property(property="display_name", type="string", example="John Doe", description="User display name"),
     *             @OA\Property(property="email", type="string", format="email", example="john@example.com", description="User email"),
     *             @OA\Property(property="username", type="string", example="john_doe", description="Unique username"),
     *             @OA\Property(property="shelf_name", type="string", example="John's Library", description="Shelf name (optional)")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="User updated successfully",
     *
     *         @OA\JsonContent(ref="#/components/schemas/User")
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *
     *     @OA\Response(response=404, description="User not found"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function update(Request $request, string $id)
    {
        $user = User::findOrFail($id);

        $request->validate([
            'display_name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,'.$user->id,
            'username' => 'required|string|max:100|unique:users,username,'.$user->id,
            'shelf_name' => 'nullable|string|max:255',
        ]);

        $user->update($request->all());

        return new UserResource($user);
    }

    /**
     * @OA\Delete(
     *     path="/users/{id}",
     *     operationId="deleteUser",
     *     tags={"Users"},
     *     summary="Delete user",
     *     description="Deletes a user account",
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="User ID",
     *         required=true,
     *
     *         @OA\Schema(type="string", example="U-1ABC-2DEF")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="User deleted successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="User deleted successfully")
     *         )
     *     ),
     *
     *     @OA\Response(response=404, description="User not found"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function destroy(string $id)
    {
        $user = User::findOrFail($id);
        $user->delete();

        return response()->json(['message' => 'User deleted successfully']);
    }
}
