<x-filament-panels::page>
    <div
        data-sp3c-template-editor-page
        x-data="{
            wide: window.matchMedia('(min-width: 1280px)').matches,
            media: null,
            listener: null,
            init() {
                this.media = window.matchMedia('(min-width: 1280px)')
                this.listener = (event) => {
                    const modal = document.querySelector('[data-card-template-preview-modal]')
                    const restoreToHeading = Boolean(modal?.contains(document.activeElement))
                    this.wide = event.matches

                    if (event.matches && modal) {
                        this.$wire.unmountAction().then(() => {
                            if (restoreToHeading) {
                                this.$nextTick(() => document.querySelector('#card-template-preview-heading')?.focus())
                            }
                        })
                    }
                }
                this.media.addEventListener('change', this.listener)
            },
            destroy() {
                this.media?.removeEventListener('change', this.listener)
            },
        }"
    >
        <div class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(20rem,26rem)]">
            <div class="min-w-0" data-card-template-editor-column>
                {{ $this->content }}
            </div>

            <template x-if="wide">
                <aside class="sticky top-[5.5rem] max-h-[calc(100vh-5.5rem)] min-w-0 overflow-y-auto" data-card-template-preview-column data-card-template-preview-wide-shell>
                    @include('filament.pages.card-template-preview', ['modal' => false])
                </aside>
            </template>
        </div>
    </div>
</x-filament-panels::page>
