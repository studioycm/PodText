<div class="space-y-3" data-testid="media-relocation-census">
    @if ($moveCount === 0 && $skipped === [])
        <p class="rounded-md bg-success-50 p-2.5 text-xs text-success-800 dark:bg-success-950 dark:text-success-200">
            {{ __('admin.media_library.relocation_none_needed') }}
        </p>
    @else
        <div class="flex flex-wrap gap-2 text-xs font-medium">
            <span class="rounded-full border border-success-500 px-2.5 py-0.5 text-success-700 dark:text-success-300">
                {{ __('admin.media_library.relocation_census_move', ['count' => $moveCount]) }}
            </span>
            @if ($skipped !== [])
                <span class="rounded-full border border-warning-500 px-2.5 py-0.5 text-warning-700 dark:text-warning-300">
                    {{ __('admin.media_library.relocation_census_skip', ['count' => count($skipped)]) }}
                </span>
            @endif
        </div>

        @if ($skipped !== [])
            <ul class="space-y-1 text-xs text-gray-600 dark:text-gray-300">
                @foreach ($skipped as $row)
                    <li class="flex flex-wrap items-baseline gap-x-2">
                        <span class="break-all font-mono text-[11px]" dir="ltr">{{ $row['name'] }}</span>
                        <span class="text-gray-500 dark:text-gray-400">{{ $row['reason'] }}</span>
                    </li>
                @endforeach
            </ul>
        @endif

        <p class="rounded-md bg-warning-50 p-2.5 text-xs text-warning-800 dark:bg-warning-950 dark:text-warning-200">
            {{ __('admin.media_library.relocation_consequence') }}
        </p>
    @endif
</div>
