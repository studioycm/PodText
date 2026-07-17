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
                    <x-public.content-group-card-part :part="$child" :preview-mode="$previewMode" />
                @endforeach
            </div>
        </x-public.card-part-shell>
        @break

    @case('entity_attribute')
        <x-public.card-part-shell :part="$part" data-test="{{ $part['test'] }}">
            <div class="{{ $part['class'] }}">
                @if($part['test'] === 'content-group-type-label')
                    <x-public.type-label :label="$part['text']" />
                @else
                    <span class="rounded-md bg-gray-100 px-2 py-1 text-xs text-gray-700 dark:bg-gray-800 dark:text-gray-200">
                        {{ $part['text'] }}
                    </span>
                @endif
            </div>
        </x-public.card-part-shell>
        @break

    @case('title')
        <x-public.card-part-shell :part="$part">
            <h2 class="{{ $part['class'] }}">
                @if($previewMode)
                    <span data-test="content-group-title" aria-description="{{ __('admin.settings_sp3c.preview.link_disabled') }}">
                        {{ $part['text'] }}
                    </span>
                @else
                    <a href="{{ $part['url'] }}" data-test="content-group-title">
                        {{ $part['text'] }}
                    </a>
                @endif
            </h2>
        </x-public.card-part-shell>
        @break

    @case('description')
        <x-public.card-part-shell :part="$part">
            <p class="{{ $part['class'] }}">
                {{ $part['text'] }}
            </p>
        </x-public.card-part-shell>
        @break

    @case('metadata_row')
        <x-public.card-part-shell :part="$part">
            <div class="{{ $part['class'] }}">
                @foreach($part['badges'] as $badge)
                    <span data-test="{{ $badge['test'] }}">{{ $badge['label'] }}</span>
                @endforeach
            </div>
        </x-public.card-part-shell>
        @break

    @case('taxonomy')
        <x-public.card-part-shell :part="$part" data-test="{{ $part['test'] }}">
            <div class="{{ $part['class'] }}">
                @foreach($part['links'] as $link)
                    @if($previewMode)
                        <span class="rounded-md border border-gray-200 px-2 py-1 text-xs text-gray-600 dark:border-gray-700 dark:text-gray-300" aria-description="{{ __('admin.settings_sp3c.preview.link_disabled') }}">
                            {{ $link['label'] }}
                        </span>
                    @else
                        <a href="{{ $link['url'] }}" class="rounded-md border border-gray-200 px-2 py-1 text-xs text-gray-600 hover:border-primary-300 hover:text-primary-700 dark:border-gray-700 dark:text-gray-300">
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
                    data-test="content-group-action-link"
                    aria-description="{{ __('admin.settings_sp3c.preview.link_disabled') }}"
                >
                    {{ $part['text'] }}
                </span>
            @else
                <a
                    href="{{ $part['url'] }}"
                    @if($part['target']) target="{{ $part['target'] }}" rel="noopener noreferrer" @endif
                    class="inline-flex text-sm font-medium text-primary-700 hover:text-primary-900 dark:text-primary-300 dark:hover:text-primary-100"
                    data-test="content-group-action-link"
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
