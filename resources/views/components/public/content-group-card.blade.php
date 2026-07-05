@props([
    'group',
    'cardTemplate' => null,
    'displayConfig' => [],
])

@php
    $templateRenderer = app(\App\Support\PublicFront\Cards\PublicFrontCardTemplateRenderer::class);
    $cardTemplate ??= $templateRenderer->resolve('content_group');
    $templateAttributes = $templateRenderer->compatibilityAttributes($cardTemplate);
    $groupUrl = \App\Filament\Public\Pages\ShowContentGroup::getUrl(['contentGroupSlug' => $group->slug], panel: 'public');
    $coverUrl = $group->cover_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($group->cover_path) : null;
    $excerpt = str($group->description_markdown ?? '')->stripTags()->squish()->limit(150);
    $categories = ($group->relationLoaded('categories') ? $group->categories : collect())
        ->where('is_visible', true)
        ->values();
    $publicItemsCount = (int) ($group->public_content_items_count ?? $group->published_content_items_count ?? 0);
    $itemLabel = $publicItemsCount === 1
        ? $group->default_item_type_label_singular
        : $group->default_item_type_label_plural;
    $initials = str($group->title)
        ->squish()
        ->substr(0, 2)
        ->upper();
@endphp

<article
    {{ $attributes->merge(['class' => 'group overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm transition hover:border-primary-300 hover:shadow-md dark:border-gray-800 dark:bg-gray-900 dark:hover:border-primary-500']) }}
    data-card-template-family="{{ $templateAttributes['data-card-template-family'] }}"
    data-card-template-key="{{ $templateAttributes['data-card-template-key'] }}"
    data-card-template-layout="{{ $templateAttributes['data-card-template-layout'] }}"
    data-card-template-parts="{{ $templateAttributes['data-card-template-parts'] }}"
>
    <a href="{{ $groupUrl }}" class="block focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600">
        @if ($coverUrl)
            <span class="block aspect-square w-full overflow-hidden bg-gray-100 dark:bg-gray-800">
                <img src="{{ $coverUrl }}" alt="" class="h-full w-full object-cover" loading="lazy">
            </span>
        @else
            <div class="flex aspect-square w-full items-center justify-center bg-gray-100 text-2xl font-semibold text-gray-500 dark:bg-gray-800 dark:text-gray-300" data-test="content-group-fallback">
                {{ $initials }}
            </div>
        @endif

        <div class="space-y-3 p-4">
            <x-public.type-label :label="$displayConfig['group_label_singular'] ?? $group->group_type_label_singular" />

            <h2 class="text-lg font-semibold leading-snug text-gray-950 group-hover:text-primary-800 dark:text-white dark:group-hover:text-primary-200">
                {{ $group->title }}
            </h2>

            @if (($displayConfig['show_description'] ?? true) && $excerpt->isNotEmpty())
                <p class="line-clamp-3 text-sm leading-6 text-gray-600 dark:text-gray-300">
                    {{ $excerpt }}
                </p>
            @endif

            @if(($displayConfig['show_categories'] ?? true) && $categories->isNotEmpty())
                <div class="flex flex-wrap gap-2" data-test="content-group-categories">
                    @foreach($categories as $category)
                        <span class="rounded-md border border-gray-200 px-2 py-1 text-xs text-gray-600 dark:border-gray-700 dark:text-gray-300">
                            {{ $category->name }}
                        </span>
                    @endforeach
                </div>
            @endif

            @if($displayConfig['show_episode_count'] ?? true)
                <p class="text-sm font-medium text-gray-700 dark:text-gray-200" data-test="content-group-public-count">
                    {{ __('public.labels.public_group_items_count', ['count' => $publicItemsCount, 'label' => $itemLabel]) }}
                </p>
            @endif
        </div>
    </a>
</article>
