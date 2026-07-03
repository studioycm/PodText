@props([
    'group',
    'url' => null,
])

@php
    $url ??= \App\Filament\Public\Pages\ShowContentGroup::getUrl([
        'contentGroupSlug' => $group->slug,
    ], panel: 'public');
    $coverUrl = $group->cover_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($group->cover_path) : null;
    $initials = str($group->title)->squish()->substr(0, 2)->toString();
@endphp

<a
    href="{{ $url }}"
    {{ $attributes->merge(['class' => 'inline-flex max-w-full items-center gap-2 rounded-md bg-primary-50 px-2 py-1 text-xs font-medium text-primary-700 ring-1 ring-primary-200 dark:bg-primary-950 dark:text-primary-200 dark:ring-primary-800']) }}
    data-test="group-badge"
>
    @if($coverUrl)
        <img
            src="{{ $coverUrl }}"
            alt=""
            class="h-6 w-6 shrink-0 rounded-sm object-cover"
            loading="lazy"
        >
    @else
        <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-sm bg-white text-[11px] font-semibold text-primary-700 dark:bg-primary-900 dark:text-primary-100">
            {{ $initials }}
        </span>
    @endif

    <span class="truncate">{{ $group->title }}</span>
</a>
