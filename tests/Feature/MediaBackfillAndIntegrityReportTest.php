<?php

use App\Enums\ImageUploadPurpose;
use App\Enums\MediaAttachmentRole;
use App\Enums\MediaMutationOperationType;
use App\Enums\MediaMutationStatus;
use App\Models\ContentGroup;
use App\Models\Media;
use App\Models\MediaAttachment;
use App\Models\MediaMutationOperation;
use App\Settings\PublicContentSettings;
use App\Support\Media\ImageUploadValidator;
use App\Support\Media\MediaIntegrityReporter;
use App\Support\PublicFront\PublicFrontConfigRegistry;
use App\Support\SettingsLifecycle\PublicSettingsPackage;
use App\Support\SettingsLifecycle\SettingsMediaIdentityProjector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\LaravelSettings\SettingsContainer;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Http::preventStrayRequests();
    Storage::fake('public');
    Storage::fake('local');
});

function saveCuratorG1Setting(string $name, array $payload): void
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

    app()->forgetInstance(PublicContentSettings::class);
    app(SettingsContainer::class)->clearCache();
}

it('treats settings media reference keys and legacy paths as atomic selection pairs', function (): void {
    $projector = app(SettingsMediaIdentityProjector::class);

    expect($projector->expandSelectedPaths(['default_images.global.path']))
        ->toEqualCanonicalizing([
            'default_images.global.path',
            'default_images.global.media_reference_key',
        ])
        ->and($projector->expandSelectedPaths(['about_page.blocks.hero.image_media_reference_key']))
        ->toEqualCanonicalizing([
            'about_page.blocks.hero.image_media_reference_key',
            'about_page.blocks.hero.image_path',
        ]);
});

it('backfills media reference keys and owner attachments idempotently outside schema migrations', function (): void {
    $source = base64_decode(trim((string) file_get_contents(base_path('tests/Fixtures/media/valid.png.base64'))), true);
    expect($source)->toBeString();
    $contents = app(ImageUploadValidator::class)->validateBytes(
        $source,
        'canonical.png',
        ImageUploadPurpose::ContentGroupCover,
    )->contents;
    $name = (string) Str::ulid();
    $media = Media::factory()->create([
        'directory' => ImageUploadPurpose::ContentGroupCover->root(),
        'name' => $name,
        'path' => ImageUploadPurpose::ContentGroupCover->root()."/{$name}.png",
        'width' => 2,
        'height' => 2,
        'size' => strlen($contents),
        'type' => 'image/png',
        'ext' => 'png',
    ]);
    Storage::disk('public')->put($media->path, $contents, 'public');
    DB::table('curator')->where('id', $media->getKey())->update(['reference_key' => null]);
    $group = ContentGroup::factory()->create(['cover_path' => $media->path]);

    $this->artisan('media:backfill-reference-keys')
        ->expectsOutputToContain('eligible=1, disallowed=0')
        ->assertSuccessful();
    expect(Media::query()->findOrFail($media->getKey())->reference_key)->toBeNull();
    expect(collect(app(MediaIntegrityReporter::class)->report()['media'])
        ->firstWhere('media_id', $media->getKey())['recommended_disposition'])->toBe('backfill_reference_key');

    $this->artisan('media:backfill-reference-keys', ['--apply' => true])
        ->expectsOutputToContain('updated=1, disallowed=0')
        ->assertSuccessful();
    $referenceKey = Media::query()->findOrFail($media->getKey())->reference_key;
    $proof = MediaMutationOperation::query()->sole();
    expect($referenceKey)->toMatch('/^[0-9A-HJKMNP-TV-Z]{26}$/')
        ->and($proof->operation)->toBe(MediaMutationOperationType::ReferenceKeyBackfill)
        ->and($proof->status)->toBe(MediaMutationStatus::Completed)
        ->and($proof->destination_sha256)->toBe(hash('sha256', $contents));

    $this->artisan('media:backfill-reference-keys', ['--apply' => true])
        ->expectsOutputToContain('updated=0, disallowed=0')
        ->assertSuccessful();
    expect(Media::query()->findOrFail($media->getKey())->reference_key)->toBe($referenceKey);
    expect(MediaMutationOperation::query()->count())->toBe(1);

    $this->artisan('media:backfill-attachments')
        ->expectsOutputToContain('eligible=1')
        ->assertSuccessful();
    expect(MediaAttachment::query()->count())->toBe(0);

    $this->artisan('media:backfill-attachments', ['--apply' => true])
        ->expectsOutputToContain('created=1')
        ->assertSuccessful();
    $this->artisan('media:backfill-attachments', ['--apply' => true])
        ->expectsOutputToContain('existing=1')
        ->assertSuccessful();

    expect(MediaAttachment::query()->count())->toBe(1)
        ->and($group->coverMediaAttachment()->firstOrFail()->media_id)->toBe($media->getKey());
});

it('keeps invalid bytes and existing svg rows unkeyed and explicitly reportable', function (): void {
    $invalidName = (string) Str::ulid();
    $invalid = Media::factory()->create([
        'name' => $invalidName,
        'path' => ImageUploadPurpose::ContentGroupCover->root()."/{$invalidName}.jpg",
        'size' => strlen('<?php echo "owned"; ?>'),
    ]);
    DB::table('curator')->where('id', $invalid->getKey())->update(['reference_key' => null]);
    Storage::disk('public')->put($invalid->path, '<?php echo "owned"; ?>', 'public');

    $jpeg = base64_decode(trim((string) file_get_contents(base_path('tests/Fixtures/media/valid.jpg.base64'))), true);
    expect($jpeg)->toBeString();
    $comment = '<?php echo "embedded"; ?>';
    $nonCanonicalBytes = substr($jpeg, 0, 2)
        ."\xFF\xFE".pack('n', strlen($comment) + 2).$comment
        .substr($jpeg, 2);
    $nonCanonicalName = (string) Str::ulid();
    $nonCanonical = Media::factory()->create([
        'name' => $nonCanonicalName,
        'path' => ImageUploadPurpose::ContentGroupCover->root()."/{$nonCanonicalName}.jpg",
        'width' => 2,
        'height' => 2,
        'size' => strlen($nonCanonicalBytes),
    ]);
    DB::table('curator')->where('id', $nonCanonical->getKey())->update(['reference_key' => null]);
    Storage::disk('public')->put($nonCanonical->path, $nonCanonicalBytes, 'public');

    $svgRows = collect([6, 7])->map(function (int $id): Media {
        $contents = "<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 10 10\"><path d=\"M0 0h10v10z\" /></svg>\n";
        $name = "legacy-header-{$id}";
        $media = Media::factory()->create([
            'id' => $id,
            'directory' => ImageUploadPurpose::HeaderLogo->root(),
            'name' => $name,
            'path' => ImageUploadPurpose::HeaderLogo->root()."/{$name}.svg",
            'width' => null,
            'height' => null,
            'size' => strlen($contents),
            'type' => 'image/svg+xml',
            'ext' => 'svg',
        ]);
        DB::table('curator')->where('id', $media->getKey())->update(['reference_key' => null]);
        Storage::disk('public')->put($media->path, $contents, 'public');

        return $media;
    });
    $checksums = $svgRows->mapWithKeys(fn (Media $media): array => [
        $media->getKey() => hash('sha256', Storage::disk('public')->get($media->path)),
    ]);

    $this->artisan('media:backfill-reference-keys', ['--apply' => true])
        ->expectsOutputToContain('updated=0, disallowed=2, pending_svg_sanitation=2')
        ->assertSuccessful();

    expect(Media::query()->whereIn('id', [$invalid->getKey(), $nonCanonical->getKey(), 6, 7])->whereNull('reference_key')->count())->toBe(4);
    $report = collect(app(MediaIntegrityReporter::class)->report()['media'])->keyBy('media_id');
    expect($report[$invalid->getKey()]['recommended_disposition'])->toBe('review_and_quarantine')
        ->and($report[$nonCanonical->getKey()]['recommended_disposition'])->toBe('review_and_quarantine')
        ->and($report[6]['recommended_disposition'])->toBe('pending_svg_sanitation')
        ->and($report[7]['recommended_disposition'])->toBe('pending_svg_sanitation');

    foreach ($svgRows as $media) {
        expect(hash('sha256', Storage::disk('public')->get($media->path)))->toBe($checksums[$media->getKey()]);
    }
});

it('refuses ambiguous disallowed and conflicting attachment backfill candidates', function (): void {
    $name = (string) Str::ulid();
    $path = "content-groups/covers/{$name}.jpg";
    $first = Media::factory()->create(['name' => $name, 'path' => $path]);
    Media::factory()->create(['name' => $name, 'path' => $path]);
    $duplicateOwner = ContentGroup::factory()->create(['cover_path' => $path]);

    $disallowed = Media::factory()->create(['visibility' => 'private']);
    $disallowedOwner = ContentGroup::factory()->create(['cover_path' => $disallowed->path]);

    $conflictOwner = ContentGroup::factory()->create(['cover_path' => $first->path]);
    $other = Media::factory()->create();
    MediaAttachment::query()->create([
        'media_id' => $other->getKey(),
        'attachable_type' => 'content_group',
        'attachable_id' => $conflictOwner->getKey(),
        'role' => MediaAttachmentRole::Cover,
        'position' => 0,
    ]);

    $this->artisan('media:backfill-attachments', ['--apply' => true])
        ->expectsOutputToContain('duplicate=2')
        ->assertSuccessful();

    expect($duplicateOwner->mediaAttachments()->count())->toBe(0)
        ->and($disallowedOwner->mediaAttachments()->count())->toBe(0)
        ->and($conflictOwner->mediaAttachments()->firstOrFail()->media_id)->toBe($other->getKey());
});

it('backfills settings reference keys alongside paths and fails closed on identity mismatch', function (): void {
    $media = Media::factory()->create([
        'directory' => ImageUploadPurpose::DefaultImage->root(),
        'name' => 'settings-default',
        'path' => ImageUploadPurpose::DefaultImage->root().'/settings-default.jpg',
    ]);
    $defaults = PublicFrontConfigRegistry::defaults()['default_images'];
    $defaults['global'] = [
        'mode' => 'custom',
        'path' => $media->path,
        'media_reference_key' => null,
    ];
    saveCuratorG1Setting('default_images', $defaults);

    $this->artisan('media:backfill-settings-reference-keys')
        ->expectsOutputToContain('would backfill')
        ->assertSuccessful();
    expect(json_decode(DB::table('settings')->where('group', PublicContentSettings::group())->where('name', 'default_images')->value('payload'), true)['global']['media_reference_key'])->toBeNull();

    $this->artisan('media:backfill-settings-reference-keys', ['--apply' => true])
        ->assertSuccessful();
    $stored = json_decode(DB::table('settings')->where('group', PublicContentSettings::group())->where('name', 'default_images')->value('payload'), true);
    expect($stored['global']['media_reference_key'])->toBe($media->reference_key)
        ->and($stored['global']['path'])->toBe($media->path);

    $this->artisan('media:backfill-settings-reference-keys', ['--apply' => true])
        ->expectsOutputToContain('already reconciled')
        ->assertSuccessful();

    $other = Media::factory()->create([
        'directory' => ImageUploadPurpose::DefaultImage->root(),
        'name' => 'other-default',
        'path' => ImageUploadPurpose::DefaultImage->root().'/other-default.jpg',
    ]);
    $defaults['global']['media_reference_key'] = $media->reference_key;
    $defaults['global']['path'] = $other->path;
    saveCuratorG1Setting('default_images', $defaults);

    $this->artisan('media:backfill-settings-reference-keys', ['--apply' => true])
        ->expectsOutputToContain('disagree')
        ->assertFailed();
});

it('backfills nested settings identities atomically while preserving non-image blocks', function (): void {
    $header = Media::factory()->create([
        'directory' => ImageUploadPurpose::HeaderLogo->root(),
        'name' => 'header-logo',
        'path' => ImageUploadPurpose::HeaderLogo->root().'/header-logo.jpg',
    ]);
    $about = Media::factory()->create([
        'directory' => ImageUploadPurpose::AboutImage->root(),
        'name' => 'about-image',
        'path' => ImageUploadPurpose::AboutImage->root().'/about-image.jpg',
    ]);
    $team = Media::factory()->create([
        'directory' => ImageUploadPurpose::TeamImage->root(),
        'name' => 'team-image',
        'path' => ImageUploadPurpose::TeamImage->root().'/team-image.jpg',
    ]);
    $default = Media::factory()->create([
        'directory' => ImageUploadPurpose::DefaultImage->root(),
        'name' => 'locked-default',
        'path' => ImageUploadPurpose::DefaultImage->root().'/locked-default.jpg',
    ]);

    $menu = PublicFrontConfigRegistry::defaults()['menu_config'];
    $menu['logo']['light_path'] = $header->path;
    $menu['logo']['light_media_reference_key'] = null;
    saveCuratorG1Setting('menu_config', $menu);

    $aboutPage = PublicFrontConfigRegistry::defaults()['about_page'];
    $aboutPage['blocks'] = [
        [
            'type' => 'image',
            'data' => [
                'image_path' => $about->path,
                'image_media_reference_key' => null,
                'alt_text' => 'About image',
            ],
        ],
        [
            'type' => 'rich_text',
            'data' => [
                'rich_content' => '<p>Preserve me</p>',
                'image_path' => 'not-an-image-block.txt',
            ],
        ],
    ];
    $aboutPage['team_profiles'] = [[
        'name' => 'Team member',
        'image_path' => $team->path,
        'image_media_reference_key' => null,
    ]];
    saveCuratorG1Setting('about_page', $aboutPage);

    $defaults = PublicFrontConfigRegistry::defaults()['default_images'];
    $defaults['global'] = [
        'mode' => 'custom',
        'path' => $default->path,
        'media_reference_key' => null,
    ];
    saveCuratorG1Setting('default_images', $defaults);

    DB::table('settings')
        ->where('group', PublicContentSettings::group())
        ->where('name', 'menu_config')
        ->update(['locked' => true]);

    $this->artisan('media:backfill-settings-reference-keys', ['--apply' => true])
        ->expectsOutputToContain('locked')
        ->assertFailed();

    $storedAbout = json_decode(DB::table('settings')->where('group', PublicContentSettings::group())->where('name', 'about_page')->value('payload'), true);
    $storedDefaults = json_decode(DB::table('settings')->where('group', PublicContentSettings::group())->where('name', 'default_images')->value('payload'), true);
    expect($storedAbout['blocks'][0]['data']['image_media_reference_key'])->toBeNull()
        ->and($storedAbout['team_profiles'][0]['image_media_reference_key'])->toBeNull()
        ->and($storedDefaults['global']['media_reference_key'])->toBeNull();

    DB::table('settings')
        ->where('group', PublicContentSettings::group())
        ->where('name', 'menu_config')
        ->update(['locked' => false]);

    $this->artisan('media:backfill-settings-reference-keys', ['--apply' => true])
        ->assertSuccessful();

    $storedMenu = json_decode(DB::table('settings')->where('group', PublicContentSettings::group())->where('name', 'menu_config')->value('payload'), true);
    $storedAbout = json_decode(DB::table('settings')->where('group', PublicContentSettings::group())->where('name', 'about_page')->value('payload'), true);
    $storedDefaults = json_decode(DB::table('settings')->where('group', PublicContentSettings::group())->where('name', 'default_images')->value('payload'), true);

    expect($storedMenu['logo']['light_media_reference_key'])->toBe($header->reference_key)
        ->and($storedAbout['blocks'][0]['data']['image_media_reference_key'])->toBe($about->reference_key)
        ->and($storedAbout['team_profiles'][0]['image_media_reference_key'])->toBe($team->reference_key)
        ->and($storedAbout['blocks'][1]['data'])->toBe([
            'rich_content' => '<p>Preserve me</p>',
            'image_path' => 'not-an-image-block.txt',
        ])
        ->and($storedDefaults['global']['media_reference_key'])->toBe($default->reference_key);
});

it('exports portable settings identities and restores their local paths without guessing', function (): void {
    $media = Media::factory()->create([
        'directory' => ImageUploadPurpose::DefaultImage->root(),
        'name' => 'portable-default',
        'path' => ImageUploadPurpose::DefaultImage->root().'/portable-default.jpg',
    ]);
    $defaults = PublicFrontConfigRegistry::defaults()['default_images'];
    $defaults['global'] = [
        'mode' => 'custom',
        'path' => $media->path,
        'media_reference_key' => $media->reference_key,
    ];
    saveCuratorG1Setting('default_images', $defaults);

    $package = PublicSettingsPackage::portableFromCurrentSettings();
    $portable = $package->toArray();

    expect($portable['portable'])->toBeTrue()
        ->and(data_get($portable, 'payload.default_images.global.media_reference_key'))->toBe($media->reference_key)
        ->and(data_get($portable, 'payload.default_images.global'))->not->toHaveKey('path');

    $localized = PublicSettingsPackage::fromArray($portable)->payloadForApplication();

    expect(data_get($localized, 'default_images.global.media_reference_key'))->toBe($media->reference_key)
        ->and(data_get($localized, 'default_images.global.path'))->toBe($media->path);

    $other = Media::factory()->create([
        'directory' => ImageUploadPurpose::DefaultImage->root(),
        'name' => 'portable-other',
        'path' => ImageUploadPurpose::DefaultImage->root().'/portable-other.jpg',
    ]);
    $mismatched = $portable;
    $mismatched['portable'] = false;
    $mismatched['payload']['default_images']['global']['path'] = $other->path;
    $mismatched['checksum'] = PublicSettingsPackage::payloadChecksum($mismatched['payload']);

    expect(fn () => PublicSettingsPackage::fromArray($mismatched)->payloadForApplication())
        ->toThrow(InvalidArgumentException::class, 'disagree');
});

it('refuses ambiguous nested about image identity without modifying settings', function (): void {
    $aboutPage = PublicFrontConfigRegistry::defaults()['about_page'];
    $aboutPage['blocks'] = [[
        'type' => 'image',
        'image_path' => 'about/outer.jpg',
        'data' => [
            'image_path' => 'about/inner.jpg',
        ],
    ]];
    saveCuratorG1Setting('about_page', $aboutPage);
    $before = DB::table('settings')
        ->where('group', PublicContentSettings::group())
        ->where('name', 'about_page')
        ->value('payload');

    $this->artisan('media:backfill-settings-reference-keys', ['--apply' => true])
        ->expectsOutputToContain('ambiguous')
        ->assertFailed();

    expect(DB::table('settings')
        ->where('group', PublicContentSettings::group())
        ->where('name', 'about_page')
        ->value('payload'))->toBe($before);
});

it('reports checksums disallowed rows duplicates attachment corruption settings mismatches and incomplete journals without mutation', function (): void {
    $present = Media::factory()->create();
    Storage::disk('public')->put($present->path, 'trusted image bytes');
    $group = ContentGroup::factory()->create(['cover_path' => $present->path]);
    MediaAttachment::query()->create([
        'media_id' => $present->getKey(),
        'attachable_type' => 'content_group',
        'attachable_id' => $group->getKey(),
        'role' => MediaAttachmentRole::Cover,
        'position' => 0,
    ]);

    $duplicate = Media::factory()->create([
        'name' => $present->name,
        'path' => $present->path,
    ]);
    $unsafe = Media::factory()->create([
        'directory' => 'content-groups/covers',
        'name' => 'unsafe',
        'path' => '../unsafe.jpg',
    ]);
    $wrongDisk = Media::factory()->create(['disk' => 'private']);

    $orphanAttachmentId = DB::table('media_attachments')->insertGetId([
        'media_id' => $present->getKey(),
        'attachable_type' => 'forged_owner',
        'attachable_id' => 999999,
        'role' => 'forged_role',
        'position' => 3,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $settingsMedia = Media::factory()->create([
        'directory' => ImageUploadPurpose::DefaultImage->root(),
        'name' => 'settings-one',
        'path' => ImageUploadPurpose::DefaultImage->root().'/settings-one.jpg',
    ]);
    $otherSettingsMedia = Media::factory()->create([
        'directory' => ImageUploadPurpose::DefaultImage->root(),
        'name' => 'settings-two',
        'path' => ImageUploadPurpose::DefaultImage->root().'/settings-two.jpg',
    ]);
    $defaults = PublicFrontConfigRegistry::defaults()['default_images'];
    $defaults['global'] = [
        'mode' => 'custom',
        'media_reference_key' => $settingsMedia->reference_key,
        'path' => $otherSettingsMedia->path,
    ];
    saveCuratorG1Setting('default_images', $defaults);

    $operation = MediaMutationOperation::factory()->create([
        'media_id' => $present->getKey(),
        'media_id_snapshot' => $present->getKey(),
        'media_reference_key' => $present->reference_key,
        'operation' => MediaMutationOperationType::Rename,
        'status' => MediaMutationStatus::Failed,
        'last_error' => 'fixture failure',
    ]);

    $before = [
        'media' => DB::table('curator')->count(),
        'attachments' => DB::table('media_attachments')->count(),
        'operations' => DB::table('media_mutation_operations')->count(),
    ];
    $report = app(MediaIntegrityReporter::class)->report();

    $presentRow = collect($report['media'])->firstWhere('media_id', $present->getKey());
    expect($presentRow['file_state'])->toBe('present')
        ->and($presentRow['sha256'])->toBe(hash('sha256', 'trusted image bytes'))
        ->and(collect($report['duplicate_locations'])->firstWhere('path', $present->path)['media'])->toHaveCount(2)
        ->and(collect($report['media'])->firstWhere('media_id', $unsafe->getKey())['file_state'])->toBe('unsafe_path')
        ->and(collect($report['media'])->firstWhere('media_id', $wrongDisk->getKey())['file_state'])->toBe('wrong_disk')
        ->and(collect($report['attachment_issues'])->flatMap(fn (array $row): array => $row['issues'])->all())->toContain('invalid_attachable_type', 'invalid_role')
        ->and(collect($report['orphan_attachments'])->firstWhere('attachment_id', $orphanAttachmentId)['issues'])->toContain('invalid_attachable_type')
        ->and(collect($report['settings_identity_issues'])->flatMap(fn (array $row): array => $row['issues'])->all())->toContain('reference_path_mismatch')
        ->and(collect($report['incomplete_mutations'])->firstWhere('id', $operation->getKey())['issues'])->toContain('review_failed_operation')
        ->and([
            'media' => DB::table('curator')->count(),
            'attachments' => DB::table('media_attachments')->count(),
            'operations' => DB::table('media_mutation_operations')->count(),
        ])->toBe($before);

    Artisan::call('media:report-integrity', ['--json' => true]);
    $json = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
    expect($json)->toHaveKeys([
        'media',
        'duplicate_locations',
        'attachment_issues',
        'orphan_attachments',
        'settings_identity_issues',
        'incomplete_mutations',
        'summary',
    ]);
});
