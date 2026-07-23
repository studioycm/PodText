<div
    data-testid="media-picker"
    class="flex h-full min-h-[70vh] flex-col"
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
    wire:loading.attr="aria-busy"
>
    <div
        data-testid="media-picker-header"
        class="sticky top-0 z-20 flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 bg-white px-4 py-3 dark:border-gray-800 dark:bg-gray-900"
        x-bind:inert="uploading || returningSelection"
    >
        <div class="flex flex-wrap items-center gap-2">
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
            <span
                class="text-sm text-gray-500"
                role="status"
                aria-live="polite"
                aria-atomic="true"
                data-testid="media-picker-selected-count"
            >
                {{ __('admin.media_library.selected_count', ['count' => count($selectedIds)]) }}
            </span>
        </div>

        <div
            class="flex min-w-0 basis-full flex-1 items-center justify-end gap-3 sm:basis-auto sm:flex-initial"
            wire:loading.attr="inert"
        >
            <div class="min-w-0 flex-1 sm:w-64">
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
            <x-filament::icon-button
                data-testid="media-picker-close"
                x-on:click="$dispatch('close-media-picker')"
                x-bind:aria-disabled="(uploading || returningSelection) ? 'true' : null"
                x-bind:disabled="uploading || returningSelection"
                icon="heroicon-o-x-mark"
                color="gray"
                :label="__('admin.actions.close')"
            />
        </div>
    </div>

    <div
        class="grid min-h-0 flex-1 lg:grid-cols-[minmax(0,1fr)_22rem]"
        wire:offline.attr="inert"
    >
        <main
            class="overflow-auto p-4"
            data-testid="media-picker-gallery"
            x-bind:inert="uploading"
        >
            <h2 class="mb-3 font-semibold">{{ __('admin.media_library.gallery_source') }}</h2>
            <ul class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-6">
                @forelse ($files as $file)
                    @php
                        $selected = in_array($file['id'], $selectedIds, true);
                        $blockedReasonId = "media-picker-blocked-{$file['id']}";
                        $selectionLabel = __(
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
                            @if (! $file['selectable']) aria-describedby="{{ $blockedReasonId }}" @endif
                            @disabled(! $file['selectable'])
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
                                <a href="{{ $file['review_url'] }}" class="font-medium underline">
                                    {{ __('admin.media_library.review_media') }}
                                </a>
                            </div>
                        @endif

                        <div class="absolute end-1 top-1 opacity-0 transition group-focus-within:opacity-100 group-hover:opacity-100 pointer-coarse:opacity-100">
                            <x-filament-actions::group
                                :actions="[
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

        <aside class="overflow-auto border-t border-gray-200 p-4 dark:border-gray-800 lg:border-s lg:border-t-0">
            <p class="mb-4 rounded-md bg-primary-50 p-3 text-xs text-primary-800 dark:bg-primary-950 dark:text-primary-200">
                {{ __('admin.media_library.acquisition_permanence') }}
            </p>

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
                <div
                    data-testid="media-picker-upload-action-guard"
                    class="mt-3"
                    x-bind:inert="uploading"
                >
                    {{ $this->uploadFilesAction }}
                </div>
            @elseif ($activeSource === 'url')
                @error('panelData.external_url')
                    <span
                        data-error-source="url"
                        class="sr-only"
                        x-init="$nextTick(() => (document.getElementById('media-picker-url-input') ?? document.getElementById('media-picker-source-url'))?.focus())"
                    ></span>
                @enderror
                <div
                    class="mt-3"
                    x-bind:inert="uploading"
                    wire:loading.attr="inert"
                    wire:offline.attr="inert"
                >
                    {{ $this->acquireUrlAction }}
                </div>
            @else
                @php($visibleStorageFiles = $this->filteredStorageFiles())
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
                                            wire:model.live.debounce.300ms="storageSearch"
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
                                @forelse ($visibleStorageFiles as $candidate)
                                    <li
                                        wire:key="storage-candidate-{{ hash('sha256', $candidate['token']) }}"
                                        class="flex items-center justify-between gap-2 rounded-md border border-gray-200 p-2 text-xs dark:border-gray-700"
                                    >
                                        <span class="min-w-0">
                                            <span class="block truncate font-medium">{{ $candidate['filename'] }}</span>
                                            <span class="block truncate text-gray-500">{{ $candidate['source'] }}</span>
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

    <div
        data-testid="media-picker-footer"
        class="sticky bottom-0 z-20 flex flex-wrap items-center justify-end gap-3 border-t border-gray-200 bg-white px-4 py-3 dark:border-gray-800 dark:bg-gray-900"
        x-bind:inert="uploading"
        wire:loading.attr="inert"
        wire:offline.attr="inert"
    >
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
        {{ $this->insertMediaAction }}
    </div>

    <x-filament-actions::modals />
</div>
