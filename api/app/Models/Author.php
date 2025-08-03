<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

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
                $model->{$model->getKeyName()} = 'A-' . strtoupper(Str::random(4)) . '-' . strtoupper(Str::random(4));
            }
        });
    }

    public function books()
    {
        return $this->belongsToMany(Book::class);
    }
}
