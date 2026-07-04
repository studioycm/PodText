<div class="space-y-4" data-test="contributor-content-items">
    <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h2 class="text-xl font-semibold tracking-normal text-gray-950 dark:text-white">
                {{ __('public.pages.contributor.items_heading') }}
            </h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-300" data-test="contributor-items-count">
                {{ trans_choice('public.results.count', $items->total(), ['count' => $items->total()]) }}
            </p>
        </div>
    </div>

    @if($items->isNotEmpty())
        <x-public.content-item-grid
            :items="$items"
            :card-options="$cardOptions"
            :card-template="$cardTemplate"
            layout="cards"
        />

        @if($items->hasPages())
            <div data-test="contributor-items-pagination">
                {{ $items->links() }}
            </div>
        @endif
    @else
        <div class="rounded-lg border border-dashed border-gray-300 bg-white p-6 text-sm text-gray-600 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300" data-test="empty-contributor-items">
            {{ __('public.empty.contributor_items') }}
        </div>
    @endif
</div>
