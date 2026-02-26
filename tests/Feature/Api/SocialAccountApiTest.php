<?php

use App\Enums\PermissionRole;
use App\Models\SocialAccount;
use App\Models\SocialAccountPermission;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('unauthenticated request returns 401', function () {
    $this->getJson('/api/social-accounts')
        ->assertUnauthorized();
});

test('returns accounts owned by user', function () {
    $user = User::factory()->create();
    SocialAccount::factory()->x()->create(['user_id' => $user->id, 'display_name' => '@mine']);
    SocialAccount::factory()->bluesky()->create(['user_id' => $user->id, 'display_name' => '@also_mine']);

    $other = User::factory()->create();
    SocialAccount::factory()->x()->create(['user_id' => $other->id, 'display_name' => '@not_mine']);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/social-accounts');

    $response->assertOk();
    $response->assertJsonCount(2, 'data');
    $response->assertJsonFragment(['display_name' => '@mine']);
    $response->assertJsonFragment(['display_name' => '@also_mine']);
    $response->assertJsonMissing(['display_name' => '@not_mine']);
});

test('returns shared accounts alongside owned accounts', function () {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();

    $shared = SocialAccount::factory()->x()->create(['user_id' => $owner->id, 'display_name' => '@shared']);
    SocialAccountPermission::create([
        'social_account_id' => $shared->id,
        'user_id' => $viewer->id,
        'role' => PermissionRole::Viewer,
    ]);

    $owned = SocialAccount::factory()->bluesky()->create(['user_id' => $viewer->id, 'display_name' => '@owned']);

    $response = $this->actingAs($viewer, 'sanctum')
        ->getJson('/api/social-accounts');

    $response->assertOk();
    $response->assertJsonCount(2, 'data');
    $response->assertJsonFragment(['display_name' => '@shared']);
    $response->assertJsonFragment(['display_name' => '@owned']);
});

test('filters by provider', function () {
    $user = User::factory()->create();
    SocialAccount::factory()->x()->create(['user_id' => $user->id]);
    SocialAccount::factory()->bluesky()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/social-accounts?provider=x');

    $response->assertOk();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonFragment(['provider' => 'x']);
});

test('never exposes credentials', function () {
    $user = User::factory()->create();
    SocialAccount::factory()->x()->create([
        'user_id' => $user->id,
        'credentials_encrypted' => ['access_token' => 'super-secret'],
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/social-accounts');

    $response->assertOk();
    $response->assertJsonMissing(['access_token']);
    $response->assertJsonMissing(['credentials_encrypted']);
    $response->assertJsonMissing(['super-secret']);
});

test('rejects invalid provider filter', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/social-accounts?provider=mastodon');

    $response->assertUnprocessable();
});
