<?php

namespace App\Services;

use App\Models\Follow;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

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
                'code' => 'CANNOT_FOLLOW_SELF',
            ];
        }

        // Check if already following (but allow pending requests to be sent again)
        $existingFollow = Follow::where('follower_id', $follower->id)
            ->where('followed_id', $following->id)
            ->first();
            
        if ($existingFollow && $existingFollow->status === 'accepted') {
            return [
                'success' => false,
                'message' => 'Already following this user',
                'code' => 'ALREADY_FOLLOWING',
            ];
        }
        
        // If there's already a pending request, return the existing status
        if ($existingFollow && $existingFollow->status === 'pending') {
            return [
                'success' => true,
                'message' => 'Follow request already sent',
                'data' => [
                    'follower' => $follower->only(['id', 'display_name', 'username']),
                    'following' => $following->only(['id', 'display_name', 'username']),
                    'following_count' => $follower->following_count,
                    'followers_count' => $following->followers_count,
                    'status' => 'pending',
                ],
            ];
        }

        DB::transaction(function () use ($follower, $following) {
            // Determine status based on whether the followed user is private
            $status = $following->is_private ? 'pending' : 'accepted';
            
            // Create follow relationship
            Follow::create([
                'follower_id' => $follower->id,
                'followed_id' => $following->id,
                'status' => $status,
            ]);

            // Only update counters if the follow is accepted immediately (public profile)
            if ($status === 'accepted') {
                $follower->increment('following_count');
                $following->increment('followers_count');
            }
        });

        $message = $following->is_private ? 
            'Follow request sent' : 
            'Successfully followed user';
            
        return [
            'success' => true,
            'message' => $message,
            'data' => [
                'follower' => $follower->only(['id', 'display_name', 'username']),
                'following' => $following->only(['id', 'display_name', 'username']),
                'following_count' => $follower->fresh()->following_count,
                'followers_count' => $following->fresh()->followers_count,
                'status' => $following->is_private ? 'pending' : 'accepted',
            ],
        ];
    }

    /**
     * Unfollow a user.
     */
    public function unfollow(User $follower, User $following): array
    {
        // Check if not following
        $existingFollow = Follow::where('follower_id', $follower->id)
            ->where('followed_id', $following->id)
            ->first();
            
        if (!$existingFollow) {
            return [
                'success' => false,
                'message' => 'Not following this user',
                'code' => 'NOT_FOLLOWING',
            ];
        }

        $wasPending = $existingFollow->status === 'pending';
        
        DB::transaction(function () use ($follower, $following, $existingFollow) {
            // Remove follow relationship
            $existingFollow->delete();

            // Only update counters if it was an accepted follow
            if ($existingFollow->status === 'accepted') {
                $follower->decrement('following_count');
                $following->decrement('followers_count');
            }
        });

        $message = $wasPending ? 'Follow request removed' : 'Successfully unfollowed user';

        return [
            'success' => true,
            'message' => $message,
            'data' => [
                'follower' => $follower->only(['id', 'display_name', 'username']),
                'following' => $following->only(['id', 'display_name', 'username']),
                'following_count' => $follower->fresh()->following_count,
                'followers_count' => $following->fresh()->followers_count,
                'was_pending' => $wasPending,
            ],
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
     * Accept a follow request.
     */
    public function acceptFollowRequest(int $followId, User $user): array
    {
        $followRequest = Follow::where('id', $followId)
            ->where('followed_id', $user->id)
            ->where('status', 'pending')
            ->first();

        if (!$followRequest) {
            return [
                'success' => false,
                'message' => 'Follow request not found',
                'code' => 'REQUEST_NOT_FOUND',
            ];
        }

        DB::transaction(function () use ($followRequest) {
            // Update status to accepted
            $followRequest->update(['status' => 'accepted']);

            // Update counters
            $follower = User::find($followRequest->follower_id);
            $following = User::find($followRequest->followed_id);
            
            $follower->increment('following_count');
            $following->increment('followers_count');
        });

        return [
            'success' => true,
            'message' => 'Follow request accepted',
            'data' => [
                'follow_id' => $followRequest->id,
                'follower' => $followRequest->follower->only(['id', 'display_name', 'username']),
            ],
        ];
    }

    /**
     * Reject a follow request.
     */
    public function rejectFollowRequest(int $followId, User $user): array
    {
        $followRequest = Follow::where('id', $followId)
            ->where('followed_id', $user->id)
            ->where('status', 'pending')
            ->first();

        if (!$followRequest) {
            return [
                'success' => false,
                'message' => 'Follow request not found',
                'code' => 'REQUEST_NOT_FOUND',
            ];
        }

        $followerData = $followRequest->follower->only(['id', 'display_name', 'username']);
        $followRequest->delete();

        return [
            'success' => true,
            'message' => 'Follow request rejected',
            'data' => [
                'follower' => $followerData,
            ],
        ];
    }

    /**
     * Get pending follow requests for a user.
     */
    public function getPendingFollowRequests(User $user): Collection
    {
        return Follow::where('followed_id', $user->id)
            ->where('status', 'pending')
            ->with('follower:id,display_name,username,avatar')
            ->orderBy('created_at', 'desc')
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
            'updated_count' => $updated,
        ];
    }
}
