<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StorePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'scheduled_for' => ['required', 'date', 'after:'.now()->subMinutes(5)->toDateTimeString()],
            'body_text' => ['required', 'string', 'max:5000'],
            'targets' => ['required', 'array', 'min:1'],
            'targets.*' => ['integer', 'exists:social_accounts,id'],
            'provider_overrides' => ['sometimes', 'array'],
            'provider_overrides.*' => ['string', 'max:5000'],
            'account_overrides' => ['sometimes', 'array'],
            'account_overrides.*' => ['string', 'max:5000'],
            'media_ids' => ['sometimes', 'array', 'max:4'],
            'media_ids.*' => ['integer', 'exists:post_media,id'],
            'alt_texts' => ['sometimes', 'array'],
            'alt_texts.*' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'scheduled_for.after' => 'The scheduled time must not be significantly in the past.',
            'targets.required' => 'At least one target social account is required.',
            'targets.min' => 'At least one target social account is required.',
        ];
    }
}
