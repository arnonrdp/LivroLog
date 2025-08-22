<?php

namespace Tests\Feature;

use App\Models\Follow;
use App\Models\User;
use App\Services\FollowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FollowSystemTest extends TestCase
{
    use RefreshDatabase;

    protected FollowService $followService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->followService = app(FollowService::class);
    }

    public function test_user_can_follow_another_user()
    {
        $follower = User::factory()->create();
        $following = User::factory()->create();

        $result = $this->followService->follow($follower, $following);

        $this->assertTrue($result['success']);
        $this->assertTrue($follower->isFollowing($following));
        $this->assertEquals(1, $follower->fresh()->following_count);
        $this->assertEquals(1, $following->fresh()->followers_count);
    }

    public function test_user_can_unfollow_another_user()
    {
        $follower = User::factory()->create(['following_count' => 1]);
        $following = User::factory()->create(['followers_count' => 1]);

        Follow::create([
            'follower_id' => $follower->id,
            'followed_id' => $following->id,
        ]);

        $result = $this->followService->unfollow($follower, $following);

        $this->assertTrue($result['success']);
        $this->assertFalse($follower->isFollowing($following));
        $this->assertEquals(0, $follower->fresh()->following_count);
        $this->assertEquals(0, $following->fresh()->followers_count);
    }

    public function test_user_cannot_follow_themselves()
    {
        $user = User::factory()->create();

        $result = $this->followService->follow($user, $user);

        $this->assertFalse($result['success']);
        $this->assertEquals('CANNOT_FOLLOW_SELF', $result['code']);
    }

    public function test_user_cannot_follow_same_user_twice()
    {
        $follower = User::factory()->create();
        $following = User::factory()->create();

        // First follow
        $this->followService->follow($follower, $following);

        // Second follow attempt
        $result = $this->followService->follow($follower, $following);

        $this->assertFalse($result['success']);
        $this->assertEquals('ALREADY_FOLLOWING', $result['code']);
    }

    public function test_follow_endpoints_require_authentication()
    {
        $user = User::factory()->create();

        $response = $this->postJson("/users/{$user->id}/follow");
        $response->assertStatus(401);

        $response = $this->deleteJson("/users/{$user->id}/follow");
        $response->assertStatus(401);

        $response = $this->getJson("/users/{$user->id}/followers");
        $response->assertStatus(401);

        $response = $this->getJson("/users/{$user->id}/following");
        $response->assertStatus(401);
    }

    public function test_follow_endpoint_works()
    {
        $follower = User::factory()->create();
        $following = User::factory()->create();

        $response = $this->actingAs($follower)
            ->postJson("/users/{$following->id}/follow");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Successfully followed user',
            ]);
    }

    public function test_unfollow_endpoint_works()
    {
        $follower = User::factory()->create();
        $following = User::factory()->create();

        // First follow
        $this->followService->follow($follower, $following);

        $response = $this->actingAs($follower)
            ->deleteJson("/users/{$following->id}/follow");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Successfully unfollowed user',
            ]);
    }

    public function test_followers_endpoint_works()
    {
        $user = User::factory()->create();
        $follower1 = User::factory()->create();
        $follower2 = User::factory()->create();

        $this->followService->follow($follower1, $user);
        $this->followService->follow($follower2, $user);

        $response = $this->actingAs($user)
            ->getJson("/users/{$user->id}/followers");

        $response->assertStatus(200)
            ->assertJson([
                'meta' => [
                    'total' => 2,
                ],
            ]);
    }

    public function test_following_endpoint_works()
    {
        $user = User::factory()->create();
        $following1 = User::factory()->create();
        $following2 = User::factory()->create();

        $this->followService->follow($user, $following1);
        $this->followService->follow($user, $following2);

        $response = $this->actingAs($user)
            ->getJson("/users/{$user->id}/following");

        $response->assertStatus(200)
            ->assertJson([
                'meta' => [
                    'total' => 2,
                ],
            ]);
    }

    public function test_follow_counts_reconciliation()
    {
        $user = User::factory()->create([
            'followers_count' => 5, // Wrong count
            'following_count' => 3, // Wrong count
        ]);

        // Create actual follows (2 followers, 1 following)
        $follower1 = User::factory()->create();
        $follower2 = User::factory()->create();
        $following1 = User::factory()->create();

        Follow::create(['follower_id' => $follower1->id, 'followed_id' => $user->id]);
        Follow::create(['follower_id' => $follower2->id, 'followed_id' => $user->id]);
        Follow::create(['follower_id' => $user->id, 'followed_id' => $following1->id]);

        $result = $this->followService->recalculateFollowCounts($user);

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['updated_count']);

        $user->refresh();
        $this->assertEquals(2, $user->followers_count);
        $this->assertEquals(1, $user->following_count);
    }
}
