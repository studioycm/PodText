<?php

namespace App\Support\PublicFront\Sections;

use App\Support\PublicFront\PublicFrontInvalidConfig;

class PublicDisplaySectionConfigResult
{
    /**
     * @param  array<string, mixed>  $sourceConfig
     * @param  array<string, mixed>  $selectionConfig
     * @param  array<string, mixed>  $displayConfig
     * @param  array<string, mixed>  $paginationConfig
     * @param  array<PublicFrontInvalidConfig>  $invalidConfig
     */
    public function __construct(
        public readonly array $sourceConfig,
        public readonly array $selectionConfig,
        public readonly array $displayConfig,
        public readonly array $paginationConfig,
        public readonly array $invalidConfig = [],
    ) {}

    public function isRenderable(): bool
    {
        return is_string($this->sourceConfig['source_type'] ?? null);
    }

    public function sourceType(): ?string
    {
        $sourceType = $this->sourceConfig['source_type'] ?? null;

        return is_string($sourceType) ? $sourceType : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function config(): array
    {
        return [
            'source_config' => $this->sourceConfig,
            'selection_config' => $this->selectionConfig,
            'display_config' => $this->displayConfig,
            'pagination_config' => $this->paginationConfig,
        ];
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
