<?php

use App\Enums\MediaDiagnosticReason;
use App\Enums\MediaLibraryTask;
use App\Filament\Resources\Media\MediaResource;
use App\Models\ContentGroup;
use App\Models\Media;
use App\Models\MediaAttachment;
use App\Settings\PublicContentSettings;
use App\Support\Media\MediaInventoryDiagnostics;
use App\Support\Media\MediaLibraryContext;
use App\Support\Media\MediaLibraryTaskQuery;
use App\Support\Media\MediaReferenceFinder;
use Filament\Facades\Filament;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Http::preventStrayRequests();
    Storage::fake('local');
    Storage::fake('public');
});

function mediaUx3M2Fixture(
    string $identity,
    array $overrides = [],
    string|false $contents = 'valid raster fixture',
): Media {
    $disk = (string) ($overrides['disk'] ?? 'public');
    $directory = (string) ($overrides['directory'] ?? 'content-groups/covers');
    $extension = (string) ($overrides['ext'] ?? 'jpg');
    $path = (string) ($overrides['path'] ?? "{$directory}/{$identity}.{$extension}");
    $media = Media::factory()->create(array_merge([
        'reference_key' => (string) Str::ulid(),
        'disk' => $disk,
        'directory' => $directory,
        'visibility' => 'public',
        'name' => $identity,
        'path' => $path,
        'width' => 100,
        'height' => 100,
        'size' => is_string($contents) ? max(strlen($contents), 1) : 1024,
        'type' => 'image/jpeg',
        'ext' => $extension,
        'title' => Str::headline($identity),
    ], $overrides));
    assert($media instanceof Media);

    if (
        is_string($contents)
        && array_key_exists($disk, config('filesystems.disks', []))
    ) {
        Storage::disk($disk)->put($media->path, $contents);
    }

    return $media;
}

function mediaUx3M2SaveSetting(string $name, array $payload): void
{
    DB::table('settings')->updateOrInsert(
        ['group' => PublicContentSettings::group(), 'name' => $name],
        [
            'locked' => false,
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ],
    );
}

/** @return array<int, int> */
function mediaUx3M2TaskIds(MediaLibraryTask $task): array
{
    return app(MediaLibraryTaskQuery::class)
        ->apply(MediaResource::getEloquentQuery(), $task)
        ->orderBy('id')
        ->pluck('id')
        ->map(fn (mixed $id): int => (int) $id)
        ->all();
}

it('defines the canonical Media Library tasks and diagnostic reasons in stable order', function (): void {
    expect(array_column(MediaLibraryTask::cases(), 'value'))->toBe([
        'all',
        'in_use',
        'no_direct_attachment',
        'needs_attention',
        'recent',
    ])->and(array_column(MediaDiagnosticReason::cases(), 'value'))->toBe([
        'portable_identity',
        'storage_disk',
        'missing_file',
        'audience_denied',
        'unsanitized_svg',
        'metadata',
    ]);

    foreach (['he', 'en'] as $locale) {
        app()->setLocale($locale);

        foreach (MediaLibraryTask::cases() as $task) {
            expect($task->getLabel())->not->toBe("admin.media_library.tasks.{$task->value}")
                ->and($task->description())->not->toBe(
                    "admin.media_library.task_descriptions.{$task->value}",
                );
        }

        foreach (MediaDiagnosticReason::cases() as $reason) {
            expect($reason->getLabel())->not->toBe(
                "admin.media_library.repair_{$reason->value}",
            );
        }
    }
});

it('builds In Use from canonical attachments and settings identities only', function (): void {
    $attachedPublic = mediaUx3M2Fixture('attached-public');
    $attachedPrivate = mediaUx3M2Fixture('attached-private', [
        'disk' => 'local',
        'visibility' => 'private',
    ]);
    $owner = ContentGroup::factory()->create();
    MediaAttachment::factory()->create([
        'media_id' => $attachedPublic->getKey(),
        'attachable_id' => $owner->getKey(),
    ]);
    MediaAttachment::factory()->create([
        'media_id' => $attachedPrivate->getKey(),
    ]);

    $legacyPath = 'content-groups/covers/shared-legacy.jpg';
    $legacyPublic = mediaUx3M2Fixture('legacy-public', ['path' => $legacyPath]);
    $legacyDuplicate = mediaUx3M2Fixture('legacy-duplicate', ['path' => $legacyPath]);
    $legacyPrivate = mediaUx3M2Fixture('legacy-private', [
        'disk' => 'local',
        'path' => $legacyPath,
        'visibility' => 'private',
    ]);

    $itemLegacy = mediaUx3M2Fixture('item-legacy', [
        'directory' => 'content-items/images',
        'path' => 'content-items/images/item-legacy.jpg',
    ]);

    $settingsPath = mediaUx3M2Fixture('settings-path', [
        'directory' => 'default-images',
        'path' => 'default-images/settings-path.jpg',
    ]);
    $settingsPrivateKey = mediaUx3M2Fixture('settings-private-key', [
        'disk' => 'local',
        'visibility' => 'private',
        'path' => 'default-images/private-key.jpg',
    ]);
    $menuPath = mediaUx3M2Fixture('settings-menu', [
        'directory' => 'header',
        'path' => 'header/settings-menu.svg',
        'type' => 'image/svg+xml',
        'ext' => 'svg',
    ]);
    $aboutKey = mediaUx3M2Fixture('settings-about-key', [
        'directory' => 'about',
        'path' => 'about/settings-about-key.jpg',
    ]);
    $teamPath = mediaUx3M2Fixture('settings-team-path', [
        'directory' => 'team',
        'path' => 'team/settings-team-path.jpg',
    ]);
    $unreferenced = mediaUx3M2Fixture('unreferenced');

    mediaUx3M2SaveSetting('menu_config', [
        'logo' => [
            'light_path' => $menuPath->path,
            'light_media_reference_key' => null,
        ],
    ]);
    mediaUx3M2SaveSetting('about_page', [
        'blocks' => [[
            'image_path' => 'about/different-path.jpg',
            'image_media_reference_key' => mb_strtolower((string) $aboutKey->reference_key),
        ]],
        'team_profiles' => [[
            'name' => 'Team member',
            'image_path' => $teamPath->path,
            'image_media_reference_key' => null,
        ]],
    ]);
    mediaUx3M2SaveSetting('default_images', [
        'global' => [
            'mode' => 'custom',
            'path' => $settingsPath->path,
            'media_reference_key' => null,
        ],
        'content_group' => [
            'mode' => 'custom',
            'path' => 'default-images/different-path.jpg',
            'media_reference_key' => mb_strtolower((string) $settingsPrivateKey->reference_key),
        ],
    ]);

    expect(mediaUx3M2TaskIds(MediaLibraryTask::InUse))->toBe(collect([
        $attachedPublic,
        $attachedPrivate,
        $settingsPath,
        $settingsPrivateKey,
        $menuPath,
        $aboutKey,
        $teamPath,
    ])->pluck('id')->sort()->values()->all())
        ->and(mediaUx3M2TaskIds(MediaLibraryTask::NoDirectAttachment))->toBe(collect([
            $legacyPublic,
            $legacyDuplicate,
            $legacyPrivate,
            $itemLegacy,
            $settingsPath,
            $settingsPrivateKey,
            $menuPath,
            $aboutKey,
            $teamPath,
            $unreferenced,
        ])->pluck('id')->sort()->values()->all());
});

it('memoizes only the two approved task badge counts', function (): void {
    $attached = mediaUx3M2Fixture('count-attached');
    $unattached = mediaUx3M2Fixture('count-unattached');
    MediaAttachment::factory()->create(['media_id' => $attached->getKey()]);
    $queries = [];

    DB::listen(function ($query) use (&$queries): void {
        if (str_contains(mb_strtolower($query->sql), 'count')) {
            $queries[] = $query->sql;
        }
    });

    $taskQuery = app(MediaLibraryTaskQuery::class);

    expect($taskQuery->counts())->toBe([
        'all' => 2,
        'no_direct_attachment' => 1,
    ])->and($taskQuery->counts())->toBe([
        'all' => 2,
        'no_direct_attachment' => 1,
    ])->and($queries)->toHaveCount(2)
        ->and($unattached->exists)->toBeTrue();
});

it('defines Recent as one inclusive rolling 30 day window ending at request time', function (): void {
    $now = Carbon::parse('2026-07-24 09:00:00', config('app.timezone'));
    $this->travelTo($now);
    $cutoff = $now->copy()->subDays(30);
    $atCutoff = mediaUx3M2Fixture('at-cutoff', ['created_at' => $cutoff]);
    mediaUx3M2Fixture('before-cutoff', [
        'created_at' => $cutoff->copy()->subSecond(),
    ]);
    $atNow = mediaUx3M2Fixture('at-now', ['created_at' => $now]);
    mediaUx3M2Fixture('after-now', ['created_at' => $now->copy()->addSecond()]);

    expect(mediaUx3M2TaskIds(MediaLibraryTask::Recent))->toBe([
        (int) $atCutoff->getKey(),
        (int) $atNow->getKey(),
    ]);
});

it('uses the exact six-reason diagnostic union including unsafe SVG bytes', function (): void {
    $portable = mediaUx3M2Fixture('portable');
    DB::table($portable->getTable())
        ->where($portable->getKeyName(), $portable->getKey())
        ->update(['reference_key' => null]);

    $storageDisk = mediaUx3M2Fixture('storage-disk', [
        'disk' => 'missing-disk',
    ], false);
    $missingFile = mediaUx3M2Fixture('missing-file', [], false);
    $audienceDenied = mediaUx3M2Fixture('audience-denied', [
        'disk' => 'local',
        'visibility' => 'private',
    ]);
    $maliciousSvg = (string) file_get_contents(base_path('tests/Fixtures/media/malicious.svg'));
    $unsafeSvg = mediaUx3M2Fixture('unsafe-svg', [
        'directory' => 'header',
        'path' => 'header/unsafe-svg.svg',
        'width' => null,
        'height' => null,
        'size' => strlen($maliciousSvg),
        'type' => 'image/svg+xml',
        'ext' => 'svg',
    ], $maliciousSvg);
    $metadata = mediaUx3M2Fixture('metadata', ['width' => 0]);
    $healthy = mediaUx3M2Fixture('healthy');

    $diagnostics = app(MediaInventoryDiagnostics::class);
    $observedReasons = collect([
        $portable->refresh(),
        $storageDisk,
        $missingFile,
        $audienceDenied,
        $unsafeSvg,
        $metadata,
    ])->flatMap(fn (Media $media): array => $diagnostics->reasons($media))
        ->unique()
        ->sort()
        ->values()
        ->all();

    expect($observedReasons)->toBe(collect(MediaDiagnosticReason::cases())
        ->map(fn (MediaDiagnosticReason $reason): string => $reason->value)
        ->sort()
        ->values()
        ->all())
        ->and(mediaUx3M2TaskIds(MediaLibraryTask::NeedsAttention))->toBe(collect([
            $portable,
            $storageDisk,
            $missingFile,
            $audienceDenied,
            $unsafeSvg,
            $metadata,
        ])->pluck('id')->sort()->values()->all());

    $unsafeIds = app(MediaLibraryTaskQuery::class)
        ->applyReason(
            MediaResource::getEloquentQuery(),
            MediaDiagnosticReason::UnsanitizedSvg->value,
        )
        ->pluck('id')
        ->map(fn (mixed $id): int => (int) $id)
        ->all();

    expect($unsafeIds)->toBe([(int) $unsafeSvg->getKey()])
        ->and($unsafeIds)->not->toContain((int) $healthy->getKey());
});

it('invalidates the exact diagnostic snapshot when one Media decision is forgotten', function (): void {
    $media = mediaUx3M2Fixture('snapshot-invalidation');
    $diagnostics = app(MediaInventoryDiagnostics::class);

    expect($diagnostics
        ->applyReasonFilter(MediaResource::getEloquentQuery(), MediaDiagnosticReason::MissingFile)
        ->pluck('id')
        ->all())->toBe([]);

    Storage::disk('public')->delete($media->path);
    $diagnostics->forget($media);

    expect($diagnostics
        ->applyReasonFilter(MediaResource::getEloquentQuery(), MediaDiagnosticReason::MissingFile)
        ->pluck('id')
        ->all())->toBe([$media->getKey()]);
});

it('leaves the query unchanged for a blank reason without scanning storage', function (
    MediaDiagnosticReason|string|null $reason,
): void {
    $media = mediaUx3M2Fixture('blank-reason', [], false);

    Storage::shouldReceive('disk')->never();
    app()->forgetInstance(MediaInventoryDiagnostics::class);
    app()->forgetInstance(MediaLibraryTaskQuery::class);

    expect(app(MediaLibraryTaskQuery::class)
        ->applyReason(MediaResource::getEloquentQuery(), $reason)
        ->pluck('id')
        ->all())->toBe([$media->getKey()]);
})->with([
    'null' => [null],
    'blank string' => [''],
]);

it('round trips only the allowlisted Media Library context with server-derived focus', function (): void {
    $context = app(MediaLibraryContext::class);
    $state = $context->capture(
        task: MediaLibraryTask::NeedsAttention->value,
        filters: [
            'type' => ['value' => 'image/jpeg'],
            'reason' => ['value' => MediaDiagnosticReason::MissingFile->value],
            'unknown' => ['value' => 'discard-me'],
        ],
        search: '  cover image  ',
        sort: 'created_at:asc',
        page: 2,
        focus: 42,
    );
    $editParameters = $context->editParameters($state);
    $parsed = $context->fromInput([
        ...$editParameters['from'],
        'focus' => 999,
    ], actualFocus: 42);

    expect($editParameters)->toBe([
        'from' => [
            'v' => 1,
            'task' => 'needs_attention',
            'mime' => 'image/jpeg',
            'reason' => 'missing_file',
            'search' => 'cover image',
            'sort' => 'created_at:asc',
            'page' => 2,
            'focus' => 42,
        ],
    ])->and($context->indexParameters($parsed))->toBe([
        'tab' => 'needs_attention',
        'filters' => [
            'type' => ['value' => 'image/jpeg'],
            'reason' => ['value' => 'missing_file'],
        ],
        'search' => 'cover image',
        'sort' => 'created_at:asc',
        'page' => 2,
        'focus' => 42,
    ])->and($context->fragment($parsed))->toBe('#media-record-42');
});

it('preserves the strict return-context shape through an actual query-string round trip', function (): void {
    $context = app(MediaLibraryContext::class);
    $state = $context->capture(
        task: MediaLibraryTask::All->value,
        filters: [],
        search: null,
        sort: null,
        page: 1,
        focus: 42,
    );
    $queryString = http_build_query($context->editParameters($state));
    parse_str($queryString, $query);
    $parsed = $context->fromInput($query['from'] ?? null, actualFocus: 42);

    expect($query['from'] ?? null)->toBe([
        'v' => '1',
        'task' => 'all',
        'mime' => '',
        'reason' => '',
        'search' => '',
        'sort' => '',
        'page' => '1',
        'focus' => '42',
    ])->and($context->indexParameters($parsed))->toBe([
        'tab' => 'all',
        'page' => 1,
        'focus' => 42,
    ]);
});

it('rejects malformed UTF-8 search bytes from list and return context', function (): void {
    $context = app(MediaLibraryContext::class);
    $state = $context->capture(
        task: MediaLibraryTask::All->value,
        filters: [],
        search: "\xFF",
        sort: null,
        page: 1,
        focus: 42,
    );
    $parsed = $context->fromInput([
        'v' => '1',
        'task' => 'all',
        'mime' => '',
        'reason' => '',
        'search' => "\xFF",
        'sort' => '',
        'page' => '1',
        'focus' => '42',
    ], actualFocus: 42);

    expect($state['search'])->toBeNull()
        ->and($context->indexParameters($parsed))->toBe([
            'tab' => 'all',
            'page' => 1,
            'focus' => 42,
        ]);
});

it('chunks one exact diagnostic snapshot and reuses its filesystem decisions at 251 rows', function (): void {
    foreach (range(1, 251) as $index) {
        mediaUx3M2Fixture("chunked-missing-{$index}", [], false);
    }

    $disk = Mockery::mock(FilesystemAdapter::class);
    $disk->shouldReceive('exists')
        ->times(251)
        ->andReturnFalse();
    Storage::shouldReceive('disk')
        ->times(251)
        ->with('public')
        ->andReturn($disk);
    app()->forgetInstance(MediaInventoryDiagnostics::class);
    app()->forgetInstance(MediaLibraryTaskQuery::class);
    $curatorSelects = [];

    DB::listen(function ($query) use (&$curatorSelects): void {
        $sql = mb_strtolower($query->sql);

        if (str_starts_with(ltrim($sql), 'select') && str_contains($sql, 'from `curator`')) {
            $curatorSelects[] = $query->sql;
        }
    });

    $ids = app(MediaLibraryTaskQuery::class)
        ->applyReason(
            MediaResource::getEloquentQuery(),
            MediaDiagnosticReason::MissingFile,
        )
        ->pluck('id');

    expect($ids)->toHaveCount(251)
        ->and($curatorSelects)->toHaveCount(3)
        ->and(21 * $ids->count())->toBe(5271);
});

it('keeps the normal In Use predicate database-only and constant as inventory scales', function (
    int $recordCount,
): void {
    foreach (range(1, $recordCount) as $index) {
        mediaUx3M2Fixture("in-use-budget-{$index}", [], false);
    }

    Storage::shouldReceive('disk')->never();
    app()->forgetInstance(MediaReferenceFinder::class);
    app()->forgetInstance(MediaLibraryTaskQuery::class);
    $queries = [];

    DB::listen(function ($query) use (&$queries): void {
        $sql = mb_strtolower($query->sql);

        if (
            str_starts_with(ltrim($sql), 'select')
            && (
                str_contains($sql, 'settings')
                || str_contains($sql, 'curator')
            )
        ) {
            $queries[] = $query->sql;
        }
    });

    $ids = app(MediaLibraryTaskQuery::class)
        ->apply(MediaResource::getEloquentQuery(), MediaLibraryTask::InUse)
        ->pluck('id');

    expect($ids)->toBeEmpty()
        ->and($queries)->toHaveCount(3);
})->with([1, 10, 25, 251]);

it('falls back to All Media for malformed or unsafe return context', function (mixed $input): void {
    $context = app(MediaLibraryContext::class);
    $parsed = $context->fromInput($input, actualFocus: 7);

    expect($context->indexParameters($parsed))->toBe([
        'tab' => 'all',
        'page' => 1,
        'focus' => 7,
    ])->and($context->fragment($parsed))->toBe('#media-record-7');
})->with([
    'missing context' => null,
    'non-array context' => 'https://example.com',
    'wrong version' => [[
        'v' => 2,
        'task' => 'all',
        'mime' => null,
        'reason' => null,
        'search' => null,
        'sort' => null,
        'page' => 1,
        'focus' => 7,
    ]],
    'unknown task' => [[
        'v' => 1,
        'task' => 'trash',
        'mime' => null,
        'reason' => null,
        'search' => null,
        'sort' => null,
        'page' => 1,
        'focus' => 7,
    ]],
    'unknown MIME' => [[
        'v' => 1,
        'task' => 'all',
        'mime' => 'text/html',
        'reason' => null,
        'search' => null,
        'sort' => null,
        'page' => 1,
        'focus' => 7,
    ]],
    'unknown reason' => [[
        'v' => 1,
        'task' => 'all',
        'mime' => null,
        'reason' => 'fix_everything',
        'search' => null,
        'sort' => null,
        'page' => 1,
        'focus' => 7,
    ]],
    'unsafe sort' => [[
        'v' => 1,
        'task' => 'all',
        'mime' => null,
        'reason' => null,
        'search' => null,
        'sort' => 'title:desc',
        'page' => 1,
        'focus' => 7,
    ]],
    'overlong search' => [[
        'v' => 1,
        'task' => 'all',
        'mime' => null,
        'reason' => null,
        'search' => str_repeat('a', 201),
        'sort' => null,
        'page' => 1,
        'focus' => 7,
    ]],
    'control characters' => [[
        'v' => 1,
        'task' => 'all',
        'mime' => null,
        'reason' => null,
        'search' => "cover\r\nLocation: https://example.com",
        'sort' => null,
        'page' => 1,
        'focus' => 7,
    ]],
    'page zero' => [[
        'v' => 1,
        'task' => 'all',
        'mime' => null,
        'reason' => null,
        'search' => null,
        'sort' => null,
        'page' => 0,
        'focus' => 7,
    ]],
    'page overflow' => [[
        'v' => 1,
        'task' => 'all',
        'mime' => null,
        'reason' => null,
        'search' => null,
        'sort' => null,
        'page' => 10_001,
        'focus' => 7,
    ]],
    'unknown return key' => [[
        'v' => 1,
        'task' => 'all',
        'mime' => null,
        'reason' => null,
        'search' => null,
        'sort' => null,
        'page' => 1,
        'focus' => 7,
        'return_url' => 'javascript:alert(1)',
    ]],
]);
