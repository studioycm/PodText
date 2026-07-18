@props([
    'modal' => false,
])

<section
    class="min-h-72 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900"
    role="region"
    aria-labelledby="card-template-preview-heading"
    data-card-template-preview-root
    x-data="{
        controlsOpen: true,
        zoom: 100,
        zoomIn() { this.zoom = Math.min(150, this.zoom + 10) },
        zoomOut() { this.zoom = Math.max(50, this.zoom - 10) },
        resetZoom() { this.zoom = 100 },
    }"
    @if($modal) data-card-template-preview-modal @else data-card-template-preview-adjacent @endif
    @if($modal) x-on:keydown.window.escape="setTimeout(() => document.querySelector('[data-test=card-template-preview-open]')?.focus(), 100)" @endif
>
    <header class="border-b border-gray-200 bg-white/95 p-3 backdrop-blur dark:border-white/10 dark:bg-gray-900/95">
        <h2
            id="card-template-preview-heading"
            class="sr-only"
            tabindex="-1"
            @if($modal) x-init="setTimeout(() => $el.focus(), 100)" @endif
            @unless($modal) x-ref="previewHeading" @endunless
        >
            {{ __('admin.settings_sp3c.preview.title') }}
        </h2>

        <div class="flex items-start justify-between gap-2">
            <div class="min-w-0">
                @if($previewRefreshedAt)
                    <p class="text-xs text-gray-500 dark:text-gray-400" data-test="card-template-preview-refreshed">
                        {{ __('admin.settings_sp3c.preview.last_refreshed', ['time' => $previewRefreshedAt]) }}
                    </p>
                @endif
            </div>

            <x-filament::icon-button
                type="button"
                color="gray"
                icon="heroicon-o-adjustments-horizontal"
                x-on:click="controlsOpen = ! controlsOpen"
                x-bind:aria-expanded="controlsOpen"
                aria-controls="card-template-preview-controls"
                :label="__('admin.settings_sp3c.preview.controls')"
                :tooltip="__('admin.settings_sp3c.preview.controls')"
                data-test="card-template-preview-controls-toggle"
            />
        </div>

        <div class="mt-2 text-sm" role="status" aria-live="polite">
            <span
                class="hidden font-medium text-warning-700 dark:text-warning-300"
                wire:dirty.class.remove="hidden"
                wire:target="data"
                data-test="card-template-preview-client-stale"
            >
                {{ __('admin.settings_sp3c.preview.stale') }}
            </span>
            <span
                wire:dirty.class="hidden"
                wire:target="data"
                @class([
                    'font-medium',
                    'text-warning-700 dark:text-warning-300' => $this->previewIsStale(),
                    'text-success-700 dark:text-success-300' => ! $this->previewIsStale(),
                ])
                data-test="card-template-preview-server-freshness"
            >
                {{ $this->previewIsStale() ? __('admin.settings_sp3c.preview.stale') : __('admin.settings_sp3c.preview.current') }}
            </span>
        </div>

        <div
            id="card-template-preview-controls"
            class="mt-3 space-y-3"
            x-show="controlsOpen"
            x-collapse
            data-card-template-preview-controls
        >
            @if($this->canChoosePreviewSample())
                <div data-card-template-preview-sample-select>
                    {{ $this->previewSampleForm }}
                </div>
            @endif

            <div class="flex flex-wrap items-center justify-between gap-2">
                <div class="inline-flex items-center overflow-hidden rounded-lg border border-gray-200 dark:border-white/10" role="group" aria-label="{{ __('admin.settings_sp3c.preview.zoom_reset') }}">
                    <x-filament::icon-button
                        type="button"
                        color="gray"
                        icon="heroicon-o-minus"
                        x-on:click="zoomOut()"
                        x-bind:disabled="zoom <= 50"
                        :label="__('admin.settings_sp3c.preview.zoom_out')"
                        data-test="card-template-preview-zoom-out"
                    />
                    <x-filament::button
                        type="button"
                        color="gray"
                        size="sm"
                        class="rounded-none"
                        x-on:click="resetZoom()"
                        :aria-label="__('admin.settings_sp3c.preview.zoom_reset')"
                        data-test="card-template-preview-zoom-reset"
                    >
                        <span x-text="`${zoom}%`">100%</span>
                    </x-filament::button>
                    <x-filament::icon-button
                        type="button"
                        color="gray"
                        icon="heroicon-o-plus"
                        x-on:click="zoomIn()"
                        x-bind:disabled="zoom >= 150"
                        :label="__('admin.settings_sp3c.preview.zoom_in')"
                        data-test="card-template-preview-zoom-in"
                    />
                </div>

                <x-filament::button
                    type="button"
                    color="gray"
                    size="sm"
                    icon="heroicon-o-arrow-path"
                    wire:click="refreshPreview"
                    wire:loading.attr="disabled"
                    wire:target="refreshPreview"
                    data-test="card-template-preview-refresh"
                >
                    {{ __('admin.settings_sp3c.preview.refresh') }}
                </x-filament::button>
            </div>
        </div>
    </header>

    <div class="min-h-56 overflow-auto p-4" data-card-template-preview-scroll>
        <div
            class="hidden min-h-48 items-center justify-center text-sm text-gray-600 dark:text-gray-300"
            wire:loading.class.remove="hidden"
            wire:loading.class="flex"
            wire:target="refreshPreview, previewControls.sample_id"
            aria-busy="true"
            data-test="card-template-preview-loading"
        >
            {{ __('admin.settings_sp3c.preview.loading') }}
        </div>

        <div wire:loading.remove wire:target="refreshPreview, previewControls.sample_id">
            @if($previewStatus === 'ready' && $previewHtml)
                <div
                    class="min-w-0"
                    x-bind:style="{ zoom: zoom / 100 }"
                    data-test="card-template-preview-ready"
                    data-card-template-preview-zoom-plane
                >
                    {!! $previewHtml !!}
                </div>
            @elseif($previewStatus === 'restricted')
                <p class="rounded-lg bg-warning-50 p-4 text-sm text-warning-800 dark:bg-warning-950 dark:text-warning-200" data-test="card-template-preview-restricted">
                    {{ __('admin.settings_sp3c.preview.restricted') }}
                </p>
            @elseif($previewStatus === 'invalid_draft')
                <div class="rounded-lg bg-danger-50 p-4 text-sm text-danger-800 dark:bg-danger-950 dark:text-danger-200" role="alert" data-test="card-template-preview-invalid">
                    <p>{{ __('admin.settings_sp3c.preview.invalid_draft') }}</p>
                    @if($modal)
                        <x-filament::button
                            type="button"
                            color="danger"
                            size="sm"
                            class="mt-3"
                            x-on:click="$wire.unmountAction().then(() => setTimeout(() => document.querySelector('[data-sp3c-template-editor] input, [data-sp3c-template-editor] select, [data-sp3c-template-editor] button')?.focus(), 0))"
                        >
                            {{ __('admin.settings_sp3c.preview.focus_invalid_field') }}
                        </x-filament::button>
                    @else
                        <x-filament::button
                            type="button"
                            color="danger"
                            size="sm"
                            class="mt-3"
                            x-on:click="document.querySelector('[data-sp3c-template-editor] input, [data-sp3c-template-editor] select, [data-sp3c-template-editor] button')?.focus()"
                        >
                            {{ __('admin.settings_sp3c.preview.focus_invalid_field') }}
                        </x-filament::button>
                    @endif
                </div>
            @elseif($previewStatus === 'no_sample')
                <p class="rounded-lg bg-gray-50 p-4 text-sm text-gray-700 dark:bg-white/5 dark:text-gray-200" data-test="card-template-preview-empty">
                    {{ $this->previewEmptyMessage() }}
                </p>
            @elseif($previewStatus === 'sample_error')
                <p class="rounded-lg bg-danger-50 p-4 text-sm text-danger-800 dark:bg-danger-950 dark:text-danger-200" role="alert" data-test="card-template-preview-error">
                    {{ __('admin.settings_sp3c.preview.sample_error') }}
                </p>
            @else
                <p class="text-sm text-gray-600 dark:text-gray-300">
                    {{ __('admin.settings_sp3c.preview.loading') }}
                </p>
            @endif
        </div>
    </div>
</section>
