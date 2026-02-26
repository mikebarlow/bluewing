<?php

namespace App\Services\SocialProviders\Contracts;

use App\Enums\MediaType;

final readonly class ProviderMediaItem
{
    public function __construct(
        public int $id,
        public MediaType $type,
        public string $mimeType,
        public string $contents,
        public int $sizeBytes,
        public ?string $altText = null,
        public ?string $filename = null,
    ) {}
}
