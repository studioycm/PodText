@props(['profiles', 'settings' => [], 'block' => []])

@php
    $heading = $block['heading'] ?? $settings['team_heading'] ?? __('public.pages.about.team_heading');
    $description = $block['body'] ?? $settings['team_description'] ?? null;
    $teamCard = $settings['team_card'] ?? [];
    $layout = $teamCard['layout'] ?? $settings['team_layout'] ?? 'grid';
@endphp

<section class="space-y-5" data-test="about-team-section">
    <div class="space-y-2">
        @if(filled($heading))
            <h2 class="text-2xl font-semibold tracking-normal text-gray-950 dark:text-white">
                {{ $heading }}
            </h2>
        @endif

        @if(filled($description))
            <p class="max-w-3xl text-base leading-7 text-gray-600 dark:text-gray-300">
                {{ $description }}
            </p>
        @endif
    </div>

    <div @class([
        'grid gap-4',
        'grid-cols-1' => $layout === 'list',
        'grid-cols-1 md:grid-cols-2 xl:grid-cols-3' => $layout !== 'list',
    ])>
        @foreach($profiles as $profile)
            <x-public.about.profile-card
                :profile="$profile"
                :settings="$teamCard"
                :layout="$layout"
                wire:key="about-team-profile-{{ $profile['key'] }}"
            />
        @endforeach
    </div>
</section>
