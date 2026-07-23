<div class="mb-4 rounded-xl border border-gray-200 p-3 dark:border-gray-700">
    <p class="mb-2 text-sm font-medium text-gray-950 dark:text-white">
        {{ __('admin.media_library.current_image') }}
    </p>

    <div class="flex items-center gap-3">
        @if (filled($previewUrl))
            <img
                src="{{ $previewUrl }}"
                alt="{{ $media->alt ?? '' }}"
                class="h-24 w-24 rounded-lg object-contain"
            />
        @else
            <div class="grid h-24 w-24 place-items-center rounded-lg bg-gray-100 px-2 text-center text-xs text-gray-500 dark:bg-gray-800">
                {{ __('admin.media_library.preview_unavailable') }}
            </div>
        @endif

        <p class="min-w-0 truncate text-sm text-gray-700 dark:text-gray-300">
            {{ $media->title ?: $media->name }}
        </p>
    </div>
</div>
