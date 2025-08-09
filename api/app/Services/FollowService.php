<?php

namespace App\Services;

use App\Models\User;
use App\Models\Follow;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Collection;

class FollowService
{
    /**
     * Follow a user.
     */
    public function follow(User $follower, User $following): array
    {
        // Check if user is trying to follow themselves
        if ($follower->id === $following->id) {
            return [
                'success' => false,
                'message' => 'You cannot follow yourself',
                'code' => 'CANNOT_FOLLOW_SELF'
            ];
        }

        // Check if already following
        if ($follower->isFollowing($following)) {
            return [
                'success' => false,
                'message' => 'Already following this user',
                'code' => 'ALREADY_FOLLOWING'
            ];
        }

        DB::transaction(function () use ($follower, $following) {
            // Create follow relationship
            Follow::create([
                'follower_id' => $follower->id,
                'followed_id' => $following->id,
            ]);

            // Update counters
            $follower->increment('following_count');
            $following->increment('followers_count');
        });

        return [
            'success' => true,
            'message' => 'Successfully followed user',
            'data' => [
                'follower' => $follower->only(['id', 'display_name', 'username']),
                'following' => $following->only(['id', 'display_name', 'username']),
                'following_count' => $follower->fresh()->following_count,
                'followers_count' => $following->fresh()->followers_count,
            ]
        ];
    }

    /**
     * Unfollow a user.
     */
    public function unfollow(User $follower, User $following): array
    {
        // Check if not following
        if (!$follower->isFollowing($following)) {
            return [
                'success' => false,
                'message' => 'Not following this user',
                'code' => 'NOT_FOLLOWING'
            ];
        }

        DB::transaction(function () use ($follower, $following) {
            // Remove follow relationship
            Follow::where('follower_id', $follower->id)
                  ->where('followed_id', $following->id)
                  ->delete();

            // Update counters
            $follower->decrement('following_count');
            $following->decrement('followers_count');
        });

        return [
            'success' => true,
            'message' => 'Successfully unfollowed user',
            'data' => [
                'follower' => $follower->only(['id', 'display_name', 'username']),
                'following' => $following->only(['id', 'display_name', 'username']),
                'following_count' => $follower->fresh()->following_count,
                'followers_count' => $following->fresh()->followers_count,
            ]
        ];
    }

    /**
     * Check if user A follows user B.
     */
    public function isFollowing(User $follower, User $following): bool
    {
        return $follower->isFollowing($following);
    }

    /**
     * Get followers of a user.
     */
    public function getFollowers(User $user, int $perPage = 20): array
    {
        $followers = $user->followers()
            ->select(['users.id', 'users.display_name', 'users.username', 'users.avatar'])
            ->paginate($perPage);

        return [
            'success' => true,
            'data' => [
                'followers' => $followers->items(),
                'pagination' => [
                    'current_page' => $followers->currentPage(),
                    'per_page' => $followers->perPage(),
                    'total' => $followers->total(),
                    'last_page' => $followers->lastPage(),
                    'has_more' => $followers->hasMorePages(),
                ]
            ]
        ];
    }

    /**
     * Get users that a user is following.
     */
    public function getFollowing(User $user, int $perPage = 20): array
    {
        $following = $user->following()
            ->select(['users.id', 'users.display_name', 'users.username', 'users.avatar'])
            ->paginate($perPage);

        return [
            'success' => true,
            'data' => [
                'following' => $following->items(),
                'pagination' => [
                    'current_page' => $following->currentPage(),
                    'per_page' => $following->perPage(),
                    'total' => $following->total(),
                    'last_page' => $following->lastPage(),
                    'has_more' => $following->hasMorePages(),
                ]
            ]
        ];
    }

    /**
     * Get follow status between current user and target user.
     */
    public function getFollowStatus(User $currentUser, User $targetUser): array
    {
        return [
            'is_following' => $currentUser->isFollowing($targetUser),
            'is_followed_by' => $currentUser->isFollowedBy($targetUser),
            'mutual_follow' => $currentUser->isFollowing($targetUser) && $currentUser->isFollowedBy($targetUser),
        ];
    }

    /**
     * Get mutual followers between two users.
     */
    public function getMutualFollowers(User $user1, User $user2): Collection
    {
        return $user1->followers()
            ->whereIn('follower_id', function ($query) use ($user2) {
                $query->select('follower_id')
                      ->from('follows')
                      ->where('followed_id', $user2->id);
            })
            ->select(['id', 'display_name', 'username', 'avatar'])
            ->get();
    }

    /**
     * Suggest users to follow based on mutual connections.
     */
    public function getSuggestedUsers(User $user, int $limit = 10): Collection
    {
        // Get users that are followed by people the current user follows
        // but not already followed by the current user
        return User::whereIn('id', function ($query) use ($user) {
                $query->select('followed_id')
                      ->from('follows as f1')
                      ->join('follows as f2', 'f1.followed_id', '=', 'f2.follower_id')
                      ->where('f1.follower_id', $user->id)
                      ->where('f2.followed_id', '!=', $user->id);
            })
            ->whereNotIn('id', function ($query) use ($user) {
                $query->select('followed_id')
                      ->from('follows')
                      ->where('follower_id', $user->id);
            })
            ->where('id', '!=', $user->id)
            ->select(['id', 'display_name', 'username', 'avatar', 'followers_count'])
            ->orderByDesc('followers_count')
            ->limit($limit)
            ->get();
    }

    /**
     * Recalculate follow counts for a user or all users.
     */
    public function recalculateFollowCounts(?User $user = null): array
    {
        $query = $user ? User::where('id', $user->id) : User::query();
        $updated = 0;

        $query->chunk(100, function ($users) use (&$updated) {
            foreach ($users as $user) {
                $followersCount = $user->followers()->count();
                $followingCount = $user->following()->count();

                if ($user->followers_count !== $followersCount || $user->following_count !== $followingCount) {
                    $user->update([
                        'followers_count' => $followersCount,
                        'following_count' => $followingCount,
                    ]);
                    $updated++;
                }
            }
        });

        return [
            'success' => true,
            'message' => "Updated follow counts for {$updated} users",
            'updated_count' => $updated
        ];
    }
}
