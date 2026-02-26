<?php

namespace App\Http\Controllers;

use App\Enums\Provider;
use App\Models\SocialAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class XOAuthController extends Controller
{
    private function authorizeUrl(): string
    {
        return config('services.x.authorize_url', 'https://x.com/i/oauth2/authorize');
    }

    private function tokenUrl(): string
    {
        return rtrim(config('services.x.api_base_url', 'https://api.x.com/2'), '/').'/oauth2/token';
    }

    private function userMeUrl(): string
    {
        return rtrim(config('services.x.api_base_url', 'https://api.x.com/2'), '/').'/users/me';
    }

    /**
     * Scopes required for posting and reading identity.
     * offline.access requests a refresh token.
     */
    private const SCOPES = 'tweet.read tweet.write users.read offline.access';

    /**
     * Redirect the user to X's authorization page.
     */
    public function redirect(Request $request): RedirectResponse
    {
        $clientId = config('services.x.client_id');

        if (empty($clientId)) {
            return redirect()
                ->route('social-accounts.connect-x')
                ->with('error', 'X OAuth2 Client ID is not configured. Set X_CLIENT_ID in your .env file.');
        }

        $state = Str::random(40);
        $codeVerifier = Str::random(128);
        $codeChallenge = strtr(rtrim(base64_encode(hash('sha256', $codeVerifier, true)), '='), '+/', '-_');

        $request->session()->put('x_oauth_state', $state);
        $request->session()->put('x_oauth_code_verifier', $codeVerifier);

        $params = http_build_query([
            'response_type' => 'code',
            'client_id' => $clientId,
            'redirect_uri' => $this->redirectUri(),
            'scope' => self::SCOPES,
            'state' => $state,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
        ]);

        return redirect()->away($this->authorizeUrl().'?'.$params);
    }

    /**
     * Handle the callback from X after authorization.
     */
    public function callback(Request $request): RedirectResponse
    {
        if ($request->has('error')) {
            $description = $request->input('error_description', $request->input('error'));

            return redirect()
                ->route('social-accounts.connect-x')
                ->with('error', "X authorization was denied: {$description}");
        }

        $storedState = $request->session()->pull('x_oauth_state');
        $codeVerifier = $request->session()->pull('x_oauth_code_verifier');

        if (! $storedState || $request->input('state') !== $storedState) {
            return redirect()
                ->route('social-accounts.connect-x')
                ->with('error', 'Invalid OAuth state. Please try connecting again.');
        }

        $code = $request->input('code');

        if (empty($code)) {
            return redirect()
                ->route('social-accounts.connect-x')
                ->with('error', 'No authorization code received from X.');
        }

        $tokens = $this->exchangeCodeForTokens($code, $codeVerifier);

        if (! $tokens) {
            return redirect()
                ->route('social-accounts.connect-x')
                ->with('error', 'Failed to exchange authorization code for tokens. Please try again.');
        }

        $userInfo = $this->fetchUserInfo($tokens['access_token']);

        if (! $userInfo) {
            return redirect()
                ->route('social-accounts.connect-x')
                ->with('error', 'Failed to retrieve your X profile. Please try again.');
        }

        SocialAccount::create([
            'user_id' => $request->user()->id,
            'provider' => Provider::X,
            'display_name' => '@'.$userInfo['username'],
            'external_identifier' => $userInfo['id'],
            'credentials_encrypted' => [
                'access_token' => $tokens['access_token'],
                'refresh_token' => $tokens['refresh_token'] ?? null,
                'expires_at' => isset($tokens['expires_in'])
                    ? now()->addSeconds($tokens['expires_in'])->toIso8601String()
                    : null,
                'scope' => $tokens['scope'] ?? null,
                'token_type' => $tokens['token_type'] ?? 'bearer',
            ],
        ]);

        return redirect()
            ->route('social-accounts.index')
            ->with('message', "X account @{$userInfo['username']} connected successfully.");
    }

    /**
     * Exchange the authorization code for an access token using PKCE.
     *
     * @return array<string, mixed>|null
     */
    protected function exchangeCodeForTokens(string $code, string $codeVerifier): ?array
    {
        try {
            $response = Http::asForm()
                ->withBasicAuth(config('services.x.client_id'), config('services.x.client_secret'))
                ->post($this->tokenUrl(), [
                    'code' => $code,
                    'grant_type' => 'authorization_code',
                    'redirect_uri' => $this->redirectUri(),
                    'code_verifier' => $codeVerifier,
                ]);

            if ($response->successful() && $response->json('access_token')) {
                return $response->json();
            }

            Log::warning('X token exchange failed', [
                'status' => $response->status(),
                'error' => $response->json('error') ?? 'unknown',
                'error_description' => $response->json('error_description') ?? '',
            ]);

            return null;
        } catch (\Throwable $e) {
            Log::error('X token exchange exception', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Fetch the authenticated user's X profile.
     *
     * @return array{id: string, username: string}|null
     */
    protected function fetchUserInfo(string $accessToken): ?array
    {
        try {
            $response = Http::withToken($accessToken)->get($this->userMeUrl());

            $data = $response->json('data');

            if ($response->successful() && ! empty($data['id']) && ! empty($data['username'])) {
                return $data;
            }

            Log::warning('X user info fetch failed', [
                'status' => $response->status(),
                'error' => $response->json('title') ?? 'unknown',
            ]);

            return null;
        } catch (\Throwable $e) {
            Log::error('X user info exception', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Build the full redirect URI from config.
     */
    protected function redirectUri(): string
    {
        $configured = config('services.x.redirect_uri');

        if (str_starts_with($configured, 'http')) {
            return $configured;
        }

        return rtrim(config('app.url'), '/').'/'.ltrim($configured, '/');
    }
}
