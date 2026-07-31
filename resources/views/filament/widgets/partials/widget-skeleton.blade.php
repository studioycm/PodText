@props([
    'rows' => 3,
])

{{-- Plain markup on purpose: a Livewire placeholder renders outside the widget
     component, so Filament's widget/section wrappers have no context here. --}}
<div
    class="fi-section animate-pulse rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10"
    aria-busy="true"
    aria-live="polite"
    data-testid="widget-skeleton"
    {{-- Every board widget is full width once hydrated; matching that here
         keeps the layout from jumping when the real widget arrives. --}}
    style="grid-column: 1 / -1"
>
    <span class="fi-sr-only">{{ __('admin.dashboard.loading') }}</span>

    <div class="flex items-center justify-between gap-3">
        <div class="h-4 w-40 rounded bg-gray-200 dark:bg-white/10"></div>
        <div class="h-5 w-20 rounded-full bg-gray-100 dark:bg-white/5"></div>
    </div>

    <div class="mt-4 space-y-2.5">
        @for ($row = 0; $row < $rows; $row++)
            <div class="flex items-center gap-3">
                <div class="h-2.5 w-32 shrink-0 rounded bg-gray-200 dark:bg-white/10"></div>
                <div
                    class="h-2.5 flex-1 rounded bg-gray-100 dark:bg-white/5"
                    style="max-width: {{ [90, 70, 80, 55, 65][$row % 5] }}%"
                ></div>
            </div>
        @endfor
    </div>
</div>
