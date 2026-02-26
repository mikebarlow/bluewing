<?php

namespace App\Services\SocialProviders\Contracts;

final readonly class ProviderPublishResult
{
    /**
     * @param  array<string, mixed>|null  $refreshedCredentials  Updated credentials the caller should persist.
     * @param  array<int, string>  $providerMediaIds  Keyed by PostMedia ID → provider media identifier.
     */
    public function __construct(
        public bool $success,
        public string $externalPostId = '',
        public string $errorMessage = '',
        public ?array $refreshedCredentials = null,
        public array $providerMediaIds = [],
    ) {}

    public static function success(string $externalPostId = '', ?array $refreshedCredentials = null, array $providerMediaIds = []): self
    {
        return new self(success: true, externalPostId: $externalPostId, refreshedCredentials: $refreshedCredentials, providerMediaIds: $providerMediaIds);
    }

    public static function failure(string $errorMessage): self
    {
        return new self(success: false, errorMessage: $errorMessage);
    }
}
