<?php

use App\Livewire\SocialAccounts\ConnectX;
use App\Models\User;
use Livewire\Livewire;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('connect x page requires authentication', function () {
    $this->get(route('social-accounts.connect-x'))
        ->assertRedirect(route('login'));
});

test('connect x page shows oauth button when configured', function () {
    config([
        'services.x.client_id' => 'test-id',
        'services.x.client_secret' => 'test-secret',
    ]);

    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(ConnectX::class)
        ->assertSee('Connect with X')
        ->assertDontSee('Configuration Required');
});

test('connect x page shows configuration warning when not configured', function () {
    config([
        'services.x.client_id' => null,
        'services.x.client_secret' => null,
    ]);

    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(ConnectX::class)
        ->assertSee('Configuration Required')
        ->assertSee('X_CLIENT_ID')
        ->assertDontSee('Connect with X');
});
