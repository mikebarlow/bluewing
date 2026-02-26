<?php

use App\Services\SocialProviders\X\XClient;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'services.x.client_id' => 'test-client-id',
        'services.x.client_secret' => 'test-client-secret',
    ]);
});

test('publishes tweet using oauth2 bearer token', function () {
    Http::fake([
        'api.x.com/2/tweets' => Http::response([
            'data' => ['id' => '1234567890'],
        ]),
    ]);

    $client = new XClient;

    $result = $client->publishText('ext-123', [
        'access_token' => 'valid-oauth2-token',
        'expires_at' => now()->addHours(1)->toIso8601String(),
    ], 'Hello from Blue Wing!');

    expect($result->success)->toBeTrue();
    expect($result->externalPostId)->toBe('1234567890');
    expect($result->refreshedCredentials)->toBeNull();

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.x.com/2/tweets'
            && $request->hasHeader('Authorization', 'Bearer valid-oauth2-token')
            && $request['text'] === 'Hello from Blue Wing!';
    });
});

test('fails when access token is missing', function () {
    $client = new XClient;

    $result = $client->publishText('ext-123', [], 'Hello');

    expect($result->success)->toBeFalse();
    expect($result->errorMessage)->toContain('access_token');
});

test('refreshes expired token before publishing', function () {
    Http::fake([
        'api.x.com/2/oauth2/token' => Http::response([
            'access_token' => 'fresh-access-token',
            'refresh_token' => 'fresh-refresh-token',
            'expires_in' => 7200,
            'scope' => 'tweet.read tweet.write users.read offline.access',
            'token_type' => 'bearer',
        ]),
        'api.x.com/2/tweets' => Http::response([
            'data' => ['id' => '9876543210'],
        ]),
    ]);

    $client = new XClient;

    $result = $client->publishText('ext-123', [
        'access_token' => 'expired-token',
        'refresh_token' => 'old-refresh-token',
        'expires_at' => now()->subMinutes(10)->toIso8601String(),
    ], 'Post with refreshed token');

    expect($result->success)->toBeTrue();
    expect($result->externalPostId)->toBe('9876543210');

    expect($result->refreshedCredentials)->not->toBeNull();
    expect($result->refreshedCredentials['access_token'])->toBe('fresh-access-token');
    expect($result->refreshedCredentials['refresh_token'])->toBe('fresh-refresh-token');
    expect($result->refreshedCredentials['expires_at'])->not->toBeNull();

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.x.com/2/tweets'
            && $request->hasHeader('Authorization', 'Bearer fresh-access-token');
    });
});

test('refreshes token that expires within buffer window', function () {
    Http::fake([
        'api.x.com/2/oauth2/token' => Http::response([
            'access_token' => 'proactively-refreshed',
            'refresh_token' => 'new-refresh',
            'expires_in' => 7200,
            'token_type' => 'bearer',
        ]),
        'api.x.com/2/tweets' => Http::response([
            'data' => ['id' => '111'],
        ]),
    ]);

    $client = new XClient;

    $result = $client->publishText('ext-123', [
        'access_token' => 'soon-expired-token',
        'refresh_token' => 'refresh-token',
        'expires_at' => now()->addMinutes(2)->toIso8601String(), // within 5-min buffer
    ], 'Proactive refresh');

    expect($result->success)->toBeTrue();
    expect($result->refreshedCredentials)->not->toBeNull();
    expect($result->refreshedCredentials['access_token'])->toBe('proactively-refreshed');
});

test('fails gracefully when refresh token is missing and token expired', function () {
    $client = new XClient;

    $result = $client->publishText('ext-123', [
        'access_token' => 'expired-token',
        'expires_at' => now()->subHour()->toIso8601String(),
    ], 'Will fail');

    expect($result->success)->toBeFalse();
    expect($result->errorMessage)->toContain('reconnect');
});

test('fails gracefully when token refresh returns error', function () {
    Http::fake([
        'api.x.com/2/oauth2/token' => Http::response([
            'error' => 'invalid_grant',
            'error_description' => 'Token has been revoked.',
        ], 400),
    ]);

    $client = new XClient;

    $result = $client->publishText('ext-123', [
        'access_token' => 'expired-token',
        'refresh_token' => 'revoked-refresh-token',
        'expires_at' => now()->subHour()->toIso8601String(),
    ], 'Will fail');

    expect($result->success)->toBeFalse();
    expect($result->errorMessage)->toContain('reconnect');
});

test('does not refresh token when no expires_at is set', function () {
    Http::fake([
        'api.x.com/2/tweets' => Http::response([
            'data' => ['id' => '555'],
        ]),
    ]);

    $client = new XClient;

    $result = $client->publishText('ext-123', [
        'access_token' => 'no-expiry-token',
    ], 'No expiry check');

    expect($result->success)->toBeTrue();
    expect($result->refreshedCredentials)->toBeNull();

    Http::assertSentCount(1);
});

test('handles x api error response', function () {
    Http::fake([
        'api.x.com/2/tweets' => Http::response([
            'detail' => 'You are not permitted to create a Tweet.',
            'title' => 'Forbidden',
        ], 403),
    ]);

    $client = new XClient;

    $result = $client->publishText('ext-123', [
        'access_token' => 'valid-token',
    ], 'Forbidden post');

    expect($result->success)->toBeFalse();
    expect($result->errorMessage)->toContain('not permitted');
});

test('token refresh sends correct request with basic auth', function () {
    Http::fake([
        'api.x.com/2/oauth2/token' => Http::response([
            'access_token' => 'new-token',
            'refresh_token' => 'new-refresh',
            'expires_in' => 7200,
            'token_type' => 'bearer',
        ]),
        'api.x.com/2/tweets' => Http::response([
            'data' => ['id' => '999'],
        ]),
    ]);

    $client = new XClient;

    $client->publishText('ext-123', [
        'access_token' => 'old-token',
        'refresh_token' => 'my-refresh-token',
        'expires_at' => now()->subHour()->toIso8601String(),
    ], 'Check refresh request');

    Http::assertSent(function ($request) {
        if ($request->url() !== 'https://api.x.com/2/oauth2/token') {
            return false;
        }

        return $request['grant_type'] === 'refresh_token'
            && $request['refresh_token'] === 'my-refresh-token'
            && $request->hasHeader('Authorization');
    });
});
