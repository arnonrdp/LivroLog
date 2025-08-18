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

        return response()->json([
            'success' => true,
            'data' => [
                'followers' => $followers->items(),
                'pagination' => [
                    'total' => $followers->total(),
                    'current_page' => $followers->currentPage(),
                    'per_page' => $followers->perPage(),
                    'last_page' => $followers->lastPage(),
                ],
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

        return response()->json([
            'success' => true,
            'data' => [
                'following' => $following->items(),
                'pagination' => [
                    'total' => $following->total(),
                    'current_page' => $following->currentPage(),
                    'per_page' => $following->perPage(),
                    'last_page' => $following->lastPage(),
                ],
            ],
        ]);
    }
}
