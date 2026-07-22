<?php

use App\Models\ContentGroup;
use App\Models\Media;
use App\Models\MediaAsset;
use App\Models\MediaAttachment;
use App\Models\MediaProviderBinding;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Http::preventStrayRequests();
});

it('creates only the minimal media asset kernel and attachment bridge', function (): void {
    $schema = DB::connection()->getSchemaBuilder();

    expect($schema->getColumnListing('media_assets'))->toBe([
        'id',
        'reference_key',
        'created_at',
        'updated_at',
    ])->and($schema->getColumnListing('media_provider_bindings'))->toBe([
        'id',
        'media_asset_id',
        'provider',
        'provider_record_key',
        'created_at',
        'updated_at',
    ])->and(Schema::hasColumns('media_attachments', [
        'media_id',
        'media_asset_id',
    ]))->toBeTrue()
        ->and(Schema::hasTable('media_folders'))->toBeFalse();

    $assetColumns = $schema->getColumnListing('media_assets');

    expect($assetColumns)->not->toContain(
        'validation_status',
        'lifecycle_status',
        'disk',
        'path',
        'sha256',
        'storage_identity_hash',
        'normalization_version',
        'repair_context',
    );

    $assetIndexes = collect($schema->getIndexes('media_assets'));
    $bindingIndexes = collect($schema->getIndexes('media_provider_bindings'));
    $bindingForeignKeys = collect($schema->getForeignKeys('media_provider_bindings'));
    $attachmentForeignKeys = collect($schema->getForeignKeys('media_attachments'));

    expect($assetIndexes->contains(
        fn (array $index): bool => $index['unique'] && $index['columns'] === ['reference_key'],
    ))->toBeTrue()
        ->and($bindingIndexes->contains(
            fn (array $index): bool => $index['unique'] && $index['columns'] === ['provider', 'provider_record_key'],
        ))->toBeTrue()
        ->and($bindingIndexes->contains(
            fn (array $index): bool => $index['unique'] && $index['columns'] === ['media_asset_id', 'provider'],
        ))->toBeTrue()
        ->and($bindingForeignKeys->contains(
            fn (array $foreignKey): bool => $foreignKey['columns'] === ['media_asset_id']
                && $foreignKey['foreign_table'] === 'media_assets',
        ))->toBeTrue()
        ->and($attachmentForeignKeys->contains(
            fn (array $foreignKey): bool => $foreignKey['columns'] === ['media_asset_id']
                && $foreignKey['foreign_table'] === 'media_assets',
        ))->toBeTrue();
});

it('generates an immutable portable asset key', function (): void {
    $asset = MediaAsset::query()->create();

    expect($asset->reference_key)->toMatch('/^[0-9A-HJKMNP-TV-Z]{26}$/');

    $originalKey = $asset->reference_key;

    expect(fn () => $asset->forceFill(['reference_key' => (string) Str::ulid()])->save())
        ->toThrow(LogicException::class, 'immutable');

    expect($asset->refresh()->reference_key)->toBe($originalKey);
});

it('binds one curator record per asset while media id remains owner authority', function (): void {
    $media = Media::factory()->create();
    $asset = MediaAsset::query()->create(['reference_key' => $media->reference_key]);
    $binding = MediaProviderBinding::query()->create([
        'media_asset_id' => $asset->getKey(),
        'provider' => 'curator',
        'provider_record_key' => (string) $media->getKey(),
    ]);
    $group = ContentGroup::factory()->create();
    $attachment = MediaAttachment::query()->create([
        'media_id' => $media->getKey(),
        'media_asset_id' => $asset->getKey(),
        'attachable_type' => 'content_group',
        'attachable_id' => $group->getKey(),
        'role' => 'cover',
    ]);

    expect($attachment->media->is($media))->toBeTrue()
        ->and($attachment->mediaAsset->is($asset))->toBeTrue()
        ->and($asset->providerBindings()->firstOrFail()->is($binding))->toBeTrue()
        ->and($binding->mediaAsset->is($asset))->toBeTrue()
        ->and($media->providerBinding->is($binding))->toBeTrue()
        ->and($media->mediaAsset->is($asset))->toBeTrue();

    expect(fn () => MediaProviderBinding::query()->create([
        'media_asset_id' => $asset->getKey(),
        'provider' => 'curator',
        'provider_record_key' => (string) ($media->getKey() + 1),
    ]))->toThrow(QueryException::class);

    $otherAsset = MediaAsset::query()->create();

    expect(fn () => MediaProviderBinding::query()->create([
        'media_asset_id' => $otherAsset->getKey(),
        'provider' => 'curator',
        'provider_record_key' => (string) $media->getKey(),
    ]))->toThrow(QueryException::class)
        ->and(fn () => $asset->delete())->toThrow(QueryException::class);
});
