<?php

namespace App\Http\Resources;

use App\Models\Book;
use App\Models\Review;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActivityResource extends JsonResource
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
            'type' => $this->type,
            'created_at' => $this->created_at,
            'user' => [
                'id' => $this->user?->id,
                'display_name' => $this->user?->display_name,
                'username' => $this->user?->username,
                'avatar' => $this->user?->avatar,
            ],
            'subject' => $this->formatSubject(),
            'metadata' => $this->metadata,
        ];
    }

    private function formatSubject(): ?array
    {
        if ($this->subject_type === 'Book') {
            $book = Book::find($this->subject_id);
            if (! $book) {
                return null;
            }

            return [
                'type' => 'Book',
                'id' => $book->id,
                'title' => $book->title,
                'authors' => $book->authors,
                'thumbnail' => $book->thumbnail,
            ];
        }

        if ($this->subject_type === 'User') {
            $user = User::find($this->subject_id);
            if (! $user) {
                return null;
            }

            return [
                'type' => 'User',
                'id' => $user->id,
                'display_name' => $user->display_name,
                'username' => $user->username,
                'avatar' => $user->avatar,
            ];
        }

        if ($this->subject_type === 'Review') {
            $review = Review::with('book')->find($this->subject_id);
            if (! $review) {
                return null;
            }

            return [
                'type' => 'Review',
                'id' => $review->id,
                'rating' => $review->rating,
                'book' => [
                    'id' => $review->book?->id,
                    'title' => $review->book?->title,
                    'authors' => $review->book?->authors,
                    'thumbnail' => $review->book?->thumbnail,
                ],
            ];
        }

        return null;
    }
}
