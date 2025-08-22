<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * @OA\Schema(
 *     schema="Book",
 *     type="object",
 *     title="Book",
 *     description="Book model with extended information",
 *
 *     @OA\Property(property="id", type="string", example="B-3D6Y-9IO8"),
 *     @OA\Property(property="google_id", type="string", example="8fcQEAAAQBAJ", nullable=true),
 *     @OA\Property(property="isbn", type="string", example="9788533613379", nullable=true),
 *     @OA\Property(property="title", type="string", example="The Lord of the Rings"),
 *     @OA\Property(property="subtitle", type="string", example="The Fellowship of the Ring", nullable=true),
 *     @OA\Property(property="authors", type="string", example="J.R.R. Tolkien"),
 *     @OA\Property(property="description", type="string", nullable=true),
 *     @OA\Property(property="thumbnail", type="string", format="url", nullable=true),
 *     @OA\Property(property="language", type="string", example="en", nullable=true),
 *     @OA\Property(property="publisher", type="string", example="HarperCollins", nullable=true),
 *     @OA\Property(property="published_date", type="string", format="date", nullable=true),
 *     @OA\Property(property="page_count", type="integer", example=423, nullable=true),
 *     @OA\Property(property="format", type="string", enum={"hardcover", "paperback", "ebook", "audiobook"}, nullable=true),
 *     @OA\Property(property="print_type", type="string", example="BOOK", nullable=true),
 *     @OA\Property(property="height", type="number", format="float", example=198.5, nullable=true, description="Height in millimeters"),
 *     @OA\Property(property="width", type="number", format="float", example=129.2, nullable=true, description="Width in millimeters"),
 *     @OA\Property(property="thickness", type="number", format="float", example=23.1, nullable=true, description="Thickness in millimeters"),
 *     @OA\Property(property="maturity_rating", type="string", enum={"NOT_MATURE", "MATURE"}, nullable=true),
 *     @OA\Property(property="categories", type="array", @OA\Items(type="string"), nullable=true),
 *     @OA\Property(property="industry_identifiers", type="array", @OA\Items(type="object"), nullable=true),
 *     @OA\Property(property="info_quality", type="string", enum={"basic", "enhanced", "complete"}, example="enhanced"),
 *     @OA\Property(property="enriched_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="edition", type="string", nullable=true),
 *     @OA\Property(property="pivot", type="object", nullable=true, description="User-specific book data (when fetched from user's library)",
 *         @OA\Property(property="added_at", type="string", format="date-time", description="When the book was added to user's library"),
 *         @OA\Property(property="read_at", type="string", format="date", nullable=true, description="Date when the book was read"),
 *         @OA\Property(property="is_private", type="boolean", description="Whether this book is private in the user's library"),
 *         @OA\Property(property="reading_status", type="string", enum={"want_to_read", "reading", "read", "abandoned", "on_hold", "re_reading"}, description="Current reading status")
 *     ),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class Book extends Model
{
    use HasFactory;

    // Constants to avoid duplication
    private const DECIMAL_PRECISION = 'decimal:2';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'isbn',
        'google_id',
        'title',
        'subtitle',
        'authors',
        'description',
        'thumbnail',
        'language',
        'publisher',
        'published_date',
        'page_count',
        'format',
        'print_type',
        'height',
        'width',
        'thickness',
        'maturity_rating',
        'categories',
        'industry_identifiers',
        'info_quality',
        'enriched_at',
        'edition',
    ];

    protected $casts = [
        'categories' => 'array',
        'industry_identifiers' => 'array',
        'published_date' => 'date',
        'enriched_at' => 'datetime',
        'height' => self::DECIMAL_PRECISION,
        'width' => self::DECIMAL_PRECISION,
        'thickness' => self::DECIMAL_PRECISION,
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = 'B-'.strtoupper(Str::random(4)).'-'.strtoupper(Str::random(4));
            }
        });
    }

    /**
     * The authors that belong to the book.
     */
    public function authors()
    {
        return $this->belongsToMany(Author::class);
    }

    /**
     * Get the users that own this book.
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'users_books')
            ->withPivot('added_at', 'read_at')
            ->withTimestamps();
    }

    /**
     * Get related books.
     */
    public function relatedBooks()
    {
        return $this->belongsToMany(Book::class, 'related_books', 'book_id', 'related_book_id');
    }

    /**
     * Get the reviews for the book.
     */
    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    /**
     * Get only public reviews for the book.
     */
    public function publicReviews()
    {
        return $this->hasMany(Review::class)->public();
    }

    /**
     * Get the average rating for the book.
     */
    public function getAverageRatingAttribute()
    {
        return $this->reviews()->public()->avg('rating');
    }

    /**
     * Get the total number of reviews for the book.
     */
    public function getReviewsCountAttribute()
    {
        return $this->reviews()->public()->count();
    }

    /**
     * Get formatted publication date with appropriate precision
     */
    public function getFormattedPublishedDateAttribute()
    {
        if (! $this->published_date) {
            return null;
        }

        $date = $this->published_date;
        $format = 'Y-m-d'; // Default: full date precision

        // Determine appropriate format based on date precision
        if ($date->format('m-d') === '01-01') {
            // If date is January 1st, likely we only have year precision
            $format = 'Y';
        } elseif ($date->format('d') === '01') {
            // If day is 1st, likely we only have year-month precision
            $format = 'Y-m';
        }

        return $date->format($format);
    }

    /**
     * Append formatted date and review stats to JSON output
     */
    protected $appends = ['formatted_published_date', 'average_rating', 'reviews_count'];
}
