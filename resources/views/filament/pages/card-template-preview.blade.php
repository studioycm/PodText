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
        cardWidth: 100,
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

        <div class="flex min-w-0 items-center justify-between gap-2">
            <div class="flex min-w-0 items-center gap-x-2 text-xs" role="status" aria-live="polite" data-card-template-preview-status-row>
                <span
                    class="hidden shrink-0 font-medium text-warning-700 dark:text-warning-300"
                    wire:dirty.class.remove="hidden"
                    wire:target="data"
                    title="{{ __('admin.settings_sp3c.preview.stale') }}"
                    data-test="card-template-preview-client-stale"
                >
                    {{ __('admin.settings_sp3c.preview.stale_short') }}
                </span>
                <span
                    wire:dirty.class="hidden"
                    wire:target="data"
                    title="{{ $this->previewIsStale() ? __('admin.settings_sp3c.preview.stale') : __('admin.settings_sp3c.preview.current') }}"
                    @class([
                        'shrink-0 font-medium',
                        'text-warning-700 dark:text-warning-300' => $this->previewIsStale(),
                        'text-success-700 dark:text-success-300' => ! $this->previewIsStale(),
                    ])
                    data-test="card-template-preview-server-freshness"
                >
                    {{ $this->previewIsStale() ? __('admin.settings_sp3c.preview.stale_short') : __('admin.settings_sp3c.preview.current_short') }}
                </span>

                @if($previewRefreshedAt)
                    <span aria-hidden="true">&middot;</span>
                    <span
                        class="truncate text-gray-500 dark:text-gray-400"
                        dir="ltr"
                        title="{{ __('admin.settings_sp3c.preview.last_refreshed', ['time' => $previewRefreshedAt]) }}"
                        data-test="card-template-preview-refreshed"
                    >
                        {{ $previewRefreshedAt }}
                    </span>
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

        <div
            id="card-template-preview-controls"
            class="mt-3"
            x-show="controlsOpen"
            x-collapse
            data-card-template-preview-controls
        >
            <div class="flex min-w-0 items-center gap-2" data-card-template-preview-controls-row>
                @if($this->canChoosePreviewSample())
                    <div class="min-w-0 flex-1" data-card-template-preview-sample-select>
                        {{ $this->previewSampleForm }}
                    </div>
                @endif

                <x-filament::input.wrapper class="w-20 shrink-0">
                    <x-filament::input.select
                        x-model.number="cardWidth"
                        aria-label="{{ __('admin.settings_sp3c.preview.width') }}"
                        data-test="card-template-preview-width"
                    >
                        @foreach([100, 90, 80, 70, 60] as $width)
                            <option value="{{ $width }}">{{ $width }}%</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>

                <x-filament::icon-button
                    type="button"
                    color="gray"
                    icon="heroicon-o-arrow-path"
                    wire:click="refreshPreview"
                    wire:loading.attr="disabled"
                    wire:target="refreshPreview"
                    :label="__('admin.settings_sp3c.preview.refresh')"
                    :tooltip="__('admin.settings_sp3c.preview.refresh')"
                    data-test="card-template-preview-refresh"
                />
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
                    class="mx-auto min-w-0"
                    x-bind:style="{ width: cardWidth + '%' }"
                    data-test="card-template-preview-ready"
                    data-card-template-preview-width-plane
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
                    <x-filament::button
                        type="button"
                        color="danger"
                        size="sm"
                        class="mt-3"
                        wire:click="focusInvalidDraftField"
                        wire:loading.attr="disabled"
                        wire:target="focusInvalidDraftField"
                        data-test="card-template-preview-focus-invalid"
                    >
                        {{ __('admin.settings_sp3c.preview.focus_invalid_field') }}
                    </x-filament::button>
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
