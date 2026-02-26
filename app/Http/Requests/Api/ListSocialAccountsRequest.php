<?php

namespace App\Http\Requests\Api;

use App\Enums\Provider;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListSocialAccountsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'provider' => ['sometimes', 'nullable', Rule::in(array_column(Provider::cases(), 'value'))],
        ];
    }
}
