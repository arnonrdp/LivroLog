<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\ActivityLike;
use App\Models\Book;
use App\Models\Comment;
use App\Models\Follow;
use App\Models\User;
use App\Services\ActivityInteractionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityInteractionTest extends TestCase
{
    use RefreshDatabase;

    protected ActivityInteractionService $interactionService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->interactionService = app(ActivityInteractionService::class);
    }

    // ==================== LIKE TESTS ====================

    public function test_user_can_like_activity()
    {
        $user = User::factory()->create();
        $activityOwner = User::factory()->create();
        $book = Book::factory()->create();
        $activity = Activity::factory()->create([
            'user_id' => $activityOwner->id,
            'subject_type' => 'Book',
            'subject_id' => $book->id,
        ]);

        $result = $this->interactionService->likeActivity($user, $activity);

        $this->assertTrue($result['success']);
        $this->assertTrue($activity->likedBy($user));
        $this->assertEquals(1, $activity->fresh()->likes_count);
    }

    public function test_user_cannot_like_same_activity_twice()
    {
        $user = User::factory()->create();
        $activity = Activity::factory()->create();

        // First like
        $this->interactionService->likeActivity($user, $activity);

        // Second like attempt (should be idempotent)
        $result = $this->interactionService->likeActivity($user, $activity);

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $activity->fresh()->likes_count);
    }

    public function test_user_can_unlike_activity()
    {
        $user = User::factory()->create();
        $activity = Activity::factory()->create(['likes_count' => 1]);

        ActivityLike::create([
            'user_id' => $user->id,
            'activity_id' => $activity->id,
        ]);

        $result = $this->interactionService->unlikeActivity($user, $activity);

        $this->assertTrue($result['success']);
        $this->assertFalse($activity->likedBy($user));
        $this->assertEquals(0, $activity->fresh()->likes_count);
    }

    public function test_unlike_non_liked_activity_is_idempotent()
    {
        $user = User::factory()->create();
        $activity = Activity::factory()->create();

        $result = $this->interactionService->unlikeActivity($user, $activity);

        $this->assertTrue($result['success']);
        $this->assertEquals(0, $activity->fresh()->likes_count);
    }

    public function test_like_endpoint_requires_authentication()
    {
        $activity = Activity::factory()->create();

        $response = $this->postJson("/activities/{$activity->id}/like");
        $response->assertStatus(401);
    }

    public function test_like_endpoint_works()
    {
        $user = User::factory()->create();
        $activity = Activity::factory()->create();

        $response = $this->actingAs($user)
            ->postJson("/activities/{$activity->id}/like");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'liked' => true,
                    'likes_count' => 1,
                ],
            ]);
    }

    public function test_unlike_endpoint_works()
    {
        $user = User::factory()->create();
        $activity = Activity::factory()->create(['likes_count' => 1]);

        ActivityLike::create([
            'user_id' => $user->id,
            'activity_id' => $activity->id,
        ]);

        $response = $this->actingAs($user)
            ->deleteJson("/activities/{$activity->id}/like");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'liked' => false,
                    'likes_count' => 0,
                ],
            ]);
    }

    public function test_get_likes_endpoint_works()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $activity = Activity::factory()->create(['likes_count' => 2]);

        ActivityLike::create(['user_id' => $user1->id, 'activity_id' => $activity->id]);
        ActivityLike::create(['user_id' => $user2->id, 'activity_id' => $activity->id]);

        $response = $this->actingAs($user1)
            ->getJson("/activities/{$activity->id}/likes");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'users')
            ->assertJson(['total' => 2]);
    }

    public function test_liking_creates_notification_for_activity_owner()
    {
        $liker = User::factory()->create();
        $activityOwner = User::factory()->create();
        $activity = Activity::factory()->create(['user_id' => $activityOwner->id]);

        $this->interactionService->likeActivity($liker, $activity);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $activityOwner->id,
            'actor_id' => $liker->id,
            'type' => 'activity_liked',
            'notifiable_type' => 'Activity',
            'notifiable_id' => $activity->id,
        ]);
    }

    public function test_liking_own_activity_does_not_create_notification()
    {
        $user = User::factory()->create();
        $activity = Activity::factory()->create(['user_id' => $user->id]);

        $this->interactionService->likeActivity($user, $activity);

        $this->assertDatabaseMissing('notifications', [
            'user_id' => $user->id,
            'type' => 'activity_liked',
        ]);
    }

    // ==================== COMMENT TESTS ====================

    public function test_user_can_add_comment()
    {
        $user = User::factory()->create();
        $activity = Activity::factory()->create();

        $result = $this->interactionService->addComment($user, $activity, 'Great book!');

        $this->assertTrue($result['success']);
        $this->assertNotNull($result['data']);
        $this->assertEquals('Great book!', $result['data']->content);
        $this->assertEquals(1, $activity->fresh()->comments_count);
    }

    public function test_comment_content_is_trimmed()
    {
        $user = User::factory()->create();
        $activity = Activity::factory()->create();

        $result = $this->interactionService->addComment($user, $activity, '   Hello world   ');

        $this->assertEquals('Hello world', $result['data']->content);
    }

    public function test_user_can_update_own_comment()
    {
        $user = User::factory()->create();
        $activity = Activity::factory()->create();
        $comment = Comment::create([
            'user_id' => $user->id,
            'activity_id' => $activity->id,
            'content' => 'Original content',
        ]);

        $result = $this->interactionService->updateComment($user, $comment, 'Updated content');

        $this->assertTrue($result['success']);
        $this->assertEquals('Updated content', $comment->fresh()->content);
    }

    public function test_user_cannot_update_other_user_comment()
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $activity = Activity::factory()->create();
        $comment = Comment::create([
            'user_id' => $owner->id,
            'activity_id' => $activity->id,
            'content' => 'Original content',
        ]);

        $result = $this->interactionService->updateComment($otherUser, $comment, 'Hacked!');

        $this->assertFalse($result['success']);
        $this->assertEquals('NOT_COMMENT_OWNER', $result['code']);
        $this->assertEquals('Original content', $comment->fresh()->content);
    }

    public function test_user_can_delete_own_comment()
    {
        $user = User::factory()->create();
        $activity = Activity::factory()->create(['comments_count' => 1]);
        $comment = Comment::create([
            'user_id' => $user->id,
            'activity_id' => $activity->id,
            'content' => 'To be deleted',
        ]);

        $result = $this->interactionService->deleteComment($user, $comment);

        $this->assertTrue($result['success']);
        $this->assertDatabaseMissing('comments', ['id' => $comment->id]);
        $this->assertEquals(0, $activity->fresh()->comments_count);
    }

    public function test_user_cannot_delete_other_user_comment()
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $activity = Activity::factory()->create();
        $comment = Comment::create([
            'user_id' => $owner->id,
            'activity_id' => $activity->id,
            'content' => 'My comment',
        ]);

        $result = $this->interactionService->deleteComment($otherUser, $comment);

        $this->assertFalse($result['success']);
        $this->assertEquals('NOT_COMMENT_OWNER', $result['code']);
        $this->assertDatabaseHas('comments', ['id' => $comment->id]);
    }

    public function test_comment_endpoints_require_authentication()
    {
        $activity = Activity::factory()->create();

        $response = $this->getJson("/activities/{$activity->id}/comments");
        $response->assertStatus(401);

        $response = $this->postJson("/activities/{$activity->id}/comments", ['content' => 'Test']);
        $response->assertStatus(401);
    }

    public function test_get_comments_endpoint_works()
    {
        $user = User::factory()->create();
        $activity = Activity::factory()->create(['comments_count' => 2]);

        Comment::create(['user_id' => $user->id, 'activity_id' => $activity->id, 'content' => 'Comment 1']);
        Comment::create(['user_id' => $user->id, 'activity_id' => $activity->id, 'content' => 'Comment 2']);

        $response = $this->actingAs($user)
            ->getJson("/activities/{$activity->id}/comments");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'content', 'user', 'created_at', 'is_owner'],
                ],
                'meta' => ['total', 'current_page', 'per_page', 'last_page'],
            ]);
    }

    public function test_add_comment_endpoint_works()
    {
        $user = User::factory()->create();
        $activity = Activity::factory()->create();

        $response = $this->actingAs($user)
            ->postJson("/activities/{$activity->id}/comments", [
                'content' => 'This is a test comment!',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'data' => ['id', 'content', 'user', 'created_at', 'is_owner'],
            ]);
    }

    public function test_add_comment_requires_content()
    {
        $user = User::factory()->create();
        $activity = Activity::factory()->create();

        $response = $this->actingAs($user)
            ->postJson("/activities/{$activity->id}/comments", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['content']);
    }

    public function test_comment_content_max_length()
    {
        $user = User::factory()->create();
        $activity = Activity::factory()->create();

        $response = $this->actingAs($user)
            ->postJson("/activities/{$activity->id}/comments", [
                'content' => str_repeat('a', 1001),
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['content']);
    }

    public function test_update_comment_endpoint_works()
    {
        $user = User::factory()->create();
        $activity = Activity::factory()->create();
        $comment = Comment::create([
            'user_id' => $user->id,
            'activity_id' => $activity->id,
            'content' => 'Original',
        ]);

        $response = $this->actingAs($user)
            ->putJson("/comments/{$comment->id}", [
                'content' => 'Updated content',
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertEquals('Updated content', $comment->fresh()->content);
    }

    public function test_delete_comment_endpoint_works()
    {
        $user = User::factory()->create();
        $activity = Activity::factory()->create(['comments_count' => 1]);
        $comment = Comment::create([
            'user_id' => $user->id,
            'activity_id' => $activity->id,
            'content' => 'To delete',
        ]);

        $response = $this->actingAs($user)
            ->deleteJson("/comments/{$comment->id}");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('comments', ['id' => $comment->id]);
    }

    public function test_commenting_creates_notification_for_activity_owner()
    {
        $commenter = User::factory()->create();
        $activityOwner = User::factory()->create();
        $activity = Activity::factory()->create(['user_id' => $activityOwner->id]);

        $this->interactionService->addComment($commenter, $activity, 'Nice!');

        $this->assertDatabaseHas('notifications', [
            'user_id' => $activityOwner->id,
            'actor_id' => $commenter->id,
            'type' => 'activity_commented',
            'notifiable_type' => 'Activity',
            'notifiable_id' => $activity->id,
        ]);
    }

    public function test_commenting_on_own_activity_does_not_create_notification()
    {
        $user = User::factory()->create();
        $activity = Activity::factory()->create(['user_id' => $user->id]);

        $this->interactionService->addComment($user, $activity, 'My own comment');

        $this->assertDatabaseMissing('notifications', [
            'user_id' => $user->id,
            'type' => 'activity_commented',
        ]);
    }

    // ==================== PRIVACY TESTS ====================

    public function test_cannot_like_private_user_activity_without_following()
    {
        $privateUser = User::factory()->create(['is_private' => true]);
        $otherUser = User::factory()->create();
        $activity = Activity::factory()->create(['user_id' => $privateUser->id]);

        $response = $this->actingAs($otherUser)
            ->postJson("/activities/{$activity->id}/like");

        $response->assertStatus(403);
    }

    public function test_can_like_private_user_activity_when_following()
    {
        $privateUser = User::factory()->create(['is_private' => true]);
        $follower = User::factory()->create();
        $activity = Activity::factory()->create(['user_id' => $privateUser->id]);

        // Create accepted follow relationship
        Follow::create([
            'follower_id' => $follower->id,
            'followed_id' => $privateUser->id,
            'status' => 'accepted',
        ]);

        $response = $this->actingAs($follower)
            ->postJson("/activities/{$activity->id}/like");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    public function test_can_like_own_activity()
    {
        $user = User::factory()->create(['is_private' => true]);
        $activity = Activity::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)
            ->postJson("/activities/{$activity->id}/like");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    public function test_can_like_public_user_activity()
    {
        $publicUser = User::factory()->create(['is_private' => false]);
        $otherUser = User::factory()->create();
        $activity = Activity::factory()->create(['user_id' => $publicUser->id]);

        $response = $this->actingAs($otherUser)
            ->postJson("/activities/{$activity->id}/like");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    public function test_cannot_comment_on_private_user_activity_without_following()
    {
        $privateUser = User::factory()->create(['is_private' => true]);
        $otherUser = User::factory()->create();
        $activity = Activity::factory()->create(['user_id' => $privateUser->id]);

        $response = $this->actingAs($otherUser)
            ->postJson("/activities/{$activity->id}/comments", ['content' => 'Nice!']);

        $response->assertStatus(403);
    }

    public function test_can_comment_on_private_user_activity_when_following()
    {
        $privateUser = User::factory()->create(['is_private' => true]);
        $follower = User::factory()->create();
        $activity = Activity::factory()->create(['user_id' => $privateUser->id]);

        Follow::create([
            'follower_id' => $follower->id,
            'followed_id' => $privateUser->id,
            'status' => 'accepted',
        ]);

        $response = $this->actingAs($follower)
            ->postJson("/activities/{$activity->id}/comments", ['content' => 'Great!']);

        $response->assertStatus(201)
            ->assertJson(['success' => true]);
    }
}
