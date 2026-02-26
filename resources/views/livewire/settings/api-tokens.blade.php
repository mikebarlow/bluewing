<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('API Tokens') }}</flux:heading>

    <x-settings.layout :heading="__('API Tokens')" :subheading="__('Create and manage tokens for API access')">

        {{-- Token revealed banner --}}
        @if ($plainTextToken)
            <div class="mb-6 rounded-lg border border-amber-300 bg-amber-50 p-4 dark:border-amber-600 dark:bg-amber-900/30" data-test="token-revealed">
                <div class="mb-2 flex items-center gap-2">
                    <flux:icon.exclamation-triangle class="size-5 text-amber-600 dark:text-amber-400" />
                    <flux:heading size="sm" class="!text-amber-800 dark:!text-amber-200">
                        {{ __('Copy this token now') }}
                    </flux:heading>
                </div>
                <flux:text class="mb-3 !text-amber-700 dark:!text-amber-300">
                    {{ __('You will not be able to see it again. Store it somewhere safe.') }}
                </flux:text>
                <div class="flex items-center gap-2">
                    <code class="block flex-1 overflow-x-auto rounded bg-white px-3 py-2 font-mono text-sm text-zinc-900 dark:bg-zinc-800 dark:text-zinc-100" data-test="plain-text-token">{{ $plainTextToken }}</code>
                </div>
                <div class="mt-3 flex justify-end">
                    <flux:button size="sm" wire:click="dismissToken">{{ __('Done') }}</flux:button>
                </div>
            </div>
        @endif

        {{-- Create token form --}}
        <form wire:submit="createToken" class="mb-6">
            <div class="flex items-end gap-3">
                <div class="flex-1">
                    <flux:input
                        wire:model="tokenName"
                        :label="__('Token name')"
                        :placeholder="__('e.g. CI/CD Pipeline')"
                        required
                    />
                </div>
                <flux:button variant="primary" type="submit">{{ __('Create') }}</flux:button>
            </div>
            @error('tokenName')
                <flux:text class="mt-1 !text-red-600 dark:!text-red-400">{{ $message }}</flux:text>
            @enderror
        </form>

        <flux:separator />

        {{-- Token list --}}
        <div class="mt-6 space-y-4">
            @forelse ($tokens as $token)
                <div
                    class="rounded-lg border p-4 {{ $highlightTokenId === $token->id ? 'border-primary-300 bg-primary-50 dark:border-primary-700 dark:bg-primary-900/20' : 'border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800' }}"
                    data-test="token-card"
                    data-token-id="{{ $token->id }}"
                >
                    <div class="flex items-start justify-between">
                        <div class="min-w-0 flex-1">
                            <flux:heading size="sm">{{ $token->name }}</flux:heading>
                            <div class="mt-1">
                                <code class="font-mono text-sm text-zinc-500 dark:text-zinc-400" data-test="token-prefix">{{ $token->token_prefix }}<span class="tracking-wider">{{ str_repeat('•', 12) }}</span></code>
                            </div>
                            <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-zinc-500 dark:text-zinc-400">
                                <span>{{ __('Created') }} {{ $token->created_at->diffForHumans() }}</span>
                                @if ($token->last_used_at)
                                    <span>{{ __('Last used') }} {{ $token->last_used_at->diffForHumans() }}</span>
                                @else
                                    <span>{{ __('Never used') }}</span>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="mt-3 flex gap-2">
                        <flux:button
                            size="sm"
                            variant="subtle"
                            wire:click="rollToken({{ $token->id }})"
                            wire:confirm="{{ __('This will regenerate the token value. The old token will stop working immediately. Continue?') }}"
                        >
                            {{ __('Roll token') }}
                        </flux:button>
                        <flux:button
                            size="sm"
                            variant="danger"
                            wire:click="deleteToken({{ $token->id }})"
                            wire:confirm="{{ __('Are you sure you want to delete this token? This cannot be undone.') }}"
                        >
                            {{ __('Delete') }}
                        </flux:button>
                    </div>
                </div>
            @empty
                <flux:text class="py-8 text-center">
                    {{ __('No API tokens yet. Create one above to get started.') }}
                </flux:text>
            @endforelse
        </div>
    </x-settings.layout>
</section>
