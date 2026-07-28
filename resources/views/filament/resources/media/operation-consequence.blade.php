<div class="space-y-3" data-testid="media-operation-consequence">
    <div class="flex items-center gap-3">
        @if (filled($projection['preview_url']))
            <img
                src="{{ $projection['preview_url'] }}"
                alt=""
                loading="lazy"
                class="h-14 w-14 flex-shrink-0 rounded-md border border-gray-200 object-cover dark:border-gray-700"
            />
        @else
            <span class="grid h-14 w-14 flex-shrink-0 place-items-center rounded-md border border-gray-200 text-gray-400 dark:border-gray-700">—</span>
        @endif
        <div class="min-w-0">
            <p class="truncate text-sm font-semibold text-gray-950 dark:text-white" dir="auto">{{ $projection['pretty_name'] }}</p>
            <p class="truncate text-xs text-gray-500 dark:text-gray-400" dir="ltr">
                {{ $storedFilename }} · {{ $projection['width'] }}×{{ $projection['height'] }} · {{ \Illuminate\Support\Number::fileSize((int) $projection['size']) }}
            </p>
        </div>
    </div>

    <p class="text-xs text-gray-600 dark:text-gray-300">{{ __('admin.media_library.op_no_usages') }}</p>

    <p @class([
        'rounded-md p-2.5 text-xs',
        'bg-danger-50 text-danger-800 dark:bg-danger-950 dark:text-danger-200' => $operation === 'delete',
        'bg-warning-50 text-warning-800 dark:bg-warning-950 dark:text-warning-200' => $operation !== 'delete',
    ])>
        {{ __("admin.media_library.{$operation}_consequence") }}
    </p>
</div>
