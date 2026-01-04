<?php

namespace App\Observers;

use App\Models\Activity;
use App\Models\Review;

class ReviewObserver
{
    /**
     * Handle the Review "created" event.
     */
    public function created(Review $review): void
    {
        if ($review->visibility_level === 'private') {
            return;
        }

        Activity::create([
            'user_id' => $review->user_id,
            'type' => 'review_written',
            'subject_type' => 'Review',
            'subject_id' => $review->id,
            'metadata' => [
                'book_id' => $review->book_id,
                'rating' => $review->rating,
            ],
        ]);
    }
}
