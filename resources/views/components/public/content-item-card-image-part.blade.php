@props([
    'part',
    'presentation',
    'previewMode' => false,
])

<a
    @unless($previewMode) href="{{ $part['url'] }}" @endunless
    class="block min-w-0 overflow-hidden bg-gray-100 dark:bg-gray-800 {{ $presentation['image'] }} {{ $part['image']['radius_class'] }}"
    aria-label="{{ $part['title'] }}"
    @if($previewMode) aria-description="{{ __('admin.settings_sp3c.preview.link_disabled') }}" @endif
    data-test="content-item-image"
    data-card-part="{{ $part['type'] }}"
    data-card-part-source="{{ $part['source'] }}"
    data-card-part-attribute="{{ $part['attribute'] }}"
    data-card-part-order="{{ $part['order'] }}"
    data-card-image-source="{{ $part['image']['source'] }}"
>
    @if($part['image']['url'])
        <img
            src="{{ $part['image']['url'] }}"
            alt="{{ $part['image']['alt'] ?? '' }}"
            class="h-full w-full {{ $part['image']['fit_class'] }}"
            loading="lazy"
        >
    @else
        <div class="flex h-full min-h-24 w-full items-center justify-center bg-gray-100 text-sm font-medium text-gray-500 dark:bg-gray-800 dark:text-gray-400">
            {{ $part['type_label'] }}
        </div>
    @endif
</a>
