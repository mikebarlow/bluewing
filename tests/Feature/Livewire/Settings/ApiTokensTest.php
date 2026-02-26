<?php

use App\Livewire\Settings\ApiTokens;
use App\Models\User;
use Livewire\Livewire;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('api tokens page requires authentication', function () {
    $this->get(route('api-tokens.index'))
        ->assertRedirect(route('login'));
});

test('authenticated user can view api tokens page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('api-tokens.index'))
        ->assertOk()
        ->assertSeeLivewire(ApiTokens::class);
});

test('user can create an api token and sees the raw token once', function () {
    $user = User::factory()->create();

    $component = Livewire::actingAs($user)
        ->test(ApiTokens::class)
        ->set('tokenName', 'My CI Token')
        ->call('createToken');

    $component->assertSet('tokenName', '');

    $plainTextToken = $component->get('plainTextToken');
    expect($plainTextToken)->not->toBeNull();
    expect($plainTextToken)->toContain('|');

    $component->assertSee('Copy this token now');
    $component->assertSee('You will not be able to see it again');

    expect($user->tokens()->count())->toBe(1);
    $token = $user->tokens()->first();
    expect($token->name)->toBe('My CI Token');
});

test('token prefix is stored as first 5 characters of raw token', function () {
    $user = User::factory()->create();

    $component = Livewire::actingAs($user)
        ->test(ApiTokens::class)
        ->set('tokenName', 'Prefix Test')
        ->call('createToken');

    $plainTextToken = $component->get('plainTextToken');
    [, $rawToken] = explode('|', $plainTextToken, 2);

    $token = $user->tokens()->first();
    expect($token->token_prefix)->toBe(substr($rawToken, 0, 5));
    expect(strlen($token->token_prefix))->toBe(5);
});

test('after dismissing, raw token is no longer visible', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(ApiTokens::class)
        ->set('tokenName', 'Dismiss Test')
        ->call('createToken')
        ->assertSet('plainTextToken', fn ($v) => $v !== null)
        ->call('dismissToken')
        ->assertSet('plainTextToken', null)
        ->assertSet('highlightTokenId', null)
        ->assertDontSee('Copy this token now');
});

test('token list shows masked preview with prefix', function () {
    $user = User::factory()->create();

    $component = Livewire::actingAs($user)
        ->test(ApiTokens::class)
        ->set('tokenName', 'List Test')
        ->call('createToken')
        ->call('dismissToken');

    $token = $user->tokens()->first();
    $component->assertSee($token->token_prefix);
    $component->assertSee('•');
});

test('raw token is never shown in list view after creation', function () {
    $user = User::factory()->create();

    $component = Livewire::actingAs($user)
        ->test(ApiTokens::class)
        ->set('tokenName', 'Never Show')
        ->call('createToken');

    $plainTextToken = $component->get('plainTextToken');
    $component->call('dismissToken');

    $component->assertDontSee($plainTextToken);
});

test('user can create multiple tokens', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(ApiTokens::class)
        ->set('tokenName', 'Token One')
        ->call('createToken')
        ->call('dismissToken')
        ->set('tokenName', 'Token Two')
        ->call('createToken')
        ->call('dismissToken');

    expect($user->tokens()->count())->toBe(2);
    expect($user->tokens()->pluck('name')->toArray())
        ->toContain('Token One', 'Token Two');
});

test('token name is required', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(ApiTokens::class)
        ->set('tokenName', '')
        ->call('createToken')
        ->assertHasErrors(['tokenName' => 'required']);

    expect($user->tokens()->count())->toBe(0);
});

test('user can roll a token and sees new raw token once', function () {
    $user = User::factory()->create();

    $component = Livewire::actingAs($user)
        ->test(ApiTokens::class)
        ->set('tokenName', 'Roll Test')
        ->call('createToken');

    $originalPlainText = $component->get('plainTextToken');
    $token = $user->tokens()->first();
    $originalHash = $token->token;

    $component->call('dismissToken')
        ->call('rollToken', $token->id);

    $newPlainText = $component->get('plainTextToken');

    expect($newPlainText)->not->toBeNull();
    expect($newPlainText)->not->toBe($originalPlainText);
    expect($newPlainText)->toStartWith($token->id.'|');

    $component->assertSee('Copy this token now');

    $token->refresh();
    expect($token->token)->not->toBe($originalHash);
});

test('rolling a token updates the prefix', function () {
    $user = User::factory()->create();

    $component = Livewire::actingAs($user)
        ->test(ApiTokens::class)
        ->set('tokenName', 'Roll Prefix Test')
        ->call('createToken');

    $token = $user->tokens()->first();
    $originalPrefix = $token->token_prefix;

    $component->call('dismissToken')
        ->call('rollToken', $token->id);

    $newPlainText = $component->get('plainTextToken');
    [, $rawToken] = explode('|', $newPlainText, 2);

    $token->refresh();
    expect($token->token_prefix)->toBe(substr($rawToken, 0, 5));
});

test('user can delete a token', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(ApiTokens::class)
        ->set('tokenName', 'Delete Me')
        ->call('createToken')
        ->call('dismissToken');

    $token = $user->tokens()->first();
    expect($token)->not->toBeNull();

    Livewire::actingAs($user)
        ->test(ApiTokens::class)
        ->call('deleteToken', $token->id);

    expect($user->tokens()->count())->toBe(0);
});

test('user cannot interact with another users token', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $accessToken = $user1->createToken('User1 Token');

    Livewire::actingAs($user2)
        ->test(ApiTokens::class)
        ->call('deleteToken', $accessToken->accessToken->id);

    expect($user1->tokens()->count())->toBe(1);
});

test('shows empty state when no tokens exist', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(ApiTokens::class)
        ->assertSee('No API tokens yet');
});

test('created token actually authenticates via sanctum', function () {
    $user = User::factory()->create();

    $newToken = $user->createToken('Auth Test');
    $newToken->accessToken->update([
        'token_prefix' => substr(explode('|', $newToken->plainTextToken, 2)[1], 0, 5),
    ]);

    $this->getJson('/api/social-accounts', [
        'Authorization' => 'Bearer '.$newToken->plainTextToken,
    ])->assertOk();
});
