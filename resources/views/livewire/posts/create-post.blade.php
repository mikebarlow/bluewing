<div>
    <flux:heading size="xl">{{ __('Create Post') }}</flux:heading>
    <flux:subheading class="mb-6">{{ __('Schedule a post to one or more social accounts.') }}</flux:subheading>

    <x-posts.form
        :accounts="$this->accounts"
        :providers="$this->providers"
    />
</div>
