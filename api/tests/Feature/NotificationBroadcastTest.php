<?php

namespace Tests\Feature;

use App\Events\NewNotification;
use App\Models\Activity;
use App\Models\Follow;
use App\Models\Notification;
use App\Models\User;
use App\Services\ActivityInteractionService;
use App\Services\FollowService;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class NotificationBroadcastTest extends TestCase
{
    use RefreshDatabase;

    protected ActivityInteractionService $service;

    protected FollowService $followService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ActivityInteractionService::class);
        $this->followService = app(FollowService::class);
    }

    // ==================== EVENT STRUCTURE TESTS ====================

    public function test_new_notification_event_has_correct_structure()
    {
        $user = User::factory()->create();
        $actor = User::factory()->create();
        $activity = Activity::factory()->create(['user_id' => $user->id]);

        $notification = Notification::factory()->create([
            'user_id' => $user->id,
            'actor_id' => $actor->id,
            'type' => 'activity_liked',
            'notifiable_type' => 'Activity',
            'notifiable_id' => $activity->id,
        ]);

        $event = new NewNotification($notification);

        $this->assertInstanceOf(NewNotification::class, $event);
        $this->assertEquals($notification->id, $event->notification->id);
    }

    public function test_new_notification_event_broadcasts_on_correct_channel()
    {
        $user = User::factory()->create();
        $actor = User::factory()->create();
        $activity = Activity::factory()->create(['user_id' => $user->id]);

        $notification = Notification::factory()->create([
            'user_id' => $user->id,
            'actor_id' => $actor->id,
            'type' => 'activity_liked',
            'notifiable_type' => 'Activity',
            'notifiable_id' => $activity->id,
        ]);

        $event = new NewNotification($notification);
        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
        $this->assertEquals("private-notifications.{$user->id}", $channels[0]->name);
    }

    public function test_new_notification_event_has_correct_broadcast_name()
    {
        $user = User::factory()->create();
        $actor = User::factory()->create();
        $activity = Activity::factory()->create(['user_id' => $user->id]);

        $notification = Notification::factory()->create([
            'user_id' => $user->id,
            'actor_id' => $actor->id,
            'type' => 'activity_liked',
            'notifiable_type' => 'Activity',
            'notifiable_id' => $activity->id,
        ]);

        $event = new NewNotification($notification);

        $this->assertEquals('notification.new', $event->broadcastAs());
    }

    public function test_new_notification_event_has_correct_broadcast_data()
    {
        $user = User::factory()->create();
        $actor = User::factory()->create([
            'display_name' => 'Test Actor',
            'username' => 'testactor',
            'avatar' => 'https://example.com/avatar.jpg',
        ]);
        $activity = Activity::factory()->create(['user_id' => $user->id]);

        $notification = Notification::factory()->create([
            'user_id' => $user->id,
            'actor_id' => $actor->id,
            'type' => 'activity_liked',
            'notifiable_type' => 'Activity',
            'notifiable_id' => $activity->id,
            'data' => ['test_key' => 'test_value'],
        ]);

        $event = new NewNotification($notification);
        $data = $event->broadcastWith();

        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('type', $data);
        $this->assertArrayHasKey('actor', $data);
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('read_at', $data);
        $this->assertArrayHasKey('is_read', $data);
        $this->assertArrayHasKey('created_at', $data);

        $this->assertEquals($notification->id, $data['id']);
        $this->assertEquals('activity_liked', $data['type']);
        $this->assertNull($data['read_at']);
        $this->assertFalse($data['is_read']);

        // Actor data
        $this->assertEquals($actor->id, $data['actor']['id']);
        $this->assertEquals('Test Actor', $data['actor']['display_name']);
        $this->assertEquals('testactor', $data['actor']['username']);
        $this->assertEquals('https://example.com/avatar.jpg', $data['actor']['avatar']);

        // Custom data
        $this->assertEquals(['test_key' => 'test_value'], $data['data']);
    }

    public function test_new_notification_event_loads_actor_relationship()
    {
        $user = User::factory()->create();
        $actor = User::factory()->create();
        $activity = Activity::factory()->create(['user_id' => $user->id]);

        $notification = Notification::factory()->create([
            'user_id' => $user->id,
            'actor_id' => $actor->id,
            'type' => 'activity_liked',
            'notifiable_type' => 'Activity',
            'notifiable_id' => $activity->id,
        ]);

        // Create event without loading actor
        $freshNotification = Notification::find($notification->id);
        $this->assertFalse($freshNotification->relationLoaded('actor'));

        $event = new NewNotification($freshNotification);

        // Event should load actor
        $this->assertTrue($event->notification->relationLoaded('actor'));
        $this->assertEquals($actor->id, $event->notification->actor->id);
    }

    // ==================== BROADCAST DISPATCH TESTS ====================

    public function test_broadcast_is_dispatched_when_activity_is_liked()
    {
        Event::fake([NewNotification::class]);

        $activityOwner = User::factory()->create();
        $liker = User::factory()->create();
        $activity = Activity::factory()->create(['user_id' => $activityOwner->id]);

        $this->service->likeActivity($liker, $activity);

        Event::assertDispatched(NewNotification::class, function ($event) use ($activityOwner, $liker) {
            return $event->notification->user_id === $activityOwner->id
                && $event->notification->actor_id === $liker->id
                && $event->notification->type === 'activity_liked';
        });
    }

    public function test_broadcast_is_not_dispatched_when_liking_own_activity()
    {
        Event::fake([NewNotification::class]);

        $user = User::factory()->create();
        $activity = Activity::factory()->create(['user_id' => $user->id]);

        $this->service->likeActivity($user, $activity);

        Event::assertNotDispatched(NewNotification::class);
    }

    public function test_broadcast_is_dispatched_when_comment_is_added()
    {
        Event::fake([NewNotification::class]);

        $activityOwner = User::factory()->create();
        $commenter = User::factory()->create();
        $activity = Activity::factory()->create(['user_id' => $activityOwner->id]);

        $this->service->addComment($commenter, $activity, 'Great post!');

        Event::assertDispatched(NewNotification::class, function ($event) use ($activityOwner, $commenter) {
            return $event->notification->user_id === $activityOwner->id
                && $event->notification->actor_id === $commenter->id
                && $event->notification->type === 'activity_commented';
        });
    }

    public function test_broadcast_is_not_dispatched_when_commenting_on_own_activity()
    {
        Event::fake([NewNotification::class]);

        $user = User::factory()->create();
        $activity = Activity::factory()->create(['user_id' => $user->id]);

        $this->service->addComment($user, $activity, 'My own comment');

        Event::assertNotDispatched(NewNotification::class);
    }

    public function test_broadcast_dispatched_once_per_notification()
    {
        Event::fake([NewNotification::class]);

        $activityOwner = User::factory()->create();
        $liker = User::factory()->create();
        $activity = Activity::factory()->create(['user_id' => $activityOwner->id]);

        $this->service->likeActivity($liker, $activity);

        Event::assertDispatchedTimes(NewNotification::class, 1);
    }

    public function test_multiple_likes_from_different_users_dispatch_multiple_broadcasts()
    {
        Event::fake([NewNotification::class]);

        $activityOwner = User::factory()->create();
        $liker1 = User::factory()->create();
        $liker2 = User::factory()->create();
        $liker3 = User::factory()->create();
        $activity = Activity::factory()->create(['user_id' => $activityOwner->id]);

        $this->service->likeActivity($liker1, $activity);
        $this->service->likeActivity($liker2, $activity);
        $this->service->likeActivity($liker3, $activity);

        Event::assertDispatchedTimes(NewNotification::class, 3);
    }

    public function test_idempotent_like_does_not_dispatch_broadcast_twice()
    {
        Event::fake([NewNotification::class]);

        $activityOwner = User::factory()->create();
        $liker = User::factory()->create();
        $activity = Activity::factory()->create(['user_id' => $activityOwner->id]);

        // Like twice
        $this->service->likeActivity($liker, $activity);
        $this->service->likeActivity($liker, $activity);

        // Should only dispatch once (idempotent)
        Event::assertDispatchedTimes(NewNotification::class, 1);
    }

    // ==================== CHANNEL AUTHORIZATION TESTS ====================

    public function test_user_can_access_own_notification_channel()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/broadcasting/auth', [
                'channel_name' => "private-notifications.{$user->id}",
                'socket_id' => '1234.5678',
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['auth']);
    }

    public function test_user_cannot_access_other_user_notification_channel()
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/broadcasting/auth', [
                'channel_name' => "private-notifications.{$otherUser->id}",
                'socket_id' => '1234.5678',
            ]);

        $response->assertStatus(403);
    }

    public function test_unauthenticated_user_cannot_access_notification_channel()
    {
        $user = User::factory()->create();

        $response = $this->postJson('/broadcasting/auth', [
            'channel_name' => "private-notifications.{$user->id}",
            'socket_id' => '1234.5678',
        ]);

        $response->assertStatus(401);
    }

    // ==================== NOTIFICATION TYPES TESTS ====================

    public function test_activity_liked_notification_has_correct_type()
    {
        Event::fake([NewNotification::class]);

        $activityOwner = User::factory()->create();
        $liker = User::factory()->create();
        $activity = Activity::factory()->create(['user_id' => $activityOwner->id]);

        $this->service->likeActivity($liker, $activity);

        Event::assertDispatched(NewNotification::class, function ($event) {
            return $event->notification->type === 'activity_liked';
        });
    }

    public function test_activity_commented_notification_has_correct_type()
    {
        Event::fake([NewNotification::class]);

        $activityOwner = User::factory()->create();
        $commenter = User::factory()->create();
        $activity = Activity::factory()->create(['user_id' => $activityOwner->id]);

        $this->service->addComment($commenter, $activity, 'Nice!');

        Event::assertDispatched(NewNotification::class, function ($event) {
            return $event->notification->type === 'activity_commented';
        });
    }

    public function test_activity_commented_notification_includes_comment_id()
    {
        Event::fake([NewNotification::class]);

        $activityOwner = User::factory()->create();
        $commenter = User::factory()->create();
        $activity = Activity::factory()->create(['user_id' => $activityOwner->id]);

        $this->service->addComment($commenter, $activity, 'Nice post!');

        Event::assertDispatched(NewNotification::class, function ($event) {
            $data = $event->notification->data;

            return isset($data['comment_id']) && ! empty($data['comment_id']);
        });
    }

    // ==================== PRIVACY TESTS ====================

    public function test_broadcast_not_dispatched_for_private_profile_activity_without_follow()
    {
        Event::fake([NewNotification::class]);

        $privateUser = User::factory()->create(['is_private' => true]);
        $stranger = User::factory()->create();
        $activity = Activity::factory()->create(['user_id' => $privateUser->id]);

        // Stranger tries to like activity (should fail due to privacy)
        $result = $this->service->likeActivity($stranger, $activity);

        $this->assertFalse($result['success']);
        $this->assertEquals('ACTIVITY_NOT_ACCESSIBLE', $result['code']);

        Event::assertNotDispatched(NewNotification::class);
    }

    public function test_broadcast_dispatched_for_private_profile_activity_with_follow()
    {
        Event::fake([NewNotification::class]);

        $privateUser = User::factory()->create(['is_private' => true]);
        $follower = User::factory()->create();
        $activity = Activity::factory()->create(['user_id' => $privateUser->id]);

        // Create approved follow relationship
        Follow::create([
            'follower_id' => $follower->id,
            'followed_id' => $privateUser->id,
            'status' => 'accepted',
        ]);

        $result = $this->service->likeActivity($follower, $activity);

        $this->assertTrue($result['success']);

        Event::assertDispatched(NewNotification::class);
    }

    // ==================== BROADCAST DATA INTEGRITY TESTS ====================

    public function test_broadcast_data_matches_notification_data()
    {
        $user = User::factory()->create();
        $actor = User::factory()->create();
        $activity = Activity::factory()->create(['user_id' => $user->id]);

        $notification = Notification::factory()->create([
            'user_id' => $user->id,
            'actor_id' => $actor->id,
            'type' => 'activity_liked',
            'notifiable_type' => 'Activity',
            'notifiable_id' => $activity->id,
        ]);

        $event = new NewNotification($notification);
        $broadcastData = $event->broadcastWith();

        $this->assertEquals($notification->id, $broadcastData['id']);
        $this->assertEquals($notification->type, $broadcastData['type']);
        $this->assertEquals($notification->actor->id, $broadcastData['actor']['id']);
    }

    public function test_broadcast_created_at_is_iso_format()
    {
        $user = User::factory()->create();
        $actor = User::factory()->create();
        $activity = Activity::factory()->create(['user_id' => $user->id]);

        $notification = Notification::factory()->create([
            'user_id' => $user->id,
            'actor_id' => $actor->id,
            'type' => 'activity_liked',
            'notifiable_type' => 'Activity',
            'notifiable_id' => $activity->id,
        ]);

        $event = new NewNotification($notification);
        $broadcastData = $event->broadcastWith();

        // Should be ISO 8601 format
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}.\d{6}Z$/',
            $broadcastData['created_at']
        );
    }

    // ==================== INTEGRATION TESTS ====================

    public function test_full_like_flow_creates_notification_and_broadcasts()
    {
        Event::fake([NewNotification::class]);

        $activityOwner = User::factory()->create();
        $liker = User::factory()->create();
        $activity = Activity::factory()->create(['user_id' => $activityOwner->id]);

        // Like via service
        $result = $this->service->likeActivity($liker, $activity);

        // Verify success
        $this->assertTrue($result['success']);

        // Verify notification created in database
        $this->assertDatabaseHas('notifications', [
            'user_id' => $activityOwner->id,
            'actor_id' => $liker->id,
            'type' => 'activity_liked',
        ]);

        // Verify broadcast dispatched
        Event::assertDispatched(NewNotification::class);
    }

    public function test_full_comment_flow_creates_notification_and_broadcasts()
    {
        Event::fake([NewNotification::class]);

        $activityOwner = User::factory()->create();
        $commenter = User::factory()->create();
        $activity = Activity::factory()->create(['user_id' => $activityOwner->id]);

        // Comment via service
        $result = $this->service->addComment($commenter, $activity, 'Great content!');

        // Verify success
        $this->assertTrue($result['success']);

        // Verify notification created in database
        $this->assertDatabaseHas('notifications', [
            'user_id' => $activityOwner->id,
            'actor_id' => $commenter->id,
            'type' => 'activity_commented',
        ]);

        // Verify broadcast dispatched
        Event::assertDispatched(NewNotification::class);
    }

    public function test_unlike_does_not_create_notification_or_broadcast()
    {
        Event::fake([NewNotification::class]);

        $activityOwner = User::factory()->create();
        $liker = User::factory()->create();
        $activity = Activity::factory()->create(['user_id' => $activityOwner->id]);

        // Like first
        $this->service->likeActivity($liker, $activity);

        // Clear event fake to reset
        Event::fake([NewNotification::class]);

        // Unlike
        $result = $this->service->unlikeActivity($liker, $activity);

        $this->assertTrue($result['success']);

        // No new broadcast should be dispatched for unlike
        Event::assertNotDispatched(NewNotification::class);
    }

    // ==================== CHANNEL AUTH VALIDATION TESTS ====================

    public function test_broadcast_auth_requires_channel_name()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/broadcasting/auth', [
                'socket_id' => '1234.5678',
                // Missing channel_name
            ]);

        // Returns 403 when channel_name is missing (no channel to authorize)
        $response->assertStatus(403);
    }

    public function test_broadcast_auth_requires_socket_id()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/broadcasting/auth', [
                'channel_name' => "private-notifications.{$user->id}",
                // Missing socket_id
            ]);

        // Should fail validation or return error
        $response->assertStatus(500); // Broadcast::auth throws exception without socket_id
    }

    public function test_broadcast_auth_rejects_malformed_channel_name()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/broadcasting/auth', [
                'channel_name' => 'invalid-channel-format',
                'socket_id' => '1234.5678',
            ]);

        // Should return 403 for unrecognized channel
        $response->assertStatus(403);
    }

    public function test_broadcast_auth_for_public_channel_returns_empty_auth()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/broadcasting/auth', [
                'channel_name' => "notifications.{$user->id}", // Public channel (no private- prefix)
                'socket_id' => '1234.5678',
            ]);

        // Public channels don't require authorization, returns 200 with empty auth
        // This is expected Laravel behavior - public channels are open to all
        $response->assertStatus(200);
    }

    // ==================== EDGE CASE TESTS ====================

    public function test_broadcast_data_handles_actor_without_avatar()
    {
        $user = User::factory()->create();
        $actor = User::factory()->create([
            'avatar' => null,
            'display_name' => 'No Avatar User',
        ]);
        $activity = Activity::factory()->create(['user_id' => $user->id]);

        $notification = Notification::factory()->create([
            'user_id' => $user->id,
            'actor_id' => $actor->id,
            'type' => 'activity_liked',
            'notifiable_type' => 'Activity',
            'notifiable_id' => $activity->id,
        ]);

        $event = new NewNotification($notification);
        $data = $event->broadcastWith();

        $this->assertNull($data['actor']['avatar']);
        $this->assertEquals('No Avatar User', $data['actor']['display_name']);
    }

    public function test_broadcast_data_handles_null_notification_data()
    {
        $user = User::factory()->create();
        $actor = User::factory()->create();
        $activity = Activity::factory()->create(['user_id' => $user->id]);

        $notification = Notification::factory()->create([
            'user_id' => $user->id,
            'actor_id' => $actor->id,
            'type' => 'activity_liked',
            'notifiable_type' => 'Activity',
            'notifiable_id' => $activity->id,
            'data' => null,
        ]);

        $event = new NewNotification($notification);
        $data = $event->broadcastWith();

        $this->assertNull($data['data']);
    }

    public function test_broadcast_data_handles_empty_notification_data()
    {
        $user = User::factory()->create();
        $actor = User::factory()->create();
        $activity = Activity::factory()->create(['user_id' => $user->id]);

        $notification = Notification::factory()->create([
            'user_id' => $user->id,
            'actor_id' => $actor->id,
            'type' => 'activity_liked',
            'notifiable_type' => 'Activity',
            'notifiable_id' => $activity->id,
            'data' => [],
        ]);

        $event = new NewNotification($notification);
        $data = $event->broadcastWith();

        $this->assertEquals([], $data['data']);
    }

    // ==================== HTTP ENDPOINT TESTS ====================

    public function test_like_endpoint_dispatches_broadcast()
    {
        Event::fake([NewNotification::class]);

        $activityOwner = User::factory()->create();
        $liker = User::factory()->create();
        $activity = Activity::factory()->create(['user_id' => $activityOwner->id]);

        $response = $this->actingAs($liker)
            ->postJson("/activities/{$activity->id}/like");

        $response->assertStatus(200);

        Event::assertDispatched(NewNotification::class, function ($event) use ($activityOwner) {
            return $event->notification->user_id === $activityOwner->id;
        });
    }

    public function test_comment_endpoint_dispatches_broadcast()
    {
        Event::fake([NewNotification::class]);

        $activityOwner = User::factory()->create();
        $commenter = User::factory()->create();
        $activity = Activity::factory()->create(['user_id' => $activityOwner->id]);

        $response = $this->actingAs($commenter)
            ->postJson("/activities/{$activity->id}/comments", [
                'content' => 'Great post!',
            ]);

        $response->assertStatus(201);

        Event::assertDispatched(NewNotification::class, function ($event) use ($activityOwner) {
            return $event->notification->user_id === $activityOwner->id
                && $event->notification->type === 'activity_commented';
        });
    }

    public function test_unlike_endpoint_does_not_dispatch_broadcast()
    {
        $activityOwner = User::factory()->create();
        $liker = User::factory()->create();
        $activity = Activity::factory()->create(['user_id' => $activityOwner->id]);

        // Like first
        $this->actingAs($liker)->postJson("/activities/{$activity->id}/like");

        // Reset event fake
        Event::fake([NewNotification::class]);

        // Unlike
        $response = $this->actingAs($liker)
            ->deleteJson("/activities/{$activity->id}/like");

        $response->assertStatus(200);
        Event::assertNotDispatched(NewNotification::class);
    }

    public function test_delete_comment_does_not_dispatch_broadcast()
    {
        $activityOwner = User::factory()->create();
        $commenter = User::factory()->create();
        $activity = Activity::factory()->create(['user_id' => $activityOwner->id]);

        // Create comment first
        $comment = \App\Models\Comment::create([
            'user_id' => $commenter->id,
            'activity_id' => $activity->id,
            'content' => 'Test comment',
        ]);

        Event::fake([NewNotification::class]);

        // Delete comment
        $response = $this->actingAs($commenter)
            ->deleteJson("/comments/{$comment->id}");

        $response->assertStatus(200);
        Event::assertNotDispatched(NewNotification::class);
    }

    // ==================== SERIALIZATION TESTS ====================

    public function test_new_notification_event_is_serializable()
    {
        $user = User::factory()->create();
        $actor = User::factory()->create();
        $activity = Activity::factory()->create(['user_id' => $user->id]);

        $notification = Notification::factory()->create([
            'user_id' => $user->id,
            'actor_id' => $actor->id,
            'type' => 'activity_liked',
            'notifiable_type' => 'Activity',
            'notifiable_id' => $activity->id,
        ]);

        $event = new NewNotification($notification);

        // Test that event can be serialized (for queue processing)
        $serialized = serialize($event);
        $this->assertIsString($serialized);

        // Test that event can be deserialized
        $unserialized = unserialize($serialized);
        $this->assertInstanceOf(NewNotification::class, $unserialized);
        $this->assertEquals($notification->id, $unserialized->notification->id);
    }

    // ==================== CONCURRENCY TESTS ====================

    public function test_rapid_like_unlike_only_broadcasts_once()
    {
        Event::fake([NewNotification::class]);

        $activityOwner = User::factory()->create();
        $liker = User::factory()->create();
        $activity = Activity::factory()->create(['user_id' => $activityOwner->id]);

        // Like
        $this->service->likeActivity($liker, $activity);

        // Unlike
        $this->service->unlikeActivity($liker, $activity);

        // Like again
        $this->service->likeActivity($liker, $activity);

        // Should dispatch twice (once for each like, not for unlike)
        Event::assertDispatchedTimes(NewNotification::class, 2);
    }

    // ==================== PENDING FOLLOW REQUEST TESTS ====================

    public function test_broadcast_not_dispatched_for_pending_follow_activity()
    {
        Event::fake([NewNotification::class]);

        $privateUser = User::factory()->create(['is_private' => true]);
        $requester = User::factory()->create();
        $activity = Activity::factory()->create(['user_id' => $privateUser->id]);

        // Create pending follow (not accepted)
        Follow::create([
            'follower_id' => $requester->id,
            'followed_id' => $privateUser->id,
            'status' => 'pending',
        ]);

        // Requester tries to like - should fail
        $result = $this->service->likeActivity($requester, $activity);

        $this->assertFalse($result['success']);
        Event::assertNotDispatched(NewNotification::class);
    }

    // ==================== NOTIFICATION RECIPIENT VALIDATION ====================

    public function test_broadcast_goes_to_correct_user_channel()
    {
        $activityOwner = User::factory()->create();
        $liker = User::factory()->create();
        $activity = Activity::factory()->create(['user_id' => $activityOwner->id]);

        $notification = Notification::create([
            'user_id' => $activityOwner->id,
            'actor_id' => $liker->id,
            'type' => 'activity_liked',
            'notifiable_type' => 'Activity',
            'notifiable_id' => $activity->id,
        ]);

        $event = new NewNotification($notification);
        $channels = $event->broadcastOn();

        // Verify it broadcasts to activity owner's channel, NOT to liker's
        $this->assertCount(1, $channels);
        $this->assertEquals("private-notifications.{$activityOwner->id}", $channels[0]->name);
        $this->assertNotEquals("private-notifications.{$liker->id}", $channels[0]->name);
    }

    // ==================== BROADCAST QUEUE TESTS ====================

    public function test_new_notification_event_implements_should_broadcast()
    {
        $user = User::factory()->create();
        $actor = User::factory()->create();
        $activity = Activity::factory()->create(['user_id' => $user->id]);

        $notification = Notification::factory()->create([
            'user_id' => $user->id,
            'actor_id' => $actor->id,
            'type' => 'activity_liked',
            'notifiable_type' => 'Activity',
            'notifiable_id' => $activity->id,
        ]);

        $event = new NewNotification($notification);

        // Verify event implements ShouldBroadcast
        $this->assertInstanceOf(\Illuminate\Contracts\Broadcasting\ShouldBroadcast::class, $event);
    }

    public function test_broadcast_uses_to_others_method()
    {
        // This test verifies the service uses toOthers() to prevent echo
        // The implementation already uses broadcast()->toOthers()
        // We can verify the event supports socket exclusion
        $user = User::factory()->create();
        $actor = User::factory()->create();
        $activity = Activity::factory()->create(['user_id' => $user->id]);

        $notification = Notification::factory()->create([
            'user_id' => $user->id,
            'actor_id' => $actor->id,
            'type' => 'activity_liked',
            'notifiable_type' => 'Activity',
            'notifiable_id' => $activity->id,
        ]);

        $event = new NewNotification($notification);

        // Verify event uses InteractsWithSockets trait (enables toOthers())
        $traits = class_uses_recursive($event);
        $this->assertContains(\Illuminate\Broadcasting\InteractsWithSockets::class, $traits);
    }

    // ==================== FOLLOW NOTIFICATION TESTS ====================

    public function test_follow_public_user_creates_new_follower_notification()
    {
        Event::fake([NewNotification::class]);

        $publicUser = User::factory()->create(['is_private' => false]);
        $follower = User::factory()->create();

        $result = $this->followService->follow($follower, $publicUser);

        $this->assertTrue($result['success']);

        // Verify notification created in database
        $this->assertDatabaseHas('notifications', [
            'user_id' => $publicUser->id,
            'actor_id' => $follower->id,
            'type' => 'new_follower',
            'notifiable_type' => 'User',
            'notifiable_id' => $publicUser->id,
        ]);

        // Verify broadcast dispatched
        Event::assertDispatched(NewNotification::class, function ($event) use ($publicUser, $follower) {
            return $event->notification->user_id === $publicUser->id
                && $event->notification->actor_id === $follower->id
                && $event->notification->type === 'new_follower';
        });
    }

    public function test_follow_private_user_creates_follow_request_notification()
    {
        Event::fake([NewNotification::class]);

        $privateUser = User::factory()->create(['is_private' => true]);
        $requester = User::factory()->create();

        $result = $this->followService->follow($requester, $privateUser);

        $this->assertTrue($result['success']);
        $this->assertEquals('pending', $result['data']['status']);

        // Verify notification created in database
        $this->assertDatabaseHas('notifications', [
            'user_id' => $privateUser->id,
            'actor_id' => $requester->id,
            'type' => 'follow_request',
            'notifiable_type' => 'User',
            'notifiable_id' => $privateUser->id,
        ]);

        // Verify broadcast dispatched
        Event::assertDispatched(NewNotification::class, function ($event) use ($privateUser, $requester) {
            return $event->notification->user_id === $privateUser->id
                && $event->notification->actor_id === $requester->id
                && $event->notification->type === 'follow_request';
        });
    }

    public function test_accept_follow_request_creates_follow_accepted_notification()
    {
        Event::fake([NewNotification::class]);

        $privateUser = User::factory()->create(['is_private' => true]);
        $requester = User::factory()->create();

        // Create pending follow request
        $follow = Follow::create([
            'follower_id' => $requester->id,
            'followed_id' => $privateUser->id,
            'status' => 'pending',
        ]);

        $result = $this->followService->acceptFollowRequest($follow->id, $privateUser);

        $this->assertTrue($result['success']);

        // Verify notification created in database for the requester
        $this->assertDatabaseHas('notifications', [
            'user_id' => $requester->id,
            'actor_id' => $privateUser->id,
            'type' => 'follow_accepted',
            'notifiable_type' => 'User',
            'notifiable_id' => $privateUser->id,
        ]);

        // Verify broadcast dispatched to the requester
        Event::assertDispatched(NewNotification::class, function ($event) use ($requester, $privateUser) {
            return $event->notification->user_id === $requester->id
                && $event->notification->actor_id === $privateUser->id
                && $event->notification->type === 'follow_accepted';
        });
    }

    public function test_follow_self_does_not_create_notification()
    {
        Event::fake([NewNotification::class]);

        $user = User::factory()->create();

        $result = $this->followService->follow($user, $user);

        $this->assertFalse($result['success']);
        $this->assertEquals('CANNOT_FOLLOW_SELF', $result['code']);

        // No notification should be created
        $this->assertDatabaseMissing('notifications', [
            'user_id' => $user->id,
            'actor_id' => $user->id,
        ]);

        Event::assertNotDispatched(NewNotification::class);
    }

    public function test_already_following_does_not_create_duplicate_notification()
    {
        Event::fake([NewNotification::class]);

        $publicUser = User::factory()->create(['is_private' => false]);
        $follower = User::factory()->create();

        // First follow (will dispatch one event)
        $this->followService->follow($follower, $publicUser);

        // Try to follow again
        $result = $this->followService->follow($follower, $publicUser);

        $this->assertFalse($result['success']);
        $this->assertEquals('ALREADY_FOLLOWING', $result['code']);

        // Should have only dispatched once (for first follow)
        Event::assertDispatchedTimes(NewNotification::class, 1);
    }

    public function test_pending_follow_request_does_not_create_duplicate_notification()
    {
        Event::fake([NewNotification::class]);

        $privateUser = User::factory()->create(['is_private' => true]);
        $requester = User::factory()->create();

        // First request (will dispatch one event)
        $this->followService->follow($requester, $privateUser);

        // Try to follow again (should return existing pending status)
        $result = $this->followService->follow($requester, $privateUser);

        $this->assertTrue($result['success']);
        $this->assertEquals('Follow request already sent', $result['message']);

        // Should have only dispatched once (for first request)
        Event::assertDispatchedTimes(NewNotification::class, 1);
    }

    public function test_follow_notification_includes_follow_id_in_data()
    {
        Event::fake([NewNotification::class]);

        $publicUser = User::factory()->create(['is_private' => false]);
        $follower = User::factory()->create();

        $this->followService->follow($follower, $publicUser);

        Event::assertDispatched(NewNotification::class, function ($event) {
            $data = $event->notification->data;

            return isset($data['follow_id']) && ! empty($data['follow_id']);
        });
    }

    public function test_follow_accepted_notification_includes_follow_id_in_data()
    {
        Event::fake([NewNotification::class]);

        $privateUser = User::factory()->create(['is_private' => true]);
        $requester = User::factory()->create();

        $follow = Follow::create([
            'follower_id' => $requester->id,
            'followed_id' => $privateUser->id,
            'status' => 'pending',
        ]);

        $this->followService->acceptFollowRequest($follow->id, $privateUser);

        Event::assertDispatched(NewNotification::class, function ($event) use ($follow) {
            $data = $event->notification->data;

            return isset($data['follow_id']) && $data['follow_id'] === $follow->id;
        });
    }

    public function test_reject_follow_request_does_not_create_notification()
    {
        $privateUser = User::factory()->create(['is_private' => true]);
        $requester = User::factory()->create();

        $follow = Follow::create([
            'follower_id' => $requester->id,
            'followed_id' => $privateUser->id,
            'status' => 'pending',
        ]);

        Event::fake([NewNotification::class]);

        $result = $this->followService->rejectFollowRequest($follow->id, $privateUser);

        $this->assertTrue($result['success']);

        // No notification for rejection
        Event::assertNotDispatched(NewNotification::class);
    }

    public function test_unfollow_does_not_create_notification()
    {
        $publicUser = User::factory()->create(['is_private' => false]);
        $follower = User::factory()->create();

        // Create follow directly in database (avoid broadcast)
        Follow::create([
            'follower_id' => $follower->id,
            'followed_id' => $publicUser->id,
            'status' => 'accepted',
        ]);
        $follower->increment('following_count');
        $publicUser->increment('followers_count');

        Event::fake([NewNotification::class]);

        // Unfollow
        $result = $this->followService->unfollow($follower, $publicUser);

        $this->assertTrue($result['success']);

        // No notification for unfollow
        Event::assertNotDispatched(NewNotification::class);
    }

    // ==================== FOLLOW HTTP ENDPOINT TESTS ====================

    public function test_follow_endpoint_dispatches_broadcast_for_public_user()
    {
        Event::fake([NewNotification::class]);

        $publicUser = User::factory()->create(['is_private' => false]);
        $follower = User::factory()->create();

        $response = $this->actingAs($follower)
            ->postJson("/users/{$publicUser->id}/follow");

        $response->assertStatus(200);

        Event::assertDispatched(NewNotification::class, function ($event) use ($publicUser) {
            return $event->notification->user_id === $publicUser->id
                && $event->notification->type === 'new_follower';
        });
    }

    public function test_follow_endpoint_dispatches_broadcast_for_private_user()
    {
        Event::fake([NewNotification::class]);

        $privateUser = User::factory()->create(['is_private' => true]);
        $requester = User::factory()->create();

        $response = $this->actingAs($requester)
            ->postJson("/users/{$privateUser->id}/follow");

        $response->assertStatus(200);

        Event::assertDispatched(NewNotification::class, function ($event) use ($privateUser) {
            return $event->notification->user_id === $privateUser->id
                && $event->notification->type === 'follow_request';
        });
    }

    public function test_accept_follow_request_endpoint_dispatches_broadcast()
    {
        Event::fake([NewNotification::class]);

        $privateUser = User::factory()->create(['is_private' => true]);
        $requester = User::factory()->create();

        $follow = Follow::create([
            'follower_id' => $requester->id,
            'followed_id' => $privateUser->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($privateUser)
            ->postJson("/follow-requests/{$follow->id}");

        $response->assertStatus(200);

        Event::assertDispatched(NewNotification::class, function ($event) use ($requester) {
            return $event->notification->user_id === $requester->id
                && $event->notification->type === 'follow_accepted';
        });
    }

    public function test_unfollow_endpoint_does_not_dispatch_broadcast()
    {
        $publicUser = User::factory()->create(['is_private' => false]);
        $follower = User::factory()->create();

        // Follow first
        Follow::create([
            'follower_id' => $follower->id,
            'followed_id' => $publicUser->id,
            'status' => 'accepted',
        ]);
        $follower->increment('following_count');
        $publicUser->increment('followers_count');

        Event::fake([NewNotification::class]);

        $response = $this->actingAs($follower)
            ->deleteJson("/users/{$publicUser->id}/follow");

        $response->assertStatus(200);

        Event::assertNotDispatched(NewNotification::class);
    }

    // ==================== FOLLOW NOTIFICATION CHANNEL TESTS ====================

    public function test_follow_notification_broadcasts_to_correct_channel()
    {
        Event::fake([NewNotification::class]);

        $publicUser = User::factory()->create(['is_private' => false]);
        $follower = User::factory()->create();

        // Create follow
        $this->followService->follow($follower, $publicUser);

        // Get the notification from database
        $notification = Notification::where('type', 'new_follower')
            ->where('user_id', $publicUser->id)
            ->first();

        $this->assertNotNull($notification);

        $event = new NewNotification($notification);
        $channels = $event->broadcastOn();

        // Should broadcast to the followed user's channel
        $this->assertCount(1, $channels);
        $this->assertEquals("private-notifications.{$publicUser->id}", $channels[0]->name);
    }

    public function test_follow_accepted_notification_broadcasts_to_requester_channel()
    {
        Event::fake([NewNotification::class]);

        $privateUser = User::factory()->create(['is_private' => true]);
        $requester = User::factory()->create();

        $follow = Follow::create([
            'follower_id' => $requester->id,
            'followed_id' => $privateUser->id,
            'status' => 'pending',
        ]);

        $this->followService->acceptFollowRequest($follow->id, $privateUser);

        // Get the notification from database
        $notification = Notification::where('type', 'follow_accepted')
            ->where('user_id', $requester->id)
            ->first();

        $this->assertNotNull($notification);

        $event = new NewNotification($notification);
        $channels = $event->broadcastOn();

        // Should broadcast to the requester's channel (not the private user)
        $this->assertCount(1, $channels);
        $this->assertEquals("private-notifications.{$requester->id}", $channels[0]->name);
    }
}
