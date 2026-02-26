<?php

use App\Domain\Posts\PostData;
use App\Domain\Posts\UpdatePostAction;
use App\Enums\PostStatus;
use App\Enums\PostTargetStatus;
use App\Enums\ScopeType;
use App\Models\Post;
use App\Models\PostMedia;
use App\Models\PostTarget;
use App\Models\PostVariant;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Validation\ValidationException;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('updates a draft post and replaces variants and targets', function () {
    $user = User::factory()->create();
    $accountA = SocialAccount::factory()->x()->create(['user_id' => $user->id]);
    $accountB = SocialAccount::factory()->bluesky()->create(['user_id' => $user->id]);

    $post = Post::factory()->create([
        'user_id' => $user->id,
        'status' => PostStatus::Draft,
    ]);

    PostVariant::factory()->create([
        'post_id' => $post->id,
        'scope_type' => ScopeType::Default,
        'body_text' => 'Old text',
    ]);

    PostTarget::factory()->create([
        'post_id' => $post->id,
        'social_account_id' => $accountA->id,
        'status' => PostTargetStatus::Pending,
    ]);

    $action = new UpdatePostAction;

    $updated = $action->execute($post, $user, new PostData(
        scheduledFor: now()->addDays(2)->toDateTimeString(),
        bodyText: 'New text',
        targetAccountIds: [$accountB->id],
        status: PostStatus::Scheduled,
    ));

    expect($updated->status)->toBe(PostStatus::Scheduled);
    expect($updated->variants)->toHaveCount(1);
    expect($updated->variants->first()->body_text)->toBe('New text');
    expect($updated->targets)->toHaveCount(1);
    expect($updated->targets->first()->social_account_id)->toBe($accountB->id);
});

test('cannot update a queued post', function () {
    $user = User::factory()->create();
    $account = SocialAccount::factory()->x()->create(['user_id' => $user->id]);

    $post = Post::factory()->create([
        'user_id' => $user->id,
        'status' => PostStatus::Queued,
    ]);

    $action = new UpdatePostAction;

    $action->execute($post, $user, new PostData(
        scheduledFor: now()->addHour()->toDateTimeString(),
        bodyText: 'Should fail',
        targetAccountIds: [$account->id],
    ));
})->throws(ValidationException::class);

test('cannot update a sent post', function () {
    $user = User::factory()->create();
    $account = SocialAccount::factory()->x()->create(['user_id' => $user->id]);

    $post = Post::factory()->create([
        'user_id' => $user->id,
        'status' => PostStatus::Sent,
    ]);

    $action = new UpdatePostAction;

    $action->execute($post, $user, new PostData(
        scheduledFor: now()->addHour()->toDateTimeString(),
        bodyText: 'Should fail',
        targetAccountIds: [$account->id],
    ));
})->throws(ValidationException::class);

test('rejects update when user lacks editor access', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $account = SocialAccount::factory()->x()->create(['user_id' => $owner->id]);

    $post = Post::factory()->create([
        'user_id' => $owner->id,
        'status' => PostStatus::Draft,
    ]);

    $action = new UpdatePostAction;

    $action->execute($post, $stranger, new PostData(
        scheduledFor: now()->addHour()->toDateTimeString(),
        bodyText: 'Hacked',
        targetAccountIds: [$account->id],
    ));
})->throws(ValidationException::class);

// ──────────────────────────────────────────────────────────
// Media integration tests
// ──────────────────────────────────────────────────────────

test('update attaches new media to post', function () {
    $user = User::factory()->create();
    $account = SocialAccount::factory()->x()->create(['user_id' => $user->id]);

    $post = Post::factory()->create([
        'user_id' => $user->id,
        'status' => PostStatus::Draft,
    ]);
    PostVariant::factory()->create(['post_id' => $post->id, 'scope_type' => ScopeType::Default]);
    PostTarget::factory()->create(['post_id' => $post->id, 'social_account_id' => $account->id]);

    $media = PostMedia::factory()->create([
        'post_id' => null,
        'user_id' => $user->id,
        'size_bytes' => 100_000,
    ]);

    $action = new UpdatePostAction;

    $updated = $action->execute($post, $user, new PostData(
        scheduledFor: now()->addHour()->toDateTimeString(),
        bodyText: 'Updated with media',
        targetAccountIds: [$account->id],
        status: PostStatus::Scheduled,
        mediaIds: [$media->id],
    ));

    expect($updated->media)->toHaveCount(1);
    expect($updated->media->first()->id)->toBe($media->id);
});

test('update detaches old media when replaced', function () {
    $user = User::factory()->create();
    $account = SocialAccount::factory()->x()->create(['user_id' => $user->id]);

    $post = Post::factory()->create([
        'user_id' => $user->id,
        'status' => PostStatus::Draft,
    ]);
    PostVariant::factory()->create(['post_id' => $post->id, 'scope_type' => ScopeType::Default]);
    PostTarget::factory()->create(['post_id' => $post->id, 'social_account_id' => $account->id]);

    $oldMedia = PostMedia::factory()->create([
        'post_id' => $post->id,
        'user_id' => $user->id,
        'size_bytes' => 100_000,
        'alt_text' => 'Old alt text',
    ]);

    $newMedia = PostMedia::factory()->create([
        'post_id' => null,
        'user_id' => $user->id,
        'size_bytes' => 200_000,
    ]);

    $action = new UpdatePostAction;

    $updated = $action->execute($post, $user, new PostData(
        scheduledFor: now()->addHour()->toDateTimeString(),
        bodyText: 'Replaced media',
        targetAccountIds: [$account->id],
        status: PostStatus::Draft,
        mediaIds: [$newMedia->id],
    ));

    expect($updated->media)->toHaveCount(1);
    expect($updated->media->first()->id)->toBe($newMedia->id);

    $oldMedia->refresh();
    expect($oldMedia->post_id)->toBeNull();
    expect($oldMedia->alt_text)->toBeNull();
});

test('update removes all media when none provided', function () {
    $user = User::factory()->create();
    $account = SocialAccount::factory()->x()->create(['user_id' => $user->id]);

    $post = Post::factory()->create([
        'user_id' => $user->id,
        'status' => PostStatus::Draft,
    ]);
    PostVariant::factory()->create(['post_id' => $post->id, 'scope_type' => ScopeType::Default]);
    PostTarget::factory()->create(['post_id' => $post->id, 'social_account_id' => $account->id]);

    $media = PostMedia::factory()->create([
        'post_id' => $post->id,
        'user_id' => $user->id,
        'size_bytes' => 100_000,
    ]);

    $action = new UpdatePostAction;

    $updated = $action->execute($post, $user, new PostData(
        scheduledFor: now()->addHour()->toDateTimeString(),
        bodyText: 'No media now',
        targetAccountIds: [$account->id],
        status: PostStatus::Draft,
        mediaIds: [],
    ));

    expect($updated->media)->toHaveCount(0);

    $media->refresh();
    expect($media->post_id)->toBeNull();
});

test('update applies alt text to media', function () {
    $user = User::factory()->create();
    $account = SocialAccount::factory()->bluesky()->create(['user_id' => $user->id]);

    $post = Post::factory()->create([
        'user_id' => $user->id,
        'status' => PostStatus::Draft,
    ]);
    PostVariant::factory()->create(['post_id' => $post->id, 'scope_type' => ScopeType::Default]);
    PostTarget::factory()->create(['post_id' => $post->id, 'social_account_id' => $account->id]);

    $media = PostMedia::factory()->create([
        'post_id' => null,
        'user_id' => $user->id,
        'size_bytes' => 100_000,
    ]);

    $action = new UpdatePostAction;

    $action->execute($post, $user, new PostData(
        scheduledFor: now()->addHour()->toDateTimeString(),
        bodyText: 'With alt text',
        targetAccountIds: [$account->id],
        status: PostStatus::Draft,
        mediaIds: [$media->id],
        altTexts: [$media->id => 'Mountain landscape'],
    ));

    $media->refresh();
    expect($media->alt_text)->toBe('Mountain landscape');
});

test('update rejects media exceeding cross-post limit', function () {
    $user = User::factory()->create();
    $xAccount = SocialAccount::factory()->x()->create(['user_id' => $user->id]);
    $bsAccount = SocialAccount::factory()->bluesky()->create(['user_id' => $user->id]);

    $post = Post::factory()->create([
        'user_id' => $user->id,
        'status' => PostStatus::Draft,
    ]);
    PostVariant::factory()->create(['post_id' => $post->id, 'scope_type' => ScopeType::Default]);

    $media = PostMedia::factory()->create([
        'post_id' => null,
        'user_id' => $user->id,
        'size_bytes' => 1_000_001,
    ]);

    $action = new UpdatePostAction;

    $action->execute($post, $user, new PostData(
        scheduledFor: now()->addHour()->toDateTimeString(),
        bodyText: 'Too big for bluesky',
        targetAccountIds: [$xAccount->id, $bsAccount->id],
        mediaIds: [$media->id],
    ));
})->throws(ValidationException::class, 'exceeds the maximum size');
