<?php

namespace Database\Factories;

use App\Enums\PostTargetStatus;
use App\Models\Post;
use App\Models\SocialAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PostTarget>
 */
class PostTargetFactory extends Factory
{
    public function definition(): array
    {
        return [
            'post_id' => Post::factory(),
            'social_account_id' => SocialAccount::factory(),
            'status' => PostTargetStatus::Pending,
        ];
    }

    public function sent(): static
    {
        return $this->state(fn () => [
            'status' => PostTargetStatus::Sent,
            'sent_at' => now(),
        ]);
    }

    public function failed(string $message = 'Provider error'): static
    {
        return $this->state(fn () => [
            'status' => PostTargetStatus::Failed,
            'error_message' => $message,
        ]);
    }
}
