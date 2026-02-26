<div>
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">{{ __('Calendar') }}</flux:heading>
            <flux:subheading>{{ __('View your scheduled posts by date.') }}</flux:subheading>
        </div>
        <flux:button variant="primary" :href="route('posts.create')" wire:navigate>
            {{ __('Create Post') }}
        </flux:button>
    </div>

    {{-- Month Navigation --}}
    <div class="mt-6 flex items-center justify-between">
        <flux:button size="sm" wire:click="previousMonth">← {{ __('Previous') }}</flux:button>
        <div class="flex items-center gap-3">
            <flux:heading size="lg">{{ $this->monthLabel }}</flux:heading>
            <flux:button size="sm" wire:click="goToToday">{{ __('Today') }}</flux:button>
        </div>
        <flux:button size="sm" wire:click="nextMonth">{{ __('Next') }} →</flux:button>
    </div>

    {{-- Filters --}}
    <div class="mt-4 flex flex-wrap items-end gap-3">
        <div class="w-40">
            <flux:select wire:model.live="filterProvider" size="sm">
                <option value="">{{ __('All Providers') }}</option>
                @foreach ($this->providers as $provider)
                    <option value="{{ $provider->value }}">{{ $provider->label() }}</option>
                @endforeach
            </flux:select>
        </div>

        <div class="w-52">
            <flux:select wire:model.live="filterAccount" size="sm">
                <option value="">{{ __('All Accounts') }}</option>
                @foreach ($this->accessibleAccounts as $account)
                    <option value="{{ $account->id }}">{{ $account->display_name }} ({{ $account->provider->label() }})</option>
                @endforeach
            </flux:select>
        </div>

        @if ($this->hasActiveFilters)
            <flux:button size="sm" variant="subtle" wire:click="clearFilters">
                {{ __('Clear filters') }}
            </flux:button>
        @endif
    </div>

    {{-- Calendar Grid --}}
    <div class="mt-4 overflow-hidden rounded-lg border border-zinc-200 dark:border-zinc-700">
        {{-- Day Headers --}}
        <div class="grid grid-cols-7 border-b border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            @foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $day)
                <div class="px-2 py-2 text-center text-xs font-medium text-zinc-500">{{ $day }}</div>
            @endforeach
        </div>

        {{-- Weeks --}}
        @foreach ($this->calendarWeeks as $week)
            <div class="grid grid-cols-7 border-b border-zinc-200 last:border-b-0 dark:border-zinc-700">
                @foreach ($week as $day)
                    <div class="min-h-[100px] border-r border-zinc-200 p-1.5 last:border-r-0 dark:border-zinc-700
                        {{ ! $day['isCurrentMonth'] ? 'bg-zinc-50/50 dark:bg-zinc-900/50' : '' }}
                        {{ $day['isToday'] ? 'bg-primary-50 dark:bg-primary-900/20' : '' }}
                    ">
                        <div class="mb-1 text-xs font-medium {{ $day['isCurrentMonth'] ? 'text-zinc-700 dark:text-zinc-300' : 'text-zinc-400 dark:text-zinc-600' }} {{ $day['isToday'] ? 'text-primary-600 dark:text-primary-400' : '' }}">
                            {{ $day['date']->day }}
                        </div>
                        <div class="space-y-0.5">
                            @foreach ($day['posts']->take(3) as $post)
                                <div class="rounded px-1 py-0.5 text-xs transition hover:opacity-80
                                    @if ($post->status === \App\Enums\PostStatus::Sent)
                                        bg-success-100 text-success-800 dark:bg-success-900 dark:text-success-200
                                    @elseif ($post->status === \App\Enums\PostStatus::Failed)
                                        bg-danger-100 text-danger-800 dark:bg-danger-900 dark:text-danger-200
                                    @elseif ($post->status === \App\Enums\PostStatus::Scheduled)
                                        bg-primary-100 text-primary-800 dark:bg-primary-900 dark:text-primary-200
                                    @else
                                        bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300
                                    @endif
                                ">
                                    <a
                                        href="{{ route('posts.edit', $post) }}"
                                        wire:navigate
                                        class="block truncate"
                                        title="{{ $post->scheduled_for->format('g:i A') }}"
                                    >
                                        {{ $post->scheduled_for->format('g:ia') }}
                                        {{ Str::limit($post->variants->where('scope_type', \App\Enums\ScopeType::Default)->first()?->body_text ?? '', 20) }}
                                    </a>
                                    <x-posts.target-chips :post="$post" />
                                </div>
                            @endforeach
                            @if ($day['posts']->count() > 3)
                                <div class="px-1 text-xs text-zinc-500">+{{ $day['posts']->count() - 3 }} more</div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endforeach
    </div>
</div>
