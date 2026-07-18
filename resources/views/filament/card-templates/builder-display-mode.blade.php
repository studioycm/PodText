<div
    class="rounded-lg border border-gray-200 p-3 dark:border-white/10"
    data-card-template-builder-display-mode
    x-data="{
        mode: @js($builderDisplayMode),
        storageKey: 'podtext.card-template-builder-display-mode',
        init() {
            const remembered = window.localStorage.getItem(this.storageKey)

            if (['inline', 'slide_over'].includes(remembered) && remembered !== this.mode) {
                this.mode = remembered
                this.$wire.setBuilderDisplayMode(remembered)
            }
        },
        choose(mode) {
            if (! ['inline', 'slide_over'].includes(mode)) {
                return
            }

            this.mode = mode
            window.localStorage.setItem(this.storageKey, mode)
            this.$wire.setBuilderDisplayMode(mode)
        },
    }"
>
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <p class="text-sm font-medium text-gray-950 dark:text-white">
                {{ __('admin.settings_sp3c.builder_display.heading') }}
            </p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                {{ __('admin.settings_sp3c.builder_display.description') }}
            </p>
        </div>

        <div class="flex gap-2" role="group" aria-label="{{ __('admin.settings_sp3c.builder_display.heading') }}">
            <x-filament::button
                type="button"
                size="sm"
                :color="$builderDisplayMode === \App\Filament\Pages\CardTemplateEditorPage::BUILDER_DISPLAY_INLINE ? 'primary' : 'gray'"
                x-on:click="choose('inline')"
                x-bind:aria-pressed="mode === 'inline'"
                data-test="card-template-builder-mode-inline"
            >
                {{ __('admin.settings_sp3c.builder_display.inline') }}
            </x-filament::button>
            <x-filament::button
                type="button"
                size="sm"
                :color="$builderDisplayMode === \App\Filament\Pages\CardTemplateEditorPage::BUILDER_DISPLAY_SLIDE_OVER ? 'primary' : 'gray'"
                x-on:click="choose('slide_over')"
                x-bind:aria-pressed="mode === 'slide_over'"
                data-test="card-template-builder-mode-slide-over"
            >
                {{ __('admin.settings_sp3c.builder_display.slide_over') }}
            </x-filament::button>
        </div>
    </div>
</div>
