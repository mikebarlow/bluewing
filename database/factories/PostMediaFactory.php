<?php

namespace Database\Factories;

use App\Enums\MediaType;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PostMedia>
 */
class PostMediaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'post_id' => Post::factory(),
            'user_id' => User::factory(),
            'type' => MediaType::Image,
            'original_filename' => $this->faker->word().'.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => $this->faker->numberBetween(1000, 500_000),
            'storage_disk' => 'local',
            'storage_path' => 'media/'.$this->faker->uuid().'.jpg',
            'alt_text' => null,
            'width' => 1200,
            'height' => 800,
            'duration_seconds' => null,
        ];
    }

    public function gif(): static
    {
        return $this->state([
            'type' => MediaType::Gif,
            'original_filename' => $this->faker->word().'.gif',
            'mime_type' => 'image/gif',
        ]);
    }

    public function video(): static
    {
        return $this->state([
            'type' => MediaType::Video,
            'original_filename' => $this->faker->word().'.mp4',
            'mime_type' => 'video/mp4',
            'width' => 1920,
            'height' => 1080,
            'duration_seconds' => $this->faker->numberBetween(5, 120),
        ]);
    }
}
