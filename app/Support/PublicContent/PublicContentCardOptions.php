<?php

namespace App\Support\PublicContent;

use App\Settings\PublicContentSettings;
use Throwable;

class PublicContentCardOptions
{
    private const IMAGE_SIZES = ['hidden', 'small', 'medium', 'large'];

    private const DENSITIES = ['compact', 'comfortable'];

    private const TITLE_SIZES = ['sm', 'base', 'lg'];

    public function __construct(
        public readonly string $imageSize = 'medium',
        public readonly string $density = 'comfortable',
        public readonly string $titleSize = 'base',
        public readonly bool $showGroupBadge = true,
        public readonly bool $showAuthors = true,
        public readonly bool $showCategories = true,
        public readonly bool $showTags = true,
        public readonly bool $showDuration = true,
        public readonly bool $showEffectiveDate = true,
        public readonly bool $showDescription = true,
        public readonly int $descriptionLines = 3,
        public readonly int $cardsPerPage = 12,
    ) {}

    public static function fromSettings(?PublicContentSettings $settings = null): self
    {
        try {
            $settings ??= app(PublicContentSettings::class);
            $values = self::values($settings);

            return new self(
                imageSize: self::finite($values['homepage_card_image_size'] ?? null, self::IMAGE_SIZES, 'medium'),
                density: self::finite($values['homepage_card_density'] ?? null, self::DENSITIES, 'comfortable'),
                titleSize: self::finite($values['homepage_card_title_size'] ?? null, self::TITLE_SIZES, 'base'),
                showGroupBadge: self::boolean($values['homepage_show_group_badge'] ?? null, true),
                showAuthors: self::boolean($values['homepage_show_authors'] ?? null, true),
                showCategories: self::boolean($values['homepage_show_categories'] ?? null, true),
                showTags: self::boolean($values['homepage_show_tags'] ?? null, true),
                showDuration: self::boolean($values['homepage_show_duration'] ?? null, true),
                showEffectiveDate: self::boolean($values['homepage_show_effective_date'] ?? null, true),
                showDescription: self::boolean($values['homepage_show_description'] ?? null, true),
                descriptionLines: self::integerRange($values['homepage_description_lines'] ?? null, 0, 4, 3),
                cardsPerPage: self::integerRange($values['homepage_cards_per_page'] ?? $values['homepage_item_limit'] ?? null, 1, 48, 12),
            );
        } catch (Throwable) {
            return new self;
        }
    }

    public function cardPaddingClass(): string
    {
        return match ($this->density) {
            'compact' => 'p-4 gap-3',
            default => 'p-5 gap-4',
        };
    }

    public function imageClass(): string
    {
        return match ($this->imageSize) {
            'small' => 'h-24',
            'large' => 'h-48',
            default => 'h-36',
        };
    }

    public function titleClass(): string
    {
        return match ($this->titleSize) {
            'sm' => 'text-base',
            'lg' => 'text-xl',
            default => 'text-lg',
        };
    }

    public function descriptionClass(): string
    {
        return match ($this->descriptionLines) {
            0 => 'hidden',
            1 => 'line-clamp-1',
            2 => 'line-clamp-2',
            4 => 'line-clamp-4',
            default => 'line-clamp-3',
        };
    }

    private static function finite(mixed $value, array $allowed, string $default): string
    {
        if (! in_array($value, $allowed, true)) {
            return $default;
        }

        return $value;
    }

    private static function boolean(mixed $value, bool $default): bool
    {
        if ($value === null) {
            return $default;
        }

        return (bool) $value;
    }

    private static function integerRange(mixed $value, int $min, int $max, int $default): int
    {
        if (! is_numeric($value)) {
            return $default;
        }

        return min($max, max($min, (int) $value));
    }

    /**
     * @return array<string, mixed>
     */
    private static function values(PublicContentSettings $settings): array
    {
        try {
            return $settings->getRepository()->getPropertiesInGroup(PublicContentSettings::group());
        } catch (Throwable) {
            return $settings->toArray();
        }
    }
}
