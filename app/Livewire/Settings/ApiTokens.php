<?php

namespace App\Livewire\Settings;

use Illuminate\Support\Str;
use Livewire\Component;

class ApiTokens extends Component
{
    public string $tokenName = '';

    public ?string $plainTextToken = null;

    public ?int $highlightTokenId = null;

    public function createToken(): void
    {
        $this->validate([
            'tokenName' => ['required', 'string', 'max:255'],
        ]);

        $user = auth()->user();
        $newAccessToken = $user->createToken($this->tokenName);

        $fullPlainText = $newAccessToken->plainTextToken;
        [, $rawToken] = explode('|', $fullPlainText, 2);

        $newAccessToken->accessToken->update([
            'token_prefix' => substr($rawToken, 0, 5),
        ]);

        $this->plainTextToken = $fullPlainText;
        $this->highlightTokenId = $newAccessToken->accessToken->id;
        $this->tokenName = '';
    }

    public function rollToken(int $tokenId): void
    {
        $token = auth()->user()->tokens()->findOrFail($tokenId);

        $plainTextToken = $this->generateTokenString();

        $token->update([
            'token' => hash('sha256', $plainTextToken),
            'token_prefix' => substr($plainTextToken, 0, 5),
        ]);

        $this->plainTextToken = $token->getKey().'|'.$plainTextToken;
        $this->highlightTokenId = $token->id;
    }

    public function deleteToken(int $tokenId): void
    {
        auth()->user()->tokens()->where('id', $tokenId)->delete();

        if ($this->highlightTokenId === $tokenId) {
            $this->dismissToken();
        }
    }

    public function dismissToken(): void
    {
        $this->plainTextToken = null;
        $this->highlightTokenId = null;
    }

    public function render()
    {
        return view('livewire.settings.api-tokens', [
            'tokens' => auth()->user()->tokens()->orderByDesc('created_at')->get(),
        ]);
    }

    /**
     * Generate a token string using the same approach as Sanctum.
     */
    protected function generateTokenString(): string
    {
        return sprintf(
            '%s%s%s',
            config('sanctum.token_prefix', ''),
            $tokenEntropy = Str::random(40),
            hash('crc32b', $tokenEntropy)
        );
    }
}
