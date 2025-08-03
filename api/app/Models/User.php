<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Str;

/**
 * @OA\Schema(
 *     schema="User",
 *     type="object",
 *     title="User",
 *     description="User model",
 *     @OA\Property(property="id", type="string", example="U-1ABC-2DEF"),
 *     @OA\Property(property="google_id", type="string", example="123456789012345678901", nullable=true),
 *     @OA\Property(property="display_name", type="string", example="John Doe"),
 *     @OA\Property(property="email", type="string", format="email", example="john@example.com"),
 *     @OA\Property(property="username", type="string", example="john_doe"),
 *     @OA\Property(property="avatar", type="string", example="https://lh3.googleusercontent.com/...", nullable=true),
 *     @OA\Property(property="shelf_name", type="string", example="John's Library"),
 *     @OA\Property(property="role", type="string", enum={"admin", "user"}, example="user"),
 *     @OA\Property(property="email_verified", type="boolean", example=false),
 *     @OA\Property(property="email_verified_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */

    protected $fillable = [
        'google_id',
        'display_name',
        'email',
        'username',
        'shelf_name',
        'password',
        'locale',
        'role',
        'avatar',
        'email_verified',
    ];

    public $incrementing = false;
    protected $keyType = 'string';

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = 'U-' . strtoupper(Str::random(4)) . '-' . strtoupper(Str::random(4));
            }
        });
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the books that belong to the user.
     */
    public function books()
    {
        return $this->belongsToMany(Book::class, 'users_books')
                    ->withPivot('added_at', 'read_at')
                    ->withTimestamps();
    }

    /**
     * Check if the user is an admin.
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }
}
