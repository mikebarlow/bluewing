<?php

use App\Enums\PermissionRole;
use App\Enums\PostStatus;
use App\Enums\ScopeType;
use App\Models\Post;
use App\Models\PostTarget;
use App\Models\PostVariant;
use App\Models\SocialAccount;
use App\Models\SocialAccountPermission;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('unauthenticated request returns 401', function () {
    $this->getJson('/api/posts')
        ->assertUnauthorized();
});

test('returns paginated posts owned by user', function () {
    $user = User::factory()->create();
    Post::factory()->count(3)->create(['user_id' => $user->id]);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/posts');

    $response->assertOk();
    $response->assertJsonCount(3, 'data');
    $response->assertJsonStructure([
        'data' => [['id', 'user_id', 'scheduled_for', 'status', 'sent_at', 'created_at', 'updated_at', 'targets']],
        'links',
        'meta',
    ]);
});

test('does not return posts for inaccessible accounts', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $account = SocialAccount::factory()->x()->create(['user_id' => $owner->id]);

    $post = Post::factory()->create(['user_id' => $owner->id]);
    PostTarget::factory()->create(['post_id' => $post->id, 'social_account_id' => $account->id]);

    $response = $this->actingAs($stranger, 'sanctum')
        ->getJson('/api/posts');

    $response->assertOk();
    $response->assertJsonCount(0, 'data');
});

test('returns posts targeting shared accounts', function () {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $account = SocialAccount::factory()->x()->create(['user_id' => $owner->id]);

    SocialAccountPermission::create([
        'social_account_id' => $account->id,
        'user_id' => $viewer->id,
        'role' => PermissionRole::Viewer,
    ]);

    $post = Post::factory()->create(['user_id' => $owner->id]);
    PostTarget::factory()->create(['post_id' => $post->id, 'social_account_id' => $account->id]);

    $response = $this->actingAs($viewer, 'sanctum')
        ->getJson('/api/posts');

    $response->assertOk();
    $response->assertJsonCount(1, 'data');
});

test('filters by status', function () {
    $user = User::factory()->create();
    Post::factory()->create(['user_id' => $user->id, 'status' => PostStatus::Draft]);
    Post::factory()->create(['user_id' => $user->id, 'status' => PostStatus::Scheduled]);
    Post::factory()->create(['user_id' => $user->id, 'status' => PostStatus::Sent]);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/posts?status=scheduled');

    $response->assertOk();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonFragment(['status' => 'scheduled']);
});

test('filters by provider', function () {
    $user = User::factory()->create();
    $xAccount = SocialAccount::factory()->x()->create(['user_id' => $user->id]);
    $bsAccount = SocialAccount::factory()->bluesky()->create(['user_id' => $user->id]);

    $xPost = Post::factory()->create(['user_id' => $user->id]);
    PostTarget::factory()->create(['post_id' => $xPost->id, 'social_account_id' => $xAccount->id]);

    $bsPost = Post::factory()->create(['user_id' => $user->id]);
    PostTarget::factory()->create(['post_id' => $bsPost->id, 'social_account_id' => $bsAccount->id]);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/posts?provider=bluesky');

    $response->assertOk();
    $response->assertJsonCount(1, 'data');
});

test('filters by date range', function () {
    $user = User::factory()->create();
    Post::factory()->create(['user_id' => $user->id, 'scheduled_for' => now()->subDays(5)]);
    Post::factory()->create(['user_id' => $user->id, 'scheduled_for' => now()->addDay()]);
    Post::factory()->create(['user_id' => $user->id, 'scheduled_for' => now()->addDays(10)]);

    $from = now()->toDateString();
    $to = now()->addDays(3)->toDateString();

    $response = $this->actingAs($user, 'sanctum')
        ->getJson("/api/posts?from={$from}&to={$to}");

    $response->assertOk();
    $response->assertJsonCount(1, 'data');
});

test('respects per_page parameter', function () {
    $user = User::factory()->create();
    Post::factory()->count(10)->create(['user_id' => $user->id]);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/posts?per_page=3');

    $response->assertOk();
    $response->assertJsonCount(3, 'data');
    $response->assertJsonPath('meta.per_page', 3);
});

test('caps per_page at 100', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/posts?per_page=999');

    $response->assertUnprocessable();
});

test('excludes variants by default', function () {
    $user = User::factory()->create();
    $post = Post::factory()->create(['user_id' => $user->id]);
    PostVariant::factory()->create(['post_id' => $post->id, 'scope_type' => ScopeType::Default, 'body_text' => 'Hello']);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/posts');

    $response->assertOk();
    $response->assertJsonMissing(['variants']);
});

test('includes variants when requested', function () {
    $user = User::factory()->create();
    $post = Post::factory()->create(['user_id' => $user->id]);
    PostVariant::factory()->create(['post_id' => $post->id, 'scope_type' => ScopeType::Default, 'body_text' => 'Hello']);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/posts?include=variants');

    $response->assertOk();
    $response->assertJsonFragment(['body_text' => 'Hello']);
});

test('validates from before to', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/posts?from=2026-03-01&to=2026-02-01');

    $response->assertUnprocessable();
});
