<?php

namespace Database\Factories;

use App\Models\Activity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Notification>
 */
class NotificationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => 'N-'.strtoupper(Str::random(4)).'-'.strtoupper(Str::random(4)),
            'user_id' => User::factory(),
            'actor_id' => User::factory(),
            'type' => fake()->randomElement(['activity_liked', 'activity_commented', 'follow_accepted']),
            'notifiable_type' => 'Activity',
            'notifiable_id' => Activity::factory(),
            'data' => null,
            'read_at' => null,
        ];
    }

    /**
     * Indicate that the notification has been read.
     */
    public function read(): static
    {
        return $this->state(fn (array $attributes) => [
            'read_at' => now(),
        ]);
    }

    /**
     * Indicate that this is a like notification.
     */
    public function activityLiked(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'activity_liked',
        ]);
    }

    /**
     * Indicate that this is a comment notification.
     */
    public function activityCommented(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'activity_commented',
        ]);
    }

    /**
     * Indicate that this is a follow accepted notification.
     */
    public function followAccepted(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'follow_accepted',
            'notifiable_type' => 'User',
            'notifiable_id' => User::factory(),
        ]);
    }
}
