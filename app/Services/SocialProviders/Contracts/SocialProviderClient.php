<?php

namespace App\Services\SocialProviders\Contracts;

interface SocialProviderClient
{
    /**
     * Validate that the given credentials are complete and usable.
     *
     * @param  array<string, mixed>  $credentials
     */
    public function validateCredentials(array $credentials): ValidationResult;

    /**
     * Publish a text post to the provider.
     *
     * @param  array<string, mixed>  $credentials
     */
    public function publishText(string $externalAccountId, array $credentials, string $text): ProviderPublishResult;

    /**
     * Publish a post with optional media attachments.
     *
     * Providers that support media should upload each item first, then include
     * provider media IDs in the post creation request. The returned result
     * includes a providerMediaIds map (PostMedia ID → provider media ID).
     *
     * @param  array<string, mixed>  $credentials
     * @param  ProviderMediaItem[]  $media
     */
    public function publish(string $externalAccountId, array $credentials, string $text, array $media = []): ProviderPublishResult;

    /**
     * Return the list of credential fields this provider requires.
     *
     * @return array<string, array{label: string, type: string, required: bool}>
     */
    public static function credentialFields(): array;
}
