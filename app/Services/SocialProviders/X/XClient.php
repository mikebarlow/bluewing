<?php

namespace App\Services\SocialProviders\X;

use App\Enums\MediaType;
use App\Services\SocialProviders\Contracts\ProviderMediaItem;
use App\Services\SocialProviders\Contracts\ProviderPublishResult;
use App\Services\SocialProviders\Contracts\SocialProviderClient;
use App\Services\SocialProviders\Contracts\ValidationResult;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class XClient implements SocialProviderClient
{
    private const EXPIRY_BUFFER_SECONDS = 300;

    public function apiBaseUrl(): string
    {
        return rtrim(config('services.x.api_base_url', 'https://api.x.com/2'), '/');
    }

    public function uploadBaseUrl(): string
    {
        return rtrim(config('services.x.upload_base_url', 'https://upload.x.com/1.1'), '/');
    }

    public function validateCredentials(array $credentials): ValidationResult
    {
        if (empty($credentials['access_token'])) {
            return ValidationResult::failure('Missing required credential: access_token');
        }

        return ValidationResult::success();
    }

    public function publishText(string $externalAccountId, array $credentials, string $text): ProviderPublishResult
    {
        return $this->publish($externalAccountId, $credentials, $text);
    }

    public function publish(string $externalAccountId, array $credentials, string $text, array $media = []): ProviderPublishResult
    {
        $validation = $this->validateCredentials($credentials);

        if (! $validation->valid) {
            return ProviderPublishResult::failure($validation->message);
        }

        $refreshedCredentials = null;

        try {
            if ($this->tokenIsExpired($credentials)) {
                $refreshed = $this->refreshAccessToken($credentials);

                if (! $refreshed) {
                    return ProviderPublishResult::failure(
                        'OAuth2 access token has expired and could not be refreshed. Please reconnect your X account.'
                    );
                }

                $credentials = $refreshed;
                $refreshedCredentials = $refreshed;
            }

            $providerMediaIds = [];

            foreach ($media as $item) {
                $uploadedId = $this->uploadMedia($credentials['access_token'], $item);

                if ($uploadedId === null) {
                    return ProviderPublishResult::failure(
                        "Failed to upload media: {$item->filename}"
                    );
                }

                $providerMediaIds[$item->id] = $uploadedId;
            }

            $payload = ['text' => $text];

            if (! empty($providerMediaIds)) {
                $payload['media'] = ['media_ids' => array_values($providerMediaIds)];
            }

            $response = Http::withToken($credentials['access_token'])
                ->post($this->apiBaseUrl().'/tweets', $payload);

            if ($response->successful() && $response->json('data.id')) {
                return ProviderPublishResult::success(
                    $response->json('data.id'),
                    $refreshedCredentials,
                    $providerMediaIds,
                );
            }

            $error = $response->json('detail')
                ?? $response->json('title')
                ?? 'Unknown X API error (HTTP '.$response->status().')';

            return ProviderPublishResult::failure($error);
        } catch (\Throwable $e) {
            Log::error('X publish failed', [
                'account' => $externalAccountId,
                'error' => $e->getMessage(),
            ]);

            return ProviderPublishResult::failure($e->getMessage());
        }
    }

    /**
     * Upload a single media item to X via the upload endpoint.
     *
     * Uses simple upload for images/GIFs, chunked INIT/APPEND/FINALIZE for video.
     */
    protected function uploadMedia(string $accessToken, ProviderMediaItem $item): ?string
    {
        $uploadUrl = $this->uploadBaseUrl().'/media/upload.json';

        if ($item->type === MediaType::Video) {
            return $this->chunkedUpload($accessToken, $item, $uploadUrl);
        }

        $response = Http::withToken($accessToken)
            ->asMultipart()
            ->post($uploadUrl, [
                ['name' => 'media_data', 'contents' => base64_encode($item->contents)],
                ['name' => 'media_category', 'contents' => $item->type === MediaType::Gif ? 'tweet_gif' : 'tweet_image'],
            ]);

        if ($response->successful() && $response->json('media_id_string')) {
            return $response->json('media_id_string');
        }

        Log::warning('X media upload failed', [
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        return null;
    }

    /**
     * Chunked media upload for video: INIT → APPEND → FINALIZE.
     */
    protected function chunkedUpload(string $accessToken, ProviderMediaItem $item, string $uploadUrl): ?string
    {
        $initResponse = Http::withToken($accessToken)
            ->asForm()
            ->post($uploadUrl, [
                'command' => 'INIT',
                'media_type' => $item->mimeType,
                'total_bytes' => $item->sizeBytes,
                'media_category' => 'amplify_video',
            ]);

        if (! $initResponse->successful() || ! $initResponse->json('media_id_string')) {
            Log::warning('X chunked upload INIT failed', ['status' => $initResponse->status()]);

            return null;
        }

        $mediaId = $initResponse->json('media_id_string');

        $chunkSize = 5 * 1024 * 1024;
        $chunks = str_split($item->contents, $chunkSize);

        foreach ($chunks as $index => $chunk) {
            $appendResponse = Http::withToken($accessToken)
                ->asMultipart()
                ->post($uploadUrl, [
                    ['name' => 'command', 'contents' => 'APPEND'],
                    ['name' => 'media_id', 'contents' => $mediaId],
                    ['name' => 'segment_index', 'contents' => (string) $index],
                    ['name' => 'media_data', 'contents' => base64_encode($chunk)],
                ]);

            if (! $appendResponse->successful()) {
                Log::warning('X chunked upload APPEND failed', [
                    'segment' => $index,
                    'status' => $appendResponse->status(),
                ]);

                return null;
            }
        }

        $finalizeResponse = Http::withToken($accessToken)
            ->asForm()
            ->post($uploadUrl, [
                'command' => 'FINALIZE',
                'media_id' => $mediaId,
            ]);

        if ($finalizeResponse->successful()) {
            return $mediaId;
        }

        Log::warning('X chunked upload FINALIZE failed', ['status' => $finalizeResponse->status()]);

        return null;
    }

    public static function credentialFields(): array
    {
        return [
            'access_token' => [
                'label' => 'Access Token (OAuth 2.0)',
                'type' => 'password',
                'required' => true,
            ],
            'refresh_token' => [
                'label' => 'Refresh Token (OAuth 2.0)',
                'type' => 'password',
                'required' => false,
            ],
            'expires_at' => [
                'label' => 'Token Expiry',
                'type' => 'text',
                'required' => false,
            ],
        ];
    }

    protected function tokenIsExpired(array $credentials): bool
    {
        if (empty($credentials['expires_at'])) {
            return false;
        }

        return Carbon::parse($credentials['expires_at'])
            ->subSeconds(self::EXPIRY_BUFFER_SECONDS)
            ->isPast();
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function refreshAccessToken(array $credentials): ?array
    {
        if (empty($credentials['refresh_token'])) {
            Log::warning('X token refresh attempted without a refresh token');

            return null;
        }

        $clientId = config('services.x.client_id');
        $clientSecret = config('services.x.client_secret');

        if (empty($clientId) || empty($clientSecret)) {
            Log::warning('X token refresh failed: missing X_CLIENT_ID or X_CLIENT_SECRET');

            return null;
        }

        try {
            $response = Http::asForm()
                ->withBasicAuth($clientId, $clientSecret)
                ->post($this->apiBaseUrl().'/oauth2/token', [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $credentials['refresh_token'],
                ]);

            if (! $response->successful() || ! $response->json('access_token')) {
                Log::warning('X token refresh failed', [
                    'status' => $response->status(),
                    'error' => $response->json('error') ?? 'unknown',
                ]);

                return null;
            }

            $data = $response->json();

            return array_merge($credentials, [
                'access_token' => $data['access_token'],
                'refresh_token' => $data['refresh_token'] ?? $credentials['refresh_token'],
                'expires_at' => isset($data['expires_in'])
                    ? now()->addSeconds($data['expires_in'])->toIso8601String()
                    : $credentials['expires_at'] ?? null,
                'scope' => $data['scope'] ?? $credentials['scope'] ?? null,
                'token_type' => $data['token_type'] ?? $credentials['token_type'] ?? 'bearer',
            ]);
        } catch (\Throwable $e) {
            Log::error('X token refresh exception', ['error' => $e->getMessage()]);

            return null;
        }
    }
}
