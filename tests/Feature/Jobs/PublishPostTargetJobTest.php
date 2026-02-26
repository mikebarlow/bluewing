<?php

use App\Enums\PostStatus;
use App\Enums\PostTargetStatus;
use App\Enums\ScopeType;
use App\Jobs\PublishPostTargetJob;
use App\Models\Post;
use App\Models\PostMedia;
use App\Models\PostTarget;
use App\Models\PostTargetMedia;
use App\Models\PostVariant;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\SocialProviders\Contracts\ProviderPublishResult;
use App\Services\SocialProviders\Contracts\SocialProviderClient;
use App\Services\SocialProviders\Contracts\ValidationResult;
use App\Services\SocialProviders\SocialProviderFactory;
use Illuminate\Support\Facades\Storage;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function createPostWithTarget(array $postAttrs = [], array $targetAttrs = []): array
{
    $user = User::factory()->create();
    $account = SocialAccount::factory()->x()->create(['user_id' => $user->id]);

    $post = Post::factory()->create(array_merge([
        'user_id' => $user->id,
        'status' => PostStatus::Queued,
    ], $postAttrs));

    PostVariant::factory()->create([
        'post_id' => $post->id,
        'scope_type' => ScopeType::Default,
        'body_text' => 'Test publish text',
    ]);

    $target = PostTarget::factory()->create(array_merge([
        'post_id' => $post->id,
        'social_account_id' => $account->id,
        'status' => PostTargetStatus::Queued,
    ], $targetAttrs));

    return [$post, $target, $account, $user];
}

function mockProviderFactory(bool $success = true, string $externalPostId = 'ext-123', string $error = 'API error', array $providerMediaIds = []): void
{
    $mock = Mockery::mock(SocialProviderClient::class);

    $mock->shouldReceive('validateCredentials')
        ->andReturn(ValidationResult::success());

    $result = $success
        ? ProviderPublishResult::success($externalPostId, providerMediaIds: $providerMediaIds)
        : ProviderPublishResult::failure($error);

    $mock->shouldReceive('publish')->andReturn($result);
    $mock->shouldReceive('publishText')->andReturn($result);

    $factory = Mockery::mock(SocialProviderFactory::class);
    $factory->shouldReceive('make')->andReturn($mock);

    app()->instance(SocialProviderFactory::class, $factory);
}

test('successful publish updates target and post status', function () {
    [$post, $target] = createPostWithTarget();
    mockProviderFactory(success: true);

    (new PublishPostTargetJob($target->id))->handle(app(SocialProviderFactory::class));

    $target->refresh();
    $post->refresh();

    expect($target->status)->toBe(PostTargetStatus::Sent);
    expect($target->sent_at)->not->toBeNull();
    expect($target->error_message)->toBeNull();

    expect($post->status)->toBe(PostStatus::Sent);
    expect($post->sent_at)->not->toBeNull();
});

test('failed publish updates target with error and marks post failed', function () {
    [$post, $target] = createPostWithTarget();
    mockProviderFactory(success: false, error: 'Rate limit exceeded');

    (new PublishPostTargetJob($target->id))->handle(app(SocialProviderFactory::class));

    $target->refresh();
    $post->refresh();

    expect($target->status)->toBe(PostTargetStatus::Failed);
    expect($target->error_message)->toBe('Rate limit exceeded');
    expect($target->sent_at)->toBeNull();

    expect($post->status)->toBe(PostStatus::Failed);
    expect($post->sent_at)->toBeNull();
});

test('post stays publishing when other targets are still queued', function () {
    $user = User::factory()->create();
    $xAccount = SocialAccount::factory()->x()->create(['user_id' => $user->id]);
    $bsAccount = SocialAccount::factory()->bluesky()->create(['user_id' => $user->id]);

    $post = Post::factory()->create([
        'user_id' => $user->id,
        'status' => PostStatus::Queued,
    ]);

    PostVariant::factory()->create([
        'post_id' => $post->id,
        'scope_type' => ScopeType::Default,
        'body_text' => 'Multi target post',
    ]);

    $xTarget = PostTarget::factory()->create([
        'post_id' => $post->id,
        'social_account_id' => $xAccount->id,
        'status' => PostTargetStatus::Queued,
    ]);

    PostTarget::factory()->create([
        'post_id' => $post->id,
        'social_account_id' => $bsAccount->id,
        'status' => PostTargetStatus::Queued,
    ]);

    mockProviderFactory(success: true);

    (new PublishPostTargetJob($xTarget->id))->handle(app(SocialProviderFactory::class));

    $post->refresh();

    // Post should still be publishing because the Bluesky target is still queued
    expect($post->status)->toBe(PostStatus::Publishing);
    expect($post->sent_at)->toBeNull();
});

test('post marked sent when all targets succeed', function () {
    $user = User::factory()->create();
    $xAccount = SocialAccount::factory()->x()->create(['user_id' => $user->id]);
    $bsAccount = SocialAccount::factory()->bluesky()->create(['user_id' => $user->id]);

    $post = Post::factory()->create([
        'user_id' => $user->id,
        'status' => PostStatus::Queued,
    ]);

    PostVariant::factory()->create([
        'post_id' => $post->id,
        'scope_type' => ScopeType::Default,
        'body_text' => 'Both targets post',
    ]);

    $xTarget = PostTarget::factory()->create([
        'post_id' => $post->id,
        'social_account_id' => $xAccount->id,
        'status' => PostTargetStatus::Queued,
    ]);

    $bsTarget = PostTarget::factory()->create([
        'post_id' => $post->id,
        'social_account_id' => $bsAccount->id,
        'status' => PostTargetStatus::Queued,
    ]);

    mockProviderFactory(success: true);

    $factory = app(SocialProviderFactory::class);

    (new PublishPostTargetJob($xTarget->id))->handle($factory);
    (new PublishPostTargetJob($bsTarget->id))->handle($factory);

    $post->refresh();

    expect($post->status)->toBe(PostStatus::Sent);
    expect($post->sent_at)->not->toBeNull();
});

test('post marked failed when one target fails and none are pending', function () {
    $user = User::factory()->create();
    $xAccount = SocialAccount::factory()->x()->create(['user_id' => $user->id]);
    $bsAccount = SocialAccount::factory()->bluesky()->create(['user_id' => $user->id]);

    $post = Post::factory()->create([
        'user_id' => $user->id,
        'status' => PostStatus::Queued,
    ]);

    PostVariant::factory()->create([
        'post_id' => $post->id,
        'scope_type' => ScopeType::Default,
        'body_text' => 'Mixed result post',
    ]);

    $xTarget = PostTarget::factory()->create([
        'post_id' => $post->id,
        'social_account_id' => $xAccount->id,
        'status' => PostTargetStatus::Queued,
    ]);

    $bsTarget = PostTarget::factory()->create([
        'post_id' => $post->id,
        'social_account_id' => $bsAccount->id,
        'status' => PostTargetStatus::Queued,
    ]);

    $successMock = Mockery::mock(SocialProviderClient::class);
    $successMock->shouldReceive('publish')
        ->andReturn(ProviderPublishResult::success('ok-1'));

    $successFactory = Mockery::mock(SocialProviderFactory::class);
    $successFactory->shouldReceive('make')->andReturn($successMock);

    app()->instance(SocialProviderFactory::class, $successFactory);

    (new PublishPostTargetJob($xTarget->id))->handle(app(SocialProviderFactory::class));

    $failMock = Mockery::mock(SocialProviderClient::class);
    $failMock->shouldReceive('publish')
        ->andReturn(ProviderPublishResult::failure('API down'));

    $failFactory = Mockery::mock(SocialProviderFactory::class);
    $failFactory->shouldReceive('make')->andReturn($failMock);

    app()->instance(SocialProviderFactory::class, $failFactory);

    (new PublishPostTargetJob($bsTarget->id))->handle(app(SocialProviderFactory::class));

    $post->refresh();

    expect($post->status)->toBe(PostStatus::Failed);
});

test('job fails gracefully when target has no text', function () {
    $user = User::factory()->create();
    $account = SocialAccount::factory()->x()->create(['user_id' => $user->id]);

    $post = Post::factory()->create([
        'user_id' => $user->id,
        'status' => PostStatus::Queued,
    ]);

    // No variants created - resolveTextForTarget will return null
    $target = PostTarget::factory()->create([
        'post_id' => $post->id,
        'social_account_id' => $account->id,
        'status' => PostTargetStatus::Queued,
    ]);

    mockProviderFactory(success: true);

    (new PublishPostTargetJob($target->id))->handle(app(SocialProviderFactory::class));

    $target->refresh();

    expect($target->status)->toBe(PostTargetStatus::Failed);
    expect($target->error_message)->toContain('No text content');
});

test('refreshed credentials are persisted to social account', function () {
    [$post, $target, $account] = createPostWithTarget();

    $refreshedCreds = [
        'access_token' => 'new-access-token',
        'refresh_token' => 'new-refresh-token',
        'expires_at' => now()->addHours(2)->toIso8601String(),
    ];

    $mock = Mockery::mock(SocialProviderClient::class);
    $mock->shouldReceive('publish')
        ->andReturn(ProviderPublishResult::success('ext-refreshed', $refreshedCreds));

    $factory = Mockery::mock(SocialProviderFactory::class);
    $factory->shouldReceive('make')->andReturn($mock);

    app()->instance(SocialProviderFactory::class, $factory);

    (new PublishPostTargetJob($target->id))->handle(app(SocialProviderFactory::class));

    $account->refresh();

    expect($account->credentials_encrypted['access_token'])->toBe('new-access-token');
    expect($account->credentials_encrypted['refresh_token'])->toBe('new-refresh-token');
});

test('uses variant precedence when publishing', function () {
    $user = User::factory()->create();
    $account = SocialAccount::factory()->x()->create(['user_id' => $user->id]);

    $post = Post::factory()->create([
        'user_id' => $user->id,
        'status' => PostStatus::Queued,
    ]);

    PostVariant::factory()->create([
        'post_id' => $post->id,
        'scope_type' => ScopeType::Default,
        'body_text' => 'Default text',
    ]);

    PostVariant::factory()->create([
        'post_id' => $post->id,
        'scope_type' => ScopeType::SocialAccount,
        'scope_value' => (string) $account->id,
        'body_text' => 'Account-specific override',
    ]);

    $target = PostTarget::factory()->create([
        'post_id' => $post->id,
        'social_account_id' => $account->id,
        'status' => PostTargetStatus::Queued,
    ]);

    $capturedText = null;

    $mock = Mockery::mock(SocialProviderClient::class);
    $mock->shouldReceive('publish')
        ->withArgs(function ($id, $creds, $text, $media) use (&$capturedText) {
            $capturedText = $text;

            return true;
        })
        ->andReturn(ProviderPublishResult::success('ext-ok'));

    $factory = Mockery::mock(SocialProviderFactory::class);
    $factory->shouldReceive('make')->andReturn($mock);

    app()->instance(SocialProviderFactory::class, $factory);

    (new PublishPostTargetJob($target->id))->handle(app(SocialProviderFactory::class));

    expect($capturedText)->toBe('Account-specific override');
});

test('publish passes media items to provider client', function () {
    Storage::fake('local');
    Storage::disk('local')->put('media/test-image.jpg', 'fake-image-contents');

    $user = User::factory()->create();
    $account = SocialAccount::factory()->x()->create(['user_id' => $user->id]);

    $post = Post::factory()->create([
        'user_id' => $user->id,
        'status' => PostStatus::Queued,
    ]);

    PostVariant::factory()->create([
        'post_id' => $post->id,
        'scope_type' => ScopeType::Default,
        'body_text' => 'Post with media',
    ]);

    PostMedia::factory()->create([
        'post_id' => $post->id,
        'user_id' => $user->id,
        'storage_disk' => 'local',
        'storage_path' => 'media/test-image.jpg',
        'alt_text' => 'Test alt',
    ]);

    $target = PostTarget::factory()->create([
        'post_id' => $post->id,
        'social_account_id' => $account->id,
        'status' => PostTargetStatus::Queued,
    ]);

    $capturedMedia = null;

    $mock = Mockery::mock(SocialProviderClient::class);
    $mock->shouldReceive('publish')
        ->withArgs(function ($id, $creds, $text, $media) use (&$capturedMedia) {
            $capturedMedia = $media;

            return true;
        })
        ->andReturn(ProviderPublishResult::success('ext-media'));

    $factory = Mockery::mock(SocialProviderFactory::class);
    $factory->shouldReceive('make')->andReturn($mock);

    app()->instance(SocialProviderFactory::class, $factory);

    (new PublishPostTargetJob($target->id))->handle(app(SocialProviderFactory::class));

    expect($capturedMedia)->toHaveCount(1);
    expect($capturedMedia[0]->contents)->toBe('fake-image-contents');
    expect($capturedMedia[0]->altText)->toBe('Test alt');
});

test('publish stores provider media ids in post_target_media', function () {
    Storage::fake('local');
    Storage::disk('local')->put('media/img.jpg', 'contents');

    $user = User::factory()->create();
    $account = SocialAccount::factory()->x()->create(['user_id' => $user->id]);

    $post = Post::factory()->create([
        'user_id' => $user->id,
        'status' => PostStatus::Queued,
    ]);

    PostVariant::factory()->create([
        'post_id' => $post->id,
        'scope_type' => ScopeType::Default,
        'body_text' => 'Media post',
    ]);

    $media = PostMedia::factory()->create([
        'post_id' => $post->id,
        'user_id' => $user->id,
        'storage_disk' => 'local',
        'storage_path' => 'media/img.jpg',
    ]);

    $target = PostTarget::factory()->create([
        'post_id' => $post->id,
        'social_account_id' => $account->id,
        'status' => PostTargetStatus::Queued,
    ]);

    mockProviderFactory(success: true, externalPostId: 'ext-ok', providerMediaIds: [$media->id => 'x-media-123']);

    (new PublishPostTargetJob($target->id))->handle(app(SocialProviderFactory::class));

    $targetMedia = PostTargetMedia::where('post_target_id', $target->id)->get();

    expect($targetMedia)->toHaveCount(1);
    expect($targetMedia->first()->post_media_id)->toBe($media->id);
    expect($targetMedia->first()->provider_media_id)->toBe('x-media-123');
});

test('publish without media sends empty media array to provider', function () {
    [$post, $target] = createPostWithTarget();

    $capturedMedia = null;

    $mock = Mockery::mock(SocialProviderClient::class);
    $mock->shouldReceive('publish')
        ->withArgs(function ($id, $creds, $text, $media) use (&$capturedMedia) {
            $capturedMedia = $media;

            return true;
        })
        ->andReturn(ProviderPublishResult::success('ext-nomedia'));

    $factory = Mockery::mock(SocialProviderFactory::class);
    $factory->shouldReceive('make')->andReturn($mock);

    app()->instance(SocialProviderFactory::class, $factory);

    (new PublishPostTargetJob($target->id))->handle(app(SocialProviderFactory::class));

    expect($capturedMedia)->toBe([]);
    expect(PostTargetMedia::count())->toBe(0);
});

test('publish with media failure marks target as failed', function () {
    Storage::fake('local');
    Storage::disk('local')->put('media/img.jpg', 'contents');

    $user = User::factory()->create();
    $account = SocialAccount::factory()->x()->create(['user_id' => $user->id]);

    $post = Post::factory()->create([
        'user_id' => $user->id,
        'status' => PostStatus::Queued,
    ]);

    PostVariant::factory()->create([
        'post_id' => $post->id,
        'scope_type' => ScopeType::Default,
        'body_text' => 'Will fail',
    ]);

    PostMedia::factory()->create([
        'post_id' => $post->id,
        'user_id' => $user->id,
        'storage_disk' => 'local',
        'storage_path' => 'media/img.jpg',
    ]);

    $target = PostTarget::factory()->create([
        'post_id' => $post->id,
        'social_account_id' => $account->id,
        'status' => PostTargetStatus::Queued,
    ]);

    mockProviderFactory(success: false, error: 'Media upload failed');

    (new PublishPostTargetJob($target->id))->handle(app(SocialProviderFactory::class));

    $target->refresh();

    expect($target->status)->toBe(PostTargetStatus::Failed);
    expect($target->error_message)->toBe('Media upload failed');
    expect(PostTargetMedia::count())->toBe(0);
});
