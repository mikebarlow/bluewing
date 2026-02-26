<?php

namespace App\Services\SocialProviders;

use App\Enums\Provider;
use App\Services\SocialProviders\Bluesky\BlueskyClient;
use App\Services\SocialProviders\Contracts\SocialProviderClient;
use App\Services\SocialProviders\X\XClient;
use InvalidArgumentException;

class SocialProviderFactory
{
    /**
     * @var array<string, class-string<SocialProviderClient>>
     */
    protected array $providers = [
        'x' => XClient::class,
        'bluesky' => BlueskyClient::class,
    ];

    public function make(Provider $provider): SocialProviderClient
    {
        return $this->makeFromString($provider->value);
    }

    public function makeFromString(string $provider): SocialProviderClient
    {
        if (! isset($this->providers[$provider])) {
            throw new InvalidArgumentException("Unsupported provider: {$provider}");
        }

        return app($this->providers[$provider]);
    }

    /**
     * Register an additional provider at runtime (useful for testing or plugins).
     *
     * @param  class-string<SocialProviderClient>  $clientClass
     */
    public function register(string $providerKey, string $clientClass): void
    {
        $this->providers[$providerKey] = $clientClass;
    }
}
