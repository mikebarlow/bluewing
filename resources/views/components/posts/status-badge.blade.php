@props(['status'])

@php
    $classes = match ($status) {
        \App\Enums\PostStatus::Draft => 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300',
        \App\Enums\PostStatus::Scheduled => 'bg-primary-100 text-primary-800 dark:bg-primary-900 dark:text-primary-200',
        \App\Enums\PostStatus::Queued => 'bg-warning-100 text-warning-800 dark:bg-warning-900 dark:text-warning-200',
        \App\Enums\PostStatus::Publishing => 'bg-warning-100 text-warning-800 dark:bg-warning-900 dark:text-warning-200',
        \App\Enums\PostStatus::Sent => 'bg-success-100 text-success-800 dark:bg-success-900 dark:text-success-200',
        \App\Enums\PostStatus::Failed => 'bg-danger-100 text-danger-800 dark:bg-danger-900 dark:text-danger-200',
        \App\Enums\PostStatus::Cancelled => 'bg-zinc-100 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400',
        default => 'bg-zinc-100 text-zinc-700',
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {$classes}"]) }}>
    {{ $status->label() }}
</span>
