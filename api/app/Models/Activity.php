<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * @OA\Schema(
 *     schema="Activity",
 *     type="object",
 *     title="Activity",
 *     description="User activity for feed",
 *
 *     @OA\Property(property="id", type="string", example="A-3D6Y-9IO8"),
 *     @OA\Property(property="user_id", type="string", example="U-ABC1-DEF2"),
 *     @OA\Property(property="type", type="string", enum={"book_added", "book_started", "book_read", "review_written", "user_followed"}, example="book_added"),
 *     @OA\Property(property="subject_type", type="string", example="Book"),
 *     @OA\Property(property="subject_id", type="string", example="B-XYZ3-UVW4"),
 *     @OA\Property(property="metadata", type="object", nullable=true),
 *     @OA\Property(property="created_at", type="string", format="date-time")
 * )
 */
class Activity extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'type',
        'subject_type',
        'subject_id',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = 'A-'.strtoupper(Str::random(4)).'-'.strtoupper(Str::random(4));
            }
            if (empty($model->created_at)) {
                $model->created_at = now();
            }
        });
    }

    /**
     * Get the user who performed the activity.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the subject of the activity (polymorphic).
     */
    public function subject()
    {
        return $this->morphTo();
    }

    /**
     * Scope to get activities for a specific user.
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to get activities of a specific type.
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope to get activities from users that a given user follows.
     */
    public function scopeFromFollowing($query, $userId)
    {
        $followingIds = Follow::where('follower_id', $userId)
            ->where('status', 'accepted')
            ->pluck('followed_id');

        return $query->whereIn('user_id', $followingIds);
    }

    /**
     * Get the likes for this activity.
     */
    public function likes()
    {
        return $this->hasMany(ActivityLike::class);
    }

    /**
     * Get the comments for this activity.
     */
    public function comments()
    {
        return $this->hasMany(Comment::class);
    }

    /**
     * Check if a user has liked this activity.
     */
    public function likedBy(User $user): bool
    {
        return $this->likes()->where('user_id', $user->id)->exists();
    }
}
