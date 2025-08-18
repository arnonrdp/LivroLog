<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * @OA\Schema(
 *     schema="Author",
 *     type="object",
 *     title="Author",
 *     description="Author model",
 *
 *     @OA\Property(property="id", type="string", example="A-1ABC-2DEF"),
 *     @OA\Property(property="name", type="string", example="J.R.R. Tolkien"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class Author extends Model
{
    use HasFactory;

    protected $fillable = [
        'id',
        'name',
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = 'A-'.strtoupper(Str::random(4)).'-'.strtoupper(Str::random(4));
            }
        });
    }

    public function books()
    {
        return $this->belongsToMany(Book::class);
    }
}
