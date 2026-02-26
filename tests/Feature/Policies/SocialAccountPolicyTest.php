<?php

use App\Enums\PermissionRole;
use App\Models\SocialAccount;
use App\Models\SocialAccountPermission;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('owner can view their own account', function () {
    $owner = User::factory()->create();
    $account = SocialAccount::factory()->create(['user_id' => $owner->id]);

    expect($owner->can('view', $account))->toBeTrue();
});

test('viewer can view shared account', function () {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $account = SocialAccount::factory()->create(['user_id' => $owner->id]);

    SocialAccountPermission::create([
        'social_account_id' => $account->id,
        'user_id' => $viewer->id,
        'role' => PermissionRole::Viewer,
    ]);

    expect($viewer->can('view', $account))->toBeTrue();
});

test('stranger cannot view account', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $account = SocialAccount::factory()->create(['user_id' => $owner->id]);

    expect($stranger->can('view', $account))->toBeFalse();
});

test('owner can update and delete their account', function () {
    $owner = User::factory()->create();
    $account = SocialAccount::factory()->create(['user_id' => $owner->id]);

    expect($owner->can('update', $account))->toBeTrue();
    expect($owner->can('delete', $account))->toBeTrue();
});

test('editor cannot update or delete account', function () {
    $owner = User::factory()->create();
    $editor = User::factory()->create();
    $account = SocialAccount::factory()->create(['user_id' => $owner->id]);

    SocialAccountPermission::create([
        'social_account_id' => $account->id,
        'user_id' => $editor->id,
        'role' => PermissionRole::Editor,
    ]);

    expect($editor->can('update', $account))->toBeFalse();
    expect($editor->can('delete', $account))->toBeFalse();
});

test('owner can manage permissions', function () {
    $owner = User::factory()->create();
    $account = SocialAccount::factory()->create(['user_id' => $owner->id]);

    expect($owner->can('managePermissions', $account))->toBeTrue();
});

test('non-owner cannot manage permissions', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $account = SocialAccount::factory()->create(['user_id' => $owner->id]);

    expect($other->can('managePermissions', $account))->toBeFalse();
});

test('editor can publish to account', function () {
    $owner = User::factory()->create();
    $editor = User::factory()->create();
    $account = SocialAccount::factory()->create(['user_id' => $owner->id]);

    SocialAccountPermission::create([
        'social_account_id' => $account->id,
        'user_id' => $editor->id,
        'role' => PermissionRole::Editor,
    ]);

    expect($editor->can('publish', $account))->toBeTrue();
});

test('viewer cannot publish to account', function () {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $account = SocialAccount::factory()->create(['user_id' => $owner->id]);

    SocialAccountPermission::create([
        'social_account_id' => $account->id,
        'user_id' => $viewer->id,
        'role' => PermissionRole::Viewer,
    ]);

    expect($viewer->can('publish', $account))->toBeFalse();
});

test('owner can publish to their account', function () {
    $owner = User::factory()->create();
    $account = SocialAccount::factory()->create(['user_id' => $owner->id]);

    expect($owner->can('publish', $account))->toBeTrue();
});
