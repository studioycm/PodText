<div class="flex h-full min-h-[70vh] flex-col">
    <div class="flex items-center justify-between gap-4 border-b border-gray-200 px-4 py-3 dark:border-gray-800">
        <div class="flex items-center gap-2">
            <x-filament::button size="xs" :color="$allMedia ? 'gray' : 'primary'" wire:click="showContextMedia">
                {{ __('admin.media_library.context_media') }}
            </x-filament::button>
            <x-filament::button size="xs" :color="$allMedia ? 'primary' : 'gray'" wire:click="showAllMedia">
                {{ __('admin.media_library.all_media') }}
            </x-filament::button>
            @if ($currentPage > 1 && blank($search))
                <x-filament::button size="xs" color="gray" wire:click="loadPreviousFiles">
                    {{ __('admin.media_library.previous_page') }}
                </x-filament::button>
            @endif
            @if ($currentPage < $lastPage && blank($search))
                <x-filament::button size="xs" color="gray" wire:click="loadMoreFiles">
                    {{ __('admin.media_library.next_page') }}
                </x-filament::button>
            @endif
            @if ($lastPage > 1 && blank($search))
                <span class="text-sm text-gray-500">{{ __('admin.media_library.page_count', ['current' => $currentPage, 'last' => $lastPage]) }}</span>
            @endif
            <span class="text-sm text-gray-500">{{ __('admin.media_library.selected_count', ['count' => count($selectedIds)]) }}</span>
        </div>

        <div class="flex items-center gap-3">
            <x-filament::input.wrapper prefix-icon="heroicon-s-magnifying-glass">
                <x-filament::input
                    type="search"
                    :placeholder="__('admin.media_library.search')"
                    wire:model.live.debounce.400ms="search"
                />
            </x-filament::input.wrapper>
            <x-filament::icon-button x-on:click="close()" icon="heroicon-o-x-mark" color="gray" :label="__('admin.actions.close')" />
        </div>
    </div>

    <div class="grid min-h-0 flex-1 lg:grid-cols-[minmax(0,1fr)_20rem]">
        <div class="overflow-auto p-4">
            <h2 class="mb-3 font-semibold">{{ __('admin.media_library.gallery_source') }}</h2>
            <ul class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-6">
                @forelse ($files as $file)
                    <li wire:key="media-picker-{{ $file['id'] }}" class="group relative aspect-square overflow-hidden rounded-md border border-gray-300 bg-gray-100 dark:border-gray-700 dark:bg-gray-900">
                        <button
                            type="button"
                            wire:click="toggleSelection({{ $file['id'] }})"
                            @disabled(! $file['selectable'])
                            @class(['block h-full w-full', 'cursor-not-allowed opacity-70' => ! $file['selectable']])
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
                            @if (in_array($file['id'], $selectedIds, true))
                                <span class="absolute inset-0 grid place-items-center bg-primary-500/20 ring-2 ring-inset ring-primary-500">
                                    <x-filament::icon icon="heroicon-s-check-circle" class="h-9 w-9 text-primary-600" />
                                </span>
                            @endif
                        </button>

                        @if (! $file['selectable'])
                            <div class="absolute inset-x-1 top-1 rounded bg-warning-50/95 p-1 text-[0.65rem] leading-tight text-warning-800 shadow-sm dark:bg-warning-950/95 dark:text-warning-200">
                                <p>{{ $file['selection_blocked_reason'] }}</p>
                                <a href="{{ $file['review_url'] }}" class="font-medium underline">
                                    {{ __('admin.media_library.review_media') }}
                                </a>
                            </div>
                        @endif

                        <div class="absolute end-1 top-1 opacity-0 transition group-focus-within:opacity-100 group-hover:opacity-100">
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
                        {{ __('admin.media_library.empty') }}
                    </li>
                @endforelse
            </ul>
        </div>

        <aside class="overflow-auto border-t border-gray-200 p-4 dark:border-gray-800 lg:border-s lg:border-t-0">
            <p class="mb-4 rounded-md bg-primary-50 p-3 text-xs text-primary-800 dark:bg-primary-950 dark:text-primary-200">
                {{ __('admin.media_library.acquisition_permanence') }}
            </p>

            <section class="border-b border-gray-200 pb-4 dark:border-gray-800">
                {{ $this->form }}
                <div class="mt-3 flex flex-wrap gap-2">
                    {{ $this->uploadFilesAction }}
                    {{ $this->acquireUrlAction }}
                </div>
            </section>

            <section class="pt-4">
                <h3 class="mb-2 font-semibold">{{ __('admin.media_library.storage_source') }}</h3>
                <x-filament::input.wrapper prefix-icon="heroicon-s-magnifying-glass">
                    <x-filament::input
                        type="search"
                        :placeholder="__('admin.media_library.storage_search')"
                        wire:model.live.debounce.400ms="storageSearch"
                    />
                </x-filament::input.wrapper>
                <ul class="mt-3 space-y-2">
                    @forelse ($storageFiles as $candidate)
                        <li wire:key="storage-candidate-{{ hash('sha256', $candidate['token']) }}" class="flex items-center justify-between gap-2 rounded-md border border-gray-200 p-2 text-xs dark:border-gray-700">
                            <span class="min-w-0">
                                <span class="block truncate font-medium">{{ $candidate['filename'] }}</span>
                                <span class="block truncate text-gray-500">{{ $candidate['source'] }}</span>
                            </span>
                            {{ ($this->acquireStorageAction)(['token' => $candidate['token']]) }}
                        </li>
                    @empty
                        <li class="text-xs text-gray-500">{{ __('admin.media_library.storage_empty') }}</li>
                    @endforelse
                </ul>
            </section>
        </aside>
    </div>

    <div class="flex flex-wrap items-center justify-end gap-3 border-t border-gray-200 px-4 py-3 dark:border-gray-800">
        <x-filament::button color="gray" wire:click="clearSelection">
            {{ __('admin.media_library.clear_selection') }}
        </x-filament::button>
        {{ $this->destroySelectedAction }}
        {{ $this->insertMediaAction }}
    </div>

    <x-filament-actions::modals />
</div>
