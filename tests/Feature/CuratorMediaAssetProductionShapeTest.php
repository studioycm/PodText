<?php

use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\Media;
use App\Models\MediaAsset;
use App\Models\MediaAttachment;
use App\Models\MediaMutationOperation;
use App\Models\MediaProviderBinding;
use App\Settings\PublicContentSettings;
use App\Support\Media\CuratorMediaAssetConverter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Http::preventStrayRequests();
    Storage::fake('public');
    Storage::fake('local');
});

it('converts the original fifteen null key incident without touching files', function (): void {
    $media = Media::factory()->count(15)->create();

    DB::table('curator')->whereIn('id', $media->modelKeys())->update(['reference_key' => null]);

    $report = app(CuratorMediaAssetConverter::class)->convert();

    expect(Media::query()->whereNull('reference_key')->count())->toBe(0)
        ->and(MediaAsset::query()->count())->toBe(15)
        ->and(MediaProviderBinding::query()->count())->toBe(15)
        ->and($report->count('keys_generated'))->toBe(15)
        ->and(Storage::disk('public')->allFiles())->toBe([]);
});

it('keeps the current fifteen valid key shape idempotent', function (): void {
    $media = Media::factory()->count(15)->create();
    $keysBefore = $media->pluck('reference_key', 'id')->all();

    $first = app(CuratorMediaAssetConverter::class)->convert();
    $second = app(CuratorMediaAssetConverter::class)->convert();

    expect(Media::query()->orderBy('id')->pluck('reference_key', 'id')->all())->toBe($keysBefore)
        ->and(MediaAsset::query()->count())->toBe(15)
        ->and(MediaProviderBinding::query()->count())->toBe(15)
        ->and($first->count('keys_reused'))->toBe(15)
        ->and($second->count('assets_created'))->toBe(0)
        ->and($second->count('bindings_created'))->toBe(0);
});

it('preserves the production shaped 403 row inventory and 108 owner relationships', function (): void {
    $media = Media::factory()->count(403)->create();
    $svgRows = $media->take(3);
    $oversized = $media->get(3);
    $firstDuplicatePair = $media->slice(4, 2);
    $secondDuplicatePair = $media->slice(6, 2);

    foreach ($svgRows as $index => $svg) {
        DB::table('curator')->where('id', $svg->getKey())->update([
            'path' => "legacy/svg-{$index}.svg",
            'name' => "svg-{$index}",
            'type' => 'image/svg+xml',
            'ext' => 'svg',
        ]);
    }

    DB::table('curator')->where('id', $oversized->getKey())->update([
        'size' => 8 * 1024 * 1024,
        'width' => 4000,
        'height' => 4000,
    ]);

    $media = Media::query()->orderBy('id')->get();
    $svgRows = $media->take(3);
    $oversized = $media->get(3);
    $firstDuplicatePair = $media->slice(4, 2);
    $secondDuplicatePair = $media->slice(6, 2);

    foreach ($firstDuplicatePair as $row) {
        Storage::disk('public')->put($row->path, 'duplicate-a');
    }

    foreach ($secondDuplicatePair as $row) {
        Storage::disk('public')->put($row->path, 'duplicate-b');
    }

    foreach (range(1, 5) as $index) {
        Storage::disk('public')->put("managed/orphan-{$index}.jpg", "orphan-{$index}");
    }

    $media->take(108)->each(function (Media $row, int $index): void {
        ContentGroup::factory()->create([
            'title' => "Production group {$index}",
            'cover_path' => $row->path,
        ]);
    });
    ContentItem::factory()->create(['image_path' => null]);
    $settingsPath = $media->get(108)->path;
    DB::table('settings')->updateOrInsert(
        [
            'group' => PublicContentSettings::group(),
            'name' => 'default_images',
        ],
        [
            'locked' => false,
            'payload' => json_encode([
                'global' => [
                    'mode' => 'custom',
                    'path' => $settingsPath,
                    'media_reference_key' => null,
                    'caption' => 'Preserve me',
                ],
            ], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ],
    );

    $rowsBefore = Media::query()
        ->orderBy('id')
        ->get(['id', 'path', 'disk', 'type', 'ext', 'size', 'width', 'height'])
        ->map(fn (Media $row): array => $row->getAttributes())
        ->all();
    $filesBefore = Storage::disk('public')->allFiles();
    $report = app(CuratorMediaAssetConverter::class)->convert();
    $rowsAfter = Media::query()
        ->orderBy('id')
        ->get(['id', 'path', 'disk', 'type', 'ext', 'size', 'width', 'height'])
        ->map(fn (Media $row): array => $row->getAttributes())
        ->all();
    $defaultImages = json_decode(
        (string) DB::table('settings')
            ->where('group', PublicContentSettings::group())
            ->where('name', 'default_images')
            ->value('payload'),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    expect(Media::query()->count())->toBe(403)
        ->and(MediaAsset::query()->count())->toBe(403)
        ->and(MediaProviderBinding::query()->count())->toBe(403)
        ->and(MediaAttachment::query()->count())->toBe(108)
        ->and(MediaMutationOperation::query()->count())->toBe(0)
        ->and($rowsAfter)->toBe($rowsBefore)
        ->and(Storage::disk('public')->allFiles())->toBe($filesBefore)
        ->and(MediaAsset::query()->whereIn('reference_key', $firstDuplicatePair->pluck('reference_key'))->count())->toBe(2)
        ->and(MediaAsset::query()->whereIn('reference_key', $secondDuplicatePair->pluck('reference_key'))->count())->toBe(2)
        ->and($oversized->fresh()->mediaAsset)->not->toBeNull()
        ->and($svgRows->every(fn (Media $row): bool => $row->fresh()->mediaAsset !== null))->toBeTrue()
        ->and($defaultImages['global'])->toMatchArray([
            'path' => $settingsPath,
            'media_reference_key' => $media->get(108)->fresh()->reference_key,
            'caption' => 'Preserve me',
        ])->and($report->count('attachments_created'))->toBe(108)
        ->and($report->count('settings_keys_filled'))->toBe(1);
});
