@inject('renderer', 'App\Support\Markdown\SafeMarkdownRenderer')

<section
    class="space-y-5"
    data-test="transcript-viewer"
    data-selected-transcription="{{ $activeTranscription?->reference_key }}"
    x-data="{
        showTimestamps: JSON.parse(localStorage.getItem('podtext.showTimestamps') ?? 'true'),
        showSpeakers: JSON.parse(localStorage.getItem('podtext.showSpeakers') ?? 'true'),
        copiedTranscript: false,
        copyTranscriptLink() {
            navigator.clipboard?.writeText(window.location.href)
            this.copiedTranscript = true
            setTimeout(() => this.copiedTranscript = false, 1800)
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
    aria-labelledby="item-transcript-heading"
>
    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div class="space-y-2">
{{--            <h2 id="item-transcript-heading" class="text-xl font-semibold text-gray-950 dark:text-white">--}}
{{--                {{ __('public.pages.item.transcript_heading') }}--}}
{{--            </h2>--}}

            @if($activeTranscription)
                <dl class="flex flex-wrap gap-2 text-xs text-gray-600 dark:text-gray-300">
                    <div class="rounded-md bg-gray-100 px-2 py-1 dark:bg-gray-800" data-test="reading-time">
                        <dt class="sr-only">{{ __('public.labels.reading_time') }}</dt>
                        <dd>{{ trans_choice('public.labels.reading_minutes_count', $readingMinutes, ['count' => $readingMinutes]) }}</dd>
                    </div>
                    <div class="rounded-md bg-gray-100 px-2 py-1 dark:bg-gray-800" data-test="transcript-length">
                        <dt class="sr-only">{{ __('public.labels.transcript_length') }}</dt>
                        <dd>{{ trans_choice('public.labels.transcript_words_count', $wordCount, ['count' => $wordCount]) }}</dd>
                    </div>
                    @if($activeTranscription->published_at)
                        <div class="rounded-md bg-gray-100 px-2 py-1 dark:bg-gray-800" data-test="transcript-published-at">
                            <dt class="sr-only">{{ __('public.labels.published_at') }}</dt>
                            <dd>{{ $activeTranscription->published_at->timezone('Asia/Jerusalem')->format('d/m/Y') }}</dd>
                        </div>
                    @endif
                    @if($activeTranscription->authors->isNotEmpty())
                        <div class="rounded-md bg-gray-100 px-2 py-1 dark:bg-gray-800" data-test="transcriber-link">
                            <dt class="sr-only">{{ __('public.labels.transcribers') }}</dt>
                            <dd class="flex flex-wrap gap-1">
                                @foreach($activeTranscription->authors as $author)
                                    <a
                                        href="{{ \App\Filament\Public\Pages\ShowContributor::getUrl(['authorSlug' => $author->slug], panel: 'public') }}"
                                        class="font-medium text-primary-700 hover:text-primary-900 dark:text-primary-300 dark:hover:text-primary-100"
                                    >
                                        {{ $author->name }}
                                    </a>@if(! $loop->last)<span aria-hidden="true">,</span>@endif
                                @endforeach
                            </dd>
                        </div>
                    @endif
                </dl>
            @endif
        </div>

        <div class="flex flex-wrap gap-2 text-sm">
            <button
                type="button"
                x-on:click="setTimestampPreference(! showTimestamps)"
                class="rounded-md border border-gray-200 px-3 py-1.5 font-medium text-gray-700 hover:border-primary-300 hover:text-primary-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600 dark:border-gray-700 dark:text-gray-200 dark:hover:border-primary-500 dark:hover:text-primary-200"
                data-test="toggle-timestamps"
            >
                <span x-show="showTimestamps">{{ __('public.viewer.hide_timestamps') }}</span>
                <span x-show="! showTimestamps">{{ __('public.viewer.show_timestamps') }}</span>
            </button>
            <button
                type="button"
                x-on:click="setSpeakerPreference(! showSpeakers)"
                class="rounded-md border border-gray-200 px-3 py-1.5 font-medium text-gray-700 hover:border-primary-300 hover:text-primary-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600 dark:border-gray-700 dark:text-gray-200 dark:hover:border-primary-500 dark:hover:text-primary-200"
                data-test="toggle-speakers"
            >
                <span x-show="showSpeakers">{{ __('public.viewer.hide_speakers') }}</span>
                <span x-show="! showSpeakers">{{ __('public.viewer.show_speakers') }}</span>
            </button>
            <button
                type="button"
                x-on:click="copyTranscriptLink()"
                class="rounded-md bg-primary-600 px-3 py-1.5 font-medium text-white hover:bg-primary-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600 dark:bg-primary-500 dark:hover:bg-primary-400"
                data-test="copy-transcript-link"
            >
                <span x-show="! copiedTranscript">{{ __('public.actions.copy_link') }}</span>
                <span x-show="copiedTranscript">{{ __('public.actions.copied') }}</span>
            </button>
        </div>
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

                    <div class="{{ $renderer->publicTranscriptClasses() }}" data-test="transcript-segment-content">
                        {!! $renderer->toTranscriptHtml($segment['markdown']) !!}
                    </div>
                </article>
            @endforeach
        </div>
    @elseif($activeTranscription)
        <div class="{{ $renderer->publicTranscriptClasses() }}" data-test="transcript-fallback-content">
            {!! $renderer->toTranscriptHtml($activeTranscription->transcript_markdown) !!}
        </div>
    @else
        <div class="rounded-lg border border-dashed border-gray-300 bg-white p-6 text-sm text-gray-600 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
            {{ __('public.empty.items') }}
        </div>
    @endif
</section>
