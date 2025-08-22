<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @OA\Schema(
 *     schema="Follow",
 *     type="object",
 *     title="Follow",
 *     description="User follow relationship model",
 *
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="follower_id", type="string", example="U-ABC1-DEF2", description="User ID of the person following"),
 *     @OA\Property(property="followed_id", type="string", example="U-XYZ3-UVW4", description="User ID of the person being followed"),
 *     @OA\Property(property="status", type="string", enum={"pending", "accepted"}, example="accepted", description="Status of the follow relationship"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 *
 * @OA\Schema(
 *     schema="FollowRequest",
 *     type="object",
 *     title="Follow Request",
 *     description="Follow request with user details",
 *
 *     @OA\Property(property="id", type="integer", example=1, description="Follow request ID"),
 *     @OA\Property(
 *         property="follower",
 *         type="object",
 *         description="User who sent the follow request",
 *         @OA\Property(property="id", type="string", example="U-ABC1-DEF2"),
 *         @OA\Property(property="display_name", type="string", example="John Doe"),
 *         @OA\Property(property="username", type="string", example="john_doe"),
 *         @OA\Property(property="avatar", type="string", example="https://example.com/avatar.jpg", nullable=true)
 *     ),
 *     @OA\Property(property="created_at", type="string", format="date-time", description="When the follow request was created")
 * )
 */
class Follow extends Model
{
    use HasFactory;

    protected $fillable = [
        'follower_id',
        'followed_id',
        'status',
    ];

    /**
     * Get the user who is following.
     */
    public function follower()
    {
        return $this->belongsTo(User::class, 'follower_id');
    }

    /**
     * Get the user who is being followed.
     */
    public function followed()
    {
        return $this->belongsTo(User::class, 'followed_id');
    }

    /**
     * Scope to get follows for a specific follower.
     */
    public function scopeForFollower($query, $followerId)
    {
        return $query->where('follower_id', $followerId);
    }

    /**
     * Scope to get follows for a specific user being followed.
     */
    public function scopeForFollowed($query, $followedId)
    {
        return $query->where('followed_id', $followedId);
    }

    /**
     * Scope to check if a specific follow relationship exists.
     */
    public function scopeRelationship($query, $followerId, $followedId)
    {
        return $query->where('follower_id', $followerId)
            ->where('followed_id', $followedId);
    }

    /**
     * Scope to get only accepted follows.
     */
    public function scopeAccepted($query)
    {
        return $query->where('status', 'accepted');
    }

    /**
     * Scope to get only pending follows.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}
