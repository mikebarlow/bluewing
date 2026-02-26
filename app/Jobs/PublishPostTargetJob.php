<?php

namespace App\Jobs;

use App\Enums\PostStatus;
use App\Enums\PostTargetStatus;
use App\Models\PostMedia;
use App\Models\PostTarget;
use App\Models\PostTargetMedia;
use App\Services\SocialProviders\Contracts\ProviderMediaItem;
use App\Services\SocialProviders\Contracts\ProviderPublishResult;
use App\Services\SocialProviders\SocialProviderFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PublishPostTargetJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public readonly int $postTargetId,
    ) {}

    public function handle(SocialProviderFactory $factory): void
    {
        $target = PostTarget::with(['socialAccount', 'post.variants', 'post.media'])->findOrFail($this->postTargetId);
        $post = $target->post;
        $account = $target->socialAccount;

        $post->update(['status' => PostStatus::Publishing]);

        $text = $post->resolveTextForTarget($target);

        if ($text === null || $text === '') {
            $this->markTargetFailed($target, 'No text content resolved for this target');
            $this->reconcilePostStatus($post);

            return;
        }

        $mediaItems = $this->buildMediaItems($post->media);

        $client = $factory->make($account->provider);
        $result = $client->publish(
            $account->external_identifier,
            $account->credentials_encrypted,
            $text,
            $mediaItems,
        );

        if ($result->refreshedCredentials) {
            $account->update(['credentials_encrypted' => $result->refreshedCredentials]);
        }

        if ($result->success) {
            $target->update([
                'status' => PostTargetStatus::Sent,
                'sent_at' => now(),
                'error_message' => null,
            ]);

            $this->storeProviderMediaIds($target, $result);

            Log::info('Published to social account', [
                'post_id' => $post->id,
                'target_id' => $target->id,
                'provider' => $account->provider->value,
                'external_post_id' => $result->externalPostId,
            ]);
        } else {
            $this->markTargetFailed($target, $result->errorMessage);
        }

        $this->reconcilePostStatus($post);
    }

    /**
     * Build ProviderMediaItem DTOs from PostMedia models, reading file contents from storage.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, PostMedia>  $mediaCollection
     * @return ProviderMediaItem[]
     */
    protected function buildMediaItems($mediaCollection): array
    {
        if ($mediaCollection->isEmpty()) {
            return [];
        }

        $items = [];

        foreach ($mediaCollection as $media) {
            $contents = Storage::disk($media->storage_disk)->get($media->storage_path);

            if ($contents === null) {
                Log::warning('Could not read media file from storage', [
                    'media_id' => $media->id,
                    'path' => $media->storage_path,
                ]);

                continue;
            }

            $items[] = new ProviderMediaItem(
                id: $media->id,
                type: $media->type,
                mimeType: $media->mime_type,
                contents: $contents,
                sizeBytes: $media->size_bytes,
                altText: $media->alt_text,
                filename: $media->original_filename,
            );
        }

        return $items;
    }

    /**
     * Create PostTargetMedia records linking provider media IDs to the target.
     */
    protected function storeProviderMediaIds(PostTarget $target, ProviderPublishResult $result): void
    {
        foreach ($result->providerMediaIds as $postMediaId => $providerMediaId) {
            PostTargetMedia::create([
                'post_target_id' => $target->id,
                'post_media_id' => $postMediaId,
                'provider_media_id' => $providerMediaId,
            ]);
        }
    }

    protected function markTargetFailed(PostTarget $target, string $message): void
    {
        $target->update([
            'status' => PostTargetStatus::Failed,
            'error_message' => $message,
        ]);
    }

    /**
     * After each target completes, check if the parent post status should be updated.
     */
    protected function reconcilePostStatus(\App\Models\Post $post): void
    {
        $targets = $post->targets()->get();

        $statuses = $targets->pluck('status');

        $hasPendingOrQueued = $statuses->contains(fn ($s) => in_array($s, [
            PostTargetStatus::Pending,
            PostTargetStatus::Queued,
        ]));

        if ($hasPendingOrQueued) {
            return;
        }

        $allSent = $statuses->every(fn ($s) => $s === PostTargetStatus::Sent);

        if ($allSent) {
            $maxSentAt = $targets->max('sent_at');

            $post->update([
                'status' => PostStatus::Sent,
                'sent_at' => $maxSentAt,
            ]);

            return;
        }

        $hasFailed = $statuses->contains(fn ($s) => $s === PostTargetStatus::Failed);

        if ($hasFailed) {
            $post->update(['status' => PostStatus::Failed]);
        }
    }
}
