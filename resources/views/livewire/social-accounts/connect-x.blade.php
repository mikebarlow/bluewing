<div>
    <flux:heading size="xl">{{ __('Connect X (Twitter) Account') }}</flux:heading>
    <flux:subheading>{{ __('Connect your X account using OAuth 2.0 to allow Blue Wing to post on your behalf.') }}</flux:subheading>

    @if (session('error'))
        <div class="mt-4 rounded-lg bg-danger-50 p-4 text-danger-700">{{ session('error') }}</div>
    @endif

    @if (session('message'))
        <div class="mt-4 rounded-lg bg-success-50 p-4 text-success-700">{{ session('message') }}</div>
    @endif

    <div class="mt-6 max-w-lg space-y-6">
        @if ($isConfigured)
            <div class="rounded-lg border border-zinc-200 p-6 dark:border-zinc-700">
                <div class="space-y-4">
                    <flux:text>
                        {{ __('Clicking the button below will redirect you to X where you can authorize Blue Wing to access your account. We request the minimum permissions needed to post on your behalf.') }}
                    </flux:text>

                    <div class="rounded-lg bg-zinc-50 p-3 text-sm text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400">
                        <p class="font-medium">{{ __('Permissions requested:') }}</p>
                        <ul class="mt-1 list-inside list-disc space-y-0.5">
                            <li>{{ __('Read your tweets') }}</li>
                            <li>{{ __('Post tweets on your behalf') }}</li>
                            <li>{{ __('Read your profile information') }}</li>
                        </ul>
                    </div>

                    <div class="flex items-center gap-4">
                        <a href="{{ route('social-accounts.x-oauth-redirect') }}"
                           class="inline-flex items-center gap-2 rounded-lg bg-zinc-900 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-zinc-800 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-100">
                            <span class="text-base font-bold">𝕏</span>
                            {{ __('Connect with X') }}
                        </a>
                        <flux:button :href="route('social-accounts.index')" wire:navigate>{{ __('Cancel') }}</flux:button>
                    </div>
                </div>
            </div>
        @else
            <div class="rounded-lg border border-warning-300 bg-warning-50 p-6 dark:border-warning-700 dark:bg-warning-900/20">
                <flux:heading size="sm" class="text-warning-800 dark:text-warning-200">{{ __('Configuration Required') }}</flux:heading>
                <flux:text class="mt-2 text-warning-700 dark:text-warning-300">
                    {{ __('X OAuth 2.0 credentials are not configured. Add the following environment variables to your .env file:') }}
                </flux:text>

                <div class="mt-3 rounded bg-zinc-800 p-3 font-mono text-sm text-zinc-100">
                    <div>X_CLIENT_ID=your_client_id</div>
                    <div>X_CLIENT_SECRET=your_client_secret</div>
                </div>

                <flux:text class="mt-3 text-sm text-warning-600 dark:text-warning-400">
                    {{ __('Get these from the') }}
                    <a href="https://developer.x.com/en/portal/dashboard" target="_blank" rel="noopener" class="underline">{{ __('X Developer Portal') }}</a>.
                    {{ __('Set the callback URL to:') }}
                    <code class="rounded bg-zinc-100 px-1.5 py-0.5 text-xs dark:bg-zinc-800">{{ url('social-accounts/connect/x/callback') }}</code>
                </flux:text>
            </div>

            <flux:button :href="route('social-accounts.index')" wire:navigate>{{ __('Back to Social Accounts') }}</flux:button>
        @endif
    </div>
</div>
