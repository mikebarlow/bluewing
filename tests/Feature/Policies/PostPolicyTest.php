<?php

use App\Enums\PermissionRole;
use App\Models\Post;
use App\Models\PostTarget;
use App\Models\SocialAccount;
use App\Models\SocialAccountPermission;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('creator can view their own post', function () {
    $user = User::factory()->create();
    $post = Post::factory()->create(['user_id' => $user->id]);

    expect($user->can('view', $post))->toBeTrue();
});

test('user with access to a target account can view the post', function () {
    $creator = User::factory()->create();
    $viewer = User::factory()->create();

    $account = SocialAccount::factory()->create(['user_id' => $creator->id]);

    SocialAccountPermission::create([
        'social_account_id' => $account->id,
        'user_id' => $viewer->id,
        'role' => PermissionRole::Viewer,
    ]);

    $post = Post::factory()->create(['user_id' => $creator->id]);
    PostTarget::factory()->create([
        'post_id' => $post->id,
        'social_account_id' => $account->id,
    ]);

    expect($viewer->can('view', $post))->toBeTrue();
});

test('stranger cannot view post', function () {
    $creator = User::factory()->create();
    $stranger = User::factory()->create();
    $post = Post::factory()->create(['user_id' => $creator->id]);

    expect($stranger->can('view', $post))->toBeFalse();
});

test('creator can update and delete their post', function () {
    $user = User::factory()->create();
    $post = Post::factory()->create(['user_id' => $user->id]);

    expect($user->can('update', $post))->toBeTrue();
    expect($user->can('delete', $post))->toBeTrue();
});

test('non-creator cannot update or delete post', function () {
    $creator = User::factory()->create();
    $other = User::factory()->create();
    $post = Post::factory()->create(['user_id' => $creator->id]);

    expect($other->can('update', $post))->toBeFalse();
    expect($other->can('delete', $post))->toBeFalse();
});
