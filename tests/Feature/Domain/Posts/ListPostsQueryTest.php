<?php

use App\Domain\Posts\ListPostsQuery;
use App\Enums\PermissionRole;
use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\PostTarget;
use App\Models\SocialAccount;
use App\Models\SocialAccountPermission;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('returns posts owned by the user', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    Post::factory()->create(['user_id' => $user->id]);
    Post::factory()->create(['user_id' => $other->id]);

    $posts = (new ListPostsQuery($user))->query()->get();

    expect($posts)->toHaveCount(1);
    expect($posts->first()->user_id)->toBe($user->id);
});

test('returns posts targeting accounts shared with user', function () {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $account = SocialAccount::factory()->x()->create(['user_id' => $owner->id]);

    SocialAccountPermission::create([
        'social_account_id' => $account->id,
        'user_id' => $viewer->id,
        'role' => PermissionRole::Viewer,
    ]);

    $post = Post::factory()->create(['user_id' => $owner->id]);
    PostTarget::factory()->create([
        'post_id' => $post->id,
        'social_account_id' => $account->id,
    ]);

    $posts = (new ListPostsQuery($viewer))->query()->get();

    expect($posts)->toHaveCount(1);
});

test('does not return posts for inaccessible accounts', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $account = SocialAccount::factory()->x()->create(['user_id' => $owner->id]);

    $post = Post::factory()->create(['user_id' => $owner->id]);
    PostTarget::factory()->create([
        'post_id' => $post->id,
        'social_account_id' => $account->id,
    ]);

    $posts = (new ListPostsQuery($stranger))->query()->get();

    expect($posts)->toHaveCount(0);
});

test('filters by status', function () {
    $user = User::factory()->create();
    Post::factory()->create(['user_id' => $user->id, 'status' => PostStatus::Draft]);
    Post::factory()->create(['user_id' => $user->id, 'status' => PostStatus::Scheduled]);

    $posts = (new ListPostsQuery($user))
        ->status('scheduled')
        ->query()
        ->get();

    expect($posts)->toHaveCount(1);
    expect($posts->first()->status)->toBe(PostStatus::Scheduled);
});

test('filters by provider', function () {
    $user = User::factory()->create();
    $xAccount = SocialAccount::factory()->x()->create(['user_id' => $user->id]);
    $bsAccount = SocialAccount::factory()->bluesky()->create(['user_id' => $user->id]);

    $xPost = Post::factory()->create(['user_id' => $user->id]);
    PostTarget::factory()->create(['post_id' => $xPost->id, 'social_account_id' => $xAccount->id]);

    $bsPost = Post::factory()->create(['user_id' => $user->id]);
    PostTarget::factory()->create(['post_id' => $bsPost->id, 'social_account_id' => $bsAccount->id]);

    $posts = (new ListPostsQuery($user))
        ->provider('x')
        ->query()
        ->get();

    expect($posts)->toHaveCount(1);
    expect($posts->first()->id)->toBe($xPost->id);
});

test('filters by account', function () {
    $user = User::factory()->create();
    $accountA = SocialAccount::factory()->x()->create(['user_id' => $user->id]);
    $accountB = SocialAccount::factory()->x()->create(['user_id' => $user->id]);

    $postA = Post::factory()->create(['user_id' => $user->id]);
    PostTarget::factory()->create(['post_id' => $postA->id, 'social_account_id' => $accountA->id]);

    $postB = Post::factory()->create(['user_id' => $user->id]);
    PostTarget::factory()->create(['post_id' => $postB->id, 'social_account_id' => $accountB->id]);

    $posts = (new ListPostsQuery($user))
        ->account((string) $accountA->id)
        ->query()
        ->get();

    expect($posts)->toHaveCount(1);
    expect($posts->first()->id)->toBe($postA->id);
});

test('filters by date range', function () {
    $user = User::factory()->create();

    Post::factory()->create([
        'user_id' => $user->id,
        'scheduled_for' => now()->subDays(5),
    ]);

    $recent = Post::factory()->create([
        'user_id' => $user->id,
        'scheduled_for' => now()->addDay(),
    ]);

    Post::factory()->create([
        'user_id' => $user->id,
        'scheduled_for' => now()->addDays(10),
    ]);

    $posts = (new ListPostsQuery($user))
        ->from(now()->subDay()->toDateTimeString())
        ->to(now()->addDays(3)->toDateTimeString())
        ->query()
        ->get();

    expect($posts)->toHaveCount(1);
    expect($posts->first()->id)->toBe($recent->id);
});

test('combines multiple filters', function () {
    $user = User::factory()->create();
    $xAccount = SocialAccount::factory()->x()->create(['user_id' => $user->id]);

    $match = Post::factory()->create([
        'user_id' => $user->id,
        'status' => PostStatus::Scheduled,
        'scheduled_for' => now()->addDay(),
    ]);
    PostTarget::factory()->create(['post_id' => $match->id, 'social_account_id' => $xAccount->id]);

    $wrongStatus = Post::factory()->create([
        'user_id' => $user->id,
        'status' => PostStatus::Draft,
        'scheduled_for' => now()->addDay(),
    ]);
    PostTarget::factory()->create(['post_id' => $wrongStatus->id, 'social_account_id' => $xAccount->id]);

    $posts = (new ListPostsQuery($user))
        ->status('scheduled')
        ->provider('x')
        ->from(now()->toDateTimeString())
        ->to(now()->addDays(3)->toDateTimeString())
        ->query()
        ->get();

    expect($posts)->toHaveCount(1);
    expect($posts->first()->id)->toBe($match->id);
});

test('empty string filters are ignored', function () {
    $user = User::factory()->create();
    Post::factory()->count(3)->create(['user_id' => $user->id]);

    $posts = (new ListPostsQuery($user))
        ->status('')
        ->provider('')
        ->account('')
        ->from('')
        ->to('')
        ->query()
        ->get();

    expect($posts)->toHaveCount(3);
});
