<?php

use App\Filament\Forms\Components\PathCuratorPicker;
use App\Filament\Pages\DisplaySettings;
use App\Filament\Pages\SettingsSubjectOwnershipRegistry;
use App\Filament\Resources\Media\MediaResource;
use App\Models\Author;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\Media;
use App\Models\MediaAttachment;
use App\Models\Transcription;
use App\Models\User;
use App\Settings\PublicContentSettings;
use App\Support\Media\PublicMediaDelivery;
use App\Support\Media\SettingsOwnerImagePresenter;
use App\Support\PublicContent\PublicTranscriptionAggregates;
use App\Support\PublicContent\PublicTranscriptionPolicy;
use App\Support\PublicContent\PublicTranscriptionSelector;
use App\Support\PublicFront\PublicDefaultImageResolver;
use App\Support\PublicFront\PublicFrontConfigRegistry;
use App\Support\PublicFront\PublicFrontConfigValidator;
use App\Support\PublicFront\PublicFrontRenderContext;
use App\Support\SettingsLifecycle\SettingsLifecycleSchema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\LaravelSettings\Events\SettingsSaved;
use Spatie\LaravelSettings\SettingsContainer;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    fakeSettingsBackupSnapshotQueue();
    Storage::fake('local');
    Storage::fake('public');
});

function clearStep10V1aPublicFrontSettingsCache(): void
{
    app()->forgetInstance(PublicContentSettings::class);
    app()->forgetInstance(PublicFrontRenderContext::class);
    app()->forgetInstance(PublicTranscriptionPolicy::class);
    app()->forgetInstance(PublicTranscriptionSelector::class);
    app()->forgetInstance(PublicTranscriptionAggregates::class);
    app()->forgetInstance(PublicDefaultImageResolver::class);
    app()->forgetInstance(PublicMediaDelivery::class);
    app(SettingsContainer::class)->clearCache();
}

function saveStep10V1aPublicFrontSettings(array $settings): void
{
    foreach ($settings as $name => $payload) {
        DB::table('settings')->updateOrInsert(
            [
                'group' => PublicContentSettings::group(),
                'name' => $name,
            ],
            [
                'locked' => false,
                'payload' => json_encode($payload),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    clearStep10V1aPublicFrontSettingsCache();
}

function registerStep10V1aMediaPath(?string $path): ?Media
{
    if (! is_string($path) || blank($path)) {
        return null;
    }

    $existing = Media::query()->where('path', $path)->first();

    if ($existing instanceof Media) {
        return $existing;
    }

    $extension = mb_strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $type = match ($extension) {
        'png' => 'image/png',
        'webp' => 'image/webp',
        'svg' => 'image/svg+xml',
        default => 'image/jpeg',
    };
    Storage::disk('public')->put($path, 'renderable media fixture');

    return Media::factory()->create([
        'reference_key' => (string) Str::ulid(),
        'disk' => 'public',
        'directory' => dirname($path),
        'name' => pathinfo($path, PATHINFO_FILENAME),
        'path' => $path,
        'visibility' => 'public',
        'type' => $type,
        'ext' => $extension,
        'size' => 24,
        'width' => $type === 'image/svg+xml' ? null : 100,
        'height' => $type === 'image/svg+xml' ? null : 100,
    ]);
}

function saveStep10V1aRenderableSettings(array $settings): void
{
    foreach ($settings['default_images'] ?? [] as &$config) {
        if (! is_array($config) || ($config['mode'] ?? null) !== 'custom') {
            continue;
        }

        $media = registerStep10V1aMediaPath($config['path'] ?? null);

        if ($media instanceof Media) {
            $config['media_reference_key'] = $media->reference_key;
        }
    }
    unset($config);

    saveStep10V1aPublicFrontSettings($settings);
}

/**
 * @return array{group: ContentGroup, item: ContentItem, author: Author, transcription: Transcription}
 */
function createStep10V1aPublicItem(
    string $title,
    array $groupAttributes = [],
    array $itemAttributes = [],
    ?Author $author = null,
    ?string $groupCoverPath = null,
    ?string $itemImagePath = null,
): array {
    $group = ContentGroup::factory()->published()->create([
        'title' => "{$title} Podcast",
        'slug' => str($title)->slug()->append('-podcast')->toString(),
        ...$groupAttributes,
    ]);
    $item = ContentItem::factory()
        ->for($group)
        ->published()
        ->create([
            'title' => "{$title} Episode",
            'slug' => str($title)->slug()->append('-episode')->toString(),
            ...$itemAttributes,
        ]);
    $author ??= Author::factory()->create([
        'name' => "{$title} Contributor",
        'slug' => str($title)->slug()->append('-contributor')->toString(),
    ]);
    $transcription = Transcription::factory()
        ->for($item)
        ->forAuthor($author)
        ->published()
        ->create([
            'title' => "{$title} Transcript",
            'transcript_markdown' => "Transcript for {$title}.",
        ]);

    $item->update(['featured_transcription_id' => $transcription->id]);

    if (is_string($groupCoverPath) && filled($groupCoverPath)) {
        $coverMedia = registerStep10V1aMediaPath($groupCoverPath);

        if ($coverMedia instanceof Media) {
            MediaAttachment::query()->create([
                'media_id' => $coverMedia->getKey(),
                'attachable_type' => 'content_group',
                'attachable_id' => $group->getKey(),
                'role' => 'cover',
                'position' => 0,
            ]);
        }
    }

    if (is_string($itemImagePath) && filled($itemImagePath)) {
        $imageMedia = registerStep10V1aMediaPath($itemImagePath);

        if ($imageMedia instanceof Media) {
            MediaAttachment::query()->create([
                'media_id' => $imageMedia->getKey(),
                'attachable_type' => 'content_item',
                'attachable_id' => $item->getKey(),
                'role' => 'primary_image',
                'position' => 0,
            ]);
        }
    }

    return [
        'group' => $group->refresh(),
        'item' => $item->refresh(),
        'author' => $author->refresh(),
        'transcription' => $transcription->refresh(),
    ];
}

function step10V1aDefaultImages(array $overrides = []): array
{
    return array_replace_recursive(
        PublicFrontConfigRegistry::defaults()['default_images'],
        $overrides,
    );
}

/**
 * @return array<string, mixed>
 */
function step10V1aContributorImageTemplate(): array
{
    return [
        'key' => 'default_contributor',
        'label' => 'V1A contributor image card',
        'family' => 'contributor',
        'layout' => 'cards',
        'density' => 'comfortable',
        'image_size' => 'medium',
        'title_size' => 'base',
        'parts' => [
            ['type' => 'image', 'source' => 'author', 'attribute' => 'name', 'visible' => true, 'order' => 10],
            ['type' => 'title', 'source' => 'author', 'attribute' => 'name', 'visible' => true, 'order' => 20],
            ['type' => 'metadata_row', 'source' => 'author', 'attribute' => 'transcription_count', 'visible' => true, 'order' => 30],
        ],
    ];
}

it('normalizes default image settings and backfills the settings row', function (): void {
    $result = app(PublicFrontConfigValidator::class)->validate([
        'default_images' => [
            'content_item' => [
                'mode' => 'custom',
                'path' => 'default-images/item.webp',
                'media_reference_key' => 'malformed-reference-key',
                'class' => 'rounded-full',
            ],
            'content_group' => [
                'mode' => 'unsafe',
                'path' => '../group.jpg',
            ],
            'contributor' => 'bad-value',
            'global' => [
                'mode' => 'none',
                'path' => 'header/logo.svg',
            ],
            'unexpected' => [],
        ],
    ]);

    $defaultImages = $result->group('default_images');
    $invalidConfig = collect($result->invalidConfig());
    $paths = $invalidConfig
        ->map(fn ($invalidConfig): string => $invalidConfig->path)
        ->all();

    expect($defaultImages['content_item'])->toBe([
        'mode' => 'custom',
        'path' => 'default-images/item.webp',
        'media_reference_key' => null,
    ])->and($defaultImages['content_group'])->toBe([
        'mode' => 'inherit',
        'path' => null,
        'media_reference_key' => null,
    ])->and($defaultImages['contributor'])->toBe([
        'mode' => 'inherit',
        'path' => null,
        'media_reference_key' => null,
    ])->and($defaultImages['global'])->toBe([
        'mode' => 'none',
        'path' => null,
        'media_reference_key' => null,
    ])->and($paths)->toContain(
        'default_images.content_item.class',
        'default_images.content_item.media_reference_key',
        'default_images.content_group.mode',
        'default_images.content_group.path',
        'default_images.contributor',
        'default_images.global.path',
        'default_images.unexpected',
    );
    expect($invalidConfig->firstWhere('path', 'default_images.content_item.media_reference_key')->reason)
        ->toBe('invalid_media_reference_key');

    DB::table('settings')
        ->where('group', PublicContentSettings::group())
        ->where('name', 'default_images')
        ->delete();

    $migration = include base_path('database/settings/2026_07_09_000005_add_public_default_image_settings.php');
    $migration->up();

    $payload = json_decode(
        DB::table('settings')
            ->where('group', PublicContentSettings::group())
            ->where('name', 'default_images')
            ->value('payload'),
        true,
    );

    expect($payload)->toMatchArray(PublicFrontConfigRegistry::defaults()['default_images']);
});

it('saves no-image mode through the public settings page', function (): void {
    $this->actingAs(User::factory()->create());

    Livewire::test(DisplaySettings::class)
        ->set('data.default_images.content_item.mode', 'none')
        ->call('save')
        ->assertHasNoFormErrors();

    clearStep10V1aPublicFrontSettingsCache();

    expect(app(PublicContentSettings::class)->default_images['content_item']['mode'])->toBe('none');
});

it('presents every default image family as a pending owner choice with its effective source', function (): void {
    $this->actingAs(User::factory()->admin()->create());
    $global = registerStep10V1aMediaPath('default-images/global-current.jpg');

    saveStep10V1aPublicFrontSettings([
        'default_images' => step10V1aDefaultImages([
            'global' => [
                'mode' => 'custom',
                'path' => $global->path,
                'media_reference_key' => $global->reference_key,
            ],
            'content_item' => ['mode' => 'inherit'],
            'content_group' => ['mode' => 'none'],
            'contributor' => ['mode' => 'inherit'],
        ]),
    ]);

    $schema = Livewire::test(DisplaySettings::class)
        ->instance()
        ->getSchema('form');
    $pickers = collect($schema->getFlatComponents(withActions: false, withHidden: true, withAbsoluteKeys: true))
        ->filter(fn (mixed $component): bool => $component instanceof PathCuratorPicker)
        ->keyBy(fn (PathCuratorPicker $component): string => $component->getStatePath(isAbsolute: false));

    expect($pickers)->toHaveCount(4);

    foreach ([
        'default_images.global.media_reference_key' => ['custom', 'configured_default'],
        'default_images.content_item.media_reference_key' => ['inherit', 'global_default'],
        'default_images.content_group.media_reference_key' => ['none', 'none'],
        'default_images.contributor.media_reference_key' => ['inherit', 'global_default'],
    ] as $path => [$mode, $source]) {
        $picker = $pickers->get($path);

        expect($picker)->toBeInstanceOf(PathCuratorPicker::class)
            ->and($picker->isOwnerChoice())->toBeTrue()
            ->and($picker->getOwnerChoicePresentation()?->ownerKind)->toBe($path)
            ->and($picker->getOwnerChoicePresentation()?->directState)->toBe($mode)
            ->and($picker->getOwnerChoicePresentation()?->shownNowSource)->toBe($source);
    }
});

it('keeps opened default-image modes authoritative against a fresh owner snapshot', function (string $mode): void {
    $this->actingAs(User::factory()->admin()->create());
    $openedMedia = $mode === 'custom'
        ? registerStep10V1aMediaPath("default-images/opened-{$mode}.jpg")
        : null;
    $pendingMedia = registerStep10V1aMediaPath("default-images/pending-{$mode}.jpg");
    $freshMedia = registerStep10V1aMediaPath("default-images/fresh-{$mode}.jpg");
    expect($pendingMedia)->toBeInstanceOf(Media::class)
        ->and($freshMedia)->toBeInstanceOf(Media::class);
    $presenter = app(SettingsOwnerImagePresenter::class);
    $openedSnapshot = $presenter->snapshot([
        'default_images' => step10V1aDefaultImages([
            'content_item' => [
                'mode' => $mode,
                'path' => $openedMedia?->path,
                'media_reference_key' => $openedMedia?->reference_key,
            ],
        ]),
    ], SettingsSubjectOwnershipRegistry::DISPLAY);
    $freshSnapshot = $presenter->snapshot([
        'default_images' => step10V1aDefaultImages([
            'content_item' => [
                'mode' => 'custom',
                'path' => $freshMedia->path,
                'media_reference_key' => $freshMedia->reference_key,
            ],
        ]),
    ], SettingsSubjectOwnershipRegistry::DISPLAY);
    $openedEvidence = $presenter->openedEvidence($openedSnapshot);
    $presenter->primeOpenedEvidence($openedEvidence);
    $choice = $presenter->choice(
        $freshSnapshot,
        'default_images.content_item.media_reference_key',
        $pendingMedia->getKey(),
        ['commit' => 'Save', 'cancel' => 'Cancel', 'admission' => 'Permanent'],
        $openedEvidence['default_images.content_item.media_reference_key'],
    );

    expect($choice->directState)->toBe($mode)
        ->and($choice->savedMediaId)->toBe($openedMedia?->getKey())
        ->and($choice->savedReferenceKey)->toBe($openedMedia?->reference_key)
        ->and($choice->directMedia['id'] ?? null)->toBe($openedMedia?->getKey())
        ->and($choice->shownNowMedia['id'] ?? null)->toBe($freshMedia->getKey())
        ->and($choice->pendingMedia['id'] ?? null)->toBe($pendingMedia->getKey());
})->with(['custom', 'inherit', 'none']);

it('presents an admitted storage media row as a numeric pending default image without mutating its identity', function (): void {
    $this->actingAs(User::factory()->admin()->create());
    $saved = registerStep10V1aMediaPath('default-images/inventory-pending-saved.jpg');
    $pending = registerStep10V1aMediaPath('media-imports/inventory-pending-default.jpg');
    expect($saved)->toBeInstanceOf(Media::class)
        ->and($pending)->toBeInstanceOf(Media::class);
    $pending->forceFill(['title' => 'Admitted storage default image'])->save();
    $pending->refresh();
    $settings = [
        'default_images' => step10V1aDefaultImages([
            'content_item' => [
                'mode' => 'custom',
                'path' => $saved->path,
                'media_reference_key' => $saved->reference_key,
            ],
        ]),
    ];
    $settingsBefore = $settings;
    $pendingAttributes = $pending->getAttributes();
    $pendingContents = Storage::disk((string) $pending->disk)->get((string) $pending->path);
    $presenter = app(SettingsOwnerImagePresenter::class);
    $snapshot = $presenter->snapshot($settings, SettingsSubjectOwnershipRegistry::DISPLAY);

    $choice = $presenter->choice(
        $snapshot,
        'default_images.content_item.media_reference_key',
        $pending->getKey(),
        ['commit' => 'Save', 'cancel' => 'Cancel', 'admission' => 'Permanent'],
    );

    expect($choice)
        ->pendingKind->toBe('replacement')
        ->and($choice->pendingMedia)->toBe([
            'id' => $pending->getKey(),
            'reference_key' => $pending->reference_key,
            'label' => 'Admitted storage default image',
            'preview_url' => route('admin.media-files.view', ['media' => $pending]),
            'details_url' => MediaResource::getUrl('edit', ['record' => $pending], panel: 'admin'),
        ])
        ->and($settings)->toBe($settingsBefore)
        ->and($pending->refresh()->getAttributes())->toBe($pendingAttributes)
        ->and($pending->disk)->toBe('public')
        ->and($pending->path)->toBe('media-imports/inventory-pending-default.jpg')
        ->and(Storage::disk((string) $pending->disk)->get((string) $pending->path))->toBe($pendingContents);
});

it('replaces a broken configured default image through the real owner picker workflow', function (): void {
    $missingReferenceKey = (string) Str::ulid();
    $missingPath = 'default-images/missing-content-item-default.jpg';
    $replacement = registerStep10V1aMediaPath('default-images/replacement-content-item-default.jpg');
    expect($replacement)->toBeInstanceOf(Media::class);
    saveStep10V1aPublicFrontSettings([
        'default_images' => step10V1aDefaultImages([
            'content_item' => [
                'mode' => 'custom',
                'path' => $missingPath,
                'media_reference_key' => $missingReferenceKey,
            ],
        ]),
    ]);

    Livewire::actingAs(User::factory()->admin()->create())
        ->test(DisplaySettings::class)
        ->assertSet(
            'data.default_images.content_item.media_reference_key',
            $missingReferenceKey,
        )
        ->set(
            'data.default_images.content_item.media_reference_key',
            $replacement->getKey(),
        )
        ->call('save')
        ->assertHasNoFormErrors();

    clearStep10V1aPublicFrontSettingsCache();

    expect(app(PublicContentSettings::class)->default_images['content_item'])
        ->toMatchArray([
            'mode' => 'custom',
            'path' => $replacement->path,
            'media_reference_key' => $replacement->reference_key,
        ]);
});

it('clears a broken configured default image through the real mode transition', function (): void {
    $missingReferenceKey = (string) Str::ulid();
    $missingPath = 'default-images/missing-content-item-default-to-clear.jpg';
    saveStep10V1aPublicFrontSettings([
        'default_images' => step10V1aDefaultImages([
            'content_item' => [
                'mode' => 'custom',
                'path' => $missingPath,
                'media_reference_key' => $missingReferenceKey,
            ],
        ]),
    ]);

    $component = Livewire::actingAs(User::factory()->admin()->create())
        ->test(DisplaySettings::class)
        ->assertSet(
            'data.default_images.content_item.media_reference_key',
            $missingReferenceKey,
        );
    $picker = collect($component->instance()->getSchema('form')->getFlatComponents(
        withActions: false,
        withHidden: true,
        withAbsoluteKeys: true,
    ))->first(fn (mixed $field): bool => $field instanceof PathCuratorPicker
        && $field->getStatePath(isAbsolute: false) === 'default_images.content_item.media_reference_key');
    assert($picker instanceof PathCuratorPicker);

    expect($picker->getOwnerChoicePresentation()?->directState)->toBe('broken')
        ->and($picker->getOwnerChoicePresentation()?->canChooseAutomatic)->toBeFalse();

    $component
        ->set('data.default_images.content_item.mode', 'none')
        ->call('save')
        ->assertHasNoFormErrors();

    clearStep10V1aPublicFrontSettingsCache();

    expect(app(PublicContentSettings::class)->default_images['content_item'])
        ->toMatchArray([
            'mode' => 'none',
            'path' => null,
            'media_reference_key' => null,
        ]);
});

it('clears a valid configured default image through the same real mode transition', function (): void {
    $current = registerStep10V1aMediaPath('default-images/valid-content-item-default-to-clear.jpg');
    expect($current)->toBeInstanceOf(Media::class);
    saveStep10V1aPublicFrontSettings([
        'default_images' => step10V1aDefaultImages([
            'content_item' => [
                'mode' => 'custom',
                'path' => $current->path,
                'media_reference_key' => $current->reference_key,
            ],
        ]),
    ]);

    Livewire::actingAs(User::factory()->admin()->create())
        ->test(DisplaySettings::class)
        ->assertSet(
            'data.default_images.content_item.media_reference_key',
            $current->getKey(),
        )
        ->set('data.default_images.content_item.mode', 'inherit')
        ->call('save')
        ->assertHasNoFormErrors();

    clearStep10V1aPublicFrontSettingsCache();

    expect(app(PublicContentSettings::class)->default_images['content_item'])
        ->toMatchArray([
            'mode' => 'inherit',
            'path' => null,
            'media_reference_key' => null,
        ]);
});

it('preserves an untouched broken custom default image when its picker is omitted', function (): void {
    $missingReferenceKey = (string) Str::ulid();
    $missingPath = 'default-images/untouched-missing-content-item-default.jpg';
    saveStep10V1aPublicFrontSettings([
        'default_images' => step10V1aDefaultImages([
            'content_item' => [
                'mode' => 'custom',
                'path' => $missingPath,
                'media_reference_key' => $missingReferenceKey,
            ],
        ]),
    ]);

    Livewire::actingAs(User::factory()->admin()->create())
        ->test(DisplaySettings::class)
        ->assertSet(
            'data.default_images.content_item.media_reference_key',
            $missingReferenceKey,
        )
        ->set('data.default_images.contributor.mode', 'none')
        ->call('save')
        ->assertHasNoFormErrors();

    clearStep10V1aPublicFrontSettingsCache();

    expect(app(PublicContentSettings::class)->default_images['content_item'])
        ->toMatchArray([
            'mode' => 'custom',
            'path' => $missingPath,
            'media_reference_key' => $missingReferenceKey,
        ]);
});

it('rejects forged nonselectable default media while preserving the trusted current selection', function (): void {
    $admin = User::factory()->admin()->create();
    $current = registerStep10V1aMediaPath('default-images/current.jpg');
    $private = Media::factory()->create([
        'reference_key' => (string) Str::ulid(),
        'disk' => 'public',
        'directory' => 'default-images',
        'name' => 'forged-private',
        'path' => 'default-images/forged-private.jpg',
        'visibility' => 'private',
        'type' => 'image/jpeg',
        'ext' => 'jpg',
        'size' => 24,
        'width' => 100,
        'height' => 100,
    ]);
    Storage::disk('public')->put($private->path, 'private media fixture');
    expect($current)->toBeInstanceOf(Media::class);

    saveStep10V1aPublicFrontSettings([
        'default_images' => step10V1aDefaultImages([
            'content_item' => [
                'mode' => 'custom',
                'path' => $current->path,
                'media_reference_key' => $current->reference_key,
            ],
        ]),
    ]);

    $component = Livewire::actingAs($admin)
        ->test(DisplaySettings::class)
        ->assertSet('data.default_images.content_item.media_reference_key', $current->getKey())
        ->set('data.default_images.content_item.media_reference_key', $private->reference_key)
        ->assertHasErrors(['data.default_images.content_item.media_reference_key'])
        ->assertSet('data.default_images.content_item.media_reference_key', $current->getKey());

    $component->call('save')->assertHasNoFormErrors();
    clearStep10V1aPublicFrontSettingsCache();

    expect(app(PublicContentSettings::class)->default_images['content_item'])
        ->toMatchArray([
            'mode' => 'custom',
            'path' => $current->path,
            'media_reference_key' => $current->reference_key,
        ]);
});

it('preserves an already stored nonselectable default media identity on unrelated saves', function (): void {
    $admin = User::factory()->admin()->create();
    $private = Media::factory()->create([
        'reference_key' => (string) Str::ulid(),
        'disk' => 'public',
        'directory' => 'default-images',
        'name' => 'existing-private',
        'path' => 'default-images/existing-private.jpg',
        'visibility' => 'private',
        'type' => 'image/jpeg',
        'ext' => 'jpg',
        'size' => 24,
        'width' => 100,
        'height' => 100,
    ]);
    Storage::disk('public')->put($private->path, 'private media fixture');
    saveStep10V1aPublicFrontSettings([
        'default_images' => step10V1aDefaultImages([
            'content_item' => [
                'mode' => 'custom',
                'path' => $private->path,
                'media_reference_key' => $private->reference_key,
            ],
        ]),
    ]);

    Livewire::actingAs($admin)
        ->test(DisplaySettings::class)
        ->assertSet('data.default_images.content_item.media_reference_key', $private->getKey())
        ->set('data.default_images.content_group.mode', 'none')
        ->call('save')
        ->assertHasNoFormErrors();

    clearStep10V1aPublicFrontSettingsCache();
    $settings = app(PublicContentSettings::class)->default_images;

    expect($settings['content_item'])
        ->toMatchArray([
            'mode' => 'custom',
            'path' => $private->path,
            'media_reference_key' => $private->reference_key,
        ])
        ->and($settings['content_group']['mode'])->toBe('none');
});

it('halts a same-unit image conflict preserves pending state and requires reload', function (): void {
    $admin = User::factory()->admin()->create();
    $openedWith = registerStep10V1aMediaPath('default-images/opened-with.jpg');
    $changedElsewhere = registerStep10V1aMediaPath('default-images/changed-elsewhere.jpg');
    $pending = registerStep10V1aMediaPath('default-images/pending-choice.jpg');
    saveStep10V1aPublicFrontSettings([
        'default_images' => step10V1aDefaultImages([
            'content_item' => [
                'mode' => 'custom',
                'path' => $openedWith->path,
                'media_reference_key' => $openedWith->reference_key,
            ],
        ]),
    ]);

    $component = Livewire::actingAs($admin)
        ->test(DisplaySettings::class)
        ->set('data.default_images.content_item.media_reference_key', $pending->getKey())
        ->set('data.default_images.contributor.mode', 'none');
    $openedFingerprint = $component->get('settingsOwnerImageFingerprint');

    saveStep10V1aPublicFrontSettings([
        'default_images' => step10V1aDefaultImages([
            'content_item' => [
                'mode' => 'custom',
                'path' => $changedElsewhere->path,
                'media_reference_key' => $changedElsewhere->reference_key,
            ],
        ]),
    ]);

    $component
        ->set('data.default_images.global.mode', 'none')
        ->assertSet('data.default_images.content_item.media_reference_key', $pending->getKey());
    expect($component->get('settingsOwnerImageFingerprint'))->toBe($openedFingerprint);
    clearStep10V1aPublicFrontSettingsCache();
    $displayBeforeHalt = SettingsSubjectOwnershipRegistry::extractOwned(
        app(PublicContentSettings::class)->toArray(),
        SettingsSubjectOwnershipRegistry::DISPLAY,
    );
    Event::fake([SettingsSaved::class]);
    $conflictTitle = __('admin.validation.settings_unit_changed', [
        'unit' => app(SettingsLifecycleSchema::class)->labelFor('default_images.content_item'),
    ]);

    $component
        ->call('save')
        ->assertNotified($conflictTitle)
        ->assertSet('data.default_images.content_item.media_reference_key', $pending->getKey())
        ->assertSet('data.default_images.global.mode', 'none')
        ->assertSet('data.default_images.contributor.mode', 'none');
    Event::assertNotDispatched(SettingsSaved::class);

    expect($component->get('settingsOwnerImageFingerprint'))->toBe($openedFingerprint);
    clearStep10V1aPublicFrontSettingsCache();
    $displayAfterHalt = SettingsSubjectOwnershipRegistry::extractOwned(
        app(PublicContentSettings::class)->toArray(),
        SettingsSubjectOwnershipRegistry::DISPLAY,
    );
    expect($displayAfterHalt)
        ->toBe($displayBeforeHalt)
        ->and(app(PublicContentSettings::class)->default_images['content_item']['media_reference_key'])
        ->toBe($changedElsewhere->reference_key);

    $component
        ->call('save')
        ->assertNotified($conflictTitle);
    Event::assertNotDispatched(SettingsSaved::class);
    clearStep10V1aPublicFrontSettingsCache();

    expect(app(PublicContentSettings::class)->default_images['content_item'])
        ->toMatchArray([
            'path' => $changedElsewhere->path,
            'media_reference_key' => $changedElsewhere->reference_key,
        ])
        ->and(app(PublicContentSettings::class)->default_images['global']['mode'])->not->toBe('none')
        ->and(app(PublicContentSettings::class)->default_images['contributor']['mode'])->not->toBe('none');
});

it('does not treat an unrelated settings subject image change as display drift', function (): void {
    $admin = User::factory()->admin()->create();
    $current = registerStep10V1aMediaPath('default-images/unrelated-current.jpg');
    $logo = registerStep10V1aMediaPath('header/unrelated-logo.png');
    saveStep10V1aPublicFrontSettings([
        'default_images' => step10V1aDefaultImages([
            'content_item' => [
                'mode' => 'custom',
                'path' => $current->path,
                'media_reference_key' => $current->reference_key,
            ],
        ]),
    ]);

    $component = Livewire::actingAs($admin)
        ->test(DisplaySettings::class)
        ->set('data.default_images.contributor.mode', 'none');
    saveStep10V1aPublicFrontSettings([
        'menu_config' => [
            'logo' => [
                'light_path' => $logo->path,
                'light_media_reference_key' => $logo->reference_key,
            ],
        ],
    ]);

    $component->call('save')->assertHasNoFormErrors();
    clearStep10V1aPublicFrontSettingsCache();

    expect(app(PublicContentSettings::class)->default_images['contributor']['mode'])->toBe('none')
        ->and(app(PublicContentSettings::class)->menu_config['logo']['light_media_reference_key'])
        ->toBe($logo->reference_key);
});

it('preserves nested settings values while adding and removing media reference keys', function (): void {
    saveStep10V1aPublicFrontSettings([
        'menu_config' => [
            'enabled' => true,
            'logo' => [
                'light_path' => 'header/light.svg',
                'dark_path' => 'header/dark.svg',
                'alt_text' => 'Custom logo',
                'display_mode' => 'image',
                'size' => 'large',
            ],
        ],
        'about_page' => [
            'blocks' => [
                [
                    'type' => 'image',
                    'image_path' => 'about/outer.jpg',
                    'caption' => 'Outer image',
                ],
                [
                    'type' => 'image',
                    'data' => [
                        'image_path' => 'about/nested.jpg',
                        'caption' => 'Nested image',
                    ],
                ],
            ],
            'team_profiles' => [[
                'name' => 'Team member',
                'image_path' => 'team/member.jpg',
            ]],
        ],
        'default_images' => [
            'global' => [
                'mode' => 'custom',
                'path' => 'default-images/global.jpg',
                'caption' => 'Keep me',
            ],
        ],
    ]);

    $migration = include base_path('database/settings/2026_07_20_000000_add_public_media_reference_keys.php');
    $migration->up();

    $payloads = DB::table('settings')
        ->where('group', PublicContentSettings::group())
        ->whereIn('name', ['menu_config', 'about_page', 'default_images'])
        ->pluck('payload', 'name')
        ->map(fn (string $payload): array => json_decode($payload, true, flags: JSON_THROW_ON_ERROR));

    expect($payloads['menu_config']['logo'])->toMatchArray([
        'light_path' => 'header/light.svg',
        'dark_path' => 'header/dark.svg',
        'alt_text' => 'Custom logo',
        'display_mode' => 'image',
        'size' => 'large',
        'light_media_reference_key' => null,
        'dark_media_reference_key' => null,
    ])->and($payloads['about_page']['blocks'][0])->toMatchArray([
        'image_path' => 'about/outer.jpg',
        'caption' => 'Outer image',
        'image_media_reference_key' => null,
    ])->and($payloads['about_page']['blocks'][1]['data'])->toMatchArray([
        'image_path' => 'about/nested.jpg',
        'caption' => 'Nested image',
        'image_media_reference_key' => null,
    ])->and($payloads['about_page']['team_profiles'][0])->toMatchArray([
        'name' => 'Team member',
        'image_path' => 'team/member.jpg',
        'image_media_reference_key' => null,
    ])->and($payloads['default_images']['global'])->toMatchArray([
        'mode' => 'custom',
        'path' => 'default-images/global.jpg',
        'caption' => 'Keep me',
        'media_reference_key' => null,
    ]);

    $migration->down();

    $rolledBack = DB::table('settings')
        ->where('group', PublicContentSettings::group())
        ->whereIn('name', ['menu_config', 'about_page', 'default_images'])
        ->pluck('payload', 'name')
        ->map(fn (string $payload): array => json_decode($payload, true, flags: JSON_THROW_ON_ERROR));

    expect($rolledBack['menu_config']['logo'])->not->toHaveKeys([
        'light_media_reference_key',
        'dark_media_reference_key',
    ])->and($rolledBack['menu_config']['logo']['alt_text'])->toBe('Custom logo')
        ->and($rolledBack['about_page']['blocks'][0])->not->toHaveKey('image_media_reference_key')
        ->and($rolledBack['about_page']['blocks'][1]['data'])->not->toHaveKey('image_media_reference_key')
        ->and($rolledBack['about_page']['team_profiles'][0])->not->toHaveKey('image_media_reference_key')
        ->and($rolledBack['default_images']['global'])->not->toHaveKey('media_reference_key')
        ->and($rolledBack['default_images']['global']['caption'])->toBe('Keep me');
});

it('renders content item custom inherit and none fallbacks on cards and item pages', function (): void {
    $custom = createStep10V1aPublicItem('V1A Item Custom');
    saveStep10V1aRenderableSettings([
        'default_images' => step10V1aDefaultImages([
            'content_item' => ['mode' => 'custom', 'path' => 'default-images/item-custom.jpg'],
        ]),
    ]);

    $this->get('/search?q=V1A%20Item%20Custom')
        ->assertSuccessful()
        ->assertSee('/storage/default-images/item-custom.jpg', false)
        ->assertSee('data-card-image-source="content_item_default"', false)
        ->assertSee('data-card-part-flow="media-leading"', false);
    $this->get("/items/{$custom['group']->slug}/{$custom['item']->slug}")
        ->assertSuccessful()
        ->assertSee('/storage/default-images/item-custom.jpg', false)
        ->assertSee('data-item-page-image-source="content_item_default"', false);

    $global = createStep10V1aPublicItem('V1A Item Global');
    saveStep10V1aRenderableSettings([
        'default_images' => step10V1aDefaultImages([
            'global' => ['mode' => 'custom', 'path' => 'default-images/global.jpg'],
            'content_item' => ['mode' => 'inherit', 'path' => null],
        ]),
    ]);

    $this->get('/search?q=V1A%20Item%20Global')
        ->assertSuccessful()
        ->assertSee('/storage/default-images/global.jpg', false)
        ->assertSee('data-card-image-source="global_default"', false);
    $this->get("/items/{$global['group']->slug}/{$global['item']->slug}")
        ->assertSuccessful()
        ->assertSee('/storage/default-images/global.jpg', false)
        ->assertSee('data-item-page-image-source="global_default"', false);

    $none = createStep10V1aPublicItem('V1A Item None', groupCoverPath: 'content-groups/covers/should-not-render.jpg');
    saveStep10V1aRenderableSettings([
        'default_images' => step10V1aDefaultImages([
            'global' => ['mode' => 'custom', 'path' => 'default-images/global-hidden.jpg'],
            'content_item' => ['mode' => 'none', 'path' => null],
        ]),
    ]);

    $this->get('/search?q=V1A%20Item%20None')
        ->assertSuccessful()
        ->assertSee('data-card-image-source="fallback"', false)
        ->assertSee('data-card-part-flow="media-leading"', false)
        ->assertDontSee('content-groups/covers/should-not-render.jpg', false)
        ->assertDontSee('default-images/global-hidden.jpg', false);
    $this->get("/items/{$none['group']->slug}/{$none['item']->slug}")
        ->assertSuccessful()
        ->assertDontSee('data-test="item-page-image"', false)
        ->assertDontSee('content-groups/covers/should-not-render.jpg', false)
        ->assertDontSee('default-images/global-hidden.jpg', false);
});

it('keeps local item images external thumbnails and podcast covers ahead of configured item fallbacks', function (): void {
    $local = createStep10V1aPublicItem('V1A Item Local', [], [
        'external_thumbnail_url' => 'https://cdn.example.test/v1a-local-external.jpg',
    ], groupCoverPath: 'content-groups/covers/ignored-local-cover.jpg', itemImagePath: 'content-items/images/v1a-local.jpg');
    $external = createStep10V1aPublicItem('V1A Item Explicit', [], [
        'external_thumbnail_url' => 'https://cdn.example.test/v1a-explicit.jpg',
    ], groupCoverPath: 'content-groups/covers/ignored-cover.jpg');
    $cover = createStep10V1aPublicItem('V1A Item Cover', groupCoverPath: 'content-groups/covers/preferred-cover.jpg');

    saveStep10V1aRenderableSettings([
        'default_images' => step10V1aDefaultImages([
            'content_item' => ['mode' => 'custom', 'path' => 'default-images/item-ignored.jpg'],
        ]),
    ]);

    $this->get('/search?q=V1A%20Item%20Local')
        ->assertSuccessful()
        ->assertSee('/storage/content-items/images/v1a-local.jpg', false)
        ->assertSee('data-card-image-source="item"', false)
        ->assertDontSee('v1a-local-external.jpg', false)
        ->assertDontSee('default-images/item-ignored.jpg', false);
    $this->get("/items/{$local['group']->slug}/{$local['item']->slug}")
        ->assertSuccessful()
        ->assertSee('/storage/content-items/images/v1a-local.jpg', false)
        ->assertSee('data-item-page-image-source="item"', false)
        ->assertDontSee('v1a-local-external.jpg', false);

    $this->get('/search?q=V1A%20Item%20Explicit')
        ->assertSuccessful()
        ->assertSee('https://cdn.example.test/v1a-explicit.jpg', false)
        ->assertSee('data-card-image-source="item_external"', false)
        ->assertDontSee('default-images/item-ignored.jpg', false);
    $this->get("/items/{$external['group']->slug}/{$external['item']->slug}")
        ->assertSuccessful()
        ->assertSee('https://cdn.example.test/v1a-explicit.jpg', false)
        ->assertSee('data-item-page-image-source="item_external"', false);

    $this->get('/search?q=V1A%20Item%20Cover')
        ->assertSuccessful()
        ->assertSee('/storage/content-groups/covers/preferred-cover.jpg', false)
        ->assertSee('data-card-image-source="group"', false)
        ->assertDontSee('default-images/item-ignored.jpg', false);
    $this->get("/items/{$cover['group']->slug}/{$cover['item']->slug}")
        ->assertSuccessful()
        ->assertSee('/storage/content-groups/covers/preferred-cover.jpg', false)
        ->assertSee('data-item-page-image-source="group"', false);
});

it('renders content group custom inherit and none fallbacks on cards and detail pages', function (): void {
    $custom = createStep10V1aPublicItem('V1A Group Custom');
    saveStep10V1aRenderableSettings([
        'default_images' => step10V1aDefaultImages([
            'content_group' => ['mode' => 'custom', 'path' => 'default-images/group-custom.jpg'],
        ]),
    ]);

    $this->get('/podcasts')
        ->assertSuccessful()
        ->assertSee('/storage/default-images/group-custom.jpg', false)
        ->assertSee('data-card-image-source="content_group_default"', false)
        ->assertSee('data-card-part-flow="media-leading"', false);
    $this->get("/podcasts/{$custom['group']->slug}")
        ->assertSuccessful()
        ->assertSee('/storage/default-images/group-custom.jpg', false)
        ->assertSee('data-content-group-image-source="content_group_default"', false);

    $global = createStep10V1aPublicItem('V1A Group Global');
    saveStep10V1aRenderableSettings([
        'default_images' => step10V1aDefaultImages([
            'global' => ['mode' => 'custom', 'path' => 'default-images/group-global.jpg'],
            'content_group' => ['mode' => 'inherit', 'path' => null],
        ]),
    ]);

    $this->get('/podcasts')
        ->assertSuccessful()
        ->assertSee('/storage/default-images/group-global.jpg', false)
        ->assertSee('data-card-image-source="global_default"', false);
    $this->get("/podcasts/{$global['group']->slug}")
        ->assertSuccessful()
        ->assertSee('/storage/default-images/group-global.jpg', false)
        ->assertSee('data-content-group-image-source="global_default"', false);

    $none = createStep10V1aPublicItem('V1A Group None');
    saveStep10V1aRenderableSettings([
        'default_images' => step10V1aDefaultImages([
            'global' => ['mode' => 'custom', 'path' => 'default-images/group-hidden.jpg'],
            'content_group' => ['mode' => 'none', 'path' => null],
            'content_item' => ['mode' => 'none', 'path' => null],
        ]),
    ]);

    $this->get('/podcasts')
        ->assertSuccessful()
        ->assertSee('data-test="content-group-fallback"', false)
        ->assertSee('data-card-part-flow="media-leading"', false)
        ->assertDontSee('default-images/group-hidden.jpg', false);
    $this->get("/podcasts/{$none['group']->slug}")
        ->assertSuccessful()
        ->assertSee('data-test="content-group-detail-fallback"', false)
        ->assertDontSee('default-images/group-hidden.jpg', false);
});

it('renders contributor custom inherit and none fallbacks on cards and detail pages', function (): void {
    $custom = createStep10V1aPublicItem('V1A Contributor Custom');
    saveStep10V1aRenderableSettings([
        'card_templates' => [
            step10V1aContributorImageTemplate(),
        ],
        'default_images' => step10V1aDefaultImages([
            'contributor' => ['mode' => 'custom', 'path' => 'default-images/contributor-custom.jpg'],
        ]),
    ]);

    $this->get('/contributors')
        ->assertSuccessful()
        ->assertSee('/storage/default-images/contributor-custom.jpg', false)
        ->assertSee('data-contributor-image-source="contributor_default"', false);
    $this->get("/contributors/{$custom['author']->slug}")
        ->assertSuccessful()
        ->assertSee('/storage/default-images/contributor-custom.jpg', false)
        ->assertSee('data-contributor-image-source="contributor_default"', false);

    $global = createStep10V1aPublicItem('V1A Contributor Global');
    saveStep10V1aRenderableSettings([
        'card_templates' => [
            step10V1aContributorImageTemplate(),
        ],
        'default_images' => step10V1aDefaultImages([
            'global' => ['mode' => 'custom', 'path' => 'default-images/contributor-global.jpg'],
            'contributor' => ['mode' => 'inherit', 'path' => null],
        ]),
    ]);

    $this->get('/contributors')
        ->assertSuccessful()
        ->assertSee('/storage/default-images/contributor-global.jpg', false)
        ->assertSee('data-contributor-image-source="global_default"', false);
    $this->get("/contributors/{$global['author']->slug}")
        ->assertSuccessful()
        ->assertSee('/storage/default-images/contributor-global.jpg', false)
        ->assertSee('data-contributor-image-source="global_default"', false);

    $none = createStep10V1aPublicItem('V1A Contributor None');
    saveStep10V1aPublicFrontSettings([
        'card_templates' => [
            step10V1aContributorImageTemplate(),
        ],
        'default_images' => step10V1aDefaultImages([
            'global' => ['mode' => 'custom', 'path' => 'default-images/contributor-hidden.jpg'],
            'contributor' => ['mode' => 'none', 'path' => null],
            'content_item' => ['mode' => 'none', 'path' => null],
        ]),
    ]);

    $this->get('/contributors')
        ->assertSuccessful()
        ->assertSee('data-test="contributor-card"', false)
        ->assertDontSee('default-images/contributor-hidden.jpg', false);
    $this->get("/contributors/{$none['author']->slug}")
        ->assertSuccessful()
        ->assertSee('data-test="contributor-page-fallback"', false)
        ->assertDontSee('default-images/contributor-hidden.jpg', false);
});
