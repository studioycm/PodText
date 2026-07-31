@props([
    'flow' => false,
])

{{-- Rule 3: every widget declares whether the range moves it. --}}
<span
    class="rounded-full border border-gray-300 px-2 py-0.5 text-xs font-medium text-gray-500 dark:border-white/10 dark:text-gray-400"
    data-testid="widget-tag-{{ $flow ? 'flow' : 'stock' }}"
>
    {{ $flow ? __('admin.dashboard.tags.flow') : __('admin.dashboard.tags.stock') }}
</span>
