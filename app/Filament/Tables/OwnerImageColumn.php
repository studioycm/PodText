<?php

namespace App\Filament\Tables;

use App\Filament\Actions\ContentImageActions;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\Media;
use App\Models\MediaAttachment;
use App\Support\PublicFront\PublicDefaultImageResolver;
use Filament\Tables\Columns\ImageColumn;
use Illuminate\Support\HtmlString;

final class OwnerImageColumn
{
    public static function contentGroup(): ImageColumn
    {
        return ImageColumn::make('effective_cover')
            ->label(__('admin.fields.cover_path'))
            ->state(fn (ContentGroup $record): ?string => self::contentGroupUrl($record))
            ->alt(fn (ContentGroup $record): string => __('admin.owner_image.thumbnail_alt', ['title' => $record->title]))
            ->tooltip(fn (ContentGroup $record, ?string $state): ?HtmlString => self::previewTooltip($state, $record->title))
            ->extraAttributes(['data-testid' => 'owner-image-thumbnail'])
            ->extraImgAttributes(['loading' => 'lazy'])
            ->imageSize(48)
            ->square()
            ->action(ContentImageActions::contentGroupCoverDetails());
    }

    public static function contentItem(): ImageColumn
    {
        return ImageColumn::make('effective_image')
            ->label(__('admin.fields.effective_image'))
            ->state(fn (ContentItem $record): ?string => self::contentItemUrl($record))
            ->alt(fn (ContentItem $record): string => __('admin.owner_image.thumbnail_alt', ['title' => $record->title]))
            ->tooltip(fn (ContentItem $record, ?string $state): ?HtmlString => self::previewTooltip($state, $record->title))
            ->extraAttributes(['data-testid' => 'owner-image-thumbnail'])
            ->extraImgAttributes(['loading' => 'lazy'])
            ->imageSize(48)
            ->square()
            ->action(ContentImageActions::contentItemImageDetails());
    }

    private static function previewTooltip(?string $url, string $title): ?HtmlString
    {
        if (blank($url)) {
            return null;
        }

        return new HtmlString(view('filament.tables.columns.owner-image-preview-tooltip', [
            'url' => $url,
            'alt' => __('admin.owner_image.preview_alt', ['title' => $title]),
        ])->render());
    }

    private static function browserUrl(?string $url): ?string
    {
        if (blank($url)) {
            return null;
        }

        return filter_var($url, FILTER_VALIDATE_URL) !== false
            ? $url
            : url($url);
    }

    private static function contentGroupUrl(ContentGroup $group): ?string
    {
        $effective = app(PublicDefaultImageResolver::class)->contentGroupImage($group);
        $media = $effective['source'] === 'group'
            ? self::loadedOwnerMedia($group, 'coverMediaAttachment', $effective['path'])
            : null;

        return $media instanceof Media
            ? route('admin.media-files.view', ['media' => $media])
            : self::browserUrl($effective['url']);
    }

    private static function contentItemUrl(ContentItem $item): ?string
    {
        $effective = app(PublicDefaultImageResolver::class)->contentItemImage($item);
        $media = match ($effective['source']) {
            'item' => self::loadedOwnerMedia(
                $item,
                'primaryImageMediaAttachment',
                $effective['path'],
            ),
            'group' => $item->contentGroup instanceof ContentGroup
                ? self::loadedOwnerMedia(
                    $item->contentGroup,
                    'coverMediaAttachment',
                    $effective['path'],
                )
                : null,
            default => null,
        };

        return $media instanceof Media
            ? route('admin.media-files.view', ['media' => $media])
            : self::browserUrl($effective['url']);
    }

    private static function loadedOwnerMedia(
        ContentGroup|ContentItem $owner,
        string $attachmentRelation,
        ?string $effectivePath,
    ): ?Media {
        $attachment = $owner->relationLoaded($attachmentRelation)
            ? $owner->getRelation($attachmentRelation)
            : null;
        $media = $attachment instanceof MediaAttachment && $attachment->relationLoaded('media')
            ? $attachment->getRelation('media')
            : null;

        return $media instanceof Media && $media->path === $effectivePath
            ? $media
            : null;
    }
}
