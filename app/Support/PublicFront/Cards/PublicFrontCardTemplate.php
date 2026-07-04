<?php

namespace App\Support\PublicFront\Cards;

class PublicFrontCardTemplate
{
    /**
     * @param  array<PublicFrontCardPart>  $parts
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $family,
        public readonly string $layout,
        public readonly string $density,
        public readonly string $imageSize,
        public readonly string $titleSize,
        public readonly array $parts,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $parts = collect($data['parts'] ?? [])
            ->filter(fn (mixed $part): bool => is_array($part))
            ->map(fn (array $part): PublicFrontCardPart => PublicFrontCardPart::fromArray($part))
            ->sortBy(fn (PublicFrontCardPart $part): int => $part->order)
            ->values()
            ->all();

        return new self(
            key: (string) ($data['key'] ?? 'default_content_item'),
            label: (string) ($data['label'] ?? ''),
            family: (string) ($data['family'] ?? PublicFrontCardTemplateRegistry::CONTENT_ITEM_FAMILY),
            layout: (string) ($data['layout'] ?? 'cards'),
            density: (string) ($data['density'] ?? 'comfortable'),
            imageSize: (string) ($data['image_size'] ?? 'medium'),
            titleSize: (string) ($data['title_size'] ?? 'base'),
            parts: $parts,
        );
    }

    /**
     * @return array<PublicFrontCardPart>
     */
    public function visibleParts(): array
    {
        return collect($this->parts)
            ->filter(fn (PublicFrontCardPart $part): bool => $part->visible)
            ->values()
            ->all();
    }

    /**
     * @return array<string>
     */
    public function partTypes(bool $visibleOnly = false): array
    {
        $parts = $visibleOnly ? $this->visibleParts() : $this->parts;

        return array_map(
            fn (PublicFrontCardPart $part): string => $part->type,
            $parts,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'family' => $this->family,
            'layout' => $this->layout,
            'density' => $this->density,
            'image_size' => $this->imageSize,
            'title_size' => $this->titleSize,
            'parts' => array_map(
                fn (PublicFrontCardPart $part): array => $part->toArray(),
                $this->parts,
            ),
        ];
    }
}
