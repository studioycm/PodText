<?php

namespace App\Support\PublicFront\Cards;

class PublicFrontCardTemplateRenderer
{
    private const CONTROLLED_CONTENT_ITEM_PARTS = [
        'image',
        'title',
        'description',
        'group_identity',
        'transcriber_line',
        'date_read_time',
        'taxonomy',
        'metadata_row',
        'action_link',
        'custom_text',
        'part_group',
        'divider',
        'spacer',
    ];

    private const CONTROLLED_CONTENT_GROUP_PARTS = [
        'image',
        'entity_attribute',
        'title',
        'description',
        'metadata_row',
        'taxonomy',
        'action_link',
        'custom_text',
        'part_group',
        'divider',
        'spacer',
    ];

    private const CONTROLLED_CONTRIBUTOR_PARTS = [
        'image',
        'title',
        'description',
        'metadata_row',
        'entity_attribute',
        'action_link',
        'custom_text',
        'part_group',
        'divider',
        'spacer',
    ];

    public function __construct(
        private readonly PublicFrontCardTemplateResolver $resolver,
    ) {}

    public function resolve(string $family, ?string $key = null, array $overrides = []): PublicFrontCardTemplate
    {
        return $this->resolver->resolve($family, $key, $overrides);
    }

    /**
     * @return array<string, string>
     */
    public function compatibilityAttributes(PublicFrontCardTemplate|string $template, ?string $key = null): array
    {
        if (is_string($template)) {
            $template = $this->resolve($template, $key);
        }

        return [
            'data-card-template-family' => $template->family,
            'data-card-template-key' => $template->key,
            'data-card-template-layout' => $template->layout,
            'data-card-template-parts' => implode(',', $template->partTypes(visibleOnly: true)),
        ];
    }

    /**
     * @return array{
     *     layout: string,
     *     density: string,
     *     image_size: string,
     *     title_size: string,
     *     article: string,
     *     image: string,
     *     body: string,
     *     title: string,
     *     description: string,
     *     title_clamp: int,
     *     description_clamp: int,
     *     controlled_parts: array<int, string>
     * }
     */
    public function contentItemPresentation(PublicFrontCardTemplate $template, string $fallbackLayout = 'cards'): array
    {
        $layout = $template->layout === 'rows' ? 'rows' : $fallbackLayout;

        if ($template->imageSize === 'large') {
            $layout = 'cards';
        }

        $padding = $template->density === 'compact' ? 'p-3' : 'p-4';
        $titleClamp = $this->lineClamp($template, 'title', 2);
        $descriptionClamp = $this->lineClamp($template, 'description', 3);
        $titleClass = match ($template->titleSize) {
            'sm' => 'text-base',
            'lg' => 'text-xl',
            default => 'text-lg',
        };

        return [
            'layout' => $layout,
            'density' => $template->density,
            'image_size' => $template->imageSize,
            'title_size' => $template->titleSize,
            'article' => $this->articleClasses($layout, $padding),
            'image' => $this->imageClasses($layout),
            'body' => "flex min-w-0 flex-1 flex-col gap-3 {$padding}",
            'title' => trim('font-semibold leading-7 text-gray-950 dark:text-white '.$titleClass.' '.$this->lineClampClass($titleClamp)),
            'description' => 'text-sm leading-6 text-gray-600 dark:text-gray-300 '.$this->lineClampClass($descriptionClamp),
            'title_clamp' => $titleClamp,
            'description_clamp' => $descriptionClamp,
            'controlled_parts' => $this->controlledContentItemParts($template),
        ];
    }

    /**
     * @return array{
     *     layout: string,
     *     density: string,
     *     image_size: string,
     *     title_size: string,
     *     article: string,
     *     link: string,
     *     image: string,
     *     body: string,
     *     title: string,
     *     description: string,
     *     title_clamp: int,
     *     description_clamp: int,
     *     controlled_parts: array<int, string>
     * }
     */
    public function contentGroupPresentation(PublicFrontCardTemplate $template): array
    {
        $layout = $template->layout === 'rows' ? 'rows' : 'cards';
        $padding = $template->density === 'compact' ? 'p-3' : 'p-4';
        $titleClamp = $this->lineClamp($template, 'title', 2);
        $descriptionClamp = $this->lineClamp($template, 'description', 3);
        $titleClass = match ($template->titleSize) {
            'sm' => 'text-base',
            'lg' => 'text-xl',
            default => 'text-lg',
        };

        return [
            'layout' => $layout,
            'density' => $template->density,
            'image_size' => $template->imageSize,
            'title_size' => $template->titleSize,
            'article' => 'group h-full overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm transition hover:border-primary-300 hover:shadow-md dark:border-gray-800 dark:bg-gray-900 dark:hover:border-primary-500',
            'link' => $layout === 'rows'
                ? 'grid h-full min-w-0 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600 md:grid-cols-[minmax(8rem,12rem)_minmax(0,1fr)]'
                : 'flex h-full min-w-0 flex-col focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600',
            'image' => $layout === 'rows'
                ? 'aspect-square w-full shrink-0 md:h-full md:w-auto'
                : 'aspect-square w-full',
            'body' => "flex min-w-0 flex-1 flex-col gap-3 {$padding}",
            'title' => trim('font-semibold leading-snug text-gray-950 group-hover:text-primary-800 dark:text-white dark:group-hover:text-primary-200 '.$titleClass.' '.$this->lineClampClass($titleClamp)),
            'description' => 'text-sm leading-6 text-gray-600 dark:text-gray-300 '.$this->lineClampClass($descriptionClamp),
            'title_clamp' => $titleClamp,
            'description_clamp' => $descriptionClamp,
            'controlled_parts' => $this->controlledContentGroupParts($template),
        ];
    }

    /**
     * @return array{
     *     layout: string,
     *     density: string,
     *     image_size: string,
     *     title_size: string,
     *     article: string,
     *     body: string,
     *     avatar: string,
     *     title: string,
     *     description: string,
     *     title_clamp: int,
     *     description_clamp: int,
     *     controlled_parts: array<int, string>
     * }
     */
    public function contributorPresentation(PublicFrontCardTemplate $template, bool $compact = false): array
    {
        $layout = $template->layout === 'rows' ? 'rows' : 'cards';
        $padding = $template->density === 'compact' ? 'p-3' : 'p-4';
        $titleClamp = $this->lineClamp($template, 'title', 2);
        $descriptionClamp = $this->lineClamp($template, 'description', 3);
        $titleClass = match ($template->titleSize) {
            'sm' => 'text-sm',
            'lg' => 'text-lg',
            default => 'text-base',
        };

        return [
            'layout' => $layout,
            'density' => $template->density,
            'image_size' => $template->imageSize,
            'title_size' => $template->titleSize,
            'article' => $compact
                ? "flex h-full w-full min-w-0 flex-col gap-3 rounded-lg border bg-white text-start shadow-sm transition hover:border-primary-300 focus:outline-none focus:ring-2 focus:ring-primary-500 dark:bg-gray-900 dark:hover:border-primary-700 {$padding}"
                : "flex h-full flex-col gap-4 rounded-lg border bg-white shadow-sm transition dark:bg-gray-900 {$padding}",
            'body' => 'flex min-w-0 flex-1 flex-col gap-3',
            'avatar' => 'flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-primary-100 text-lg font-semibold text-primary-800 dark:bg-primary-900 dark:text-primary-100',
            'title' => trim('font-semibold leading-6 text-gray-950 dark:text-white '.$titleClass.' '.$this->lineClampClass($titleClamp)),
            'description' => 'text-sm leading-6 text-gray-600 dark:text-gray-300 '.$this->lineClampClass($descriptionClamp),
            'title_clamp' => $titleClamp,
            'description_clamp' => $descriptionClamp,
            'controlled_parts' => $this->controlledContributorParts($template, $compact),
        ];
    }

    /**
     * @return array<int, PublicFrontCardPart>
     */
    public function contentItemParts(PublicFrontCardTemplate $template): array
    {
        return collect($template->visibleParts())
            ->filter(fn (PublicFrontCardPart $part): bool => in_array($part->type, self::CONTROLLED_CONTENT_ITEM_PARTS, true))
            ->reject(fn (PublicFrontCardPart $part): bool => $part->type === 'image' && $template->imageSize === 'hidden')
            ->values()
            ->all();
    }

    /**
     * @return array<int, PublicFrontCardPart>
     */
    public function contentGroupParts(PublicFrontCardTemplate $template): array
    {
        return collect($template->visibleParts())
            ->filter(fn (PublicFrontCardPart $part): bool => in_array($part->type, self::CONTROLLED_CONTENT_GROUP_PARTS, true))
            ->reject(fn (PublicFrontCardPart $part): bool => $part->type === 'image' && $template->imageSize === 'hidden')
            ->values()
            ->all();
    }

    /**
     * @return array<int, PublicFrontCardPart>
     */
    public function contributorParts(PublicFrontCardTemplate $template, bool $compact = false): array
    {
        return collect($template->visibleParts())
            ->filter(fn (PublicFrontCardPart $part): bool => in_array($part->type, self::CONTROLLED_CONTRIBUTOR_PARTS, true))
            ->reject(fn (PublicFrontCardPart $part): bool => $part->type === 'image' && $template->imageSize === 'hidden')
            ->reject(fn (PublicFrontCardPart $part): bool => $compact && in_array($part->type, ['action_link', 'description'], true))
            ->values()
            ->all();
    }

    private function articleClasses(string $layout, string $padding): string
    {
        if ($layout === 'rows') {
            return "grid min-w-0 gap-4 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm transition hover:border-primary-300 hover:shadow-md dark:border-gray-800 dark:bg-gray-900 dark:hover:border-primary-700 md:grid-cols-[minmax(8rem,12rem)_minmax(0,1fr)] {$padding}";
        }

        return 'flex h-full min-w-0 flex-col overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm transition hover:border-primary-300 hover:shadow-md dark:border-gray-800 dark:bg-gray-900 dark:hover:border-primary-700';
    }

    private function imageClasses(string $layout): string
    {
        if ($layout === 'rows') {
            return 'aspect-square w-full shrink-0 md:h-full md:w-auto';
        }

        return 'aspect-square w-full';
    }

    private function lineClamp(PublicFrontCardTemplate $template, string $type, int $fallback): int
    {
        foreach ($template->visibleParts() as $part) {
            if ($part->type === $type && $part->lineClamp !== null) {
                return max(1, min(5, $part->lineClamp));
            }
        }

        return $fallback;
    }

    private function lineClampClass(int $lines): string
    {
        return match (max(1, min(5, $lines))) {
            1 => 'line-clamp-1',
            2 => 'line-clamp-2',
            4 => 'line-clamp-4',
            5 => 'line-clamp-5',
            default => 'line-clamp-3',
        };
    }

    /**
     * @return array<int, string>
     */
    private function controlledContentItemParts(PublicFrontCardTemplate $template): array
    {
        return collect($this->contentItemParts($template))
            ->map(fn (PublicFrontCardPart $part): string => $part->type)
            ->intersect(self::CONTROLLED_CONTENT_ITEM_PARTS)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function controlledContentGroupParts(PublicFrontCardTemplate $template): array
    {
        return collect($this->contentGroupParts($template))
            ->map(fn (PublicFrontCardPart $part): string => $part->type)
            ->intersect(self::CONTROLLED_CONTENT_GROUP_PARTS)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function controlledContributorParts(PublicFrontCardTemplate $template, bool $compact): array
    {
        return collect($this->contributorParts($template, $compact))
            ->map(fn (PublicFrontCardPart $part): string => $part->type)
            ->intersect(self::CONTROLLED_CONTRIBUTOR_PARTS)
            ->unique()
            ->values()
            ->all();
    }
}
