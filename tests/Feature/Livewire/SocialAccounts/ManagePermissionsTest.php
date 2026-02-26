<?php

use App\Enums\PermissionRole;
use App\Livewire\SocialAccounts\ManagePermissions;
use App\Models\SocialAccount;
use App\Models\SocialAccountPermission;
use App\Models\User;
use Livewire\Livewire;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('non-owner cannot access permissions page', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $account = SocialAccount::factory()->create(['user_id' => $owner->id]);

    Livewire::actingAs($other)
        ->test(ManagePermissions::class, ['account' => $account])
        ->assertForbidden();
});

test('owner can grant viewer access', function () {
    $owner = User::factory()->create();
    $grantee = User::factory()->create();
    $account = SocialAccount::factory()->create(['user_id' => $owner->id]);

    Livewire::actingAs($owner)
        ->test(ManagePermissions::class, ['account' => $account])
        ->set('email', $grantee->email)
        ->set('role', 'viewer')
        ->call('grantAccess');

    $permission = SocialAccountPermission::where('social_account_id', $account->id)
        ->where('user_id', $grantee->id)
        ->first();

    expect($permission)->not->toBeNull();
    expect($permission->role)->toBe(PermissionRole::Viewer);
});

test('owner can grant editor access', function () {
    $owner = User::factory()->create();
    $grantee = User::factory()->create();
    $account = SocialAccount::factory()->create(['user_id' => $owner->id]);

    Livewire::actingAs($owner)
        ->test(ManagePermissions::class, ['account' => $account])
        ->set('email', $grantee->email)
        ->set('role', 'editor')
        ->call('grantAccess');

    $permission = SocialAccountPermission::where('social_account_id', $account->id)
        ->where('user_id', $grantee->id)
        ->first();

    expect($permission->role)->toBe(PermissionRole::Editor);
});

test('granting to nonexistent email shows error', function () {
    $owner = User::factory()->create();
    $account = SocialAccount::factory()->create(['user_id' => $owner->id]);

    Livewire::actingAs($owner)
        ->test(ManagePermissions::class, ['account' => $account])
        ->set('email', 'nobody@example.com')
        ->set('role', 'viewer')
        ->call('grantAccess')
        ->assertHasErrors('email');
});

test('owner cannot grant permissions to themselves', function () {
    $owner = User::factory()->create();
    $account = SocialAccount::factory()->create(['user_id' => $owner->id]);

    Livewire::actingAs($owner)
        ->test(ManagePermissions::class, ['account' => $account])
        ->set('email', $owner->email)
        ->set('role', 'viewer')
        ->call('grantAccess')
        ->assertHasErrors('email');
});

test('owner can revoke access', function () {
    $owner = User::factory()->create();
    $grantee = User::factory()->create();
    $account = SocialAccount::factory()->create(['user_id' => $owner->id]);

    $permission = SocialAccountPermission::create([
        'social_account_id' => $account->id,
        'user_id' => $grantee->id,
        'role' => PermissionRole::Viewer,
    ]);

    Livewire::actingAs($owner)
        ->test(ManagePermissions::class, ['account' => $account])
        ->call('revokeAccess', $permission->id);

    expect(SocialAccountPermission::find($permission->id))->toBeNull();
});

test('owner can change role', function () {
    $owner = User::factory()->create();
    $grantee = User::factory()->create();
    $account = SocialAccount::factory()->create(['user_id' => $owner->id]);

    $permission = SocialAccountPermission::create([
        'social_account_id' => $account->id,
        'user_id' => $grantee->id,
        'role' => PermissionRole::Viewer,
    ]);

    Livewire::actingAs($owner)
        ->test(ManagePermissions::class, ['account' => $account])
        ->call('updateRole', $permission->id, 'editor');

    expect($permission->fresh()->role)->toBe(PermissionRole::Editor);
});
