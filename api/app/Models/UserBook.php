<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @OA\Schema(
 *     schema="UserBook",
 *     type="object",
 *     title="UserBook",
 *     description="User book relationship (pivot table model)",
 *
 *     @OA\Property(property="user_id", type="string", example="U-1ABC-2DEF"),
 *     @OA\Property(property="book_id", type="string", example="B-3XYZ-4UVW"),
 *     @OA\Property(property="added_at", type="string", format="date-time", description="When the book was added to user's library"),
 *     @OA\Property(property="read_at", type="string", format="date", nullable=true, description="Date when the book was read"),
 *     @OA\Property(property="is_private", type="boolean", example=false, description="Whether this book is private in the user's library"),
 *     @OA\Property(property="reading_status", type="string", enum={"want_to_read", "reading", "read", "abandoned", "on_hold", "re_reading"}, example="read", description="User's reading status for this book"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class UserBook extends Model
{
    protected $table = 'users_books';

    protected $fillable = [
        'user_id',
        'book_id',
        'added_at',
        'read_at',
        'is_private',
        'reading_status',
    ];

    protected $casts = [
        'added_at' => 'datetime',
        'read_at' => 'date',
        'is_private' => 'boolean',
        'reading_status' => 'string',
    ];
}
