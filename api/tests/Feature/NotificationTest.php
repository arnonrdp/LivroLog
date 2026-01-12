<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Notification;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    protected NotificationService $notificationService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->notificationService = app(NotificationService::class);
    }

    // ==================== SERVICE TESTS ====================

    public function test_can_get_user_notifications()
    {
        $user = User::factory()->create();
        $actor = User::factory()->create();
        $activity = Activity::factory()->create(['user_id' => $user->id]);

        Notification::factory()->count(3)->create([
            'user_id' => $user->id,
            'actor_id' => $actor->id,
            'notifiable_type' => 'Activity',
            'notifiable_id' => $activity->id,
        ]);

        $result = $this->notificationService->getNotifications($user);

        $this->assertCount(3, $result);
    }

    public function test_notifications_are_paginated()
    {
        $user = User::factory()->create();
        $actor = User::factory()->create();
        $activity = Activity::factory()->create(['user_id' => $user->id]);

        Notification::factory()->count(25)->create([
            'user_id' => $user->id,
            'actor_id' => $actor->id,
            'notifiable_type' => 'Activity',
            'notifiable_id' => $activity->id,
        ]);

        $result = $this->notificationService->getNotifications($user, 10);

        $this->assertCount(10, $result);
        $this->assertEquals(25, $result->total());
        $this->assertEquals(3, $result->lastPage());
    }

    public function test_can_get_unread_count()
    {
        $user = User::factory()->create();
        $actor = User::factory()->create();
        $activity = Activity::factory()->create(['user_id' => $user->id]);

        // Create 3 unread notifications
        Notification::factory()->count(3)->create([
            'user_id' => $user->id,
            'actor_id' => $actor->id,
            'notifiable_type' => 'Activity',
            'notifiable_id' => $activity->id,
            'read_at' => null,
        ]);

        // Create 2 read notifications
        Notification::factory()->count(2)->read()->create([
            'user_id' => $user->id,
            'actor_id' => $actor->id,
            'notifiable_type' => 'Activity',
            'notifiable_id' => $activity->id,
        ]);

        $count = $this->notificationService->getUnreadCount($user);

        $this->assertEquals(3, $count);
    }

    public function test_can_mark_notification_as_read()
    {
        $user = User::factory()->create();
        $actor = User::factory()->create();
        $activity = Activity::factory()->create(['user_id' => $user->id]);
        $notification = Notification::factory()->create([
            'user_id' => $user->id,
            'actor_id' => $actor->id,
            'notifiable_type' => 'Activity',
            'notifiable_id' => $activity->id,
            'read_at' => null,
        ]);

        $result = $this->notificationService->markAsRead($user, $notification);

        $this->assertTrue($result);
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_cannot_mark_other_user_notification_as_read()
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $actor = User::factory()->create();
        $activity = Activity::factory()->create(['user_id' => $owner->id]);
        $notification = Notification::factory()->create([
            'user_id' => $owner->id,
            'actor_id' => $actor->id,
            'notifiable_type' => 'Activity',
            'notifiable_id' => $activity->id,
            'read_at' => null,
        ]);

        $result = $this->notificationService->markAsRead($otherUser, $notification);

        $this->assertFalse($result);
        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_can_mark_all_notifications_as_read()
    {
        $user = User::factory()->create();
        $actor = User::factory()->create();
        $activity = Activity::factory()->create(['user_id' => $user->id]);

        Notification::factory()->count(5)->create([
            'user_id' => $user->id,
            'actor_id' => $actor->id,
            'notifiable_type' => 'Activity',
            'notifiable_id' => $activity->id,
            'read_at' => null,
        ]);

        $count = $this->notificationService->markAllAsRead($user);

        $this->assertEquals(5, $count);
        $this->assertEquals(0, Notification::where('user_id', $user->id)->whereNull('read_at')->count());
    }

    // ==================== ENDPOINT TESTS ====================

    public function test_notification_endpoints_require_authentication()
    {
        $response = $this->getJson('/notifications');
        $response->assertStatus(401);

        $response = $this->getJson('/notifications/unread-count');
        $response->assertStatus(401);

        $response = $this->postJson('/notifications/read-all');
        $response->assertStatus(401);
    }

    public function test_get_notifications_endpoint_works()
    {
        $user = User::factory()->create();
        $actor = User::factory()->create();
        $activity = Activity::factory()->create(['user_id' => $user->id]);

        Notification::factory()->count(3)->create([
            'user_id' => $user->id,
            'actor_id' => $actor->id,
            'notifiable_type' => 'Activity',
            'notifiable_id' => $activity->id,
        ]);

        $response = $this->actingAs($user)
            ->getJson('/notifications');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'type', 'actor', 'is_read', 'created_at'],
                ],
                'meta' => ['total', 'current_page', 'per_page', 'last_page', 'unread_count'],
            ]);
    }

    public function test_get_notifications_endpoint_supports_pagination()
    {
        $user = User::factory()->create();
        $actor = User::factory()->create();
        $activity = Activity::factory()->create(['user_id' => $user->id]);

        Notification::factory()->count(25)->create([
            'user_id' => $user->id,
            'actor_id' => $actor->id,
            'notifiable_type' => 'Activity',
            'notifiable_id' => $activity->id,
        ]);

        $response = $this->actingAs($user)
            ->getJson('/notifications?page=2&per_page=10');

        $response->assertStatus(200)
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.current_page', 2);
    }

    public function test_get_unread_count_endpoint_works()
    {
        $user = User::factory()->create();
        $actor = User::factory()->create();
        $activity = Activity::factory()->create(['user_id' => $user->id]);

        Notification::factory()->count(5)->create([
            'user_id' => $user->id,
            'actor_id' => $actor->id,
            'notifiable_type' => 'Activity',
            'notifiable_id' => $activity->id,
            'read_at' => null,
        ]);

        $response = $this->actingAs($user)
            ->getJson('/notifications/unread-count');

        $response->assertStatus(200)
            ->assertJson(['unread_count' => 5]);
    }

    public function test_mark_notification_as_read_endpoint_works()
    {
        $user = User::factory()->create();
        $actor = User::factory()->create();
        $activity = Activity::factory()->create(['user_id' => $user->id]);
        $notification = Notification::factory()->create([
            'user_id' => $user->id,
            'actor_id' => $actor->id,
            'notifiable_type' => 'Activity',
            'notifiable_id' => $activity->id,
            'read_at' => null,
        ]);

        $response = $this->actingAs($user)
            ->postJson("/notifications/{$notification->id}/read");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_mark_notification_as_read_returns_403_for_wrong_user()
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $actor = User::factory()->create();
        $activity = Activity::factory()->create(['user_id' => $owner->id]);
        $notification = Notification::factory()->create([
            'user_id' => $owner->id,
            'actor_id' => $actor->id,
            'notifiable_type' => 'Activity',
            'notifiable_id' => $activity->id,
        ]);

        $response = $this->actingAs($otherUser)
            ->postJson("/notifications/{$notification->id}/read");

        $response->assertStatus(403);
    }

    public function test_mark_all_read_endpoint_works()
    {
        $user = User::factory()->create();
        $actor = User::factory()->create();
        $activity = Activity::factory()->create(['user_id' => $user->id]);

        Notification::factory()->count(5)->create([
            'user_id' => $user->id,
            'actor_id' => $actor->id,
            'notifiable_type' => 'Activity',
            'notifiable_id' => $activity->id,
            'read_at' => null,
        ]);

        $response = $this->actingAs($user)
            ->postJson('/notifications/read-all');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'marked_count' => 5,
            ]);

        $this->assertEquals(0, Notification::where('user_id', $user->id)->whereNull('read_at')->count());
    }

    public function test_notifications_only_show_own_notifications()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $actor = User::factory()->create();
        $activity1 = Activity::factory()->create(['user_id' => $user1->id]);
        $activity2 = Activity::factory()->create(['user_id' => $user2->id]);

        Notification::factory()->count(3)->create([
            'user_id' => $user1->id,
            'actor_id' => $actor->id,
            'notifiable_type' => 'Activity',
            'notifiable_id' => $activity1->id,
        ]);
        Notification::factory()->count(2)->create([
            'user_id' => $user2->id,
            'actor_id' => $actor->id,
            'notifiable_type' => 'Activity',
            'notifiable_id' => $activity2->id,
        ]);

        $response = $this->actingAs($user1)
            ->getJson('/notifications');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_notifications_are_ordered_by_created_at_descending()
    {
        $user = User::factory()->create();
        $actor = User::factory()->create();
        $activity = Activity::factory()->create(['user_id' => $user->id]);

        $older = Notification::factory()->create([
            'user_id' => $user->id,
            'actor_id' => $actor->id,
            'notifiable_type' => 'Activity',
            'notifiable_id' => $activity->id,
            'created_at' => now()->subDay(),
        ]);

        $newer = Notification::factory()->create([
            'user_id' => $user->id,
            'actor_id' => $actor->id,
            'notifiable_type' => 'Activity',
            'notifiable_id' => $activity->id,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->getJson('/notifications');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertEquals($newer->id, $data[0]['id']);
        $this->assertEquals($older->id, $data[1]['id']);
    }

    public function test_unread_count_in_notifications_meta()
    {
        $user = User::factory()->create();
        $actor = User::factory()->create();
        $activity = Activity::factory()->create(['user_id' => $user->id]);

        // 3 unread
        Notification::factory()->count(3)->create([
            'user_id' => $user->id,
            'actor_id' => $actor->id,
            'notifiable_type' => 'Activity',
            'notifiable_id' => $activity->id,
            'read_at' => null,
        ]);

        // 2 read
        Notification::factory()->count(2)->read()->create([
            'user_id' => $user->id,
            'actor_id' => $actor->id,
            'notifiable_type' => 'Activity',
            'notifiable_id' => $activity->id,
        ]);

        $response = $this->actingAs($user)
            ->getJson('/notifications');

        $response->assertStatus(200)
            ->assertJsonPath('meta.unread_count', 3)
            ->assertJsonPath('meta.total', 5);
    }
}
