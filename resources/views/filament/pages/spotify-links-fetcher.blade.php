<x-filament-panels::page>
    <div data-tools1-spotify-fetcher data-tools1-rtl="{{ app()->getLocale() === 'he' ? 'rtl' : 'ltr' }}" class="space-y-6">
        <x-filament::section>
            <x-slot name="heading">
                {{ __('admin.spotify_fetcher.sections.input') }}
            </x-slot>

            <x-slot name="description">
                {{ __('admin.spotify_fetcher.descriptions.input') }}
            </x-slot>

            <div class="grid grid-cols-1 gap-5 xl:grid-cols-[1fr_22rem]">
                <label class="space-y-1">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-200">
                        {{ __('admin.spotify_fetcher.fields.links') }}
                    </span>
                    <textarea
                        wire:model.defer="linksInput"
                        rows="9"
                        dir="auto"
                        class="block min-h-56 w-full rounded-lg border-gray-300 bg-white font-mono text-sm leading-6 text-gray-950 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-white/10 dark:bg-gray-950 dark:text-white"
                    ></textarea>
                </label>

                <div class="space-y-4">
                    <label class="space-y-1">
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-200">
                            {{ __('admin.spotify_fetcher.fields.entity_mode') }}
                        </span>
                        <select
                            wire:model.live="entityMode"
                            class="block w-full rounded-lg border-gray-300 bg-white text-sm text-gray-950 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-white/10 dark:bg-white/5 dark:text-white"
                        >
                            <option value="episodes">{{ __('admin.spotify_fetcher.modes.episodes') }}</option>
                            <option value="shows">{{ __('admin.spotify_fetcher.modes.shows') }}</option>
                        </select>
                    </label>

                    <label class="space-y-1">
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-200">
                            {{ __('admin.spotify_fetcher.fields.batch_cap') }}
                        </span>
                        <input
                            type="number"
                            min="1"
                            max="100"
                            wire:model.live="batchCap"
                            class="block w-full rounded-lg border-gray-300 bg-white text-sm text-gray-950 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-white/10 dark:bg-white/5 dark:text-white"
                        />
                        <span class="text-xs text-gray-500 dark:text-gray-400">
                            {{ __('admin.spotify_fetcher.helpers.batch_cap') }}
                        </span>
                    </label>

                    <label class="space-y-1">
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-200">
                            {{ __('admin.spotify_fetcher.fields.connection') }}
                        </span>
                        <select
                            wire:model.live="connectionId"
                            class="block w-full rounded-lg border-gray-300 bg-white text-sm text-gray-950 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-white/10 dark:bg-white/5 dark:text-white"
                        >
                            <option value="">{{ __('admin.spotify_fetcher.connection_options.reduced') }}</option>
                            @foreach($this->spotifyConnections() as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                        <span class="text-xs text-gray-500 dark:text-gray-400">
                            {{ __('admin.spotify_fetcher.helpers.connection') }}
                        </span>
                        <span class="block text-xs text-gray-500 dark:text-gray-400">
                            {{ __('admin.spotify_fetcher.helpers.reduced_open_graph') }}
                        </span>
                    </label>

                    <label class="space-y-1">
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-200">
                            {{ __('admin.spotify_fetcher.fields.csv_upload') }}
                        </span>
                        <input
                            type="file"
                            wire:model="csvUpload"
                            accept=".csv,text/csv,text/plain"
                            class="block w-full text-sm text-gray-700 file:me-3 file:rounded-lg file:border-0 file:bg-primary-600 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-primary-500 dark:text-gray-200"
                        />
                        @error('csvUpload')
                            <span class="text-sm text-danger-600 dark:text-danger-400">{{ $message }}</span>
                        @enderror
                    </label>
                </div>
            </div>

            <div class="mt-5 flex flex-wrap items-center gap-2">
                <x-filament::button type="button" icon="heroicon-o-list-bullet" wire:click="parseLinks">
                    {{ __('admin.spotify_fetcher.actions.parse') }}
                </x-filament::button>

                <x-filament::button type="button" icon="heroicon-o-arrow-path" wire:click="fetch" wire:loading.attr="disabled">
                    {{ __('admin.spotify_fetcher.actions.fetch') }}
                </x-filament::button>

                <span wire:loading wire:target="fetch" class="text-sm text-gray-500 dark:text-gray-400">
                    {{ __('admin.spotify_fetcher.loading.fetch') }}
                </span>
            </div>
        </x-filament::section>

        @if($warnings !== [] || $usedReducedMode)
            <x-filament::section>
                <x-slot name="heading">
                    {{ __('admin.spotify_fetcher.sections.warnings') }}
                </x-slot>

                @if($usedReducedMode)
                    <p class="text-sm font-medium text-warning-700 dark:text-warning-300">
                        {{ __('admin.spotify_fetcher.reduced_mode_label') }}
                    </p>
                @endif

                @if($warnings !== [])
                    <ul class="mt-3 list-inside list-disc space-y-1 text-sm text-warning-800 dark:text-warning-200">
                        @foreach($warnings as $warning)
                            <li>{{ $warning }}</li>
                        @endforeach
                    </ul>
                @endif
            </x-filament::section>
        @endif

        @if($parsedLinks !== [])
            <x-filament::section>
                <x-slot name="heading">
                    {{ __('admin.spotify_fetcher.sections.parsed') }}
                </x-slot>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[42rem] divide-y divide-gray-200 text-sm dark:divide-white/10">
                        <thead>
                            <tr class="text-start text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">
                                <th class="px-3 py-2">{{ __('admin.spotify_fetcher.fields.type') }}</th>
                                <th class="px-3 py-2">{{ __('admin.spotify_fetcher.fields.external_id') }}</th>
                                <th class="px-3 py-2">{{ __('admin.spotify_fetcher.fields.source') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                            @foreach($parsedLinks as $link)
                                <tr>
                                    <td class="px-3 py-2">{{ __("admin.spotify_fetcher.types.{$link['type']}") }}</td>
                                    <td class="px-3 py-2 font-mono">{{ $link['id'] }}</td>
                                    <td class="px-3 py-2 break-all">{{ $link['input'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        @endif

        @if($rows !== [])
            <x-filament::section>
                <x-slot name="heading">
                    {{ __('admin.spotify_fetcher.sections.results') }}
                </x-slot>

                <div class="mb-4 flex flex-wrap gap-2">
                    <x-filament::button type="button" color="gray" icon="heroicon-o-arrow-down-tray" wire:click="downloadEpisodesCsv">
                        {{ __('admin.spotify_fetcher.actions.download_episodes') }}
                    </x-filament::button>

                    @if($podcastRows !== [])
                        <x-filament::button type="button" color="gray" icon="heroicon-o-arrow-down-tray" wire:click="downloadPodcastsCsv">
                            {{ __('admin.spotify_fetcher.actions.download_podcasts') }}
                        </x-filament::button>
                    @endif
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[96rem] divide-y divide-gray-200 text-sm dark:divide-white/10">
                        <thead>
                            <tr class="text-start text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">
                                <th class="px-3 py-2">{{ __('admin.spotify_fetcher.fields.status') }}</th>
                                <th class="px-3 py-2">{{ __('admin.spotify_fetcher.fields.source_tier') }}</th>
                                <th class="px-3 py-2">{{ __('admin.fields.title') }}</th>
                                <th class="px-3 py-2">{{ __('admin.fields.title_prefix') }}</th>
                                <th class="px-3 py-2">{{ __('admin.fields.description_markdown') }}</th>
                                <th class="px-3 py-2">{{ __('admin.fields.duration_seconds') }}</th>
                                <th class="px-3 py-2">{{ __('admin.fields.original_published_at') }}</th>
                                <th class="px-3 py-2">{{ __('admin.fields.external_id') }}</th>
                                <th class="px-3 py-2">{{ __('admin.spotify_fetcher.fields.image_preview') }}</th>
                                <th class="px-3 py-2">{{ __('admin.fields.external_thumbnail_url') }}</th>
                                <th class="px-3 py-2">{{ __('admin.spotify_fetcher.fields.show') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                            @foreach($rows as $index => $row)
                                <tr wire:key="spotify-row-{{ $index }}">
                                    <td class="px-3 py-2 align-top">
                                        <span @class([
                                            'inline-flex rounded-md px-2 py-1 text-xs font-medium',
                                            'bg-success-50 text-success-700 dark:bg-success-500/10 dark:text-success-300' => $row['status'] === 'fetched',
                                            'bg-warning-50 text-warning-700 dark:bg-warning-500/10 dark:text-warning-300' => $row['status'] === 'reduced',
                                            'bg-danger-50 text-danger-700 dark:bg-danger-500/10 dark:text-danger-300' => $row['status'] === 'error',
                                        ])>
                                            {{ $row['status_label'] }}
                                        </span>
                                        @if(filled($row['reason'] ?? null))
                                            <p class="mt-1 max-w-48 text-xs text-gray-500 dark:text-gray-400">{{ $row['reason'] }}</p>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 align-top">
                                        @if(filled($row['source_label'] ?? null))
                                            <span @class([
                                                'inline-flex rounded-md px-2 py-1 text-xs font-medium',
                                                'bg-primary-50 text-primary-700 dark:bg-primary-500/10 dark:text-primary-300' => ($row['source'] ?? null) === 'api',
                                                'bg-warning-50 text-warning-700 dark:bg-warning-500/10 dark:text-warning-300' => ($row['source'] ?? null) === 'reduced',
                                            ])>
                                                {{ $row['source_label'] }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 align-top">
                                        <input type="text" wire:model.live.debounce.500ms="rows.{{ $index }}.title" class="w-56 rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-white/5 dark:text-white" />
                                    </td>
                                    <td class="px-3 py-2 align-top">
                                        <input type="text" wire:model.live.debounce.500ms="rows.{{ $index }}.title_prefix" class="w-48 rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-white/5 dark:text-white" />
                                    </td>
                                    <td class="px-3 py-2 align-top">
                                        <textarea rows="3" wire:model.live.debounce.500ms="rows.{{ $index }}.description_markdown" class="w-80 rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-white/5 dark:text-white"></textarea>
                                        @if(filled($row['description_preview'] ?? null))
                                            <p class="mt-1 max-w-80 text-xs text-gray-500 dark:text-gray-400" title="{{ $row['description_markdown'] }}">
                                                {{ $row['description_preview'] }}
                                            </p>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 align-top">
                                        <input type="number" min="0" wire:model.live.debounce.500ms="rows.{{ $index }}.duration_seconds" class="w-28 rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-white/5 dark:text-white" />
                                    </td>
                                    <td class="px-3 py-2 align-top">
                                        <input type="text" wire:model.live.debounce.500ms="rows.{{ $index }}.release_date" class="w-40 rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-white/5 dark:text-white" />
                                    </td>
                                    <td class="px-3 py-2 align-top">
                                        <input type="text" wire:model.live.debounce.500ms="rows.{{ $index }}.external_id" class="w-44 rounded-lg border-gray-300 font-mono text-sm dark:border-white/10 dark:bg-white/5 dark:text-white" />
                                    </td>
                                    <td class="px-3 py-2 align-top">
                                        @if(filled($row['external_thumbnail_url'] ?? null))
                                            <img
                                                data-spotify-image-preview
                                                src="{{ $row['external_thumbnail_url'] }}"
                                                alt="{{ $row['title'] ?? __('admin.spotify_fetcher.fields.image_preview') }}"
                                                loading="lazy"
                                                referrerpolicy="no-referrer"
                                                class="h-14 w-14 rounded-md border border-gray-200 object-cover dark:border-white/10"
                                            />
                                        @else
                                            <span data-spotify-image-preview-empty class="text-xs text-gray-500 dark:text-gray-400">
                                                {{ __('admin.spotify_fetcher.placeholders.no_image') }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 align-top">
                                        <input type="url" wire:model.live.debounce.500ms="rows.{{ $index }}.external_thumbnail_url" class="w-72 rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-white/5 dark:text-white" />
                                    </td>
                                    <td class="px-3 py-2 align-top">
                                        <input type="text" wire:model.live.debounce.500ms="rows.{{ $index }}.show_name" class="w-48 rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-white/5 dark:text-white" />
                                        <input type="text" wire:model.live.debounce.500ms="rows.{{ $index }}.show_id" class="mt-2 w-48 rounded-lg border-gray-300 font-mono text-sm dark:border-white/10 dark:bg-white/5 dark:text-white" />
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        @endif

        @if($podcastRows !== [])
            <x-filament::section>
                <x-slot name="heading">
                    {{ __('admin.spotify_fetcher.sections.podcasts') }}
                </x-slot>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[56rem] divide-y divide-gray-200 text-sm dark:divide-white/10">
                        <thead>
                            <tr class="text-start text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">
                                <th class="px-3 py-2">{{ __('admin.spotify_fetcher.fields.status') }}</th>
                                <th class="px-3 py-2">{{ __('admin.fields.title') }}</th>
                                <th class="px-3 py-2">{{ __('admin.fields.description_markdown') }}</th>
                                <th class="px-3 py-2">{{ __('admin.spotify_fetcher.fields.show_id') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                            @foreach($podcastRows as $index => $row)
                                <tr wire:key="spotify-podcast-row-{{ $index }}">
                                    <td class="px-3 py-2 align-top">{{ $row['status_label'] }}</td>
                                    <td class="px-3 py-2 align-top">
                                        <input type="text" wire:model.live.debounce.500ms="podcastRows.{{ $index }}.title" class="w-56 rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-white/5 dark:text-white" />
                                    </td>
                                    <td class="px-3 py-2 align-top">
                                        <textarea rows="3" wire:model.live.debounce.500ms="podcastRows.{{ $index }}.description_markdown" class="w-96 rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-white/5 dark:text-white"></textarea>
                                    </td>
                                    <td class="px-3 py-2 align-top font-mono">{{ $row['show_id'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
