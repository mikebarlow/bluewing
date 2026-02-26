<?php

use App\Domain\Posts\CreatePostAction;
use App\Domain\Posts\PostData;
use App\Enums\PermissionRole;
use App\Enums\PostStatus;
use App\Enums\PostTargetStatus;
use App\Enums\ScopeType;
use App\Models\PostMedia;
use App\Models\SocialAccount;
use App\Models\SocialAccountPermission;
use App\Models\User;
use Illuminate\Validation\ValidationException;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('creates a draft post with default variant and targets', function () {
    $user = User::factory()->create();
    $account = SocialAccount::factory()->x()->create(['user_id' => $user->id]);

    $action = new CreatePostAction;

    $post = $action->execute($user, new PostData(
        scheduledFor: now()->addHour()->toDateTimeString(),
        bodyText: 'Hello world',
        targetAccountIds: [$account->id],
        status: PostStatus::Draft,
    ));

    expect($post->status)->toBe(PostStatus::Draft);
    expect($post->user_id)->toBe($user->id);
    expect($post->variants)->toHaveCount(1);
    expect($post->variants->first()->scope_type)->toBe(ScopeType::Default);
    expect($post->variants->first()->body_text)->toBe('Hello world');
    expect($post->targets)->toHaveCount(1);
    expect($post->targets->first()->social_account_id)->toBe($account->id);
    expect($post->targets->first()->status)->toBe(PostTargetStatus::Pending);
});

test('creates a scheduled post with provider and account overrides', function () {
    $user = User::factory()->create();
    $xAccount = SocialAccount::factory()->x()->create(['user_id' => $user->id]);
    $bsAccount = SocialAccount::factory()->bluesky()->create(['user_id' => $user->id]);

    $action = new CreatePostAction;

    $post = $action->execute($user, new PostData(
        scheduledFor: now()->addDay()->toDateTimeString(),
        bodyText: 'Default text',
        targetAccountIds: [$xAccount->id, $bsAccount->id],
        providerOverrides: ['x' => 'X-specific text'],
        accountOverrides: [$bsAccount->id => 'Bluesky account override'],
        status: PostStatus::Scheduled,
    ));

    expect($post->status)->toBe(PostStatus::Scheduled);
    expect($post->variants)->toHaveCount(3);
    expect($post->targets)->toHaveCount(2);

    $default = $post->variants->where('scope_type', ScopeType::Default)->first();
    expect($default->body_text)->toBe('Default text');

    $providerVariant = $post->variants->where('scope_type', ScopeType::Provider)->first();
    expect($providerVariant->scope_value)->toBe('x');
    expect($providerVariant->body_text)->toBe('X-specific text');

    $accountVariant = $post->variants->where('scope_type', ScopeType::SocialAccount)->first();
    expect($accountVariant->scope_value)->toBe((string) $bsAccount->id);
    expect($accountVariant->body_text)->toBe('Bluesky account override');
});

test('skips empty provider and account overrides', function () {
    $user = User::factory()->create();
    $account = SocialAccount::factory()->x()->create(['user_id' => $user->id]);

    $action = new CreatePostAction;

    $post = $action->execute($user, new PostData(
        scheduledFor: now()->addHour()->toDateTimeString(),
        bodyText: 'Only default',
        targetAccountIds: [$account->id],
        providerOverrides: ['x' => '', 'bluesky' => '   '],
        accountOverrides: [$account->id => ''],
    ));

    expect($post->variants)->toHaveCount(1);
});

test('throws validation error when user lacks editor access to a target', function () {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $account = SocialAccount::factory()->x()->create(['user_id' => $owner->id]);

    SocialAccountPermission::create([
        'social_account_id' => $account->id,
        'user_id' => $viewer->id,
        'role' => PermissionRole::Viewer,
    ]);

    $action = new CreatePostAction;

    $action->execute($viewer, new PostData(
        scheduledFor: now()->addHour()->toDateTimeString(),
        bodyText: 'Should fail',
        targetAccountIds: [$account->id],
    ));
})->throws(ValidationException::class);

test('editor on shared account can create post', function () {
    $owner = User::factory()->create();
    $editor = User::factory()->create();
    $account = SocialAccount::factory()->x()->create(['user_id' => $owner->id]);

    SocialAccountPermission::create([
        'social_account_id' => $account->id,
        'user_id' => $editor->id,
        'role' => PermissionRole::Editor,
    ]);

    $action = new CreatePostAction;

    $post = $action->execute($editor, new PostData(
        scheduledFor: now()->addHour()->toDateTimeString(),
        bodyText: 'Shared account post',
        targetAccountIds: [$account->id],
        status: PostStatus::Scheduled,
    ));

    expect($post->user_id)->toBe($editor->id);
    expect($post->targets)->toHaveCount(1);
});

test('throws validation error for nonexistent account', function () {
    $user = User::factory()->create();

    $action = new CreatePostAction;

    $action->execute($user, new PostData(
        scheduledFor: now()->addHour()->toDateTimeString(),
        bodyText: 'Bad target',
        targetAccountIds: [99999],
    ));
})->throws(ValidationException::class);

// ──────────────────────────────────────────────────────────
// Media integration tests
// ──────────────────────────────────────────────────────────

test('creates post with attached media', function () {
    $user = User::factory()->create();
    $account = SocialAccount::factory()->x()->create(['user_id' => $user->id]);

    $media1 = PostMedia::factory()->create([
        'post_id' => null,
        'user_id' => $user->id,
        'size_bytes' => 100_000,
    ]);
    $media2 = PostMedia::factory()->create([
        'post_id' => null,
        'user_id' => $user->id,
        'size_bytes' => 200_000,
    ]);

    $action = new CreatePostAction;

    $post = $action->execute($user, new PostData(
        scheduledFor: now()->addHour()->toDateTimeString(),
        bodyText: 'Post with images',
        targetAccountIds: [$account->id],
        status: PostStatus::Scheduled,
        mediaIds: [$media1->id, $media2->id],
    ));

    $post->load('media');

    expect($post->media)->toHaveCount(2);
    expect($post->media->pluck('id')->sort()->values()->all())
        ->toBe([$media1->id, $media2->id]);
});

test('applies alt texts to attached media', function () {
    $user = User::factory()->create();
    $account = SocialAccount::factory()->bluesky()->create(['user_id' => $user->id]);

    $media = PostMedia::factory()->create([
        'post_id' => null,
        'user_id' => $user->id,
        'size_bytes' => 100_000,
        'alt_text' => null,
    ]);

    $action = new CreatePostAction;

    $post = $action->execute($user, new PostData(
        scheduledFor: now()->addHour()->toDateTimeString(),
        bodyText: 'Post with alt text',
        targetAccountIds: [$account->id],
        status: PostStatus::Scheduled,
        mediaIds: [$media->id],
        altTexts: [$media->id => 'A beautiful sunset over the ocean'],
    ));

    $media->refresh();

    expect($media->post_id)->toBe($post->id);
    expect($media->alt_text)->toBe('A beautiful sunset over the ocean');
});

test('post without media still works', function () {
    $user = User::factory()->create();
    $account = SocialAccount::factory()->x()->create(['user_id' => $user->id]);

    $action = new CreatePostAction;

    $post = $action->execute($user, new PostData(
        scheduledFor: now()->addHour()->toDateTimeString(),
        bodyText: 'Text only',
        targetAccountIds: [$account->id],
        status: PostStatus::Draft,
        mediaIds: [],
    ));

    $post->load('media');

    expect($post->media)->toHaveCount(0);
    expect($post->variants)->toHaveCount(1);
});

test('rejects media that does not belong to user', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $account = SocialAccount::factory()->x()->create(['user_id' => $user->id]);

    $media = PostMedia::factory()->create([
        'post_id' => null,
        'user_id' => $otherUser->id,
        'size_bytes' => 100_000,
    ]);

    $action = new CreatePostAction;

    $action->execute($user, new PostData(
        scheduledFor: now()->addHour()->toDateTimeString(),
        bodyText: 'Stolen media',
        targetAccountIds: [$account->id],
        mediaIds: [$media->id],
    ));
})->throws(ValidationException::class, 'could not be found');

test('rejects nonexistent media ids', function () {
    $user = User::factory()->create();
    $account = SocialAccount::factory()->x()->create(['user_id' => $user->id]);

    $action = new CreatePostAction;

    $action->execute($user, new PostData(
        scheduledFor: now()->addHour()->toDateTimeString(),
        bodyText: 'Ghost media',
        targetAccountIds: [$account->id],
        mediaIds: [99999],
    ));
})->throws(ValidationException::class, 'could not be found');

test('rejects image exceeding bluesky limit when cross posting', function () {
    $user = User::factory()->create();
    $xAccount = SocialAccount::factory()->x()->create(['user_id' => $user->id]);
    $bsAccount = SocialAccount::factory()->bluesky()->create(['user_id' => $user->id]);

    $media = PostMedia::factory()->create([
        'post_id' => null,
        'user_id' => $user->id,
        'size_bytes' => 1_000_001, // Exceeds Bluesky's 1,000,000 byte limit
    ]);

    $action = new CreatePostAction;

    $action->execute($user, new PostData(
        scheduledFor: now()->addHour()->toDateTimeString(),
        bodyText: 'Too big for Bluesky',
        targetAccountIds: [$xAccount->id, $bsAccount->id],
        mediaIds: [$media->id],
    ));
})->throws(ValidationException::class, 'exceeds the maximum size');

test('allows image within x limit when only targeting x', function () {
    $user = User::factory()->create();
    $xAccount = SocialAccount::factory()->x()->create(['user_id' => $user->id]);

    $media = PostMedia::factory()->create([
        'post_id' => null,
        'user_id' => $user->id,
        'size_bytes' => 4_000_000, // 4 MB, within X's 5 MB limit
    ]);

    $action = new CreatePostAction;

    $post = $action->execute($user, new PostData(
        scheduledFor: now()->addHour()->toDateTimeString(),
        bodyText: 'Big image for X only',
        targetAccountIds: [$xAccount->id],
        status: PostStatus::Scheduled,
        mediaIds: [$media->id],
    ));

    $post->load('media');

    expect($post->media)->toHaveCount(1);
});

test('rejects mixing images and video', function () {
    $user = User::factory()->create();
    $account = SocialAccount::factory()->x()->create(['user_id' => $user->id]);

    $image = PostMedia::factory()->create([
        'post_id' => null,
        'user_id' => $user->id,
        'size_bytes' => 100_000,
    ]);
    $video = PostMedia::factory()->video()->create([
        'post_id' => null,
        'user_id' => $user->id,
        'size_bytes' => 100_000,
    ]);

    $action = new CreatePostAction;

    $action->execute($user, new PostData(
        scheduledFor: now()->addHour()->toDateTimeString(),
        bodyText: 'Mixed media',
        targetAccountIds: [$account->id],
        mediaIds: [$image->id, $video->id],
    ));
})->throws(ValidationException::class, 'cannot mix');

test('allows single video attachment', function () {
    $user = User::factory()->create();
    $account = SocialAccount::factory()->x()->create(['user_id' => $user->id]);

    $video = PostMedia::factory()->video()->create([
        'post_id' => null,
        'user_id' => $user->id,
        'size_bytes' => 50_000_000,
    ]);

    $action = new CreatePostAction;

    $post = $action->execute($user, new PostData(
        scheduledFor: now()->addHour()->toDateTimeString(),
        bodyText: 'Video post',
        targetAccountIds: [$account->id],
        status: PostStatus::Scheduled,
        mediaIds: [$video->id],
    ));

    $post->load('media');

    expect($post->media)->toHaveCount(1);
    expect($post->media->first()->type->value)->toBe('video');
});

test('rejects more than 4 images', function () {
    $user = User::factory()->create();
    $account = SocialAccount::factory()->x()->create(['user_id' => $user->id]);

    $mediaIds = [];
    for ($i = 0; $i < 5; $i++) {
        $mediaIds[] = PostMedia::factory()->create([
            'post_id' => null,
            'user_id' => $user->id,
            'size_bytes' => 100_000,
        ])->id;
    }

    $action = new CreatePostAction;

    $action->execute($user, new PostData(
        scheduledFor: now()->addHour()->toDateTimeString(),
        bodyText: 'Too many images',
        targetAccountIds: [$account->id],
        mediaIds: $mediaIds,
    ));
})->throws(ValidationException::class, 'maximum of 4');
