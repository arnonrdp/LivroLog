<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    protected NotificationService $service;

    public function __construct(NotificationService $service)
    {
        $this->service = $service;
    }

    /**
     * Get notifications for authenticated user.
     *
     * @OA\Get(
     *     path="/notifications",
     *     summary="Get notifications for authenticated user",
     *     tags={"Notifications"},
     *     security={{"sanctum":{}}},
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
     *         description="List of notifications",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Notification")),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="total", type="integer"),
     *                 @OA\Property(property="current_page", type="integer"),
     *                 @OA\Property(property="per_page", type="integer"),
     *                 @OA\Property(property="last_page", type="integer"),
     *                 @OA\Property(property="unread_count", type="integer")
     *             )
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->get('per_page', 20);
        $notifications = $this->service->getNotifications($request->user(), $perPage);

        return response()->json([
            'data' => NotificationResource::collection($notifications),
            'meta' => [
                'total' => $notifications->total(),
                'current_page' => $notifications->currentPage(),
                'per_page' => $notifications->perPage(),
                'last_page' => $notifications->lastPage(),
                'unread_count' => $this->service->getUnreadCount($request->user()),
            ],
        ]);
    }

    /**
     * Get unread notification count.
     *
     * @OA\Get(
     *     path="/notifications/unread-count",
     *     summary="Get unread notification count",
     *     tags={"Notifications"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Unread count",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="unread_count", type="integer", example=5)
     *         )
     *     )
     * )
     */
    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'unread_count' => $this->service->getUnreadCount($request->user()),
        ]);
    }

    /**
     * Mark a notification as read.
     *
     * @OA\Post(
     *     path="/notifications/{notification}/read",
     *     summary="Mark a notification as read",
     *     tags={"Notifications"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="notification",
     *         in="path",
     *         required=true,
     *
     *         @OA\Schema(type="string", example="N-3D6Y-9IO8")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Notification marked as read",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Notification marked as read")
     *         )
     *     ),
     *
     *     @OA\Response(response=403, description="Cannot mark this notification")
     * )
     */
    public function markAsRead(Request $request, Notification $notification): JsonResponse
    {
        $success = $this->service->markAsRead($request->user(), $notification);

        return response()->json([
            'success' => $success,
            'message' => $success ? 'Notification marked as read' : 'Cannot mark this notification',
        ], $success ? 200 : 403);
    }

    /**
     * Mark all notifications as read.
     *
     * @OA\Post(
     *     path="/notifications/read-all",
     *     summary="Mark all notifications as read",
     *     tags={"Notifications"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(
     *         response=200,
     *         description="All notifications marked as read",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="marked_count", type="integer", example=5)
     *         )
     *     )
     * )
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $count = $this->service->markAllAsRead($request->user());

        return response()->json([
            'success' => true,
            'message' => "Marked {$count} notifications as read",
            'marked_count' => $count,
        ]);
    }
}
