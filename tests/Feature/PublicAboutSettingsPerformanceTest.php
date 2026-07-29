<?php

use App\Enums\ImageUploadPurpose;
use App\Filament\Forms\Components\PathCuratorPicker;
use App\Filament\Pages\AboutSettings;
use App\Filament\Pages\SettingsSubjectOwnershipRegistry;
use App\Models\Media;
use App\Models\User;
use App\Policies\CuratorMediaPolicy;
use App\Settings\PublicContentSettings;
use App\Support\Media\OwnerImageChoicePresentation;
use App\Support\Media\SettingsOwnerImagePresenter;
use App\Support\PublicFront\PublicFrontConfigReader;
use App\Support\PublicFront\PublicFrontConfigRegistry;
use App\Support\PublicFront\PublicFrontRenderContext;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\LaravelSettings\SettingsContainer;

uses(RefreshDatabase::class);

final class Task7DenyMediaSelectPolicy extends CuratorMediaPolicy
{
    public function select(User $user, Media $media): bool
    {
        return false;
    }
}

final class Task7DenyMediaViewPolicy extends CuratorMediaPolicy
{
    public function view(User $user, Media $media): bool
    {
        return false;
    }
}

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Http::preventStrayRequests();
    Storage::fake('public');
});

function task7AboutMedia(string $name): Media
{
    $path = ImageUploadPurpose::AboutImage->root()."/{$name}.png";
    Storage::disk('public')->put($path, 'about-image');

    return Media::factory()->create([
        'reference_key' => (string) Str::ulid(),
        'disk' => 'public',
        'directory' => ImageUploadPurpose::AboutImage->root(),
        'visibility' => 'public',
        'name' => $name,
        'path' => $path,
        'width' => 640,
        'height' => 360,
        'size' => 2048,
        'type' => 'image/png',
        'ext' => 'png',
    ]);
}

function task7OwnerChoicePresentation(Media $media): OwnerImageChoicePresentation
{
    $slotKey = 'about_page.blocks.task7_image.image_media_reference_key';
    $settings = [
        'about_page' => [
            'blocks' => [[
                'key' => 'task7_image',
                'type' => 'image',
                'image_media_reference_key' => $media->reference_key,
                'image_path' => $media->path,
            ]],
            'team_profiles' => [],
        ],
    ];
    $presenter = app(SettingsOwnerImagePresenter::class);
    $snapshot = $presenter->snapshot($settings, SettingsSubjectOwnershipRegistry::ABOUT);
    $evidence = $presenter->openedEvidence($snapshot);
    $presenter->primeOpenedEvidence($evidence);

    return $presenter->choice(
        $snapshot,
        $slotKey,
        (int) $media->getKey(),
        ['commit' => 'Save', 'cancel' => 'Cancel', 'admission' => 'Permanent'],
        $evidence[$slotKey],
    );
}

/**
 * @return array{result: mixed, queries: array<int, string>}
 */
function task7CaptureMediaQueries(Closure $callback): array
{
    $recording = true;
    $queries = [];

    DB::listen(function (QueryExecuted $query) use (&$queries, &$recording): void {
        if (
            $recording
            && preg_match('/\bfrom ["`]?curator["`]?\b/i', $query->sql) === 1
        ) {
            $queries[] = mb_strtolower((string) preg_replace('/\s+/', ' ', trim($query->sql)));
        }
    });

    try {
        $result = $callback();
    } finally {
        $recording = false;
    }

    return ['result' => $result ?? null, 'queries' => $queries];
}

function task7MediaPointShapeSignature(string $sql): ?string
{
    if (preg_match('/\blower\s*\(\s*["`]?reference_key["`]?\s*\)\s*=\s*\?/', $sql) === 1) {
        return $sql;
    }

    if (preg_match('/["`]?id["`]?\s*=\s*\?/', $sql) === 1) {
        return $sql;
    }

    if (preg_match('/["`]?id["`]?\s+in\s*\(\s*\d+\s*\)/', $sql) === 1) {
        return preg_replace(
            '/(["`]?id["`]?\s+in\s*\(\s*)\d+(\s*\))/',
            '$1?$2',
            $sql,
        );
    }

    return preg_match('/["`]?id["`]?\s+in\s*\(\s*\?\s*\)/', $sql) === 1
        ? $sql
        : null;
}

/**
 * @param  Collection<int, array{sql: string, bindings: array<int, mixed>}>  $mediaSelects
 * @return Collection<int, array{
 *     signature: string,
 *     count: int,
 *     sql: array<int, string>,
 *     bindings: array<int, array<int, mixed>>
 * }>
 */
function task7RepeatedMediaPointShapes(Collection $mediaSelects): Collection
{
    return $mediaSelects
        ->map(function (array $query): array {
            return [
                ...$query,
                'signature' => task7MediaPointShapeSignature($query['sql']),
            ];
        })
        ->filter(fn (array $query): bool => is_string($query['signature']))
        ->groupBy('signature')
        ->filter(fn (Collection $shapeQueries): bool => $shapeQueries->count() > 1)
        ->map(fn (Collection $shapeQueries, string $signature): array => [
            'signature' => $signature,
            'count' => $shapeQueries->count(),
            'sql' => $shapeQueries->pluck('sql')->all(),
            'bindings' => $shapeQueries->pluck('bindings')->all(),
        ])
        ->values();
}

it('groups distinct SQLite literal single-media ID queries as one repeated point shape', function (): void {
    $literalQueries = collect([
        [
            'sql' => 'select * from "curator" where "curator"."id" in (17)',
            'bindings' => [],
        ],
        [
            'sql' => 'select * from "curator" where "curator"."id" in (18)',
            'bindings' => [],
        ],
    ]);

    $repeatedShapes = task7RepeatedMediaPointShapes($literalQueries);

    expect($repeatedShapes)->toHaveCount(1)
        ->and($repeatedShapes->first())->toBe([
            'signature' => 'select * from "curator" where "curator"."id" in (?)',
            'count' => 2,
            'sql' => [
                'select * from "curator" where "curator"."id" in (17)',
                'select * from "curator" where "curator"."id" in (18)',
            ],
            'bindings' => [[], []],
        ]);
});

it('keeps default-false owner presentations on the initial hydration lookup and view-Gate path', function (): void {
    $admin = User::factory()->admin()->create();
    $host = Livewire::actingAs($admin)->test(AboutSettings::class);
    $saved = task7AboutMedia('task7-default-false-hydration');
    $presentation = new OwnerImageChoicePresentation(
        ownerKind: 'about_page.blocks.task7_default_false.image_media_reference_key',
        ownerKindLabel: 'About',
        ownerLabel: 'Default-false image',
        slotKind: 'about_block',
        slotLabel: 'Image',
        isProspective: false,
        directState: 'present',
        directMedia: [
            'id' => (int) $saved->getKey(),
            'reference_key' => (string) $saved->reference_key,
            'label' => 'Saved image',
            'preview_url' => null,
            'details_url' => null,
        ],
        shownNowSource: 'configured_default',
        shownNowMedia: null,
        shownNowPreviewUrl: null,
        savedMediaId: (int) $saved->getKey(),
        savedReferenceKey: (string) $saved->reference_key,
        pendingKind: 'unchanged',
        pendingMedia: null,
        canClearPending: false,
        canChooseAutomatic: false,
        expectedMediaId: (int) $saved->getKey(),
        expectedLegacyPath: (string) $saved->path,
        commitBoundary: ['commit' => 'Save', 'cancel' => 'Cancel', 'admission' => 'Permanent'],
        warningCodes: [],
    );
    $picker = PathCuratorPicker::make('image_media_reference_key')
        ->purpose(ImageUploadPurpose::AboutImage)
        ->referenceKeyIdentity()
        ->ownerChoice(fn (): OwnerImageChoicePresentation => $presentation)
        ->container($host->instance()->getSchema('form'))
        ->state((int) $saved->getKey());
    $exception = null;

    Gate::policy(Media::class, Task7DenyMediaViewPolicy::class);

    try {
        $captured = task7CaptureMediaQueries(function () use (&$exception, $picker): void {
            try {
                $picker->callAfterStateHydrated();
            } catch (AuthorizationException $caught) {
                $exception = $caught;
            }
        });
    } finally {
        Gate::policy(Media::class, CuratorMediaPolicy::class);
    }

    expect($presentation->savedMediaCanSelect)->toBeFalse()
        ->and($exception)->toBeInstanceOf(AuthorizationException::class)
        ->and($captured['queries'])->toHaveCount(1)
        ->and($captured['queries'][0])->toContain('"curator"."id" in (');
});

it('hydrates an authorized Settings exact saved identity without another media read', function (): void {
    $admin = User::factory()->admin()->create();
    $host = Livewire::actingAs($admin)->test(AboutSettings::class);
    $saved = task7AboutMedia('task7-authorized-hydration');
    $presentation = task7OwnerChoicePresentation($saved);
    $picker = PathCuratorPicker::make('image_media_reference_key')
        ->purpose(ImageUploadPurpose::AboutImage)
        ->referenceKeyIdentity()
        ->ownerChoice(fn (): OwnerImageChoicePresentation => $presentation)
        ->container($host->instance()->getSchema('form'))
        ->state((int) $saved->getKey());

    $captured = task7CaptureMediaQueries(function () use ($picker): int|string|null {
        $picker->callAfterStateHydrated();

        return $picker->getState();
    });

    expect($presentation->savedMediaCanSelect)->toBeTrue()
        ->and($captured['result'])->toBe((int) $saved->getKey())
        ->and($captured['queries'])->toBe([]);
});

it('keeps the authenticated About Settings initial request query work bounded', function (int $ownerCount): void {
    $admin = User::factory()->admin()->create();
    $blocks = [];
    $profiles = [];

    foreach (range(1, $ownerCount) as $index) {
        $aboutName = "settings-about-{$ownerCount}-{$index}";
        $teamName = "settings-team-{$ownerCount}-{$index}";
        $aboutMedia = Media::factory()->create([
            'reference_key' => (string) Str::ulid(),
            'disk' => 'public',
            'directory' => ImageUploadPurpose::AboutImage->root(),
            'visibility' => 'public',
            'name' => $aboutName,
            'path' => ImageUploadPurpose::AboutImage->root()."/{$aboutName}.png",
            'width' => 640,
            'height' => 360,
            'size' => 2048,
            'type' => 'image/png',
            'ext' => 'png',
        ]);
        $teamMedia = Media::factory()->create([
            'reference_key' => (string) Str::ulid(),
            'disk' => 'public',
            'directory' => ImageUploadPurpose::TeamImage->root(),
            'visibility' => 'public',
            'name' => $teamName,
            'path' => ImageUploadPurpose::TeamImage->root()."/{$teamName}.png",
            'width' => 640,
            'height' => 360,
            'size' => 2048,
            'type' => 'image/png',
            'ext' => 'png',
        ]);

        Storage::disk('public')->put($aboutMedia->path, 'about-image');
        Storage::disk('public')->put($teamMedia->path, 'team-image');

        $blocks[] = [
            'key' => "settings_about_{$ownerCount}_{$index}",
            'type' => 'image',
            'visible' => true,
            'sort' => $index * 10,
            'image_path' => $aboutMedia->path,
            'image_media_reference_key' => $aboutMedia->reference_key,
            'image_alt' => "About image {$index}",
        ];
        $profiles[] = [
            'key' => "settings_team_{$ownerCount}_{$index}",
            'visible' => true,
            'sort' => $index * 10,
            'image_path' => $teamMedia->path,
            'image_media_reference_key' => $teamMedia->reference_key,
            'name' => "Team member {$index}",
        ];
    }

    $aboutPage = PublicFrontConfigRegistry::defaults()['about_page'];
    $aboutPage['enabled'] = true;
    $aboutPage['blocks'] = $blocks;
    $aboutPage['team_profiles'] = $profiles;

    DB::table('settings')->updateOrInsert(
        [
            'group' => PublicContentSettings::group(),
            'name' => 'about_page',
        ],
        [
            'locked' => false,
            'payload' => json_encode($aboutPage, JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ],
    );

    app()->forgetInstance(PublicContentSettings::class);
    app()->forgetInstance(PublicFrontConfigReader::class);
    app()->forgetInstance(PublicFrontRenderContext::class);
    app(SettingsContainer::class)->clearCache();
    DB::connection()->getPdo();
    $this->actingAs($admin);

    $recording = true;
    $queries = [];

    DB::listen(function (QueryExecuted $query) use (&$queries, &$recording): void {
        if (! $recording) {
            return;
        }

        $normalizedSql = mb_strtolower((string) preg_replace('/\s+/', ' ', trim($query->sql)));
        $queries[] = [
            'sql' => $normalizedSql,
            'bindings' => array_map(
                fn (mixed $binding): mixed => is_scalar($binding) || $binding === null
                    ? $binding
                    : get_debug_type($binding),
                $query->bindings,
            ),
        ];
    });

    try {
        Livewire::test(AboutSettings::class)->assertOk();
    } finally {
        $recording = false;
    }

    $selects = collect($queries)
        ->filter(fn (array $query): bool => str_starts_with($query['sql'], 'select '));
    $settingsSelects = $selects
        ->filter(fn (array $query): bool => preg_match('/\bfrom ["`]?settings["`]?\b/', $query['sql']) === 1);
    $mediaSelects = $selects
        ->filter(fn (array $query): bool => preg_match('/\bfrom ["`]?curator["`]?\b/', $query['sql']) === 1);
    $otherSelects = $selects
        ->reject(fn (array $query): bool => $settingsSelects->contains($query) || $mediaSelects->contains($query));
    $writes = collect($queries)
        ->filter(fn (array $query): bool => preg_match('/^(insert|update|delete|replace)\b/', $query['sql']) === 1);
    $otherStatements = collect($queries)
        ->reject(fn (array $query): bool => $selects->contains($query) || $writes->contains($query));
    $repeatedShapes = collect($queries)
        ->groupBy('sql')
        ->filter(fn ($shapeQueries): bool => $shapeQueries->count() > 1)
        ->map(fn ($shapeQueries, string $sql): array => [
            'sql' => $sql,
            'count' => $shapeQueries->count(),
            'bindings' => $shapeQueries->pluck('bindings')->all(),
        ])
        ->values();
    $repeatedMediaPointShapes = task7RepeatedMediaPointShapes($mediaSelects);
    $trace = json_encode([
        'configured_rows' => [
            'about_blocks' => $ownerCount,
            'team_profiles' => $ownerCount,
        ],
        'counts' => [
            'all_queries' => count($queries),
            'settings_selects' => $settingsSelects->count(),
            'media_selects' => $mediaSelects->count(),
            'other_selects' => $otherSelects->count(),
            'total_selects' => $selects->count(),
            'writes' => $writes->count(),
            'other_statements' => $otherStatements->count(),
        ],
        'repeated_normalized_shapes' => $repeatedShapes->all(),
        'repeated_media_point_shapes' => $repeatedMediaPointShapes->all(),
        'queries' => $queries,
    ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    expect($writes->all(), $trace)->toBe([])
        ->and($otherStatements->all(), $trace)->toBe([])
        ->and($repeatedMediaPointShapes->all(), $trace)->toBe([])
        ->and($mediaSelects->count(), $trace)->toBeLessThanOrEqual(9)
        ->and($settingsSelects->count(), $trace)->toBeLessThanOrEqual(3)
        ->and($selects->count(), $trace)->toBeLessThanOrEqual(14);
})->with([
    '2 About blocks and 2 team profiles' => [2],
    '8 About blocks and 8 team profiles' => [8],
]);

it('dehydrates an authorized exact saved owner identity without another media read and memoizes its presentation', function (): void {
    $admin = User::factory()->admin()->create();
    $host = Livewire::actingAs($admin)->test(AboutSettings::class);
    $saved = task7AboutMedia('task7-exact-saved');
    $presentation = task7OwnerChoicePresentation($saved);
    $presentationReads = 0;
    $picker = PathCuratorPicker::make('image_media_reference_key')
        ->purpose(ImageUploadPurpose::AboutImage)
        ->referenceKeyIdentity()
        ->ownerChoice(function () use (&$presentationReads, $presentation): OwnerImageChoicePresentation {
            $presentationReads++;

            return $presentation;
        })
        ->container($host->instance()->getSchema('form'));

    $captured = task7CaptureMediaQueries(fn (): array => [
        $picker->getStateToDehydrate((int) $saved->getKey()),
        $picker->getStateToDehydrate((int) $saved->getKey()),
    ]);

    expect($presentation->savedMediaCanSelect)->toBeTrue()
        ->and($captured['result'])->toBe([
            ['data.image_media_reference_key' => $saved->reference_key],
            ['data.image_media_reference_key' => $saved->reference_key],
        ])
        ->and($captured['queries'])->toBe([])
        ->and($presentationReads)->toBe(1);
});

it('preserves exact broken owner evidence without a media lookup', function (): void {
    $admin = User::factory()->admin()->create();
    $host = Livewire::actingAs($admin)->test(AboutSettings::class);
    $referenceKey = (string) Str::ulid();
    $presentation = new OwnerImageChoicePresentation(
        ownerKind: 'about_page.blocks.task7_broken.image_media_reference_key',
        ownerKindLabel: 'About',
        ownerLabel: 'Broken image',
        slotKind: 'about_block',
        slotLabel: 'Image',
        isProspective: false,
        directState: 'broken',
        directMedia: null,
        shownNowSource: 'none',
        shownNowMedia: null,
        shownNowPreviewUrl: null,
        savedMediaId: null,
        savedReferenceKey: $referenceKey,
        pendingKind: 'unchanged',
        pendingMedia: null,
        canClearPending: false,
        canChooseAutomatic: true,
        expectedMediaId: null,
        expectedLegacyPath: 'about/task7-broken.png',
        commitBoundary: ['commit' => 'Save', 'cancel' => 'Cancel', 'admission' => 'Permanent'],
        warningCodes: [],
    );
    $picker = PathCuratorPicker::make('image_media_reference_key')
        ->purpose(ImageUploadPurpose::AboutImage)
        ->referenceKeyIdentity()
        ->ownerChoice(fn (): OwnerImageChoicePresentation => $presentation)
        ->container($host->instance()->getSchema('form'));

    $captured = task7CaptureMediaQueries(
        fn (): array => $picker->getStateToDehydrate($referenceKey),
    );

    expect($captured['result'])->toBe(['data.image_media_reference_key' => $referenceKey])
        ->and($captured['queries'])->toBe([]);
});

it('keeps mismatched and non-owner numeric identities on the existing lookup and Gate path', function (): void {
    $admin = User::factory()->admin()->create();
    $host = Livewire::actingAs($admin)->test(AboutSettings::class);
    $saved = task7AboutMedia('task7-saved-for-mismatch');
    $replacement = task7AboutMedia('task7-mismatched-replacement');
    $presentation = task7OwnerChoicePresentation($saved);
    $ownerPicker = PathCuratorPicker::make('owner_media_reference_key')
        ->purpose(ImageUploadPurpose::AboutImage)
        ->referenceKeyIdentity()
        ->ownerChoice(fn (): OwnerImageChoicePresentation => $presentation)
        ->container($host->instance()->getSchema('form'));
    $ordinaryPicker = PathCuratorPicker::make('ordinary_media_reference_key')
        ->purpose(ImageUploadPurpose::AboutImage)
        ->referenceKeyIdentity()
        ->container($host->instance()->getSchema('form'));

    $captured = task7CaptureMediaQueries(fn (): array => [
        'owner' => $ownerPicker->getStateToDehydrate((int) $replacement->getKey()),
        'ordinary' => $ordinaryPicker->getStateToDehydrate((int) $saved->getKey()),
    ]);

    expect($captured['result'])->toBe([
        'owner' => ['data.owner_media_reference_key' => $replacement->reference_key],
        'ordinary' => ['data.ordinary_media_reference_key' => $saved->reference_key],
    ])->and($captured['queries'])->toHaveCount(2)
        ->each->toContain('"curator"."id" = ?');
});

it('falls back to the existing lookup and Gate denial when saved-media select policy is lost', function (): void {
    $admin = User::factory()->admin()->create();
    $host = Livewire::actingAs($admin)->test(AboutSettings::class);
    $saved = task7AboutMedia('task7-policy-loss');

    Gate::policy(Media::class, Task7DenyMediaSelectPolicy::class);

    try {
        $presentation = task7OwnerChoicePresentation($saved);
        $picker = PathCuratorPicker::make('image_media_reference_key')
            ->purpose(ImageUploadPurpose::AboutImage)
            ->referenceKeyIdentity()
            ->ownerChoice(fn (): OwnerImageChoicePresentation => $presentation)
            ->container($host->instance()->getSchema('form'));
        $exception = null;
        $captured = task7CaptureMediaQueries(function () use (&$exception, $picker, $saved): void {
            try {
                $picker->getStateToDehydrate((int) $saved->getKey());
            } catch (AuthorizationException $caught) {
                $exception = $caught;
            }
        });
    } finally {
        Gate::policy(Media::class, CuratorMediaPolicy::class);
    }

    expect($presentation->savedMediaCanSelect)->toBeFalse()
        ->and($exception)->toBeInstanceOf(AuthorizationException::class)
        ->and($captured['queries'])->toHaveCount(1)
        ->and($captured['queries'][0])->toContain('"curator"."id" = ?');
});
