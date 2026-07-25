<?php

namespace Database\Factories;

use App\Models\Tag;
use App\Models\User;
use App\Services\TagService;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tag>
 */
class TagFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => 'T-'.strtoupper(Str::random(4)).'-'.strtoupper(Str::random(4)),
            'user_id' => User::factory(),
            'name' => fake()->unique()->word(),
            'color' => fake()->randomElement(TagService::COLORS),
        ];
    }
}
