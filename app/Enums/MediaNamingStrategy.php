<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum MediaNamingStrategy: string implements HasLabel
{
    case Slug = 'slug';
    case ReferenceKey = 'reference_key';
    case SlugKey = 'slug_key';
    case Title = 'title';

    public static function fromSetting(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::Slug;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $strategy): string => $strategy->value,
            self::cases(),
        );
    }

    public function getLabel(): string
    {
        return __("admin.media_naming_strategies.{$this->value}");
    }
}
