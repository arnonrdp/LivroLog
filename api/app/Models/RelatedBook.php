<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @OA\Schema(
 *     schema="RelatedBook",
 *     type="object",
 *     title="RelatedBook",
 *     description="Related book relationship model",
 *
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="book_id", type="string", example="B-1ABC-2DEF"),
 *     @OA\Property(property="related_book_id", type="string", example="B-3XYZ-4UVW"),
 *     @OA\Property(property="relationship_type", type="string", example="similar", description="Type of relationship between books"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class RelatedBook extends Model
{
    protected $fillable = [
        'book_id',
        'related_book_id',
        'relationship_type',
    ];
}
