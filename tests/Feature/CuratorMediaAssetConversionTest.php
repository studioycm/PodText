<?php

use App\Enums\MediaAttachmentRole;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\Media;
use App\Models\MediaAsset;
use App\Models\MediaAttachment;
use App\Models\MediaProviderBinding;
use App\Models\User;
use App\Settings\PublicContentSettings;
use App\Support\Media\CuratorMediaAssetConverter;
use App\Support\Media\MediaAttachmentManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Http::preventStrayRequests();
    Storage::fake('public');
    Storage::fake('local');
});

function setCuratorReferenceKey(Media $media, ?string $referenceKey): void
{
    DB::table('curator')
        ->where('id', $media->getKey())
        ->update(['reference_key' => $referenceKey]);
}

function saveMediaConversionSetting(string $name, array $payload): void
{
    DB::table('settings')->updateOrInsert(
        [
            'group' => PublicContentSettings::group(),
            'name' => $name,
        ],
        [
            'locked' => false,
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ],
    );
}

function readMediaConversionSetting(string $name): array
{
    $payload = DB::table('settings')
        ->where('group', PublicContentSettings::group())
        ->where('name', $name)
        ->value('payload');

    return json_decode((string) $payload, true, flags: JSON_THROW_ON_ERROR);
}

it('reuses only unique valid keys and creates one asset and curator binding per row', function (): void {
    $valid = Media::factory()->create();
    $missing = Media::factory()->create();
    $malformed = Media::factory()->create();
    $duplicateA = Media::factory()->create();
    $duplicateB = Media::factory()->create();
    $duplicateKey = (string) Str::ulid();

    setCuratorReferenceKey($missing, null);
    setCuratorReferenceKey($malformed, 'not-a-ulid');
    Schema::table('curator', function ($table): void {
        $table->dropUnique(['reference_key']);
    });
    setCuratorReferenceKey($duplicateA, $duplicateKey);
    setCuratorReferenceKey($duplicateB, $duplicateKey);

    $pathsBefore = Media::query()->orderBy('id')->pluck('path', 'id')->all();
    $report = app(CuratorMediaAssetConverter::class)->convert();
    $media = Media::query()->orderBy('id')->get()->keyBy('id');

    expect(MediaAsset::query()->count())->toBe(5)
        ->and(MediaProviderBinding::query()->count())->toBe(5)
        ->and(MediaProviderBinding::query()->orderBy('provider_record_key')->pluck('provider_record_key')->all())
        ->toBe(collect($media->keys())->map(fn (mixed $id): string => (string) $id)->sort()->values()->all())
        ->and($media[$valid->getKey()]->reference_key)->toBe($valid->reference_key)
        ->and($media[$missing->getKey()]->reference_key)->toMatch('/^[0-9A-HJKMNP-TV-Z]{26}$/')
        ->and($media[$malformed->getKey()]->reference_key)->toMatch('/^[0-9A-HJKMNP-TV-Z]{26}$/')
        ->and($media[$duplicateA->getKey()]->reference_key)->not->toBe($duplicateKey)
        ->and($media[$duplicateB->getKey()]->reference_key)->not->toBe($duplicateKey)
        ->and($media[$duplicateA->getKey()]->reference_key)->not->toBe($media[$duplicateB->getKey()]->reference_key)
        ->and(Media::query()->orderBy('id')->pluck('path', 'id')->all())->toBe($pathsBefore)
        ->and($report->count('assets_created'))->toBe(5)
        ->and($report->count('bindings_created'))->toBe(5)
        ->and($report->count('keys_reused'))->toBe(1)
        ->and($report->count('keys_generated'))->toBe(4);
});

it('reuses a unique lowercase ulid without changing its portable value', function (): void {
    $media = Media::factory()->create();
    $lowercaseKey = strtolower((string) Str::ulid());
    setCuratorReferenceKey($media, $lowercaseKey);

    $report = app(CuratorMediaAssetConverter::class)->convert();

    expect($media->fresh()->reference_key)->toBe($lowercaseKey)
        ->and($media->fresh()->mediaAsset->reference_key)->toBe($lowercaseKey)
        ->and($report->count('keys_reused'))->toBe(1)
        ->and($report->count('keys_generated'))->toBe(0);
});

it('is idempotent and bridges existing or uniquely matched owner relationships', function (): void {
    $cover = Media::factory()->create();
    $primary = Media::factory()->create([
        'directory' => 'content-items/images',
        'path' => 'content-items/images/episode.jpg',
        'name' => 'episode',
    ]);
    $attachedGroup = ContentGroup::factory()->create(['cover_path' => 'stale/cover.jpg']);
    $matchedGroup = ContentGroup::factory()->create(['cover_path' => $cover->path]);
    $matchedItem = ContentItem::factory()->create(['image_path' => $primary->path]);
    $existing = MediaAttachment::query()->create([
        'media_id' => $cover->getKey(),
        'attachable_type' => 'content_group',
        'attachable_id' => $attachedGroup->getKey(),
        'role' => MediaAttachmentRole::Cover,
    ]);

    $first = app(CuratorMediaAssetConverter::class)->convert();
    $second = app(CuratorMediaAssetConverter::class)->convert();

    $coverAssetId = $cover->fresh()->mediaAsset->getKey();
    $primaryAssetId = $primary->fresh()->mediaAsset->getKey();

    expect($existing->fresh()->media_id)->toBe($cover->getKey())
        ->and($existing->fresh()->media_asset_id)->toBe($coverAssetId)
        ->and($attachedGroup->fresh()->cover_path)->toBe($cover->path)
        ->and($matchedGroup->coverMediaAttachment()->firstOrFail()->media_id)->toBe($cover->getKey())
        ->and($matchedGroup->coverMediaAttachment()->firstOrFail()->media_asset_id)->toBe($coverAssetId)
        ->and($matchedItem->primaryImageMediaAttachment()->firstOrFail()->media_id)->toBe($primary->getKey())
        ->and($matchedItem->primaryImageMediaAttachment()->firstOrFail()->media_asset_id)->toBe($primaryAssetId)
        ->and(MediaAsset::query()->count())->toBe(2)
        ->and(MediaProviderBinding::query()->count())->toBe(2)
        ->and(MediaAttachment::query()->count())->toBe(3)
        ->and($first->count('owner_paths_repaired'))->toBe(1)
        ->and($first->count('attachments_bridged'))->toBe(1)
        ->and($first->count('attachments_created'))->toBe(2)
        ->and($second->count('assets_created'))->toBe(0)
        ->and($second->count('bindings_created'))->toBe(0)
        ->and($second->count('attachments_created'))->toBe(0);
});

it('reports duplicate paths and missing files without hiding rows or guessing owners', function (): void {
    $first = Media::factory()->create(['path' => 'legacy/shared.jpg']);
    $second = Media::factory()->create(['path' => 'legacy/shared.jpg']);
    $group = ContentGroup::factory()->create(['cover_path' => 'legacy/shared.jpg']);

    $report = app(CuratorMediaAssetConverter::class)->convert();

    expect(Media::query()->count())->toBe(2)
        ->and(MediaAsset::query()->count())->toBe(2)
        ->and(MediaProviderBinding::query()->count())->toBe(2)
        ->and($group->coverMediaAttachment()->doesntExist())->toBeTrue()
        ->and($group->fresh()->cover_path)->toBe('legacy/shared.jpg')
        ->and($report->count('duplicate_paths'))->toBe(1)
        ->and($report->count('unresolved_owners'))->toBe(1)
        ->and($report->count('missing_files'))->toBe(2)
        ->and($first->fresh()->mediaAsset)->not->toBeNull()
        ->and($second->fresh()->mediaAsset)->not->toBeNull();
});

it('fills settings keys only for unique paths and preserves every compatibility path', function (): void {
    $unique = Media::factory()->create(['path' => 'headers/unique.svg']);
    Media::factory()->create(['path' => 'headers/duplicate.svg']);
    Media::factory()->create(['path' => 'headers/duplicate.svg']);
    saveMediaConversionSetting('menu_config', [
        'logo' => [
            'light_path' => 'headers/unique.svg',
            'light_media_reference_key' => null,
            'dark_path' => 'headers/duplicate.svg',
            'dark_media_reference_key' => null,
            'alt_text' => 'Keep this',
        ],
    ]);

    $report = app(CuratorMediaAssetConverter::class)->convert();
    $menu = readMediaConversionSetting('menu_config');

    expect($menu['logo'])->toMatchArray([
        'light_path' => 'headers/unique.svg',
        'light_media_reference_key' => $unique->fresh()->reference_key,
        'dark_path' => 'headers/duplicate.svg',
        'dark_media_reference_key' => null,
        'alt_text' => 'Keep this',
    ])->and($report->count('settings_keys_filled'))->toBe(1)
        ->and($report->count('unresolved_settings'))->toBe(1);
});

it('repairs only an obsolete settings key when its compatibility path is unique', function (): void {
    $unique = Media::factory()->create(['path' => 'headers/unique.svg']);
    $other = Media::factory()->create(['path' => 'headers/other.svg']);
    saveMediaConversionSetting('menu_config', [
        'logo' => [
            'light_path' => $unique->path,
            'light_media_reference_key' => 'obsolete-portable-key',
            'dark_path' => $unique->path,
            'dark_media_reference_key' => $other->reference_key,
        ],
    ]);

    $report = app(CuratorMediaAssetConverter::class)->convert();
    $logo = readMediaConversionSetting('menu_config')['logo'];

    expect($logo['light_media_reference_key'])->toBe($unique->fresh()->reference_key)
        ->and($logo['dark_media_reference_key'])->toBe($other->fresh()->reference_key)
        ->and($logo['light_path'])->toBe($unique->path)
        ->and($logo['dark_path'])->toBe($unique->path)
        ->and($report->count('settings_keys_filled'))->toBe(1)
        ->and($report->count('unresolved_settings'))->toBe(0);
});

it('rolls back every database change when settings reconciliation fails', function (): void {
    $first = Media::factory()->create();
    $second = Media::factory()->create();
    setCuratorReferenceKey($first, null);
    setCuratorReferenceKey($second, null);
    saveMediaConversionSetting('about_page', [
        'blocks' => [[
            'type' => 'image',
            'image_path' => $first->path,
            'data' => [
                'type' => 'image',
                'image_path' => $second->path,
            ],
        ]],
    ]);

    expect(fn () => app(CuratorMediaAssetConverter::class)->convert())
        ->toThrow(InvalidArgumentException::class, 'ambiguous');

    expect(MediaAsset::query()->count())->toBe(0)
        ->and(MediaProviderBinding::query()->count())->toBe(0)
        ->and(MediaAttachment::query()->count())->toBe(0)
        ->and($first->fresh()->reference_key)->toBeNull()
        ->and($second->fresh()->reference_key)->toBeNull();
});

it('performs existence checks without changing managed files', function (): void {
    $present = Media::factory()->create();
    $missing = Media::factory()->create();
    Storage::disk('public')->put($present->path, 'original-bytes');
    $filesBefore = Storage::disk('public')->allFiles();

    $report = app(CuratorMediaAssetConverter::class)->convert();

    expect(Storage::disk('public')->allFiles())->toBe($filesBefore)
        ->and(Storage::disk('public')->get($present->path))->toBe('original-bytes')
        ->and(Storage::disk('public')->missing($missing->path))->toBeTrue()
        ->and($report->count('missing_files'))->toBe(1);
});

it('dual writes the portable bridge for new attachments after conversion', function (): void {
    $actor = User::factory()->admin()->create();
    $group = ContentGroup::factory()->create();
    $media = Media::factory()->create();
    Storage::disk('public')->put($media->path, 'image');
    app(CuratorMediaAssetConverter::class)->convert();

    $attachment = app(MediaAttachmentManager::class)
        ->attach($group, $media, MediaAttachmentRole::Cover, $actor);

    expect($attachment->media_id)->toBe($media->getKey())
        ->and($attachment->media_asset_id)->toBe($media->fresh()->mediaAsset->getKey());
});
