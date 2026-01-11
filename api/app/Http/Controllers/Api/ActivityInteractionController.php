<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CommentResource;
use App\Models\Activity;
use App\Models\Comment;
use App\Services\ActivityInteractionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityInteractionController extends Controller
{
    protected ActivityInteractionService $service;

    public function __construct(ActivityInteractionService $service)
    {
        $this->service = $service;
    }

    /**
     * Like an activity.
     *
     * @OA\Post(
     *     path="/activities/{activity}/like",
     *     summary="Like an activity",
     *     tags={"Feed"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="activity",
     *         in="path",
     *         required=true,
     *
     *         @OA\Schema(type="string", example="A-3D6Y-9IO8")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Activity liked successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Activity liked"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="liked", type="boolean", example=true),
     *                 @OA\Property(property="likes_count", type="integer", example=5)
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=403, description="Cannot interact with this activity")
     * )
     */
    public function like(Request $request, Activity $activity): JsonResponse
    {
        $result = $this->service->likeActivity($request->user(), $activity);

        return response()->json($result, $result['success'] ? 200 : 403);
    }

    /**
     * Unlike an activity.
     *
     * @OA\Delete(
     *     path="/activities/{activity}/like",
     *     summary="Unlike an activity",
     *     tags={"Feed"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="activity",
     *         in="path",
     *         required=true,
     *
     *         @OA\Schema(type="string", example="A-3D6Y-9IO8")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Activity unliked successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Activity unliked"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="liked", type="boolean", example=false),
     *                 @OA\Property(property="likes_count", type="integer", example=4)
     *             )
     *         )
     *     )
     * )
     */
    public function unlike(Request $request, Activity $activity): JsonResponse
    {
        $result = $this->service->unlikeActivity($request->user(), $activity);

        return response()->json($result);
    }

    /**
     * Get users who liked an activity.
     *
     * @OA\Get(
     *     path="/activities/{activity}/likes",
     *     summary="Get users who liked an activity",
     *     tags={"Feed"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="activity",
     *         in="path",
     *         required=true,
     *
     *         @OA\Schema(type="string", example="A-3D6Y-9IO8")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="List of users who liked the activity",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(
     *                 property="users",
     *                 type="array",
     *
     *                 @OA\Items(
     *                     type="object",
     *
     *                     @OA\Property(property="id", type="string"),
     *                     @OA\Property(property="display_name", type="string"),
     *                     @OA\Property(property="username", type="string"),
     *                     @OA\Property(property="avatar", type="string", nullable=true)
     *                 )
     *             ),
     *             @OA\Property(property="total", type="integer", example=10)
     *         )
     *     )
     * )
     */
    public function getLikes(Activity $activity): JsonResponse
    {
        $result = $this->service->getLikers($activity);

        return response()->json($result);
    }

    /**
     * Get comments for an activity.
     *
     * @OA\Get(
     *     path="/activities/{activity}/comments",
     *     summary="Get comments for an activity",
     *     tags={"Feed"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="activity",
     *         in="path",
     *         required=true,
     *
     *         @OA\Schema(type="string", example="A-3D6Y-9IO8")
     *     ),
     *
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         required=false,
     *
     *         @OA\Schema(type="integer", example=20)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="List of comments"
     *     )
     * )
     */
    public function getComments(Request $request, Activity $activity): JsonResponse
    {
        $perPage = $request->get('per_page', 20);

        $comments = $activity->comments()
            ->with('user:id,display_name,username,avatar')
            ->orderBy('created_at', 'asc')
            ->paginate($perPage);

        return response()->json([
            'data' => CommentResource::collection($comments),
            'meta' => [
                'total' => $comments->total(),
                'current_page' => $comments->currentPage(),
                'per_page' => $comments->perPage(),
                'last_page' => $comments->lastPage(),
            ],
        ]);
    }

    /**
     * Add a comment to an activity.
     *
     * @OA\Post(
     *     path="/activities/{activity}/comments",
     *     summary="Add a comment to an activity",
     *     tags={"Feed"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="activity",
     *         in="path",
     *         required=true,
     *
     *         @OA\Schema(type="string", example="A-3D6Y-9IO8")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="content", type="string", example="Great book choice!")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Comment added successfully"
     *     ),
     *     @OA\Response(response=403, description="Cannot interact with this activity")
     * )
     */
    public function addComment(Request $request, Activity $activity): JsonResponse
    {
        $validated = $request->validate([
            'content' => 'required|string|max:1000',
        ]);

        $result = $this->service->addComment($request->user(), $activity, $validated['content']);

        if ($result['success']) {
            $result['data'] = new CommentResource($result['data']);
        }

        return response()->json($result, $result['success'] ? 201 : 403);
    }

    /**
     * Update a comment.
     *
     * @OA\Put(
     *     path="/comments/{comment}",
     *     summary="Update a comment",
     *     tags={"Feed"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="comment",
     *         in="path",
     *         required=true,
     *
     *         @OA\Schema(type="string", example="C-3D6Y-9IO8")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="content", type="string", example="Updated comment!")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Comment updated successfully"
     *     ),
     *     @OA\Response(response=403, description="Cannot edit this comment")
     * )
     */
    public function updateComment(Request $request, Comment $comment): JsonResponse
    {
        $validated = $request->validate([
            'content' => 'required|string|max:1000',
        ]);

        $result = $this->service->updateComment($request->user(), $comment, $validated['content']);

        if ($result['success']) {
            $result['data'] = new CommentResource($result['data']);
        }

        return response()->json($result, $result['success'] ? 200 : 403);
    }

    /**
     * Delete a comment.
     *
     * @OA\Delete(
     *     path="/comments/{comment}",
     *     summary="Delete a comment",
     *     tags={"Feed"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="comment",
     *         in="path",
     *         required=true,
     *
     *         @OA\Schema(type="string", example="C-3D6Y-9IO8")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Comment deleted successfully"
     *     ),
     *     @OA\Response(response=403, description="Cannot delete this comment")
     * )
     */
    public function deleteComment(Request $request, Comment $comment): JsonResponse
    {
        $result = $this->service->deleteComment($request->user(), $comment);

        return response()->json($result, $result['success'] ? 200 : 403);
    }
}
