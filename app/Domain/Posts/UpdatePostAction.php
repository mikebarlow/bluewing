<?php

namespace App\Domain\Posts;

use App\Domain\Media\ValidateMediaForTargetsAction;
use App\Enums\PermissionRole;
use App\Enums\PostStatus;
use App\Enums\PostTargetStatus;
use App\Enums\ScopeType;
use App\Models\Post;
use App\Models\PostMedia;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdatePostAction
{
    public function __construct(
        protected ValidateMediaForTargetsAction $validateMedia = new ValidateMediaForTargetsAction,
    ) {}

    /**
     * Update an existing post, replacing its variants, targets, and media.
     *
     * @throws ValidationException if the post cannot be edited or the user lacks access.
     */
    public function execute(Post $post, User $user, PostData $data): Post
    {
        if (! in_array($post->status, [PostStatus::Draft, PostStatus::Scheduled])) {
            throw ValidationException::withMessages([
                'post' => 'This post can no longer be edited.',
            ]);
        }

        $this->assertEditorAccessToAllTargets($user, $data->targetAccountIds);

        $mediaRecords = $this->loadAndValidateMedia($user, $data);

        return DB::transaction(function () use ($post, $data, $mediaRecords) {
            $post->update([
                'scheduled_for' => $data->scheduledFor,
                'status' => $data->status,
            ]);

            $post->variants()->delete();
            $post->targets()->delete();

            $this->detachOldMedia($post, $data->mediaIds);
            $this->createVariants($post, $data);
            $this->createTargets($post, $data);
            $this->attachMedia($post, $mediaRecords, $data->altTexts);

            return $post->fresh(['variants', 'targets', 'media']);
        });
    }

    /**
     * @param  array<int>  $accountIds
     *
     * @throws ValidationException
     */
    protected function assertEditorAccessToAllTargets(User $user, array $accountIds): void
    {
        foreach ($accountIds as $accountId) {
            $account = SocialAccount::find($accountId);

            if (! $account || ! $account->userHasRole($user, PermissionRole::Editor)) {
                throw ValidationException::withMessages([
                    'targets' => 'You do not have permission to publish to one of the selected accounts.',
                ]);
            }
        }
    }

    /**
     * Load media records and validate them against target provider limits.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, PostMedia>
     *
     * @throws ValidationException
     */
    protected function loadAndValidateMedia(User $user, PostData $data): \Illuminate\Database\Eloquent\Collection
    {
        if (empty($data->mediaIds)) {
            return PostMedia::query()->whereRaw('1 = 0')->get();
        }

        $media = PostMedia::whereIn('id', $data->mediaIds)
            ->where('user_id', $user->id)
            ->get();

        if ($media->count() !== count($data->mediaIds)) {
            throw ValidationException::withMessages([
                'media' => 'One or more media files could not be found or do not belong to you.',
            ]);
        }

        $providers = SocialAccount::whereIn('id', $data->targetAccountIds)
            ->pluck('provider')
            ->unique()
            ->values()
            ->all();

        $mediaArrays = $media->map(fn (PostMedia $m) => [
            'type' => $m->type,
            'size_bytes' => $m->size_bytes,
            'mime_type' => $m->mime_type,
            'original_filename' => $m->original_filename,
        ])->all();

        $errors = $this->validateMedia->execute($mediaArrays, $providers);

        if (! empty($errors)) {
            throw ValidationException::withMessages([
                'media' => $errors,
            ]);
        }

        return $media;
    }

    /**
     * Detach media that was previously linked to this post but is no longer in the new set.
     *
     * @param  array<int>  $newMediaIds
     */
    protected function detachOldMedia(Post $post, array $newMediaIds): void
    {
        $post->media()
            ->whereNotIn('id', $newMediaIds)
            ->update(['post_id' => null, 'alt_text' => null]);
    }

    /**
     * Attach media records to the post and apply alt texts.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, PostMedia>  $mediaRecords
     * @param  array<int, string>  $altTexts
     */
    protected function attachMedia(Post $post, $mediaRecords, array $altTexts): void
    {
        foreach ($mediaRecords as $media) {
            $updates = ['post_id' => $post->id];

            if (isset($altTexts[$media->id]) && $altTexts[$media->id] !== '') {
                $updates['alt_text'] = $altTexts[$media->id];
            }

            $media->update($updates);
        }
    }

    protected function createVariants(Post $post, PostData $data): void
    {
        $post->variants()->create([
            'scope_type' => ScopeType::Default,
            'scope_value' => null,
            'body_text' => $data->bodyText,
        ]);

        foreach ($data->providerOverrides as $provider => $text) {
            if (trim($text) !== '') {
                $post->variants()->create([
                    'scope_type' => ScopeType::Provider,
                    'scope_value' => $provider,
                    'body_text' => $text,
                ]);
            }
        }

        foreach ($data->accountOverrides as $accountId => $text) {
            if (trim($text) !== '') {
                $post->variants()->create([
                    'scope_type' => ScopeType::SocialAccount,
                    'scope_value' => (string) $accountId,
                    'body_text' => $text,
                ]);
            }
        }
    }

    protected function createTargets(Post $post, PostData $data): void
    {
        foreach ($data->targetAccountIds as $accountId) {
            $post->targets()->create([
                'social_account_id' => $accountId,
                'status' => PostTargetStatus::Pending,
            ]);
        }
    }
}
