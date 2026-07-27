<?php

use App\Enums\ImageUploadPurpose;
use App\Enums\LegacyMediaTransitionDisposition;
use App\Enums\MediaMutationOperationType;
use App\Enums\MediaMutationStatus;
use App\Enums\UserRole;
use App\Models\ContentGroup;
use App\Models\Media;
use App\Models\MediaAttachment;
use App\Models\MediaMutationOperation;
use App\Models\User;
use App\Settings\PublicContentSettings;
use App\Support\Media\ImageUploadValidator;
use App\Support\Media\LegacyMediaTransitionExecutor;
use App\Support\Media\LegacyMediaTransitionPlanner;
use App\Support\Media\MediaFilesystemMutationCoordinator;
use App\Support\PublicFront\PublicFrontConfigRegistry;
use App\Support\SettingsLifecycle\PublicContentSettingsWriteCoordinator;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
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

function legacyTransitionFixture(string $name): string
{
    $contents = file_get_contents(base_path("tests/Fixtures/media/{$name}"));
    if (! is_string($contents)) {
        throw new RuntimeException("Missing fixture [{$name}].");
    }

    return str_ends_with($name, '.base64') ? (base64_decode(trim($contents), true) ?: throw new RuntimeException('Invalid fixture.')) : $contents;
}

function legacyTransitionMedia(string $contents, ?string $key = null, ImageUploadPurpose $purpose = ImageUploadPurpose::ContentGroupCover): Media
{
    $name = (string) Str::ulid();
    $path = $purpose->root()."/{$name}.jpg";
    $validated = app(ImageUploadValidator::class)->validateBytes($contents, 'legacy.jpg', $purpose);
    Storage::disk('public')->put($path, $contents, 'public');
    $media = Media::factory()->create([
        'reference_key' => $key ?? (string) Str::ulid(), 'directory' => $purpose->root(), 'name' => $name, 'path' => $path,
        'size' => strlen($contents), 'width' => $validated->width, 'height' => $validated->height,
        'type' => $validated->mimeType, 'ext' => $validated->extension,
    ]);
    DB::table('curator')->where('id', $media->getKey())->update(['reference_key' => $key]);

    return $media->fresh();
}

function legacyTransitionNormalizedJpeg(): string
{
    return app(ImageUploadValidator::class)
        ->validateBytes(legacyTransitionFixture('valid.jpg.base64'), 'legacy.jpg', ImageUploadPurpose::ContentGroupCover)
        ->contents;
}

it('keeps the reviewed legacy disposition vocabulary exhaustive', function (): void {
    expect(array_column(LegacyMediaTransitionDisposition::cases(), 'value'))->toBe([
        'key_only', 'normalize_existing', 'sanitize_svg', 'import_exact_path', 'detach_to_default', 'blocked',
    ]);
});

it('builds a deterministic, exhaustive legacy transition manifest', function (): void {
    $exact = legacyTransitionMedia(legacyTransitionNormalizedJpeg(), null);
    $svgName = (string) Str::ulid();
    $svg = Media::factory()->create(['reference_key' => null, 'directory' => 'header', 'name' => $svgName, 'path' => "header/{$svgName}.svg", 'type' => 'image/svg+xml', 'ext' => 'svg', 'width' => null, 'height' => null, 'size' => strlen('<svg></svg>')]);
    Storage::disk('public')->put($svg->path, '<svg></svg>', 'public');
    $planner = app(LegacyMediaTransitionPlanner::class);
    $first = $planner->manifest();
    $second = $planner->manifest();
    $entries = collect($first->entries)->keyBy('media_id');

    expect($first->digest())->toBe($second->digest())
        ->and($entries[$exact->getKey()]['disposition'])->toBe(LegacyMediaTransitionDisposition::KeyOnly->value)
        ->and($entries[$svg->getKey()]['disposition'])->toBe(LegacyMediaTransitionDisposition::SanitizeSvg->value);
    $planner->assertClosed($first);
});

it('classifies every legacy observation without making a row visible', function (): void {
    $keyOnly = legacyTransitionMedia(legacyTransitionNormalizedJpeg(), null);
    $normalize = legacyTransitionMedia(legacyTransitionFixture('valid.jpg.base64'), null);
    $svgName = (string) Str::ulid();
    $svg = Media::factory()->create(['reference_key' => null, 'directory' => 'header', 'name' => $svgName, 'path' => "header/{$svgName}.svg", 'type' => 'image/svg+xml', 'ext' => 'svg', 'width' => null, 'height' => null, 'size' => strlen('<svg></svg>')]);
    Storage::disk('public')->put($svg->path, '<svg></svg>', 'public');
    $missing = Media::factory()->create(['reference_key' => null, 'directory' => 'content-groups/covers', 'name' => 'missing', 'path' => 'content-groups/covers/missing.jpg']);
    $missingOwner = ContentGroup::factory()->create();
    DB::table('content_groups')->where('id', $missingOwner->getKey())->update(['cover_path' => $missing->path]);
    $blocked = Media::factory()->create(['reference_key' => null, 'directory' => null, 'name' => 'root', 'path' => 'root.jpg']);
    $rowlessPath = 'content-groups/covers/rowless.jpg';
    Storage::disk('public')->put($rowlessPath, legacyTransitionFixture('valid.jpg.base64'), 'public');
    $rowlessOwner = ContentGroup::factory()->create(['cover_path' => $rowlessPath]);

    $entries = collect(app(LegacyMediaTransitionPlanner::class)->manifest()->entries);
    // The assertion below also keeps rowless owner references in the digest.

    expect($entries->firstWhere('media_id', $keyOnly->getKey())['disposition'])->toBe('key_only')
        ->and($entries->firstWhere('media_id', $normalize->getKey())['disposition'])->toBe('normalize_existing')
        ->and($entries->firstWhere('media_id', $svg->getKey())['disposition'])->toBe('sanitize_svg')
        ->and($entries->firstWhere('media_id', $missing->getKey())['disposition'])->toBe('detach_to_default')
        ->and($entries->firstWhere('media_id', $blocked->getKey())['disposition'])->toBe('blocked')
        ->and($entries->first(fn (array $entry): bool => $entry['path'] === $rowlessPath)['disposition'])->toBe('import_exact_path')
        ->and($entries->first(fn (array $entry): bool => $entry['path'] === $rowlessPath)['active_references'])->toContain("content_group:{$rowlessOwner->getKey()}:cover");
});

it('reports an explicit closure failure for a partial Curator schema', function (): void {
    Schema::drop('curator');
    Schema::create('curator', fn (Blueprint $table) => $table->id());
    $planner = app(LegacyMediaTransitionPlanner::class);
    $manifest = $planner->manifest();

    expect(fn () => $planner->assertClosed($manifest))->toThrow(RuntimeException::class, 'schema is incomplete');
});

it('issues a key only after exact-byte proof and journals it idempotently', function (): void {
    $media = legacyTransitionMedia(legacyTransitionNormalizedJpeg(), null);
    $actor = User::factory()->admin()->create();
    $planner = app(LegacyMediaTransitionPlanner::class);
    $manifest = $planner->manifest();
    $digest = $manifest->digest();

    expect(collect($manifest->entries)->firstWhere('media_id', $media->getKey())['disposition'])->toBe(LegacyMediaTransitionDisposition::KeyOnly->value);
    expect(collect(app(LegacyMediaTransitionPlanner::class)->manifest()->entries)->firstWhere('media_id', $media->getKey())['disposition'])->toBe(LegacyMediaTransitionDisposition::KeyOnly->value);
    expect(collect(app(LegacyMediaTransitionPlanner::class)->manifest()->entries)->firstWhere('media_id', $media->getKey())['disposition'])->toBe(LegacyMediaTransitionDisposition::KeyOnly->value);

    expect(app(LegacyMediaTransitionExecutor::class)->apply([$media->getKey()], $digest, $actor))
        ->toBe(['key_only_completed'])
        ->and($media->fresh()->reference_key)->toMatch('/^[0-9A-HJKMNP-TV-Z]{26}$/');
    expect(app(LegacyMediaTransitionExecutor::class)->apply([$media->getKey()], app(LegacyMediaTransitionPlanner::class)->manifest()->digest(), $actor))
        ->toBe(['already_transitioned']);
});

it('rejects an apply whose reviewed digest changed', function (): void {
    $media = legacyTransitionMedia(legacyTransitionNormalizedJpeg(), null);
    $actor = User::factory()->admin()->create();
    expect(fn () => app(LegacyMediaTransitionExecutor::class)->apply([$media->getKey()], str_repeat('a', 64), $actor))
        ->toThrow(RuntimeException::class, 'manifest changed');
});

it('denies a non-admin actor even with an exact manifest digest', function (): void {
    $media = legacyTransitionMedia(legacyTransitionNormalizedJpeg(), null);
    $actor = User::factory()->role(UserRole::Moderator)->create();
    $manifest = app(LegacyMediaTransitionPlanner::class)->manifest();

    expect(fn () => app(LegacyMediaTransitionExecutor::class)->apply([$media->getKey()], $manifest->digest(), $actor))
        ->toThrow(AuthorizationException::class);
});

it('rejects source bytes changed after review without issuing a key', function (): void {
    $media = legacyTransitionMedia(legacyTransitionNormalizedJpeg(), null);
    $actor = User::factory()->admin()->create();
    $manifest = app(LegacyMediaTransitionPlanner::class)->manifest();
    Storage::disk('public')->put($media->path, 'changed after review', 'public');

    expect(fn () => app(LegacyMediaTransitionExecutor::class)->apply([$media->getKey()], $manifest->digest(), $actor))
        ->toThrow(RuntimeException::class, 'manifest changed');
    expect(DB::table('curator')->where('id', $media->getKey())->value('reference_key'))->toBeNull();
});

it('emits a stable raw settings token for a valid default-image legacy row', function (): void {
    $media = legacyTransitionMedia(legacyTransitionNormalizedJpeg(), null, ImageUploadPurpose::DefaultImage);
    $defaults = PublicFrontConfigRegistry::defaults()['default_images'];
    $defaults['global']['mode'] = 'custom';
    $defaults['global']['path'] = $media->path;
    DB::table('settings')->updateOrInsert(
        ['group' => PublicContentSettings::group(), 'name' => 'default_images'],
        ['locked' => false, 'payload' => json_encode($defaults, JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now()],
    );
    $entry = collect(app(LegacyMediaTransitionPlanner::class)->manifest()->entries)->first(fn (array $entry): bool => (int) $entry['media_id'] === (int) $media->getKey());

    expect(DB::table('settings')->where('group', PublicContentSettings::group())->where('name', 'default_images')->exists())->toBeTrue()
        ->and($entry['active_references'])->not->toBeEmpty();
});

it('acquires the settings mutex before revalidating a legacy transition plan with settings snapshots', function (): void {
    app()->instance(
        PublicContentSettingsWriteCoordinator::class,
        new PublicContentSettingsWriteCoordinator(waitSeconds: 0),
    );
    $media = legacyTransitionMedia(
        legacyTransitionFixture('valid.jpg.base64'),
        null,
        ImageUploadPurpose::DefaultImage,
    );
    $defaults = PublicFrontConfigRegistry::defaults()['default_images'];
    $defaults['global'] = [
        'mode' => 'custom',
        'path' => $media->path,
        'media_reference_key' => null,
    ];
    DB::table('settings')->updateOrInsert(
        ['group' => PublicContentSettings::group(), 'name' => 'default_images'],
        [
            'locked' => false,
            'payload' => json_encode($defaults, JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ],
    );
    $actor = User::factory()->admin()->create();
    $manifest = app(LegacyMediaTransitionPlanner::class)->manifest();
    $heldLock = Cache::lock(PublicContentSettingsWriteCoordinator::LOCK_KEY, 30);
    expect($heldLock->get())->toBeTrue();

    try {
        expect(fn () => app(LegacyMediaTransitionExecutor::class)->apply(
            [$media->getKey()],
            $manifest->digest(),
            $actor,
        ))->toThrow(
            LockTimeoutException::class,
            'Timed out acquiring the public content settings write lock.',
        );
    } finally {
        $heldLock->release();
    }

    $stored = json_decode((string) DB::table('settings')
        ->where('group', PublicContentSettings::group())
        ->where('name', 'default_images')
        ->value('payload'), true, flags: JSON_THROW_ON_ERROR);

    expect($media->fresh()->reference_key)->toBeNull()
        ->and($stored['global']['path'])->toBe($media->path)
        ->and($stored['global']['media_reference_key'])->toBeNull();
    Storage::disk('public')->assertExists($media->path);
});

it('does not treat an arbitrary settings string containing a path as a media reference', function (): void {
    $media = legacyTransitionMedia(legacyTransitionNormalizedJpeg(), null, ImageUploadPurpose::DefaultImage);
    DB::table('settings')->updateOrInsert(
        ['group' => PublicContentSettings::group(), 'name' => 'default_images'],
        ['locked' => false, 'payload' => json_encode(['note' => "do not match {$media->path}"], JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now()],
    );

    $entry = collect(app(LegacyMediaTransitionPlanner::class)->manifest()->entries)->firstWhere('media_id', $media->getKey());

    expect($entry['active_references'])->toBe([]);
});

it('keeps the existing Curator ID while normalizing a production-shaped active owner', function (): void {
    $media = legacyTransitionMedia(legacyTransitionFixture('valid.jpg.base64'), null);
    $sourcePath = $media->path;
    $group = ContentGroup::factory()->create(['cover_path' => $media->path]);
    $default = legacyTransitionMedia(legacyTransitionFixture('valid.jpg.base64'), null, ImageUploadPurpose::DefaultImage);
    $defaults = PublicFrontConfigRegistry::defaults()['default_images'];
    $defaults['content_group'] = ['mode' => 'custom', 'path' => $default->path, 'media_reference_key' => null];
    DB::table('settings')->updateOrInsert(
        ['group' => PublicContentSettings::group(), 'name' => 'default_images'],
        ['locked' => false, 'payload' => json_encode($defaults, JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now()],
    );
    $actor = User::factory()->admin()->create();
    $manifest = app(LegacyMediaTransitionPlanner::class)->manifest();

    expect(collect($manifest->entries)->firstWhere('media_id', $media->getKey())['disposition'])->toBe(LegacyMediaTransitionDisposition::NormalizeExisting->value);
    expect(app(LegacyMediaTransitionExecutor::class)->apply([$media->getKey()], $manifest->digest(), $actor))->toBe(['normalize_existing_completed']);
    expect(app(LegacyMediaTransitionExecutor::class)->apply([$default->getKey()], app(LegacyMediaTransitionPlanner::class)->manifest()->digest(), $actor))->toBe(['normalize_existing_completed']);
    expect($media->fresh()->getKey())->toBe($media->getKey())
        ->and($media->fresh()->reference_key)->not->toBeNull()
        ->and($group->refresh()->cover_path)->toBe($media->fresh()->path)
        ->and(MediaAttachment::query()->where('media_id', $media->getKey())->where('attachable_type', 'content_group')->where('attachable_id', $group->getKey())->exists())->toBeTrue()
        ->and($default->fresh()->reference_key)->not->toBeNull()
        ->and(Storage::disk('local')->allFiles('media-quarantine'))->not->toBeEmpty();
});

it('keeps a compatible pre-existing attachment on the same row during O1', function (): void {
    $media = legacyTransitionMedia(legacyTransitionFixture('valid.jpg.base64'), null);
    $group = ContentGroup::factory()->create(['cover_path' => $media->path]);
    MediaAttachment::factory()->create([
        'media_id' => $media->getKey(), 'attachable_type' => 'content_group', 'attachable_id' => $group->getKey(), 'role' => 'cover',
    ]);
    $actor = User::factory()->admin()->create();
    $manifest = app(LegacyMediaTransitionPlanner::class)->manifest();

    expect(app(LegacyMediaTransitionExecutor::class)->apply([$media->getKey()], $manifest->digest(), $actor))->toBe(['normalize_existing_completed'])
        ->and(MediaAttachment::query()->where('media_id', $media->getKey())->where('attachable_id', $group->getKey())->count())->toBe(1);
});

it('sanitizes one safe SVG through the same-ID journaled transition without making it browseable first', function (): void {
    $name = (string) Str::ulid();
    $path = "header/{$name}.svg";
    $source = '<svg xmlns="http://www.w3.org/2000/svg"><rect width="1" height="1"/></svg>';
    Storage::disk('public')->put($path, $source, 'public');
    $media = Media::factory()->create([
        'reference_key' => null, 'disk' => 'public', 'directory' => 'header', 'name' => $name, 'path' => $path,
        'visibility' => 'public', 'type' => 'image/svg+xml', 'ext' => 'svg', 'size' => strlen($source), 'width' => null, 'height' => null,
    ]);
    DB::table('curator')->where('id', $media->getKey())->update(['reference_key' => null]);
    $manifest = app(LegacyMediaTransitionPlanner::class)->manifest();
    $entry = collect($manifest->entries)->firstWhere('media_id', $media->getKey());

    expect($entry['disposition'])->toBe('sanitize_svg')
        ->and($media->fresh()->reference_key)->toBeNull();
    expect(app(LegacyMediaTransitionExecutor::class)->apply([$media->getKey()], $manifest->digest(), User::factory()->admin()->create()))
        ->toBe(['sanitize_svg_completed']);
    expect($media->fresh()->getKey())->toBe($media->getKey())
        ->and($media->fresh()->reference_key)->not->toBeNull()
        ->and(hash('sha256', Storage::disk('public')->get($media->fresh()->path)))->toBe($entry['sanitized_sha256']);
});

it('imports one proof-bound rowless path only through the exact path selector', function (): void {
    $path = 'content-groups/covers/rowless-proof.jpg';
    Storage::disk('public')->put($path, legacyTransitionFixture('valid.jpg.base64'), 'public');
    ContentGroup::factory()->create(['cover_path' => $path]);
    $manifest = app(LegacyMediaTransitionPlanner::class)->manifest();

    expect(app(LegacyMediaTransitionExecutor::class)->applyPath($path, $manifest->digest(), User::factory()->admin()->create()))
        ->toStartWith('import_exact_path_completed:')
        ->and(Media::query()->where('path', $path)->exists())->toBeFalse();
});

it('fails closed for duplicate, malicious, missing, or spoofed existing SVG rows', function (): void {
    $duplicatePath = 'header/duplicate.svg';
    Storage::disk('public')->put($duplicatePath, '<svg xmlns="http://www.w3.org/2000/svg"/>', 'public');
    foreach (range(1, 2) as $number) {
        $row = Media::factory()->create(['reference_key' => null, 'disk' => 'public', 'directory' => 'header', 'name' => 'duplicate', 'path' => $duplicatePath, 'visibility' => 'public', 'type' => 'image/svg+xml', 'ext' => 'svg', 'size' => strlen('<svg xmlns="http://www.w3.org/2000/svg"/>'), 'width' => null, 'height' => null]);
        DB::table('curator')->where('id', $row->getKey())->update(['reference_key' => null]);
    }
    $maliciousPath = 'header/malicious.svg';
    $malicious = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>';
    Storage::disk('public')->put($maliciousPath, $malicious, 'public');
    $maliciousRow = Media::factory()->create(['reference_key' => null, 'disk' => 'public', 'directory' => 'header', 'name' => 'malicious', 'path' => $maliciousPath, 'visibility' => 'public', 'type' => 'image/svg+xml', 'ext' => 'svg', 'size' => strlen($malicious), 'width' => null, 'height' => null]);
    DB::table('curator')->where('id', $maliciousRow->getKey())->update(['reference_key' => null]);
    $missingRow = Media::factory()->create(['reference_key' => null, 'disk' => 'public', 'directory' => 'header', 'name' => 'missing', 'path' => 'header/missing.svg', 'visibility' => 'public', 'type' => 'image/svg+xml', 'ext' => 'svg', 'size' => 1, 'width' => null, 'height' => null]);
    $spoofedPath = 'header/spoofed.svg';
    Storage::disk('public')->put($spoofedPath, '<svg xmlns="http://www.w3.org/2000/svg"/>', 'public');
    $spoofedRow = Media::factory()->create(['reference_key' => null, 'disk' => 'public', 'directory' => 'header', 'name' => 'spoofed', 'path' => $spoofedPath, 'visibility' => 'public', 'type' => 'image/svg+xml', 'ext' => 'svg', 'size' => 99, 'width' => null, 'height' => null]);
    DB::table('curator')->whereIn('id', [$missingRow->getKey(), $spoofedRow->getKey()])->update(['reference_key' => null]);

    $entries = collect(app(LegacyMediaTransitionPlanner::class)->manifest()->entries);
    expect($entries->where('path', $duplicatePath)->pluck('reason')->unique()->all())->toBe(['duplicate_storage_identity'])
        ->and($entries->firstWhere('media_id', $maliciousRow->getKey())['reason'])->toBe('svg_source_invalid')
        ->and($entries->firstWhere('media_id', $missingRow->getKey())['reason'])->toBe('svg_source_missing_or_nonpublic')
        ->and($entries->firstWhere('media_id', $spoofedRow->getKey())['reason'])->toBe('svg_source_invalid');
});

it('plans and authorizes settings-only rowless raster and SVG candidates', function (): void {
    $rasterPath = 'default-images/settings-only.png';
    $svgPath = 'header/settings-only.svg';
    Storage::disk('public')->put($rasterPath, legacyTransitionFixture('valid.png.base64'), 'public');
    Storage::disk('public')->put($svgPath, '<svg xmlns="http://www.w3.org/2000/svg"><rect width="1" height="1"/></svg>', 'public');
    $defaults = PublicFrontConfigRegistry::defaults()['default_images'];
    $defaults['global'] = ['mode' => 'custom', 'path' => $rasterPath, 'media_reference_key' => null];
    $menu = PublicFrontConfigRegistry::defaults()['menu_config'];
    $menu['logo']['light_path'] = $svgPath;
    $menu['logo']['light_media_reference_key'] = null;
    DB::table('settings')->updateOrInsert(['group' => PublicContentSettings::group(), 'name' => 'default_images'], ['locked' => false, 'payload' => json_encode($defaults, JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now()]);
    DB::table('settings')->updateOrInsert(['group' => PublicContentSettings::group(), 'name' => 'menu_config'], ['locked' => false, 'payload' => json_encode($menu, JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now()]);
    $manifest = app(LegacyMediaTransitionPlanner::class)->manifest();
    $entries = collect($manifest->entries)->keyBy('path');

    expect($entries[$rasterPath]['disposition'])->toBe('import_exact_path')
        ->and($entries[$svgPath]['disposition'])->toBe('import_exact_path');
    expect(fn () => app(LegacyMediaTransitionExecutor::class)->applyPath($rasterPath, $manifest->digest(), User::factory()->moderator()->create()))
        ->toThrow(AuthorizationException::class);
    expect(app(LegacyMediaTransitionExecutor::class)->applyPath($rasterPath, $manifest->digest(), User::factory()->admin()->create()))->toStartWith('import_exact_path_completed:');
});

it('rejects a rowless source or digest changed after review', function (): void {
    $path = 'content-groups/covers/changed-rowless.png';
    Storage::disk('public')->put($path, legacyTransitionFixture('valid.png.base64'), 'public');
    ContentGroup::factory()->create(['cover_path' => $path]);
    $manifest = app(LegacyMediaTransitionPlanner::class)->manifest();
    Storage::disk('public')->put($path, legacyTransitionFixture('valid.jpg.base64'), 'public');

    expect(fn () => app(LegacyMediaTransitionExecutor::class)->applyPath($path, $manifest->digest(), User::factory()->admin()->create()))
        ->toThrow(RuntimeException::class, 'manifest changed');
});

it('keeps a production-shaped aggregate manifest deterministic without mutating media', function (): void {
    $bytes = legacyTransitionNormalizedJpeg();
    $validated = app(ImageUploadValidator::class)->validateBytes($bytes, 'fixture.jpg', ImageUploadPurpose::ContentGroupCover);
    foreach (range(1, 300) as $number) {
        $name = sprintf('fixture-%03d', $number);
        $path = "content-groups/covers/{$name}.jpg";
        Storage::disk('public')->put($path, $bytes, 'public');
        $media = Media::factory()->create(['reference_key' => null, 'disk' => 'public', 'directory' => 'content-groups/covers', 'name' => $name, 'path' => $path, 'visibility' => 'public', 'type' => 'image/jpeg', 'ext' => 'jpg', 'size' => strlen($bytes), 'width' => $validated->width, 'height' => $validated->height]);
        DB::table('curator')->where('id', $media->getKey())->update(['reference_key' => null]);
        ContentGroup::factory()->create(['cover_path' => $path]);
    }
    $rowless = 'content-groups/covers/fixture-rowless.jpg';
    Storage::disk('public')->put($rowless, legacyTransitionFixture('valid.jpg.base64'), 'public');
    ContentGroup::factory()->create(['cover_path' => $rowless]);
    $defaultName = 'fixture-default';
    $defaultPath = "default-images/{$defaultName}.jpg";
    Storage::disk('public')->put($defaultPath, $bytes, 'public');
    $default = Media::factory()->create(['reference_key' => null, 'disk' => 'public', 'directory' => 'default-images', 'name' => $defaultName, 'path' => $defaultPath, 'visibility' => 'public', 'type' => 'image/jpeg', 'ext' => 'jpg', 'size' => strlen($bytes), 'width' => $validated->width, 'height' => $validated->height]);
    DB::table('curator')->where('id', $default->getKey())->update(['reference_key' => null]);
    $defaults = PublicFrontConfigRegistry::defaults()['default_images'];
    $defaults['global'] = ['mode' => 'custom', 'path' => $defaultPath, 'media_reference_key' => null];
    DB::table('settings')->updateOrInsert(['group' => PublicContentSettings::group(), 'name' => 'default_images'], ['locked' => false, 'payload' => json_encode($defaults, JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now()]);
    $root = Media::factory()->create(['reference_key' => null, 'directory' => null, 'name' => 'root', 'path' => 'legacy-root.jpg']);
    DB::table('curator')->where('id', $root->getKey())->update(['reference_key' => null]);
    $svg = '<svg xmlns="http://www.w3.org/2000/svg"><rect width="1" height="1"/></svg>';
    $headerRows = collect(['fixture-header-light', 'fixture-header-dark'])->map(function (string $name) use ($svg): Media {
        $path = "header/{$name}.svg";
        Storage::disk('public')->put($path, $svg, 'public');
        $media = Media::factory()->create(['reference_key' => null, 'disk' => 'public', 'directory' => 'header', 'name' => $name, 'path' => $path, 'visibility' => 'public', 'type' => 'image/svg+xml', 'ext' => 'svg', 'size' => strlen($svg), 'width' => null, 'height' => null]);
        DB::table('curator')->where('id', $media->getKey())->update(['reference_key' => null]);

        return $media;
    });
    $menu = PublicFrontConfigRegistry::defaults()['menu_config'];
    $menu['logo']['light_path'] = $headerRows[0]->path;
    $menu['logo']['dark_path'] = $headerRows[1]->path;
    $menu['logo']['light_media_reference_key'] = null;
    $menu['logo']['dark_media_reference_key'] = null;
    DB::table('settings')->updateOrInsert(['group' => PublicContentSettings::group(), 'name' => 'menu_config'], ['locked' => false, 'payload' => json_encode($menu, JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now()]);
    $first = app(LegacyMediaTransitionPlanner::class)->manifest();
    $second = app(LegacyMediaTransitionPlanner::class)->manifest();

    expect($first->digest())->toBe($second->digest())
        ->and(collect($first->entries)->where('disposition', 'key_only')->count())->toBe(301)
        ->and(collect($first->entries)->firstWhere('path', $rowless)['disposition'])->toBe('import_exact_path')
        ->and(collect($first->entries)->firstWhere('media_id', $root->getKey())['disposition'])->toBe('blocked')
        ->and(collect($first->entries)->where('disposition', 'sanitize_svg')->count())->toBe(2)
        ->and(collect($first->entries)->firstWhere('media_id', $default->getKey())['active_references'])->not->toBeEmpty()
        ->and(collect($first->entries)->filter(fn (array $entry): bool => ($entry['active_references'] ?? []) !== [] && blank($entry['disposition'] ?? null)))->toBeEmpty()
        ->and(Media::query()->whereNull('reference_key')->count())->toBe(304);
});

it('refuses a conflicting attachment without changing the legacy row', function (): void {
    $media = legacyTransitionMedia(legacyTransitionFixture('valid.jpg.base64'), null);
    $other = legacyTransitionMedia(legacyTransitionNormalizedJpeg());
    $group = ContentGroup::factory()->create(['cover_path' => $media->path]);
    MediaAttachment::factory()->create([
        'media_id' => $other->getKey(), 'attachable_type' => 'content_group', 'attachable_id' => $group->getKey(), 'role' => 'cover',
    ]);
    $actor = User::factory()->admin()->create();
    $manifest = app(LegacyMediaTransitionPlanner::class)->manifest();

    expect(fn () => app(LegacyMediaTransitionExecutor::class)->apply([$media->getKey()], $manifest->digest(), $actor))
        ->toThrow(RuntimeException::class, 'conflicting attachment')
        ->and($media->fresh()->reference_key)->toBeNull()
        ->and($group->refresh()->cover_path)->toBe($media->path);
});

it('rejects an exact reviewed row while another media mutation lease is open', function (): void {
    $media = legacyTransitionMedia(legacyTransitionNormalizedJpeg(), null);
    $actor = User::factory()->admin()->create();
    $manifest = app(LegacyMediaTransitionPlanner::class)->manifest();
    MediaMutationOperation::factory()->create([
        'media_id' => $media->getKey(), 'media_id_snapshot' => $media->getKey(),
        'operation' => MediaMutationOperationType::Swap, 'status' => MediaMutationStatus::Copied,
        'lease_token' => (string) Str::ulid(), 'lease_expires_at' => now()->addMinutes(5),
    ]);

    expect(fn () => app(LegacyMediaTransitionExecutor::class)->apply([$media->getKey()], $manifest->digest(), $actor))
        ->toThrow(RuntimeException::class, 'manifest changed')
        ->and($media->fresh()->reference_key)->toBeNull();
});

it('rejects multi-ID apply so one transition cannot approve another row after state changed', function (): void {
    $first = legacyTransitionMedia(legacyTransitionNormalizedJpeg(), null);
    $second = legacyTransitionMedia(legacyTransitionNormalizedJpeg(), null);
    $actor = User::factory()->admin()->create();
    $manifest = app(LegacyMediaTransitionPlanner::class)->manifest();

    expect(fn () => app(LegacyMediaTransitionExecutor::class)->apply([$first->getKey(), $second->getKey()], $manifest->digest(), $actor))
        ->toThrow(RuntimeException::class, 'Exactly one reviewed media ID')
        ->and($first->fresh()->reference_key)->toBeNull()
        ->and($second->fresh()->reference_key)->toBeNull();
});

it('fails closed when the reviewed source disappears before quarantine', function (): void {
    $media = legacyTransitionMedia(legacyTransitionFixture('valid.jpg.base64'), null);
    $actor = User::factory()->admin()->create();
    $manifest = app(LegacyMediaTransitionPlanner::class)->manifest();
    Storage::disk('public')->delete($media->path);

    expect(fn () => app(LegacyMediaTransitionExecutor::class)->apply([$media->getKey()], $manifest->digest(), $actor))
        ->toThrow(RuntimeException::class)
        ->and($media->fresh()->reference_key)->toBeNull()
        ->and($media->fresh()->path)->toBe($media->path)
        ->and(MediaMutationOperation::query()->where('media_id', $media->getKey())->where('operation', MediaMutationOperationType::LegacyTransition)->count())->toBe(0);
});

it('rejects a stale reviewed owner fingerprint without switching the row', function (): void {
    $media = legacyTransitionMedia(legacyTransitionFixture('valid.jpg.base64'), null);
    $group = ContentGroup::factory()->create(['cover_path' => $media->path]);
    $entry = collect(app(LegacyMediaTransitionPlanner::class)->manifest()->entries)->firstWhere('media_id', $media->getKey());
    DB::table('content_groups')->where('id', $group->getKey())->update(['cover_path' => null]);

    expect(fn () => app(LegacyMediaTransitionPlanner::class)->assertEntryCurrent($entry))
        ->toThrow(RuntimeException::class, 'entry changed')
        ->and($media->fresh()->reference_key)->toBeNull();
});

it('compensates a LegacyTransition destination copy failure after quarantine and staging', function (): void {
    $media = legacyTransitionMedia(legacyTransitionFixture('valid.jpg.base64'), null);
    $actor = User::factory()->admin()->create();
    $manifest = app(LegacyMediaTransitionPlanner::class)->manifest();
    $public = Storage::disk('public');
    $failingPublic = Mockery::mock($public)->makePartial();
    $failingPublic->shouldReceive('put')->once()->andReturnFalse();
    Storage::set('public', $failingPublic);

    expect(fn () => app(LegacyMediaTransitionExecutor::class)->apply([$media->getKey()], $manifest->digest(), $actor))
        ->toThrow(RuntimeException::class, 'could not be copied');

    $operation = MediaMutationOperation::query()->where('operation', MediaMutationOperationType::LegacyTransition)->sole();
    expect($operation->status)->toBe(MediaMutationStatus::Failed)
        ->and($media->fresh()->reference_key)->toBeNull()
        ->and($media->fresh()->path)->toBe($media->path)
        ->and($public->get($media->path))->toBe(legacyTransitionFixture('valid.jpg.base64'))
        ->and(Storage::disk('local')->allFiles('media-staging'))->toBe([])
        ->and(Storage::disk('local')->allFiles('media-quarantine'))->toBe([]);
});

it('keeps the same-ID LegacyTransition committed when source cleanup is pending and repair resumes', function (): void {
    $media = legacyTransitionMedia(legacyTransitionFixture('valid.jpg.base64'), null);
    $sourcePath = $media->path;
    $actor = User::factory()->admin()->create();
    $manifest = app(LegacyMediaTransitionPlanner::class)->manifest();
    $public = Storage::disk('public');
    $failingPublic = Mockery::mock($public)->makePartial();
    $failingPublic->shouldReceive('delete')->once()->andReturnFalse();
    Storage::set('public', $failingPublic);

    expect(app(LegacyMediaTransitionExecutor::class)->apply([$media->getKey()], $manifest->digest(), $actor))->toBe(['cleanup_pending']);
    $operation = MediaMutationOperation::query()->where('operation', MediaMutationOperationType::LegacyTransition)->sole();
    $newPath = $media->fresh()->path;
    expect($operation->status)->toBe(MediaMutationStatus::CleanupPending)
        ->and($media->fresh()->getKey())->toBe($media->getKey())
        ->and($media->fresh()->reference_key)->not->toBeNull()
        ->and($public->exists($sourcePath))->toBeTrue();

    Storage::set('public', $public);
    $operation->forceFill(['lease_token' => null, 'lease_expires_at' => now()->subMinute()])->save();
    expect(app(MediaFilesystemMutationCoordinator::class)->repair($operation))->toBe('completed_cleanup')
        ->and($operation->refresh()->status)->toBe(MediaMutationStatus::Completed)
        ->and(Storage::disk('public')->exists($newPath))->toBeTrue()
        ->and(Storage::disk('local')->exists((string) $operation->quarantine_path))->toBeTrue();
});

it('fingerprints exact settings payload state and attachment tuples', function (): void {
    $media = legacyTransitionMedia(legacyTransitionNormalizedJpeg(), null, ImageUploadPurpose::DefaultImage);
    $defaults = PublicFrontConfigRegistry::defaults()['default_images'];
    $defaults['global']['mode'] = 'custom';
    $defaults['global']['path'] = $media->path;
    DB::table('settings')->updateOrInsert(['group' => PublicContentSettings::group(), 'name' => 'default_images'], ['locked' => false, 'payload' => json_encode($defaults, JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now()]);
    $group = ContentGroup::factory()->create();
    $attachment = MediaAttachment::factory()->create(['media_id' => $media->getKey(), 'attachable_type' => 'content_group', 'attachable_id' => $group->getKey(), 'role' => 'cover', 'position' => 0]);
    $entry = collect(app(LegacyMediaTransitionPlanner::class)->manifest()->entries)->firstWhere('media_id', $media->getKey());

    expect($entry['review_state']['settings'][0])->toHaveKeys(['name', 'payload_sha256', 'locked', 'locations'])
        ->and($entry['review_state']['attachments'][0])->toMatchArray(['media_id' => $media->getKey(), 'attachable_type' => 'content_group', 'attachable_id' => $group->getKey(), 'role' => 'cover', 'position' => 0]);
    DB::table('settings')->where('group', PublicContentSettings::group())->where('name', 'default_images')->update(['locked' => true]);
    DB::table('media_attachments')->where('id', $attachment->getKey())->update(['position' => 1]);

    expect(fn () => app(LegacyMediaTransitionPlanner::class)->assertEntryCurrent($entry))->toThrow(RuntimeException::class, 'entry changed');
});
