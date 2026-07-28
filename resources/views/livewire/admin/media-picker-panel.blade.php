<div
    data-testid="media-picker"
    @class([
        'flex h-full flex-col',
        'min-h-[60vh]' => $isInlineOwnerWorkspace,
        'min-h-[70vh]' => ! $isInlineOwnerWorkspace,
    ])
    x-data="{ uploading: false, uploadFocusId: null }"
    x-on:livewire-upload-start="
        const active = document.activeElement;
        uploadFocusId = active !== document.body && $root.contains(active)
            ? (active.id ||= 'media-picker-upload-return-target')
            : null;
        uploading = true
    "
    x-on:livewire-upload-finish="
        uploading = false;
        $nextTick(() => {
            const candidate = uploadFocusId ? document.getElementById(uploadFocusId) : null;
            const target = candidate?.isConnected
                && ! candidate.disabled
                && candidate.getClientRects().length
                    ? candidate
                    : $root.querySelector('[data-testid=media-picker-source-upload]');
            target?.focus();
            uploadFocusId = null;
        })
    "
    x-on:livewire-upload-error="
        uploading = false;
        $nextTick(() => {
            const candidate = uploadFocusId ? document.getElementById(uploadFocusId) : null;
            const target = candidate?.isConnected
                && ! candidate.disabled
                && candidate.getClientRects().length
                    ? candidate
                    : $root.querySelector('[data-testid=media-picker-source-upload]');
            target?.focus();
            uploadFocusId = null;
        })
    "
    x-on:livewire-upload-cancel="
        uploading = false;
        $nextTick(() => {
            const candidate = uploadFocusId ? document.getElementById(uploadFocusId) : null;
            const target = candidate?.isConnected
                && ! candidate.disabled
                && candidate.getClientRects().length
                    ? candidate
                    : $root.querySelector('[data-testid=media-picker-source-upload]');
            target?.focus();
            uploadFocusId = null;
        })
    "
    x-bind:aria-busy="(uploading || returningSelection) ? 'true' : null"
    x-bind:inert="returningSelection"
    x-on:open-media-details.window="$wire.mountAction('mediaDetails', { id: $event.detail.id })"
    wire:loading.attr="aria-busy"
>
    @if (! ($isOwnerChoice && $isInlineOwnerWorkspace))
    <div
        data-testid="media-picker-header"
        class="sticky top-0 z-20 flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 bg-white px-4 py-3 dark:border-gray-800 dark:bg-gray-900"
        x-bind:inert="uploading || returningSelection"
    >
        <div class="flex flex-wrap items-center gap-2">
            @if (! $isOwnerChoice)
            <x-filament::button
                size="xs"
                :color="$allMedia ? 'gray' : 'primary'"
                wire:click="showContextMedia"
                wire:loading.attr="disabled"
                wire:offline.attr="disabled"
            >
                {{ __('admin.media_library.context_media') }}
            </x-filament::button>
            <x-filament::button
                size="xs"
                :color="$allMedia ? 'primary' : 'gray'"
                wire:click="showAllMedia"
                wire:loading.attr="disabled"
                wire:offline.attr="disabled"
            >
                {{ __('admin.media_library.all_media') }}
            </x-filament::button>
            @endif
        </div>

        @if (! $isInlineOwnerWorkspace)
            <x-filament::icon-button
                data-testid="media-picker-close"
                x-on:click="$dispatch('close-media-picker')"
                x-bind:aria-disabled="(uploading || returningSelection) ? 'true' : null"
                x-bind:disabled="uploading || returningSelection"
                icon="heroicon-o-x-mark"
                color="gray"
                :label="__('admin.actions.close')"
            />
        @endif
    </div>
    @endif

    @if ($isOwnerChoice)
        <x-filament::tabs
            :label="__('admin.media_library.source_navigation')"
            contained
            class="mx-4 mt-2 overflow-x-auto"
            data-testid="media-picker-owner-source-navigation"
            wire:loading.attr="inert"
        >
            @foreach (['gallery', 'upload', 'url', 'storage'] as $source)
                <x-filament::tabs.item
                    :active="$activeSource === $source"
                    id="media-picker-source-{{ $source }}"
                    data-testid="media-picker-source-{{ $source }}"
                    wire:click="activateSource('{{ $source }}')"
                    x-bind:disabled="uploading"
                    wire:offline.attr="disabled"
                >
                    {{ __("admin.media_library.{$source}_source") }}
                </x-filament::tabs.item>
            @endforeach
        </x-filament::tabs>
    @endif

    <div
        @class([
            'min-h-0 flex-1',
            'grid lg:grid-cols-[minmax(0,1fr)_22rem]' => ! $isOwnerChoice,
        ])
        wire:offline.attr="inert"
    >
        <main
            @class([
                'overflow-auto p-4',
                'order-2 lg:order-1' => ! $isOwnerChoice,
                'hidden' => $isOwnerChoice && $activeSource !== 'gallery',
            ])
            data-testid="media-picker-gallery"
            x-bind:inert="uploading"
        >
            @unless ($isOwnerChoice)
                <h2 class="mb-3 font-semibold">{{ __('admin.media_library.gallery_source') }}</h2>
            @endunless

            <div
                data-testid="media-picker-gallery-toolbar"
                class="mb-3 flex flex-wrap items-center gap-3"
            >
                <div class="min-w-0 flex-1 sm:max-w-64" wire:loading.attr="inert">
                    <label for="media-picker-gallery-search" class="sr-only">
                        {{ __('admin.media_library.gallery_search_label') }}
                    </label>
                    <x-filament::input.wrapper prefix-icon="heroicon-s-magnifying-glass">
                        <x-filament::input
                            id="media-picker-gallery-search"
                            data-testid="media-picker-gallery-search"
                            type="search"
                            :placeholder="__('admin.media_library.search')"
                            wire:model.live.debounce.400ms="search"
                            wire:loading.attr="disabled"
                            wire:offline.attr="disabled"
                        />
                    </x-filament::input.wrapper>
                </div>
                <div wire:loading.attr="inert">
                    <label for="media-picker-search-scope" class="sr-only">
                        {{ __('admin.media_library.search_scope', ['scope' => __("admin.media_library.search_scopes.{$searchScope}")]) }}
                    </label>
                    <x-filament::input.wrapper>
                        <x-filament::input.select
                            id="media-picker-search-scope"
                            data-testid="media-picker-search-scope"
                            wire:model.live="searchScope"
                        >
                            @foreach (['all', 'title', 'owner', 'filename'] as $scopeOption)
                                <option value="{{ $scopeOption }}">{{ __("admin.media_library.search_scopes.{$scopeOption}") }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </div>
                @if ($isOwnerChoice || $allMedia)
                    <div wire:loading.attr="inert">
                        <label for="media-picker-directory-filter" class="sr-only">
                            {{ __('admin.media_library.directory_filter_label') }}
                        </label>
                        <x-filament::input.wrapper>
                            <x-filament::input.select
                                id="media-picker-directory-filter"
                                data-testid="media-picker-directory-filter"
                                wire:model.live="directoryFilter"
                                wire:loading.attr="disabled"
                                wire:offline.attr="disabled"
                            >
                                <option value="">{{ __('admin.media_library.all_media') }}</option>
                                @foreach ($this->directoryOptions() as $value => $label)
                                    <option value="{{ $value }}" dir="ltr">{{ $label }}</option>
                                @endforeach
                            </x-filament::input.select>
                        </x-filament::input.wrapper>
                    </div>
                @endif
                <span
                    class="text-sm text-gray-500"
                    role="status"
                    aria-live="polite"
                    aria-atomic="true"
                    data-testid="media-picker-selected-count"
                >
                    {{ __('admin.media_library.selected_count', ['count' => count($selectedIds)]) }}
                </span>
                @if ($currentPage > 1 && blank($search))
                    <x-filament::button
                        size="xs"
                        color="gray"
                        wire:click="loadPreviousFiles"
                        wire:loading.attr="disabled"
                        wire:offline.attr="disabled"
                    >
                        {{ __('admin.media_library.previous_page') }}
                    </x-filament::button>
                @endif
                @if ($currentPage < $lastPage && blank($search))
                    <x-filament::button
                        size="xs"
                        color="gray"
                        wire:click="loadMoreFiles"
                        wire:loading.attr="disabled"
                        wire:offline.attr="disabled"
                    >
                        {{ __('admin.media_library.next_page') }}
                    </x-filament::button>
                @endif
                @if ($lastPage > 1 && blank($search))
                    <span class="text-sm text-gray-500">
                        {{ __('admin.media_library.page_count', ['current' => $currentPage, 'last' => $lastPage]) }}
                    </span>
                @endif
            </div>
            <ul @class([
                'grid grid-cols-2 gap-3',
                'sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-5' => $isOwnerChoice,
                'sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-6' => ! $isOwnerChoice,
            ])>
                @forelse ($files as $file)
                    @php
                        $selected = in_array($file['id'], $selectedIds, true);
                        $ownerSelected = $isOwnerChoice && $selected;
                        $selectionDisabled = ! $file['selectable'] || $ownerSelected;
                        $blockedReasonId = "media-picker-blocked-{$file['id']}";
                        $selectionLabel = $ownerSelected
                            ? __('admin.media_library.current_image').': '.$file['pretty_name']
                            : __(
                                $selected
                                    ? 'admin.media_library.deselect_image'
                                    : 'admin.media_library.select_image',
                                ['name' => $file['pretty_name']],
                            );
                    @endphp
                    <li
                        wire:key="media-picker-{{ $file['id'] }}"
                        class="group relative aspect-square overflow-hidden rounded-md border border-gray-300 bg-gray-100 dark:border-gray-700 dark:bg-gray-900"
                    >
                        <button
                            type="button"
                            wire:click="toggleSelection({{ $file['id'] }})"
                            wire:loading.attr="disabled"
                            wire:offline.attr="disabled"
                            aria-label="{{ $selectionLabel }}"
                            aria-pressed="{{ $selected ? 'true' : 'false' }}"
                            @if ($selectionDisabled) aria-disabled="true" @endif
                            @if (! $file['selectable']) aria-describedby="{{ $blockedReasonId }}" @endif
                            @disabled($selectionDisabled)
                            @class([
                                'block h-full w-full focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-inset focus-visible:ring-primary-500',
                                'cursor-not-allowed opacity-70' => ! $file['selectable'],
                            ])
                        >
                            @if (filled($file['preview_url']))
                                <x-curator::display
                                    :item="$file"
                                    :src="$file['preview_url']"
                                    :alt="$file['alt'] ?? ''"
                                    :lazy="true"
                                    class="h-full w-full object-cover"
                                />
                            @else
                                <span class="grid h-full place-items-center px-3 text-center text-xs text-gray-500">
                                    {{ __('admin.media_library.preview_unavailable') }}
                                </span>
                            @endif
                            @if ($selected)
                                <span class="absolute inset-0 grid place-items-center bg-primary-500/20 ring-2 ring-inset ring-primary-500">
                                    <x-filament::icon icon="heroicon-s-check-circle" class="h-9 w-9 text-primary-600" />
                                </span>
                            @endif
                        </button>

                        @if (! $file['selectable'])
                            <div
                                id="{{ $blockedReasonId }}"
                                class="absolute inset-x-1 top-1 rounded bg-warning-50/95 p-1 text-[0.65rem] leading-tight text-warning-800 shadow-sm dark:bg-warning-950/95 dark:text-warning-200"
                            >
                                <p>{{ $file['selection_blocked_reason'] }}</p>
                                @if ($isOwnerChoice)
                                    @if (filled($file['details_url'] ?? null))
                                        <button
                                            type="button"
                                            wire:click="mountAction('mediaDetails', { id: {{ $file['id'] }} })"
                                            class="font-medium underline"
                                            data-testid="media-picker-owner-details-{{ $file['id'] }}"
                                        >
                                            {{ __('admin.media_library.open_details') }}
                                        </button>
                                    @endif
                                @else
                                    <a href="{{ $file['review_url'] }}" class="font-medium underline">
                                        {{ __('admin.media_library.review_media') }}
                                    </a>
                                @endif
                            </div>
                        @endif

                        @if ($isOwnerChoice)
                            @if ($file['selectable'] && filled($file['details_url'] ?? null))
                                <button
                                    type="button"
                                    wire:click="mountAction('mediaDetails', { id: {{ $file['id'] }} })"
                                    class="absolute end-1 top-1 rounded-md bg-white/95 px-2 py-1 text-xs font-medium text-primary-700 shadow-sm dark:bg-gray-900/95 dark:text-primary-300"
                                    data-testid="media-picker-owner-details-{{ $file['id'] }}"
                                >
                                    {{ __('admin.media_library.open_details') }}
                                </button>
                            @endif
                        @else
                            <div class="absolute end-1 top-1 opacity-0 transition group-focus-within:opacity-100 group-hover:opacity-100 pointer-coarse:opacity-100">
                                <x-filament-actions::group
                                    :actions="[
                                        ($this->mediaDetailsAction)(['id' => $file['id']]),
                                        ($this->viewItemAction)(['id' => $file['id']]),
                                        ($this->downloadItemAction)(['id' => $file['id']]),
                                        ($this->editItemAction)(['id' => $file['id']]),
                                        ($this->renameItemAction)(['id' => $file['id']]),
                                        ($this->swapItemAction)(['id' => $file['id']]),
                                        ($this->destroyItemAction)(['id' => $file['id']]),
                                    ]"
                                    color="gray"
                                    size="xs"
                                />
                            </div>
                        @endif

                        <p class="pointer-events-none absolute inset-x-0 bottom-0 truncate bg-gradient-to-t from-black/80 px-2 pb-1 pt-6 text-xs text-white">
                            {{ $file['pretty_name'] }}
                        </p>
                    </li>
                @empty
                    <li class="col-span-full py-12 text-center text-sm text-gray-500">
                        @if (filled($search))
                            <p>{{ __('admin.media_library.empty_search') }}</p>
                            <x-filament::button
                                class="mt-3"
                                size="xs"
                                color="gray"
                                wire:click="$set('search', '')"
                                wire:loading.attr="disabled"
                                wire:offline.attr="disabled"
                            >
                                {{ __('admin.media_library.clear_search') }}
                            </x-filament::button>
                        @elseif ($directoryFilter !== '')
                            <p>{{ __('admin.media_library.empty_directory') }}</p>
                            <x-filament::button
                                class="mt-3"
                                size="xs"
                                color="gray"
                                wire:click="$set('directoryFilter', '')"
                                wire:loading.attr="disabled"
                                wire:offline.attr="disabled"
                            >
                                {{ __('admin.media_library.all_media') }}
                            </x-filament::button>
                        @elseif (! $allMedia)
                            <p>{{ __('admin.media_library.empty_context') }}</p>
                            <x-filament::button
                                class="mt-3"
                                size="xs"
                                color="gray"
                                wire:click="showAllMedia"
                                wire:loading.attr="disabled"
                                wire:offline.attr="disabled"
                            >
                                {{ __('admin.media_library.all_media') }}
                            </x-filament::button>
                        @else
                            <p>{{ __('admin.media_library.empty_library') }}</p>
                            <x-filament::button
                                class="mt-3"
                                size="xs"
                                color="gray"
                                wire:click="activateSource('upload')"
                                wire:loading.attr="disabled"
                                wire:offline.attr="disabled"
                            >
                                {{ __('admin.media_library.upload_source') }}
                            </x-filament::button>
                        @endif
                    </li>
                @endforelse
            </ul>
        </main>

        <aside @class([
            'overflow-auto border-b border-gray-200 p-4 dark:border-gray-800',
            'order-1 lg:order-2 lg:border-b-0 lg:border-s' => ! $isOwnerChoice,
            'hidden' => $isOwnerChoice && $activeSource === 'gallery',
        ])>
            @php
                $permanenceNoteHtml = str_replace(
                    ':link',
                    '<a href="'.e($this->mediaLibraryUrl()).'" target="_blank" rel="noopener noreferrer" class="font-medium underline">'
                        .e(__('admin.media_library.manage_gallery'))
                        .'</a>',
                    e(__($isInlineOwnerWorkspace
                        ? 'admin.media_library.acquisition_permanence_inline'
                        : 'admin.media_library.acquisition_permanence')),
                );
            @endphp

            @if (in_array($activeSource, ['upload', 'url'], true))
                <div class="sticky top-0 z-10 -mx-4 -mt-4 mb-3 flex flex-wrap items-center gap-x-4 gap-y-2 border-b border-gray-200 bg-white px-4 py-2 dark:border-gray-800 dark:bg-gray-900">
                    @if ($activeSource === 'upload')
                        <span
                            data-testid="media-picker-upload-action-guard"
                            x-bind:inert="uploading"
                        >
                            {{ $this->uploadFilesAction }}
                        </span>
                    @else
                        <span
                            data-testid="media-picker-url-action-guard"
                            x-bind:inert="uploading"
                            wire:loading.attr="inert"
                            wire:offline.attr="inert"
                        >
                            {{ $this->acquireUrlAction }}
                        </span>
                    @endif
                    <p class="min-w-48 flex-1 text-xs text-primary-800 dark:text-primary-200">
                        {!! $permanenceNoteHtml !!}
                    </p>
                </div>
            @elseif ($activeSource === 'storage')
                <p class="mb-4 rounded-md bg-primary-50 p-3 text-xs text-primary-800 dark:bg-primary-950 dark:text-primary-200">
                    {!! $permanenceNoteHtml !!}
                </p>
            @endif

            @if (! $isOwnerChoice)
            <x-filament::tabs
                :label="__('admin.media_library.source_navigation')"
                contained
                class="mb-4 overflow-x-auto"
                data-testid="media-picker-source-navigation"
                wire:loading.attr="inert"
            >
                @foreach (['upload', 'url', 'storage'] as $source)
                    <x-filament::tabs.item
                        :active="$activeSource === $source"
                        id="media-picker-source-{{ $source }}"
                        data-testid="media-picker-source-{{ $source }}"
                        wire:click="activateSource('{{ $source }}')"
                        x-bind:disabled="uploading"
                        wire:offline.attr="disabled"
                    >
                        {{ __("admin.media_library.{$source}_source") }}
                    </x-filament::tabs.item>
                @endforeach
            </x-filament::tabs>
            @endif

            <div
                class="min-h-6"
                aria-live="polite"
                aria-atomic="true"
            >
                <p
                    x-cloak
                    x-show="uploading"
                    class="text-xs text-primary-700 dark:text-primary-300"
                >
                    {{ __('admin.media_library.upload_in_progress') }}
                </p>
                <p wire:loading class="text-xs text-primary-700 dark:text-primary-300">
                    {{ __('admin.media_library.working') }}
                </p>
                <p wire:offline class="text-xs text-danger-700 dark:text-danger-300">
                    {{ __('admin.media_library.offline') }}
                </p>
            </div>

            <fieldset
                data-testid="media-picker-source-form"
                wire:offline.attr="disabled"
            >
                <legend class="sr-only">{{ __('admin.media_library.source_navigation') }}</legend>
                {{ $this->form }}
            </fieldset>

            @if ($activeSource === 'upload')
                @error('panelData.uploads')
                    <span
                        data-error-source="upload"
                        class="sr-only"
                        x-init="$nextTick(() => (document.getElementById('media-picker-upload-input') ?? document.getElementById('media-picker-source-upload'))?.focus())"
                    ></span>
                @enderror

                @if ($uploadResults !== [])
                    @php
                        $fateCounts = collect($uploadResults)->countBy('fate');
                    @endphp
                    <div
                        class="mt-3 overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700"
                        data-testid="media-picker-upload-results"
                        role="status"
                        aria-live="polite"
                    >
                        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-200 bg-gray-50 px-3 py-1.5 text-xs font-semibold dark:border-gray-700 dark:bg-gray-800/60">
                            <span>{{ __('admin.media_library.upload_results_heading', ['count' => count($uploadResults)]) }}</span>
                            <span class="font-normal text-gray-500 dark:text-gray-400">
                                {{ __('admin.media_library.upload_results_counts', [
                                    'acquired' => $fateCounts->get('acquired', 0),
                                    'failed' => $fateCounts->get('failed', 0),
                                    'not_attempted' => $fateCounts->get('not_attempted', 0),
                                ]) }}
                            </span>
                        </div>
                        <ul>
                            @foreach ($uploadResults as $row)
                                <li class="flex min-w-0 items-center gap-2 border-b border-dashed border-gray-200 px-3 py-1.5 text-xs last:border-b-0 dark:border-gray-700">
                                    <span @class([
                                        'shrink-0 rounded-full border px-2 text-[11px] font-bold',
                                        'border-success-500 text-success-600 dark:text-success-400' => $row['fate'] === 'acquired',
                                        'border-danger-500 text-danger-600 dark:text-danger-400' => $row['fate'] === 'failed',
                                        'border-gray-400 text-gray-500 dark:text-gray-400' => $row['fate'] === 'not_attempted',
                                    ])>{{ __("admin.media_library.upload_fate_{$row['fate']}") }}</span>
                                    <span class="min-w-0 truncate font-mono text-[11px]" dir="ltr">{{ $row['name'] }}</span>
                                    <span @class([
                                        'ms-auto shrink-0',
                                        'text-danger-600 dark:text-danger-400' => $row['fate'] === 'failed',
                                        'text-gray-500 dark:text-gray-400' => $row['fate'] !== 'failed',
                                    ])>
                                        @if ($row['fate'] === 'acquired')
                                            {{ __('admin.media_library.upload_fate_acquired_note') }}
                                        @elseif ($row['fate'] === 'failed')
                                            {{ __("admin.media_library.upload_reason_{$row['reason']}") }}
                                        @else
                                            {{ __('admin.media_library.upload_fate_queue_note') }}
                                        @endif
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            @elseif ($activeSource === 'url')
                @error('panelData.external_url')
                    <span
                        data-error-source="url"
                        class="sr-only"
                        x-init="$nextTick(() => (document.getElementById('media-picker-url-input') ?? document.getElementById('media-picker-source-url'))?.focus())"
                    ></span>
                @enderror

                <div wire:ignore>
                    <div
                        class="mt-3"
                        data-testid="media-picker-url-preview"
                        x-data="{ src: null, failed: false, w: null, h: null }"
                        x-init="
                            $nextTick(() => {
                                const input = document.getElementById('media-picker-url-input');
                                input?.addEventListener('blur', () => {
                                    const value = input.value.trim();
                                    failed = false;
                                    w = null;
                                    h = null;
                                    src = value.startsWith('https://') ? value : null;
                                });
                            })
                        "
                    >
                        <template x-if="src">
                            <div class="flex min-w-0 items-center gap-3 rounded-lg border border-gray-200 p-2.5 dark:border-gray-700">
                                <img
                                    x-show="! failed"
                                    x-bind:src="src"
                                    x-on:load="w = $el.naturalWidth; h = $el.naturalHeight"
                                    x-on:error="failed = true"
                                    alt=""
                                    class="h-24 w-24 shrink-0 rounded-md border border-gray-200 object-contain dark:border-gray-700"
                                />
                                <div class="grid min-w-0 gap-0.5 text-xs">
                                    <p x-show="! failed" class="text-success-700 dark:text-success-400">
                                        {{ __('admin.media_library.url_preview_loaded') }}
                                    </p>
                                    <p x-show="! failed && w" class="text-gray-500 dark:text-gray-400">
                                        <span dir="ltr" x-text="w + '×' + h"></span> · {{ __('admin.media_library.url_preview_dims_note') }}
                                    </p>
                                    <p x-show="failed" class="text-danger-600 dark:text-danger-400">
                                        {{ __('admin.media_library.url_preview_failed') }}
                                    </p>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            @else
                <section
                    id="media-picker-panel-storage"
                    data-testid="media-picker-panel-storage"
                    class="pt-3"
                >
                    @if (! $storageConfigured)
                        <p class="text-xs text-gray-500">
                            {{ __('admin.media_library.storage_unconfigured') }}
                        </p>
                    @else
                        <fieldset
                            x-bind:disabled="uploading"
                            wire:loading.attr="disabled"
                            wire:offline.attr="disabled"
                        >
                            <legend class="sr-only">{{ __('admin.media_library.storage_source') }}</legend>
                            <div class="flex items-end gap-2">
                                <div class="min-w-0 flex-1">
                                    <label for="media-picker-storage-search" class="sr-only">
                                        {{ __('admin.media_library.storage_search_label') }}
                                    </label>
                                    <x-filament::input.wrapper prefix-icon="heroicon-s-magnifying-glass">
                                        <x-filament::input
                                            id="media-picker-storage-search"
                                            data-testid="media-picker-storage-search"
                                            type="search"
                                            :placeholder="__('admin.media_library.storage_search')"
                                            wire:model.live.debounce.1500ms="storageSearch"
                                            :aria-invalid="$errors->has('storageAcquisition') ? 'true' : 'false'"
                                            aria-describedby="media-picker-storage-error"
                                        />
                                    </x-filament::input.wrapper>
                                </div>
                                <x-filament::button
                                    data-testid="media-picker-storage-refresh"
                                    size="xs"
                                    color="gray"
                                    wire:click="refreshStorageFiles"
                                >
                                    {{ __('admin.media_library.refresh_storage') }}
                                </x-filament::button>
                            </div>

                            <div id="media-picker-storage-error" class="mt-2">
                                @error('storageAcquisition')
                                    <p
                                        data-error-source="storage"
                                        class="text-xs text-danger-600 dark:text-danger-400"
                                        x-init="$nextTick(() => (document.getElementById('media-picker-storage-search') ?? document.getElementById('media-picker-source-storage'))?.focus())"
                                    >
                                        {{ $message }}
                                    </p>
                                @enderror
                            </div>

                            <ul class="mt-3 space-y-2">
                                @forelse ($storageFiles as $candidate)
                                    <li
                                        wire:key="storage-candidate-{{ hash('sha256', $candidate['token']) }}"
                                        class="flex items-center justify-between gap-2 rounded-md border border-gray-200 p-2 text-xs dark:border-gray-700"
                                    >
                                        <span class="min-w-0 flex-1">
                                            <span class="block truncate font-medium" dir="ltr">{{ $candidate['filename'] }}</span>
                                            <span class="block truncate text-gray-500">
                                                {{ $candidate['source'] }}
                                                @if (filled($candidate['ext'] ?? null))
                                                    · <span dir="ltr">{{ $candidate['ext'] }}@if (($candidate['size'] ?? null) !== null) · {{ \Illuminate\Support\Number::fileSize((int) $candidate['size']) }}@endif</span>
                                                @endif
                                            </span>
                                            @if ($storageErrorToken === $candidate['token'] && $errors->has('storageAcquisition'))
                                                <span class="block text-danger-600 dark:text-danger-400" data-testid="media-picker-storage-row-error">
                                                    {{ $errors->first('storageAcquisition') }}
                                                </span>
                                            @endif
                                        </span>
                                        {{ ($this->acquireStorageAction)(['token' => $candidate['token']]) }}
                                    </li>
                                @empty
                                    <li class="text-xs text-gray-500">
                                        @if (filled($storageSearch))
                                            {{ __('admin.media_library.storage_search_empty') }}
                                        @else
                                            {{ __('admin.media_library.storage_empty') }}
                                        @endif
                                    </li>
                                @endforelse
                            </ul>
                        </fieldset>
                    @endif
                </section>
            @endif
        </aside>
    </div>

    @if (! $isOwnerChoice)
    <div
        data-testid="media-picker-footer"
        class="sticky bottom-0 z-20 flex flex-wrap items-center justify-end gap-3 border-t border-gray-200 bg-white px-4 py-3 dark:border-gray-800 dark:bg-gray-900"
        x-bind:inert="uploading"
        wire:loading.attr="inert"
        wire:offline.attr="inert"
    >
        @if (! $isInlineOwnerWorkspace)
            <x-filament::button
                color="gray"
                wire:click="clearSelection"
                x-bind:disabled="uploading"
                wire:loading.attr="disabled"
                wire:offline.attr="disabled"
            >
                {{ __('admin.media_library.clear_selection') }}
            </x-filament::button>
            {{ $this->destroySelectedAction }}
        @endif
        {{ $this->insertMediaAction }}
    </div>
    @endif

    <x-filament-actions::modals />
</div>
