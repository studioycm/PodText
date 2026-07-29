<?php

use App\Enums\ExternalImageFailureReason;
use App\Enums\ImageUploadPurpose;
use App\Enums\MediaAcquisitionDisposition;
use App\Enums\MediaAttachmentRole;
use App\Enums\UserRole;
use App\Filament\Forms\Components\PathCuratorPicker;
use App\Filament\Resources\Media\MediaResource;
use App\Livewire\Admin\DisabledVendorCuratorSurface;
use App\Livewire\Admin\MediaPickerPanel;
use App\Models\ContentGroup;
use App\Models\Media;
use App\Models\MediaAsset;
use App\Models\MediaAttachment;
use App\Models\MediaProviderBinding;
use App\Models\User;
use App\Settings\AdminUxSettings;
use App\Support\Media\ExternalImageRejectedException;
use App\Support\Media\ExternalImageUnavailableException;
use App\Support\Media\MediaAcquisitionManager;
use App\Support\Media\MediaAcquisitionResult;
use App\Support\Media\SafeExternalImageFetcher;
use App\Support\Media\StorageImageCandidateBrowser;
use Filament\Actions\Testing\TestAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Http::preventStrayRequests();
    Storage::fake('local');
    Storage::fake('public');
    Storage::fake('public_assets');
});

function pickerPanel(array $overrides = []): Testable
{
    return Livewire::test(MediaPickerPanel::class, array_merge([
        'purpose' => ImageUploadPurpose::ContentGroupCover->value,
        'selectedIds' => [],
        'isMultiple' => false,
        'maxItems' => 1,
    ], $overrides));
}

function pickerMediaFixture(string $filename): UploadedFile
{
    $encoded = file_get_contents(base_path("tests/Fixtures/media/{$filename}.base64"));

    return UploadedFile::fake()->createWithContent(
        $filename,
        base64_decode((string) $encoded, true),
    );
}

it('requires an admin actor at the picker server boundary', function (): void {
    pickerPanel()->assertForbidden();

    $this->actingAs(User::factory()->role(UserRole::Moderator)->create());
    pickerPanel()->assertForbidden();

    $this->actingAs(User::factory()->admin()->create());
    pickerPanel()->assertOk();
});

it('replaces the globally registered vendor picker and curation aliases with fail closed surfaces', function (): void {
    Livewire::test('curator-panel')->assertNotFound();
    Livewire::test('curator-curation')->assertNotFound();

    expect(app('livewire.finder')->resolveClassComponentClassName('curator-panel'))->toBe(DisabledVendorCuratorSurface::class)
        ->and(app('livewire.finder')->resolveClassComponentClassName('curator-curation'))->toBe(DisabledVendorCuratorSurface::class);
});

it('locks the server-owned purpose against livewire tampering', function (): void {
    $this->actingAs(User::factory()->admin()->create());

    expect(fn () => pickerPanel()->set('purpose', ImageUploadPurpose::HeaderLogo->value))
        ->toThrow(CannotUpdateLockedPropertyException::class);
});

it('starts owner choice on All Media and Gallery without a Current folder concept', function (): void {
    $this->actingAs(User::factory()->admin()->create());

    pickerPanel([
        'isOwnerChoice' => true,
        'savedMediaId' => null,
    ])
        ->assertSet('allMedia', true)
        ->assertSet('activeSource', 'gallery')
        ->assertSee('data-testid="media-picker-owner-source-navigation"', false)
        ->assertSee('data-testid="media-picker-source-gallery"', false)
        ->assertSee('data-testid="media-picker-source-upload"', false)
        ->assertSee('data-testid="media-picker-source-url"', false)
        ->assertSee('data-testid="media-picker-source-storage"', false)
        ->assertDontSee(__('admin.media_library.context_media'));
});

it('rejects the hidden Current folder transition in owner choice mode', function (): void {
    $this->actingAs(User::factory()->admin()->create());

    pickerPanel([
        'isOwnerChoice' => true,
        'savedMediaId' => null,
    ])
        ->call('showContextMedia')
        ->assertStatus(422);
});

it('locks the owner All Media scope against client tampering', function (): void {
    $this->actingAs(User::factory()->admin()->create());

    expect(fn () => pickerPanel([
        'isOwnerChoice' => true,
        'savedMediaId' => null,
    ])->set('allMedia', false))
        ->toThrow(CannotUpdateLockedPropertyException::class);
});

it('keeps owner search inventory wide after server-side scope drift', function (): void {
    $this->actingAs(User::factory()->admin()->create());
    $crossFolder = Media::factory()->create([
        'directory' => 'header',
        'title' => 'Cross-folder owner image',
    ]);
    $component = pickerPanel([
        'isOwnerChoice' => true,
        'savedMediaId' => null,
    ]);
    $instance = $component->instance();

    $instance->allMedia = false;
    $instance->search = 'Cross-folder owner';
    $instance->updatedSearch();

    expect(collect($instance->files)->pluck('id')->all())
        ->toContain($crossFolder->getKey());
});

it('dispatches an owner Gallery tile immediately as the one pending selection', function (bool $inline): void {
    $this->actingAs(User::factory()->admin()->create());
    $saved = Media::factory()->create();
    $replacement = Media::factory()->create();
    $original = $replacement->only(['path', 'disk', 'visibility']);
    Storage::disk($replacement->disk)->put($replacement->path, 'replacement fixture');

    $component = pickerPanel([
        'selectedIds' => [$saved->getKey()],
        'isInlineOwnerWorkspace' => $inline,
        'isOwnerChoice' => true,
        'savedMediaId' => $saved->getKey(),
    ])
        ->assertSet('selectedIds', [$saved->getKey()])
        ->call('toggleSelection', $replacement->getKey());

    $component
        ->assertSet('selectedIds', [$replacement->getKey()])
        ->assertDispatched('insert-media', fn (string $event, array $parameters): bool => $parameters === [
            'mediaId' => $replacement->getKey(),
            'mediaIds' => [$replacement->getKey()],
        ])
        ->assertNotDispatched('close-media-picker');

    expect($replacement->refresh()->only(array_keys($original)))->toBe($original);
})->with([
    'nested field' => [false],
    'inline dedicated action' => [true],
]);

it('renders the selected owner tile as a disabled localized current image', function (string $locale): void {
    app()->setLocale($locale);
    $this->actingAs(User::factory()->admin()->create());
    $selected = Media::factory()->create(['title' => 'Accessible owner image']);
    Storage::disk($selected->disk)->put($selected->path, 'selected owner fixture');
    $component = pickerPanel([
        'selectedIds' => [$selected->getKey()],
        'isOwnerChoice' => true,
        'savedMediaId' => $selected->getKey(),
    ]);
    $selectionLabel = __('admin.media_library.current_image').': '.$selected->title;
    $html = $component->html();
    preg_match(
        '/<button[^>]*aria-label="'.preg_quote($selectionLabel, '/').'"[^>]*>/',
        $html,
        $buttonMatches,
    );

    expect($buttonMatches)->toHaveCount(1)
        ->and($buttonMatches[0])->toContain('aria-pressed="true"')
        ->and($buttonMatches[0])->toContain('aria-disabled="true"')
        ->and($buttonMatches[0])->toContain('disabled')
        ->and($html)->not->toContain(__('admin.media_library.deselect_image', ['name' => $selected->title]));

    $component
        ->call('toggleSelection', $selected->getKey())
        ->assertSet('selectedIds', [$selected->getKey()])
        ->assertNotDispatched('insert-media');
})->with([
    'Hebrew' => ['he'],
    'English' => ['en'],
]);

it('ignores hostile clear selection calls in every owner mode', function (bool $inline): void {
    $this->actingAs(User::factory()->admin()->create());
    $selected = Media::factory()->create();

    pickerPanel([
        'selectedIds' => [$selected->getKey()],
        'isInlineOwnerWorkspace' => $inline,
        'isOwnerChoice' => true,
        'savedMediaId' => $selected->getKey(),
    ])
        ->call('clearSelection')
        ->assertSet('selectedIds', [$selected->getKey()]);
})->with([
    'nested field' => [false],
    'inline dedicated action' => [true],
]);

it('restores owner pending selection from the locked saved identity without mutation', function (): void {
    $this->actingAs(User::factory()->admin()->create());
    $saved = Media::factory()->create();
    $pending = Media::factory()->create();
    $forged = Media::factory()->create();
    $savedIdentity = $saved->only(['path', 'disk', 'visibility']);

    pickerPanel([
        'selectedIds' => [$pending->getKey()],
        'isOwnerChoice' => true,
        'savedMediaId' => $saved->getKey(),
    ])
        ->dispatch('owner-media-selection-restored', selectedId: $forged->getKey())
        ->assertSet('selectedIds', [$saved->getKey()])
        ->assertNotDispatched('insert-media');

    expect($saved->refresh()->only(array_keys($savedIdentity)))->toBe($savedIdentity);
});

it('synchronizes owner pending selection changes through trusted records', function (): void {
    $this->actingAs(User::factory()->admin()->create());
    $saved = Media::factory()->create();
    $replacement = Media::factory()->create();
    Storage::disk($replacement->disk)->put($replacement->path, 'replacement fixture');

    pickerPanel([
        'selectedIds' => [$saved->getKey()],
        'isOwnerChoice' => true,
        'savedMediaId' => $saved->getKey(),
    ])
        ->dispatch('owner-media-selection-changed', selectedId: null)
        ->assertSet('selectedIds', [])
        ->dispatch('owner-media-selection-changed', selectedId: $replacement->getKey())
        ->assertSet('selectedIds', [$replacement->getKey()])
        ->assertNotDispatched('insert-media');
});

it('shows only one authorized details slide-over trigger on each owner Gallery card', function (): void {
    $this->actingAs(User::factory()->admin()->create());
    $media = Media::factory()->create(['title' => 'Owner details card']);

    $component = pickerPanel([
        'isOwnerChoice' => true,
        'savedMediaId' => null,
    ])
        ->assertActionHidden(TestAction::make('viewItem'))
        ->assertActionHidden(TestAction::make('downloadItem'))
        ->assertActionHidden(TestAction::make('editItem'))
        ->assertActionHidden(TestAction::make('renameItem'))
        ->assertActionHidden(TestAction::make('swapItem'))
        ->assertActionHidden(TestAction::make('destroyItem'))
        ->assertActionHidden(TestAction::make('destroySelected'))
        ->assertActionHidden(TestAction::make('insertMedia'));
    $html = $component->html();

    expect(substr_count($html, 'data-testid="media-picker-owner-details-'.$media->getKey().'"'))->toBe(1)
        ->and($html)->toContain("mountAction('mediaDetails', { id: ".$media->getKey().' })')
        ->and($html)->not->toContain('href="'.e(MediaResource::getUrl('edit', ['record' => $media])).'"')
        ->and($html)->not->toContain(__('admin.media_library.use_selected'))
        ->and($html)->not->toContain(__('admin.media_library.delete_selected'));
});

it('omits owner card Details when current Media update authorization is denied', function (): void {
    $this->actingAs(User::factory()->admin()->create());
    $media = Media::factory()->create();
    Gate::before(
        static fn (User $user, string $ability): ?bool => $ability === 'update' ? false : null,
    );

    pickerPanel([
        'isOwnerChoice' => true,
        'savedMediaId' => null,
    ])->assertDontSee('data-testid="media-picker-owner-details-'.$media->getKey().'"', false);
});

it('locks owner choice workflow and saved identity against livewire tampering', function (): void {
    $this->actingAs(User::factory()->admin()->create());
    $saved = Media::factory()->create();

    expect(fn () => pickerPanel([
        'isOwnerChoice' => true,
        'savedMediaId' => $saved->getKey(),
    ])->set('isOwnerChoice', false))
        ->toThrow(CannotUpdateLockedPropertyException::class)
        ->and(fn () => pickerPanel([
            'isOwnerChoice' => true,
            'savedMediaId' => $saved->getKey(),
        ])->set('savedMediaId', null))
        ->toThrow(CannotUpdateLockedPropertyException::class);
});

it('keeps owner source navigation responsive at two narrow and five desktop Gallery columns', function (): void {
    $this->actingAs(User::factory()->admin()->create());

    pickerPanel([
        'isOwnerChoice' => true,
        'savedMediaId' => null,
    ])
        ->assertSee('grid grid-cols-2 gap-3', false)
        ->assertSee('xl:grid-cols-5', false)
        ->assertDontSee('xl:grid-cols-6', false)
        ->call('activateSource', 'upload')
        ->assertSet('activeSource', 'upload')
        ->assertSee(rtrim(Str::before(__('admin.media_library.acquisition_permanence'), ':link')));
});

it('makes one successful owner admission pending while keeping the Media permanent', function (): void {
    $this->actingAs(User::factory()->admin()->create());

    $component = pickerPanel([
        'isOwnerChoice' => true,
        'savedMediaId' => null,
    ])
        ->call('activateSource', 'upload')
        ->fillForm(['uploads' => [pickerMediaFixture('valid.jpg')]])
        ->callAction(TestAction::make('uploadFiles'))
        ->assertHasNoFormErrors()
        ->assertDispatched('insert-media');

    $media = Media::query()->sole();

    expect($component->get('selectedIds'))->toBe([$media->getKey()]);
    Storage::disk($media->disk)->assertExists($media->path);
});

it('keeps every owner batch admission permanent without choosing an arbitrary pending image', function (): void {
    $this->actingAs(User::factory()->admin()->create());

    $component = pickerPanel([
        'isInlineOwnerWorkspace' => true,
        'isOwnerChoice' => true,
        'savedMediaId' => null,
    ])
        ->call('activateSource', 'upload')
        ->fillForm([
            'uploads' => [
                pickerMediaFixture('valid.jpg'),
                pickerMediaFixture('valid.png'),
            ],
        ])
        ->callAction(TestAction::make('uploadFiles'))
        ->assertHasNoFormErrors()
        ->assertNotDispatched('insert-media')
        ->assertSee(rtrim(Str::before(__('admin.media_library.acquisition_permanence_inline'), ':link')));

    expect($component->get('selectedIds'))->toBe([])
        ->and(Media::query()->count())->toBe(2);

    foreach (Media::query()->get() as $media) {
        Storage::disk($media->disk)->assertExists($media->path);
    }
});

it('bounds browse payloads while treating the purpose root as an initial logical folder', function (): void {
    $this->actingAs(User::factory()->admin()->create());
    $contextMedia = Media::factory()->count(30)->create();
    $otherFolder = Media::factory()->create([
        'directory' => 'header',
        'path' => 'header/'.Str::ulid().'.jpg',
    ]);

    $component = pickerPanel();
    $files = $component->get('files');

    expect($files)->toHaveCount(25)
        ->and(array_keys($files[0]))->toBe([
            'id',
            'reference_key',
            'pretty_name',
            'alt',
            'ext',
            'size',
            'width',
            'height',
            'preview_url',
            'needs_repair',
            'repair_reasons',
            'selectable',
            'selection_blocked_reason',
            'review_url',
            'ops',
        ])
        ->and(array_column($files, 'id'))->each->toBeIn($contextMedia->modelKeys())
        ->and(array_column($files, 'id'))->not->toContain($otherFolder->getKey());

    $component->call('loadMoreFiles');

    expect($component->get('files'))->toHaveCount(5)
        ->and(array_column($component->get('files'), 'id'))->each->toBeIn($contextMedia->modelKeys());

    $component->call('loadPreviousFiles');

    expect($component->get('files'))->toHaveCount(25);

    $component->call('showAllMedia');

    expect($component->get('allMedia'))->toBeTrue()
        ->and(array_column($component->get('files'), 'id'))->toContain($otherFolder->getKey());

    $component->call('showContextMedia');

    expect($component->get('allMedia'))->toBeFalse()
        ->and(array_column($component->get('files'), 'id'))->not->toContain($otherFolder->getKey());
});

it('bounds picker uploads and concurrent transfers', function (): void {
    $this->actingAs(User::factory()->admin()->create());
    $single = pickerPanel();
    $singleUpload = collect($single->instance()->getSchema('form')->getFlatComponents(withHidden: true))
        ->first(fn (mixed $field): bool => $field instanceof FileUpload && $field->getName() === 'uploads');
    $multiple = pickerPanel([
        'isMultiple' => true,
        'maxItems' => 10,
    ]);
    $multipleUpload = collect($multiple->instance()->getSchema('form')->getFlatComponents(withHidden: true))
        ->first(fn (mixed $field): bool => $field instanceof FileUpload && $field->getName() === 'uploads');
    $inlineSelection = Media::factory()->create();
    $inline = pickerPanel([
        'selectedIds' => [$inlineSelection->getKey()],
        'isInlineOwnerWorkspace' => true,
    ])
        ->assertSee(__('admin.media_library.inline_batch_help'))
        ->assertSee(__('admin.media_library.upload_one_or_multiple'))
        ->assertDontSee('data-testid="media-picker-close"', false)
        ->assertDontSee(__('admin.media_library.delete_selected'))
        ->assertActionHidden(TestAction::make('destroySelected'));
    $inlineUpload = collect($inline->instance()->getSchema('form')->getFlatComponents(withHidden: true))
        ->first(fn (mixed $field): bool => $field instanceof FileUpload && $field->getName() === 'uploads');

    assert($singleUpload instanceof FileUpload);
    assert($multipleUpload instanceof FileUpload);
    assert($inlineUpload instanceof FileUpload);

    expect($singleUpload)->toBeInstanceOf(FileUpload::class)
        ->and($singleUpload->isMultiple())->toBeFalse()
        ->and($singleUpload->getMaxFiles())->toBe(1)
        ->and($singleUpload->getMaxParallelUploads())->toBe(2)
        ->and($multipleUpload)->toBeInstanceOf(FileUpload::class)
        ->and($multipleUpload->isMultiple())->toBeTrue()
        ->and($multipleUpload->getMaxFiles())->toBe(40)
        ->and($multipleUpload->getMaxParallelUploads())->toBe(2)
        ->and($inlineUpload->isMultiple())->toBeTrue()
        ->and($inlineUpload->getMaxFiles())->toBe(40)
        ->and($inlineUpload->getMaxParallelUploads())->toBe(2);
});

it('uses bounded Admin UX batch browse and search settings for new picker work', function (): void {
    $this->actingAs(User::factory()->admin()->create());
    $settings = app(AdminUxSettings::class);
    $settings->media_acquisition_max_kilobytes = 4096;
    $settings->media_acquisition_upload_batch_limit = 3;
    $settings->media_upload_queue_limit = 7;
    $settings->media_picker_browse_limit = 12;
    $settings->media_picker_search_limit = 11;
    $settings->save();
    Media::factory()->count(20)->create(['title' => 'Configured search']);

    $component = pickerPanel([
        'isMultiple' => true,
        'maxItems' => 5,
    ]);
    $upload = collect($component->instance()->getSchema('form')->getFlatComponents(withHidden: true))
        ->first(fn (mixed $field): bool => $field instanceof FileUpload && $field->getName() === 'uploads');

    assert($upload instanceof FileUpload);

    expect($component->get('files'))->toHaveCount(12)
        ->and($upload)->toBeInstanceOf(FileUpload::class)
        ->and($upload->getMaxFiles())->toBe(7)
        ->and($upload->getMaxSize())->toBe(4096);

    $component->set('search', 'Configured');

    expect($component->get('files'))->toHaveCount(11);
});

it('shows Gallery Upload URL and Storage in one shared picker with permanence help', function (): void {
    $this->actingAs(User::factory()->admin()->create());

    pickerPanel()
        ->assertSee(__('admin.media_library.gallery_source'))
        ->assertSee(__('admin.media_library.upload_source'))
        ->assertSee(__('admin.media_library.url_source'))
        ->assertSee(__('admin.media_library.storage_source'))
        ->assertSee(rtrim(Str::before(__('admin.media_library.acquisition_permanence'), ':link')));
});

it('loads Storage only on first activation and explicit refresh', function (): void {
    $this->actingAs(User::factory()->admin()->create());
    $browser = Mockery::mock(StorageImageCandidateBrowser::class);
    $browser->shouldReceive('hasConfiguredSources')->once()->andReturnTrue();
    $browser->shouldReceive('browse')
        ->once()
        ->with('')
        ->andReturn([[
            'token' => 'first-token',
            'filename' => 'first.jpg',
            'source' => 'Public imports',
        ]]);
    $browser->shouldReceive('browse')
        ->twice()
        ->with('first')
        ->andReturn(
            [[
                'token' => 'searched-token',
                'filename' => 'first.jpg',
                'source' => 'Public imports',
            ]],
            [[
                'token' => 'refreshed-token',
                'filename' => 'refreshed.jpg',
                'source' => 'Public imports',
            ]],
        );
    app()->instance(StorageImageCandidateBrowser::class, $browser);

    $component = pickerPanel()
        ->assertSet('activeSource', 'upload')
        ->assertSet('storageLoaded', false)
        ->assertSet('storageFiles', [])
        ->call('activateSource', 'storage')
        ->assertSet('activeSource', 'storage')
        ->assertSet('storageLoaded', true)
        ->assertSet('storageFiles.0.filename', 'first.jpg')
        ->set('storageSearch', 'first')
        ->assertSet('storageFiles.0.token', 'searched-token')
        ->assertSee('first.jpg')
        ->call('activateSource', 'url')
        ->call('activateSource', 'storage')
        ->assertSet('storageFiles.0.filename', 'first.jpg')
        ->call('refreshStorageFiles')
        ->assertSet('storageFiles.0.filename', 'refreshed.jpg')
        ->assertSet('storageSearch', 'first');
});

it('uses a 1500 millisecond server-backed Storage search binding', function (): void {
    $this->actingAs(User::factory()->admin()->create());
    $browser = Mockery::mock(StorageImageCandidateBrowser::class);
    $browser->shouldReceive('hasConfiguredSources')->once()->andReturnTrue();
    $browser->shouldReceive('browse')->once()->with('')->andReturn([]);
    $browser->shouldReceive('browse')->once()->with('nested cover')->andReturn([[
        'token' => 'nested-token',
        'filename' => 'nested-cover.jpg',
        'source' => 'Laravel public disk',
    ]]);
    app()->instance(StorageImageCandidateBrowser::class, $browser);

    pickerPanel()
        ->call('activateSource', 'storage')
        ->set('storageSearch', '  nested cover  ')
        ->assertSet('storageSearch', 'nested cover')
        ->assertSet('storageFiles.0.filename', 'nested-cover.jpg')
        ->assertSee('wire:model.live.debounce.1500ms="storageSearch"', false);
});

it('locks source and Storage-load authority against direct Livewire tampering', function (): void {
    $this->actingAs(User::factory()->admin()->create());

    expect(fn () => pickerPanel()->set('activeSource', 'storage'))
        ->toThrow(CannotUpdateLockedPropertyException::class);
    expect(fn () => pickerPanel()->set('storageLoaded', true))
        ->toThrow(CannotUpdateLockedPropertyException::class);
    expect(fn () => pickerPanel()->set('storageConfigured', false))
        ->toThrow(CannotUpdateLockedPropertyException::class);
    expect(fn () => pickerPanel()->set('isInlineOwnerWorkspace', true))
        ->toThrow(CannotUpdateLockedPropertyException::class);
});

it('preserves Upload and URL state while changing validated source tabs', function (): void {
    $this->actingAs(User::factory()->admin()->create());

    $component = pickerPanel()
        ->fillForm([
            'uploads' => [pickerMediaFixture('valid.jpg')],
            'external_url' => 'https://cdn.example.test/preserved.png',
        ])
        ->call('activateSource', 'url')
        ->assertSet('activeSource', 'url')
        ->call('activateSource', 'upload')
        ->assertSet('activeSource', 'upload');

    expect(data_get($component->get('panelData'), 'uploads'))->toHaveCount(1)
        ->and(data_get($component->get('panelData'), 'external_url'))
        ->toBe('https://cdn.example.test/preserved.png');

    pickerPanel()
        ->call('activateSource', 'forged')
        ->assertStatus(422);
});

it('validates only the active source while preserving inactive source state', function (): void {
    $this->actingAs(User::factory()->admin()->create());

    $component = pickerPanel()
        ->fillForm([
            'uploads' => [pickerMediaFixture('valid.jpg')],
            'external_url' => 'not-a-valid-url',
        ])
        ->call('activateSource', 'upload')
        ->callAction(TestAction::make('uploadFiles'))
        ->assertHasNoFormErrors();

    expect(Media::query()->count())->toBe(1)
        ->and(data_get($component->get('panelData'), 'external_url'))
        ->toBe('not-a-valid-url');

    $fetcher = Mockery::mock(SafeExternalImageFetcher::class);
    $fetcher->shouldReceive('fetch')
        ->once()
        ->with('https://cdn.example.test/source-local.png')
        ->andReturn(base64_decode((string) file_get_contents(base_path('tests/Fixtures/media/valid.png.base64')), true));
    app()->instance(SafeExternalImageFetcher::class, $fetcher);

    $urlComponent = pickerPanel()
        ->fillForm([
            'uploads' => [UploadedFile::fake()->createWithContent('forged.jpg', '<?php echo 1;')],
            'external_url' => 'https://cdn.example.test/source-local.png',
        ])
        ->call('activateSource', 'url')
        ->callAction(TestAction::make('acquireUrl'))
        ->assertHasNoFormErrors();

    expect(Media::query()->count())->toBe(2)
        ->and(data_get($urlComponent->get('panelData'), 'uploads'))
        ->toHaveCount(1);
});

it('keeps acquisition actions visible but disabled until their source has input', function (): void {
    $this->actingAs(User::factory()->admin()->create());

    pickerPanel()
        ->assertActionVisible(TestAction::make('uploadFiles'))
        ->assertActionDisabled(TestAction::make('uploadFiles'))
        ->assertActionVisible(TestAction::make('acquireUrl'))
        ->assertActionDisabled(TestAction::make('acquireUrl'))
        ->fillForm(['uploads' => [pickerMediaFixture('valid.jpg')]])
        ->assertActionEnabled(TestAction::make('uploadFiles'))
        ->fillForm(['external_url' => 'https://cdn.example.test/image.png'])
        ->assertActionEnabled(TestAction::make('acquireUrl'));
});

it('rejects Upload URL and Storage acquisition before permanent work when a multi picker is full', function (): void {
    $this->actingAs(User::factory()->admin()->create());
    $selected = Media::factory()->create([
        'directory' => ImageUploadPurpose::ContentGroupCover->root(),
    ]);
    assert($selected instanceof Media);

    $uploadAcquisitions = Mockery::mock(MediaAcquisitionManager::class);
    $uploadAcquisitions->shouldReceive('acquireUploads')->never();
    app()->instance(MediaAcquisitionManager::class, $uploadAcquisitions);

    pickerPanel([
        'selectedIds' => [$selected->getKey()],
        'isMultiple' => true,
        'maxItems' => 1,
    ])
        ->fillForm(['uploads' => [pickerMediaFixture('valid.jpg')]])
        ->callAction(TestAction::make('uploadFiles'))
        ->assertHasFormErrors(['uploads'])
        ->assertSee(__('admin.media_library.selection_limit_exceeded'));

    expect(Media::query()->count())->toBe(1);

    app()->forgetInstance(MediaAcquisitionManager::class);
    $urlAcquisitionCalled = false;
    $urlAcquisitions = Mockery::mock(MediaAcquisitionManager::class);
    $urlAcquisitions->shouldReceive('acquireExternalUrl')
        ->zeroOrMoreTimes()
        ->andReturnUsing(function () use (&$urlAcquisitionCalled, $selected): Media {
            $urlAcquisitionCalled = true;

            return $selected;
        });
    app()->instance(MediaAcquisitionManager::class, $urlAcquisitions);

    pickerPanel([
        'selectedIds' => [$selected->getKey()],
        'isMultiple' => true,
        'maxItems' => 1,
    ])
        ->call('activateSource', 'url')
        ->fillForm(['external_url' => 'https://cdn.example.test/not-fetched.png'])
        ->callAction(TestAction::make('acquireUrl'))
        ->assertHasFormErrors(['external_url'])
        ->assertSee(__('admin.media_library.acquisition_selection_limit_exceeded'));

    expect($urlAcquisitionCalled)->toBeFalse();

    app()->forgetInstance(MediaAcquisitionManager::class);
    Storage::disk('public')->put(
        'media-imports/not-registered.jpg',
        base64_decode((string) file_get_contents(base_path('tests/Fixtures/media/valid.jpg.base64')), true),
    );
    $storageComponent = pickerPanel([
        'selectedIds' => [$selected->getKey()],
        'isMultiple' => true,
        'maxItems' => 1,
    ])->call('activateSource', 'storage');
    $candidate = $storageComponent->get('storageFiles')[0];
    $storageAcquisitionCalled = false;
    $storageAcquisitions = Mockery::mock(MediaAcquisitionManager::class);
    $storageAcquisitions->shouldReceive('acquireStorageCandidate')
        ->zeroOrMoreTimes()
        ->andReturnUsing(function () use (&$storageAcquisitionCalled, $selected): MediaAcquisitionResult {
            $storageAcquisitionCalled = true;

            return new MediaAcquisitionResult(
                media: $selected,
                disposition: MediaAcquisitionDisposition::Reused,
            );
        });
    app()->instance(MediaAcquisitionManager::class, $storageAcquisitions);

    $storageComponent
        ->callAction(
            TestAction::make('acquireStorage'),
            arguments: ['token' => $candidate['token']],
        )
        ->assertHasErrors(['storageAcquisition'])
        ->assertSee(__('admin.media_library.acquisition_selection_limit_exceeded'));

    expect($storageAcquisitionCalled)->toBeFalse()
        ->and(Media::query()->count())->toBe(1);
    Storage::disk('public')->assertExists('media-imports/not-registered.jpg');
});

it('renders distinct Storage configuration candidate and search empty states', function (): void {
    $this->actingAs(User::factory()->admin()->create());

    config()->set('media.acquisition.storage_sources', []);
    pickerPanel()
        ->call('activateSource', 'storage')
        ->assertSee(__('admin.media_library.storage_unconfigured'))
        ->assertDontSee('data-testid="media-picker-storage-search"', false)
        ->assertDontSee('data-testid="media-picker-storage-refresh"', false);

    config()->set('media.acquisition.storage_sources', [
        'public_imports' => [
            'disk' => 'public',
            'root' => 'media-imports',
            'mode' => 'register',
            'label' => ['en' => 'Public imports', 'he' => 'ייבוא ציבורי'],
        ],
    ]);
    pickerPanel()
        ->call('activateSource', 'storage')
        ->assertSee(__('admin.media_library.storage_empty'));
    pickerPanel()
        ->call('activateSource', 'storage')
        ->set('storageSearch', 'missing')
        ->assertSee(__('admin.media_library.storage_search_empty'));
});

it('disables implicit picker dismissal while retaining the explicit close route', function (): void {
    $pickerAction = PathCuratorPicker::make('image')
        ->purpose(ImageUploadPurpose::ContentGroupCover)
        ->getPickerAction();

    expect($pickerAction->isModalClosedByClickingAway())->toBeFalse()
        ->and($pickerAction->isModalClosedByEscaping())->toBeFalse()
        ->and($pickerAction->hasModalCloseButton())->toBeFalse()
        ->and($pickerAction->getModalSubmitAction())->toBeNull()
        ->and($pickerAction->getModalCancelAction())->toBeNull()
        ->and($pickerAction->hasFormWrapper())->toBeFalse();
});

it('renders source navigation loading offline and explicit-close hooks', function (): void {
    $this->actingAs(User::factory()->admin()->create());

    pickerPanel()
        ->assertSee('data-testid="media-picker-source-navigation"', false)
        ->assertSee('data-testid="media-picker-close"', false)
        ->assertSee('wire:loading.attr="inert"', false)
        ->assertSee('wire:offline', false)
        ->assertSee('livewire-upload-start', false)
        ->assertSee('x-bind:disabled="uploading"', false)
        ->assertSee(__('admin.media_library.working'))
        ->assertSee(__('admin.media_library.offline'));
});

it('renders localized accessible gallery controls and touch-visible item actions', function (string $locale): void {
    app()->setLocale($locale);
    $this->actingAs(User::factory()->admin()->create());
    $directory = ImageUploadPurpose::ContentGroupCover->root();
    $selected = Media::factory()->create([
        'directory' => $directory,
        'title' => 'Accessible selected image',
    ]);
    $blocked = Media::factory()->create([
        'directory' => $directory,
        'title' => 'Accessible blocked image',
        'visibility' => 'private',
    ]);

    pickerPanel(['selectedIds' => [$selected->getKey()]])
        ->call('activateSource', 'storage')
        ->assertSee(__('admin.media_library.gallery_search_label'))
        ->assertSee(__('admin.media_library.storage_search_label'))
        ->assertSee(__('admin.media_library.deselect_image', ['name' => $selected->title]))
        ->assertSee(__('admin.media_library.select_image', ['name' => $blocked->title]))
        ->assertSee('aria-pressed="true"', false)
        ->assertSee('aria-pressed="false"', false)
        ->assertSee('aria-describedby="media-picker-blocked-'.$blocked->getKey().'"', false)
        ->assertSee('aria-live="polite"', false)
        ->assertSee('aria-atomic="true"', false)
        ->assertSee('focus-visible:ring-4', false)
        ->assertSee('pointer-coarse:opacity-100', false);
})->with([
    'Hebrew' => ['he'],
    'English' => ['en'],
]);

it('caps picker search at fifty trusted projected records', function (): void {
    $this->actingAs(User::factory()->admin()->create());
    Media::factory()->count(60)->create(['title' => 'Searchable cover']);
    Media::factory()->create([
        'directory' => 'header',
        'path' => 'header/'.Str::ulid().'.jpg',
        'title' => 'Searchable cover',
    ]);

    $component = pickerPanel()->set('search', 'Searchable');

    expect($component->get('files'))->toHaveCount(50)
        ->and(array_column($component->get('files'), 'pretty_name'))->each->toBe('Searchable cover');
});

it('selects an existing image from another logical folder without mutating it', function (): void {
    $this->actingAs(User::factory()->admin()->create());
    $wrongPurpose = Media::factory()->create([
        'directory' => 'header',
        'path' => 'header/'.Str::ulid().'.jpg',
    ]);
    Storage::disk('public')->put($wrongPurpose->path, 'existing media fixture');
    $originalIdentity = $wrongPurpose->only([
        'disk', 'directory', 'visibility', 'name', 'path', 'width', 'height', 'size', 'type', 'ext',
    ]);

    pickerPanel()
        ->call('showAllMedia')
        ->call('toggleSelection', $wrongPurpose->getKey())
        ->assertSet('selectedIds', [$wrongPurpose->getKey()])
        ->callAction(TestAction::make('insertMedia'))
        ->assertDispatched('insert-media', fn (string $event, array $parameters): bool => $parameters === [
            'mediaId' => $wrongPurpose->getKey(),
            'mediaIds' => [$wrongPurpose->getKey()],
        ]);

    expect($wrongPurpose->refresh()->only(array_keys($originalIdentity)))->toBe($originalIdentity);
    Storage::disk('public')->assertExists($wrongPurpose->path);
});

it('whitelists picker metadata edits and ignores forged storage identity', function (): void {
    $this->actingAs(User::factory()->admin()->create());
    $media = Media::factory()->create();
    $originalPath = $media->path;

    pickerPanel()->callAction(
        TestAction::make('editItem'),
        data: [
            'title' => 'Updated title',
            'alt' => 'Updated alt',
            'disk' => 'local',
            'path' => 'header/forged.svg',
            'name' => 'forged',
        ],
        arguments: ['id' => $media->getKey()],
    );

    expect($media->refresh()->title)->toBe('Updated title')
        ->and($media->alt)->toBe('Updated alt')
        ->and($media->disk)->toBe('public')
        ->and($media->path)->toBe($originalPath);
});

it('keeps nonselectable inventory visible while fencing selection and file mutations', function (): void {
    $this->actingAs(User::factory()->admin()->create());
    $allowed = Media::factory()->create();
    $blocked = Media::factory()->create([
        'visibility' => 'private',
    ]);
    Storage::disk('public')->put($allowed->path, 'allowed fixture');
    Storage::disk('public')->put($blocked->path, 'private fixture');

    pickerPanel()
        ->call('toggleSelection', $blocked->getKey())
        ->assertStatus(422);

    pickerPanel()->callAction(
        TestAction::make('editItem'),
        data: ['title' => 'Repair review'],
        arguments: ['id' => $blocked->getKey()],
    );

    expect($blocked->refresh()->title)->toBe('Repair review');

    $blockedOps = collect(pickerPanel()->get('files'))
        ->firstWhere('id', $blocked->getKey())['ops'];

    expect($blockedOps['delete']['allowed'])->toBeFalse()
        ->and($blockedOps['delete']['reason'])->toBe(__('admin.media_library.op_blocked_unmanaged'))
        ->and($blockedOps['rename']['allowed'])->toBeFalse()
        ->and($blockedOps['swap']['allowed'])->toBeFalse();

    foreach (['destroyItem', 'renameItem'] as $action) {
        pickerPanel()->callAction(
            TestAction::make($action),
            arguments: ['id' => $blocked->getKey()],
        );
    }

    $viewComponent = pickerPanel();
    $viewAction = $viewComponent->instance()
        ->viewItemAction()
        ->livewire($viewComponent->instance())
        ->arguments(['id' => $blocked->getKey()]);

    expect($viewAction->getUrl())->toBe(route('admin.media-files.view', ['media' => $blocked->getKey()]));

    pickerPanel()->callAction(
        TestAction::make('swapItem'),
        data: ['replacement' => pickerMediaFixture('valid.png')],
        arguments: ['id' => $blocked->getKey()],
    );

    expect($blocked->refresh()->visibility)->toBe('private');

    pickerPanel([
        'selectedIds' => [$allowed->getKey(), $blocked->getKey()],
        'isMultiple' => true,
        'maxItems' => 2,
    ])
        ->callAction(TestAction::make('destroySelected'))
        ->assertNotified(__('admin.media_library.bulk_delete_done_title'));

    expect(Media::query()->whereKey($allowed->getKey())->exists())->toBeFalse()
        ->and(Media::query()->whereKey($blocked->getKey())->exists())->toBeTrue();
});

it('uploads renames swaps and deletes through real picker actions', function (): void {
    $this->actingAs(User::factory()->admin()->create());

    $component = pickerPanel()
        ->fillForm([
            'uploads' => [pickerMediaFixture('valid.jpg')],
            'external_url' => 'https://cdn.example.test/preserved-after-upload.png',
        ])
        ->callAction(TestAction::make('uploadFiles'))
        ->assertHasNoFormErrors()
        ->assertNotified(
            Notification::make()
                ->success()
                ->title(__('admin.media_library.uploaded'))
                ->body(__('admin.media_library.acquisition_created_single', ['count' => 1])),
        );

    $media = Media::query()->sole();
    expect(data_get($component->get('panelData'), 'external_url'))
        ->toBe('https://cdn.example.test/preserved-after-upload.png');
    $component->assertDispatched(
        'insert-media',
        fn (string $event, array $parameters): bool => $parameters === [
            'mediaId' => $media->getKey(),
            'mediaIds' => [$media->getKey()],
        ],
    );
    $originalPath = $media->path;
    Storage::disk('public')->assertExists($originalPath);
    expect($media->mediaAsset)->toBeInstanceOf(MediaAsset::class)
        ->and($media->providerBinding)->toBeInstanceOf(MediaProviderBinding::class);

    $component->callAction(
        TestAction::make('renameItem'),
        arguments: ['id' => $media->getKey()],
    );
    $renamed = $media->refresh();

    expect($renamed->path)->not->toBe($originalPath);
    Storage::disk('public')->assertMissing($originalPath);
    Storage::disk('public')->assertExists($renamed->path);

    $component->callAction(
        TestAction::make('swapItem'),
        data: ['replacement' => pickerMediaFixture('valid.png')],
        arguments: ['id' => $renamed->getKey()],
    );
    $swapped = $media->refresh();

    expect($swapped->ext)->toBe('png')
        ->and($swapped->type)->toBe('image/png');
    Storage::disk('public')->assertExists($swapped->path);

    $component->callAction(
        TestAction::make('destroyItem'),
        arguments: ['id' => $swapped->getKey()],
    );

    expect(Media::query()->whereKey($swapped->getKey())->exists())->toBeFalse();
});

it('rejects forged multi-file state in a single picker before permanent admission', function (): void {
    $this->actingAs(User::factory()->admin()->create());

    pickerPanel()
        ->fillForm([
            'uploads' => [
                pickerMediaFixture('valid.jpg'),
                pickerMediaFixture('valid.png'),
            ],
        ])
        ->callAction(TestAction::make('uploadFiles'))
        ->assertHasFormErrors(['uploads'])
        ->assertSet('activeSource', 'upload')
        ->assertSee('data-error-source="upload"', false);

    expect(Media::query()->count())->toBe(0)
        ->and(MediaAsset::query()->count())->toBe(0)
        ->and(MediaProviderBinding::query()->count())->toBe(0)
        ->and(Storage::disk('public')->allFiles())->toBe([]);
});

it('admits an inline owner upload batch without choosing an arbitrary owner image', function (): void {
    $this->actingAs(User::factory()->admin()->create());

    $component = pickerPanel([
        'isInlineOwnerWorkspace' => true,
    ])
        ->fillForm([
            'uploads' => [
                pickerMediaFixture('valid.jpg'),
                pickerMediaFixture('valid.png'),
            ],
        ])
        ->callAction(TestAction::make('uploadFiles'))
        ->assertHasNoFormErrors()
        ->assertNotDispatched('insert-media')
        ->assertNotified(
            Notification::make()
                ->success()
                ->title(__('admin.media_library.uploaded'))
                ->body(__('admin.media_library.acquisition_batch_created', [
                    'count' => 2,
                ])),
        );

    expect($component->get('selectedIds'))->toBe([])
        ->and(Media::query()->count())->toBe(2)
        ->and(MediaAsset::query()->count())->toBe(2)
        ->and(MediaProviderBinding::query()->count())->toBe(2);

    foreach (Media::query()->get() as $media) {
        Storage::disk('public')->assertExists($media->path);
    }
});

it('keeps successful inline owner batch acquisitions permanent after a partial failure', function (): void {
    $this->actingAs(User::factory()->admin()->create());
    $bindingAttempts = 0;

    Event::listen('eloquent.creating: '.MediaProviderBinding::class, function () use (&$bindingAttempts): void {
        $bindingAttempts++;

        if ($bindingAttempts === 2) {
            throw new RuntimeException('second binding failed');
        }
    });

    $component = pickerPanel([
        'isInlineOwnerWorkspace' => true,
    ])
        ->fillForm([
            'uploads' => [
                pickerMediaFixture('valid.jpg'),
                pickerMediaFixture('valid.png'),
            ],
        ])
        ->callAction(TestAction::make('uploadFiles'))
        ->assertHasNoFormErrors()
        ->assertNotDispatched('insert-media')
        ->assertNotified(
            Notification::make()
                ->warning()
                ->title(__('admin.media_library.upload_partial_title'))
                ->body(__('admin.media_library.acquisition_batch_partial', [
                    'added' => 1,
                    'failed' => 1,
                    'not_attempted' => 0,
                ])),
        );

    $media = Media::query()->sole();

    expect($component->get('selectedIds'))->toBe([])
        ->and(MediaAsset::query()->count())->toBe(1)
        ->and(MediaProviderBinding::query()->count())->toBe(1)
        ->and($component->get('panelData.uploads'))->toHaveCount(1)
        ->and(collect($component->get('uploadResults'))->pluck('fate')->all())
        ->toBe(['acquired', 'failed']);
    $component
        ->assertSee('data-testid="media-picker-upload-results"', false)
        ->assertSee(__('admin.media_library.upload_fate_failed'))
        ->assertSee(__('admin.media_library.upload_reason_admission'))
        ->assertSee(__('admin.media_library.upload_retry_remaining', ['count' => 1]));
    Storage::disk('public')->assertExists($media->path);
});

it('keeps one inline owner upload selected as pending outer action state', function (): void {
    $this->actingAs(User::factory()->admin()->create());

    $component = pickerPanel([
        'isInlineOwnerWorkspace' => true,
    ])
        ->fillForm([
            'uploads' => [pickerMediaFixture('valid.jpg')],
        ])
        ->callAction(TestAction::make('uploadFiles'))
        ->assertHasNoFormErrors()
        ->assertDispatched('insert-media');

    expect($component->get('selectedIds'))->toBe([Media::query()->sole()->getKey()]);
});

it('selects and reports permanent successes when a later picker batch admission fails', function (): void {
    $this->actingAs(User::factory()->admin()->create());
    $bindingAttempts = 0;

    Event::listen('eloquent.creating: '.MediaProviderBinding::class, function () use (&$bindingAttempts): void {
        $bindingAttempts++;

        if ($bindingAttempts === 2) {
            throw new RuntimeException('second binding failed');
        }
    });

    $component = pickerPanel([
        'isMultiple' => true,
        'maxItems' => 10,
    ])
        ->fillForm([
            'uploads' => [
                pickerMediaFixture('valid.jpg'),
                pickerMediaFixture('valid.png'),
            ],
        ])
        ->callAction(TestAction::make('uploadFiles'))
        ->assertHasNoFormErrors()
        ->assertNotified(
            Notification::make()
                ->warning()
                ->title(__('admin.media_library.upload_partial_title'))
                ->body(__('admin.media_library.upload_partial_body', [
                    'added' => 1,
                    'failed' => 1,
                    'not_attempted' => 0,
                ])),
        );

    $media = Media::query()->sole();

    expect($component->get('selectedIds'))->toBe([$media->getKey()])
        ->and(MediaAsset::query()->count())->toBe(1)
        ->and(MediaProviderBinding::query()->count())->toBe(1)
        ->and(Storage::disk('public')->allFiles())->toBe([$media->path]);
});

it('keeps a successful multi upload selected until explicit insertion', function (): void {
    $this->actingAs(User::factory()->admin()->create());

    $component = pickerPanel([
        'isMultiple' => true,
        'maxItems' => 5,
    ])
        ->fillForm(['uploads' => [pickerMediaFixture('valid.jpg')]])
        ->callAction(TestAction::make('uploadFiles'))
        ->assertHasNoFormErrors()
        ->assertNotDispatched('insert-media');

    expect($component->get('selectedIds'))->toBe([Media::query()->sole()->getKey()]);
});

it('rejects malformed picker upload state without creating media', function (): void {
    $this->actingAs(User::factory()->admin()->create());

    pickerPanel()
        ->fillForm([
            'uploads' => [UploadedFile::fake()->createWithContent('forged.jpg', '<?php echo 1;')],
        ])
        ->callAction(TestAction::make('uploadFiles'))
        ->assertHasFormErrors(['uploads']);

    expect(Media::query()->count())->toBe(0);
});

it('acquires an extensionless URL immediately through the shared picker admission path', function (): void {
    $this->actingAs(User::factory()->admin()->create());
    $fetcher = Mockery::mock(SafeExternalImageFetcher::class);
    $fetcher->shouldReceive('fetch')
        ->once()
        ->with('https://cdn.example.test/artwork')
        ->andReturn(base64_decode((string) file_get_contents(base_path('tests/Fixtures/media/valid.png.base64')), true));
    app()->instance(SafeExternalImageFetcher::class, $fetcher);

    $component = pickerPanel()
        ->call('activateSource', 'url')
        ->fillForm(['external_url' => 'https://cdn.example.test/artwork'])
        ->callAction(TestAction::make('acquireUrl'))
        ->assertHasNoFormErrors()
        ->assertNotified(
            Notification::make()
                ->success()
                ->title(__('admin.media_library.url_acquired'))
                ->body(__('admin.media_library.acquisition_created_single', ['count' => 1])),
        )
        ->assertDispatched('insert-media', fn (string $event, array $parameters): bool => $parameters === [
            'mediaId' => Media::query()->sole()->getKey(),
            'mediaIds' => [Media::query()->sole()->getKey()],
        ]);

    $media = Media::query()->sole();

    expect($component->get('selectedIds'))->toBe([$media->getKey()])
        ->and($media->ext)->toBe('png')
        ->and($media->mediaAsset)->toBeInstanceOf(MediaAsset::class)
        ->and($media->providerBinding)->toBeInstanceOf(MediaProviderBinding::class);
    Storage::disk('public')->assertExists($media->path);
});

it('keeps successful multi acquisition selected until explicit insertion', function (): void {
    $this->actingAs(User::factory()->admin()->create());
    $fetcher = Mockery::mock(SafeExternalImageFetcher::class);
    $fetcher->shouldReceive('fetch')
        ->once()
        ->with('https://cdn.example.test/artwork')
        ->andReturn(base64_decode((string) file_get_contents(base_path('tests/Fixtures/media/valid.png.base64')), true));
    app()->instance(SafeExternalImageFetcher::class, $fetcher);

    $component = pickerPanel([
        'isMultiple' => true,
        'maxItems' => 5,
    ])
        ->fillForm([
            'uploads' => [pickerMediaFixture('valid.jpg')],
            'external_url' => 'https://cdn.example.test/artwork',
        ])
        ->call('activateSource', 'url')
        ->callAction(TestAction::make('acquireUrl'))
        ->assertHasNoFormErrors()
        ->assertNotDispatched('insert-media');

    expect($component->get('selectedIds'))->toBe([Media::query()->sole()->getKey()])
        ->and(data_get($component->get('panelData'), 'uploads'))->toHaveCount(1)
        ->and(data_get($component->get('panelData'), 'external_url'))->toBeNull();
});

it('maps URL rejection and transient failures to safe localized field copy', function (): void {
    $this->actingAs(User::factory()->admin()->create());
    $unsafeDetails = 'https://token@private.example.test/image.png 10.0.0.1';
    $failures = [
        [
            new ExternalImageRejectedException(ExternalImageFailureReason::Blocked, $unsafeDetails),
            __('admin.media_library.url_failure_blocked'),
        ],
        [
            new ExternalImageUnavailableException(
                ExternalImageFailureReason::TemporarilyUnavailable,
                $unsafeDetails,
            ),
            __('admin.media_library.url_failure_temporarily_unavailable'),
        ],
    ];

    foreach ($failures as [$exception, $expectedMessage]) {
        $fetcher = Mockery::mock(SafeExternalImageFetcher::class);
        $fetcher->shouldReceive('fetch')->once()->andThrow($exception);
        app()->instance(SafeExternalImageFetcher::class, $fetcher);

        $component = pickerPanel()
            ->call('activateSource', 'url')
            ->fillForm(['external_url' => 'https://cdn.example.test/artwork'])
            ->callAction(TestAction::make('acquireUrl'))
            ->assertHasFormErrors(['external_url'])
            ->assertSet('activeSource', 'url')
            ->assertSet('panelData.external_url', 'https://cdn.example.test/artwork')
            ->assertSee('data-error-source="url"', false);
        $message = $component->instance()->getErrorBag()->first('panelData.external_url');

        expect($message)->toBe($expectedMessage)
            ->and($message)->not->toContain($unsafeDetails)
            ->and($message)->not->toContain('10.0.0.1');
    }
});

it('maps image admission and unexpected URL failures without exposing internals', function (): void {
    $this->actingAs(User::factory()->admin()->create());
    $unsafeDetails = 'SQLSTATE secret /private/media/path';
    $failures = [
        [
            new InvalidArgumentException($unsafeDetails),
            __('admin.media_library.url_failure_invalid_image'),
        ],
        [
            new RuntimeException($unsafeDetails),
            __('admin.media_library.url_failure_unexpected'),
        ],
    ];

    foreach ($failures as [$exception, $expectedMessage]) {
        $acquisitions = Mockery::mock(MediaAcquisitionManager::class);
        $acquisitions->shouldReceive('acquireExternalUrl')->once()->andThrow($exception);
        app()->instance(MediaAcquisitionManager::class, $acquisitions);

        $component = pickerPanel()
            ->call('activateSource', 'url')
            ->fillForm(['external_url' => 'https://cdn.example.test/artwork'])
            ->callAction(TestAction::make('acquireUrl'))
            ->assertHasFormErrors(['external_url'])
            ->assertSet('activeSource', 'url')
            ->assertSet('panelData.external_url', 'https://cdn.example.test/artwork')
            ->assertSee('data-error-source="url"', false);
        $message = $component->instance()->getErrorBag()->first('panelData.external_url');

        expect($message)->toBe($expectedMessage)
            ->and($message)->not->toContain($unsafeDetails)
            ->and($message)->not->toContain('/private/media/path');
    }
});

it('rethrows URL acquisition authorization failures instead of converting them to field errors', function (): void {
    $this->actingAs(User::factory()->admin()->create());
    $acquisitions = Mockery::mock(MediaAcquisitionManager::class);
    $acquisitions->shouldReceive('acquireExternalUrl')
        ->once()
        ->andThrow(new AuthorizationException('forbidden acquisition'));
    app()->instance(MediaAcquisitionManager::class, $acquisitions);

    $component = pickerPanel()
        ->call('activateSource', 'url')
        ->fillForm(['external_url' => 'https://cdn.example.test/artwork']);
    $action = $component->instance()
        ->acquireUrlAction()
        ->livewire($component->instance());

    expect(fn () => $action->call())
        ->toThrow(AuthorizationException::class);
});

it('routes Storage acquisition failures to the visible dedicated error', function (): void {
    $this->actingAs(User::factory()->admin()->create());
    Storage::disk('public')->put(
        'media-imports/storage-error.jpg',
        base64_decode((string) file_get_contents(base_path('tests/Fixtures/media/valid.jpg.base64')), true),
    );
    $component = pickerPanel()
        ->call('activateSource', 'storage');
    $candidate = $component->get('storageFiles')[0];
    $acquisitions = Mockery::mock(MediaAcquisitionManager::class);
    $acquisitions->shouldReceive('acquireStorageCandidate')
        ->once()
        ->andThrow(new RuntimeException('internal /private/path token=secret'));
    $acquisitions->shouldReceive('storageCandidates')
        ->once()
        ->with('try another image')
        ->andReturn([]);
    $acquisitions->shouldReceive('storageCandidates')
        ->once()
        ->with('retry this image')
        ->andReturn([]);
    app()->instance(MediaAcquisitionManager::class, $acquisitions);

    $component
        ->callAction(
            TestAction::make('acquireStorage'),
            arguments: ['token' => $candidate['token']],
        )
        ->assertSet('activeSource', 'storage')
        ->assertSee('data-error-source="storage"', false)
        ->assertSee(__('admin.media_library.storage_invalid'))
        ->assertDontSee('/private/path')
        ->assertDontSee('token=secret');

    expect($component->instance()->getErrorBag()->first('storageAcquisition'))
        ->toBe(__('admin.media_library.storage_invalid'));

    $component
        ->set('storageSearch', 'try another image')
        ->assertHasNoErrors(['storageAcquisition']);

    $component->instance()->addError(
        'storageAcquisition',
        __('admin.media_library.storage_invalid'),
    );
    $component->instance()->storageSearch = '  retry this image  ';
    $component->instance()->updatedStorageSearch();

    expect($component->instance()->getErrorBag()->has('storageAcquisition'))->toBeFalse()
        ->and($component->instance()->storageSearch)->toBe('retry this image');
});

it('keeps real Storage lock contention visible and retryable', function (): void {
    $this->actingAs(User::factory()->admin()->create());
    Storage::disk('public')->put(
        'media-imports/storage-contended.jpg',
        base64_decode((string) file_get_contents(base_path('tests/Fixtures/media/valid.jpg.base64')), true),
    );
    $component = pickerPanel()
        ->call('activateSource', 'storage');
    $candidate = $component->get('storageFiles')[0];
    $acquisitions = Mockery::mock(MediaAcquisitionManager::class);
    $acquisitions->shouldReceive('acquireStorageCandidate')
        ->once()
        ->andThrow(new LockTimeoutException('internal lock key'));
    app()->instance(MediaAcquisitionManager::class, $acquisitions);

    $component
        ->callAction(
            TestAction::make('acquireStorage'),
            arguments: ['token' => $candidate['token']],
        )
        ->assertSet('activeSource', 'storage')
        ->assertHasErrors(['storageAcquisition'])
        ->assertSee(__('admin.media_library.storage_busy'))
        ->assertDontSee('internal lock key');

    expect($component->instance()->getErrorBag()->first('storageAcquisition'))
        ->toBe(__('admin.media_library.storage_busy'));
});

it('keeps Storage retryable with safe copy when configured-source enumeration fails', function (): void {
    $this->actingAs(User::factory()->admin()->create());
    $acquisitions = Mockery::mock(MediaAcquisitionManager::class);
    $acquisitions->shouldReceive('storageCandidates')
        ->once()
        ->with('')
        ->andThrow(new RuntimeException('filesystem secret /mounted/private/path'));
    app()->instance(MediaAcquisitionManager::class, $acquisitions);

    $component = pickerPanel()
        ->call('activateSource', 'storage')
        ->assertSet('activeSource', 'storage')
        ->assertSet('storageLoaded', false)
        ->assertSee(__('admin.media_library.storage_load_failed'))
        ->assertDontSee('/mounted/private/path');

    expect($component->instance()->getErrorBag()->first('storageAcquisition'))
        ->toBe(__('admin.media_library.storage_load_failed'));
});

it('acquires an opaque Storage candidate and keeps the library item before owner insertion', function (): void {
    $this->actingAs(User::factory()->admin()->create());
    Storage::disk('public')->put(
        'media-imports/storage-cover.jpg',
        base64_decode((string) file_get_contents(base_path('tests/Fixtures/media/valid.jpg.base64')), true),
    );

    $component = pickerPanel()
        ->call('activateSource', 'storage');
    $candidate = $component->get('storageFiles')[0];

    expect($candidate)->not->toHaveKey('path');

    $component
        ->callAction(
            TestAction::make('acquireStorage'),
            arguments: ['token' => $candidate['token']],
        )
        ->assertHasNoFormErrors()
        ->assertNotified(
            Notification::make()
                ->success()
                ->title(__('admin.media_library.storage_registered'))
                ->body(__('admin.media_library.acquisition_created_single', ['count' => 1])),
        );

    $media = Media::query()->sole();
    $component->assertDispatched(
        'insert-media',
        fn (string $event, array $parameters): bool => $parameters === [
            'mediaId' => $media->getKey(),
            'mediaIds' => [$media->getKey()],
        ],
    );

    expect($component->get('selectedIds'))->toBe([$media->getKey()])
        ->and($media->path)->toBe('media-imports/storage-cover.jpg')
        ->and($media->mediaAsset)->toBeInstanceOf(MediaAsset::class);

    pickerPanel()
        ->call('activateSource', 'storage')
        ->callAction(
            TestAction::make('acquireStorage'),
            arguments: ['token' => $candidate['token']],
        )
        ->assertNotified(
            Notification::make()
                ->success()
                ->title(__('admin.media_library.storage_reused'))
                ->body(__('admin.media_library.acquisition_reused_single', ['count' => 1])),
        );
});

it('keeps a successful multi Storage acquisition selected until explicit insertion', function (): void {
    $this->actingAs(User::factory()->admin()->create());
    Storage::disk('public')->put(
        'media-imports/storage-multi.jpg',
        base64_decode((string) file_get_contents(base_path('tests/Fixtures/media/valid.jpg.base64')), true),
    );
    $component = pickerPanel([
        'isMultiple' => true,
        'maxItems' => 5,
    ])
        ->fillForm([
            'uploads' => [pickerMediaFixture('valid.jpg')],
            'external_url' => 'https://cdn.example.test/preserved-after-storage.png',
        ])
        ->call('activateSource', 'storage');
    $candidate = collect($component->get('storageFiles'))
        ->firstWhere('filename', 'storage-multi.jpg');

    $component
        ->callAction(
            TestAction::make('acquireStorage'),
            arguments: ['token' => $candidate['token']],
        )
        ->assertHasNoFormErrors()
        ->assertNotDispatched('insert-media');

    expect($component->get('selectedIds'))->toBe([Media::query()->sole()->getKey()])
        ->and(data_get($component->get('panelData'), 'uploads'))->toHaveCount(1)
        ->and(data_get($component->get('panelData'), 'external_url'))
        ->toBe('https://cdn.example.test/preserved-after-storage.png');
});

it('renders the exact owner picker source and permanence contract in both locales', function (string $locale): void {
    app()->setLocale($locale);
    $this->actingAs(User::factory()->admin()->create());
    $media = Media::factory()->create(['title' => 'Mixed תמונה & image']);
    Storage::disk((string) $media->disk)->put((string) $media->path, 'image fixture');
    $component = pickerPanel([
        'selectedIds' => [$media->getKey()],
        'isInlineOwnerWorkspace' => true,
        'isOwnerChoice' => true,
        'savedMediaId' => $media->getKey(),
    ])
        ->assertSee(__('admin.media_library.source_navigation'))
        ->assertSee(__('admin.media_library.gallery_source'))
        ->assertSee(__('admin.media_library.upload_source'))
        ->assertSee(__('admin.media_library.url_source'))
        ->assertSee(__('admin.media_library.storage_source'))
        ->assertSee(__('admin.media_library.gallery_search_label'))
        ->assertSee(__('admin.media_library.selected_count', ['count' => 1]))
        ->assertSee(__('admin.media_library.current_image').': '.$media->title)
        ->assertSee(__('admin.media_library.open_details'))
        ->assertSee(__('admin.media_library.working'))
        ->assertSee(__('admin.media_library.offline'))
        ->assertDontSee('admin.media_library.', false);

    app()->setLocale($locale);
    $component
        ->call('activateSource', 'upload')
        ->assertSee(rtrim(Str::before(__('admin.media_library.acquisition_permanence_inline'), ':link')))
        ->assertSee(__('admin.media_library.inline_batch_help'))
        ->assertSee(__('admin.media_library.upload_one_or_multiple'))
        ->assertDontSee('admin.media_library.', false);

    app()->setLocale($locale);
    $component
        ->call('activateSource', 'url')
        ->assertSee(__('admin.media_library.url_field'))
        ->assertSee(__('admin.media_library.url_help'))
        ->assertSee(__('admin.media_library.add_and_choose'))
        ->assertDontSee('admin.media_library.', false);

    app()->setLocale($locale);
    $component
        ->call('activateSource', 'storage')
        ->assertSee(__('admin.media_library.storage_search_label'))
        ->assertSee(__('admin.media_library.refresh_storage'))
        ->assertSee(__('admin.media_library.add_and_choose'))
        ->assertDontSee('admin.media_library.', false);

    expect($component->html())->toContain(e('Mixed תמונה & image'));
})->with([
    'Hebrew' => ['he'],
    'English' => ['en'],
]);

it('keeps generic and owner picker layout contracts structurally separate', function (): void {
    $this->actingAs(User::factory()->admin()->create());
    $media = Media::factory()->create();
    Storage::disk((string) $media->disk)->put((string) $media->path, 'image fixture');
    $genericHtml = pickerPanel(['selectedIds' => [$media->getKey()]])->html();
    $ownerHtml = pickerPanel([
        'selectedIds' => [$media->getKey()],
        'isInlineOwnerWorkspace' => true,
        'isOwnerChoice' => true,
        'savedMediaId' => $media->getKey(),
    ])->html();

    expect($genericHtml)
        ->toContain('min-h-[70vh]')
        ->toContain('xl:grid-cols-6')
        ->toContain('data-testid="media-picker-footer"')
        ->toContain('data-testid="media-picker-close"')
        ->toContain(__('admin.media_library.context_media'))
        ->toContain(__('admin.media_library.use_selected'))
        ->not->toContain('podtext-owner-image-modal')
        ->and($ownerHtml)
        ->toContain('min-h-[60vh]')
        ->toContain('grid grid-cols-2 gap-3')
        ->toContain('xl:grid-cols-5')
        ->not->toContain('xl:grid-cols-6')
        ->not->toContain('data-testid="media-picker-footer"')
        ->not->toContain('data-testid="media-picker-close"')
        ->not->toContain(__('admin.media_library.context_media'))
        ->not->toContain(__('admin.media_library.use_selected'));
});

it('filters the owner gallery by directory with validation, paging reset, and an empty state', function (): void {
    $this->actingAs(User::factory()->admin()->create());
    $cover = Media::factory()->create(['directory' => 'content-groups/covers', 'title' => 'Cover A']);
    $headerLogo = Media::factory()->create(['directory' => 'header', 'title' => 'Header logo']);
    $rootSpare = Media::factory()->create(['directory' => '', 'title' => 'Root spare']);

    $component = pickerPanel(['isOwnerChoice' => true, 'savedMediaId' => null]);

    expect(collect($component->instance()->files)->pluck('id')->all())
        ->toContain($cover->getKey())
        ->toContain($headerLogo->getKey())
        ->toContain($rootSpare->getKey())
        ->and($component->instance()->directoryOptions())
        ->toHaveKeys(['content-groups/covers', 'header', '__root__']);

    $component
        ->assertSee('data-testid="media-picker-directory-filter"', false)
        ->set('directoryFilter', 'header')
        ->assertSet('directoryFilter', 'header')
        ->assertSet('currentPage', 1);
    expect(collect($component->instance()->files)->pluck('id')->all())
        ->toBe([$headerLogo->getKey()]);

    $component->set('directoryFilter', '__root__');
    expect(collect($component->instance()->files)->pluck('id')->all())
        ->toBe([$rootSpare->getKey()]);

    $component->set('directoryFilter', '../etc')->assertSet('directoryFilter', '');
    expect(collect($component->instance()->files)->pluck('id')->all())->toHaveCount(3);

    $component->set('directoryFilter', 'header');
    DB::table('curator')
        ->where('id', $headerLogo->getKey())
        ->update(['directory' => 'moved-elsewhere']);
    $component->call('showAllMedia')
        ->assertSee(__('admin.media_library.empty_directory'));
});

it('shows the directory filter only when browsing all media outside owner choice', function (): void {
    $this->actingAs(User::factory()->admin()->create());
    Media::factory()->create(['directory' => 'header', 'title' => 'Header logo']);

    $component = pickerPanel();
    $component->assertDontSee('data-testid="media-picker-directory-filter"', false);

    $component->call('showAllMedia')
        ->assertSee('data-testid="media-picker-directory-filter"', false)
        ->set('directoryFilter', 'header')
        ->assertSet('directoryFilter', 'header');

    $component->call('showContextMedia')
        ->assertSet('directoryFilter', '')
        ->assertDontSee('data-testid="media-picker-directory-filter"', false);
});

it('opens a read-only media details slide-over from owner details triggers', function (): void {
    $this->actingAs(User::factory()->admin()->create());
    $selectable = Media::factory()->create([
        'directory' => 'header',
        'title' => 'Slide over subject',
        'name' => 'slide-over-subject',
        'path' => 'header/slide-over-subject.png',
        'disk' => 'public',
        'visibility' => 'public',
        'type' => 'image/png',
        'ext' => 'png',
        'size' => 2048,
        'width' => 320,
        'height' => 240,
    ]);
    Storage::disk('public')->put($selectable->path, 'slide-over');
    $blocked = Media::factory()->create([
        'directory' => 'header',
        'title' => 'Blocked slide over subject',
    ]);

    $component = pickerPanel(['isOwnerChoice' => true, 'savedMediaId' => null]);

    $component
        ->assertSee("mountAction('mediaDetails'", false)
        ->call('mountAction', 'mediaDetails', ['id' => $selectable->getKey()]);
    $modalHtml = $component->getMountedActionModalHtml();

    expect($modalHtml)
        ->toContain('data-testid="media-details-slide-over"')
        ->toContain('Slide over subject')
        ->toContain('image/png')
        ->toContain('320×240')
        ->toContain((string) $selectable->reference_key)
        ->toContain(__('admin.media_library.details_known_usages'))
        ->toContain(__('admin.media_library.details_no_warnings'))
        ->toContain(__('admin.media_library.details_full_page'));

    $component
        ->call('unmountAction')
        ->call('mountAction', 'mediaDetails', ['id' => $blocked->getKey()]);
    $blockedHtml = $component->getMountedActionModalHtml();

    expect($blockedHtml)
        ->toContain('Blocked slide over subject')
        ->not->toContain(__('admin.media_library.details_no_warnings'));
});

it('names every file fate and keeps the queue when validation blocks a batch', function (): void {
    $this->actingAs(User::factory()->admin()->create());

    $component = pickerPanel(['isInlineOwnerWorkspace' => true])
        ->fillForm([
            'uploads' => [
                pickerMediaFixture('valid.jpg'),
                UploadedFile::fake()->createWithContent('broken.jpg', '<?php echo 1;'),
            ],
        ])
        ->callAction(TestAction::make('uploadFiles'))
        ->assertHasErrors(['panelData.uploads']);

    expect(Media::query()->count())->toBe(0)
        ->and($component->get('panelData.uploads'))->toHaveCount(2)
        ->and(collect($component->get('uploadResults'))->pluck('fate')->all())
        ->toBe(['not_attempted', 'failed']);
    $component
        ->assertSee('data-testid="media-picker-upload-results"', false)
        ->assertSee(__('admin.media_library.upload_fate_not_attempted'))
        ->assertSee(__('admin.media_library.upload_reason_validation'))
        ->assertSee('broken.jpg');
});

it('renders the browser-side url preview island on the url tab', function (): void {
    $this->actingAs(User::factory()->admin()->create());

    pickerPanel()
        ->call('activateSource', 'url')
        ->assertSee('data-testid="media-picker-url-preview"', false)
        ->assertSee(__('admin.media_library.url_preview_failed'))
        ->assertSee(__('admin.media_library.url_help'));
});

it('admits a large queue in batch-limit chunks with an accumulating receipt', function (): void {
    $this->actingAs(User::factory()->admin()->create());
    $settings = app(AdminUxSettings::class);
    $settings->media_acquisition_upload_batch_limit = 2;
    $settings->media_upload_queue_limit = 5;
    $settings->save();

    $component = pickerPanel(['isInlineOwnerWorkspace' => true])
        ->fillForm([
            'uploads' => [
                pickerMediaFixture('valid.jpg'),
                pickerMediaFixture('valid.png'),
                pickerMediaFixture('valid.webp'),
            ],
        ])
        ->callAction(TestAction::make('uploadFiles'))
        ->assertHasNoFormErrors()
        ->assertNotified(
            Notification::make()
                ->success()
                ->title(__('admin.media_library.uploaded'))
                ->body(__('admin.media_library.acquisition_batch_created', ['count' => 2])
                    .' '.__('admin.media_library.upload_more_queued', ['count' => 1])),
        );

    expect(Media::query()->count())->toBe(2)
        ->and($component->get('panelData.uploads'))->toHaveCount(1)
        ->and(collect($component->get('uploadResults'))->pluck('fate')->all())
        ->toBe(['acquired', 'acquired', 'not_attempted']);
    $component->assertSee(__('admin.media_library.upload_retry_remaining', ['count' => 1]));

    $component->callAction(TestAction::make('uploadFiles'))->assertHasNoFormErrors();

    expect(Media::query()->count())->toBe(3)
        ->and($component->get('panelData.uploads'))->toHaveCount(0)
        ->and(collect($component->get('uploadResults'))->pluck('fate')->all())
        ->toBe(['acquired', 'acquired', 'acquired']);
});

it('projects per-file operation availability with truthful reasons', function (): void {
    $this->actingAs(User::factory()->admin()->create());
    $orphan = Media::factory()->create();
    $referenced = Media::factory()->create([
        'name' => '01J00000000000000000000031',
        'path' => 'content-groups/covers/01J00000000000000000000031.jpg',
    ]);
    $referencedOwner = ContentGroup::factory()->create();
    MediaAttachment::query()->create([
        'media_id' => $referenced->getKey(),
        'attachable_type' => 'content_group',
        'attachable_id' => $referencedOwner->getKey(),
        'role' => MediaAttachmentRole::Cover,
        'position' => 0,
    ]);

    $component = pickerPanel();
    $files = collect($component->get('files'));
    $orphanOps = $files->firstWhere('id', $orphan->getKey())['ops'];
    $referencedOps = $files->firstWhere('id', $referenced->getKey())['ops'];

    expect($orphanOps['rename']['allowed'])->toBeTrue()
        ->and($orphanOps['delete']['allowed'])->toBeTrue()
        ->and($orphanOps['delete']['reason'])->toBeNull()
        ->and($referencedOps['delete']['allowed'])->toBeFalse()
        ->and($referencedOps['delete']['reason'])->toBeString()->not->toBe('')
        ->and($referencedOps['swap']['allowed'])->toBeFalse()
        ->and($referencedOps['rename']['reason'])->toBe($referencedOps['swap']['reason']);

    expect($component->instance()->destroyItemAction()->getLabel())
        ->toBe(__('admin.media_library.delete_permanently'));
});

it('scopes the panel search including owner titles', function (): void {
    $this->actingAs(User::factory()->admin()->create());
    $titled = Media::factory()->create([
        'name' => '01J00000000000000000000061',
        'path' => 'content-groups/covers/01J00000000000000000000061.jpg',
        'title' => 'כרומה כתומה',
    ]);
    $owned = Media::factory()->create([
        'name' => '01J00000000000000000000062',
        'path' => 'content-groups/covers/01J00000000000000000000062.jpg',
        'title' => null,
    ]);
    $ownedOwner = ContentGroup::factory()->create(['title' => 'שיחות עומק']);
    MediaAttachment::query()->create([
        'media_id' => $owned->getKey(),
        'attachable_type' => 'content_group',
        'attachable_id' => $ownedOwner->getKey(),
        'role' => MediaAttachmentRole::Cover,
        'position' => 0,
    ]);

    $component = pickerPanel()
        ->set('searchScope', 'owner')
        ->set('search', 'עומק');

    expect(array_column($component->get('files'), 'id'))->toBe([$owned->getKey()]);

    $component->set('searchScope', 'title')->set('search', 'כרומה');

    expect(array_column($component->get('files'), 'id'))->toBe([$titled->getKey()]);

    $component->set('searchScope', 'garbage');

    expect($component->get('searchScope'))->toBe('all');
});
