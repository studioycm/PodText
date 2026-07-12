<?php

namespace App\Enums;

enum MediaNamingStrategy: string
{
    case Slug = 'slug';
    case ReferenceKey = 'reference_key';
    case SlugKey = 'slug_key';

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
}
