<?php

namespace App\Models;

use App\Events\BookCreated;
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
 *     @OA\Property(property="amazon_asin", type="string", example="B08LPMFDQC", nullable=true),
 *     @OA\Property(property="amazon_rating", type="number", format="float", example=4.5, nullable=true, description="Amazon average star rating (1.0 to 5.0)"),
 *     @OA\Property(property="amazon_rating_count", type="integer", example=1234, nullable=true, description="Number of Amazon customer reviews"),
 *     @OA\Property(property="asin_status", type="string", enum={"pending", "processing", "completed", "failed"}, example="completed"),
 *     @OA\Property(property="asin_processed_at", type="string", format="date-time", nullable=true),
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
        'amazon_asin',
        'amazon_rating',
        'amazon_rating_count',
        'asin_status',
        'asin_processed_at',
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
        'asin_processed_at' => 'datetime',
        'height' => self::DECIMAL_PRECISION,
        'width' => self::DECIMAL_PRECISION,
        'thickness' => self::DECIMAL_PRECISION,
        'amazon_rating' => self::DECIMAL_PRECISION,
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = 'B-'.strtoupper(Str::random(4)).'-'.strtoupper(Str::random(4));
            }
        });

        static::created(function ($model) {
            // Dispatch BookCreated event after a book is successfully created
            BookCreated::dispatch($model);
        });
    }

    /**
     * Retrieve the model for a bound value.
     *
     * Allows route model binding to work with both internal book IDs (B-XXXX-XXXX)
     * and Google Books IDs for better API flexibility.
     *
     * @param  mixed  $value
     * @param  string|null  $field
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function resolveRouteBinding($value, $field = null)
    {
        return $this->where('id', $value)
            ->orWhere('google_id', $value)
            ->first();
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
            ->using(UserBook::class)
            ->withPivot('added_at', 'read_at', 'is_private', 'reading_status')
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
     * Get sanitized description with preserved inline formatting
     */
    public function getSanitizedDescriptionAttribute()
    {
        if (! $this->description) {
            return null;
        }

        // Decode HTML entities first
        $text = html_entity_decode($this->description, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Convert Amazon-style span tags to semantic tags first
        // Bold and italic combination
        $text = preg_replace('/<span[^>]*class="[^"]*a-text-bold\s+a-text-italic[^"]*"[^>]*>(.*?)<\/span>/is', '<b><i>$1</i></b>', $text);
        $text = preg_replace('/<span[^>]*class="[^"]*a-text-italic\s+a-text-bold[^"]*"[^>]*>(.*?)<\/span>/is', '<b><i>$1</i></b>', $text);

        // Just bold
        $text = preg_replace('/<span[^>]*class="[^"]*a-text-bold[^"]*"[^>]*>(.*?)<\/span>/is', '<b>$1</b>', $text);

        // Just italic
        $text = preg_replace('/<span[^>]*class="[^"]*a-text-italic[^"]*"[^>]*>(.*?)<\/span>/is', '<i>$1</i>', $text);

        // Now convert semantic tags to markdown-style markers
        // Bold tags
        $text = preg_replace('/<(b|strong)>/i', '**', $text);
        $text = preg_replace('/<\/(b|strong)>/i', '**', $text);

        // Italic tags
        $text = preg_replace('/<(i|em)>/i', '__', $text);
        $text = preg_replace('/<\/(i|em)>/i', '__', $text);

        // Underline tags
        $text = preg_replace('/<u>/i', '~~', $text);
        $text = preg_replace('/<\/u>/i', '~~', $text);

        // Replace common HTML tags with appropriate formatting
        // Preserve line breaks from <br> and <p> tags
        $text = preg_replace('/<br\s*\/?>/i', "\n", $text);
        $text = preg_replace('/<\/p>/i', "\n\n", $text);
        $text = preg_replace('/<p[^>]*>/i', '', $text);

        // Replace list items with bullet points
        $text = preg_replace('/<li[^>]*>/i', '• ', $text);
        $text = preg_replace('/<\/li>/i', "\n", $text);

        // Add line breaks for headers
        $text = preg_replace('/<h[1-6][^>]*>/i', "\n", $text);
        $text = preg_replace('/<\/h[1-6]>/i', "\n", $text);

        // Remove all remaining HTML tags (like span, div, etc)
        $text = strip_tags($text);

        // Clean up multiple spaces and line breaks
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n\s*\n\s*\n/', "\n\n", $text);

        // Trim the result
        return trim($text);
    }

    /**
     * Parse text with markdown-like markers into structured spans
     */
    private function parseInlineFormatting($text)
    {
        $result = [];
        $parts = preg_split('/(\*\*|__|~~)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE);

        $currentStyle = [];
        $buffer = '';

        for ($i = 0; $i < count($parts); $i++) {
            $part = $parts[$i];

            if ($part === '**') {
                // Toggle bold
                if (! empty($buffer)) {
                    $result[] = ['text' => $buffer, 'style' => $currentStyle];
                    $buffer = '';
                }
                if (in_array('bold', $currentStyle)) {
                    $currentStyle = array_diff($currentStyle, ['bold']);
                } else {
                    $currentStyle[] = 'bold';
                }
            } elseif ($part === '__') {
                // Toggle italic
                if (! empty($buffer)) {
                    $result[] = ['text' => $buffer, 'style' => $currentStyle];
                    $buffer = '';
                }
                if (in_array('italic', $currentStyle)) {
                    $currentStyle = array_diff($currentStyle, ['italic']);
                } else {
                    $currentStyle[] = 'italic';
                }
            } elseif ($part === '~~') {
                // Toggle underline
                if (! empty($buffer)) {
                    $result[] = ['text' => $buffer, 'style' => $currentStyle];
                    $buffer = '';
                }
                if (in_array('underline', $currentStyle)) {
                    $currentStyle = array_diff($currentStyle, ['underline']);
                } else {
                    $currentStyle[] = 'underline';
                }
            } else {
                $buffer .= $part;
            }
        }

        // Add any remaining text
        if (! empty($buffer)) {
            $result[] = ['text' => $buffer, 'style' => array_values($currentStyle)];
        }

        return $result;
    }

    /**
     * Get formatted description for display (preserves basic formatting)
     */
    public function getFormattedDescriptionAttribute()
    {
        if (! $this->description) {
            return null;
        }

        $sanitized = $this->sanitized_description;

        // Convert line breaks to proper structure for frontend display
        $paragraphs = explode("\n\n", $sanitized);
        $formatted = array_map(function ($p) {
            $p = trim($p);
            if (empty($p)) {
                return '';
            }

            // If it starts with a bullet, create list
            if (strpos($p, '•') === 0) {
                $items = explode("\n", $p);
                $listItems = array_map(function ($item) {
                    $cleanItem = trim(str_replace('•', '', $item));

                    return $this->parseInlineFormatting($cleanItem);
                }, array_filter($items));

                return ['type' => 'list', 'items' => $listItems];
            }

            // Otherwise it's a paragraph with inline formatting
            return [
                'type' => 'paragraph',
                'content' => $this->parseInlineFormatting($p),
            ];
        }, $paragraphs);

        return array_values(array_filter($formatted));
    }

    /**
     * Get Amazon purchase links for all regions
     */
    public function getAmazonLinksAttribute(): array
    {
        $service = app(\App\Services\AmazonLinkEnrichmentService::class);

        // Use getAttributes() instead of toArray() to avoid infinite recursion
        return $service->generateAllRegionLinks($this->getAttributes());
    }

    /**
     * Append formatted date and review stats to JSON output
     */
    protected $appends = ['formatted_published_date', 'average_rating', 'reviews_count', 'formatted_description', 'amazon_links'];
}
