@props([
    'mediaUrl',
    'embedUrl' => null,
    'title' => null,
])

@php
    $embedUrl = filled($embedUrl) ? (string) $embedUrl : null;
    $mediaUrl = (string) $mediaUrl;
    $scheme = $embedUrl ? parse_url($embedUrl, PHP_URL_SCHEME) : null;
    $host = $embedUrl ? strtolower((string) parse_url($embedUrl, PHP_URL_HOST)) : null;
    $allowedHosts = array_map('strtolower', config('media.embeds.allowed_hosts', []));
    $canRenderEmbed = $embedUrl
        && $scheme === 'https'
        && ! str_contains($embedUrl, '<')
        && ! str_contains($embedUrl, '>')
        && in_array($host, $allowedHosts, true);
@endphp

<section {{ $attributes->merge(['class' => 'space-y-3']) }} aria-labelledby="media-heading">
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
    @else
        <a
            href="{{ $mediaUrl }}"
            target="_blank"
            rel="noopener noreferrer"
            class="inline-flex w-fit rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600 dark:bg-primary-500 dark:hover:bg-primary-400"
        >
            {{ __('public.media.open_source') }}
        </a>
    @endif
</section>
