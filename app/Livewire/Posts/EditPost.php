<?php

namespace App\Livewire\Posts;

use App\Domain\Media\ValidateMediaForTargetsAction;
use App\Domain\Posts\PostData;
use App\Domain\Posts\UpdatePostAction;
use App\Domain\SocialAccounts\GetAccessibleAccountsQuery;
use App\Enums\PostStatus;
use App\Enums\ScopeType;
use App\Models\Post;
use App\Models\PostMedia;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
#[Title('Edit Post')]
class EditPost extends Component
{
    use WithFileUploads;

    public Post $post;

    public string $scheduled_for = '';

    public string $body_text = '';

    /** @var array<int> */
    public array $selected_accounts = [];

    /** @var array<string, string> */
    public array $provider_overrides = [];

    /** @var array<int, string> */
    public array $account_overrides = [];

    /** @var array<int> IDs of uploaded PostMedia records */
    public array $media_ids = [];

    /** @var array<int, string> Alt text keyed by PostMedia ID */
    public array $alt_texts = [];

    /** @var array Temporary file uploads from Livewire */
    public $uploads = [];

    public function mount(Post $post): void
    {
        $this->authorize('update', $post);

        $this->post = $post->load(['variants', 'targets', 'media']);

        $this->scheduled_for = $post->scheduled_for?->format('Y-m-d\TH:i') ?? '';
        $this->selected_accounts = $post->targets->pluck('social_account_id')->map(fn ($id) => (int) $id)->all();

        $defaultVariant = $post->variants->where('scope_type', ScopeType::Default)->first();
        $this->body_text = $defaultVariant?->body_text ?? '';

        foreach ($post->variants->where('scope_type', ScopeType::Provider) as $variant) {
            $this->provider_overrides[$variant->scope_value] = $variant->body_text;
        }

        foreach ($post->variants->where('scope_type', ScopeType::SocialAccount) as $variant) {
            $this->account_overrides[(int) $variant->scope_value] = $variant->body_text;
        }

        $this->media_ids = $post->media->pluck('id')->all();

        foreach ($post->media as $media) {
            if ($media->alt_text) {
                $this->alt_texts[$media->id] = $media->alt_text;
            }
        }
    }

    #[Computed]
    public function accounts()
    {
        return (new GetAccessibleAccountsQuery(Auth::user()))->editable();
    }

    #[Computed]
    public function providers(): array
    {
        return collect($this->accounts)
            ->pluck('provider')
            ->unique()
            ->values()
            ->all();
    }

    #[Computed]
    public function selectedProviders(): array
    {
        return collect($this->accounts)
            ->whereIn('id', $this->selected_accounts)
            ->pluck('provider')
            ->unique()
            ->values()
            ->all();
    }

    #[Computed]
    public function hasBlueskyTarget(): bool
    {
        return collect($this->selectedProviders)
            ->contains(fn ($p) => $p->value === 'bluesky');
    }

    #[Computed]
    public function canEdit(): bool
    {
        return in_array($this->post->status, [PostStatus::Draft, PostStatus::Scheduled]);
    }

    #[Computed]
    public function mediaItems()
    {
        if (empty($this->media_ids)) {
            return collect();
        }

        return PostMedia::whereIn('id', $this->media_ids)
            ->where('user_id', Auth::id())
            ->get()
            ->sortBy(fn ($m) => array_search($m->id, $this->media_ids));
    }

    public function updatedUploads(): void
    {
        $this->validate([
            'uploads.*' => 'file|mimes:jpg,jpeg,png,gif,webp,mp4,mov,avi,webm|max:524288',
        ]);

        $validator = app(ValidateMediaForTargetsAction::class);
        $disk = config('filesystems.media_disk', 'public');

        foreach ($this->uploads as $file) {
            $mediaType = $validator->detectMediaType($file->getMimeType() ?? $file->getClientMimeType());
            $path = $file->store('media', $disk);

            $media = PostMedia::create([
                'user_id' => Auth::id(),
                'type' => $mediaType->value,
                'original_filename' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType() ?? $file->getClientMimeType(),
                'size_bytes' => $file->getSize(),
                'storage_disk' => $disk,
                'storage_path' => $path,
            ]);

            $this->media_ids[] = $media->id;
        }

        $this->uploads = [];
        unset($this->mediaItems);
    }

    public function removeMedia(int $mediaId): void
    {
        $this->media_ids = array_values(array_filter(
            $this->media_ids,
            fn ($id) => $id !== $mediaId,
        ));

        unset($this->alt_texts[$mediaId]);
        unset($this->mediaItems);
    }

    public function save(string $action = 'draft'): void
    {
        if (! $this->canEdit) {
            session()->flash('error', 'This post can no longer be edited.');

            return;
        }

        $this->validate([
            'body_text' => 'required|string|max:5000',
            'scheduled_for' => 'required|date|after:now',
            'selected_accounts' => 'required|array|min:1',
            'selected_accounts.*' => 'exists:social_accounts,id',
        ]);

        $status = $action === 'schedule' ? PostStatus::Scheduled : PostStatus::Draft;

        try {
            app(UpdatePostAction::class)->execute(
                $this->post,
                Auth::user(),
                new PostData(
                    scheduledFor: $this->scheduled_for,
                    bodyText: $this->body_text,
                    targetAccountIds: $this->selected_accounts,
                    providerOverrides: $this->provider_overrides,
                    accountOverrides: $this->account_overrides,
                    status: $status,
                    mediaIds: $this->media_ids,
                    altTexts: array_filter($this->alt_texts),
                ),
            );
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $livewireField = $field === 'targets' ? 'selected_accounts' : $field;
                $this->addError($livewireField, $messages[0]);
            }

            return;
        }

        $label = $status === PostStatus::Scheduled ? 'scheduled' : 'saved as draft';
        session()->flash('message', "Post {$label} successfully.");

        $this->redirect(route('dashboard'), navigate: true);
    }

    public function render()
    {
        return view('livewire.posts.edit-post');
    }
}
