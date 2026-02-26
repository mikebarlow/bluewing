<?php

namespace App\Http\Requests\Api;

use App\Enums\PostStatus;
use App\Enums\Provider;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListPostsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'nullable', Rule::in(array_column(PostStatus::cases(), 'value'))],
            'provider' => ['sometimes', 'nullable', Rule::in(array_column(Provider::cases(), 'value'))],
            'from' => ['sometimes', 'nullable', 'date'],
            'to' => ['sometimes', 'nullable', 'date', 'after_or_equal:from'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'include' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
