<?php

use App\Enums\PermissionRole;
use App\Models\SocialAccount;
use App\Models\SocialAccountPermission;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('owner always has viewer and editor access', function () {
    $owner = User::factory()->create();
    $account = SocialAccount::factory()->create(['user_id' => $owner->id]);

    expect($account->userHasRole($owner, PermissionRole::Viewer))->toBeTrue();
    expect($account->userHasRole($owner, PermissionRole::Editor))->toBeTrue();
});

test('user with no permission has no access', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $account = SocialAccount::factory()->create(['user_id' => $owner->id]);

    expect($account->userHasRole($stranger, PermissionRole::Viewer))->toBeFalse();
    expect($account->userHasRole($stranger, PermissionRole::Editor))->toBeFalse();
});

test('viewer can view but not edit', function () {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $account = SocialAccount::factory()->create(['user_id' => $owner->id]);

    SocialAccountPermission::create([
        'social_account_id' => $account->id,
        'user_id' => $viewer->id,
        'role' => PermissionRole::Viewer,
    ]);

    expect($account->userHasRole($viewer, PermissionRole::Viewer))->toBeTrue();
    expect($account->userHasRole($viewer, PermissionRole::Editor))->toBeFalse();
});

test('editor can view and edit', function () {
    $owner = User::factory()->create();
    $editor = User::factory()->create();
    $account = SocialAccount::factory()->create(['user_id' => $owner->id]);

    SocialAccountPermission::create([
        'social_account_id' => $account->id,
        'user_id' => $editor->id,
        'role' => PermissionRole::Editor,
    ]);

    expect($account->userHasRole($editor, PermissionRole::Viewer))->toBeTrue();
    expect($account->userHasRole($editor, PermissionRole::Editor))->toBeTrue();
});
