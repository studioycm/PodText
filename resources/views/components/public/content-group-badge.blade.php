@props([
    'group',
    'url' => null,
    'mode' => 'name_only',
    'mainImageSource' => 'fallback',
    'allowDuplicateThumbnail' => false,
])

@php
    $url ??= \App\Filament\Public\Pages\ShowContentGroup::getUrl([
        'contentGroupSlug' => $group->slug,
    ], panel: 'public');
    $coverUrl = $group->cover_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($group->cover_path) : null;
    $showThumbnail = $mode === 'thumbnail_name'
        && $coverUrl
        && ($mainImageSource !== 'group' || $allowDuplicateThumbnail);
@endphp

<a
    href="{{ $url }}"
    {{ $attributes->merge(['class' => 'inline-flex max-w-full items-center gap-2 rounded-md bg-transparent px-0 py-0 text-sm font-medium text-primary-700 hover:text-primary-900 dark:text-primary-300 dark:hover:text-primary-100']) }}
    data-test="group-badge"
    data-group-badge-mode="{{ $mode }}"
    data-group-badge-thumbnail="{{ $showThumbnail ? 'true' : 'false' }}"
>
    @if($showThumbnail)
        <img
            src="{{ $coverUrl }}"
            alt="{{ $group->cover_alt_text ?: $group->title }}"
            class="h-6 w-6 shrink-0 rounded-sm object-cover"
            loading="lazy"
        >
    @endif

    <span class="truncate">{{ $group->title }}</span>
</a>
