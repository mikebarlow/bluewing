<?php

use App\Services\SocialProviders\X\XClient;

test('x client api base url uses api.x.com', function () {
    $client = new XClient;

    expect($client->apiBaseUrl())->toContain('api.x.com');
    expect($client->apiBaseUrl())->not->toContain('twitter.com');
});

test('x client upload base url uses upload.x.com', function () {
    $client = new XClient;

    expect($client->uploadBaseUrl())->toContain('upload.x.com');
    expect($client->uploadBaseUrl())->not->toContain('twitter.com');
});

test('no twitter.com domain is used in x config', function () {
    $apiBaseUrl = config('services.x.api_base_url');
    $uploadBaseUrl = config('services.x.upload_base_url');
    $authorizeUrl = config('services.x.authorize_url');

    expect($apiBaseUrl)->not->toContain('twitter.com');
    expect($uploadBaseUrl)->not->toContain('twitter.com');
    expect($authorizeUrl)->not->toContain('twitter.com');
});

test('x config api base url defaults to api.x.com', function () {
    expect(config('services.x.api_base_url'))->toBe('https://api.x.com/2');
});

test('x config upload base url defaults to upload.x.com', function () {
    expect(config('services.x.upload_base_url'))->toBe('https://upload.x.com/1.1');
});

test('x config authorize url uses x.com', function () {
    expect(config('services.x.authorize_url'))->toBe('https://x.com/i/oauth2/authorize');
});
