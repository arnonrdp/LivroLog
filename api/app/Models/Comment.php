<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * @OA\Schema(
 *     schema="Comment",
 *     type="object",
 *     title="Comment",
 *     description="Comment on an activity",
 *
 *     @OA\Property(property="id", type="string", example="C-3D6Y-9IO8"),
 *     @OA\Property(property="user_id", type="string", example="U-ABC1-DEF2"),
 *     @OA\Property(property="activity_id", type="string", example="A-XYZ3-UVW4"),
 *     @OA\Property(property="content", type="string", example="Great book choice!"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class Comment extends Model
{
    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'activity_id',
        'content',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = 'C-'.strtoupper(Str::random(4)).'-'.strtoupper(Str::random(4));
            }
        });
    }

    /**
     * Get the user who wrote the comment.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the activity that was commented on.
     */
    public function activity()
    {
        return $this->belongsTo(Activity::class);
    }
}
