<?php

namespace App\Support\PublicFront\Cards;

use App\Filament\Public\Pages\BrowseCategoryContentItems;
use App\Filament\Public\Pages\ShowContentGroup;
use App\Models\Category;
use App\Models\ContentGroup;
use App\Support\PublicContent\PublicContentCardOptions;
use App\Support\PublicFront\PublicDefaultImageResolver;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\Paginator;

class PublicContentGroupCardPresenter
{
    public function __construct(
        private readonly PublicFrontCardTemplateRenderer $renderer,
        private readonly PublicDefaultImageResolver $defaultImages,
    ) {}

    /**
     * @param  array<string, mixed>  $displayConfig
     * @return array<string, mixed>
     */
    public function present(ContentGroup $group, PublicFrontCardTemplate $template, array $displayConfig = []): array
    {
        $presentation = $this->renderer->contentGroupPresentation($template);

        return $this->presentWithPresentation($group, $template, $displayConfig, $presentation);
    }

    /**
     * @param  iterable<int, ContentGroup>  $groups
     * @param  array<string, mixed>  $displayConfig
     * @return array<int, array<string, mixed>>
     */
    public function presentMany(iterable $groups, PublicFrontCardTemplate $template, array $displayConfig = []): array
    {
        $presentation = $this->renderer->contentGroupPresentation($template);

        $groups = $groups instanceof Paginator ? $groups->getCollection() : collect($groups);

        return $groups
            ->map(fn (ContentGroup $group): array => $this->presentWithPresentation($group, $template, $displayConfig, $presentation))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $displayConfig
     * @param  array<string, mixed>  $presentation
     * @return array<string, mixed>
     */
    private function presentWithPresentation(ContentGroup $group, PublicFrontCardTemplate $template, array $displayConfig, array $presentation): array
    {
        $groupUrl = ShowContentGroup::getUrl(['contentGroupSlug' => $group->slug], panel: 'public');
        $publicItemsCount = (int) ($group->public_content_items_count ?? $group->published_content_items_count ?? 0);
        $publicTranscriptionsCount = (int) ($group->public_transcriptions_count ?? 0);
        $publicTranscriberCount = (int) ($group->public_transcriber_count ?? 0);
        $totalWordCount = (int) ($group->public_total_word_count ?? 0);
        $totalReadingMinutes = $this->readingMinutes($totalWordCount);
        $latestTranscriptionDate = $this->date($group->public_latest_transcription_published_at ?? null);
        $itemLabel = $publicItemsCount === 1
            ? $group->default_item_type_label_singular
            : $group->default_item_type_label_plural;
        $imageFit = $this->option($displayConfig['image_fit'] ?? null, ['cover', 'contain'], 'cover');
        $imageRadius = $this->option($displayConfig['image_radius'] ?? null, [
            'sharp',
            'low_rounded',
            'mid_rounded',
            'high_rounded',
            'round',
            'circle',
        ], 'mid_rounded');
        $image = $this->defaultImages->contentGroupImage($group);

        $data = [
            'id' => $group->getKey(),
            'group' => $group,
            'url' => $groupUrl,
            'title' => (string) $group->title,
            'description' => $this->plainText($group->description_markdown),
            'type_label' => $this->plainText($displayConfig['group_label_singular'] ?? null)
                ?? (string) $group->group_type_label_singular,
            'image' => [
                'url' => $image['url'],
                'source' => $image['source'],
                'path' => $image['path'],
                'fit' => $imageFit,
                'fit_class' => $imageFit === 'contain' ? 'object-contain' : 'object-cover',
                'radius' => $imageRadius,
                'radius_class' => PublicContentCardOptions::radiusClass($imageRadius),
                'initials' => str($group->title)->squish()->substr(0, 2)->upper()->toString(),
            ],
            'categories' => $this->categoryLinks($group),
            'public_items_count' => $publicItemsCount,
            'public_items_count_label' => __('public.labels.public_group_items_count', [
                'count' => $publicItemsCount,
                'label' => $itemLabel,
            ]),
            'public_transcriptions_count' => $publicTranscriptionsCount,
            'public_transcriptions_count_label' => trans_choice('public.labels.public_transcriptions_count', $publicTranscriptionsCount, ['count' => $publicTranscriptionsCount]),
            'public_transcriber_count' => $publicTranscriberCount,
            'public_transcriber_count_label' => trans_choice('public.labels.public_transcribers_count', $publicTranscriberCount, ['count' => $publicTranscriberCount]),
            'total_reading_minutes' => $totalReadingMinutes,
            'total_reading_minutes_label' => trans_choice('public.labels.public_group_reading_minutes_count', $totalReadingMinutes, ['count' => $totalReadingMinutes]),
            'latest_transcription_date' => $latestTranscriptionDate,
            'latest_transcription_date_label' => $latestTranscriptionDate
                ? __('public.labels.public_group_latest_transcription_date', ['date' => $latestTranscriptionDate])
                : null,
            'display' => [
                'show_description' => $this->boolean($displayConfig['show_description'] ?? null, true),
                'show_categories' => $this->boolean($displayConfig['show_categories'] ?? null, true),
                'show_episode_count' => $this->boolean($displayConfig['show_episode_count'] ?? null, true),
            ],
        ];

        $parts = $this->parts($template, $data, $presentation);

        return [
            ...$data,
            'presentation' => $presentation,
            'template_attributes' => $this->renderer->compatibilityAttributes($template),
            'parts' => $parts,
            'media_parts' => collect($parts)->where('region', 'media')->values()->all(),
            'body_parts' => collect($parts)->where('region', 'body')->values()->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $presentation
     * @return array<int, array<string, mixed>>
     */
    private function parts(PublicFrontCardTemplate $template, array $data, array $presentation): array
    {
        $templateParts = $this->renderer->contentGroupParts($template);

        if ($templateParts === []) {
            $templateParts = [$this->fallbackTitlePart()];
        }

        return collect($templateParts)
            ->map(fn (PublicFrontCardPart $part): ?array => $this->part($part, $data, $presentation))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $presentation
     * @return array<string, mixed>|null
     */
    private function part(PublicFrontCardPart $part, array $data, array $presentation): ?array
    {
        $base = [
            'key' => "{$part->type}-{$part->source}-{$part->attribute}-{$part->order}",
            'type' => $part->type,
            'source' => $part->source,
            'attribute' => $part->attribute,
            'order' => $part->order,
            'layout' => $part->layout,
            'label' => $part->label,
            'label_position' => $part->labelPosition,
            'label_alignment' => $part->labelAlignment,
            'icon' => $part->icon,
            'icon_position' => $part->iconPosition,
            'class' => $this->partClass($part),
        ];

        return match ($part->type) {
            'part_group' => $this->partGroup($base, $part, $data, $presentation),
            'image' => $this->imagePart($base, $data, $presentation),
            'entity_attribute' => $this->entityAttributePart($base, $part, $data),
            'title' => $this->titlePart($base, $part, $data, $presentation),
            'description' => $this->descriptionPart($base, $part, $data, $presentation),
            'metadata_row' => $this->metadataRowPart($base, $part, $data),
            'taxonomy' => $this->taxonomyPart($base, $part, $data),
            'action_link' => $this->actionLinkPart($base, $part, $data),
            'custom_text' => $this->customTextPart($base, $part),
            'divider' => [...$base, 'region' => 'body'],
            'spacer' => [...$base, 'region' => 'body'],
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $presentation
     * @return array<string, mixed>|null
     */
    private function partGroup(array $base, PublicFrontCardPart $part, array $data, array $presentation): ?array
    {
        $children = collect($part->children)
            ->map(fn (PublicFrontCardPart $child): ?array => $this->part($child, $data, $presentation))
            ->filter(fn (?array $child): bool => $child !== null && ($child['region'] ?? null) === 'body')
            ->values()
            ->all();

        if ($children === []) {
            return null;
        }

        return [
            ...$base,
            'region' => 'body',
            'children' => $children,
            'columns' => $part->columns ?? 'auto',
            'gap' => $part->gap ?? 'compact',
            'alignment' => $part->alignment ?? 'start',
            'children_class' => $this->groupChildrenClass($part),
        ];
    }

    /**
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $presentation
     * @return array<string, mixed>|null
     */
    private function imagePart(array $base, array $data, array $presentation): ?array
    {
        if ($presentation['image_size'] === 'hidden') {
            return null;
        }

        return [
            ...$base,
            'region' => 'media',
            'url' => $data['url'],
            'title' => $data['title'],
            'image' => $data['image'],
            'type_label' => $data['type_label'],
        ];
    }

    /**
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|null
     */
    private function entityAttributePart(array $base, PublicFrontCardPart $part, array $data): ?array
    {
        $text = $this->textValue($part, $data);

        if (! is_string($text) || blank($text)) {
            return null;
        }

        return [
            ...$base,
            'region' => 'body',
            'text' => $text,
            'test' => $part->source === 'content_group' && $part->attribute === 'type_label'
                ? 'content-group-type-label'
                : 'content-group-attribute',
        ];
    }

    /**
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $presentation
     * @return array<string, mixed>|null
     */
    private function titlePart(array $base, PublicFrontCardPart $part, array $data, array $presentation): ?array
    {
        $text = $this->textValue($part, $data) ?: $data['title'];

        if (! is_string($text) || blank($text)) {
            return null;
        }

        return [
            ...$base,
            'region' => 'body',
            'url' => $this->urlValue($part, $data) ?: $data['url'],
            'text' => $text,
            'class' => $presentation['title'],
        ];
    }

    /**
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $presentation
     * @return array<string, mixed>|null
     */
    private function descriptionPart(array $base, PublicFrontCardPart $part, array $data, array $presentation): ?array
    {
        if (! $data['display']['show_description']) {
            return null;
        }

        $text = $this->textValue($part, $data) ?: $data['description'];

        if (! is_string($text) || blank($text)) {
            return null;
        }

        return [
            ...$base,
            'region' => 'body',
            'text' => $text,
            'class' => $presentation['description'],
        ];
    }

    /**
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|null
     */
    private function metadataRowPart(array $base, PublicFrontCardPart $part, array $data): ?array
    {
        if ($part->source === 'content_group' && $part->attribute === 'item_count') {
            if (! $data['display']['show_episode_count']) {
                return null;
            }

            return [
                ...$base,
                'region' => 'body',
                'badges' => [[
                    'label' => $data['public_items_count_label'],
                    'test' => 'content-group-public-count',
                ]],
            ];
        }

        if ($part->source === 'content_group' && $part->attribute === 'public_episode_count') {
            if (! $data['display']['show_episode_count']) {
                return null;
            }

            return [
                ...$base,
                'region' => 'body',
                'badges' => [[
                    'label' => $data['public_items_count_label'],
                    'test' => 'content-group-public-count',
                ]],
            ];
        }

        $text = $this->textValue($part, $data);

        if (! is_string($text) || blank($text)) {
            return null;
        }

        return [
            ...$base,
            'region' => 'body',
            'badges' => [[
                'label' => $text,
                'test' => 'content-group-metadata',
            ]],
        ];
    }

    /**
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|null
     */
    private function taxonomyPart(array $base, PublicFrontCardPart $part, array $data): ?array
    {
        if (! $data['display']['show_categories'] || $part->source !== 'categories' || $data['categories'] === []) {
            return null;
        }

        return [
            ...$base,
            'region' => 'body',
            'links' => $data['categories'],
            'test' => 'content-group-categories',
        ];
    }

    /**
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|null
     */
    private function actionLinkPart(array $base, PublicFrontCardPart $part, array $data): ?array
    {
        $url = $this->urlValue($part, $data) ?: $data['url'];

        if (! is_string($url) || blank($url)) {
            return null;
        }

        return [
            ...$base,
            'region' => 'body',
            'url' => $url,
            'text' => filled($part->label) ? $part->label : __('public.actions.view_more'),
            'target' => $part->urlTarget === 'blank' ? '_blank' : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $base
     * @return array<string, mixed>|null
     */
    private function customTextPart(array $base, PublicFrontCardPart $part): ?array
    {
        $text = $this->plainText($part->text);

        if ($text === null) {
            return null;
        }

        return [
            ...$base,
            'region' => 'body',
            'text' => $text,
            'class' => trim('leading-6 text-gray-700 dark:text-gray-200 '.$this->fontSizeClass($part->fontSize).' '.$this->lineClampClass($part->lineClamp)),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function textValue(PublicFrontCardPart $part, array $data): ?string
    {
        return match ("{$part->source}.{$part->attribute}") {
            'content_group.title', 'content_group.identity' => $data['title'],
            'content_group.description' => $data['description'],
            'content_group.type_label' => $data['type_label'],
            'content_group.item_count', 'content_group.public_episode_count' => $data['public_items_count_label'],
            'content_group.transcription_count' => $data['public_transcriptions_count_label'],
            'content_group.total_reading_time' => $data['total_reading_minutes'] > 0 ? $data['total_reading_minutes_label'] : null,
            'content_group.latest_transcription_date' => $data['latest_transcription_date_label'],
            'content_group.transcriber_count' => $data['public_transcriber_count_label'],
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function urlValue(PublicFrontCardPart $part, array $data): ?string
    {
        return match ("{$part->source}.{$part->attribute}") {
            'content_group.url' => $data['url'],
            default => null,
        };
    }

    /**
     * @return array<int, array{label: string, url: string}>
     */
    private function categoryLinks(ContentGroup $group): array
    {
        if (! $group->relationLoaded('categories')) {
            return [];
        }

        return $group->categories
            ->where('is_visible', true)
            ->values()
            ->map(fn (Category $category): array => [
                'label' => (string) $category->name,
                'url' => BrowseCategoryContentItems::getUrl(['categorySlug' => $category->slug], panel: 'public'),
            ])
            ->all();
    }

    private function boolean(mixed $value, bool $fallback): bool
    {
        return is_bool($value) ? $value : $fallback;
    }

    /**
     * @param  array<int, string>  $allowed
     */
    private function option(mixed $value, array $allowed, string $fallback): string
    {
        return is_string($value) && in_array($value, $allowed, true) ? $value : $fallback;
    }

    private function plainText(mixed $text): ?string
    {
        $text = str($text ?? '')->stripTags()->squish()->toString();

        return $text === '' ? null : $text;
    }

    private function readingMinutes(int $wordCount): int
    {
        if ($wordCount <= 0) {
            return 0;
        }

        return max(1, (int) ceil($wordCount / 200));
    }

    private function date(mixed $date): ?string
    {
        if ($date === null || $date === '') {
            return null;
        }

        return Carbon::parse($date)->timezone('Asia/Jerusalem')->format('d/m/Y');
    }

    private function partClass(PublicFrontCardPart $part): string
    {
        return match ($part->type) {
            'metadata_row' => 'flex flex-wrap gap-2 text-sm font-medium text-gray-700 dark:text-gray-200',
            'taxonomy' => 'flex flex-wrap gap-2',
            'action_link' => 'pt-1',
            'entity_attribute' => 'flex flex-wrap gap-2',
            'part_group' => 'w-full',
            'divider' => 'border-t border-gray-200 dark:border-gray-800',
            'spacer' => 'h-2',
            default => 'min-w-0',
        };
    }

    private function groupChildrenClass(PublicFrontCardPart $part): string
    {
        $gap = match ($part->gap) {
            'comfortable' => 'gap-2',
            'spacious' => 'gap-4',
            default => 'gap-1.5',
        };
        $justify = match ($part->alignment) {
            'center' => 'justify-center',
            'end' => 'justify-end',
            'between' => 'justify-between',
            default => 'justify-start',
        };
        $items = match ($part->alignment) {
            'center' => 'items-center',
            'end' => 'items-end',
            default => 'items-start',
        };

        return match ($part->layout) {
            'stacked' => trim("flex w-full flex-col {$gap} {$items}"),
            'grid' => trim('grid w-full '.$this->groupColumnClass($part->columns).' '.$gap.' '.$justify),
            default => trim("flex w-full flex-wrap items-center {$gap} {$justify}"),
        };
    }

    private function groupColumnClass(?string $columns): string
    {
        return match ($columns) {
            '1' => 'grid-cols-1',
            '2' => 'grid-cols-2',
            '3' => 'grid-cols-3',
            '4' => 'grid-cols-4',
            default => 'grid-flow-col auto-cols-max',
        };
    }

    private function fontSizeClass(?string $fontSize): string
    {
        return match ($fontSize) {
            'xs' => 'text-xs',
            'base' => 'text-base',
            'lg' => 'text-lg',
            default => 'text-sm',
        };
    }

    private function lineClampClass(?int $lines): string
    {
        return match ($lines) {
            0 => 'line-clamp-none',
            1 => 'line-clamp-1',
            2 => 'line-clamp-2',
            4 => 'line-clamp-4',
            default => $lines === 5 ? 'line-clamp-5' : '',
        };
    }

    private function fallbackTitlePart(): PublicFrontCardPart
    {
        return new PublicFrontCardPart(
            type: 'title',
            source: 'content_group',
            attribute: 'title',
            label: null,
            labelPosition: null,
            labelAlignment: null,
            icon: null,
            iconPosition: null,
            layout: 'inline',
            columns: null,
            gap: null,
            alignment: null,
            visible: true,
            order: 0,
            lineClamp: null,
            fontSize: null,
            urlTarget: 'self',
            text: null,
        );
    }
}
