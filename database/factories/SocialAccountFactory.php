<?php

namespace Database\Factories;

use App\Enums\Provider;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SocialAccount>
 */
class SocialAccountFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'provider' => fake()->randomElement(Provider::cases()),
            'display_name' => '@'.fake()->userName(),
            'external_identifier' => (string) fake()->unique()->randomNumber(9),
            'credentials_encrypted' => ['token' => 'test-token'],
        ];
    }

    public function x(): static
    {
        return $this->state(fn () => [
            'provider' => Provider::X,
        ]);
    }

    public function bluesky(): static
    {
        return $this->state(fn () => [
            'provider' => Provider::Bluesky,
        ]);
    }
}
