<?php

namespace Database\Factories;

use App\Enums\ScopeType;
use App\Models\Post;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PostVariant>
 */
class PostVariantFactory extends Factory
{
    public function definition(): array
    {
        return [
            'post_id' => Post::factory(),
            'scope_type' => ScopeType::Default,
            'scope_value' => null,
            'body_text' => fake()->sentence(),
        ];
    }

    public function forProvider(string $provider): static
    {
        return $this->state(fn () => [
            'scope_type' => ScopeType::Provider,
            'scope_value' => $provider,
        ]);
    }

    public function forSocialAccount(int $socialAccountId): static
    {
        return $this->state(fn () => [
            'scope_type' => ScopeType::SocialAccount,
            'scope_value' => (string) $socialAccountId,
        ]);
    }
}
