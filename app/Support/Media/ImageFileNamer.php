<?php

namespace App\Support\Media;

use App\Enums\MediaNamingStrategy;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ImageFileNamer
{
    public const CONTENT_GROUP_COVER = 'content_group_cover';

    public const HEADER = 'header';

    public const TEAM = 'team';

    public const ABOUT = 'about';

    public const DEFAULT_IMAGES = 'default_images';

    /**
     * @return array<int, string>
     */
    public static function appOwnedDirectories(): array
    {
        return [
            self::directoryFor(self::CONTENT_GROUP_COVER),
            self::directoryFor(self::HEADER),
            self::directoryFor(self::TEAM),
            self::directoryFor(self::ABOUT),
            self::directoryFor(self::DEFAULT_IMAGES),
        ];
    }

    public static function directoryFor(string $family): string
    {
        return match ($family) {
            self::CONTENT_GROUP_COVER => 'content-groups/covers',
            self::HEADER => 'header',
            self::TEAM => 'team',
            self::ABOUT => 'about',
            self::DEFAULT_IMAGES => 'default-images',
            default => trim($family, '/'),
        };
    }

    public static function storageFileName(
        ?string $slug,
        string $referenceKey,
        string $mimeType,
        MediaNamingStrategy|string|null $strategy = null,
        ?callable $exists = null,
    ): string {
        $strategy = $strategy instanceof MediaNamingStrategy
            ? $strategy
            : MediaNamingStrategy::fromSetting($strategy);

        return self::uniqueFileName(
            self::storageStem($slug, $referenceKey, $strategy),
            self::extensionForMimeType($mimeType),
            $exists,
        );
    }

    public static function exportFileName(?string $slug, string $referenceKey, string $mimeType): string
    {
        return self::storageStem($slug, $referenceKey, MediaNamingStrategy::SlugKey)
            .'.'
            .self::extensionForMimeType($mimeType);
    }

    public static function storageStem(?string $slug, string $referenceKey, MediaNamingStrategy $strategy): string
    {
        $slug = self::cleanStem($slug);
        $referenceKey = self::cleanStem($referenceKey);

        return match ($strategy) {
            MediaNamingStrategy::Slug => $slug ?: $referenceKey,
            MediaNamingStrategy::ReferenceKey => $referenceKey,
            MediaNamingStrategy::SlugKey => $slug ? "{$slug}--{$referenceKey}" : $referenceKey,
        };
    }

    public static function extensionForMimeType(string $mimeType): string
    {
        return match (mb_strtolower($mimeType)) {
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/svg+xml', 'image/svg' => 'svg',
            default => throw new InvalidArgumentException("Unsupported image MIME type [{$mimeType}]."),
        };
    }

    private static function uniqueFileName(string $stem, string $extension, ?callable $exists): string
    {
        $stem = $stem ?: (string) Str::ulid();
        $candidate = "{$stem}.{$extension}";

        if ($exists === null || ! $exists($candidate)) {
            return $candidate;
        }

        for ($suffix = 2; $suffix <= 999; $suffix++) {
            $candidate = "{$stem}-{$suffix}.{$extension}";

            if (! $exists($candidate)) {
                return $candidate;
            }
        }

        return "{$stem}-".Str::ulid().".{$extension}";
    }

    private static function cleanStem(?string $value): string
    {
        return Str::of((string) $value)
            ->squish()
            ->lower()
            ->replaceMatches('/[^\pL\pN_-]+/u', '-')
            ->replaceMatches('/-+/', '-')
            ->trim('-_')
            ->toString();
    }
}
