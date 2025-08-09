<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Follow;
use App\Services\FollowService;
use Illuminate\Database\Seeder;

class FollowSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $followService = app(FollowService::class);

        // Get existing users
        $users = User::all();

        if ($users->count() < 2) {
            $this->command->info('Need at least 2 users to create follow relationships');
            return;
        }

        $this->command->info("Creating follow relationships for {$users->count()} users...");

        // Create some follow relationships
        foreach ($users as $index => $user) {
            // Each user follows 1-2 random other users
            $followCount = min(rand(1, 2), $users->count() - 1);
            $otherUsers = $users->except($user->id)->random($followCount);

            foreach ($otherUsers as $otherUser) {
                try {
                    $followService->follow($user, $otherUser);
                    $this->command->info("{$user->display_name} is now following {$otherUser->display_name}");
                } catch (\Exception $e) {
                    // Skip if relationship already exists
                    continue;
                }
            }
        }

        $this->command->info('Follow relationships created successfully!');

        // Display stats
        $totalFollows = Follow::count();
        $avgFollowers = User::avg('followers_count');
        $avgFollowing = User::avg('following_count');

        $this->command->info("Total follow relationships: {$totalFollows}");
        $this->command->info("Average followers per user: " . round($avgFollowers, 2));
        $this->command->info("Average following per user: " . round($avgFollowing, 2));
    }
}
