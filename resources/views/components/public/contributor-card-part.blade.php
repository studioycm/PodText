@props([
    'part',
    'presentation',
    'compact' => false,
    'previewMode' => false,
])

@switch($part['type'])
    @case('part_group')
        <x-public.card-part-shell :part="$part" class="{{ $part['class'] }}">
            <div
                class="{{ $part['children_class'] }}"
                data-card-part-group-layout="{{ $part['layout'] }}"
                data-card-part-group-columns="{{ $part['columns'] }}"
                data-card-part-group-gap="{{ $part['gap'] }}"
                data-card-part-group-alignment="{{ $part['alignment'] }}"
            >
                @foreach($part['children'] as $child)
                    <x-public.contributor-card-part :part="$child" :presentation="$presentation" :compact="$compact" :preview-mode="$previewMode" />
                @endforeach
            </div>
        </x-public.card-part-shell>
        @break

    @case('image')
        <x-public.card-part-shell :part="$part" class="{{ $part['class'] }}">
            @if($part['image']['url'])
                <img
                    src="{{ $part['image']['url'] }}"
                    alt=""
                    class="{{ $presentation['avatar'] }} object-cover"
                    loading="lazy"
                    data-test="contributor-image"
                    data-contributor-image-source="{{ $part['image']['source'] }}"
                >
            @else
                <div class="{{ $presentation['avatar'] }}">{{ $part['initial'] }}</div>
            @endif
        </x-public.card-part-shell>
        @break

    @case('title')
        @if($compact)
            <x-public.card-part-shell :part="$part">
                <span class="{{ $part['class'] }}" data-test="contributor-name">
                    {{ $part['text'] }}
                </span>
            </x-public.card-part-shell>
        @else
            <x-public.card-part-shell :part="$part">
                <div @class(['flex items-start gap-3' => $part['show_avatar']])>
                    @if($part['show_avatar'])
                        @if($part['image']['url'])
                            <img
                                src="{{ $part['image']['url'] }}"
                                alt=""
                                class="{{ $presentation['avatar'] }} object-cover"
                                loading="lazy"
                                data-test="contributor-image"
                                data-contributor-image-source="{{ $part['image']['source'] }}"
                            >
                        @else
                            <div class="{{ $presentation['avatar'] }}">{{ $part['initial'] }}</div>
                        @endif
                    @endif

                    <h3 class="{{ $part['class'] }}" data-test="contributor-name">
                        @if($part['url'] && ! $previewMode)
                            <a href="{{ $part['url'] }}">{{ $part['text'] }}</a>
                        @else
                            <span @if($previewMode) aria-description="{{ __('admin.settings_sp3c.preview.link_disabled') }}" @endif>
                                {{ $part['text'] }}
                            </span>
                        @endif
                    </h3>
                </div>
            </x-public.card-part-shell>
        @endif
        @break

    @case('description')
        <x-public.card-part-shell :part="$part">
            <p class="{{ $part['class'] }}" data-test="contributor-bio-preview">
                {{ $part['text'] }}
            </p>
        </x-public.card-part-shell>
        @break

    @case('metadata_row')
        <x-public.card-part-shell :part="$part">
            <div class="{{ $part['class'] }}">
                @foreach($part['badges'] as $badge)
                    @if($compact)
                        <span
                            class="inline-flex shrink-0 items-center gap-1.5 rounded-full bg-primary-50 px-2.5 py-1 text-xs font-medium text-primary-800 dark:bg-primary-950 dark:text-primary-100"
                            title="{{ $badge['title'] ?? $badge['label'] }}"
                            data-test="{{ $badge['test'] }}"
                        >
                            {{ $badge['label'] }}
                        </span>
                    @else
                        <span
                            class="rounded-md bg-gray-100 px-2 py-1 dark:bg-gray-800"
                            title="{{ $badge['title'] ?? $badge['label'] }}"
                            data-test="{{ $badge['test'] }}"
                        >
                            {{ $badge['label'] }}
                        </span>
                    @endif
                @endforeach
            </div>
        </x-public.card-part-shell>
        @break

    @case('entity_attribute')
        <x-public.card-part-shell :part="$part">
            <p class="{{ $part['class'] }}">
                {{ $part['text'] }}
            </p>
        </x-public.card-part-shell>
        @break

    @case('action_link')
        <x-public.card-part-shell :part="$part" class="{{ $part['class'] }}">
            @if($previewMode)
                <span
                    class="inline-flex text-sm font-medium text-primary-700 dark:text-primary-300"
                    data-test="contributor-link"
                    aria-description="{{ __('admin.settings_sp3c.preview.link_disabled') }}"
                >
                    {{ $part['text'] }}
                </span>
            @else
                <a
                    href="{{ $part['url'] }}"
                    @if($part['target']) target="{{ $part['target'] }}" rel="noopener noreferrer" @endif
                    class="inline-flex text-sm font-medium text-primary-700 hover:text-primary-900 dark:text-primary-300 dark:hover:text-primary-100"
                    data-test="contributor-link"
                >
                    {{ $part['text'] }}
                </a>
            @endif
        </x-public.card-part-shell>
        @break

    @case('custom_text')
        <x-public.card-part-shell :part="$part">
            <p class="{{ $part['class'] }}" data-test="card-custom-text">
                {{ $part['text'] }}
            </p>
        </x-public.card-part-shell>
        @break

    @case('divider')
        <x-public.card-part-shell :part="$part">
            <div class="{{ $part['class'] }}"></div>
        </x-public.card-part-shell>
        @break

    @case('spacer')
        <x-public.card-part-shell :part="$part">
            <div class="{{ $part['class'] }}"></div>
        </x-public.card-part-shell>
        @break
@endswitch
