<?php

use App\Enums\ImageUploadPurpose;
use App\Enums\UserRole;
use App\Livewire\Admin\DisabledVendorCuratorSurface;
use App\Livewire\Admin\MediaPickerPanel;
use App\Models\Media;
use App\Models\User;
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

it('bounds browse and load-more payloads and excludes every wrong record boundary', function (): void {
    $this->actingAs(User::factory()->admin()->create());
    $allowed = Media::factory()->count(30)->create();
    $root = ImageUploadPurpose::ContentGroupCover->root();

    Media::factory()->create(['disk' => 'local']);
    Media::factory()->create(['visibility' => 'private']);
    Media::factory()->create(['directory' => 'header', 'path' => 'header/'.Str::ulid().'.jpg']);
    Media::factory()->create(['path' => "{$root}/nested/".Str::ulid().'.jpg']);
    Media::factory()->create(['path' => "{$root}/..%2Foutside.jpg"]);
    Media::factory()->create(['path' => $root.'\\outside.jpg']);
    Media::factory()->create(['type' => 'image/png', 'ext' => 'jpg']);
    Media::factory()->create(['type' => 'image/gif', 'ext' => 'gif', 'path' => "{$root}/".Str::ulid().'.gif']);

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
        ])
        ->and(array_column($files, 'id'))->each->toBeIn($allowed->modelKeys());

    $component->call('loadMoreFiles');

    expect($component->get('files'))->toHaveCount(5)
        ->and(array_column($component->get('files'), 'id'))->each->toBeIn($allowed->modelKeys());

    $component->call('loadPreviousFiles');

    expect($component->get('files'))->toHaveCount(25);
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

it('reloads selection IDs through the purpose scope and emits only the trusted ID', function (): void {
    $this->actingAs(User::factory()->admin()->create());
    $allowed = Media::factory()->create();
    $wrongPurpose = Media::factory()->create([
        'directory' => 'header',
        'path' => 'header/'.Str::ulid().'.jpg',
    ]);

    expect(fn () => pickerPanel()->call('toggleSelection', $wrongPurpose->getKey()))
        ->toThrow(ModelNotFoundException::class);

    pickerPanel()
        ->call('toggleSelection', $allowed->getKey())
        ->assertSet('selectedIds', [$allowed->getKey()])
        ->callAction(TestAction::make('insertMedia'))
        ->assertDispatched('insert-media', fn (string $event, array $parameters): bool => $parameters === [[
            'mediaId' => $allowed->getKey(),
            'mediaIds' => [$allowed->getKey()],
        ]]);
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

it('revalidates forged selected arrays and every record action argument through the purpose scope', function (): void {
    $this->actingAs(User::factory()->admin()->create());
    $allowed = Media::factory()->create();
    $wrongPurpose = Media::factory()->create([
        'directory' => ImageUploadPurpose::HeaderLogo->root(),
        'path' => ImageUploadPurpose::HeaderLogo->root().'/'.Str::ulid().'.jpg',
    ]);

    expect(fn () => pickerPanel()
        ->set('selectedIds', [$wrongPurpose->getKey()])
        ->callAction(TestAction::make('insertMedia')))
        ->toThrow(ModelNotFoundException::class);

    foreach (['editItem', 'downloadItem', 'destroyItem', 'renameItem'] as $action) {
        expect(fn () => pickerPanel()->callAction(
            TestAction::make($action),
            data: $action === 'editItem' ? ['title' => 'Forged'] : [],
            arguments: ['id' => $wrongPurpose->getKey()],
        ))->toThrow(ModelNotFoundException::class);
    }

    $viewComponent = pickerPanel();
    $viewAction = $viewComponent->instance()
        ->viewItemAction()
        ->livewire($viewComponent->instance())
        ->arguments(['id' => $wrongPurpose->getKey()]);

    expect(fn () => $viewAction->getUrl())->toThrow(ModelNotFoundException::class);

    expect(fn () => pickerPanel()->callAction(
        TestAction::make('swapItem'),
        data: ['replacement' => pickerMediaFixture('valid.png')],
        arguments: ['id' => $wrongPurpose->getKey()],
    ))->toThrow(ModelNotFoundException::class);

    expect(fn () => pickerPanel()
        ->set('selectedIds', [$allowed->getKey(), $wrongPurpose->getKey()])
        ->callAction(TestAction::make('destroySelected')))
        ->toThrow(ModelNotFoundException::class);

    expect(Media::query()->whereKey([$allowed->getKey(), $wrongPurpose->getKey()])->count())->toBe(2);
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
