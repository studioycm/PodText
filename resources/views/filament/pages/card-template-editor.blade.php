<x-filament-panels::page>
    <div
        data-sp3c-template-editor-page
        x-data="{
            wide: window.matchMedia('(min-width: 1024px)').matches,
            media: null,
            listener: null,
            validationFocusTimer: null,
            validationRestoreTimer: null,
            validationRootActionModalId: null,
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
            retryValidationFocus(statePath, attempt) {
                if (attempt >= 20) {
                    this.focusSafeValidationFallback(statePath)

                    return
                }

                this.validationFocusTimer = window.setTimeout(
                    () => this.focusValidationTarget(statePath, attempt + 1),
                    50,
                )
            },
            focusValidationTarget(statePath, attempt = 0) {
                window.clearTimeout(this.validationFocusTimer)

                if (! statePath.startsWith('mountedActions.')) {
                    this.validationRootActionModalId = null
                }

                const wrapper = Array.from(document.querySelectorAll('[data-field-wrapper]'))
                    .find((element) => {
                        const schemaComponent = element.closest('[x-data]')

                        return schemaComponent
                            && window.Alpine?.$data(schemaComponent)?.$statePath === statePath
                    })

                if (! wrapper) {
                    this.retryValidationFocus(statePath, attempt)

                    return
                }

                if (wrapper.getClientRects().length === 0) {
                    this.retryValidationFocus(statePath, attempt)

                    return
                }

                const actionModal = wrapper.closest('.fi-modal[id]')

                if (actionModal && statePath.startsWith('mountedActions.')) {
                    this.validationRootActionModalId = actionModal.id.replace(/-action-\d+$/, '-action-0')
                }

                if (statePath === 'data.parts' || statePath.endsWith('.data.children')) {
                    wrapper.setAttribute('tabindex', '-1')
                    wrapper.focus()

                    if (document.activeElement !== wrapper) {
                        this.focusSafeValidationFallback(statePath)
                    }

                    return
                }

                const focusable = Array.from(wrapper.querySelectorAll(
                    'input:not([type=hidden]):not([disabled]), select:not([disabled]), textarea:not([disabled]), button:not([disabled])',
                )).find((element) => element.getClientRects().length > 0)

                if (! focusable) {
                    wrapper.setAttribute('tabindex', '-1')
                    wrapper.focus()

                    if (document.activeElement !== wrapper) {
                        this.focusSafeValidationFallback(statePath)
                    }

                    return
                }

                focusable.dispatchEvent(new CustomEvent('focus-input', { bubbles: true }))
                this.$nextTick(() => {
                    if (! wrapper.contains(document.activeElement)) {
                        focusable.focus()
                    }

                    if (! wrapper.contains(document.activeElement)) {
                        this.retryValidationFocus(statePath, attempt)
                    }
                })
            },
            focusFirstVisible(selectors) {
                for (const selector of selectors) {
                    const target = Array.from(document.querySelectorAll(selector))
                        .find((element) => element.getClientRects().length > 0 && ! element.hasAttribute('disabled'))

                    if (target) {
                        target.focus()

                        return true
                    }
                }

                return false
            },
            focusSafeValidationFallback(statePath = null) {
                const actionModal = document.querySelector('.fi-modal.fi-modal-open[id]')

                if (actionModal && statePath?.startsWith('mountedActions.')) {
                    this.validationRootActionModalId = actionModal.id.replace(/-action-\d+$/, '-action-0')
                }

                this.focusFirstVisible([
                    '.fi-modal.fi-modal-open .fi-modal-close-btn',
                    '[data-test=card-template-preview-focus-invalid]',
                    '[data-test=card-template-preview-open]',
                    '[data-sp3c-template-editor] input, [data-sp3c-template-editor] select, [data-sp3c-template-editor] button',
                ])
            },
            restoreValidationTrigger(event) {
                if (event.detail.id !== this.validationRootActionModalId) {
                    return
                }

                this.validationRootActionModalId = null
                window.clearTimeout(this.validationRestoreTimer)
                this.validationRestoreTimer = window.setTimeout(
                    () => this.focusFirstVisible([
                        '[data-test=card-template-preview-focus-invalid]',
                        '[data-test=card-template-preview-open]',
                        '[data-sp3c-template-editor] input, [data-sp3c-template-editor] select, [data-sp3c-template-editor] button',
                    ]),
                    100,
                )
            },
            clearValidationActionIdentity(event) {
                if (event.detail.newActionNestingIndex !== null
                    || this.validationRootActionModalId !== `fi-${event.detail.id}-action-0`) {
                    return
                }

                this.validationRootActionModalId = null
            },
            destroy() {
                this.media?.removeEventListener('change', this.listener)
                window.clearTimeout(this.validationFocusTimer)
                window.clearTimeout(this.validationRestoreTimer)
            },
        }"
        x-on:card-template-validation-target.window="focusValidationTarget($event.detail.statePath)"
        x-on:modal-closed.window="restoreValidationTrigger($event)"
        x-on:sync-action-modals.window="clearValidationActionIdentity($event)"
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
