<?php

use App\Enums\PermissionRole;
use App\Models\PostMedia;
use App\Models\SocialAccount;
use App\Models\SocialAccountPermission;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('creates post with media_ids attaches media to post', function () {
    $user = User::factory()->create();
    $account = SocialAccount::factory()->x()->create(['user_id' => $user->id]);

    $media1 = PostMedia::factory()->create(['user_id' => $user->id, 'post_id' => null]);
    $media2 = PostMedia::factory()->create(['user_id' => $user->id, 'post_id' => null]);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/posts', [
            'scheduled_for' => now()->addHour()->toDateTimeString(),
            'body_text' => 'Post with images',
            'targets' => [$account->id],
            'media_ids' => [$media1->id, $media2->id],
        ]);

    $response->assertCreated();
    $response->assertJsonCount(2, 'data.media');

    $postId = $response->json('data.id');
    expect($media1->fresh()->post_id)->toBe($postId);
    expect($media2->fresh()->post_id)->toBe($postId);
});

test('creates post with media_ids and alt_texts', function () {
    $user = User::factory()->create();
    $account = SocialAccount::factory()->bluesky()->create(['user_id' => $user->id]);

    $media = PostMedia::factory()->create(['user_id' => $user->id, 'post_id' => null]);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/posts', [
            'scheduled_for' => now()->addHour()->toDateTimeString(),
            'body_text' => 'Post with alt text',
            'targets' => [$account->id],
            'media_ids' => [$media->id],
            'alt_texts' => [$media->id => 'A sunset over the ocean'],
        ]);

    $response->assertCreated();

    expect($media->fresh()->alt_text)->toBe('A sunset over the ocean');
    $response->assertJsonPath('data.media.0.alt_text', 'A sunset over the ocean');
});

test('creates post without media when media_ids not provided', function () {
    $user = User::factory()->create();
    $account = SocialAccount::factory()->x()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/posts', [
            'scheduled_for' => now()->addHour()->toDateTimeString(),
            'body_text' => 'No media post',
            'targets' => [$account->id],
        ]);

    $response->assertCreated();
    $response->assertJsonCount(0, 'data.media');
});

test('rejects post with media belonging to another user', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $account = SocialAccount::factory()->x()->create(['user_id' => $user->id]);

    $otherMedia = PostMedia::factory()->create(['user_id' => $other->id, 'post_id' => null]);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/posts', [
            'scheduled_for' => now()->addHour()->toDateTimeString(),
            'body_text' => 'Stolen media',
            'targets' => [$account->id],
            'media_ids' => [$otherMedia->id],
        ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['media']);
});

test('rejects post with nonexistent media_id', function () {
    $user = User::factory()->create();
    $account = SocialAccount::factory()->x()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/posts', [
            'scheduled_for' => now()->addHour()->toDateTimeString(),
            'body_text' => 'Ghost media',
            'targets' => [$account->id],
            'media_ids' => [99999],
        ]);

    $response->assertUnprocessable();
});

test('rejects post when media exceeds provider limit', function () {
    $user = User::factory()->create();
    $account = SocialAccount::factory()->bluesky()->create(['user_id' => $user->id]);

    $media = PostMedia::factory()->create([
        'user_id' => $user->id,
        'post_id' => null,
        'size_bytes' => 2_000_000,
        'mime_type' => 'image/jpeg',
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/posts', [
            'scheduled_for' => now()->addHour()->toDateTimeString(),
            'body_text' => 'Too big for Bluesky',
            'targets' => [$account->id],
            'media_ids' => [$media->id],
        ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['media']);
});

test('rejects mixing images and video', function () {
    $user = User::factory()->create();
    $account = SocialAccount::factory()->x()->create(['user_id' => $user->id]);

    $image = PostMedia::factory()->create(['user_id' => $user->id, 'post_id' => null]);
    $video = PostMedia::factory()->video()->create([
        'user_id' => $user->id,
        'post_id' => null,
        'size_bytes' => 1000,
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/posts', [
            'scheduled_for' => now()->addHour()->toDateTimeString(),
            'body_text' => 'Mixed media',
            'targets' => [$account->id],
            'media_ids' => [$image->id, $video->id],
        ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['media']);
});

test('rejects more than max images per post', function () {
    $user = User::factory()->create();
    $account = SocialAccount::factory()->x()->create(['user_id' => $user->id]);

    $mediaIds = [];
    for ($i = 0; $i < 5; $i++) {
        $mediaIds[] = PostMedia::factory()->create([
            'user_id' => $user->id,
            'post_id' => null,
        ])->id;
    }

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/posts', [
            'scheduled_for' => now()->addHour()->toDateTimeString(),
            'body_text' => 'Too many images',
            'targets' => [$account->id],
            'media_ids' => $mediaIds,
        ]);

    $response->assertUnprocessable();
});

test('cross-posting enforces strictest media limit via API', function () {
    $user = User::factory()->create();
    $xAccount = SocialAccount::factory()->x()->create(['user_id' => $user->id]);
    $bsAccount = SocialAccount::factory()->bluesky()->create(['user_id' => $user->id]);

    $media = PostMedia::factory()->create([
        'user_id' => $user->id,
        'post_id' => null,
        'size_bytes' => 3_000_000,
        'mime_type' => 'image/jpeg',
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/posts', [
            'scheduled_for' => now()->addHour()->toDateTimeString(),
            'body_text' => 'Cross-post',
            'targets' => [$xAccount->id, $bsAccount->id],
            'media_ids' => [$media->id],
        ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['media']);
});

test('viewer cannot create post with media via API', function () {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $account = SocialAccount::factory()->x()->create(['user_id' => $owner->id]);

    SocialAccountPermission::create([
        'social_account_id' => $account->id,
        'user_id' => $viewer->id,
        'role' => PermissionRole::Viewer,
    ]);

    $media = PostMedia::factory()->create(['user_id' => $viewer->id, 'post_id' => null]);

    $response = $this->actingAs($viewer, 'sanctum')
        ->postJson('/api/posts', [
            'scheduled_for' => now()->addHour()->toDateTimeString(),
            'body_text' => 'Should fail',
            'targets' => [$account->id],
            'media_ids' => [$media->id],
        ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['targets']);
});
