<?php

use App\Livewire\SocialAccounts\ConnectBluesky;
use App\Models\SocialAccount;
use App\Models\User;
use Livewire\Livewire;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('connect bluesky page requires authentication', function () {
    $this->get(route('social-accounts.connect-bluesky'))
        ->assertRedirect(route('login'));
});

test('user can connect a bluesky account', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(ConnectBluesky::class)
        ->set('display_name', 'My Bluesky')
        ->set('handle', 'user.bsky.social')
        ->set('app_password', 'xxxx-xxxx-xxxx-xxxx')
        ->call('save')
        ->assertRedirect(route('social-accounts.index'));

    $account = SocialAccount::where('user_id', $user->id)->first();

    expect($account)->not->toBeNull();
    expect($account->provider->value)->toBe('bluesky');
    expect($account->display_name)->toBe('My Bluesky');
    expect($account->credentials_encrypted)->toHaveKey('handle');
    expect($account->credentials_encrypted)->toHaveKey('app_password');
});

test('connect bluesky requires handle and app password', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(ConnectBluesky::class)
        ->set('display_name', '')
        ->set('handle', '')
        ->set('app_password', '')
        ->call('save')
        ->assertHasErrors(['display_name', 'handle', 'app_password']);
});
