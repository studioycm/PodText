<?php

use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\Media;
use App\Models\MediaAttachment;
use App\Support\PublicFront\PublicDefaultImageResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Http::preventStrayRequests();
    Storage::fake('public');
});

function altChainMedia(string $path, ?string $title): Media
{
    /** @var Media $media */
    $media = Media::factory()->create([
        'disk' => 'public',
        'directory' => dirname($path),
        'visibility' => 'public',
        'name' => pathinfo($path, PATHINFO_FILENAME),
        'path' => $path,
        'width' => 40,
        'height' => 40,
        'size' => 1024,
        'type' => 'image/jpeg',
        'ext' => 'jpg',
        'title' => $title,
    ]);
    Storage::disk('public')->put($path, 'image-bytes');

    return $media;
}

function altChainAttach(Media $media, ContentGroup|ContentItem $owner, string $role): void
{
    MediaAttachment::query()->create([
        'media_id' => $media->getKey(),
        'attachable_type' => $owner instanceof ContentGroup ? 'content_group' : 'content_item',
        'attachable_id' => $owner->getKey(),
        'role' => $role,
        'position' => 0,
    ]);
}

it('uses the media title as the public item alt and falls back to the item title', function (): void {
    $group = ContentGroup::factory()->create();
    $titledItem = ContentItem::factory()->for($group)->create(['title' => 'פרק עם כותרת מדיה']);
    $untitledItem = ContentItem::factory()->for($group)->create(['title' => 'פרק בלי כותרת מדיה']);
    $titledMedia = altChainMedia('content-items/images/alt-titled.jpg', 'כותרת מדיה נבחרת');
    $untitledMedia = altChainMedia('content-items/images/alt-untitled.jpg', null);
    altChainAttach($titledMedia, $titledItem, 'primary_image');
    altChainAttach($untitledMedia, $untitledItem, 'primary_image');

    $resolver = app(PublicDefaultImageResolver::class);

    expect($resolver->contentItemImage($titledItem)['alt'])->toBe('כותרת מדיה נבחרת')
        ->and($resolver->contentItemImage($untitledItem)['alt'])->toBe('פרק בלי כותרת מדיה');
});

it('keeps the editorial cover alt winning over the media title on group covers', function (): void {
    $editorialGroup = ContentGroup::factory()->create([
        'title' => 'קבוצת אלט ידני',
        'cover_alt_text' => 'אלט ידני מנצח',
    ]);
    $mediaTitleGroup = ContentGroup::factory()->create([
        'title' => 'קבוצת כותרת מדיה',
        'cover_alt_text' => null,
    ]);
    $plainGroup = ContentGroup::factory()->create([
        'title' => 'קבוצה רגילה',
        'cover_alt_text' => null,
    ]);
    $editorialCover = altChainMedia('content-groups/covers/alt-editorial.jpg', 'כותרת עטיפה');
    $titledCover = altChainMedia('content-groups/covers/alt-media-title.jpg', 'כותרת עטיפה שנייה');
    $plainCover = altChainMedia('content-groups/covers/alt-plain.jpg', null);
    altChainAttach($editorialCover, $editorialGroup, 'cover');
    altChainAttach($titledCover, $mediaTitleGroup, 'cover');
    altChainAttach($plainCover, $plainGroup, 'cover');

    $resolver = app(PublicDefaultImageResolver::class);

    expect($resolver->contentGroupImage($editorialGroup)['alt'])->toBe('אלט ידני מנצח')
        ->and($resolver->contentGroupImage($mediaTitleGroup)['alt'])->toBe('כותרת עטיפה שנייה')
        ->and($resolver->contentGroupImage($plainGroup)['alt'])->toBe('קבוצה רגילה');
});
