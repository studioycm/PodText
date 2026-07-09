@props([
    'podcastIdentity',
])

@php
    $podcastIcon = \App\Support\PublicFront\Cards\PublicFrontCardIconResolver::resolve($podcastIdentity['icon'] ?? null);
    $podcastIconPosition = $podcastIdentity['icon_position'] ?? 'hidden';
@endphp

<a
    href="{{ $podcastIdentity['url'] }}"
    class="{{ $podcastIdentity['class'] }}"
    @if(filled($podcastIdentity['style'] ?? null)) style="{{ $podcastIdentity['style'] }}" @endif
    data-test="item-podcast-identity"
    data-podcast-identity-mode="{{ $podcastIdentity['mode'] }}"
    data-podcast-identity-position="{{ $podcastIdentity['position'] }}"
    data-podcast-identity-size="{{ $podcastIdentity['size'] }}"
    data-podcast-identity-color="{{ $podcastIdentity['color'] }}"
    data-podcast-identity-icon="{{ $podcastIdentity['icon'] ?? 'none' }}"
    data-podcast-identity-icon-position="{{ $podcastIconPosition }}"
>
    @if($podcastIcon && $podcastIconPosition === 'inline_before')
        <x-filament::icon :icon="$podcastIcon" class="h-4 w-4 shrink-0" />
    @endif

    <span class="min-w-0 truncate">{{ $podcastIdentity['label'] }}</span>

    @if($podcastIcon && $podcastIconPosition === 'inline_after')
        <x-filament::icon :icon="$podcastIcon" class="h-4 w-4 shrink-0" />
    @endif
</a>
