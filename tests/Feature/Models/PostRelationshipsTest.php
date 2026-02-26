<?php

use App\Enums\PostStatus;
use App\Enums\PostTargetStatus;
use App\Enums\Provider;
use App\Models\Post;
use App\Models\PostTarget;
use App\Models\PostVariant;
use App\Models\SocialAccount;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('post belongs to a user', function () {
    $user = User::factory()->create();
    $post = Post::factory()->create(['user_id' => $user->id]);

    expect($post->user->id)->toBe($user->id);
});

test('post has many targets', function () {
    $post = Post::factory()->create();
    PostTarget::factory()->count(2)->create(['post_id' => $post->id]);

    expect($post->targets)->toHaveCount(2);
});

test('post has many variants', function () {
    $post = Post::factory()->create();
    PostVariant::factory()->count(3)->create(['post_id' => $post->id]);

    expect($post->variants)->toHaveCount(3);
});

test('post target belongs to post and social account', function () {
    $account = SocialAccount::factory()->x()->create();
    $post = Post::factory()->create();
    $target = PostTarget::factory()->create([
        'post_id' => $post->id,
        'social_account_id' => $account->id,
    ]);

    expect($target->post->id)->toBe($post->id);
    expect($target->socialAccount->id)->toBe($account->id);
});

test('social account has correct provider cast', function () {
    $account = SocialAccount::factory()->x()->create();

    expect($account->provider)->toBe(Provider::X);
});

test('post has correct status cast', function () {
    $post = Post::factory()->scheduled()->create();

    expect($post->status)->toBe(PostStatus::Scheduled);
});

test('post target has correct status cast', function () {
    $target = PostTarget::factory()->sent()->create();

    expect($target->status)->toBe(PostTargetStatus::Sent);
    expect($target->sent_at)->not->toBeNull();
});

test('user has many social accounts', function () {
    $user = User::factory()->create();
    SocialAccount::factory()->count(3)->create(['user_id' => $user->id]);

    expect($user->socialAccounts)->toHaveCount(3);
});

test('user has many posts', function () {
    $user = User::factory()->create();
    Post::factory()->count(2)->create(['user_id' => $user->id]);

    expect($user->posts)->toHaveCount(2);
});

test('social account credentials are encrypted', function () {
    $account = SocialAccount::factory()->create([
        'credentials_encrypted' => ['api_key' => 'secret-123', 'api_secret' => 'secret-456'],
    ]);

    $fresh = SocialAccount::find($account->id);

    expect($fresh->credentials_encrypted)->toBe(['api_key' => 'secret-123', 'api_secret' => 'secret-456']);

    // Verify the raw database value is not plain text
    $raw = \Illuminate\Support\Facades\DB::table('social_accounts')
        ->where('id', $account->id)
        ->value('credentials_encrypted');

    expect($raw)->not->toContain('secret-123');
});

test('user accessible social accounts includes owned and shared', function () {
    $owner = User::factory()->create();
    $collaborator = User::factory()->create();

    $ownedAccount = SocialAccount::factory()->create(['user_id' => $owner->id]);
    $sharedAccount = SocialAccount::factory()->create(['user_id' => $collaborator->id]);

    \App\Models\SocialAccountPermission::create([
        'social_account_id' => $sharedAccount->id,
        'user_id' => $owner->id,
        'role' => \App\Enums\PermissionRole::Editor,
    ]);

    $accessible = $owner->accessibleSocialAccounts();

    expect($accessible)->toHaveCount(2);
    expect($accessible->pluck('id')->toArray())->toContain($ownedAccount->id, $sharedAccount->id);
});
