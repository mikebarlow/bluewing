<?php

namespace Database\Factories;

use App\Enums\PostStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Post>
 */
class PostFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'scheduled_for' => now()->addHour(),
            'status' => PostStatus::Draft,
        ];
    }

    public function scheduled(): static
    {
        return $this->state(fn () => [
            'status' => PostStatus::Scheduled,
        ]);
    }

    public function sent(): static
    {
        return $this->state(fn () => [
            'status' => PostStatus::Sent,
            'sent_at' => now(),
        ]);
    }
}
