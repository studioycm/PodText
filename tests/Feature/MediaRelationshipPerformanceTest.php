<?php

use App\Enums\ImageUploadPurpose;
use App\Enums\MediaAttachmentRole;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\Media;
use App\Models\MediaAttachment;
use App\Models\User;
use App\Support\Media\LegacyOwnerMediaDiagnosticProjector;
use App\Support\Media\MediaAttachmentIdentityResolver;
use App\Support\Media\MediaFilesystemMutationCoordinator;
use App\Support\Media\UnsafeLegacyOwnerMediaException;
use App\Support\PublicFront\About\PublicAboutPageRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Http::preventStrayRequests();
    Storage::fake('public');
    Storage::fake('local');
});

it('keeps owner attachment resolution queries bounded for collections of one ten and fifty', function (int $ownerCount): void {
    $groupIds = [];
    $itemIds = [];

    for ($index = 0; $index < $ownerCount; $index++) {
        $cover = Media::factory()->create();
        $primaryName = (string) Str::ulid();
        $primary = Media::factory()->create([
            'directory' => ImageUploadPurpose::ContentItemPrimaryImage->root(),
            'name' => $primaryName,
            'path' => ImageUploadPurpose::ContentItemPrimaryImage->root()."/{$primaryName}.jpg",
        ]);
        $group = ContentGroup::factory()->create(['cover_path' => $cover->path]);
        $item = ContentItem::factory()->for($group)->create(['image_path' => $primary->path]);
        MediaAttachment::factory()->create([
            'media_id' => $cover->getKey(),
            'attachable_type' => 'content_group',
            'attachable_id' => $group->getKey(),
            'role' => MediaAttachmentRole::Cover,
            'position' => 0,
        ]);
        MediaAttachment::factory()->create([
            'media_id' => $primary->getKey(),
            'attachable_type' => 'content_item',
            'attachable_id' => $item->getKey(),
            'role' => MediaAttachmentRole::PrimaryImage,
            'position' => 0,
        ]);
        $groupIds[] = $group->getKey();
        $itemIds[] = $item->getKey();
    }

    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        $queries[] = $query->sql;
    });
    $resolver = app(MediaAttachmentIdentityResolver::class);
    $groups = ContentGroup::query()
        ->with('coverMediaAttachment.media')
        ->whereKey($groupIds)
        ->get();
    $groups->each(fn (ContentGroup $group): array => $resolver->resolve($group, MediaAttachmentRole::Cover));

    expect($queries)->toHaveCount(3);

    $queries = [];
    $items = ContentItem::query()
        ->with([
            'contentGroup.coverMediaAttachment.media',
            'primaryImageMediaAttachment.media',
        ])
        ->whereKey($itemIds)
        ->get();
    $items->each(function (ContentItem $item) use ($resolver): void {
        $resolver->resolve($item, MediaAttachmentRole::PrimaryImage);
        $resolver->resolve($item->contentGroup, MediaAttachmentRole::Cover);
    });

    expect($queries)->toHaveCount(6);
})->with([1, 10, 50]);

it('keeps unsafe owner diagnostics database-only after the table eager loads', function (int $ownerCount): void {
    $groupIds = [];
    $itemIds = [];
    for ($index = 0; $index < $ownerCount; $index++) {
        $cover = Media::factory()->create(['reference_key' => null]);
        DB::table('curator')->where('id', $cover->getKey())->update(['reference_key' => null]);
        $name = (string) Str::ulid();
        $primary = Media::factory()->create(['reference_key' => null, 'directory' => ImageUploadPurpose::ContentItemPrimaryImage->root(), 'name' => $name, 'path' => ImageUploadPurpose::ContentItemPrimaryImage->root()."/{$name}.jpg"]);
        DB::table('curator')->where('id', $primary->getKey())->update(['reference_key' => null]);
        $group = ContentGroup::factory()->create(['cover_path' => $cover->path]);
        $item = ContentItem::factory()->for($group)->create(['image_path' => $primary->path]);
        $groupIds[] = $group->getKey();
        $itemIds[] = $item->getKey();
    }
    $groups = ContentGroup::query()->with(['coverMediaAttachment.media', 'legacyCoverMediaRows'])->whereKey($groupIds)->get();
    $items = ContentItem::query()->with([
        'contentGroup.coverMediaAttachment.media',
        'contentGroup.legacyCoverMediaRows',
        'primaryImageMediaAttachment.media',
        'legacyPrimaryImageMediaRows',
    ])->whereKey($itemIds)->get();
    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        $queries[] = $query->sql;
    });
    $projector = app(LegacyOwnerMediaDiagnosticProjector::class);
    $resolver = app(MediaAttachmentIdentityResolver::class);
    Storage::shouldReceive('disk')->never();
    foreach ($groups as $group) {
        expect($projector->hasUnsafe($group, MediaAttachmentRole::Cover))->toBeTrue();
        expect(fn () => $resolver->resolve($group, MediaAttachmentRole::Cover))->toThrow(UnsafeLegacyOwnerMediaException::class);
    }
    foreach ($items as $item) {
        expect($projector->hasUnsafe($item, MediaAttachmentRole::PrimaryImage))->toBeTrue();
        expect(fn () => $resolver->resolve($item, MediaAttachmentRole::PrimaryImage))->toThrow(UnsafeLegacyOwnerMediaException::class);
    }
    expect($queries)->toHaveCount(0);
})->with([1, 10, 50]);

it('batches about and team settings identity queries for one ten and fifty records', function (int $recordCount): void {
    $aboutRecords = [];
    $teamRecords = [];

    for ($index = 0; $index < $recordCount; $index++) {
        $aboutName = (string) Str::ulid();
        $about = Media::factory()->create([
            'directory' => ImageUploadPurpose::AboutImage->root(),
            'name' => $aboutName,
            'path' => ImageUploadPurpose::AboutImage->root()."/{$aboutName}.jpg",
        ]);
        $teamName = (string) Str::ulid();
        $team = Media::factory()->create([
            'directory' => ImageUploadPurpose::TeamImage->root(),
            'name' => $teamName,
            'path' => ImageUploadPurpose::TeamImage->root()."/{$teamName}.jpg",
        ]);
        $aboutRecords[] = [
            'image_media_reference_key' => $about->reference_key,
            'image_path' => $about->path,
        ];
        $teamRecords[] = [
            'image_media_reference_key' => $team->reference_key,
            'image_path' => $team->path,
        ];
    }

    $renderer = app(PublicAboutPageRenderer::class);
    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        if (str_contains($query->sql, '"curator"')) {
            $queries[] = $query->sql;
        }
    });

    $renderer->primeImageIdentities($aboutRecords, ImageUploadPurpose::AboutImage);
    foreach ($aboutRecords as $record) {
        expect($renderer->imageUrl(
            $record['image_path'],
            $record['image_media_reference_key'],
            ImageUploadPurpose::AboutImage,
        ))->not->toBeNull();
    }

    $renderer->primeImageIdentities($teamRecords, ImageUploadPurpose::TeamImage);
    foreach ($teamRecords as $record) {
        expect($renderer->imageUrl(
            $record['image_path'],
            $record['image_media_reference_key'],
            ImageUploadPurpose::TeamImage,
        ))->not->toBeNull();
    }

    expect($queries)->toHaveCount(4);
})->with([1, 10, 50]);

it('keeps bulk legacy and attachment reference discovery bounded for one ten and fifty deletes', function (int $recordCount): void {
    $actor = User::factory()->admin()->create();
    $records = Media::factory()->count($recordCount)->create();

    foreach ($records as $record) {
        Storage::disk('public')->put($record->path, 'deletion fixture');
    }

    $referenceQueries = [];
    DB::listen(function ($query) use (&$referenceQueries): void {
        if (
            str_contains($query->sql, '"media_attachments"')
            || (str_contains($query->sql, '"content_groups"') && str_contains($query->sql, '"cover_path"'))
            || (str_contains($query->sql, '"content_items"') && str_contains($query->sql, '"image_path"'))
            || (str_contains($query->sql, '"settings"') && str_contains($query->sql, '"payload"'))
        ) {
            $referenceQueries[] = $query->sql;
        }
    });

    app(MediaFilesystemMutationCoordinator::class)->deleteMany($records, $actor);

    expect(count($referenceQueries))->toBeLessThanOrEqual(6)
        ->and(Media::query()->whereKey($records->modelKeys())->exists())->toBeFalse();
})->with([1, 10, 50]);

it('uses the evidence-backed attachment identity and mutation repair indexes in sqlite', function (): void {
    expect(DB::connection()->getDriverName())->toBe('sqlite');

    $plans = [
        'media_attachments_owner_role_unique' => DB::select(
            'EXPLAIN QUERY PLAN SELECT id FROM media_attachments WHERE attachable_type = ? AND attachable_id = ? AND role = ? LIMIT 1',
            ['content_group', 1, 'cover'],
        ),
        'media_attachments_media_role_index' => DB::select(
            'EXPLAIN QUERY PLAN SELECT id FROM media_attachments WHERE media_id = ? AND role = ?',
            [1, 'cover'],
        ),
        'curator_reference_key_unique' => DB::select(
            'EXPLAIN QUERY PLAN SELECT id FROM curator WHERE reference_key = ? LIMIT 1',
            [(string) Str::ulid()],
        ),
        'media_mutations_status_updated_index' => DB::select(
            'EXPLAIN QUERY PLAN SELECT id FROM media_mutation_operations WHERE status = ? ORDER BY updated_at, id LIMIT 100',
            ['cleanup_pending'],
        ),
        'media_mutations_media_status_index' => DB::select(
            'EXPLAIN QUERY PLAN SELECT id FROM media_mutation_operations WHERE media_id = ? AND status = ?',
            [1, 'cleanup_pending'],
        ),
    ];

    foreach ($plans as $index => $rows) {
        $detail = collect($rows)->pluck('detail')->implode(' ');
        expect($detail)->toContain($index);
    }
});
