<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="ReviewResource",
 *     type="object",
 *     title="Review Resource",
 *     description="Review resource with user and book information",
 *
 *     @OA\Property(property="id", type="string", example="R-3D6Y-9IO8", description="Review ID"),
 *     @OA\Property(property="user_id", type="string", example="U-ABC1-DEF2", description="User ID who wrote the review"),
 *     @OA\Property(property="book_id", type="string", example="B-XYZ3-UVW4", description="Book ID being reviewed"),
 *     @OA\Property(property="title", type="string", example="Amazing book!", nullable=true, description="Review title"),
 *     @OA\Property(property="content", type="string", example="This book was incredible...", description="Review content"),
 *     @OA\Property(property="rating", type="integer", minimum=1, maximum=5, example=5, description="Rating from 1 to 5"),
 *     @OA\Property(property="visibility_level", type="string", enum={"private", "friends", "public"}, example="public", description="Review visibility level"),
 *     @OA\Property(property="is_spoiler", type="boolean", example=false, description="Whether the review contains spoilers"),
 *     @OA\Property(property="helpful_count", type="integer", example=12, description="Number of users who found this review helpful"),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2025-08-04T12:57:42.000000Z", description="Review creation timestamp"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-08-04T12:57:42.000000Z", description="Review last update timestamp"),
 *     @OA\Property(property="user", type="object", nullable=true, description="User who wrote the review",
 *         @OA\Property(property="id", type="string", example="U-ABC1-DEF2"),
 *         @OA\Property(property="display_name", type="string", example="John Doe"),
 *         @OA\Property(property="username", type="string", example="johndoe"),
 *         @OA\Property(property="avatar", type="string", nullable=true, example="https://example.com/avatar.jpg")
 *     ),
 *     @OA\Property(property="book", type="object", nullable=true, description="Book being reviewed",
 *         @OA\Property(property="id", type="string", example="B-XYZ3-UVW4"),
 *         @OA\Property(property="title", type="string", example="The Great Book"),
 *         @OA\Property(property="thumbnail", type="string", nullable=true, example="https://example.com/book-cover.jpg"),
 *         @OA\Property(property="formatted_published_date", type="string", nullable=true, example="2023"),
 *         @OA\Property(property="average_rating", type="string", nullable=true, example="4.5000"),
 *         @OA\Property(property="reviews_count", type="integer", example=25)
 *     )
 * )
 */
class ReviewResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'book_id' => $this->book_id,
            'title' => $this->title,
            'content' => $this->content,
            'rating' => $this->rating,
            'visibility_level' => $this->visibility_level,
            'is_spoiler' => $this->is_spoiler,
            'helpful_count' => $this->helpful_count,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'user' => $this->whenLoaded('user', [
                'id' => $this->user?->id,
                'display_name' => $this->user?->display_name,
                'username' => $this->user?->username,
                'avatar' => $this->user?->avatar,
            ]),
            'book' => $this->whenLoaded('book', [
                'id' => $this->book?->id,
                'title' => $this->book?->title,
                'thumbnail' => $this->book?->thumbnail,
                'formatted_published_date' => $this->book?->formatted_published_date,
                'average_rating' => $this->book?->average_rating,
                'reviews_count' => $this->book?->reviews_count,
            ]),
        ];
    }
}
