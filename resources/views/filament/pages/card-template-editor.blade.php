<x-filament-panels::page>
    <div
        data-sp3c-template-editor-page
        x-data="{
            wide: window.matchMedia('(min-width: 1024px)').matches,
            media: null,
            listener: null,
            init() {
                this.media = window.matchMedia('(min-width: 1024px)')
                this.listener = async (event) => {
                    if (! event.matches) {
                        const adjacent = document.querySelector('[data-card-template-preview-adjacent]')
                        const restoreToTrigger = Boolean(adjacent?.contains(document.activeElement))

                        this.wide = false

                        if (restoreToTrigger) {
                            this.$nextTick(() => document.querySelector('[data-test=card-template-preview-open]')?.focus())
                        }

                        return
                    }

                    const modal = document.querySelector('[data-card-template-preview-modal]')
                    const modalWindow = modal?.closest('[aria-modal=true]')
                    const restoreToHeading = Boolean((modalWindow ?? modal)?.contains(document.activeElement))

                    if (modal) {
                        await this.$wire.unmountAction()
                    }

                    if (! this.media?.matches) {
                        if (restoreToHeading) {
                            this.$nextTick(() => document.querySelector('[data-test=card-template-preview-open]')?.focus())
                        }

                        return
                    }

                    this.wide = true

                    if (restoreToHeading) {
                        this.$nextTick(() => document.querySelector('#card-template-preview-heading')?.focus())
                    }
                }
                this.media.addEventListener('change', this.listener)
            },
            destroy() {
                this.media?.removeEventListener('change', this.listener)
            },
        }"
    >
        <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(16rem,20rem)] xl:grid-cols-[minmax(0,1fr)_minmax(20rem,26rem)]">
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
