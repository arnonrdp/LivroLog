<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * @OA\Schema(
 *     schema="Tag",
 *     type="object",
 *     title="Tag",
 *     description="User-defined tag for organizing books",
 *
 *     @OA\Property(property="id", type="string", example="T-1ABC-2DEF"),
 *     @OA\Property(property="user_id", type="string", example="U-1ABC-2DEF"),
 *     @OA\Property(property="name", type="string", example="Doação"),
 *     @OA\Property(property="color", type="string", example="#EF4444"),
 *     @OA\Property(property="books_count", type="integer", example=5, description="Number of books with this tag"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class Tag extends Model
{
    use HasFactory;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'user_id',
        'name',
        'color',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = 'T-'.strtoupper(Str::random(4)).'-'.strtoupper(Str::random(4));
            }
        });
    }

    /**
     * Get the user that owns this tag.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the books that have this tag for the tag's owner.
     */
    public function books()
    {
        return $this->belongsToMany(Book::class, 'user_book_tags')
            ->wherePivot('user_id', $this->user_id)
            ->withTimestamps();
    }

    /**
     * Get the count of books with this tag.
     */
    public function getBooksCountAttribute(): int
    {
        return $this->books()->count();
    }
}
