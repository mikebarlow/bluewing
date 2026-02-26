<div>
    <flux:heading size="xl">{{ __('Connect Bluesky Account') }}</flux:heading>
    <flux:subheading>{{ __('Enter your Bluesky handle and an App Password to connect your account.') }}</flux:subheading>

    <form wire:submit="save" class="mt-6 max-w-lg space-y-6">
        @error('credentials')
            <div class="rounded-lg bg-danger-50 p-4 text-danger-700">{{ $message }}</div>
        @enderror

        <flux:input wire:model="display_name" :label="__('Display Name')" placeholder="My Bluesky Account" required />
        <flux:input wire:model="handle" :label="__('Handle')" placeholder="yourname.bsky.social" required />
        <flux:input wire:model="app_password" :label="__('App Password')" type="password" required />

        <flux:text class="text-sm">
            {{ __('You can create an App Password in your Bluesky account settings under Privacy and Security → App Passwords.') }}
        </flux:text>

        <div class="flex items-center gap-4">
            <flux:button variant="primary" type="submit">{{ __('Connect Account') }}</flux:button>
            <flux:button :href="route('social-accounts.index')" wire:navigate>{{ __('Cancel') }}</flux:button>
        </div>
    </form>
</div>
