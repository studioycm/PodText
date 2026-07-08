<?php

namespace App\Support\PublicFront\Cards;

class PublicFrontCardPart
{
    public function __construct(
        public readonly string $type,
        public readonly ?string $source,
        public readonly ?string $attribute,
        public readonly ?string $label,
        public readonly ?string $labelPosition,
        public readonly ?string $labelAlignment,
        public readonly ?string $icon,
        public readonly ?string $iconPosition,
        public readonly string $layout,
        public readonly ?string $columns,
        public readonly ?string $gap,
        public readonly ?string $alignment,
        public readonly bool $visible,
        public readonly int $order,
        public readonly ?int $lineClamp,
        public readonly ?string $fontSize,
        public readonly ?string $urlTarget,
        public readonly ?string $text,
        /** @var array<int, self> */
        public readonly array $children = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            type: (string) ($data['type'] ?? 'custom_text'),
            source: $data['source'] ?? null,
            attribute: $data['attribute'] ?? null,
            label: $data['label'] ?? null,
            labelPosition: $data['label_position'] ?? null,
            labelAlignment: $data['label_alignment'] ?? null,
            icon: $data['icon'] ?? null,
            iconPosition: $data['icon_position'] ?? null,
            layout: (string) ($data['layout'] ?? 'inline'),
            columns: isset($data['columns']) ? (string) $data['columns'] : null,
            gap: $data['gap'] ?? null,
            alignment: $data['alignment'] ?? null,
            visible: (bool) ($data['visible'] ?? true),
            order: (int) ($data['order'] ?? 0),
            lineClamp: isset($data['line_clamp']) ? (int) $data['line_clamp'] : null,
            fontSize: $data['font_size'] ?? null,
            urlTarget: $data['url_target'] ?? null,
            text: $data['text'] ?? null,
            children: collect($data['children'] ?? [])
                ->filter(fn (mixed $child): bool => is_array($child))
                ->map(fn (array $child): self => self::fromArray($child))
                ->values()
                ->all(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'type' => $this->type,
            'source' => $this->source,
            'attribute' => $this->attribute,
            'label' => $this->label,
            'label_position' => $this->labelPosition,
            'label_alignment' => $this->labelAlignment,
            'icon' => $this->icon,
            'icon_position' => $this->iconPosition,
            'layout' => $this->layout,
            'columns' => $this->columns,
            'gap' => $this->gap,
            'alignment' => $this->alignment,
            'visible' => $this->visible,
            'order' => $this->order,
            'line_clamp' => $this->lineClamp,
            'font_size' => $this->fontSize,
            'url_target' => $this->urlTarget,
            'text' => $this->text,
            'children' => $this->children === []
                ? null
                : collect($this->children)
                    ->map(fn (self $child): array => $child->toArray())
                    ->all(),
        ], fn (mixed $value): bool => $value !== null);
    }
}
