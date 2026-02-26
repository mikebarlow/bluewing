<?php

use App\Enums\PermissionRole;
use App\Enums\PostStatus;
use App\Enums\PostTargetStatus;
use App\Enums\ScopeType;
use App\Models\Post;
use App\Models\SocialAccount;
use App\Models\SocialAccountPermission;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('unauthenticated request returns 401', function () {
    $this->postJson('/api/posts', [])
        ->assertUnauthorized();
});

test('creates a scheduled post with 201 response', function () {
    $user = User::factory()->create();
    $account = SocialAccount::factory()->x()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/posts', [
            'scheduled_for' => now()->addHour()->toDateTimeString(),
            'body_text' => 'Hello from the API',
            'targets' => [$account->id],
        ]);

    $response->assertCreated();
    $response->assertJsonPath('data.status', 'scheduled');
    $response->assertJsonPath('data.user_id', $user->id);
    $response->assertJsonCount(1, 'data.targets');
    $response->assertJsonCount(1, 'data.variants');
    $response->assertJsonFragment(['body_text' => 'Hello from the API']);

    $post = Post::first();
    expect($post->status)->toBe(PostStatus::Scheduled);
    expect($post->variants)->toHaveCount(1);
    expect($post->targets)->toHaveCount(1);
    expect($post->targets->first()->status)->toBe(PostTargetStatus::Pending);
});

test('creates post with provider and account overrides', function () {
    $user = User::factory()->create();
    $xAccount = SocialAccount::factory()->x()->create(['user_id' => $user->id]);
    $bsAccount = SocialAccount::factory()->bluesky()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/posts', [
            'scheduled_for' => now()->addHour()->toDateTimeString(),
            'body_text' => 'Default text',
            'targets' => [$xAccount->id, $bsAccount->id],
            'provider_overrides' => [
                'x' => 'X-specific text',
            ],
            'account_overrides' => [
                $bsAccount->id => 'Bluesky account override',
            ],
        ]);

    $response->assertCreated();

    $post = Post::first();
    expect($post->variants)->toHaveCount(3);
    expect($post->targets)->toHaveCount(2);

    $default = $post->variants->where('scope_type', ScopeType::Default)->first();
    expect($default->body_text)->toBe('Default text');

    $providerVariant = $post->variants->where('scope_type', ScopeType::Provider)->first();
    expect($providerVariant->scope_value)->toBe('x');

    $accountVariant = $post->variants->where('scope_type', ScopeType::SocialAccount)->first();
    expect($accountVariant->scope_value)->toBe((string) $bsAccount->id);
});

test('rejects post without body text', function () {
    $user = User::factory()->create();
    $account = SocialAccount::factory()->x()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/posts', [
            'scheduled_for' => now()->addHour()->toDateTimeString(),
            'targets' => [$account->id],
        ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['body_text']);
});

test('rejects post without targets', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/posts', [
            'scheduled_for' => now()->addHour()->toDateTimeString(),
            'body_text' => 'No targets',
        ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['targets']);
});

test('rejects post with past scheduled_for', function () {
    $user = User::factory()->create();
    $account = SocialAccount::factory()->x()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/posts', [
            'scheduled_for' => now()->subHour()->toDateTimeString(),
            'body_text' => 'Too late',
            'targets' => [$account->id],
        ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['scheduled_for']);
});

test('rejects post when user lacks editor access to target', function () {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $account = SocialAccount::factory()->x()->create(['user_id' => $owner->id]);

    SocialAccountPermission::create([
        'social_account_id' => $account->id,
        'user_id' => $viewer->id,
        'role' => PermissionRole::Viewer,
    ]);

    $response = $this->actingAs($viewer, 'sanctum')
        ->postJson('/api/posts', [
            'scheduled_for' => now()->addHour()->toDateTimeString(),
            'body_text' => 'Should fail',
            'targets' => [$account->id],
        ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['targets']);
    expect(Post::count())->toBe(0);
});

test('editor on shared account can create post', function () {
    $owner = User::factory()->create();
    $editor = User::factory()->create();
    $account = SocialAccount::factory()->x()->create(['user_id' => $owner->id]);

    SocialAccountPermission::create([
        'social_account_id' => $account->id,
        'user_id' => $editor->id,
        'role' => PermissionRole::Editor,
    ]);

    $response = $this->actingAs($editor, 'sanctum')
        ->postJson('/api/posts', [
            'scheduled_for' => now()->addHour()->toDateTimeString(),
            'body_text' => 'Shared account post',
            'targets' => [$account->id],
        ]);

    $response->assertCreated();
    expect(Post::count())->toBe(1);
    expect(Post::first()->user_id)->toBe($editor->id);
});

test('rejects nonexistent target account', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/posts', [
            'scheduled_for' => now()->addHour()->toDateTimeString(),
            'body_text' => 'Bad target',
            'targets' => [99999],
        ]);

    $response->assertUnprocessable();
});

test('rejects empty targets array', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/posts', [
            'scheduled_for' => now()->addHour()->toDateTimeString(),
            'body_text' => 'No targets',
            'targets' => [],
        ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['targets']);
});
