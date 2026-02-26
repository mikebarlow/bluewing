@props(['post', 'limit' => \App\Livewire\Posts\Calendar::PREVIEW_LIMIT])

@php
    $summary = \App\Livewire\Posts\Calendar::targetsSummary($post, $limit);
    $providerBadge = fn (string $provider) => match ($provider) {
        'x' => 'X',
        'bluesky' => 'BS',
        default => strtoupper(substr($provider, 0, 2)),
    };
    $providerColor = fn (string $provider) => match ($provider) {
        'x' => 'bg-zinc-900 text-white dark:bg-zinc-200 dark:text-zinc-900',
        'bluesky' => 'bg-sky-600 text-white dark:bg-sky-500',
        default => 'bg-zinc-500 text-white',
    };
@endphp

@if ($summary['total'] > 0)
    <div
        x-data="{ showPopover: false }"
        x-on:mouseenter="showPopover = true"
        x-on:mouseleave="showPopover = false"
        x-on:click.stop="showPopover = !showPopover"
        x-on:keydown.escape.window="showPopover = false"
        class="relative mt-0.5 flex flex-wrap items-center gap-0.5"
        role="group"
        aria-label="{{ __('Target accounts') }}"
    >
        @foreach ($summary['preview'] as $target)
            <span
                class="inline-flex max-w-full items-center gap-0.5 rounded px-1 py-px text-[10px] leading-tight {{ $providerColor($target['provider']) }}"
                title="{{ $target['provider_label'] }}: {{ $target['display_name'] }}"
            >
                <span class="font-bold" aria-hidden="true">{{ $providerBadge($target['provider']) }}</span>
                <span class="truncate max-w-[4rem]">{{ Str::limit($target['display_name'], 12) }}</span>
            </span>
        @endforeach

        @if ($summary['overflow'] > 0)
            <span
                class="inline-flex items-center rounded bg-zinc-200 px-1 py-px text-[10px] leading-tight font-medium text-zinc-600 dark:bg-zinc-700 dark:text-zinc-300"
                aria-label="{{ $summary['overflow'] }} {{ __('more accounts') }}"
            >
                +{{ $summary['overflow'] }}
            </span>
        @endif

        {{-- Popover with full target list --}}
        <div
            x-show="showPopover"
            x-cloak
            x-transition.opacity.duration.150ms
            class="absolute top-full left-0 z-50 mt-1 w-52 rounded-lg border border-zinc-200 bg-white p-2 shadow-lg dark:border-zinc-700 dark:bg-zinc-800"
            role="tooltip"
        >
            <p class="mb-1.5 text-[10px] font-semibold uppercase tracking-wide text-zinc-400 dark:text-zinc-500">
                {{ __('Target Accounts') }} ({{ $summary['total'] }})
            </p>
            <ul class="space-y-1">
                @foreach ($post->targets as $target)
                    <li class="flex items-center gap-1.5 text-xs text-zinc-700 dark:text-zinc-300">
                        <span class="inline-flex shrink-0 items-center rounded px-1 py-px text-[10px] font-bold leading-tight {{ $providerColor($target->socialAccount->provider->value) }}">
                            {{ $providerBadge($target->socialAccount->provider->value) }}
                        </span>
                        <span class="truncate" title="{{ $target->socialAccount->display_name }}">
                            {{ $target->socialAccount->display_name }}
                        </span>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
@endif
