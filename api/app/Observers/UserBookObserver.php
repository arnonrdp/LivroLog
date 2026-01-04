<?php

namespace App\Observers;

use App\Models\Activity;
use App\Models\UserBook;

class UserBookObserver
{
    /**
     * Handle the UserBook "created" event.
     */
    public function created(UserBook $userBook): void
    {
        if ($userBook->is_private) {
            return;
        }

        Activity::create([
            'user_id' => $userBook->user_id,
            'type' => 'book_added',
            'subject_type' => 'Book',
            'subject_id' => $userBook->book_id,
        ]);
    }

    /**
     * Handle the UserBook "updated" event.
     */
    public function updated(UserBook $userBook): void
    {
        if ($userBook->is_private) {
            return;
        }

        // Check if reading_status changed
        if ($userBook->isDirty('reading_status')) {
            $newStatus = $userBook->reading_status;

            if ($newStatus === 'reading') {
                Activity::create([
                    'user_id' => $userBook->user_id,
                    'type' => 'book_started',
                    'subject_type' => 'Book',
                    'subject_id' => $userBook->book_id,
                ]);
            } elseif ($newStatus === 'read') {
                Activity::create([
                    'user_id' => $userBook->user_id,
                    'type' => 'book_read',
                    'subject_type' => 'Book',
                    'subject_id' => $userBook->book_id,
                ]);
            }
        }
    }
}
