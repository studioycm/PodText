<?php

namespace App\Support\PublicFront\Sections;

use App\Models\HomepageSection;
use App\Support\PublicFront\Cards\PublicFrontCardTemplate;
use App\Support\PublicFront\PublicFrontInvalidConfig;
use Illuminate\Support\Collection;

class PublicDisplaySectionResult
{
    /**
     * @param  Collection<int, mixed>  $items
     * @param  Collection<int, mixed>  $contentGroups
     * @param  Collection<int, mixed>  $categories
     * @param  Collection<int, mixed>  $contributors
     * @param  array<string, mixed>  $sourceConfig
     * @param  array<string, mixed>  $selectionConfig
     * @param  array<string, mixed>  $displayConfig
     * @param  array<string, mixed>  $paginationConfig
     * @param  array<PublicFrontInvalidConfig>  $invalidConfig
     */
    public function __construct(
        public readonly string $key,
        public readonly ?HomepageSection $section,
        public readonly string $sourceType,
        public readonly ?string $heading,
        public readonly bool $showHeading,
        public readonly ?string $targetLabel,
        public readonly ?string $viewMoreUrl,
        public readonly ?PublicFrontCardTemplate $cardTemplate,
        public readonly Collection $items,
        public readonly Collection $contentGroups,
        public readonly Collection $categories,
        public readonly Collection $contributors,
        public readonly array $sourceConfig,
        public readonly array $selectionConfig,
        public readonly array $displayConfig,
        public readonly array $paginationConfig,
        public readonly array $invalidConfig = [],
    ) {}

    public function hasResults(): bool
    {
        return $this->items->isNotEmpty()
            || $this->contentGroups->isNotEmpty()
            || $this->categories->isNotEmpty()
            || $this->contributors->isNotEmpty();
    }

    public function layout(string $fallback = 'cards'): string
    {
        return $this->cardTemplate?->layout === 'rows' ? 'rows' : $fallback;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function invalidConfigArray(): array
    {
        return array_map(
            fn (PublicFrontInvalidConfig $invalidConfig): array => $invalidConfig->toArray(),
            $this->invalidConfig,
        );
    }
}
