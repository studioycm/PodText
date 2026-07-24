@php
    $items = $getSelectedItems();
    $itemsCount = count($items);
    $isMultiple = $isMultiple();
    $maxItems = $getMaxItems();
    $isInlineOwnerWorkspace = $isInlineOwnerWorkspace();
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div class="w-full">
        <ul @class([
            'grid gap-4 sm:grid-cols-2' => $itemsCount > 0 && ! $isInlineOwnerWorkspace,
            'space-y-3' => $itemsCount > 0 && $isInlineOwnerWorkspace,
        ])>
            @foreach ($items as $uuid => $item)
                <li
                    wire:key="{{ $this->getId() }}.{{ $uuid }}.media-item"
                    data-testid="media-picker-selected-item"
                    @if ($isInlineOwnerWorkspace) data-inline-owner-summary="true" @endif
                    @class([
                        'relative overflow-hidden rounded-lg border border-gray-300 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900',
                        'flex min-w-0 items-center gap-3 p-3' => $isInlineOwnerWorkspace,
                    ])
                >
                    @if (filled($item['preview_url'] ?? null))
                        <div
                            @class([
                                'checkered flex items-center justify-center overflow-hidden',
                                'h-20 w-20 shrink-0 rounded-md' => $isInlineOwnerWorkspace,
                                'h-48' => ! $isInlineOwnerWorkspace,
                            ])
                            data-contain-fit="true"
                        >
                            <x-curator::display
                                :item="$item"
                                :src="$item['preview_url']"
                                :alt="$item['alt'] ?? ''"
                                :lazy="true"
                                :constrained="true"
                                class="h-full w-full object-contain"
                            />
                        </div>
                    @else
                        <div @class([
                            'flex items-center justify-center px-4 text-sm text-gray-500',
                            'h-20 w-20 shrink-0 rounded-md' => $isInlineOwnerWorkspace,
                            'h-24' => ! $isInlineOwnerWorkspace,
                        ])>
                            {{ __('admin.media_library.legacy_missing', ['name' => $item['pretty_name']]) }}
                        </div>
                    @endif

                    <div @class([
                        'flex min-w-0 flex-1 items-center gap-2',
                        'p-3' => ! $isInlineOwnerWorkspace,
                    ])>
                        <span class="min-w-0 flex-1 truncate text-sm">{{ $item['pretty_name'] }}</span>

                        @if (filled($item['id'] ?? null))
                            <x-filament-actions::group
                                :actions="[
                                    $getAction('view')(['id' => $item['id']]),
                                    $getAction('edit')(['id' => $item['id']]),
                                    $getAction('download')(['id' => $item['id']]),
                                    $getAction('remove'),
                                ]"
                                color="gray"
                                size="xs"
                            />
                        @else
                            {{ $getAction('remove') }}
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>

        @if (! $isInlineOwnerWorkspace)
            <div @class(['mt-4' => $itemsCount > 0, 'flex items-center gap-3'])>
                @if ((! $maxItems || $itemsCount < $maxItems) || (! $isMultiple && $itemsCount === 1))
                    {{ $getAction('launchPanel') }}
                @endif

                @if ($itemsCount > 1)
                    {{ $getAction('removeAll') }}
                @endif
            </div>
        @endif
    </div>
</x-dynamic-component>
