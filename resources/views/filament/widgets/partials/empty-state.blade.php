@props([
    'heading' => '',
    'description' => null,
    'icon' => null,
    'testid' => null,
])

{{-- The one dashed empty-state idiom for the custom dashboard widgets: an
     empty widget states what would appear here, inside a quiet dashed panel,
     instead of a bare footnote line. --}}
<div
    class="flex flex-col items-center gap-1 rounded-xl border border-dashed border-gray-300 px-4 py-6 text-center dark:border-white/10"
    @if ($testid) data-testid="{{ $testid }}" @endif
>
    @if ($icon)
        <x-filament::icon :icon="$icon" class="mb-1 size-6 text-gray-400 dark:text-gray-500" />
    @endif

    <p class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ $heading }}</p>

    @if ($description)
        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $description }}</p>
    @endif
</div>
