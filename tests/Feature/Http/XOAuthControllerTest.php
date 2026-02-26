<?php

use App\Enums\Provider;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Support\Facades\Http;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    config([
        'services.x.client_id' => 'test-client-id',
        'services.x.client_secret' => 'test-client-secret',
        'services.x.redirect_uri' => '/social-accounts/connect/x/callback',
    ]);
});

test('redirect sends user to x authorization url with pkce params', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('social-accounts.x-oauth-redirect'));

    $response->assertRedirect();

    $location = $response->headers->get('Location');
    expect($location)->toStartWith('https://x.com/i/oauth2/authorize');
    expect($location)->toContain('response_type=code');
    expect($location)->toContain('client_id=test-client-id');
    expect($location)->toContain('code_challenge_method=S256');
    expect($location)->toContain('scope=');
    expect($location)->toContain('state=');
    expect($location)->toContain('code_challenge=');

    expect(session('x_oauth_state'))->not->toBeNull();
    expect(session('x_oauth_code_verifier'))->not->toBeNull();
});

test('redirect shows error when client id is missing', function () {
    config(['services.x.client_id' => null]);
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('social-accounts.x-oauth-redirect'));

    $response->assertRedirect(route('social-accounts.connect-x'));
    $response->assertSessionHas('error');
});

test('callback rejects invalid state parameter', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->withSession(['x_oauth_state' => 'valid-state', 'x_oauth_code_verifier' => 'verifier'])
        ->get(route('social-accounts.x-oauth-callback', ['state' => 'wrong-state', 'code' => 'abc']));

    $response->assertRedirect(route('social-accounts.connect-x'));
    $response->assertSessionHas('error', 'Invalid OAuth state. Please try connecting again.');

    expect(SocialAccount::count())->toBe(0);
});

test('callback rejects missing state parameter', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->withSession(['x_oauth_state' => 'valid-state', 'x_oauth_code_verifier' => 'verifier'])
        ->get(route('social-accounts.x-oauth-callback', ['code' => 'abc']));

    $response->assertRedirect(route('social-accounts.connect-x'));
    $response->assertSessionHas('error');
});

test('callback handles user denial', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->get(route('social-accounts.x-oauth-callback', [
            'error' => 'access_denied',
            'error_description' => 'The user denied your request.',
        ]));

    $response->assertRedirect(route('social-accounts.connect-x'));
    $response->assertSessionHas('error');
    expect(session('error'))->toContain('denied');
});

test('successful callback stores social account with encrypted credentials', function () {
    Http::fake([
        'api.x.com/2/oauth2/token' => Http::response([
            'access_token' => 'test-access-token',
            'refresh_token' => 'test-refresh-token',
            'expires_in' => 7200,
            'scope' => 'tweet.read tweet.write users.read offline.access',
            'token_type' => 'bearer',
        ]),
        'api.x.com/2/users/me' => Http::response([
            'data' => [
                'id' => '123456789',
                'username' => 'testuser',
                'name' => 'Test User',
            ],
        ]),
    ]);

    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->withSession(['x_oauth_state' => 'valid-state', 'x_oauth_code_verifier' => 'test-verifier'])
        ->get(route('social-accounts.x-oauth-callback', [
            'state' => 'valid-state',
            'code' => 'auth-code-from-x',
        ]));

    $response->assertRedirect(route('social-accounts.index'));
    $response->assertSessionHas('message');
    expect(session('message'))->toContain('@testuser');

    $account = SocialAccount::where('user_id', $user->id)->first();

    expect($account)->not->toBeNull();
    expect($account->provider)->toBe(Provider::X);
    expect($account->display_name)->toBe('@testuser');
    expect($account->external_identifier)->toBe('123456789');

    $creds = $account->credentials_encrypted;
    expect($creds['access_token'])->toBe('test-access-token');
    expect($creds['refresh_token'])->toBe('test-refresh-token');
    expect($creds['scope'])->toBe('tweet.read tweet.write users.read offline.access');
    expect($creds['token_type'])->toBe('bearer');
    expect($creds['expires_at'])->not->toBeNull();
});

test('callback handles token exchange failure', function () {
    Http::fake([
        'api.x.com/2/oauth2/token' => Http::response([
            'error' => 'invalid_grant',
            'error_description' => 'Authorization code has expired.',
        ], 400),
    ]);

    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->withSession(['x_oauth_state' => 'valid-state', 'x_oauth_code_verifier' => 'test-verifier'])
        ->get(route('social-accounts.x-oauth-callback', [
            'state' => 'valid-state',
            'code' => 'expired-code',
        ]));

    $response->assertRedirect(route('social-accounts.connect-x'));
    $response->assertSessionHas('error');
    expect(SocialAccount::count())->toBe(0);
});

test('callback handles user info fetch failure', function () {
    Http::fake([
        'api.x.com/2/oauth2/token' => Http::response([
            'access_token' => 'test-access-token',
            'refresh_token' => 'test-refresh-token',
            'expires_in' => 7200,
            'token_type' => 'bearer',
        ]),
        'api.x.com/2/users/me' => Http::response(['errors' => [['message' => 'Unauthorized']]], 401),
    ]);

    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->withSession(['x_oauth_state' => 'valid-state', 'x_oauth_code_verifier' => 'test-verifier'])
        ->get(route('social-accounts.x-oauth-callback', [
            'state' => 'valid-state',
            'code' => 'auth-code',
        ]));

    $response->assertRedirect(route('social-accounts.connect-x'));
    $response->assertSessionHas('error');
    expect(SocialAccount::count())->toBe(0);
});

test('connect x page requires authentication', function () {
    $this->get(route('social-accounts.connect-x'))
        ->assertRedirect(route('login'));
});

test('redirect requires authentication', function () {
    $this->get(route('social-accounts.x-oauth-redirect'))
        ->assertRedirect(route('login'));
});

test('callback requires authentication', function () {
    $this->get(route('social-accounts.x-oauth-callback'))
        ->assertRedirect(route('login'));
});
