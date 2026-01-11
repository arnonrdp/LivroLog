<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\ActivityLike;
use App\Models\Comment;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ActivityInteractionService
{
    /**
     * Like an activity (idempotent).
     */
    public function likeActivity(User $user, Activity $activity): array
    {
        // Check if activity owner's profile allows this
        if (! $this->canInteractWithActivity($user, $activity)) {
            return [
                'success' => false,
                'message' => 'Cannot interact with this activity',
                'code' => 'ACTIVITY_NOT_ACCESSIBLE',
            ];
        }

        // Check if already liked (idempotent)
        $existingLike = ActivityLike::where('user_id', $user->id)
            ->where('activity_id', $activity->id)
            ->first();

        if ($existingLike) {
            return [
                'success' => true,
                'message' => 'Activity already liked',
                'data' => [
                    'liked' => true,
                    'likes_count' => $activity->likes_count,
                ],
            ];
        }

        DB::transaction(function () use ($user, $activity) {
            ActivityLike::create([
                'user_id' => $user->id,
                'activity_id' => $activity->id,
            ]);

            $activity->increment('likes_count');

            // Create notification if not liking own activity
            if ($activity->user_id !== $user->id) {
                Notification::create([
                    'user_id' => $activity->user_id,
                    'actor_id' => $user->id,
                    'type' => 'activity_liked',
                    'notifiable_type' => 'Activity',
                    'notifiable_id' => $activity->id,
                ]);
            }
        });

        return [
            'success' => true,
            'message' => 'Activity liked',
            'data' => [
                'liked' => true,
                'likes_count' => $activity->fresh()->likes_count,
            ],
        ];
    }

    /**
     * Unlike an activity.
     */
    public function unlikeActivity(User $user, Activity $activity): array
    {
        $like = ActivityLike::where('user_id', $user->id)
            ->where('activity_id', $activity->id)
            ->first();

        if (! $like) {
            return [
                'success' => true,
                'message' => 'Activity not liked',
                'data' => [
                    'liked' => false,
                    'likes_count' => $activity->likes_count,
                ],
            ];
        }

        DB::transaction(function () use ($like, $activity) {
            $like->delete();
            $activity->decrement('likes_count');
        });

        return [
            'success' => true,
            'message' => 'Activity unliked',
            'data' => [
                'liked' => false,
                'likes_count' => $activity->fresh()->likes_count,
            ],
        ];
    }

    /**
     * Add a comment to an activity.
     */
    public function addComment(User $user, Activity $activity, string $content): array
    {
        if (! $this->canInteractWithActivity($user, $activity)) {
            return [
                'success' => false,
                'message' => 'Cannot interact with this activity',
                'code' => 'ACTIVITY_NOT_ACCESSIBLE',
            ];
        }

        $content = trim($content);

        $comment = DB::transaction(function () use ($user, $activity, $content) {
            $comment = Comment::create([
                'user_id' => $user->id,
                'activity_id' => $activity->id,
                'content' => $content,
            ]);

            $activity->increment('comments_count');

            // Create notification if not commenting on own activity
            if ($activity->user_id !== $user->id) {
                Notification::create([
                    'user_id' => $activity->user_id,
                    'actor_id' => $user->id,
                    'type' => 'activity_commented',
                    'notifiable_type' => 'Activity',
                    'notifiable_id' => $activity->id,
                    'data' => ['comment_id' => $comment->id],
                ]);
            }

            return $comment;
        });

        return [
            'success' => true,
            'message' => 'Comment added',
            'data' => $comment->load('user'),
        ];
    }

    /**
     * Update a comment.
     */
    public function updateComment(User $user, Comment $comment, string $content): array
    {
        $content = trim($content);

        if ($comment->user_id !== $user->id) {
            return [
                'success' => false,
                'message' => 'Cannot edit this comment',
                'code' => 'NOT_COMMENT_OWNER',
            ];
        }

        $comment->update(['content' => $content]);

        return [
            'success' => true,
            'message' => 'Comment updated',
            'data' => $comment->fresh()->load('user'),
        ];
    }

    /**
     * Delete a comment.
     */
    public function deleteComment(User $user, Comment $comment): array
    {
        if ($comment->user_id !== $user->id) {
            return [
                'success' => false,
                'message' => 'Cannot delete this comment',
                'code' => 'NOT_COMMENT_OWNER',
            ];
        }

        DB::transaction(function () use ($comment) {
            $activity = $comment->activity;
            $comment->delete();
            $activity->decrement('comments_count');
        });

        return [
            'success' => true,
            'message' => 'Comment deleted',
        ];
    }

    /**
     * Check if user can interact with an activity (privacy check).
     */
    private function canInteractWithActivity(User $user, Activity $activity): bool
    {
        $activityOwner = $activity->user;

        // Can always interact with own activities
        if ($user->id === $activityOwner->id) {
            return true;
        }

        // If owner is not private, anyone can interact
        if (! $activityOwner->is_private) {
            return true;
        }

        // For private profiles, only followers can interact
        return $user->isFollowing($activityOwner);
    }

    /**
     * Get users who liked an activity.
     */
    public function getLikers(Activity $activity, int $limit = 10): array
    {
        $likes = $activity->likes()
            ->with('user:id,display_name,username,avatar')
            ->latest()
            ->limit($limit)
            ->get();

        return [
            'users' => $likes->pluck('user'),
            'total' => $activity->likes_count,
        ];
    }
}
