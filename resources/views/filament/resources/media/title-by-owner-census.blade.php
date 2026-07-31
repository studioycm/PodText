<div class="space-y-3" data-testid="media-title-by-owner-census">
    <div class="flex flex-wrap gap-2 text-xs font-medium">
        <span class="border-success-500 text-success-700 dark:text-success-300 rounded-full border px-2.5 py-0.5">
            {{ __('admin.media_library.title_by_owner_census_titled', ['count' => count($titled)]) }}
        </span>
        @if ($skipped !== [])
            <span class="rounded-full border border-gray-400 px-2.5 py-0.5 text-gray-600 dark:text-gray-300">
                {{ __('admin.media_library.title_by_owner_census_skipped', ['count' => count($skipped)]) }}
            </span>
        @endif
    </div>

    @if ($titled !== [])
        <ul class="space-y-1 text-xs text-gray-600 dark:text-gray-300">
            @foreach ($titled as $row)
                <li class="flex flex-wrap items-baseline gap-x-2">
                    <span class="font-mono text-[11px] break-all" dir="ltr">{{ $row['name'] }}</span>
                    <span class="font-medium text-gray-950 dark:text-white" dir="auto">← «{{ $row['title'] }}»</span>
                </li>
            @endforeach
        </ul>
    @endif

    @if ($skipped !== [])
        <ul class="space-y-1 text-xs text-gray-500 dark:text-gray-400">
            @foreach ($skipped as $name)
                <li class="flex flex-wrap items-baseline gap-x-2">
                    <span class="font-mono text-[11px] break-all" dir="ltr">{{ $name }}</span>
                    <span>{{ __('admin.media_library.title_by_owner_none') }}</span>
                </li>
            @endforeach
        </ul>
    @endif

    <p class="bg-warning-50 text-warning-800 dark:bg-warning-950 dark:text-warning-200 rounded-md p-2.5 text-xs">
        {{ __('admin.media_library.title_by_owner_consequence') }}
    </p>
</div>
