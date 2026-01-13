<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;

class NotificationService
{
    /**
     * Get paginated notifications for a user.
     */
    public function getNotifications(User $user, int $perPage = 20)
    {
        return Notification::forUser($user->id)
            ->with(['actor:id,display_name,username,avatar', 'notifiable'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Get unread count for a user.
     */
    public function getUnreadCount(User $user): int
    {
        return Notification::forUser($user->id)->unread()->count();
    }

    /**
     * Mark a notification as read.
     */
    public function markAsRead(User $user, Notification $notification): bool
    {
        if ($notification->user_id !== $user->id) {
            return false;
        }

        $notification->update(['read_at' => now()]);

        return true;
    }

    /**
     * Mark all notifications as read for a user.
     */
    public function markAllAsRead(User $user): int
    {
        return Notification::forUser($user->id)
            ->unread()
            ->update(['read_at' => now()]);
    }

    /**
     * Delete old read notifications (cleanup).
     */
    public function deleteOldNotifications(int $daysOld = 30): int
    {
        return Notification::whereNotNull('read_at')
            ->where('read_at', '<', now()->subDays($daysOld))
            ->delete();
    }

    /**
     * Mark all notifications as read for a specific activity.
     */
    public function markAsReadByActivity(User $user, string $activityId): int
    {
        return Notification::forUser($user->id)
            ->unread()
            ->where('notifiable_type', 'App\\Models\\Activity')
            ->where('notifiable_id', $activityId)
            ->update(['read_at' => now()]);
    }
}
