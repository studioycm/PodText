<?php

namespace App\Support\PublicFront\Cards;

use App\Models\Author;

class PublicContributorCardPresenter
{
    public function __construct(
        private readonly PublicFrontCardTemplateRenderer $renderer,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function present(
        Author $author,
        string $fullPageUrl,
        PublicFrontCardTemplate $template,
        bool $compact = false,
        bool $selected = false,
    ): array {
        $presentation = $this->renderer->contributorPresentation($template, $compact);
        $transcriptionsCount = (int) ($author->public_transcriptions_count ?? 0);
        $contentItemsCount = (int) ($author->public_content_items_count ?? 0);

        $data = [
            'author' => $author,
            'url' => $fullPageUrl,
            'name' => (string) $author->name,
            'bio' => $this->plainText($author->bio_markdown),
            'initial' => str($author->name)->squish()->substr(0, 1)->upper()->toString(),
            'compact' => $compact,
            'selected' => $selected,
            'selected_classes' => $selected
                ? 'border-primary-400 ring-2 ring-primary-200 dark:border-primary-500 dark:ring-primary-900'
                : 'border-gray-200 hover:border-primary-300 dark:border-gray-800 dark:hover:border-primary-700',
            'counts' => [
                'transcriptions' => $transcriptionsCount,
                'content_items' => $contentItemsCount,
                'transcriptions_label' => trans_choice('public.labels.public_transcriptions_count', $transcriptionsCount, ['count' => $transcriptionsCount]),
                'content_items_label' => trans_choice('public.labels.public_content_items_count', $contentItemsCount, ['count' => $contentItemsCount]),
            ],
        ];

        $parts = $this->parts($template, $data, $presentation, $compact);

        return [
            ...$data,
            'presentation' => $presentation,
            'template_attributes' => $this->renderer->compatibilityAttributes($template),
            'parts' => $parts,
            'body_parts' => collect($parts)->where('region', 'body')->values()->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $presentation
     * @return array<int, array<string, mixed>>
     */
    private function parts(PublicFrontCardTemplate $template, array $data, array $presentation, bool $compact): array
    {
        $templateParts = $this->renderer->contributorParts($template, $compact);

        if ($templateParts === []) {
            $templateParts = [$this->fallbackTitlePart()];
        }

        return collect($templateParts)
            ->map(fn (PublicFrontCardPart $part): ?array => $this->part($part, $data, $presentation, $compact))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $presentation
     * @return array<string, mixed>|null
     */
    private function part(PublicFrontCardPart $part, array $data, array $presentation, bool $compact): ?array
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
            'class' => $this->partClass($part),
        ];

        return match ($part->type) {
            'image' => $this->imagePart($base, $data),
            'title' => $this->titlePart($base, $part, $data, $presentation, $compact),
            'description' => $this->descriptionPart($base, $part, $data, $presentation),
            'metadata_row' => $this->metadataRowPart($base, $part, $data),
            'entity_attribute' => $this->entityAttributePart($base, $part, $data),
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
     * @return array<string, mixed>
     */
    private function imagePart(array $base, array $data): array
    {
        return [
            ...$base,
            'region' => 'body',
            'initial' => $data['initial'],
        ];
    }

    /**
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $presentation
     * @return array<string, mixed>|null
     */
    private function titlePart(array $base, PublicFrontCardPart $part, array $data, array $presentation, bool $compact): ?array
    {
        $text = $this->textValue($part, $data) ?: $data['name'];

        if (! is_string($text) || blank($text)) {
            return null;
        }

        return [
            ...$base,
            'region' => 'body',
            'url' => $compact ? null : ($this->urlValue($part, $data) ?: $data['url']),
            'text' => $text,
            'class' => $presentation['title'],
            'initial' => $data['initial'],
            'show_avatar' => ! $compact && $presentation['image_size'] === 'hidden',
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
        $text = $this->textValue($part, $data) ?: $data['bio'];

        if (! is_string($text) || blank($text)) {
            return null;
        }

        return [
            ...$base,
            'region' => 'body',
            'text' => str($text)->limit(130)->toString(),
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
        $badge = $this->metadataBadge($part, $data);

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
            'text' => filled($part->label) ? $part->label : __('public.actions.view_contributor'),
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
    private function metadataBadge(PublicFrontCardPart $part, array $data): ?array
    {
        return match ("{$part->source}.{$part->attribute}") {
            'author.transcription_count', 'contributor.transcription_count' => [
                'label' => $data['counts']['transcriptions_label'],
                'test' => 'public-transcriptions-count',
                'title' => $data['counts']['transcriptions_label'],
            ],
            'author.content_item_count', 'contributor.public_item_count' => [
                'label' => $data['counts']['content_items_label'],
                'test' => 'public-content-items-count',
                'title' => $data['counts']['content_items_label'],
            ],
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function textValue(PublicFrontCardPart $part, array $data): ?string
    {
        return match ("{$part->source}.{$part->attribute}") {
            'author.name', 'contributor.name' => $data['name'],
            'author.bio', 'contributor.bio' => $data['bio'],
            'author.transcription_count', 'contributor.transcription_count' => $data['counts']['transcriptions_label'],
            'author.content_item_count', 'contributor.public_item_count' => $data['counts']['content_items_label'],
            'author.url', 'contributor.url' => $data['url'],
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function urlValue(PublicFrontCardPart $part, array $data): ?string
    {
        return match ("{$part->source}.{$part->attribute}") {
            'author.url', 'contributor.url' => $data['url'],
            default => null,
        };
    }

    private function plainText(mixed $text): ?string
    {
        $text = str($text ?? '')->stripTags()->squish()->toString();

        return $text === '' ? null : $text;
    }

    private function partClass(PublicFrontCardPart $part): string
    {
        return match ($part->type) {
            'image' => 'flex items-start gap-3',
            'metadata_row' => 'flex flex-wrap gap-2 text-xs text-gray-600 dark:text-gray-300',
            'action_link' => 'pt-1',
            'entity_attribute' => 'text-sm text-gray-600 dark:text-gray-300',
            'divider' => 'border-t border-gray-200 dark:border-gray-800',
            'spacer' => 'h-2',
            default => 'min-w-0',
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
            source: 'author',
            attribute: 'name',
            label: null,
            labelPosition: null,
            icon: null,
            iconPosition: null,
            layout: 'inline',
            visible: true,
            order: 0,
            lineClamp: null,
            fontSize: null,
            urlTarget: 'self',
            text: null,
        );
    }
}
