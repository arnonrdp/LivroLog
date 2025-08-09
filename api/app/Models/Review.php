<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

/**
 * @OA\Schema(
 *     schema="Review",
 *     type="object",
 *     title="Review",
 *     description="Book review model",
 *     @OA\Property(property="id", type="string", example="R-3D6Y-9IO8"),
 *     @OA\Property(property="user_id", type="string", example="U-ABC1-DEF2"),
 *     @OA\Property(property="book_id", type="string", example="B-XYZ3-UVW4"),
 *     @OA\Property(property="title", type="string", example="Amazing book!", nullable=true),
 *     @OA\Property(property="content", type="string", example="This book was incredible..."),
 *     @OA\Property(property="rating", type="integer", minimum=1, maximum=5, example=5),
 *     @OA\Property(property="visibility_level", type="string", enum={"private", "friends", "public"}, example="public"),
 *     @OA\Property(property="is_spoiler", type="boolean", example=false),
 *     @OA\Property(property="helpful_count", type="integer", example=12),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class Review extends Model
{
    use HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'book_id',
        'title',
        'content',
        'rating',
        'visibility_level',
        'is_spoiler',
        'helpful_count',
    ];

    protected $casts = [
        'is_spoiler' => 'boolean',
        'helpful_count' => 'integer',
        'rating' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = 'R-' . strtoupper(Str::random(4)) . '-' . strtoupper(Str::random(4));
            }
        });
    }

    /**
     * Get the user that owns the review.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the book that the review belongs to.
     */
    public function book()
    {
        return $this->belongsTo(Book::class);
    }

    /**
     * Scope a query to only include public reviews.
     */
    public function scopePublic($query)
    {
        return $query->where('visibility_level', 'public');
    }

    /**
     * Scope a query to only include private reviews.
     */
    public function scopePrivate($query)
    {
        return $query->where('visibility_level', 'private');
    }

    /**
     * Scope a query to only include reviews visible to friends.
     */
    public function scopeFriends($query)
    {
        return $query->where('visibility_level', 'friends');
    }

    /**
     * Scope a query to filter by rating.
     */
    public function scopeByRating($query, $rating)
    {
        return $query->where('rating', $rating);
    }

    /**
     * Scope to get reviews for a specific book.
     */
    public function scopeForBook($query, $bookId)
    {
        return $query->where('book_id', $bookId);
    }

    /**
     * Scope to get reviews by a specific user.
     */
    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }
}
