<?php

namespace App\Support\PublicFront\Cards;

use App\Filament\Public\Pages\BrowseCategoryContentItems;
use App\Filament\Public\Pages\BrowseTagContentItems;
use App\Filament\Public\Pages\ShowContentGroup;
use App\Filament\Public\Pages\ShowContentItem;
use App\Models\Author;
use App\Models\Category;
use App\Models\ContentItem;
use App\Models\ContentTag;
use App\Models\Transcription;
use App\Support\PublicContent\PublicContentCardOptions;
use App\Support\PublicContent\PublicTranscriptionPolicy;
use App\Support\PublicContent\PublicTranscriptionSelector;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Support\Facades\Storage;

class PublicContentItemCardPresenter
{
    public function __construct(
        private readonly PublicFrontCardTemplateRenderer $renderer,
        private readonly PublicTranscriptionSelector $selector,
        private readonly PublicTranscriptionPolicy $policy,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function present(
        ContentItem $item,
        PublicContentCardOptions $options,
        PublicFrontCardTemplate $template,
        string $layout = 'cards',
        ?Author $contributorContext = null,
    ): array {
        $presentation = $this->renderer->contentItemPresentation($template, $layout);

        return $this->presentWithPresentation($item, $options, $template, $presentation, $contributorContext);
    }

    /**
     * @param  iterable<int, ContentItem>  $items
     * @return array<int, array<string, mixed>>
     */
    public function presentMany(
        iterable $items,
        PublicContentCardOptions $options,
        PublicFrontCardTemplate $template,
        string $layout = 'cards',
        ?Author $contributorContext = null,
    ): array {
        $presentation = $this->renderer->contentItemPresentation($template, $layout);

        $items = $items instanceof Paginator ? $items->getCollection() : collect($items);

        return $items
            ->map(fn (ContentItem $item): array => $this->presentWithPresentation(
                $item,
                $options,
                $template,
                $presentation,
                $contributorContext,
            ))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $presentation
     * @return array<string, mixed>
     */
    private function presentWithPresentation(
        ContentItem $item,
        PublicContentCardOptions $options,
        PublicFrontCardTemplate $template,
        array $presentation,
        ?Author $contributorContext = null,
    ): array {
        $itemUrl = ShowContentItem::getUrl([
            'contentGroupSlug' => $item->contentGroup->slug,
            'contentItemSlug' => $item->slug,
        ], panel: 'public');
        $groupUrl = ShowContentGroup::getUrl([
            'contentGroupSlug' => $item->contentGroup->slug,
        ], panel: 'public');
        $effectiveTranscription = $this->selectedTranscription($item, $contributorContext);
        $effectiveDate = $effectiveTranscription?->published_at?->timezone('Asia/Jerusalem')->format('d/m/Y');
        $originalDate = $item->original_published_at?->timezone('Asia/Jerusalem')->format('d/m/Y');
        $duration = $this->duration($item->duration_seconds);
        $groupCoverUrl = $item->contentGroup->cover_path
            ? Storage::disk('public')->url($item->contentGroup->cover_path)
            : null;
        $imageUrl = $item->external_thumbnail_url ?: $groupCoverUrl;
        $imageSource = $item->external_thumbnail_url ? 'item' : ($groupCoverUrl ? 'group' : 'fallback');
        $titleText = $options->groupBadgeMode === 'combined_title'
            ? $item->contentGroup->title.$options->groupTitleSeparator.$item->title
            : $item->title;
        $categories = $this->categoryLinks($item);
        $tags = $this->tagLinks($item);
        $transcribers = $this->transcribers($effectiveTranscription);
        $publicTranscriptionsCount = $this->publicTranscriptionsCount($item);
        $showTranscriptionCount = $options->transcriptionDisplay === 'effective_plus_count'
            && $this->policy->countModeCountsAllPublished()
            && $publicTranscriptionsCount > 1;

        $data = [
            'id' => $item->getKey(),
            'item' => $item,
            'group' => $item->contentGroup,
            'url' => $itemUrl,
            'group_url' => $groupUrl,
            'title' => $titleText,
            'description' => $this->plainText($item->description_markdown),
            'type_label' => $item->effectiveTypeLabelSingular(),
            'image' => [
                'url' => $imageUrl,
                'source' => $imageSource,
                'fit_class' => $options->imageFitClass(),
                'radius_class' => $options->imageRadiusClass(),
            ],
            'transcribers' => $transcribers,
            'categories' => $categories,
            'tags' => $tags,
            'duration' => $duration,
            'effective_date' => $effectiveDate,
            'original_date' => $originalDate,
            'public_transcriptions_count' => $publicTranscriptionsCount,
            'public_transcriptions_count_label' => trans_choice('public.labels.public_transcriptions_count', $publicTranscriptionsCount, ['count' => $publicTranscriptionsCount]),
            'show_transcription_count' => $showTranscriptionCount,
            'transcription' => [
                'title' => $effectiveTranscription?->title,
                'word_count' => $effectiveTranscription?->word_count,
                'read_time' => $this->readTime($effectiveTranscription?->word_count),
                'reading_time' => $this->readTime($effectiveTranscription?->word_count),
                'published_at' => $effectiveDate,
                'transcribers' => $transcribers,
            ],
            'content_group' => [
                'title' => $item->contentGroup->title,
                'description' => $this->plainText($item->contentGroup->description_markdown),
                'identity' => $item->contentGroup->title,
                'type_label' => $item->contentGroup->group_type_label_singular,
                'url' => $groupUrl,
            ],
        ];

        $parts = $this->parts($template, $data, $options, $presentation);

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
    private function parts(PublicFrontCardTemplate $template, array $data, PublicContentCardOptions $options, array $presentation): array
    {
        $templateParts = $this->renderer->contentItemParts($template);

        if ($templateParts === []) {
            $templateParts = [$this->fallbackTitlePart()];
        }

        return collect($templateParts)
            ->map(fn (PublicFrontCardPart $part): ?array => $this->part($part, $data, $options, $presentation))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $presentation
     * @return array<string, mixed>|null
     */
    private function part(PublicFrontCardPart $part, array $data, PublicContentCardOptions $options, array $presentation): ?array
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
            'part_group' => $this->partGroup($base, $part, $data, $options, $presentation),
            'image' => $this->imagePart($base, $data, $presentation),
            'title' => $this->titlePart($base, $part, $data, $presentation),
            'description' => $this->descriptionPart($base, $part, $data, $options, $presentation),
            'group_identity' => $this->groupIdentityPart($base, $data, $options),
            'transcriber_line' => $this->transcriberLinePart($base, $data, $options),
            'date_read_time' => $this->dateReadTimePart($base, $data, $options),
            'metadata_row' => $this->metadataRowPart($base, $part, $data, $options),
            'taxonomy' => $this->taxonomyPart($base, $part, $data, $options),
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
    private function partGroup(array $base, PublicFrontCardPart $part, array $data, PublicContentCardOptions $options, array $presentation): ?array
    {
        $children = collect($part->children)
            ->map(fn (PublicFrontCardPart $child): ?array => $this->part($child, $data, $options, $presentation))
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
    private function descriptionPart(array $base, PublicFrontCardPart $part, array $data, PublicContentCardOptions $options, array $presentation): ?array
    {
        if (! $options->showDescription || $options->descriptionLines <= 0) {
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
    private function groupIdentityPart(array $base, array $data, PublicContentCardOptions $options): ?array
    {
        if (! $options->showGroupBadge || $options->groupBadgeMode === 'combined_title') {
            return null;
        }

        return [
            ...$base,
            'region' => 'body',
            'group' => $data['group'],
            'mode' => $options->groupBadgeMode,
            'main_image_source' => $data['image']['source'],
            'allow_duplicate_thumbnail' => $options->groupBadgeDuplicateThumbnail,
        ];
    }

    /**
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|null
     */
    private function transcriberLinePart(array $base, array $data, PublicContentCardOptions $options): ?array
    {
        if (! $options->showAuthors || $data['transcribers'] === []) {
            return null;
        }

        return [
            ...$base,
            'region' => 'body',
            'badges' => $data['transcribers'],
        ];
    }

    /**
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|null
     */
    private function dateReadTimePart(array $base, array $data, PublicContentCardOptions $options): ?array
    {
        if (! $options->showEffectiveDate || ! is_string($data['effective_date'])) {
            return null;
        }

        return [
            ...$base,
            'region' => 'body',
            'badges' => [
                [
                    'label' => $data['effective_date'],
                    'test' => 'effective-date',
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|null
     */
    private function metadataRowPart(array $base, PublicFrontCardPart $part, array $data, PublicContentCardOptions $options): ?array
    {
        $badge = $this->metadataBadge($part, $data, $options);

        if ($badge === null) {
            return null;
        }

        return [
            ...$base,
            'region' => 'body',
            'badges' => [$badge],
        ];
    }

    /**
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|null
     */
    private function taxonomyPart(array $base, PublicFrontCardPart $part, array $data, PublicContentCardOptions $options): ?array
    {
        $source = $part->source;

        if ($source === 'categories' && $options->showCategories && $data['categories'] !== []) {
            return [
                ...$base,
                'region' => 'body',
                'links' => $data['categories'],
                'test' => 'item-categories',
                'link_class' => 'rounded-md border border-gray-200 px-2 py-1 text-xs text-gray-600 hover:border-primary-300 hover:text-primary-700 dark:border-gray-700 dark:text-gray-300',
            ];
        }

        if ($source === 'tags' && $options->showTags && $data['tags'] !== []) {
            return [
                ...$base,
                'region' => 'body',
                'links' => $data['tags'],
                'test' => 'item-tags',
                'link_class' => 'rounded-md bg-gray-950 px-2 py-1 text-xs text-white hover:bg-primary-700 dark:bg-gray-100 dark:text-gray-950',
            ];
        }

        return null;
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
     * @return array{label: string, test: string}|null
     */
    private function metadataBadge(PublicFrontCardPart $part, array $data, PublicContentCardOptions $options): ?array
    {
        $key = "{$part->source}.{$part->attribute}";

        if ($key === 'content_item.duration' && (! $options->showDuration || ! is_string($data['duration']))) {
            return null;
        }

        if ($key === 'content_item.transcription_count' && ! $data['show_transcription_count']) {
            return null;
        }

        if (in_array($key, ['content_item.effective_date', 'transcription.published_at'], true)
            && (! $options->showEffectiveDate || ! is_string($data['effective_date']))) {
            return null;
        }

        $value = $this->textValue($part, $data);

        if (! is_string($value) || blank($value)) {
            return null;
        }

        return [
            'label' => $value,
            'test' => match ($key) {
                'content_item.duration' => 'duration',
                'content_item.effective_date', 'transcription.published_at' => 'effective-date',
                default => 'card-metadata',
            },
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function textValue(PublicFrontCardPart $part, array $data): ?string
    {
        return match ("{$part->source}.{$part->attribute}") {
            'content_item.title' => $data['title'],
            'content_item.description' => $data['description'],
            'content_item.duration' => $data['duration'],
            'content_item.effective_date' => $data['effective_date'],
            'content_item.effective_transcription_title' => $data['transcription']['title'],
            'content_item.original_published_at' => $data['original_date'],
            'content_item.read_time', 'content_item.reading_time' => $data['transcription']['read_time'],
            'content_item.transcribers' => collect($data['transcribers'])->pluck('label')->join(', '),
            'content_item.transcription_count' => $data['public_transcriptions_count_label'],
            'content_item.type_label' => $data['type_label'],
            'content_item.media_provider' => $data['item']->embed_provider,
            'content_group.title', 'content_group.identity' => $data['content_group']['title'],
            'content_group.description' => $data['content_group']['description'],
            'content_group.type_label' => $data['content_group']['type_label'],
            'transcription.title' => $data['transcription']['title'],
            'transcription.author_name', 'transcription.transcribers' => collect($data['transcribers'])->pluck('label')->join(', '),
            'transcription.published_at' => $data['transcription']['published_at'],
            'transcription.read_time', 'transcription.reading_time' => $data['transcription']['read_time'],
            'transcription.word_count' => $data['transcription']['word_count'] !== null ? number_format((int) $data['transcription']['word_count']) : null,
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function urlValue(PublicFrontCardPart $part, array $data): ?string
    {
        return match ("{$part->source}.{$part->attribute}") {
            'content_item.url' => $data['url'],
            'content_group.url' => $data['group_url'],
            default => null,
        };
    }

    /**
     * @return array<int, array{label: string}>
     */
    private function transcribers(?Transcription $transcription): array
    {
        if (! $transcription) {
            return [];
        }

        $transcription->loadMissing('authors');

        return collect($transcription->transcriberNames())
            ->map(fn (string $name): array => ['label' => $name])
            ->values()
            ->all();
    }

    private function selectedTranscription(ContentItem $item, ?Author $contributorContext): ?Transcription
    {
        if ($contributorContext instanceof Author && $item->relationLoaded('transcriptions')) {
            $matched = $item->transcriptions
                ->first(function (Transcription $transcription) use ($contributorContext): bool {
                    return $transcription->relationLoaded('authors')
                        && $transcription->authors->contains('id', $contributorContext->getKey());
                });

            if ($matched instanceof Transcription) {
                return $matched;
            }
        }

        return $this->selector->effectiveTranscriptionForItem($item);
    }

    private function publicTranscriptionsCount(ContentItem $item): int
    {
        if (isset($item->public_transcriptions_count)) {
            return (int) $item->public_transcriptions_count;
        }

        return $this->selector->publicTranscriptionsCountForItem($item);
    }

    /**
     * @return array<int, array{label: string, url: string}>
     */
    private function categoryLinks(ContentItem $item): array
    {
        return $item->effectiveCategories()
            ->where('is_visible', true)
            ->values()
            ->map(fn (Category $category): array => [
                'label' => (string) $category->name,
                'url' => BrowseCategoryContentItems::getUrl(['categorySlug' => $category->slug], panel: 'public'),
            ])
            ->all();
    }

    /**
     * @return array<int, array{label: string, url: string}>
     */
    private function tagLinks(ContentItem $item): array
    {
        $tags = $item->relationLoaded('enabledContentTags')
            ? $item->enabledContentTags
            : $item->publicTags();

        return $tags
            ->map(fn (ContentTag $tag): array => [
                'label' => (string) $tag->name,
                'url' => BrowseTagContentItems::getUrl(['tagSlug' => $tag->slug], panel: 'public'),
            ])
            ->values()
            ->all();
    }

    private function duration(?int $seconds): ?string
    {
        if ($seconds === null || $seconds <= 0) {
            return null;
        }

        return gmdate($seconds >= 3600 ? 'H:i:s' : 'i:s', $seconds);
    }

    private function plainText(?string $text): ?string
    {
        $text = str($text ?? '')->stripTags()->squish()->toString();

        return $text === '' ? null : $text;
    }

    private function readTime(?int $wordCount): ?string
    {
        if ($wordCount === null || $wordCount <= 0) {
            return null;
        }

        return max(1, (int) ceil($wordCount / 200)).' min';
    }

    private function partClass(PublicFrontCardPart $part): string
    {
        return match ($part->type) {
            'metadata_row', 'date_read_time', 'transcriber_line' => 'flex flex-wrap gap-2 text-xs text-gray-600 dark:text-gray-300',
            'taxonomy' => 'flex flex-wrap gap-2',
            'action_link' => 'pt-1',
            'part_group' => 'min-w-0',
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
            'stacked' => trim("flex flex-col {$gap} {$items}"),
            'grid' => trim('grid '.$this->groupColumnClass($part->columns).' '.$gap.' '.$justify),
            default => trim("flex flex-wrap items-center {$gap} {$justify}"),
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
            source: 'content_item',
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
