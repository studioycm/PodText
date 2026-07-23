<?php

use App\Enums\ImageUploadPurpose;
use App\Enums\UserRole;
use App\Filament\Resources\Media\Pages\CreateMedia;
use App\Filament\Resources\Media\Pages\EditMedia;
use App\Filament\Resources\Media\Pages\ListMedia;
use App\Filament\Resources\Media\Schemas\MediaForm;
use App\Models\ContentGroup;
use App\Models\Media;
use App\Models\MediaAsset;
use App\Models\MediaMutationOperation;
use App\Models\MediaProviderBinding;
use App\Models\User;
use Awcodes\Curator\Config\CurationManager;
use Awcodes\Curator\Facades\Curator;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Concerns\RestrictsFileUploadsToSchemaComponents;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Symfony\Component\Finder\Finder;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Http::preventStrayRequests();
    Storage::fake('local');
    Storage::fake('public');
});

it('pins curator global upload configuration to the app-owned maximum union', function (): void {
    expect(config('curator.default_disk'))->toBe('public')
        ->and(config('curator.default_visibility'))->toBe('public')
        ->and(Curator::getAcceptedFileTypes())->toBe([
            'image/jpeg',
            'image/png',
            'image/webp',
            'image/svg+xml',
        ])
        ->and(Curator::getMaxSize())->toBe(2048)
        ->and(Curator::getDiskName())->toBe('public')
        ->and(Curator::getVisibility())->toBe('public')
        ->and(Curator::shouldPreserveFilenames())->toBeFalse()
        ->and(config('livewire.temporary_file_upload.disk'))->toBe('local')
        ->and(config('curator.curation_formats'))->toBe(['jpg', 'png', 'webp'])
        ->and(config('curator.features.curations'))->toBeFalse()
        ->and(class_uses_recursive(ListMedia::class))->toContain(RestrictsFileUploadsToSchemaComponents::class);

    $installedPreset = app(CurationManager::class)->getPresets()[0];

    expect($installedPreset->getFormat())->toBe('webp')
        ->and($installedPreset->getQuality())->toBe(60)
        ->and(collect((new ReflectionClass(MediaForm::class))->getMethods())->pluck('name'))->not->toContain('curation');
});

it('keeps rich editor file attachments and curator curation editing disabled', function (): void {
    $editorCount = 0;

    foreach (Finder::create()->files()->in(app_path('Filament'))->name('*.php') as $file) {
        $contents = $file->getContents();
        expect($contents)->not->toContain('AttachCuratorMediaPlugin');
        $lines = explode("\n", $contents);

        foreach ($lines as $index => $line) {
            if (! str_contains($line, 'RichEditor::make(') && ! str_contains($line, 'MarkdownEditor::make(')) {
                continue;
            }

            $editorCount++;
            $configuration = implode("\n", array_slice($lines, $index, 12));
            expect($configuration)->toContain('->fileAttachments(false)');
        }
    }

    expect($editorCount)->toBeGreaterThan(0);
});

function appOwnedMediaRecord(array $overrides = []): Media
{
    return Media::factory()->create(array_merge([
        'disk' => 'public',
        'directory' => 'content-groups/covers',
        'visibility' => 'public',
        'name' => '01J00000000000000000000000',
        'path' => 'content-groups/covers/01J00000000000000000000000.jpg',
        'width' => 100,
        'height' => 100,
        'size' => 1024,
        'type' => 'image/jpeg',
        'ext' => 'jpg',
        'title' => 'Allowed image',
    ], $overrides));
}

function appOwnedMediaFixture(string $filename): UploadedFile
{
    $encoded = file_get_contents(base_path("tests/Fixtures/media/{$filename}.base64"));

    return UploadedFile::fake()->createWithContent(
        $filename,
        base64_decode((string) $encoded, true),
    );
}

it('shows the complete image inventory and exposes repair rows through a filter', function (): void {
    $this->actingAs(User::factory()->admin()->create());

    $allowed = appOwnedMediaRecord();
    $wrongDisk = appOwnedMediaRecord([
        'disk' => 'local',
        'name' => '01J00000000000000000000001',
        'path' => 'content-groups/covers/01J00000000000000000000001.jpg',
    ]);
    $wrongType = appOwnedMediaRecord([
        'name' => '01J00000000000000000000002',
        'path' => 'content-groups/covers/01J00000000000000000000002.gif',
        'type' => 'image/gif',
        'ext' => 'gif',
    ]);
    Storage::disk('public')->put($allowed->path, 'allowed fixture');
    Storage::disk('local')->put($wrongDisk->path, 'local fixture');
    Storage::disk('public')->put($wrongType->path, 'gif fixture');

    $component = Livewire::test(ListMedia::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$allowed, $wrongDisk, $wrongType]);

    $component
        ->filterTable('needs_repair')
        ->assertCanSeeTableRecords([$wrongDisk, $wrongType])
        ->assertCanNotSeeTableRecords([$allowed]);
});

it('bounds resource pagination uploads and concurrent transfers', function (): void {
    $this->actingAs(User::factory()->admin()->create());
    Media::factory()->count(26)->create();

    $list = Livewire::test(ListMedia::class)->assertOk();

    expect($list->instance()->getTableRecords())->toHaveCount(25)
        ->and($list->instance()->getTable()->getDefaultPaginationPageOption())->toBe(25)
        ->and($list->instance()->getTable()->getPaginationPageOptions())->toBe([25]);

    $create = Livewire::test(CreateMedia::class);
    $upload = collect($create->instance()->getSchema('form')->getFlatComponents(withHidden: true))
        ->first(fn (mixed $component): bool => $component instanceof FileUpload && $component->getName() === 'uploads');

    expect($upload)->toBeInstanceOf(FileUpload::class)
        ->and($upload->getMaxFiles())->toBe(10)
        ->and($upload->getMaxParallelUploads())->toBe(2);

    $contents = base64_decode(
        (string) file_get_contents(base_path('tests/Fixtures/media/valid.jpg.base64')),
        true,
    );
    $uploads = collect(range(1, 11))
        ->map(fn (int $index): UploadedFile => UploadedFile::fake()->createWithContent(
            "image-{$index}.jpg",
            $contents,
        ))
        ->all();

    $create
        ->fillForm([
            'purpose' => ImageUploadPurpose::ContentGroupCover->value,
            'uploads' => $uploads,
        ])
        ->call('create')
        ->assertHasFormErrors(['uploads']);

    expect(Media::query()->count())->toBe(26);
});

it('uploads multiple images through shared admission without acquisition journals', function (): void {
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(CreateMedia::class)
        ->fillForm([
            'purpose' => ImageUploadPurpose::ContentGroupCover->value,
            'uploads' => [
                appOwnedMediaFixture('valid.jpg'),
                appOwnedMediaFixture('valid.png'),
            ],
            'title' => 'Imported cover',
            'alt' => 'Cover alt text',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $media = Media::query()->orderBy('id')->get();

    expect($media)->toHaveCount(2)
        ->and($media->pluck('disk')->unique()->all())->toBe(['public'])
        ->and($media->pluck('directory')->unique()->all())->toBe(['content-groups/covers'])
        ->and($media->pluck('visibility')->unique()->all())->toBe(['public'])
        ->and($media->pluck('title')->unique()->all())->toBe(['Imported cover'])
        ->and($media->every(fn (Media $record): bool => preg_match(
            '#^content-groups/covers/[0-9A-HJKMNP-TV-Z]{26}\.(?:jpg|png)$#',
            $record->path,
        ) === 1))->toBeTrue();

    expect(MediaMutationOperation::query()->count())->toBe(0)
        ->and(MediaAsset::query()->count())->toBe(2)
        ->and(MediaProviderBinding::query()->count())->toBe(2);

    foreach ($media as $record) {
        Storage::disk('public')->assertExists($record->path);
    }
});

it('restricts resource access to admins and keeps file identity immutable during metadata edits', function (): void {
    $record = appOwnedMediaRecord();

    $this->actingAs(User::factory()->role(UserRole::Moderator)->create());
    Livewire::test(ListMedia::class)->assertForbidden();

    $this->actingAs(User::factory()->admin()->create());
    Livewire::test(EditMedia::class, ['record' => $record->getRouteKey()])
        ->fillForm([
            'title' => 'Updated title',
            'alt' => 'Updated alt',
            'path' => 'header/forged.svg',
            'disk' => 'local',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $record->refresh();

    expect($record->title)->toBe('Updated title')
        ->and($record->alt)->toBe('Updated alt')
        ->and($record->disk)->toBe('public')
        ->and($record->path)->toBe('content-groups/covers/01J00000000000000000000000.jpg');
});

it('routes rename swap and delete table actions through the app coordinator', function (): void {
    $this->actingAs(User::factory()->admin()->create());
    $record = appOwnedMediaRecord();
    Storage::disk('public')->put($record->path, appOwnedMediaFixture('valid.jpg')->getContent());

    $component = Livewire::test(ListMedia::class);
    $component->callAction(TestAction::make('rename')->table($record));
    $renamed = $record->refresh();

    expect($renamed->path)->not->toBe('content-groups/covers/01J00000000000000000000000.jpg');

    $component->callAction(
        TestAction::make('swap')->table($renamed),
        ['replacement' => appOwnedMediaFixture('valid.png')],
    );
    $swapped = $record->refresh();

    expect($swapped->type)->toBe('image/png')
        ->and($swapped->ext)->toBe('png');

    $component->callAction(TestAction::make('delete')->table($swapped));

    expect(Media::query()->whereKey($record->getKey())->exists())->toBeFalse();
});

it('keeps file mutations closed for repair rows and mixed bulk deletion', function (): void {
    $this->actingAs(User::factory()->admin()->create());
    $wrongScope = appOwnedMediaRecord([
        'disk' => 'local',
        'name' => '01J00000000000000000000003',
        'path' => 'content-groups/covers/01J00000000000000000000003.jpg',
    ]);
    $deletable = appOwnedMediaRecord([
        'name' => '01J00000000000000000000004',
        'path' => 'content-groups/covers/01J00000000000000000000004.jpg',
    ]);
    $referenced = appOwnedMediaRecord([
        'name' => '01J00000000000000000000005',
        'path' => 'content-groups/covers/01J00000000000000000000005.jpg',
    ]);
    ContentGroup::factory()->create(['cover_path' => $referenced->path]);
    Storage::disk('public')->put($deletable->path, 'delete fixture');
    Storage::disk('public')->put($referenced->path, 'referenced fixture');

    Livewire::test(ListMedia::class)->assertCanSeeTableRecords([$wrongScope]);

    Livewire::test(ListMedia::class)
        ->assertActionHidden(TestAction::make('rename')->table($wrongScope))
        ->assertActionHidden(TestAction::make('swap')->table($wrongScope))
        ->assertActionHidden(TestAction::make('delete')->table($wrongScope));

    Livewire::test(ListMedia::class)
        ->selectTableRecords([$deletable, $referenced])
        ->callAction(TestAction::make('deleteSelected')->table()->bulk());

    expect(Media::query()->whereKey([$wrongScope->getKey(), $deletable->getKey(), $referenced->getKey()])->count())->toBe(3)
        ->and(MediaMutationOperation::query()->count())->toBe(0);
});
