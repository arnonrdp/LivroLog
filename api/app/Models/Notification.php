<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * @OA\Schema(
 *     schema="Notification",
 *     type="object",
 *     title="Notification",
 *     description="User notification",
 *
 *     @OA\Property(property="id", type="string", example="N-3D6Y-9IO8"),
 *     @OA\Property(property="user_id", type="string", example="U-ABC1-DEF2"),
 *     @OA\Property(property="actor_id", type="string", example="U-XYZ3-UVW4"),
 *     @OA\Property(property="type", type="string", enum={"activity_liked", "activity_commented", "new_follower", "follow_request", "follow_accepted"}, example="activity_liked"),
 *     @OA\Property(property="notifiable_type", type="string", example="Activity"),
 *     @OA\Property(property="notifiable_id", type="string", example="A-DEF5-GHI6"),
 *     @OA\Property(property="data", type="object", nullable=true),
 *     @OA\Property(property="read_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class Notification extends Model
{
    use HasFactory;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'actor_id',
        'type',
        'notifiable_type',
        'notifiable_id',
        'data',
        'read_at',
    ];

    protected $casts = [
        'data' => 'array',
        'read_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = 'N-'.strtoupper(Str::random(4)).'-'.strtoupper(Str::random(4));
            }
        });
    }

    /**
     * Get the user who receives the notification.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the user who triggered the notification.
     */
    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * Get the notifiable entity (polymorphic).
     */
    public function notifiable()
    {
        return $this->morphTo();
    }

    /**
     * Scope to get unread notifications.
     */
    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    /**
     * Scope to get notifications for a specific user.
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }
}
