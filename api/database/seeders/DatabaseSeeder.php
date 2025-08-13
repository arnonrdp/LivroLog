<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            BookSeeder::class,
            UserBookSeeder::class,
            RealisticReviewSeeder::class,
            SocialSeeder::class,
        ]);

        $this->command->info('✅ Database seeded successfully with realistic data!');
        $this->command->info('📊 Created:');
        $this->command->info('   - 10 Users (9 regular + 1 admin)');
        $this->command->info('   - 12 Real Books with working thumbnails');
        $this->command->info('   - 10-30 Books per user in their libraries');
        $this->command->info('   - Reviews with different ratings, visibility, and spoiler settings');
        $this->command->info('   - Social follows between users');
        $this->command->info('   - Popular books available via /showcase endpoint');
    }
}
