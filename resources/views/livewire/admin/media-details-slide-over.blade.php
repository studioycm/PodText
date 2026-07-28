<div class="space-y-4" data-testid="media-details-slide-over">
    @if (filled($projection['preview_url']))
        <img
            src="{{ $projection['preview_url'] }}"
            alt=""
            loading="lazy"
            class="mx-auto max-h-96 w-[85%] rounded-lg border border-gray-200 object-contain dark:border-gray-700"
        />
    @else
        <span class="mx-auto grid h-40 w-[85%] place-items-center rounded-lg border border-gray-200 text-gray-400 dark:border-gray-700">—</span>
    @endif
    <div class="flex min-w-0 flex-wrap items-baseline gap-x-3 gap-y-1">
        <p class="min-w-0 truncate text-sm font-semibold text-gray-950 dark:text-white" dir="auto">
            {{ $projection['pretty_name'] }}
        </p>
        <button
            type="button"
            class="text-xs font-medium text-primary-600 hover:text-primary-500 dark:text-primary-400"
            x-data="{ copied: false }"
            x-on:click="navigator.clipboard?.writeText(@js($projection['pretty_name'])); copied = true; setTimeout(() => copied = false, 1500)"
        >
            <span x-show="! copied">{{ __('admin.owner_image.actions.copy_filename') }}</span>
            <span x-cloak x-show="copied">{{ __('admin.owner_image.copy_success') }}</span>
        </button>
    </div>

    <dl class="grid gap-1.5 border-t border-dashed border-gray-200 pt-3 text-xs text-gray-600 dark:border-gray-700 dark:text-gray-300">
        <div class="flex flex-wrap gap-1">
            <dt class="font-medium text-gray-950 dark:text-white">{{ __('admin.owner_image.metadata.mime') }}:</dt>
            <dd dir="ltr">{{ $mime }}</dd>
            <dt class="font-medium text-gray-950 dark:text-white">· {{ __('admin.owner_image.metadata.dimensions') }}:</dt>
            <dd dir="ltr">{{ $projection['width'] }}×{{ $projection['height'] }}</dd>
            <dt class="font-medium text-gray-950 dark:text-white">· {{ __('admin.owner_image.metadata.file_size') }}:</dt>
            <dd dir="ltr">{{ \Illuminate\Support\Number::fileSize((int) $projection['size']) }}</dd>
        </div>
        <div class="flex flex-wrap gap-1">
            <dt class="font-medium text-gray-950 dark:text-white">{{ __('admin.owner_image.metadata.stored_filename') }}:</dt>
            <dd class="break-all" dir="ltr">{{ $storedFilename }}</dd>
        </div>
        <div class="flex flex-wrap gap-1">
            <dt class="font-medium text-gray-950 dark:text-white">{{ __('admin.owner_image.metadata.directory') }}:</dt>
            <dd class="break-all" dir="ltr">{{ filled($directory) ? $directory : __('admin.media_library.root_directory') }}</dd>
        </div>
        <div class="flex flex-wrap gap-1">
            <dt class="font-medium text-gray-950 dark:text-white">{{ __('admin.owner_image.metadata.reference_key') }}:</dt>
            <dd class="break-all font-mono text-[11px]" dir="ltr">{{ $projection['reference_key'] }}</dd>
        </div>
    </dl>

    <div class="border-t border-dashed border-gray-200 pt-3 text-xs dark:border-gray-700">
        <p class="mb-1 font-medium text-gray-950 dark:text-white">
            {{ __('admin.media_library.details_known_usages') }} ({{ count($references) }})
        </p>
        @if ($references === [])
            <p class="text-gray-500 dark:text-gray-400">{{ __('admin.media_library.details_no_usages') }}</p>
        @else
            <ul class="list-inside list-disc space-y-0.5 text-gray-600 dark:text-gray-300">
                @foreach ($references as $reference)
                    <li>
                        @if (filled($reference['url'] ?? null))
                            <a
                                href="{{ $reference['url'] }}"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="font-medium text-primary-600 hover:text-primary-500 dark:text-primary-400"
                            >{{ $reference['label'] }}</a>
                        @else
                            {{ $reference['label'] ?? $reference }}
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    @if ($projection['selectable'])
        <p class="rounded-md bg-success-50 p-2.5 text-xs text-success-800 dark:bg-success-950 dark:text-success-200">
            {{ __('admin.media_library.details_no_warnings') }}
        </p>
    @else
        <p class="rounded-md bg-warning-50 p-2.5 text-xs text-warning-800 dark:bg-warning-950 dark:text-warning-200">
            {{ $projection['selection_blocked_reason'] }}
        </p>
    @endif

    @if (filled($issueReviewUrl ?? null))
        <p>
            <a
                href="{{ $issueReviewUrl }}"
                class="text-sm font-medium text-warning-700 hover:text-warning-600 dark:text-warning-300"
                data-testid="media-details-issue-review-link"
            >
                {{ __('admin.media_issue_review.heading') }} ←
            </a>
        </p>
    @endif

    @if (filled($projection['details_url'] ?? null))
        <p class="border-t border-dashed border-gray-200 pt-3 dark:border-gray-700">
            <a
                href="{{ $projection['details_url'] }}"
                target="_blank"
                rel="noopener noreferrer"
                class="text-sm font-medium text-primary-600 hover:text-primary-500 dark:text-primary-400"
            >
                {{ __('admin.media_library.details_full_page') }} ↗
            </a>
        </p>
    @endif
</div>
