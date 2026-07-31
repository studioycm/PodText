<div class="space-y-3" data-testid="media-bulk-delete-census">
    <div class="flex flex-wrap gap-2 text-xs font-medium">
        <span class="border-success-500 text-success-700 dark:text-success-300 rounded-full border px-2.5 py-0.5">
            {{ __('admin.media_library.bulk_delete_census_eligible', ['count' => $eligibleCount]) }}
        </span>
        @if ($blocked !== [])
            <span class="border-warning-500 text-warning-700 dark:text-warning-300 rounded-full border px-2.5 py-0.5">
                {{ __('admin.media_library.bulk_delete_census_blocked', ['count' => count($blocked)]) }}
            </span>
        @endif
    </div>

    @if ($blocked !== [])
        <ul class="space-y-1 text-xs text-gray-600 dark:text-gray-300">
            @foreach ($blocked as $row)
                <li class="flex flex-wrap items-baseline gap-x-2">
                    <span class="font-medium" dir="auto">{{ $row['name'] }}</span>
                    <span class="text-gray-500 dark:text-gray-400">{{ $row['reason'] }}</span>
                </li>
            @endforeach
        </ul>
    @endif

    @if ($eligibleCount > 0)
        <p class="bg-danger-50 text-danger-800 dark:bg-danger-950 dark:text-danger-200 rounded-md p-2.5 text-xs">
            {{ __('admin.media_library.bulk_delete_consequence', ['count' => $eligibleCount]) }}
        </p>
    @else
        <p class="bg-warning-50 text-warning-800 dark:bg-warning-950 dark:text-warning-200 rounded-md p-2.5 text-xs">
            {{ __('admin.media_library.bulk_delete_none_eligible') }}
        </p>
    @endif
</div>
