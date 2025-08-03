<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @OA\Schema(
 *     schema="Showcase",
 *     type="object",
 *     title="Showcase",
 *     description="Showcase book model",
 *     @OA\Property(property="id", type="integer", format="int64", example=1),
 *     @OA\Property(property="title", type="string", example="The Lord of the Rings"),
 *     @OA\Property(property="authors", type="string", example="J.R.R. Tolkien"),
 *     @OA\Property(property="isbn", type="string", example="9788533613379"),
 *     @OA\Property(property="description", type="string"),
 *     @OA\Property(property="thumbnail", type="string", format="url", nullable=true),
 *     @OA\Property(property="link", type="string", format="url", nullable=true),
 *     @OA\Property(property="publisher", type="string", example="HarperCollins"),
 *     @OA\Property(property="language", type="string", example="en"),
 *     @OA\Property(property="edition", type="string", nullable=true),
 *     @OA\Property(property="order_index", type="integer", example=1),
 *     @OA\Property(property="is_active", type="boolean", example=true),
 *     @OA\Property(property="notes", type="string", nullable=true),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class Showcase extends Model
{
    protected $table = 'showcase';

    protected $fillable = [
        'title',
        'authors',
        'isbn',
        'description',
        'thumbnail',
        'link',
        'publisher',
        'language',
        'edition',
        'order_index',
        'is_active',
        'notes'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'order_index' => 'integer'
    ];

    /**
     * Scope to get only active books
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to order by display order
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('order_index')->orderBy('created_at', 'desc');
    }
}
