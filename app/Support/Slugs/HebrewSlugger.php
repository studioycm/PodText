<?php

namespace App\Support\Slugs;

use Closure;
use Illuminate\Support\Str;

class HebrewSlugger
{
    public const MaxLength = 120;

    public static function slug(?string $source, ?string $fallback = null, int $maxLength = self::MaxLength): string
    {
        $slug = mb_strtolower((string) $source, 'UTF-8');
        $slug = (string) preg_replace('/\p{Mn}+/u', '', $slug);
        $slug = (string) preg_replace('/[\x{2010}-\x{2015}\x{2212}]+/u', '-', $slug);
        $slug = (string) preg_replace('/[\s_]+/u', '-', $slug);
        $slug = (string) preg_replace('/[^a-z0-9\x{05D0}-\x{05EA}-]+/u', '', $slug);
        $slug = (string) preg_replace('/-+/u', '-', $slug);
        $slug = trim($slug, '-');

        if ($maxLength > 0) {
            $slug = trim(mb_substr($slug, 0, $maxLength, 'UTF-8'), '-');
        }

        if (filled($slug)) {
            return $slug;
        }

        if ($fallback !== null) {
            return $fallback;
        }

        return Str::lower((string) Str::ulid());
    }

    public static function unique(
        ?string $source,
        Closure $exists,
        ?string $fallback = null,
        int $maxLength = self::MaxLength,
    ): string {
        $baseSlug = static::slug($source, $fallback, $maxLength);
        $slug = $baseSlug;
        $suffix = 2;

        while ($exists($slug)) {
            $suffixText = "-{$suffix}";
            $baseLength = max(1, $maxLength - mb_strlen($suffixText, 'UTF-8'));
            $trimmedBase = trim(mb_substr($baseSlug, 0, $baseLength, 'UTF-8'), '-');

            if (blank($trimmedBase)) {
                $trimmedBase = static::slug(null, null, $baseLength);
            }

            $slug = "{$trimmedBase}{$suffixText}";
            $suffix++;
        }

        return $slug;
    }

    public static function isUlidLike(string $slug): bool
    {
        return preg_match('/^[0-9a-hjkmnp-tv-z]{26}$/i', $slug) === 1;
    }
}
