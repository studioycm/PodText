<?php

use App\Enums\MediaAttachmentRole;
use App\Filament\Resources\ContentGroups\Pages\EditContentGroup;
use App\Filament\Resources\ContentGroups\Pages\ListContentGroups;
use App\Filament\Resources\ContentItems\Pages\EditContentItem;
use App\Filament\Resources\ContentItems\Pages\EditEpisodeWorkspace;
use App\Filament\Resources\ContentItems\Pages\ListContentItems;
use App\Filament\Resources\Media\MediaResource;
use App\Jobs\DownloadExternalContentItemImage;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\Media;
use App\Models\MediaAttachment;
use App\Models\User;
use App\Settings\PublicContentSettings;
use App\Support\Media\MediaAttachmentFormState;
use App\Support\Media\MediaAttachmentManager;
use App\Support\Media\OwnerImagePresenter;
use App\Support\PublicFront\PublicDefaultImageResolver;
use App\Support\PublicFront\PublicFrontConfigRegistry;
use App\Support\PublicFront\PublicFrontRenderContext;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\LaravelSettings\SettingsContainer;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Http::preventStrayRequests();
    Storage::fake('local');
    Storage::fake('public');
});

function ownerImageMedia(string $path, array $overrides = [], bool $store = true): Media
{
    $extension = mb_strtolower(pathinfo($path, PATHINFO_EXTENSION));
    /** @var Media $media */
    $media = Media::factory()->create(array_merge([
        'reference_key' => (string) Str::ulid(),
        'disk' => 'public',
        'directory' => dirname($path),
        'visibility' => 'public',
        'name' => pathinfo($path, PATHINFO_FILENAME),
        'path' => $path,
        'width' => 640,
        'height' => 360,
        'size' => 2048,
        'type' => $extension === 'png' ? 'image/png' : 'image/jpeg',
        'ext' => $extension,
        'title' => 'Owner image',
        'exif' => ['original_filename' => 'Original upload.jpg'],
    ], $overrides));

    if ($store) {
        Storage::disk((string) $media->disk)->put((string) $media->path, 'image-bytes');
    }

    return $media;
}

function saveOwnerImageDefaultSettings(array $overrides): void
{
    DB::table('settings')->updateOrInsert(
        [
            'group' => PublicContentSettings::group(),
            'name' => 'default_images',
        ],
        [
            'locked' => false,
            'payload' => json_encode(array_replace_recursive(
                PublicFrontConfigRegistry::defaults()['default_images'],
                $overrides,
            )),
            'created_at' => now(),
            'updated_at' => now(),
        ],
    );

    app()->forgetInstance(PublicContentSettings::class);
    app()->forgetInstance(PublicFrontRenderContext::class);
    app()->forgetInstance(PublicDefaultImageResolver::class);
    app(SettingsContainer::class)->clearCache();
}

it('presents a direct owner image with truthful canonical metadata and safe admin links', function (): void {
    $admin = User::factory()->admin()->create();
    $group = ContentGroup::factory()->create();
    $item = ContentItem::factory()->for($group)->create();
    $media = ownerImageMedia('content-items/images/direct-episode.jpg', [
        'title' => 'Direct episode art',
        'exif' => ['original_filename' => 'Interview final ORIGINAL.JPG'],
    ]);
    app(MediaAttachmentManager::class)->attach(
        $item,
        $media,
        MediaAttachmentRole::PrimaryImage,
        $admin,
    );

    $this->actingAs($admin);
    $presentation = app(OwnerImagePresenter::class)->present(
        $item,
        MediaAttachmentRole::PrimaryImage,
    );

    expect($presentation)
        ->effectiveSource->toBe('direct_media')
        ->brokenDirect->toBeFalse()
        ->expectedMediaId->toBe($media->getKey())
        ->expectedLegacyPath->toBe($media->path)
        ->and($presentation->media)->not->toBeNull()
        ->and($presentation->media['title'])->toBe('Direct episode art')
        ->and($presentation->media['original_filename'])->toBe('Interview final ORIGINAL.JPG')
        ->and($presentation->media['stored_filename'])->toBe('direct-episode.jpg')
        ->and($presentation->media['mime'])->toBe('image/jpeg')
        ->and($presentation->media['extension'])->toBe('jpg')
        ->and($presentation->media['dimensions'])->toBe('640 × 360')
        ->and($presentation->media['file_size'])->toBeString()->not->toBe('')
        ->and($presentation->media['directory'])->toBe('content-items/images')
        ->and($presentation->media['disk'])->toBe('public')
        ->and($presentation->media['reference_key'])->toBe($media->reference_key)
        ->and($presentation->media['preview_url'])->toBe(route('admin.media-files.view', ['media' => $media]))
        ->and($presentation->media['download_url'])->toBe(route('admin.media-files.download', ['media' => $media]))
        ->and($presentation->media['review_url'])->toBe(MediaResource::getUrl('edit', ['record' => $media]))
        ->and($presentation->media['updated_at'])->toMatch('/^\d{2}\/\d{2}\/\d{4} \d{2}:\d{2}$/');
});

it('opens the shared detail workspace from a table thumbnail without row navigation', function (): void {
    $admin = User::factory()->admin()->create();
    $group = ContentGroup::factory()->create();
    $item = ContentItem::factory()->for($group)->create(['title' => 'Keyboard episode']);
    $media = ownerImageMedia('content-items/images/keyboard-episode.jpg', [
        'title' => 'Keyboard artwork',
        'exif' => ['original_filename' => 'Keyboard original.jpg'],
    ]);
    app(MediaAttachmentManager::class)->attach(
        $item,
        $media,
        MediaAttachmentRole::PrimaryImage,
        $admin,
    );

    Livewire::actingAs($admin)
        ->test(ListContentItems::class)
        ->mountAction(TestAction::make('contentItemImageDetails')->table($item))
        ->assertMountedActionModalSee(__('admin.owner_image.heading'))
        ->assertMountedActionModalSee(__('admin.owner_image.sources.direct_media'))
        ->assertMountedActionModalSee('Keyboard original.jpg')
        ->assertMountedActionModalSee('keyboard-episode.jpg')
        ->assertSet('mountedActions.0.data.expected_media_id', $media->getKey())
        ->assertSet('mountedActions.0.data.expected_legacy_path', $media->path);
});

it('reuses the same integrated workspace on podcast edit classic episode edit and episode workspace', function (): void {
    $admin = User::factory()->admin()->create();
    $group = ContentGroup::factory()->create();
    $item = ContentItem::factory()->for($group)->create();
    $cover = ownerImageMedia('content-groups/covers/edit-surface-cover.jpg');
    $episode = ownerImageMedia('content-items/images/edit-surface-episode.jpg');
    $manager = app(MediaAttachmentManager::class);
    $manager->attach($group, $cover, MediaAttachmentRole::Cover, $admin);
    $manager->attach($item, $episode, MediaAttachmentRole::PrimaryImage, $admin);

    foreach ([
        [EditContentGroup::class, $group, 'chooseContentGroupCover'],
        [EditContentItem::class, $item, 'chooseContentItemImage'],
        [EditEpisodeWorkspace::class, $item, 'chooseContentItemImage'],
    ] as [$pageClass, $record, $actionName]) {
        Livewire::actingAs($admin)
            ->test($pageClass, ['record' => $record->getRouteKey()])
            ->mountAction($actionName)
            ->assertMountedActionModalSee(__('admin.owner_image.heading'))
            ->assertMountedActionModalSee(__('admin.owner_image.actions.copy_filename'))
            ->assertHasNoErrors();
    }
});

it('removes only the direct image through the root workspace action', function (): void {
    $admin = User::factory()->admin()->create();
    $group = ContentGroup::factory()->create();
    $media = ownerImageMedia('content-groups/covers/remove-through-workspace.jpg');
    app(MediaAttachmentManager::class)->attach(
        $group,
        $media,
        MediaAttachmentRole::Cover,
        $admin,
    );

    Livewire::actingAs($admin)
        ->test(ListContentGroups::class)
        ->mountAction(TestAction::make('chooseContentGroupCover')->table($group))
        ->callMountedAction(['operation' => 'remove'])
        ->assertHasNoErrors();

    expect($group->coverMediaAttachment()->exists())->toBeFalse()
        ->and($group->refresh()->cover_path)->toBeNull()
        ->and(Media::query()->whereKey($media)->exists())->toBeTrue()
        ->and(Storage::disk('public')->exists($media->path))->toBeTrue();
});

it('queues external image import through the root workspace without attaching early', function (): void {
    Queue::fake();
    $admin = User::factory()->admin()->create();
    $group = ContentGroup::factory()->create();
    $item = ContentItem::factory()->for($group)->create([
        'external_thumbnail_url' => 'https://cdn.example.test/import-me.jpg',
    ]);

    Livewire::actingAs($admin)
        ->test(ListContentItems::class)
        ->mountAction(TestAction::make('contentItemImageDetails')->table($item))
        ->assertMountedActionModalSee(__('admin.owner_image.external_not_media'))
        ->callMountedAction(['operation' => 'import_external'])
        ->assertHasNoErrors();

    Queue::assertPushed(
        DownloadExternalContentItemImage::class,
        fn (DownloadExternalContentItemImage $job): bool => $job->contentItemId === $item->getKey()
            && $job->userId === $admin->getKey()
            && $job->overwrite === false
            && $job->expectedUrl === $item->external_thumbnail_url,
    );

    expect($item->primaryImageMediaAttachment()->exists())->toBeFalse()
        ->and($item->refresh()->image_path)->toBeNull();
});

it('identifies external and inherited effective sources without pretending an external URL is Media', function (): void {
    $admin = User::factory()->admin()->create();
    $cover = ownerImageMedia('content-groups/covers/inherited-cover.jpg', [
        'title' => 'Inherited podcast cover',
    ]);
    $group = ContentGroup::factory()->create();
    app(MediaAttachmentManager::class)->attach(
        $group,
        $cover,
        MediaAttachmentRole::Cover,
        $admin,
    );
    $external = ContentItem::factory()->for($group)->create([
        'external_thumbnail_url' => 'https://cdn.example.test/episode.jpg',
    ]);
    $inherited = ContentItem::factory()->for($group)->create([
        'external_thumbnail_url' => null,
    ]);

    $this->actingAs($admin);
    $externalPresentation = app(OwnerImagePresenter::class)->present(
        $external,
        MediaAttachmentRole::PrimaryImage,
    );
    $inheritedPresentation = app(OwnerImagePresenter::class)->present(
        $inherited,
        MediaAttachmentRole::PrimaryImage,
    );

    expect($externalPresentation)
        ->effectiveSource->toBe('external_url')
        ->media->toBeNull()
        ->canImportExternal->toBeTrue()
        ->and($externalPresentation->effectivePreviewUrl)->toBe('https://cdn.example.test/episode.jpg')
        ->and($inheritedPresentation)
        ->effectiveSource->toBe('inherited_podcast_cover')
        ->canImportExternal->toBeFalse()
        ->and($inheritedPresentation->media['id'])->toBe($cover->getKey())
        ->and($inheritedPresentation->media['title'])->toBe('Inherited podcast cover');
});

it('identifies configured and global default Media sources', function (): void {
    $admin = User::factory()->admin()->create();
    $configured = ownerImageMedia('default-images/configured-group.jpg');
    $global = ownerImageMedia('default-images/global-fallback.jpg');
    $configuredGroup = ContentGroup::factory()->create();

    saveOwnerImageDefaultSettings([
        'content_group' => [
            'mode' => 'custom',
            'path' => $configured->path,
            'media_reference_key' => $configured->reference_key,
        ],
    ]);

    $this->actingAs($admin);
    $configuredPresentation = app(OwnerImagePresenter::class)->present(
        $configuredGroup,
        MediaAttachmentRole::Cover,
    );

    saveOwnerImageDefaultSettings([
        'content_item' => [
            'mode' => 'inherit',
            'path' => null,
            'media_reference_key' => null,
        ],
        'global' => [
            'mode' => 'custom',
            'path' => $global->path,
            'media_reference_key' => $global->reference_key,
        ],
    ]);
    $globalItem = ContentItem::factory()
        ->for(ContentGroup::factory())
        ->create(['external_thumbnail_url' => null]);
    $globalPresentation = app(OwnerImagePresenter::class)->present(
        $globalItem,
        MediaAttachmentRole::PrimaryImage,
    );

    expect($configuredPresentation)
        ->effectiveSource->toBe('configured_default')
        ->and($configuredPresentation->media['id'])->toBe($configured->getKey())
        ->and($globalPresentation)
        ->effectiveSource->toBe('global_default')
        ->and($globalPresentation->media['id'])->toBe($global->getKey());
});

it('identifies an empty automatic fallback without inventing Media metadata', function (): void {
    $item = ContentItem::factory()
        ->for(ContentGroup::factory())
        ->create(['external_thumbnail_url' => null]);

    $presentation = app(OwnerImagePresenter::class)->present(
        $item,
        MediaAttachmentRole::PrimaryImage,
    );

    expect($presentation)
        ->effectiveSource->toBe('none')
        ->effectivePreviewUrl->toBeNull()
        ->media->toBeNull()
        ->brokenDirect->toBeFalse()
        ->canRemoveDirect->toBeFalse();
});

it('shows a broken direct association separately from the effective fallback', function (): void {
    $admin = User::factory()->admin()->create();
    $missing = ownerImageMedia('content-items/images/missing-direct.jpg', [
        'title' => 'Missing direct row file',
    ]);
    $group = ContentGroup::factory()->create();
    $item = ContentItem::factory()->for($group)->create([
        'external_thumbnail_url' => 'https://cdn.example.test/fallback.jpg',
    ]);
    app(MediaAttachmentManager::class)->attach(
        $item,
        $missing,
        MediaAttachmentRole::PrimaryImage,
        $admin,
    );
    Storage::disk('public')->delete($missing->path);

    $this->actingAs($admin);
    $presentation = app(OwnerImagePresenter::class)->present(
        $item,
        MediaAttachmentRole::PrimaryImage,
    );

    expect($presentation)
        ->effectiveSource->toBe('external_url')
        ->effectivePreviewUrl->toBe('https://cdn.example.test/fallback.jpg')
        ->brokenDirect->toBeTrue()
        ->canRemoveDirect->toBeTrue()
        ->and($presentation->warningCodes)->toContain('missing_file')
        ->and($presentation->media['id'])->toBe($missing->getKey())
        ->and($presentation->media['preview_url'])->toBeNull()
        ->and($presentation->media['review_url'])->toBe(MediaResource::getUrl('edit', ['record' => $missing]));
});

it('keeps audience denial and unsafe SVG diagnostics separate from the effective fallback', function (): void {
    $private = ownerImageMedia('private/private-direct.jpg', [
        'disk' => 'local',
        'directory' => 'private',
        'visibility' => 'private',
    ]);
    $svg = ownerImageMedia('content-items/images/unsafe-direct.svg', [
        'type' => 'image/svg+xml',
        'ext' => 'svg',
        'width' => null,
        'height' => null,
    ], store: false);
    Storage::disk('public')->put(
        $svg->path,
        '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"></svg>',
    );

    foreach ([
        [$private, 'audience_denied'],
        [$svg, 'unsanitized_svg'],
    ] as [$media, $warning]) {
        $item = ContentItem::factory()
            ->for(ContentGroup::factory())
            ->create([
                'external_thumbnail_url' => 'https://cdn.example.test/d01-fallback.jpg',
                'image_path' => $media->path,
            ]);
        MediaAttachment::factory()->create([
            'media_id' => $media->getKey(),
            'attachable_type' => 'content_item',
            'attachable_id' => $item->getKey(),
            'role' => MediaAttachmentRole::PrimaryImage,
            'position' => 0,
        ]);

        $presentation = app(OwnerImagePresenter::class)->present(
            $item,
            MediaAttachmentRole::PrimaryImage,
        );

        expect($presentation)
            ->effectiveSource->toBe('external_url')
            ->effectivePreviewUrl->toBe('https://cdn.example.test/d01-fallback.jpg')
            ->brokenDirect->toBeTrue()
            ->and($presentation->warningCodes)->toContain($warning)
            ->and($presentation->media['id'])->toBe($media->getKey())
            ->and($presentation->media['preview_url'])->toBeNull();
    }
});

it('refreshes canonical Media metadata when the detail workspace opens', function (): void {
    $admin = User::factory()->admin()->create();
    $group = ContentGroup::factory()->create();
    $media = ownerImageMedia('content-groups/covers/before-refresh.jpg', [
        'title' => 'Before refresh',
    ]);
    app(MediaAttachmentManager::class)->attach(
        $group,
        $media,
        MediaAttachmentRole::Cover,
        $admin,
    );

    Storage::disk('public')->put('content-groups/covers/after-refresh.jpg', 'updated-image-bytes');
    Storage::disk('public')->delete($media->path);
    $media->forceFill([
        'title' => 'After refresh',
        'name' => 'after-refresh',
        'path' => 'content-groups/covers/after-refresh.jpg',
    ])->saveQuietly();

    $this->actingAs($admin);
    $presentation = app(OwnerImagePresenter::class)->present(
        $group,
        MediaAttachmentRole::Cover,
    );

    expect($presentation)
        ->effectiveSource->toBe('direct_media')
        ->and($presentation->media['title'])->toBe('After refresh')
        ->and($presentation->media['stored_filename'])->toBe('after-refresh.jpg')
        ->and($presentation->media['preview_url'])->toBe(route('admin.media-files.view', ['media' => $media]));
});

it('mounts the shared workspace for a broken direct image while keeping fallback and warning separate', function (): void {
    $admin = User::factory()->admin()->create();
    $missing = ownerImageMedia('content-items/images/action-missing.jpg');
    $group = ContentGroup::factory()->create();
    $item = ContentItem::factory()->for($group)->create([
        'external_thumbnail_url' => 'https://cdn.example.test/action-fallback.jpg',
    ]);
    app(MediaAttachmentManager::class)->attach(
        $item,
        $missing,
        MediaAttachmentRole::PrimaryImage,
        $admin,
    );
    Storage::disk('public')->delete($missing->path);

    Livewire::actingAs($admin)
        ->test(ListContentItems::class)
        ->mountAction(TestAction::make('chooseContentItemImage')->table($item))
        ->assertMountedActionModalSee(__('admin.owner_image.sources.external_url'))
        ->assertMountedActionModalSee(__('admin.owner_image.broken_direct_heading'))
        ->assertMountedActionModalSee(__('admin.owner_image.warnings.missing_file'))
        ->assertMountedActionModalSee('action-missing.jpg')
        ->assertHasNoErrors();
});

it('mounts and detaches an attachment whose Media row disappeared', function (): void {
    $admin = User::factory()->admin()->create();
    $missing = ownerImageMedia('content-items/images/missing-media-row.jpg');
    $group = ContentGroup::factory()->create();
    $item = ContentItem::factory()->for($group)->create([
        'external_thumbnail_url' => 'https://cdn.example.test/missing-row-fallback.jpg',
    ]);
    app(MediaAttachmentManager::class)->attach(
        $item,
        $missing,
        MediaAttachmentRole::PrimaryImage,
        $admin,
    );

    DB::statement('PRAGMA defer_foreign_keys = ON');
    DB::table('curator')->where('id', $missing->getKey())->delete();

    Livewire::actingAs($admin)
        ->test(ListContentItems::class)
        ->mountAction(TestAction::make('chooseContentItemImage')->table($item))
        ->assertMountedActionModalSee(__('admin.owner_image.sources.external_url'))
        ->assertMountedActionModalSee(__('admin.owner_image.broken_direct_heading'))
        ->assertMountedActionModalSee(__('admin.owner_image.warnings.missing_attachment_media'))
        ->callMountedAction(['operation' => 'remove'])
        ->assertHasNoErrors();

    expect($item->primaryImageMediaAttachment()->exists())->toBeFalse()
        ->and($item->refresh()->image_path)->toBeNull();
});

it('rejects a stale normal replacement and preserves the newer owner image', function (): void {
    $admin = User::factory()->admin()->create();
    $group = ContentGroup::factory()->create();
    $current = ownerImageMedia('content-groups/covers/current.jpg');
    $newer = ownerImageMedia('content-groups/covers/newer.jpg');
    $staleTarget = ownerImageMedia('content-groups/covers/stale-target.jpg');
    $manager = app(MediaAttachmentManager::class);
    $manager->attach($group, $current, MediaAttachmentRole::Cover, $admin);

    $this->actingAs($admin);
    $snapshot = app(OwnerImagePresenter::class)->present($group, MediaAttachmentRole::Cover);
    $manager->attach($group, $newer, MediaAttachmentRole::Cover, $admin);

    expect(fn () => app(MediaAttachmentFormState::class)->persist(
        $group,
        $staleTarget->reference_key,
        MediaAttachmentRole::Cover,
        $admin,
        expectedMediaId: $snapshot->expectedMediaId,
        expectedLegacyPath: $snapshot->expectedLegacyPath,
        enforceExpectedIdentity: true,
    ))->toThrow(ValidationException::class);

    expect($group->coverMediaAttachment()->value('media_id'))->toBe($newer->getKey())
        ->and($group->refresh()->cover_path)->toBe($newer->path);
});

it('rejects a stale direct removal and preserves the newer owner image', function (): void {
    $admin = User::factory()->admin()->create();
    $group = ContentGroup::factory()->create();
    $current = ownerImageMedia('content-groups/covers/remove-current.jpg');
    $newer = ownerImageMedia('content-groups/covers/remove-newer.jpg');
    $manager = app(MediaAttachmentManager::class);
    $manager->attach($group, $current, MediaAttachmentRole::Cover, $admin);

    $this->actingAs($admin);
    $snapshot = app(OwnerImagePresenter::class)->present($group, MediaAttachmentRole::Cover);
    $manager->attach($group, $newer, MediaAttachmentRole::Cover, $admin);

    expect(fn () => app(MediaAttachmentFormState::class)->detachDirectIfUnchanged(
        $group,
        MediaAttachmentRole::Cover,
        $admin,
        $snapshot->expectedMediaId,
        $snapshot->expectedLegacyPath,
    ))->toThrow(ValidationException::class);

    expect($group->coverMediaAttachment()->value('media_id'))->toBe($newer->getKey())
        ->and($group->refresh()->cover_path)->toBe($newer->path);
});

it('ships the Package 4 workspace labels and feedback in Hebrew and English', function (): void {
    $en = require lang_path('en/admin.php');
    $he = require lang_path('he/admin.php');

    foreach ([
        'owner_image.heading',
        'owner_image.sources.direct_media',
        'owner_image.sources.inherited_podcast_cover',
        'owner_image.sources.external_url',
        'owner_image.sources.configured_default',
        'owner_image.broken_direct_body',
        'owner_image.metadata.original_filename',
        'owner_image.metadata.stored_filename',
        'owner_image.actions.change_image',
        'owner_image.actions.use_automatic_image',
        'owner_image.actions.copy_filename',
        'owner_image.copy_success',
        'validation.owner_image_changed',
    ] as $key) {
        expect(data_get($en, $key))->toBeString()->not->toBe('')
            ->and(data_get($he, $key))->toBeString()->not->toBe('');
    }
});
