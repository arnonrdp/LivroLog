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
        $currentUser = $request->user();

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
            'likes_count' => $this->likes_count ?? 0,
            'comments_count' => $this->comments_count ?? 0,
            'is_liked' => $currentUser
                ? $this->likes()->where('user_id', $currentUser->id)->exists()
                : false,
        ];
    }

    /**
     * Format the subject using eager-loaded relation to avoid N+1 queries.
     */
    private function formatSubject(): ?array
    {
        // Use eager-loaded subject relation when available
        $subject = $this->relationLoaded('subject') ? $this->subject : null;

        if ($this->subject_type === 'Book') {
            if (! $subject instanceof Book) {
                return null;
            }

            return [
                'type' => 'Book',
                'id' => $subject->id,
                'title' => $subject->title,
                'authors' => $subject->authors,
                'thumbnail' => $subject->thumbnail,
            ];
        }

        if ($this->subject_type === 'User') {
            if (! $subject instanceof User) {
                return null;
            }

            return [
                'type' => 'User',
                'id' => $subject->id,
                'display_name' => $subject->display_name,
                'username' => $subject->username,
                'avatar' => $subject->avatar,
            ];
        }

        if ($this->subject_type === 'Review') {
            if (! $subject instanceof Review) {
                return null;
            }

            // Use eager-loaded book relation on review
            $book = $subject->relationLoaded('book') ? $subject->book : null;

            return [
                'type' => 'Review',
                'id' => $subject->id,
                'rating' => $subject->rating,
                'book' => $book ? [
                    'id' => $book->id,
                    'title' => $book->title,
                    'authors' => $book->authors,
                    'thumbnail' => $book->thumbnail,
                ] : null,
            ];
        }

        return null;
    }
}
