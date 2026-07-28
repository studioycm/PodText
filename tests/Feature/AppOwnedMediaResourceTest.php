<?php

use App\Enums\ImageUploadPurpose;
use App\Enums\MediaDiagnosticReason;
use App\Enums\MediaLibraryTask;
use App\Enums\UserRole;
use App\Filament\Resources\Media\MediaResource;
use App\Filament\Resources\Media\Pages\CreateMedia;
use App\Filament\Resources\Media\Pages\EditMedia;
use App\Filament\Resources\Media\Pages\ListMedia;
use App\Filament\Resources\Media\Schemas\MediaForm;
use App\Filament\Resources\Media\Tables\MediaTable;
use App\Models\ContentGroup;
use App\Models\Media;
use App\Models\MediaAsset;
use App\Models\MediaAttachment;
use App\Models\MediaMutationOperation;
use App\Models\MediaProviderBinding;
use App\Models\User;
use App\Support\Media\MediaInventoryDiagnostics;
use App\Support\Media\MediaRecordProjector;
use Awcodes\Curator\Config\CurationManager;
use Awcodes\Curator\Facades\Curator;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\RestrictsFileUploadsToSchemaComponents;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Layout\Grid as TableGrid;
use Filament\Tables\Enums\RecordActionsPosition;
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

it('shows the complete image inventory and exposes diagnostic rows through the Needs Attention task', function (): void {
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
        ->set('activeTab', 'needs_attention')
        ->assertCanSeeTableRecords([$wrongDisk, $wrongType])
        ->assertCanNotSeeTableRecords([$allowed]);
});

it('renders Media as a native responsive card gallery without losing table controls', function (): void {
    $this->actingAs(User::factory()->admin()->create());
    $media = appOwnedMediaRecord([
        'title' => null,
        'name' => 'stored-card-name',
        'path' => 'content-groups/covers/stored-card-name.jpg',
        'visibility' => 'private',
        'exif' => ['original_filename' => 'Original display filename.jpg'],
    ]);
    DB::table($media->getTable())
        ->where($media->getKeyName(), $media->getKey())
        ->update(['reference_key' => null]);
    $media->refresh();
    Storage::disk('public')->put($media->path, 'card fixture');
    ContentGroup::factory()->create([
        'title' => 'First known reference',
        'cover_path' => $media->path,
    ]);
    ContentGroup::factory()->create([
        'title' => 'Second known reference',
        'cover_path' => $media->path,
    ]);
    $reasons = app(MediaInventoryDiagnostics::class)->reasons($media);

    $component = Livewire::test(ListMedia::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$media]);
    $table = $component->instance()->getTable();
    $columnsLayout = array_values($table->getColumnsLayout());
    $recordActions = array_values($table->getRecordActions());

    $editAction = $table->getAction('edit');
    assert($editAction instanceof EditAction);
    $editAction->record($media);

    expect($table->getRecordUrl($media))->toBeNull()
        ->and($table->getRecordAction($media))->toBe('mediaDetails')
        ->and($editAction->getUrl())->toBe($component->instance()->editUrlForMedia($media));

    $component->assertSeeInOrder([
        'Original display filename.jpg',
        'stored-card-name.jpg',
        trans_choice('admin.media_library.known_reference_count', 2, ['count' => 2]),
        __('admin.media_library.needs_attention'),
        __('admin.media_library.repair_portable_identity'),
        trans_choice('admin.media_library.additional_issue_count', 1, ['count' => 1]),
        'image/jpeg',
    ]);

    expect($reasons)->toBe(['portable_identity', 'audience_denied'])
        ->and($table->getContentGrid())->toBe([
            'md' => 2,
            'lg' => 3,
            '2xl' => 4,
        ])
        ->and($columnsLayout[0] ?? null)->toBeInstanceOf(TableGrid::class)
        ->and($recordActions)->toHaveCount(3)
        ->and($recordActions[0]->getName())->toBe('mediaDetails')
        ->and($recordActions[1])->toBeInstanceOf(EditAction::class)
        ->and($recordActions[1]->getName())->toBe('edit')
        ->and($recordActions[1]->isButton())->toBeTrue()
        ->and($recordActions[1]->isLabelHidden())->toBeFalse()
        ->and((string) $recordActions[0]->getLabel())->toBe(__('admin.owner_image.actions.open_details'))
        ->and((string) $recordActions[1]->getLabel())->toBe(__('admin.media_library.open_details'))
        ->and($recordActions[2])->toBeInstanceOf(ActionGroup::class)
        ->and(array_keys($recordActions[2]->getFlatActions()))->toBe([
            'view',
            'download',
            'rename',
            'swap',
            'delete',
        ])
        ->and(array_keys($table->getFlatRecordActions()))->toBe([
            'mediaDetails',
            'edit',
            'view',
            'download',
            'rename',
            'swap',
            'delete',
        ])
        ->and($table->getRecordActionsPosition())->toBe(RecordActionsPosition::AfterContent)
        ->and($table->getFilters())->toHaveKeys(['type', 'reason'])
        ->and($table->getToolbarActions())->toHaveCount(1)
        ->and($table->getDefaultPaginationPageOption())->toBe(25)
        ->and($table->getDefaultSortOptionLabel())
        ->toBe(__('admin.media_library.added_newest_first'));
});

it('configures the five canonical native tasks with only the two approved badges', function (): void {
    $this->actingAs(User::factory()->admin()->create());
    $ready = appOwnedMediaRecord([
        'name' => 'canonical-ready',
        'path' => 'content-groups/covers/canonical-ready.jpg',
    ]);
    $missing = appOwnedMediaRecord([
        'name' => 'canonical-missing',
        'path' => 'content-groups/covers/canonical-missing.jpg',
    ]);
    Storage::disk('public')->put($ready->path, 'ready fixture');

    $component = Livewire::test(ListMedia::class)->assertOk();
    $tabs = $component->instance()->getCachedTabs();

    expect(array_keys($tabs))->toBe(array_column(MediaLibraryTask::cases(), 'value'))
        ->and(collect($tabs)->map(fn ($tab): string => (string) $tab->getLabel())->all())
        ->toBe(collect(MediaLibraryTask::cases())
            ->mapWithKeys(fn (MediaLibraryTask $task): array => [$task->value => $task->getLabel()])
            ->all())
        ->and($tabs['all']->getBadge())->toBe('2')
        ->and($tabs['no_direct_attachment']->getBadge())->toBe('2')
        ->and($tabs['in_use']->getBadge())->toBeNull()
        ->and($tabs['needs_attention']->getBadge())->toBeNull()
        ->and($tabs['recent']->getBadge())->toBeNull()
        ->and($component->instance()->getTable()->getDescription())
        ->toBe(MediaLibraryTask::All->description());

    $component
        ->set('activeTab', MediaLibraryTask::NeedsAttention->value)
        ->assertCanSeeTableRecords([$missing])
        ->assertCanNotSeeTableRecords([$ready]);

    expect($component->instance()->getTable()->getDescription())
        ->toBe(MediaLibraryTask::NeedsAttention->description());
});

it('sorts Added newest or oldest with a deterministic Media key tie break', function (): void {
    $this->actingAs(User::factory()->admin()->create());
    $createdAt = now()->startOfSecond();
    $first = appOwnedMediaRecord([
        'name' => 'sort-first',
        'path' => 'content-groups/covers/sort-first.jpg',
        'created_at' => $createdAt,
    ]);
    $second = appOwnedMediaRecord([
        'name' => 'sort-second',
        'path' => 'content-groups/covers/sort-second.jpg',
        'created_at' => $createdAt,
    ]);
    Storage::disk('public')->put($first->path, 'first');
    Storage::disk('public')->put($second->path, 'second');

    $component = Livewire::test(ListMedia::class);

    expect($component->instance()->getTableRecords()->pluck('id')->all())->toBe([
        $second->getKey(),
        $first->getKey(),
    ]);

    $component->set('tableSort', 'created_at:asc');

    expect($component->instance()->getTableRecords()->pluck('id')->all())->toBe([
        $first->getKey(),
        $second->getKey(),
    ]);
});

it('composes task MIME reason search sort page and one canonical details context', function (): void {
    $this->actingAs(User::factory()->admin()->create());
    $matching = appOwnedMediaRecord([
        'title' => 'Cover target',
        'name' => 'matching-context',
        'path' => 'content-groups/covers/matching-context.jpg',
    ]);
    $wrongMime = appOwnedMediaRecord([
        'title' => 'Cover target PNG',
        'name' => 'wrong-mime-context',
        'path' => 'content-groups/covers/wrong-mime-context.png',
        'type' => 'image/png',
        'ext' => 'png',
    ]);
    $healthy = appOwnedMediaRecord([
        'title' => 'Cover target healthy',
        'name' => 'healthy-context',
        'path' => 'content-groups/covers/healthy-context.jpg',
    ]);
    Storage::disk('public')->put($healthy->path, 'healthy fixture');

    $component = Livewire::test(ListMedia::class)
        ->set('activeTab', MediaLibraryTask::NeedsAttention->value)
        ->filterTable('type', 'image/jpeg')
        ->filterTable('reason', MediaDiagnosticReason::MissingFile)
        ->searchTable('Cover target')
        ->set('tableSort', 'created_at:asc')
        ->assertCanSeeTableRecords([$matching])
        ->assertCanNotSeeTableRecords([$wrongMime, $healthy]);

    $component->call('setPage', 2);
    $url = $component->instance()->editUrlForMedia($matching);
    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
    $expectedContext = [
        'v' => '1',
        'task' => 'needs_attention',
        'mime' => 'image/jpeg',
        'reason' => 'missing_file',
        'search' => 'Cover target',
        'sort' => 'created_at:asc',
        'page' => '2',
        'focus' => (string) $matching->getKey(),
    ];
    $table = $component->instance()->getTable();
    $editAction = $table->getAction('edit');
    assert($editAction instanceof EditAction);
    $editAction->record($matching);

    expect($query['from'] ?? null)->toBe($expectedContext)
        ->and($table->getRecordUrl($matching))->toBeNull()
        ->and($table->getRecordAction($matching))->toBe('mediaDetails')
        ->and($editAction->getUrl())->toBe($url);

    $component
        ->removeTableFilter('reason')
        ->assertSet('activeTab', MediaLibraryTask::NeedsAttention->value)
        ->assertSet('tableSearch', 'Cover target')
        ->assertSet('tableSort', 'created_at:asc');

    expect(data_get($component->get('tableFilters'), 'type.value'))->toBe('image/jpeg')
        ->and(data_get($component->get('tableFilters'), 'reason.value'))->toBeNull()
        ->and($component->instance()->getTablePage())->toBe(1);
});

it('fails closed for a forged non-image MIME filter value', function (): void {
    $this->actingAs(User::factory()->admin()->create());
    $forgedType = appOwnedMediaRecord([
        'name' => 'forged-mime',
        'path' => 'legacy/forged-mime.pdf',
        'directory' => 'legacy',
        'type' => 'application/pdf',
        'ext' => 'pdf',
    ]);

    Livewire::test(ListMedia::class)
        ->filterTable('type', 'application/pdf')
        ->assertCanNotSeeTableRecords([$forgedType]);
});

it('reconstructs one safe Media Library destination for Back and Cancel while Save stays on Edit', function (): void {
    $this->actingAs(User::factory()->admin()->create());
    $media = appOwnedMediaRecord([
        'title' => 'Context edit target',
        'name' => 'context-edit-target',
        'path' => 'content-groups/covers/context-edit-target.jpg',
    ]);
    $from = [
        'v' => 1,
        'task' => 'needs_attention',
        'mime' => 'image/jpeg',
        'reason' => 'missing_file',
        'search' => 'Context edit',
        'sort' => 'created_at:asc',
        'page' => 2,
        'focus' => $media->getKey(),
    ];
    $component = Livewire::withQueryParams(['from' => $from])
        ->test(EditMedia::class, ['record' => $media->getRouteKey()])
        ->assertOk();
    $expected = MediaResource::getUrl('index', [
        'tab' => 'needs_attention',
        'filters' => [
            'type' => ['value' => 'image/jpeg'],
            'reason' => ['value' => 'missing_file'],
        ],
        'search' => 'Context edit',
        'sort' => 'created_at:asc',
        'page' => 2,
        'focus' => $media->getKey(),
    ]).'#media-record-'.$media->getKey();
    $back = collect($component->instance()->getCachedHeaderActions())
        ->first(fn (Action $action): bool => $action->getName() === 'backToMediaLibrary');
    $cancel = (fn (): Action => $this->getCancelFormAction())
        ->call($component->instance());

    expect($component->instance()->mediaLibraryReturnUrl())->toBe($expected)
        ->and($back)->toBeInstanceOf(Action::class)
        ->and($back?->getUrl())->toBe($expected)
        ->and($back?->getIcon())->toBe(Heroicon::OutlinedPhoto)
        ->and($cancel->getUrl())->toBe($expected)
        ->and($cancel->getAlpineClickHandler())->toBeNull();

    $component
        ->fillForm(['title' => 'Saved in place'])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNoRedirect();

    expect($media->refresh()->title)->toBe('Saved in place')
        ->and($component->instance()->mediaLibraryReturnUrl())->toBe($expected);
});

it('normalizes malformed native Media list query state without an exception', function (
    array $query,
): void {
    $this->actingAs(User::factory()->admin()->create());
    appOwnedMediaRecord();

    Livewire::withQueryParams($query)
        ->test(ListMedia::class)
        ->assertOk()
        ->assertSet('activeTab', MediaLibraryTask::All->value)
        ->assertSet('tableSearch', '')
        ->assertSet('tableSort', null)
        ->assertSet('focus', null);
})->with([
    'array tab' => [['tab' => ['needs_attention']]],
    'array sort' => [['sort' => ['created_at:asc']]],
    'array search' => [['search' => ['cover']]],
    'array MIME value' => [['filters' => ['type' => ['value' => ['image/jpeg']]]]],
    'array focus' => [['focus' => ['1']]],
    'unknown values' => [[
        'tab' => 'trash',
        'sort' => 'title:desc',
        'search' => str_repeat('x', 201),
        'filters' => ['reason' => ['value' => 'fix_everything']],
        'page' => 0,
        'focus' => 'not-an-id',
    ]],
]);

it('normalizes an invalid task received after the Media list has mounted', function (): void {
    $this->actingAs(User::factory()->admin()->create());
    $media = appOwnedMediaRecord();

    Livewire::test(ListMedia::class)
        ->set('activeTab', 'trash')
        ->assertSet('activeTab', MediaLibraryTask::All->value)
        ->assertCanSeeTableRecords([$media]);
});

it('refreshes the two task badges after successful existing delete actions', function (): void {
    $this->actingAs(User::factory()->admin()->create());
    $records = collect(range(1, 3))->map(function (int $index): Media {
        $media = appOwnedMediaRecord([
            'name' => "badge-delete-{$index}",
            'path' => "content-groups/covers/badge-delete-{$index}.jpg",
        ]);
        Storage::disk('public')->put($media->path, 'deletable fixture');

        return $media;
    });
    $component = Livewire::test(ListMedia::class);

    expect($component->instance()->getCachedTabs()['all']->getBadge())->toBe('3')
        ->and($component->instance()->getCachedTabs()['no_direct_attachment']->getBadge())->toBe('3');

    $component->callAction(TestAction::make('delete')->table($records->shift()));

    expect($component->instance()->getCachedTabs()['all']->getBadge())->toBe('2')
        ->and($component->instance()->getCachedTabs()['no_direct_attachment']->getBadge())->toBe('2');

    $component
        ->selectTableRecords($records)
        ->callAction(TestAction::make('deleteSelected')->table()->bulk());

    expect($component->instance()->getCachedTabs()['all']->getBadge())->toBe('0')
        ->and($component->instance()->getCachedTabs()['no_direct_attachment']->getBadge())->toBe('0');
});

it('describes zero known references without claiming that Media is unused', function (): void {
    $this->actingAs(User::factory()->admin()->create());
    $media = appOwnedMediaRecord([
        'title' => 'No known references fixture',
        'name' => 'no-known-references',
        'path' => 'content-groups/covers/no-known-references.jpg',
    ]);
    Storage::disk('public')->put($media->path, 'unreferenced fixture');

    Livewire::test(ListMedia::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$media])
        ->assertSee(trans_choice('admin.media_library.known_reference_count', 0, ['count' => 0]))
        ->assertDontSee('Unused');
});

it('uses every stable Media card identity fallback in order', function (
    array $attributes,
    string $expected,
): void {
    $this->actingAs(User::factory()->admin()->create());
    $column = Livewire::test(ListMedia::class)
        ->instance()
        ->getTable()
        ->getColumn('card_title');
    $media = appOwnedMediaRecord();
    $media->forceFill(array_merge([
        'title' => null,
        'exif' => [],
        'path' => '',
        'name' => '',
    ], $attributes));

    $identity = new ReflectionMethod(MediaTable::class, 'displayIdentity');

    expect($identity->invoke(null, $media))->toBe($expected);
})->with([
    'title' => [
        ['title' => 'Human title', 'exif' => ['original_filename' => 'Original.jpg'], 'path' => 'stored.jpg', 'name' => 'stored'],
        'Human title',
    ],
    'original filename' => [
        ['exif' => ['original_filename' => 'Original.jpg'], 'path' => 'stored.jpg', 'name' => 'stored'],
        'Original.jpg',
    ],
    'stored basename' => [
        ['path' => 'content-groups/covers/stored.jpg', 'name' => 'stored'],
        'stored.jpg',
    ],
    'Media name' => [
        ['name' => 'stored-name'],
        'stored-name',
    ],
]);

it('does not claim zero known references when the bounded reference count is unavailable', function (): void {
    $this->actingAs(User::factory()->admin()->create());
    $media = appOwnedMediaRecord([
        'disk' => 'local',
        'title' => 'Attached non-public fixture',
        'name' => 'attached-non-public',
        'path' => 'content-groups/covers/attached-non-public.jpg',
    ]);
    $group = ContentGroup::factory()->create();
    MediaAttachment::factory()->create([
        'media_id' => $media->getKey(),
        'attachable_id' => $group->getKey(),
    ]);
    Storage::disk('local')->put($media->path, 'non-public fixture');

    Livewire::test(ListMedia::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$media])
        ->assertSee(__('admin.media_library.known_reference_count_unavailable'))
        ->assertDontSee(trans_choice('admin.media_library.known_reference_count', 0, ['count' => 0]));
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
        ->and($media->pluck('title')->unique()->all())->toBe(['Imported cover valid'])
        ->and($media->pluck('alt')->unique()->all())->toBe(['Cover alt text valid'])
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
                    'failed' => 1,
                    'not_attempted' => 0,
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

it('prefixes normalized per-file titles and alt in a multi upload and keeps single uploads verbatim', function (): void {
    $this->actingAs(User::factory()->admin()->create());
    $fixtureAs = static function (string $source, string $as): UploadedFile {
        $encoded = file_get_contents(base_path("tests/Fixtures/media/{$source}.base64"));

        return UploadedFile::fake()->createWithContent($as, base64_decode((string) $encoded, true));
    };

    Livewire::test(CreateMedia::class)
        ->fillForm([
            'purpose' => ImageUploadPurpose::ContentGroupCover->value,
            'uploads' => [
                $fixtureAs('valid.jpg', 'season-two_cover (1).jpg'),
                $fixtureAs('valid.png', 'season-two_cover (2).png'),
            ],
            'title' => 'עונה 2',
            'alt' => 'עטיפת עונה 2',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Media::query()->orderBy('id')->pluck('title')->all())
        ->toBe(['עונה 2 season two cover (1)', 'עונה 2 season two cover (2)'])
        ->and(Media::query()->orderBy('id')->pluck('alt')->all())
        ->toBe(['עטיפת עונה 2 season two cover (1)', 'עטיפת עונה 2 season two cover (2)']);

    Media::query()->forceDelete();

    Livewire::test(CreateMedia::class)
        ->fillForm([
            'purpose' => ImageUploadPurpose::ContentGroupCover->value,
            'uploads' => [$fixtureAs('valid.jpg', 'solo.jpg')],
            'title' => 'כותרת מדויקת',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Media::query()->sole()->title)->toBe('כותרת מדויקת');
});

it('renames a media title inline from the gallery card', function (): void {
    $this->actingAs(User::factory()->admin()->create());
    $media = appOwnedMediaRecord(['title' => null]);
    Storage::disk('public')->put($media->path, 'inline title fixture');

    Livewire::test(ListMedia::class)
        ->assertSee('data-testid="media-library-card-title-input"', false)
        ->assertSee(__('admin.media_library.inline_title_placeholder'))
        ->call('updateTableColumnState', 'title', (string) $media->getKey(), 'כותרת מהגלריה');

    expect($media->refresh()->title)->toBe('כותרת מהגלריה');
});

it('returns to the gallery after creating uploads instead of the first record edit page', function (): void {
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(CreateMedia::class)
        ->fillForm([
            'purpose' => ImageUploadPurpose::ContentGroupCover->value,
            'uploads' => [appOwnedMediaFixture('valid.jpg')],
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertRedirect(MediaResource::getUrl('index'));
});
