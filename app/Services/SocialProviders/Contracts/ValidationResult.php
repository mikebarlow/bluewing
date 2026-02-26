<?php

namespace App\Services\SocialProviders\Contracts;

final readonly class ValidationResult
{
    public function __construct(
        public bool $valid,
        public string $message = '',
    ) {}

    public static function success(): self
    {
        return new self(valid: true);
    }

    public static function failure(string $message): self
    {
        return new self(valid: false, message: $message);
    }
}
