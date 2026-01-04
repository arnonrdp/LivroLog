<?php

namespace App\Observers;

use App\Models\Activity;
use App\Models\Follow;

class FollowObserver
{
    /**
     * Handle the Follow "created" event.
     */
    public function created(Follow $follow): void
    {
        // Only create activity when follow is immediately accepted (public profile)
        if ($follow->status === 'accepted') {
            $this->createFollowActivity($follow);
        }
    }

    /**
     * Handle the Follow "updated" event.
     */
    public function updated(Follow $follow): void
    {
        // Create activity when pending follow is accepted
        if ($follow->isDirty('status') && $follow->status === 'accepted') {
            $this->createFollowActivity($follow);
        }
    }

    private function createFollowActivity(Follow $follow): void
    {
        Activity::create([
            'user_id' => $follow->follower_id,
            'type' => 'user_followed',
            'subject_type' => 'User',
            'subject_id' => $follow->followed_id,
        ]);
    }
}
