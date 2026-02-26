@props(['mediaItems', 'hasBlueskyTarget' => false])

<div>
    <flux:heading size="sm" class="mb-1">{{ __('Media') }}</flux:heading>
    <flux:subheading class="mb-3">{{ __('Attach images (max 4) or a single video. Drag & drop or click to browse.') }}</flux:subheading>

    {{-- Drop zone --}}
    <div
        x-data="{ dragging: false }"
        x-on:dragover.prevent="dragging = true"
        x-on:dragleave.prevent="dragging = false"
        x-on:drop.prevent="
            dragging = false;
            $refs.fileInput.files = $event.dataTransfer.files;
            $refs.fileInput.dispatchEvent(new Event('change', { bubbles: true }));
        "
        :class="dragging ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/20' : 'border-zinc-300 dark:border-zinc-600'"
        class="relative flex flex-col items-center justify-center rounded-lg border-2 border-dashed p-6 transition"
    >
        <div class="pointer-events-none text-center">
            <svg class="mx-auto mb-2 size-8 text-zinc-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
            </svg>
            <p class="text-sm text-zinc-600 dark:text-zinc-400">
                {{ __('Drop files here or') }}
                <span class="font-medium text-primary-600 dark:text-primary-400">{{ __('browse') }}</span>
            </p>
            <p class="mt-1 text-xs text-zinc-500">{{ __('JPG, PNG, GIF, WebP, MP4, MOV, AVI, WebM') }}</p>
        </div>

        <input
            x-ref="fileInput"
            type="file"
            wire:model="uploads"
            multiple
            accept="image/jpeg,image/png,image/gif,image/webp,video/mp4,video/quicktime,video/x-msvideo,video/webm"
            class="absolute inset-0 cursor-pointer opacity-0"
        />
    </div>

    {{-- Upload progress --}}
    <div wire:loading wire:target="uploads" class="mt-3">
        <div class="flex items-center gap-2 text-sm text-zinc-500">
            <svg class="size-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
            {{ __('Uploading...') }}
        </div>
    </div>

    @error('uploads.*')
        <flux:text class="mt-2 text-danger-600">{{ $message }}</flux:text>
    @enderror

    @error('media')
        <flux:text class="mt-2 text-danger-600">{{ $message }}</flux:text>
    @enderror

    {{-- Media previews --}}
    @if ($mediaItems->isNotEmpty())
        <div class="mt-4 space-y-3">
            @foreach ($mediaItems as $media)
                <div class="flex gap-3 rounded-lg border border-zinc-200 p-3 dark:border-zinc-700" wire:key="media-{{ $media->id }}">
                    {{-- Thumbnail --}}
                    <div class="flex-shrink-0">
                        @if ($media->type->isImage())
                            <img
                                src="{{ $media->url() }}"
                                alt="{{ $media->alt_text ?? $media->original_filename }}"
                                class="size-20 rounded object-cover"
                            />
                        @else
                            <div class="flex size-20 items-center justify-center rounded bg-zinc-100 dark:bg-zinc-800">
                                <svg class="size-8 text-zinc-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m15.75 10.5 4.72-4.72a.75.75 0 0 1 1.28.53v11.38a.75.75 0 0 1-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 0 0 2.25-2.25v-9a2.25 2.25 0 0 0-2.25-2.25h-9A2.25 2.25 0 0 0 2.25 7.5v9a2.25 2.25 0 0 0 2.25 2.25Z" />
                                </svg>
                            </div>
                        @endif
                    </div>

                    {{-- Details --}}
                    <div class="min-w-0 flex-1">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $media->original_filename }}</p>
                                <p class="text-xs text-zinc-500">
                                    {{ $media->type->label() }} &middot; {{ number_format($media->size_bytes / 1024, 0) }} KB
                                </p>
                            </div>
                            <button
                                type="button"
                                wire:click="removeMedia({{ $media->id }})"
                                class="flex-shrink-0 text-zinc-400 transition hover:text-danger-500"
                                title="{{ __('Remove') }}"
                            >
                                <svg class="size-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        {{-- Alt text --}}
                        <div class="mt-2">
                            <input
                                type="text"
                                wire:model.blur="alt_texts.{{ $media->id }}"
                                placeholder="{{ $hasBlueskyTarget ? __('Alt text (required for Bluesky)') : __('Alt text (optional)') }}"
                                class="w-full rounded border border-zinc-300 px-2.5 py-1.5 text-sm placeholder-zinc-400 focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-100 dark:placeholder-zinc-500"
                            />
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
