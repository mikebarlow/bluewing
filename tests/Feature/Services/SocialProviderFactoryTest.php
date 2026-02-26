<?php

use App\Enums\Provider;
use App\Services\SocialProviders\Bluesky\BlueskyClient;
use App\Services\SocialProviders\Contracts\SocialProviderClient;
use App\Services\SocialProviders\Contracts\ValidationResult;
use App\Services\SocialProviders\SocialProviderFactory;
use App\Services\SocialProviders\X\XClient;

test('resolves x client from enum', function () {
    $factory = app(SocialProviderFactory::class);
    $client = $factory->make(Provider::X);

    expect($client)->toBeInstanceOf(XClient::class);
});

test('resolves bluesky client from enum', function () {
    $factory = app(SocialProviderFactory::class);
    $client = $factory->make(Provider::Bluesky);

    expect($client)->toBeInstanceOf(BlueskyClient::class);
});

test('resolves from string', function () {
    $factory = app(SocialProviderFactory::class);

    expect($factory->makeFromString('x'))->toBeInstanceOf(XClient::class);
    expect($factory->makeFromString('bluesky'))->toBeInstanceOf(BlueskyClient::class);
});

test('throws for unsupported provider', function () {
    $factory = app(SocialProviderFactory::class);
    $factory->makeFromString('mastodon');
})->throws(InvalidArgumentException::class, 'Unsupported provider: mastodon');

test('allows registering custom providers', function () {
    $factory = app(SocialProviderFactory::class);

    $factory->register('test_provider', FakeTestProvider::class);

    $client = $factory->makeFromString('test_provider');

    expect($client)->toBeInstanceOf(FakeTestProvider::class);
});

test('x client validates missing credentials', function () {
    $client = new XClient;

    $result = $client->validateCredentials([]);

    expect($result->valid)->toBeFalse();
    expect($result->message)->toContain('access_token');
});

test('x client validates complete credentials', function () {
    $client = new XClient;

    $result = $client->validateCredentials([
        'access_token' => 'oauth2-access-token',
    ]);

    expect($result->valid)->toBeTrue();
});

test('bluesky client validates missing credentials', function () {
    $client = new BlueskyClient;

    $result = $client->validateCredentials([]);

    expect($result->valid)->toBeFalse();
    expect($result->message)->toContain('handle');
});

test('bluesky client validates complete credentials', function () {
    $client = new BlueskyClient;

    $result = $client->validateCredentials([
        'handle' => 'user.bsky.social',
        'app_password' => 'xxxx-xxxx-xxxx-xxxx',
    ]);

    expect($result->valid)->toBeTrue();
});

test('credential fields are defined for x', function () {
    $fields = XClient::credentialFields();

    expect($fields)->toHaveKeys([
        'access_token',
        'refresh_token',
        'expires_at',
    ]);

    expect($fields['access_token']['required'])->toBeTrue();
    expect($fields['refresh_token']['required'])->toBeFalse();
});

test('credential fields are defined for bluesky', function () {
    $fields = BlueskyClient::credentialFields();

    expect($fields)->toHaveKeys(['handle', 'app_password']);
    expect($fields['handle']['required'])->toBeTrue();
    expect($fields['app_password']['required'])->toBeTrue();
});

// Minimal fake provider for the registration test
class FakeTestProvider implements SocialProviderClient
{
    public function validateCredentials(array $credentials): ValidationResult
    {
        return ValidationResult::success();
    }

    public function publishText(string $externalAccountId, array $credentials, string $text): \App\Services\SocialProviders\Contracts\ProviderPublishResult
    {
        return $this->publish($externalAccountId, $credentials, $text);
    }

    public function publish(string $externalAccountId, array $credentials, string $text, array $media = []): \App\Services\SocialProviders\Contracts\ProviderPublishResult
    {
        return \App\Services\SocialProviders\Contracts\ProviderPublishResult::success('fake-id');
    }

    public static function credentialFields(): array
    {
        return [];
    }
}
