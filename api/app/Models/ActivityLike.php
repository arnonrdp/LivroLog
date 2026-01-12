<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * @OA\Schema(
 *     schema="ActivityLike",
 *     type="object",
 *     title="ActivityLike",
 *     description="Like on an activity",
 *
 *     @OA\Property(property="id", type="string", example="L-3D6Y-9IO8"),
 *     @OA\Property(property="user_id", type="string", example="U-ABC1-DEF2"),
 *     @OA\Property(property="activity_id", type="string", example="A-XYZ3-UVW4"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class ActivityLike extends Model
{
    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'activity_id',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = 'L-'.strtoupper(Str::random(4)).'-'.strtoupper(Str::random(4));
            }
        });
    }

    /**
     * Get the user who liked the activity.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the liked activity.
     */
    public function activity()
    {
        return $this->belongsTo(Activity::class);
    }
}
