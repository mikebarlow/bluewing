<?php

namespace App\Livewire\Posts;

use App\Domain\Media\ValidateMediaForTargetsAction;
use App\Domain\Posts\CreatePostAction;
use App\Domain\Posts\PostData;
use App\Domain\SocialAccounts\GetAccessibleAccountsQuery;
use App\Enums\PostStatus;
use App\Models\PostMedia;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
#[Title('Create Post')]
class CreatePost extends Component
{
    use WithFileUploads;

    public string $scheduled_for = '';

    public string $body_text = '';

    /** @var array<int> */
    public array $selected_accounts = [];

    /** @var array<string, string> Provider-level overrides keyed by provider value */
    public array $provider_overrides = [];

    /** @var array<int, string> Account-level overrides keyed by social_account_id */
    public array $account_overrides = [];

    /** @var array<int> IDs of uploaded PostMedia records */
    public array $media_ids = [];

    /** @var array<int, string> Alt text keyed by PostMedia ID */
    public array $alt_texts = [];

    /** @var array Temporary file uploads from Livewire */
    public $uploads = [];

    public function mount(): void
    {
        $this->scheduled_for = now()->addHour()->format('Y-m-d\TH:i');
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
        $this->validate([
            'body_text' => 'required|string|max:5000',
            'scheduled_for' => 'required|date|after:now',
            'selected_accounts' => 'required|array|min:1',
            'selected_accounts.*' => 'exists:social_accounts,id',
        ]);

        $status = $action === 'schedule' ? PostStatus::Scheduled : PostStatus::Draft;

        try {
            app(CreatePostAction::class)->execute(
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
        return view('livewire.posts.create-post');
    }
}
