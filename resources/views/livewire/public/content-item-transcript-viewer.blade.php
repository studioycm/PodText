@inject('renderer', 'App\Support\Markdown\SafeMarkdownRenderer')

<section
    class="space-y-5"
    data-test="transcript-viewer"
    data-selected-transcription="{{ $activeTranscription?->reference_key }}"
    x-data="{
        showTimestamps: JSON.parse(localStorage.getItem('podtext.showTimestamps') ?? 'true'),
        showSpeakers: JSON.parse(localStorage.getItem('podtext.showSpeakers') ?? 'true'),
        fontStep: 0,
        copiedTranscript: false,
        init() {
            this.fontStep = this.normalizeFontStep(localStorage.getItem('podtext.transcript.fontStep') ?? 0)
        },
        copyTranscriptLink() {
            navigator.clipboard?.writeText(window.location.href)
            this.copiedTranscript = true
            setTimeout(() => this.copiedTranscript = false, 1800)
        },
        normalizeFontStep(value) {
            const step = Number(value)

            if (Number.isNaN(step)) {
                return 0
            }

            return Math.max(-2, Math.min(3, step))
        },
        setFontStep(value) {
            this.fontStep = this.normalizeFontStep(value)
            localStorage.setItem('podtext.transcript.fontStep', String(this.fontStep))
        },
        increaseFont() {
            this.setFontStep(this.fontStep + 1)
        },
        decreaseFont() {
            this.setFontStep(this.fontStep - 1)
        },
        resetFont() {
            this.setFontStep(0)
        },
        setTimestampPreference(value) {
            this.showTimestamps = value
            localStorage.setItem('podtext.showTimestamps', JSON.stringify(value))
        },
        setSpeakerPreference(value) {
            this.showSpeakers = value
            localStorage.setItem('podtext.showSpeakers', JSON.stringify(value))
        },
    }"
    x-on:podtext:transcript-font-increase.window="increaseFont()"
    x-on:podtext:transcript-font-decrease.window="decreaseFont()"
    x-on:podtext:transcript-font-reset.window="resetFont()"
    aria-labelledby="item-transcript-heading"
>
    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div class="min-w-0 space-y-3">
            <h2 id="item-transcript-heading" class="sr-only">
                {{ __('public.pages.item.transcript_heading') }}
            </h2>

            @if($activeTranscription && $details)
                <dl class="flex flex-wrap items-center gap-2 text-xs text-gray-600 dark:text-gray-300" data-test="transcript-details-row">
                    @if($details['title'])
                        <div class="max-w-full rounded-md bg-gray-100 px-2 py-1 font-medium text-gray-800 dark:bg-gray-800 dark:text-gray-100" data-test="transcript-detail-title">
                            <dt class="sr-only">{{ __('public.labels.transcription') }}</dt>
                            <dd class="truncate">{{ $details['title'] }}</dd>
                        </div>
                    @endif

                    <div class="rounded-md bg-gray-100 px-2 py-1 dark:bg-gray-800" data-test="reading-time" data-transcript-detail="reading-time">
                        <dt class="sr-only">{{ __('public.labels.reading_time') }}</dt>
                        <dd>{{ $details['reading_time'] }}</dd>
                    </div>
                    <div class="rounded-md bg-gray-100 px-2 py-1 dark:bg-gray-800" data-test="transcript-length" data-transcript-detail="word-count">
                        <dt class="sr-only">{{ __('public.labels.transcript_length') }}</dt>
                        <dd>{{ $details['word_count'] }}</dd>
                    </div>

                    @if($details['published_at'] && $details['published_part'])
                        <div class="{{ $details['published_class'] }}" data-test="transcript-published-at" data-transcript-detail="published-at">
                            <dt class="sr-only">{{ __('public.labels.published_at') }}</dt>
                            <dd>
                                <x-public.card-part-shell :part="$details['published_part']">
                                    <span class="block min-w-0 truncate">{{ $details['published_at'] }}</span>
                                </x-public.card-part-shell>
                            </dd>
                        </div>
                    @endif

                    @if($details['transcribers'] !== [])
                        <div class="rounded-md bg-gray-100 px-2 py-1 dark:bg-gray-800" data-test="transcriber-link" data-transcript-detail="transcribers">
                            <dt class="sr-only">{{ __('public.labels.transcribers') }}</dt>
                            <dd class="flex flex-wrap gap-1">
                                @foreach($details['transcribers'] as $transcriber)
                                    <a
                                        href="{{ $transcriber['url'] }}"
                                        class="font-medium text-primary-700 hover:text-primary-900 dark:text-primary-300 dark:hover:text-primary-100"
                                    >
                                        {{ $transcriber['label'] }}
                                    </a>@if(! $loop->last)<span aria-hidden="true">,</span>@endif
                                @endforeach
                            </dd>
                        </div>
                    @endif
                </dl>
            @endif
        </div>

        @if($showActionsMenu)
            <div
                class="relative self-start"
                x-data="{ actionsOpen: false }"
                x-on:keydown.escape.window="actionsOpen = false"
                data-test="transcript-actions-menu"
            >
                <button
                    type="button"
                    x-on:click="actionsOpen = ! actionsOpen"
                    class="rounded-md border border-gray-200 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 shadow-sm hover:border-primary-300 hover:text-primary-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:border-primary-500"
                    data-test="transcript-actions-menu-trigger"
                    aria-haspopup="menu"
                    x-bind:aria-expanded="actionsOpen.toString()"
                >
                    {{ __('public.viewer.actions') }}
                </button>

                <div
                    x-show="actionsOpen"
                    x-cloak
                    x-transition
                    x-on:click.outside="actionsOpen = false"
                    class="absolute end-0 z-10 mt-2 w-64 rounded-lg border border-gray-200 bg-white p-2 text-sm shadow-lg dark:border-gray-700 dark:bg-gray-900"
                    role="menu"
                >
                    <button
                        type="button"
                        x-on:click="setTimestampPreference(! showTimestamps)"
                        class="flex w-full items-center rounded-md px-3 py-2 text-start font-medium text-gray-700 hover:bg-gray-50 hover:text-primary-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600 dark:text-gray-200 dark:hover:bg-gray-800 dark:hover:text-primary-200"
                        data-test="toggle-timestamps"
                        role="menuitem"
                    >
                        <span x-show="showTimestamps">{{ __('public.viewer.hide_timestamps') }}</span>
                        <span x-show="! showTimestamps">{{ __('public.viewer.show_timestamps') }}</span>
                    </button>
                    <button
                        type="button"
                        x-on:click="setSpeakerPreference(! showSpeakers)"
                        class="flex w-full items-center rounded-md px-3 py-2 text-start font-medium text-gray-700 hover:bg-gray-50 hover:text-primary-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600 dark:text-gray-200 dark:hover:bg-gray-800 dark:hover:text-primary-200"
                        data-test="toggle-speakers"
                        role="menuitem"
                    >
                        <span x-show="showSpeakers">{{ __('public.viewer.hide_speakers') }}</span>
                        <span x-show="! showSpeakers">{{ __('public.viewer.show_speakers') }}</span>
                    </button>
                    <button
                        type="button"
                        x-on:click="copyTranscriptLink()"
                        class="flex w-full items-center rounded-md px-3 py-2 text-start font-medium text-gray-700 hover:bg-gray-50 hover:text-primary-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600 dark:text-gray-200 dark:hover:bg-gray-800 dark:hover:text-primary-200"
                        data-test="copy-transcript-link"
                        role="menuitem"
                    >
                        <span x-show="! copiedTranscript">{{ __('public.actions.copy_link') }}</span>
                        <span x-show="copiedTranscript">{{ __('public.actions.copied') }}</span>
                    </button>
                    <button
                        type="button"
                        x-on:click="$dispatch('podtext:transcript-font-decrease')"
                        class="flex w-full items-center rounded-md px-3 py-2 text-start font-medium text-gray-700 hover:bg-gray-50 hover:text-primary-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600 dark:text-gray-200 dark:hover:bg-gray-800 dark:hover:text-primary-200"
                        data-test="transcript-font-decrease"
                        role="menuitem"
                    >
                        {{ __('public.viewer.decrease_font') }}
                    </button>
                    <button
                        type="button"
                        x-on:click="$dispatch('podtext:transcript-font-increase')"
                        class="flex w-full items-center rounded-md px-3 py-2 text-start font-medium text-gray-700 hover:bg-gray-50 hover:text-primary-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600 dark:text-gray-200 dark:hover:bg-gray-800 dark:hover:text-primary-200"
                        data-test="transcript-font-increase"
                        role="menuitem"
                    >
                        {{ __('public.viewer.increase_font') }}
                    </button>
                    <button
                        type="button"
                        x-on:click="$dispatch('podtext:transcript-font-reset')"
                        class="flex w-full items-center rounded-md px-3 py-2 text-start font-medium text-gray-700 hover:bg-gray-50 hover:text-primary-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600 dark:text-gray-200 dark:hover:bg-gray-800 dark:hover:text-primary-200"
                        data-test="transcript-font-reset"
                        role="menuitem"
                    >
                        {{ __('public.viewer.reset_font') }}
                    </button>
                    <button
                        type="button"
                        x-on:click="$dispatch('podtext:transcript-fullscreen-toggle')"
                        class="flex w-full items-center rounded-md px-3 py-2 text-start font-medium text-gray-700 hover:bg-gray-50 hover:text-primary-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600 dark:text-gray-200 dark:hover:bg-gray-800 dark:hover:text-primary-200"
                        data-test="transcript-fullscreen-toggle"
                        role="menuitem"
                    >
                        {{ __('public.viewer.fullscreen') }}
                    </button>
                    <button
                        type="button"
                        x-on:click="$dispatch('podtext:transcript-player-toggle')"
                        class="flex w-full items-center rounded-md px-3 py-2 text-start font-medium text-gray-700 hover:bg-gray-50 hover:text-primary-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600 dark:text-gray-200 dark:hover:bg-gray-800 dark:hover:text-primary-200"
                        data-test="transcript-player-toggle"
                        role="menuitem"
                    >
                        <span x-show="! playerHidden">{{ __('public.viewer.hide_player') }}</span>
                        <span x-show="playerHidden">{{ __('public.viewer.show_player') }}</span>
                    </button>
                </div>
            </div>
        @endif
    </div>

    @if($transcriptions->count() > 1)
        <div class="flex flex-wrap gap-2" data-test="transcript-tabs" aria-label="{{ __('public.labels.transcriptions') }}">
            @foreach($transcriptions as $transcription)
                <button
                    type="button"
                    wire:key="transcription-tab-{{ $transcription->reference_key }}"
                    wire:click="selectTranscription(@js($transcription->reference_key))"
                    class="rounded-md border px-3 py-2 text-sm font-medium transition {{ $activeTranscription?->is($transcription) ? 'border-primary-500 bg-primary-50 text-primary-900 dark:border-primary-400 dark:bg-primary-400/10 dark:text-primary-100' : 'border-gray-200 text-gray-700 hover:border-primary-300 hover:text-primary-700 dark:border-gray-700 dark:text-gray-300 dark:hover:border-primary-500 dark:hover:text-primary-200' }}"
                    data-test="transcript-tab"
                    @if($activeTranscription?->is($transcription)) aria-current="true" @endif
                >
                    <span class="block">{{ $transcription->title ?: __('public.labels.transcription') }}</span>
                    @if($transcription->authors->isNotEmpty())
                        <span class="mt-0.5 block text-xs font-normal opacity-80" data-test="transcript-tab-transcribers">
                            {{ $transcription->authors->pluck('name')->join(', ') }}
                        </span>
                    @endif
                </button>
            @endforeach
        </div>
    @endif

    @if($activeTranscription && $segments !== [])
        <div class="space-y-5">
            @foreach($segments as $segment)
                <article
                    id="{{ $segment['anchor'] }}"
                    class="scroll-mt-24 border-s-2 border-primary-300 ps-4"
                    data-test="transcript-segment"
                >
                    <div class="mb-2 flex flex-wrap items-center gap-3 text-sm">
                        <a
                            href="#{{ $segment['anchor'] }}"
                            class="font-mono text-primary-700 hover:text-primary-900 dark:text-primary-300 dark:hover:text-primary-100"
                            dir="ltr"
                            x-show="showTimestamps"
                            data-test="timestamp-anchor"
                        >
                            {{ $segment['timestamp'] }}
                        </a>
                        <span
                            class="font-semibold text-gray-800 dark:text-gray-100"
                            x-show="showSpeakers"
                            data-test="speaker-label"
                        >
                            {{ $segment['speaker'] }}
                        </span>
                    </div>

                    <div
                        class="{{ $renderer->publicTranscriptClasses() }}"
                        x-bind:class="{ 'text-sm': fontStep <= -1, 'text-base': fontStep === 0, 'text-lg': fontStep === 1, 'text-xl': fontStep >= 2 }"
                        data-test="transcript-segment-content"
                        data-transcript-font-wrapper
                    >
                        {!! $renderer->toTranscriptHtml($segment['markdown']) !!}
                    </div>
                </article>
            @endforeach
        </div>
    @elseif($activeTranscription)
        <div
            class="{{ $renderer->publicTranscriptClasses() }}"
            x-bind:class="{ 'text-sm': fontStep <= -1, 'text-base': fontStep === 0, 'text-lg': fontStep === 1, 'text-xl': fontStep >= 2 }"
            data-test="transcript-fallback-content"
            data-transcript-font-wrapper
        >
            {!! $renderer->toTranscriptHtml($activeTranscription->transcript_markdown) !!}
        </div>
    @else
        <div class="rounded-lg border border-dashed border-gray-300 bg-white p-6 text-sm text-gray-600 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
            {{ __('public.empty.items') }}
        </div>
    @endif
</section>
