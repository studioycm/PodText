@props([
    'mediaUrl' => null,
    'embedUrl' => null,
    'title' => null,
    'provider' => null,
    'sourceTitle' => null,
    'sourceDescription' => null,
    'durationSeconds' => null,
    'publishedAt' => null,
])

@php
    $embedUrl = filled($embedUrl) ? trim((string) $embedUrl) : null;
    $mediaUrl = filled($mediaUrl) ? trim((string) $mediaUrl) : null;
    $allowedHosts = array_map('strtolower', config('media.embeds.allowed_hosts', []));
    $safeEmbedHost = $embedUrl ? strtolower((string) parse_url($embedUrl, PHP_URL_HOST)) : null;
    $safeMediaHost = $mediaUrl ? parse_url($mediaUrl, PHP_URL_HOST) : null;
    $canRenderEmbed = $embedUrl
        && ! str_contains($embedUrl, '<')
        && ! str_contains($embedUrl, '>')
        && filter_var($embedUrl, FILTER_VALIDATE_URL) !== false
        && parse_url($embedUrl, PHP_URL_SCHEME) === 'https'
        && in_array($safeEmbedHost, $allowedHosts, true);
    $canRenderSource = $mediaUrl
        && ! str_contains($mediaUrl, '<')
        && ! str_contains($mediaUrl, '>')
        && filter_var($mediaUrl, FILTER_VALIDATE_URL) !== false
        && parse_url($mediaUrl, PHP_URL_SCHEME) === 'https';
    $providerLabel = filled($provider)
        ? \Illuminate\Support\Str::of((string) $provider)->replace(['-', '_'], ' ')->headline()->toString()
        : null;
    $duration = $durationSeconds
        ? gmdate($durationSeconds >= 3600 ? 'H:i:s' : 'i:s', (int) $durationSeconds)
        : null;
    $publishedDate = $publishedAt instanceof \Illuminate\Support\Carbon
        ? $publishedAt->timezone('Asia/Jerusalem')->format('d/m/Y')
        : null;
@endphp

<section {{ $attributes->merge(['class' => 'space-y-3']) }} aria-labelledby="media-heading" data-test="media-embed">
    <h2 id="media-heading" class="text-xl font-semibold text-gray-950 dark:text-white">
        {{ __('public.media.heading') }}
    </h2>

    @if ($canRenderEmbed)
        <div x-data="{ loaded: false }" class="relative aspect-video overflow-hidden rounded-lg bg-gray-100 ring-1 ring-gray-950/10 dark:bg-gray-800 dark:ring-white/10">
            <div
                x-show="! loaded"
                class="absolute inset-0 grid place-items-center text-sm text-gray-600 dark:text-gray-300"
            >
                {{ __('public.media.loading') }}
            </div>

            <iframe
                src="{{ $embedUrl }}"
                title="{{ $title ?: __('public.media.embed_title') }}"
                class="h-full w-full"
                loading="lazy"
                referrerpolicy="strict-origin-when-cross-origin"
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                allowfullscreen
                x-on:load="loaded = true"
            ></iframe>
        </div>
    @elseif ($canRenderSource)
        <a
            href="{{ $mediaUrl }}"
            target="_blank"
            rel="noopener noreferrer"
            class="inline-flex w-fit rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600 dark:bg-primary-500 dark:hover:bg-primary-400"
            data-test="media-source-link"
        >
            {{ __('public.media.open_source') }}
        </a>
    @endif

    @if($providerLabel || filled($sourceTitle) || filled($sourceDescription) || $duration || $publishedDate || $safeMediaHost)
        <dl class="space-y-2 text-sm text-gray-600 dark:text-gray-300">
            @if($providerLabel)
                <div data-test="media-provider">
                    <dt class="sr-only">{{ __('public.media.provider') }}</dt>
                    <dd>{{ $providerLabel }}</dd>
                </div>
            @endif

            @if(filled($sourceTitle))
                <div data-test="media-title">
                    <dt class="sr-only">{{ __('public.media.source_title') }}</dt>
                    <dd class="font-medium text-gray-900 dark:text-white">{{ $sourceTitle }}</dd>
                </div>
            @endif

            @if(filled($sourceDescription))
                <div data-test="media-description">
                    <dt class="sr-only">{{ __('public.media.source_description') }}</dt>
                    <dd>{{ $sourceDescription }}</dd>
                </div>
            @endif

            @if($duration)
                <div data-test="media-duration">
                    <dt class="sr-only">{{ __('public.labels.duration') }}</dt>
                    <dd>{{ __('public.labels.duration_value', ['duration' => $duration]) }}</dd>
                </div>
            @endif

            @if($publishedDate)
                <div data-test="media-published-at">
                    <dt class="sr-only">{{ __('public.labels.published_at') }}</dt>
                    <dd>{{ $publishedDate }}</dd>
                </div>
            @endif

            @if($safeMediaHost)
                <div data-test="media-source-host">
                    <dt class="sr-only">{{ __('public.media.source_host') }}</dt>
                    <dd>{{ $safeMediaHost }}</dd>
                </div>
            @endif
        </dl>
    @endif
</section>
