<?php

use App\Livewire\SocialAccounts\Index;
use App\Models\SocialAccount;
use App\Models\User;
use Livewire\Livewire;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('social accounts page requires authentication', function () {
    $this->get(route('social-accounts.index'))
        ->assertRedirect(route('login'));
});

test('authenticated user can see social accounts page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('social-accounts.index'))
        ->assertOk();
});

test('user sees their own accounts', function () {
    $user = User::factory()->create();
    $account = SocialAccount::factory()->x()->create([
        'user_id' => $user->id,
        'display_name' => '@testhandle',
    ]);

    Livewire::actingAs($user)
        ->test(Index::class)
        ->assertSee('@testhandle');
});

test('owner can disconnect their account', function () {
    $user = User::factory()->create();
    $account = SocialAccount::factory()->create(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test(Index::class)
        ->call('deleteSocialAccount', $account->id);

    expect(SocialAccount::find($account->id))->toBeNull();
});

test('non-owner cannot disconnect account', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $account = SocialAccount::factory()->create(['user_id' => $owner->id]);

    Livewire::actingAs($other)
        ->test(Index::class)
        ->call('deleteSocialAccount', $account->id)
        ->assertForbidden();
});
