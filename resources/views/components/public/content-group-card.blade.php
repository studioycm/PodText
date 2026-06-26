@props(['group'])

@php
    $groupUrl = \App\Filament\Public\Pages\ShowContentGroup::getUrl(['contentGroupSlug' => $group->slug], panel: 'public');
    $coverUrl = $group->cover_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($group->cover_path) : null;
    $excerpt = str($group->description_markdown ?? '')->stripTags()->squish()->limit(150);
@endphp

<article {{ $attributes->merge(['class' => 'group overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm transition hover:border-primary-300 hover:shadow-md dark:border-gray-800 dark:bg-gray-900 dark:hover:border-primary-500']) }}>
    <a href="{{ $groupUrl }}" class="block focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600">
        @if ($coverUrl)
            <img src="{{ $coverUrl }}" alt="" class="aspect-[16/10] w-full object-cover">
        @else
            <div class="flex aspect-[16/10] items-center justify-center bg-gray-100 text-sm font-medium text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                {{ $group->group_type_label_singular }}
            </div>
        @endif

        <div class="space-y-3 p-4">
            <x-public.type-label :label="$group->group_type_label_singular" />

            <h2 class="text-lg font-semibold leading-snug text-gray-950 group-hover:text-primary-800 dark:text-white dark:group-hover:text-primary-200">
                {{ $group->title }}
            </h2>

            @if ($excerpt->isNotEmpty())
                <p class="line-clamp-3 text-sm leading-6 text-gray-600 dark:text-gray-300">
                    {{ $excerpt }}
                </p>
            @endif

            <p class="text-sm font-medium text-gray-700 dark:text-gray-200">
                {{ trans_choice('public.labels.published_items_count', $group->published_content_items_count, ['count' => $group->published_content_items_count]) }}
            </p>
        </div>
    </a>
</article>
