<x-filament-panels::page>
    <div
        x-data="podtextMarkdownTools"
        x-init="init()"
        data-tools1-tabs
        data-tools1-rtl="{{ app()->getLocale() === 'he' ? 'rtl' : 'ltr' }}"
        class="space-y-6"
    >
        <div class="border-b border-gray-200 dark:border-white/10">
            <nav class="-mb-px flex gap-6" aria-label="{{ __('admin.tools.tabs.label') }}">
                <button
                    type="button"
                    x-on:click="activeTab = 'markdown'"
                    x-bind:class="activeTab === 'markdown' ? 'border-primary-600 text-primary-600 dark:border-primary-400 dark:text-primary-400' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400 dark:hover:border-white/20 dark:hover:text-gray-200'"
                    class="border-b-2 px-1 py-3 text-sm font-medium"
                >
                    {{ __('admin.tools.tabs.markdown') }}
                </button>
            </nav>
        </div>

        <section x-show="activeTab === 'markdown'" class="space-y-5">
            <x-filament::section>
                <x-slot name="heading">
                    {{ __('admin.tools.markdown.heading') }}
                </x-slot>

                <x-slot name="description">
                    {{ __('admin.tools.markdown.local_storage_hint') }}
                </x-slot>

                <div class="flex flex-wrap items-center gap-2">
                    <x-filament::button
                        type="button"
                        icon="heroicon-o-plus"
                        x-on:click="addEditor()"
                    >
                        {{ __('admin.tools.actions.add_editor') }}
                    </x-filament::button>

                    <x-filament::button
                        type="button"
                        color="gray"
                        icon="heroicon-o-clipboard-document-list"
                        x-on:click="copySelectedAsCells()"
                    >
                        {{ __('admin.tools.actions.copy_selected_cells') }}
                    </x-filament::button>

                    <x-filament::button
                        type="button"
                        color="gray"
                        icon="heroicon-o-clipboard-document-check"
                        x-on:click="copyAllAsCells()"
                    >
                        {{ __('admin.tools.actions.copy_all_cells') }}
                    </x-filament::button>

                    <span
                        x-show="copiedMessage"
                        x-text="copiedMessage"
                        class="text-sm font-medium text-success-700 dark:text-success-400"
                    ></span>
                </div>
            </x-filament::section>

            <template x-for="(editor, index) in editors" :key="editor.id">
                <x-filament::section>
                    <div class="space-y-4">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <label class="inline-flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-200">
                                <input
                                    type="checkbox"
                                    x-model="editor.selected"
                                    class="rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500 dark:border-white/10 dark:bg-white/5"
                                />
                                <span x-text="editor.title || @js(__('admin.tools.markdown.untitled_editor'))"></span>
                            </label>

                            <div class="flex flex-wrap items-center gap-2">
                                <x-filament::button
                                    type="button"
                                    size="sm"
                                    color="gray"
                                    icon="heroicon-o-clipboard"
                                    x-on:click="copyEditor(editor)"
                                >
                                    {{ __('admin.tools.actions.copy_markdown') }}
                                </x-filament::button>

                                <x-filament::icon-button
                                    icon="heroicon-o-trash"
                                    color="danger"
                                    x-bind:disabled="editors.length === 1"
                                    x-on:click="removeEditor(editor.id)"
                                    label="{{ __('admin.tools.actions.remove_editor') }}"
                                />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-4 lg:grid-cols-[minmax(12rem,18rem)_1fr]">
                            <label class="space-y-1">
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-200">
                                    {{ __('admin.tools.fields.editor_title') }}
                                </span>
                                <input
                                    type="text"
                                    x-model.debounce.250ms="editor.title"
                                    class="block w-full rounded-lg border-gray-300 bg-white text-sm text-gray-950 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-white/10 dark:bg-white/5 dark:text-white"
                                />
                            </label>

                            <label class="space-y-1">
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-200">
                                    {{ __('admin.tools.fields.markdown') }}
                                </span>
                                <textarea
                                    x-model.debounce.250ms="editor.markdown"
                                    rows="12"
                                    dir="auto"
                                    class="block min-h-72 w-full resize-y rounded-lg border-gray-300 bg-white font-mono text-sm leading-6 text-gray-950 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-white/10 dark:bg-gray-950 dark:text-white"
                                ></textarea>
                            </label>
                        </div>
                    </div>
                </x-filament::section>
            </template>
        </section>
    </div>

    @once
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('podtextMarkdownTools', () => ({
                    activeTab: 'markdown',
                    copiedMessage: '',
                    editors: [],
                    storageKey: 'podtext.adminTools.markdownEditors.v1',
                    init() {
                        this.editors = this.loadEditors()
                        this.$watch('editors', () => this.persist(), { deep: true })
                    },
                    loadEditors() {
                        try {
                            const stored = JSON.parse(localStorage.getItem(this.storageKey) || '[]')

                            if (Array.isArray(stored) && stored.length > 0) {
                                return stored.map((editor) => ({
                                    id: editor.id || crypto.randomUUID(),
                                    markdown: editor.markdown || '',
                                    selected: Boolean(editor.selected),
                                    title: editor.title || '',
                                }))
                            }
                        } catch (error) {
                            localStorage.removeItem(this.storageKey)
                        }

                        return [this.newEditor()]
                    },
                    newEditor() {
                        return {
                            id: crypto.randomUUID(),
                            markdown: '',
                            selected: true,
                            title: '',
                        }
                    },
                    persist() {
                        localStorage.setItem(this.storageKey, JSON.stringify(this.editors))
                    },
                    addEditor() {
                        this.editors.push(this.newEditor())
                    },
                    removeEditor(id) {
                        if (this.editors.length === 1) {
                            return
                        }

                        this.editors = this.editors.filter((editor) => editor.id !== id)
                    },
                    cellPayload(editors) {
                        return editors
                            .map((editor) => `"${String(editor.markdown || '').replace(/\r\n/g, '\n').replace(/\r/g, '\n').replace(/"/g, '""')}"`)
                            .join('\n')
                    },
                    copyEditor(editor) {
                        this.copyText(editor.markdown || '', @js(__('admin.tools.copied.markdown')))
                    },
                    copySelectedAsCells() {
                        const selected = this.editors.filter((editor) => editor.selected)
                        this.copyText(this.cellPayload(selected), @js(__('admin.tools.copied.selected_cells')))
                    },
                    copyAllAsCells() {
                        this.copyText(this.cellPayload(this.editors), @js(__('admin.tools.copied.all_cells')))
                    },
                    copyText(text, message) {
                        if (navigator.clipboard && navigator.clipboard.writeText) {
                            navigator.clipboard.writeText(text)
                        } else {
                            const textarea = document.createElement('textarea')
                            textarea.value = text
                            textarea.style.position = 'fixed'
                            textarea.style.opacity = '0'
                            document.body.appendChild(textarea)
                            textarea.select()
                            document.execCommand('copy')
                            textarea.remove()
                        }

                        this.copiedMessage = message
                        window.setTimeout(() => this.copiedMessage = '', 2200)
                    },
                }))
            })
        </script>
    @endonce
</x-filament-panels::page>
