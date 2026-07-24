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
use App\Support\Media\MediaInventoryDiagnostics;
use App\Support\Media\MediaRecordProjector;
use Awcodes\Curator\Config\CurationManager;
use Awcodes\Curator\Facades\Curator;
use Filament\Actions\Action;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\RestrictsFileUploadsToSchemaComponents;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Layout\Grid as TableGrid;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
    $media = Media::factory()->create(array_merge([
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
    assert($media instanceof Media);

    return $media;
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

it('renders Media as a native responsive card gallery without losing table controls', function (): void {
    $this->actingAs(User::factory()->admin()->create());
    $media = appOwnedMediaRecord([
        'title' => 'Original display filename.jpg',
        'name' => 'stored-card-name',
        'path' => 'content-groups/covers/stored-card-name.jpg',
        'exif' => ['original_filename' => 'Original display filename.jpg'],
    ]);
    Storage::disk('public')->put($media->path, 'card fixture');

    $component = Livewire::test(ListMedia::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$media])
        ->assertSee('Original display filename.jpg')
        ->assertSee('stored-card-name.jpg')
        ->assertSee('image/jpeg')
        ->assertSee(__('admin.media_library.ready'));
    $table = $component->instance()->getTable();
    $columnsLayout = array_values($table->getColumnsLayout());

    expect($table->getContentGrid())->toBe([
        'md' => 2,
        'lg' => 3,
        '2xl' => 4,
    ])
        ->and($columnsLayout[0] ?? null)->toBeInstanceOf(TableGrid::class)
        ->and($table->getRecordActions())->toHaveCount(6)
        ->and($table->getFilters())->toHaveKeys(['type', 'needs_repair'])
        ->and($table->getToolbarActions())->toHaveCount(1)
        ->and($table->getDefaultPaginationPageOption())->toBe(25);
});

it('reuses one request-local filesystem existence decision per projected raster', function (int $recordCount): void {
    $records = collect(range(1, $recordCount))
        ->map(fn (int $index): Media => appOwnedMediaRecord([
            'reference_key' => (string) Str::ulid(),
            'name' => "probe-{$index}",
            'path' => "content-groups/covers/probe-{$index}.jpg",
        ]));
    $disk = Mockery::mock(FilesystemAdapter::class);
    $disk->shouldReceive('exists')
        ->times($recordCount)
        ->andReturnTrue();
    Storage::shouldReceive('disk')
        ->times($recordCount)
        ->with('public')
        ->andReturn($disk);
    app()->forgetInstance(MediaInventoryDiagnostics::class);
    $projector = app(MediaRecordProjector::class);

    $records->each(fn (Media $media): array => $projector->project($media));
})->with([1, 10, 25]);

it('keeps Media card authorization reference queries bounded by page not record count', function (int $recordCount): void {
    $this->actingAs(User::factory()->admin()->create());
    $records = collect(range(1, $recordCount))
        ->map(function (int $index): Media {
            $media = appOwnedMediaRecord([
                'reference_key' => (string) Str::ulid(),
                'name' => "query-budget-{$index}",
                'path' => "content-groups/covers/query-budget-{$index}.jpg",
            ]);
            Storage::disk('public')->put($media->path, 'query budget fixture');

            return $media;
        });
    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        $sql = mb_strtolower($query->sql);

        if (
            str_contains($sql, 'media_attachments')
            || str_contains($sql, 'content_groups')
            || str_contains($sql, 'content_items')
            || str_contains($sql, 'settings')
            || (str_contains($sql, 'curator') && str_contains($sql, 'path'))
        ) {
            $queries[] = $query->sql;
        }
    });

    Livewire::test(ListMedia::class)
        ->assertOk()
        ->assertCanSeeTableRecords($records)
        ->html();

    expect(count($queries))->toBeLessThanOrEqual(
        20,
        json_encode($queries, JSON_THROW_ON_ERROR),
    );
})->with([1, 10, 25]);

it('bounds resource pagination uploads and concurrent transfers', function (): void {
    $this->actingAs(User::factory()->admin()->create());
    Media::factory()->count(26)->create();

    $list = Livewire::test(ListMedia::class)
        ->assertOk()
        ->assertActionExists('create', function (Action $action): bool {
            return $action->getLabel() === __('admin.media_library.upload_multiple')
                && $action->getIcon() === Heroicon::ArrowUpTray;
        });

    expect($list->instance()->getTableRecords())->toHaveCount(25)
        ->and($list->instance()->getTable()->getDefaultPaginationPageOption())->toBe(25)
        ->and($list->instance()->getTable()->getPaginationPageOptions())->toBe([25]);

    $create = Livewire::test(CreateMedia::class)
        ->assertSee(__('admin.media_library.batch_files_help'));
    $upload = collect($create->instance()->getSchema('form')->getFlatComponents(withHidden: true))
        ->first(fn (mixed $component): bool => $component instanceof FileUpload && $component->getName() === 'uploads');

    assert($upload instanceof FileUpload);

    expect($upload)->toBeInstanceOf(FileUpload::class)
        ->and($upload->getLabel())->toBe(__('admin.media_library.batch_files'))
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

it('reports a partial Media Resource batch without removing an earlier permanent item', function (): void {
    $this->actingAs(User::factory()->admin()->create());
    $bindingAttempts = 0;

    Event::listen('eloquent.creating: '.MediaProviderBinding::class, function () use (&$bindingAttempts): void {
        $bindingAttempts++;

        if ($bindingAttempts === 2) {
            throw new RuntimeException('second binding failed');
        }
    });

    Livewire::test(CreateMedia::class)
        ->fillForm([
            'purpose' => ImageUploadPurpose::ContentGroupCover->value,
            'uploads' => [
                appOwnedMediaFixture('valid.jpg'),
                appOwnedMediaFixture('valid.png'),
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertNotified(
            Notification::make()
                ->warning()
                ->title(__('admin.media_library.upload_partial_title'))
                ->body(__('admin.media_library.upload_partial_body', [
                    'added' => 1,
                    'not_added' => 1,
                ])),
        );

    $media = Media::query()->sole();

    expect(MediaAsset::query()->count())->toBe(1)
        ->and(MediaProviderBinding::query()->count())->toBe(1);
    Storage::disk('public')->assertExists($media->path);
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
