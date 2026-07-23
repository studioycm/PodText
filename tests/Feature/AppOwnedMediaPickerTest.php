<?php

use App\Enums\ImageUploadPurpose;
use App\Enums\UserRole;
use App\Livewire\Admin\DisabledVendorCuratorSurface;
use App\Livewire\Admin\MediaPickerPanel;
use App\Models\Media;
use App\Models\MediaAsset;
use App\Models\MediaProviderBinding;
use App\Models\User;
use App\Settings\AdminUxSettings;
use App\Support\Media\SafeExternalImageFetcher;
use Filament\Actions\Testing\TestAction;
use Filament\Forms\Components\FileUpload;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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
    $component = pickerPanel();
    $upload = collect($component->instance()->getSchema('form')->getFlatComponents(withHidden: true))
        ->first(fn (mixed $field): bool => $field instanceof FileUpload && $field->getName() === 'uploads');

    expect($upload)->toBeInstanceOf(FileUpload::class)
        ->and($upload->getMaxFiles())->toBe(10)
        ->and($upload->getMaxParallelUploads())->toBe(2);
});

it('uses bounded Admin UX batch browse and search settings for new picker work', function (): void {
    $this->actingAs(User::factory()->admin()->create());
    $settings = app(AdminUxSettings::class);
    $settings->media_acquisition_max_kilobytes = 4096;
    $settings->media_acquisition_upload_batch_limit = 3;
    $settings->media_picker_browse_limit = 12;
    $settings->media_picker_search_limit = 11;
    $settings->save();
    Media::factory()->count(20)->create(['title' => 'Configured search']);

    $component = pickerPanel();
    $upload = collect($component->instance()->getSchema('form')->getFlatComponents(withHidden: true))
        ->first(fn (mixed $field): bool => $field instanceof FileUpload && $field->getName() === 'uploads');

    expect($component->get('files'))->toHaveCount(12)
        ->and($upload)->toBeInstanceOf(FileUpload::class)
        ->and($upload->getMaxFiles())->toBe(3)
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
        ->assertSee(__('admin.media_library.acquisition_permanence'));
});

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
        ->assertDispatched('insert-media', fn (string $event, array $parameters): bool => $parameters === [[
            'mediaId' => $wrongPurpose->getKey(),
            'mediaIds' => [$wrongPurpose->getKey()],
        ]]);

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

    foreach (['destroyItem', 'renameItem'] as $action) {
        expect(fn () => pickerPanel()->callAction(
            TestAction::make($action),
            arguments: ['id' => $blocked->getKey()],
        ))->toThrow(ModelNotFoundException::class);
    }

    $viewComponent = pickerPanel();
    $viewAction = $viewComponent->instance()
        ->viewItemAction()
        ->livewire($viewComponent->instance())
        ->arguments(['id' => $blocked->getKey()]);

    expect($viewAction->getUrl())->toBe(route('admin.media-files.view', ['media' => $blocked->getKey()]));

    expect(fn () => pickerPanel()->callAction(
        TestAction::make('swapItem'),
        data: ['replacement' => pickerMediaFixture('valid.png')],
        arguments: ['id' => $blocked->getKey()],
    ))->toThrow(ModelNotFoundException::class);

    expect(fn () => pickerPanel([
        'selectedIds' => [$allowed->getKey(), $blocked->getKey()],
        'isMultiple' => true,
        'maxItems' => 2,
    ])
        ->callAction(TestAction::make('destroySelected')))
        ->toThrow(ModelNotFoundException::class);

    expect(Media::query()->whereKey([$allowed->getKey(), $blocked->getKey()])->count())->toBe(2);
});

it('uploads renames swaps and deletes through real picker actions', function (): void {
    $this->actingAs(User::factory()->admin()->create());

    $component = pickerPanel()
        ->fillForm(['uploads' => [pickerMediaFixture('valid.jpg')]])
        ->callAction(TestAction::make('uploadFiles'))
        ->assertHasNoFormErrors();

    $media = Media::query()->sole();
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
        ->fillForm(['external_url' => 'https://cdn.example.test/artwork'])
        ->callAction(TestAction::make('acquireUrl'))
        ->assertHasNoFormErrors();

    $media = Media::query()->sole();

    expect($component->get('selectedIds'))->toBe([$media->getKey()])
        ->and($media->ext)->toBe('png')
        ->and($media->mediaAsset)->toBeInstanceOf(MediaAsset::class)
        ->and($media->providerBinding)->toBeInstanceOf(MediaProviderBinding::class);
    Storage::disk('public')->assertExists($media->path);
});

it('acquires an opaque Storage candidate and keeps the library item before owner insertion', function (): void {
    $this->actingAs(User::factory()->admin()->create());
    Storage::disk('public')->put(
        'media-imports/storage-cover.jpg',
        base64_decode((string) file_get_contents(base_path('tests/Fixtures/media/valid.jpg.base64')), true),
    );

    $component = pickerPanel();
    $candidate = $component->get('storageFiles')[0];

    expect($candidate)->not->toHaveKey('path');

    $component
        ->callAction(
            TestAction::make('acquireStorage'),
            arguments: ['token' => $candidate['token']],
        )
        ->assertHasNoFormErrors();

    $media = Media::query()->sole();

    expect($component->get('selectedIds'))->toBe([$media->getKey()])
        ->and($media->path)->toBe('media-imports/storage-cover.jpg')
        ->and($media->mediaAsset)->toBeInstanceOf(MediaAsset::class);
});
