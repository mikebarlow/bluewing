<?php

use App\Domain\SocialAccounts\GetAccessibleAccountsQuery;
use App\Enums\PermissionRole;
use App\Models\SocialAccount;
use App\Models\SocialAccountPermission;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('returns accounts owned by user', function () {
    $user = User::factory()->create();
    SocialAccount::factory()->x()->create(['user_id' => $user->id]);
    SocialAccount::factory()->bluesky()->create(['user_id' => $user->id]);

    $accounts = (new GetAccessibleAccountsQuery($user))->get();

    expect($accounts)->toHaveCount(2);
});

test('returns accounts shared with user', function () {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $account = SocialAccount::factory()->x()->create(['user_id' => $owner->id]);

    SocialAccountPermission::create([
        'social_account_id' => $account->id,
        'user_id' => $viewer->id,
        'role' => PermissionRole::Viewer,
    ]);

    $accounts = (new GetAccessibleAccountsQuery($viewer))->get();

    expect($accounts)->toHaveCount(1);
    expect($accounts->first()->id)->toBe($account->id);
});

test('does not return accounts user has no access to', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    SocialAccount::factory()->x()->create(['user_id' => $owner->id]);

    $accounts = (new GetAccessibleAccountsQuery($stranger))->get();

    expect($accounts)->toHaveCount(0);
});

test('filters by provider', function () {
    $user = User::factory()->create();
    SocialAccount::factory()->x()->create(['user_id' => $user->id]);
    SocialAccount::factory()->bluesky()->create(['user_id' => $user->id]);

    $accounts = (new GetAccessibleAccountsQuery($user))
        ->provider('x')
        ->get();

    expect($accounts)->toHaveCount(1);
    expect($accounts->first()->provider->value)->toBe('x');
});

test('editable returns only accounts user can edit', function () {
    $owner = User::factory()->create();
    $user = User::factory()->create();

    $editableAccount = SocialAccount::factory()->x()->create(['user_id' => $owner->id]);
    $viewableAccount = SocialAccount::factory()->bluesky()->create(['user_id' => $owner->id]);

    SocialAccountPermission::create([
        'social_account_id' => $editableAccount->id,
        'user_id' => $user->id,
        'role' => PermissionRole::Editor,
    ]);

    SocialAccountPermission::create([
        'social_account_id' => $viewableAccount->id,
        'user_id' => $user->id,
        'role' => PermissionRole::Viewer,
    ]);

    $editable = (new GetAccessibleAccountsQuery($user))->editable();

    expect($editable)->toHaveCount(1);
    expect($editable->first()->id)->toBe($editableAccount->id);
});

test('owner accounts are always editable', function () {
    $user = User::factory()->create();
    SocialAccount::factory()->x()->create(['user_id' => $user->id]);
    SocialAccount::factory()->bluesky()->create(['user_id' => $user->id]);

    $editable = (new GetAccessibleAccountsQuery($user))->editable();

    expect($editable)->toHaveCount(2);
});

test('empty provider filter returns all accessible', function () {
    $user = User::factory()->create();
    SocialAccount::factory()->x()->create(['user_id' => $user->id]);
    SocialAccount::factory()->bluesky()->create(['user_id' => $user->id]);

    $accounts = (new GetAccessibleAccountsQuery($user))
        ->provider('')
        ->get();

    expect($accounts)->toHaveCount(2);
});
