<?php

use App\Enums\MediaType;
use App\Models\Post;
use App\Models\PostMedia;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('post media belongs to a post', function () {
    $media = PostMedia::factory()->create();

    expect($media->post)->toBeInstanceOf(Post::class);
});

test('post media belongs to a user', function () {
    $media = PostMedia::factory()->create();

    expect($media->user)->toBeInstanceOf(User::class);
});

test('post has many media', function () {
    $post = Post::factory()->create();

    PostMedia::factory()->count(3)->create([
        'post_id' => $post->id,
        'user_id' => $post->user_id,
    ]);

    expect($post->media)->toHaveCount(3);
});

test('post media casts type to media type enum', function () {
    $media = PostMedia::factory()->create(['type' => 'image']);

    expect($media->type)->toBe(MediaType::Image);
});

test('post media gif factory', function () {
    $media = PostMedia::factory()->gif()->create();

    expect($media->type)->toBe(MediaType::Gif);
    expect($media->mime_type)->toBe('image/gif');
});

test('post media video factory', function () {
    $media = PostMedia::factory()->video()->create();

    expect($media->type)->toBe(MediaType::Video);
    expect($media->mime_type)->toBe('video/mp4');
    expect($media->duration_seconds)->not->toBeNull();
});

test('post media can be unattached to a post', function () {
    $user = User::factory()->create();

    $media = PostMedia::factory()->create([
        'post_id' => null,
        'user_id' => $user->id,
    ]);

    expect($media->post)->toBeNull();
    expect($media->user_id)->toBe($user->id);
});
