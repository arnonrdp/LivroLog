<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\FollowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Follows",
 *     description="User follow/unfollow operations"
 * )
 */
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
     *     tags={"Follows"},
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
     *     path="/users/{id}/unfollow",
     *     summary="Unfollow a user",
     *     tags={"Follows"},
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
     *     path="/users/{id}/follow-status",
     *     summary="Get follow status between current user and target user",
     *     tags={"Follows"},
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
     *         description="Follow status information",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="is_following", type="boolean", example=true),
     *             @OA\Property(property="is_followed_by", type="boolean", example=false),
     *             @OA\Property(property="mutual_follow", type="boolean", example=false)
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="User not found"
     *     )
     * )
     */
    public function followStatus(Request $request, User $user): JsonResponse
    {
        $currentUser = $request->user();
        $status = $this->followService->getFollowStatus($currentUser, $user);

        return response()->json($status);
    }
}
