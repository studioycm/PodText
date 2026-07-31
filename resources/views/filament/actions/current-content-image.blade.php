@php
    $metadataLabels = [
        'title' => __('admin.owner_image.metadata.title'),
        'original_filename' => __('admin.owner_image.metadata.original_filename'),
        'stored_filename' => __('admin.owner_image.metadata.stored_filename'),
        'mime' => __('admin.owner_image.metadata.mime'),
        'extension' => __('admin.owner_image.metadata.extension'),
        'dimensions' => __('admin.owner_image.metadata.dimensions'),
        'file_size' => __('admin.owner_image.metadata.file_size'),
        'directory' => __('admin.owner_image.metadata.directory'),
        'disk' => __('admin.owner_image.metadata.disk'),
        'reference_key' => __('admin.owner_image.metadata.reference_key'),
        'updated_at' => __('admin.owner_image.metadata.updated_at'),
    ];
@endphp

<div
    x-data="{
        copied: null,
        copyTimer: null,
        markCopied(key) {
            this.copied = key;
            clearTimeout(this.copyTimer);
            this.copyTimer = setTimeout(() => (this.copied = null), 2000);
        },
        fallbackCopy(value, key) {
            const textarea = document.createElement('textarea');
            textarea.value = value;
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand('copy');
            textarea.remove();
            this.markCopied(key);
        },
        copy(value, key) {
            if (! navigator.clipboard?.writeText) {
                this.fallbackCopy(value, key);

                return;
            }

            navigator.clipboard
                .writeText(value)
                .then(() => this.markCopied(key))
                .catch(() => this.fallbackCopy(value, key));
        },
    }"
    class="min-w-0 space-y-5"
    data-testid="owner-image-workspace"
>
    <section
        class="min-w-0 rounded-xl border border-gray-200 p-4 dark:border-gray-700"
        data-testid="owner-image-effective"
    >
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <h3 class="text-sm font-semibold text-gray-950 dark:text-white">
                {{ __('admin.owner_image.effective_image') }}
            </h3>

            <span class="inline-flex rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-200">
                {{ __("admin.owner_image.sources.{$presentation->effectiveSource}") }}
            </span>
        </div>

        <div class="grid min-h-40 min-w-0 place-items-center rounded-lg bg-gray-50 p-3 dark:bg-gray-900">
            @if (filled($presentation->effectivePreviewUrl))
                <img
                    src="{{ $presentation->effectivePreviewUrl }}"
                    alt="{{ $presentation->effectiveAlt ?? __('admin.owner_image.preview_alt_generic') }}"
                    width="300"
                    height="300"
                    loading="lazy"
                    class="h-auto max-h-[300px] w-full max-w-[300px] object-contain"
                    data-testid="owner-image-effective-preview"
                />
            @else
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ __('admin.owner_image.preview_unavailable') }}
                </p>
            @endif
        </div>

        @if ($presentation->effectiveSource === 'external_url')
            <p class="mt-3 text-sm text-gray-600 dark:text-gray-300">
                {{ __('admin.owner_image.external_not_media') }}
            </p>
        @endif
    </section>

    @if ($presentation->brokenDirect || $presentation->warningCodes !== [])
        <section
            class="border-warning-300 bg-warning-50 dark:border-warning-700 dark:bg-warning-950/30 rounded-xl border p-4"
            data-testid="owner-image-warning"
        >
            <h3 class="text-warning-900 dark:text-warning-100 text-sm font-semibold">
                {{
                    $presentation->brokenDirect
                    ? __('admin.owner_image.broken_direct_heading')
                    : __('admin.owner_image.warnings_heading')
                }}
            </h3>

            @if ($presentation->brokenDirect)
                <p class="text-warning-800 dark:text-warning-200 mt-1 text-sm">
                    {{ __('admin.owner_image.broken_direct_body') }}
                </p>
            @endif

            @if ($presentation->warningCodes !== [])
                <ul class="text-warning-800 dark:text-warning-200 mt-2 list-inside list-disc space-y-1 text-sm">
                    @foreach ($presentation->warningCodes as $warningCode)
                        <li>{{ __("admin.owner_image.warnings.{$warningCode}") }}</li>
                    @endforeach
                </ul>
            @endif
        </section>
    @endif

    @if (is_array($presentation->media))
        <section class="rounded-xl border border-gray-200 p-4 dark:border-gray-700" data-testid="owner-image-metadata">
            <h3 class="mb-3 text-sm font-semibold text-gray-950 dark:text-white">
                {{ __('admin.owner_image.media_metadata') }}
            </h3>

            <dl class="divide-y divide-gray-100 text-sm dark:divide-gray-800">
                @foreach ($metadataLabels as $key => $metadataLabel)
                    @if (filled($presentation->media[$key] ?? null))
                        <div class="grid gap-1 py-2 sm:grid-cols-[10rem_minmax(0,1fr)] sm:gap-3">
                            <dt class="font-medium text-gray-600 dark:text-gray-300">{{ $metadataLabel }}</dt>
                            <dd class="min-w-0 break-words text-gray-950 dark:text-white">
                                <span
                                    dir="{{
                                        in_array($key, [
                                            'original_filename',
                                            'stored_filename',
                                            'mime',
                                            'extension',
                                            'dimensions',
                                            'file_size',
                                            'directory',
                                            'disk',
                                            'reference_key',
                                            'updated_at',
                                        ], true) ? 'ltr' : 'auto'
                                    }}"
                                    class="inline-block max-w-full"
                                    data-testid="owner-image-metadata-{{ str($key)->replace('_', '-') }}"
                                >
                                    {{ $presentation->media[$key] }}
                                </span>

                                @if (in_array($key, ['original_filename', 'stored_filename'], true))
                                    <button
                                        type="button"
                                        class="text-primary-600 hover:text-primary-500 focus-visible:outline-primary-600 dark:text-primary-400 ms-2 inline-flex items-center gap-1 font-medium focus-visible:outline-2 focus-visible:outline-offset-2"
                                        x-on:click="copy(@js($presentation->media[$key]), @js($key))"
                                        data-testid="owner-image-copy-{{ str($key)->replace('_', '-') }}"
                                    >
                                        {{ __('admin.owner_image.actions.copy_filename') }}
                                    </button>

                                    <span
                                        x-cloak
                                        x-show="copied === @js($key)"
                                        class="text-success-600 dark:text-success-400 ms-2"
                                        role="status"
                                        aria-live="polite"
                                    >
                                        {{ __('admin.owner_image.copy_success') }}
                                    </span>
                                @endif
                            </dd>
                        </div>
                    @endif
                @endforeach
            </dl>

            <div class="mt-4 flex flex-wrap gap-3">
                @if (filled($presentation->media['preview_url'] ?? null))
                    <a
                        href="{{ $presentation->media['preview_url'] }}"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="text-primary-600 hover:text-primary-500 dark:text-primary-400 text-sm font-medium"
                    >
                        {{ __('admin.owner_image.actions.open_preview') }}
                    </a>
                @endif

                @if (filled($presentation->media['download_url'] ?? null))
                    <a
                        href="{{ $presentation->media['download_url'] }}"
                        class="text-primary-600 hover:text-primary-500 dark:text-primary-400 text-sm font-medium"
                        data-testid="owner-image-download"
                    >
                        {{ __('admin.owner_image.actions.download') }}
                    </a>
                @endif

                @if (filled($presentation->media['review_url'] ?? null))
                    <a
                        href="{{ $presentation->media['review_url'] }}"
                        class="text-primary-600 hover:text-primary-500 dark:text-primary-400 text-sm font-medium"
                    >
                        {{ __('admin.owner_image.actions.review_media') }}
                    </a>
                @endif
            </div>
        </section>
    @endif

    @if ($presentation->reviewMedia !== [])
        <section class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
            <h3 class="mb-2 text-sm font-semibold text-gray-950 dark:text-white">
                {{ __('admin.owner_image.related_media') }}
            </h3>

            <ul class="space-y-2 text-sm">
                @foreach ($presentation->reviewMedia as $reviewMedia)
                    <li>
                        <a
                            href="{{ $reviewMedia['url'] }}"
                            class="text-primary-600 hover:text-primary-500 dark:text-primary-400 font-medium"
                        >
                            {{ $reviewMedia['label'] }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    <p class="text-sm text-gray-600 dark:text-gray-300">{{ __('admin.owner_image.automatic_fallback_hint') }}</p>
</div>
