@props(['profile'])

@inject('renderer', 'App\Support\PublicFront\About\PublicAboutPageRenderer')

@php
    $imageUrl = $renderer->imageUrl($profile['image_path'] ?? null);
@endphp

<article
    {{ $attributes->merge(['class' => 'flex h-full min-w-0 gap-4 rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900']) }}
    data-test="about-team-profile"
    data-profile-key="{{ $profile['key'] }}"
>
    @if($imageUrl)
        <img
            src="{{ $imageUrl }}"
            alt="{{ $profile['name'] }}"
            class="size-20 shrink-0 rounded-full object-cover"
            loading="lazy"
            data-test="about-team-profile-image"
        >
    @endif

    <div class="min-w-0 space-y-2">
        @if(filled($profile['title'] ?? null))
            <p class="text-xs font-medium uppercase tracking-normal text-primary-700 dark:text-primary-300">
                {{ $profile['title'] }}
            </p>
        @endif

        <h3 class="text-lg font-semibold tracking-normal text-gray-950 dark:text-white" data-test="about-team-profile-name">
            {{ $profile['name'] }}
        </h3>

        @if(filled($profile['description'] ?? null))
            <p class="text-sm leading-6 text-gray-600 dark:text-gray-300">
                {{ $profile['description'] }}
            </p>
        @endif
    </div>
</article>
