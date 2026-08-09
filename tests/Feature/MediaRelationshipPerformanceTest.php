<?php

use App\Enums\ImageUploadPurpose;
use App\Enums\MediaAttachmentRole;
use App\Enums\MediaMutationStatus;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\Media;
use App\Models\MediaAttachment;
use App\Models\User;
use App\Support\Media\MediaAttachmentIdentityResolver;
use App\Support\Media\MediaFilesystemMutationCoordinator;
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
        $group = ContentGroup::factory()->create();
        $item = ContentItem::factory()->for($group)->create();
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
        if (str_contains($query->sql, '`curator`')) {
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
            str_contains($query->sql, '`media_attachments`')
            || (str_contains($query->sql, '`content_groups`') && str_contains($query->sql, '`cover_path`'))
            || (str_contains($query->sql, '`content_items`') && str_contains($query->sql, '`image_path`'))
            || (str_contains($query->sql, '`settings`') && str_contains($query->sql, '`payload`'))
        ) {
            $referenceQueries[] = $query->sql;
        }
    });

    app(MediaFilesystemMutationCoordinator::class)->deleteMany($records, $actor);

    expect(count($referenceQueries))->toBeLessThanOrEqual(6)
        ->and(Media::query()->whereKey($records->modelKeys())->exists())->toBeFalse();
})->with([1, 10, 50]);

it('uses the evidence-backed attachment identity and mutation repair indexes', function (): void {
    expect(DB::connection()->getDriverName())->toBe('mysql');

    // sqlite's EXPLAIN QUERY PLAN reports the chosen access method
    // structurally, independent of row presence — mysql's plain EXPLAIN
    // only populates key/possible_keys for a fully-bound UNIQUE lookup when
    // it can't shortcut to "no matching row in const table" (spec §7 SQL
    // strict mode). One matching row per unique-keyed query is enough; the
    // non-unique ref-type lookups plan correctly even on an empty table.
    $media = Media::factory()->create();
    MediaAttachment::factory()->create([
        'media_id' => $media->getKey(),
        'attachable_type' => 'content_group',
        'attachable_id' => 1,
        'role' => MediaAttachmentRole::Cover,
        'position' => 0,
    ]);

    $plans = [
        'media_attachments_owner_role_unique' => DB::select(
            'EXPLAIN SELECT id FROM media_attachments WHERE attachable_type = ? AND attachable_id = ? AND role = ? LIMIT 1',
            ['content_group', 1, MediaAttachmentRole::Cover->value],
        ),
        'media_attachments_media_role_index' => DB::select(
            'EXPLAIN SELECT id FROM media_attachments WHERE media_id = ? AND role = ?',
            [$media->getKey(), MediaAttachmentRole::Cover->value],
        ),
        'curator_reference_key_unique' => DB::select(
            'EXPLAIN SELECT id FROM curator WHERE reference_key = ? LIMIT 1',
            [$media->reference_key],
        ),
        'media_mutations_status_updated_index' => DB::select(
            'EXPLAIN SELECT id FROM media_mutation_operations WHERE status = ? ORDER BY updated_at, id LIMIT 100',
            [MediaMutationStatus::CleanupPending->value],
        ),
        'media_mutations_media_status_index' => DB::select(
            'EXPLAIN SELECT id FROM media_mutation_operations WHERE media_id = ? AND status = ?',
            [$media->getKey(), MediaMutationStatus::CleanupPending->value],
        ),
    ];

    foreach ($plans as $index => $rows) {
        // possible_keys (the optimizer's usable-index candidates), not key
        // (its cost-based pick): on these near-empty fixture tables MySQL
        // sometimes prefers a different valid index than it would at
        // production cardinality. This test guards index existence and
        // applicability, not the optimizer's tie-break.
        $candidates = collect($rows)->pluck('possible_keys')->implode(',');
        expect($candidates)->toContain($index);
    }
});
