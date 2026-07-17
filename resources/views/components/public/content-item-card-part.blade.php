@props([
    'part',
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
                    <x-public.content-item-card-part :part="$child" :preview-mode="$previewMode" />
                @endforeach
            </div>
        </x-public.card-part-shell>
        @break

    @case('group_identity')
        <x-public.card-part-shell :part="$part" class="{{ $part['class'] }}">
            <x-public.content-group-badge
                :group="$part['group']"
                :mode="$part['mode']"
                :main-image-source="$part['main_image_source']"
                :allow-duplicate-thumbnail="$part['allow_duplicate_thumbnail']"
                :preview-mode="$previewMode"
            />
        </x-public.card-part-shell>
        @break

    @case('title')
        <x-public.card-part-shell :part="$part">
            <h3 class="{{ $part['class'] }}">
                @if($previewMode)
                    <span data-test="content-item-title" aria-description="{{ __('admin.settings_sp3c.preview.link_disabled') }}">
                        {{ $part['text'] }}
                    </span>
                @else
                    <a href="{{ $part['url'] }}" data-test="content-item-title">
                        {{ $part['text'] }}
                    </a>
                @endif
            </h3>
        </x-public.card-part-shell>
        @break

    @case('description')
        <x-public.card-part-shell :part="$part">
            <p class="{{ $part['class'] }}" data-test="item-description">
                {{ $part['text'] }}
            </p>
        </x-public.card-part-shell>
        @break

    @case('transcriber_line')
        <x-public.card-part-shell :part="$part">
            <div class="{{ $part['class'] }}">
                @foreach($part['badges'] as $badge)
                    <span class="rounded-md bg-gray-100 px-2 py-1 dark:bg-gray-800" data-test="item-transcriber">{{ $badge['label'] }}</span>
                @endforeach
            </div>
        </x-public.card-part-shell>
        @break

    @case('date_read_time')
    @case('metadata_row')
        <x-public.card-part-shell :part="$part">
            <div class="{{ $part['class'] }}">
                @foreach($part['badges'] as $badge)
                    <span class="rounded-md bg-gray-100 px-2 py-1 dark:bg-gray-800" data-test="{{ $badge['test'] }}">{{ $badge['label'] }}</span>
                @endforeach
            </div>
        </x-public.card-part-shell>
        @break

    @case('taxonomy')
        <x-public.card-part-shell :part="$part" data-test="{{ $part['test'] }}">
            <div class="{{ $part['class'] }}">
                @foreach($part['links'] as $link)
                    @if($previewMode)
                        <span class="{{ $part['link_class'] }}" aria-description="{{ __('admin.settings_sp3c.preview.link_disabled') }}">
                            {{ $link['label'] }}
                        </span>
                    @else
                        <a href="{{ $link['url'] }}" class="{{ $part['link_class'] }}">
                            {{ $link['label'] }}
                        </a>
                    @endif
                @endforeach
            </div>
        </x-public.card-part-shell>
        @break

    @case('action_link')
        <x-public.card-part-shell :part="$part" class="{{ $part['class'] }}">
            @if($previewMode)
                <span
                    class="inline-flex text-sm font-medium text-primary-700 dark:text-primary-300"
                    data-test="content-item-action-link"
                    aria-description="{{ __('admin.settings_sp3c.preview.link_disabled') }}"
                >
                    {{ $part['text'] }}
                </span>
            @else
                <a
                    href="{{ $part['url'] }}"
                    @if($part['target']) target="{{ $part['target'] }}" rel="noopener noreferrer" @endif
                    class="inline-flex text-sm font-medium text-primary-700 hover:text-primary-900 dark:text-primary-300 dark:hover:text-primary-100"
                    data-test="content-item-action-link"
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
