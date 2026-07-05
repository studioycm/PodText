<?php

namespace App\Support\PublicFront\About;

class PublicAboutPageRegistry
{
    /**
     * @return array<string>
     */
    public static function blockTypes(): array
    {
        return [
            'heading',
            'markdown',
            'rich_content',
            'image',
            'callout',
            'form_cta',
            'team_section',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function blockTypeOptions(): array
    {
        return collect(self::blockTypes())
            ->mapWithKeys(fn (string $type): array => [$type => __("admin.about_block_types.{$type}")])
            ->all();
    }

    /**
     * @return array<string>
     */
    public static function styles(): array
    {
        return [
            'default',
            'muted',
            'accent',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function styleOptions(): array
    {
        return collect(self::styles())
            ->mapWithKeys(fn (string $style): array => [$style => __("admin.about_block_styles.{$style}")])
            ->all();
    }

    /**
     * @return array<string>
     */
    public static function teamLayouts(): array
    {
        return [
            'grid',
            'list',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function teamLayoutOptions(): array
    {
        return collect(self::teamLayouts())
            ->mapWithKeys(fn (string $layout): array => [$layout => __("admin.about_team_layouts.{$layout}")])
            ->all();
    }

    /**
     * @return array<string>
     */
    public static function imageDirectories(): array
    {
        return [
            'about',
            'team',
        ];
    }

    /**
     * @return array<string>
     */
    public static function acceptedImageTypes(): array
    {
        return [
            'image/jpeg',
            'image/png',
            'image/webp',
        ];
    }

    public static function maxImageSize(): int
    {
        return 2048;
    }
}
