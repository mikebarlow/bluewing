<?php

namespace App\Domain\Posts;

use App\Enums\PostStatus;

/**
 * Portable representation of a post's content, targets, and media.
 *
 * Used by both CreatePostAction and UpdatePostAction so the same
 * data structure can originate from Livewire, API requests, or tests.
 */
final readonly class PostData
{
    /**
     * @param  array<int>  $targetAccountIds  Social account IDs to publish to.
     * @param  array<string, string>  $providerOverrides  Keyed by provider value (e.g. "x" => "text").
     * @param  array<int, string>  $accountOverrides  Keyed by social_account_id.
     * @param  array<int>  $mediaIds  IDs of pre-uploaded PostMedia records to attach.
     * @param  array<int, string>  $altTexts  Alt text keyed by PostMedia ID.
     */
    public function __construct(
        public string $scheduledFor,
        public string $bodyText,
        public array $targetAccountIds,
        public array $providerOverrides = [],
        public array $accountOverrides = [],
        public PostStatus $status = PostStatus::Draft,
        public array $mediaIds = [],
        public array $altTexts = [],
    ) {}
}
