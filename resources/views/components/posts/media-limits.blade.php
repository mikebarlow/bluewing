<div class="rounded-lg border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-800/50">
    <p class="mb-3 text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ __('Platform Media Limits') }}</p>

    <div class="grid grid-cols-2 gap-x-6 gap-y-2 text-xs text-zinc-600 dark:text-zinc-400">
        <div>
            <p class="mb-1 font-semibold text-zinc-900 dark:text-zinc-200">X</p>
            <p>{{ __('Image') }}: 5 MB</p>
            <p>{{ __('GIF') }}: 15 MB</p>
            <p>{{ __('Video') }}: 512 MB</p>
        </div>
        <div>
            <p class="mb-1 font-semibold text-zinc-900 dark:text-zinc-200">Bluesky</p>
            <p>{{ __('Images') }}: 1 MB {{ __('each') }}</p>
            <p>{{ __('Videos') }}: 100 MB</p>
            <p>{{ __('Per-image alt text supported') }}</p>
        </div>
    </div>

    <p class="mt-3 text-xs text-zinc-500 dark:text-zinc-400">
        {{ __('When cross-posting, the strictest platform limit applies.') }}
    </p>
</div>
