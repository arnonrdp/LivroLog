<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityResource;
use App\Models\Activity;
use App\Models\Follow;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FeedController extends Controller
{
    /**
     * Get the authenticated user's feed (activities from followed users and self).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $perPage = $request->get('per_page', 20);

        // Get IDs of followed users and include own user ID
        $userIds = Follow::where('follower_id', $user->id)
            ->where('status', 'accepted')
            ->pluck('followed_id')
            ->push($user->id);

        $activities = Activity::whereIn('user_id', $userIds)
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        $grouped = $this->groupActivities($activities->items());

        return response()->json([
            'data' => ActivityResource::collection($activities),
            'grouped' => $grouped,
            'meta' => [
                'total' => $activities->total(),
                'current_page' => $activities->currentPage(),
                'per_page' => $activities->perPage(),
                'last_page' => $activities->lastPage(),
            ],
        ]);
    }

    /**
     * Get activities for a specific user.
     */
    public function userActivities(Request $request, string $identifier): JsonResponse
    {
        $currentUser = $request->user();
        $perPage = $request->get('per_page', 20);

        // Find user by ID or username
        $user = User::where('id', $identifier)
            ->orWhere('username', $identifier)
            ->first();

        if (! $user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        // Check privacy
        if ($user->is_private && (! $currentUser || $currentUser->id !== $user->id)) {
            // Check if current user follows this private user
            $isFollowing = $currentUser
                ? Follow::where('follower_id', $currentUser->id)
                    ->where('followed_id', $user->id)
                    ->where('status', 'accepted')
                    ->exists()
                : false;

            if (! $isFollowing) {
                return response()->json([
                    'message' => 'This profile is private',
                    'data' => [],
                    'grouped' => [],
                ], 403);
            }
        }

        $activities = Activity::where('user_id', $user->id)
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        $grouped = $this->groupActivities($activities->items());

        return response()->json([
            'data' => ActivityResource::collection($activities),
            'grouped' => $grouped,
            'meta' => [
                'total' => $activities->total(),
                'current_page' => $activities->currentPage(),
                'per_page' => $activities->perPage(),
                'last_page' => $activities->lastPage(),
            ],
        ]);
    }

    /**
     * Group activities by user, type and date.
     * Ensures no duplicate subjects (books, users, reviews) within the same group.
     */
    private function groupActivities(array $activities): array
    {
        $grouped = [];

        foreach ($activities as $activity) {
            $date = $activity->created_at->format('Y-m-d');
            $key = $activity->user_id.'_'.$activity->type.'_'.$date;

            if (! isset($grouped[$key])) {
                $grouped[$key] = [
                    'user' => [
                        'id' => $activity->user?->id,
                        'display_name' => $activity->user?->display_name,
                        'username' => $activity->user?->username,
                        'avatar' => $activity->user?->avatar,
                    ],
                    'type' => $activity->type,
                    'date' => $date,
                    'count' => 0,
                    'activities' => [],
                    'seen_subjects' => [], // Track unique subjects
                ];
            }

            // Create unique key for subject to avoid duplicates
            $subjectKey = $activity->subject_type.'_'.$activity->subject_id;

            // Only add if we haven't seen this subject in this group
            if (! in_array($subjectKey, $grouped[$key]['seen_subjects'])) {
                $grouped[$key]['seen_subjects'][] = $subjectKey;
                $grouped[$key]['count']++;
                $grouped[$key]['activities'][] = (new ActivityResource($activity))->toArray(request());
            }
        }

        // Remove the tracking array before returning
        foreach ($grouped as &$group) {
            unset($group['seen_subjects']);
        }

        return array_values($grouped);
    }
}
