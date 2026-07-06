@props(['profile', 'settings' => [], 'layout' => 'grid'])

@inject('renderer', 'App\Support\PublicFront\About\PublicAboutPageRenderer')

@php
    $imageUrl = $renderer->imageUrl($profile['image_path'] ?? null);
    $showImage = ($settings['show_image'] ?? true) === true;
    $showTitle = ($settings['show_title'] ?? true) === true;
    $showDescription = ($settings['show_description'] ?? true) === true;
    $imageSize = match ($settings['image_size'] ?? 'medium') {
        'small' => 'size-14',
        'large' => 'size-28',
        default => 'size-20',
    };
    $imageFit = in_array($settings['image_fit'] ?? null, ['cover', 'contain'], true) ? $settings['image_fit'] : 'cover';
    $imageFitClass = $imageFit === 'contain' ? 'object-contain' : 'object-cover';
    $imageRadius = in_array($settings['image_radius'] ?? null, ['sharp', 'low_rounded', 'mid_rounded', 'high_rounded', 'round', 'circle'], true)
        ? $settings['image_radius']
        : 'circle';
    $imageRadiusClass = \App\Support\PublicContent\PublicContentCardOptions::radiusClass($imageRadius);
    $densityClasses = ($settings['density'] ?? 'comfortable') === 'compact'
        ? 'gap-3 p-3'
        : 'gap-4 p-4';
    $descriptionLines = max(0, min(6, (int) ($settings['description_lines'] ?? 3)));
    $descriptionClamp = match ($descriptionLines) {
        0 => '',
        1 => 'line-clamp-1',
        2 => 'line-clamp-2',
        4 => 'line-clamp-4',
        5 => 'line-clamp-5',
        6 => 'line-clamp-6',
        default => 'line-clamp-3',
    };
@endphp

<article
    {{ $attributes->merge(['class' => "flex h-full min-w-0 rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900 {$densityClasses}"]) }}
    data-test="about-team-profile"
    data-profile-key="{{ $profile['key'] }}"
    data-team-card-layout="{{ $layout }}"
    data-team-card-density="{{ $settings['density'] ?? 'comfortable' }}"
    data-team-card-image-size="{{ $settings['image_size'] ?? 'medium' }}"
    data-team-card-image-fit="{{ $imageFit }}"
    data-team-card-image-radius="{{ $imageRadius }}"
>
    @if($showImage && $imageUrl)
        <img
            src="{{ $imageUrl }}"
            alt="{{ $profile['name'] }}"
            class="{{ $imageSize }} {{ $imageRadiusClass }} {{ $imageFitClass }} shrink-0 bg-gray-100 dark:bg-gray-800"
            loading="lazy"
            data-test="about-team-profile-image"
        >
    @endif

    <div class="min-w-0 space-y-2">
        @if($showTitle && filled($profile['title'] ?? null))
            <p class="text-xs font-medium uppercase tracking-normal text-primary-700 dark:text-primary-300">
                {{ $profile['title'] }}
            </p>
        @endif

        <h3 class="text-lg font-semibold tracking-normal text-gray-950 dark:text-white" data-test="about-team-profile-name">
            {{ $profile['name'] }}
        </h3>

        @if($showDescription && filled($profile['description'] ?? null))
            <p class="text-sm leading-6 text-gray-600 dark:text-gray-300 {{ $descriptionClamp }}" data-test="about-team-profile-description">
                {{ $profile['description'] }}
            </p>
        @endif
    </div>
</article>
