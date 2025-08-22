<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

/**
 * @OA\Schema(
 *     schema="User",
 *     type="object",
 *     title="User",
 *     description="User model",
 *
 *     @OA\Property(property="id", type="string", example="U-1ABC-2DEF"),
 *     @OA\Property(property="google_id", type="string", example="123456789012345678901", nullable=true),
 *     @OA\Property(property="display_name", type="string", example="John Doe"),
 *     @OA\Property(property="email", type="string", format="email", example="john@example.com"),
 *     @OA\Property(property="username", type="string", example="john_doe"),
 *     @OA\Property(property="avatar", type="string", example="https://lh3.googleusercontent.com/...", nullable=true),
 *     @OA\Property(property="shelf_name", type="string", example="John's Library"),
 *     @OA\Property(property="role", type="string", enum={"admin", "user"}, example="user"),
 *     @OA\Property(property="followers_count", type="integer", example=15),
 *     @OA\Property(property="following_count", type="integer", example=8),
 *     @OA\Property(property="is_private", type="boolean", example=false, description="Whether the user's profile and library are private"),
 *     @OA\Property(property="is_following", type="boolean", example=false, description="Whether the current user is following this user (only included when authenticated)"),
 *     @OA\Property(property="has_pending_follow_request", type="boolean", example=false, description="Whether the current user has a pending follow request to this user (only included when authenticated)"),
 *     @OA\Property(property="pending_follow_requests_count", type="integer", example=3, description="Number of pending follow requests for this user (only for private accounts)"),
 *     @OA\Property(property="email_verified", type="boolean", example=false),
 *     @OA\Property(property="email_verified_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'google_id',
        'display_name',
        'email',
        'username',
        'shelf_name',
        'password',
        'locale',
        'role',
        'avatar',
        'email_verified',
        'followers_count',
        'following_count',
        'is_private',
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = 'U-'.strtoupper(Str::random(4)).'-'.strtoupper(Str::random(4));
            }
        });
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'email_verified' => 'boolean',
            'is_private' => 'boolean',
            'followers_count' => 'integer',
            'following_count' => 'integer',
        ];
    }

    /**
     * Get the books that belong to the user.
     */
    public function books()
    {
        return $this->belongsToMany(Book::class, 'users_books')
            ->withPivot('added_at', 'read_at', 'is_private', 'reading_status')
            ->withTimestamps();
    }

    /**
     * Get the reviews written by the user.
     */
    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    /**
     * Check if the user is an admin.
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Get the users that this user is following.
     */
    public function following()
    {
        return $this->buildFollowRelationship('follower_id', 'followed_id');
    }

    /**
     * Get the users that are following this user.
     */
    public function followers()
    {
        return $this->buildFollowRelationship('followed_id', 'follower_id');
    }

    /**
     * Get the follow relationships where this user is the follower.
     */
    public function followingRelationships()
    {
        return $this->hasMany(Follow::class, 'follower_id');
    }

    /**
     * Get the follow relationships where this user is being followed.
     */
    public function followerRelationships()
    {
        return $this->hasMany(Follow::class, 'followed_id');
    }

    /**
     * Check if this user is following another user.
     */
    public function isFollowing(User $user): bool
    {
        return $this->checkFollowRelationship($user, 'following');
    }

    /**
     * Check if this user is followed by another user.
     */
    public function isFollowedBy(User $user): bool
    {
        return $this->checkFollowRelationship($user, 'followers');
    }

    /**
     * Build follow relationship with consistent configuration
     */
    private function buildFollowRelationship(string $foreignKey, string $relatedKey)
    {
        return $this->belongsToMany(User::class, 'follows', $foreignKey, $relatedKey)
            ->wherePivot('status', 'accepted')
            ->withTimestamps();
    }

    /**
     * Check follow relationship existence
     */
    private function checkFollowRelationship(User $user, string $relationshipType): bool
    {
        $column = $relationshipType === 'following' ? 'followed_id' : 'follower_id';

        return $this->$relationshipType()->where($column, $user->id)->exists();
    }

    /**
     * Get the count of pending follow requests for this user.
     */
    public function getPendingFollowRequestsCountAttribute(): int
    {
        if (! $this->is_private) {
            return 0;
        }

        return Follow::where('followed_id', $this->id)
            ->where('status', 'pending')
            ->count();
    }
}
