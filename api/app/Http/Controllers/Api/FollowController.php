<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\FollowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FollowController extends Controller
{
    protected FollowService $followService;

    public function __construct(FollowService $followService)
    {
        $this->followService = $followService;
    }

    /**
     * @OA\Post(
     *     path="/users/{id}/follow",
     *     summary="Follow a user",
     *     tags={"Social"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *
     *         @OA\Schema(type="string", example="U-ABC1-DEF2")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Successfully followed user",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Successfully followed user"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="follower",
     *                     type="object",
     *                     @OA\Property(property="id", type="string", example="U-XYZ3-UVW4"),
     *                     @OA\Property(property="display_name", type="string", example="John Doe"),
     *                     @OA\Property(property="username", type="string", example="john_doe")
     *                 ),
     *                 @OA\Property(
     *                     property="following",
     *                     type="object",
     *                     @OA\Property(property="id", type="string", example="U-ABC1-DEF2"),
     *                     @OA\Property(property="display_name", type="string", example="Jane Smith"),
     *                     @OA\Property(property="username", type="string", example="jane_smith")
     *                 ),
     *                 @OA\Property(property="following_count", type="integer", example=5),
     *                 @OA\Property(property="followers_count", type="integer", example=12)
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=400,
     *         description="Bad request (already following, cannot follow self)",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Already following this user"),
     *             @OA\Property(property="code", type="string", example="ALREADY_FOLLOWING")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="User not found"
     *     )
     * )
     */
    public function follow(Request $request, User $user): JsonResponse
    {
        $currentUser = $request->user();
        $result = $this->followService->follow($currentUser, $user);

        $statusCode = $result['success'] ? 200 : 400;

        return response()->json($result, $statusCode);
    }

    /**
     * @OA\Delete(
     *     path="/users/{id}/follow",
     *     summary="Unfollow a user",
     *     tags={"Social"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *
     *         @OA\Schema(type="string", example="U-ABC1-DEF2")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Successfully unfollowed user",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Successfully unfollowed user"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="following_count", type="integer", example=4),
     *                 @OA\Property(property="followers_count", type="integer", example=11)
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=400,
     *         description="Bad request (not following user)",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Not following this user"),
     *             @OA\Property(property="code", type="string", example="NOT_FOLLOWING")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="User not found"
     *     )
     * )
     */
    public function unfollow(Request $request, User $user): JsonResponse
    {
        $currentUser = $request->user();
        $result = $this->followService->unfollow($currentUser, $user);

        $statusCode = $result['success'] ? 200 : 400;

        return response()->json($result, $statusCode);
    }

    /**
     * @OA\Get(
     *     path="/users/{id}/followers",
     *     summary="Get list of followers for a user",
     *     tags={"Social"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *
     *         @OA\Schema(type="string", example="U-ABC1-DEF2")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="List of followers",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="followers",
     *                     type="array",
     *
     *                     @OA\Items(ref="#/components/schemas/User")
     *                 ),
     *
     *                 @OA\Property(
     *                     property="pagination",
     *                     type="object",
     *                     @OA\Property(property="total", type="integer", example=10),
     *                     @OA\Property(property="current_page", type="integer", example=1),
     *                     @OA\Property(property="per_page", type="integer", example=20),
     *                     @OA\Property(property="last_page", type="integer", example=1)
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="User not found"
     *     )
     * )
     */
    public function followers(Request $request, User $user): JsonResponse
    {
        $perPage = $request->get('per_page', 20);
        $followers = $user->followers()->paginate($perPage);

        $currentUser = $request->user();
        $followersData = collect($followers->items())->map(function ($follower) use ($currentUser) {
            return [
                'id' => $follower->id,
                'avatar' => $follower->avatar,
                'display_name' => $follower->display_name,
                'username' => $follower->username,
                'shelf_name' => $follower->shelf_name,
                'is_following' => $currentUser ? $currentUser->following()->where('followed_id', $follower->id)->exists() : false,
                'followers_count' => $follower->followers_count,
                'following_count' => $follower->following_count,
            ];
        });

        return response()->json([
            'data' => $followersData,
            'meta' => [
                'total' => $followers->total(),
                'current_page' => $followers->currentPage(),
                'per_page' => $followers->perPage(),
                'last_page' => $followers->lastPage(),
            ],
        ]);
    }

    /**
     * @OA\Get(
     *     path="/users/{id}/following",
     *     summary="Get list of users that a user is following",
     *     tags={"Social"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *
     *         @OA\Schema(type="string", example="U-ABC1-DEF2")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="List of following users",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="following",
     *                     type="array",
     *
     *                     @OA\Items(ref="#/components/schemas/User")
     *                 ),
     *
     *                 @OA\Property(
     *                     property="pagination",
     *                     type="object",
     *                     @OA\Property(property="total", type="integer", example=10),
     *                     @OA\Property(property="current_page", type="integer", example=1),
     *                     @OA\Property(property="per_page", type="integer", example=20),
     *                     @OA\Property(property="last_page", type="integer", example=1)
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="User not found"
     *     )
     * )
     */
    public function following(Request $request, User $user): JsonResponse
    {
        $perPage = $request->get('per_page', 20);
        $following = $user->following()->paginate($perPage);

        $currentUser = $request->user();
        $followingData = collect($following->items())->map(function ($followedUser) use ($currentUser) {
            return [
                'id' => $followedUser->id,
                'avatar' => $followedUser->avatar,
                'display_name' => $followedUser->display_name,
                'username' => $followedUser->username,
                'shelf_name' => $followedUser->shelf_name,
                'is_following' => true, // Always true since they're in the following list
                'is_follower' => $currentUser ? $currentUser->followers()->where('follower_id', $followedUser->id)->exists() : false,
                'followers_count' => $followedUser->followers_count,
                'following_count' => $followedUser->following_count,
            ];
        });

        return response()->json([
            'data' => $followingData,
            'meta' => [
                'total' => $following->total(),
                'current_page' => $following->currentPage(),
                'per_page' => $following->perPage(),
                'last_page' => $following->lastPage(),
            ],
        ]);
    }

    /**
     * @OA\Get(
     *     path="/follow-requests",
     *     summary="Get pending follow requests for authenticated user",
     *     tags={"Social"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(
     *         response=200,
     *         description="List of pending follow requests",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *
     *                 @OA\Items(
     *                     type="object",
     *
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(
     *                         property="follower",
     *                         type="object",
     *                         @OA\Property(property="id", type="string", example="U-ABC1-DEF2"),
     *                         @OA\Property(property="display_name", type="string", example="John Doe"),
     *                         @OA\Property(property="username", type="string", example="john_doe"),
     *                         @OA\Property(property="avatar", type="string", example="https://example.com/avatar.jpg")
     *                     ),
     *                     @OA\Property(property="created_at", type="string", format="date-time")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function getFollowRequests(Request $request): JsonResponse
    {
        $currentUser = $request->user();
        $requests = $this->followService->getPendingFollowRequests($currentUser);

        $requestsData = $requests->map(function ($request) {
            return [
                'id' => $request->id,
                'follower' => $request->follower,
                'created_at' => $request->created_at,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $requestsData,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/follow-requests/{followId}",
     *     summary="Accept a follow request",
     *     tags={"Social"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="followId",
     *         in="path",
     *         required=true,
     *
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Follow request accepted successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Follow request accepted"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="follow_id", type="integer", example=1),
     *                 @OA\Property(
     *                     property="follower",
     *                     type="object",
     *                     @OA\Property(property="id", type="string", example="U-ABC1-DEF2"),
     *                     @OA\Property(property="display_name", type="string", example="John Doe"),
     *                     @OA\Property(property="username", type="string", example="john_doe")
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Follow request not found"
     *     )
     * )
     */
    public function acceptFollowRequest(Request $request, int $followId): JsonResponse
    {
        $currentUser = $request->user();
        $result = $this->followService->acceptFollowRequest($followId, $currentUser);

        $statusCode = $result['success'] ? 200 : 404;

        return response()->json($result, $statusCode);
    }

    /**
     * @OA\Delete(
     *     path="/follow-requests/{followId}",
     *     summary="Reject a follow request",
     *     tags={"Social"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="followId",
     *         in="path",
     *         required=true,
     *
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Follow request rejected successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Follow request rejected"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="follower",
     *                     type="object",
     *                     @OA\Property(property="id", type="string", example="U-ABC1-DEF2"),
     *                     @OA\Property(property="display_name", type="string", example="John Doe"),
     *                     @OA\Property(property="username", type="string", example="john_doe")
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Follow request not found"
     *     )
     * )
     */
    public function rejectFollowRequest(Request $request, int $followId): JsonResponse
    {
        $currentUser = $request->user();
        $result = $this->followService->rejectFollowRequest($followId, $currentUser);

        $statusCode = $result['success'] ? 200 : 404;

        return response()->json($result, $statusCode);
    }
}
