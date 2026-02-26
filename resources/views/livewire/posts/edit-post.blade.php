<div>
    <flux:heading size="xl">{{ __('Edit Post') }}</flux:heading>

    @if (! $this->canEdit)
        <div class="mt-4 rounded-lg bg-warning-50 p-4 text-warning-700">
            {{ __('This post can no longer be edited because it has already been queued or published.') }}
        </div>
    @else
        <flux:subheading class="mb-6">{{ __('Update your scheduled post.') }}</flux:subheading>

        <x-posts.form
            :accounts="$this->accounts"
            :providers="$this->providers"
        />
    @endif
</div>
