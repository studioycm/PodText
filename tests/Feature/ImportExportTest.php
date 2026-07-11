<?php

use App\Enums\PublicationStatus;
use App\Filament\Exports\AuthorExporter;
use App\Filament\Exports\ContentGroupExporter;
use App\Filament\Exports\ContentItemExporter;
use App\Filament\Imports\AuthorImporter;
use App\Filament\Imports\ContentGroupImporter;
use App\Filament\Imports\ContentItemImporter;
use App\Models\Author;
use App\Models\Category;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\User;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Models\Export;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\Jobs\ImportCsv;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function importRecord(string $importerClass, array $row, ?array $columnMap = null, array $options = []): Import
{
    $import = Import::query()->create([
        'file_name' => 'test.csv',
        'file_path' => 'imports/test.csv',
        'importer' => $importerClass,
        'processed_rows' => 0,
        'total_rows' => 1,
        'successful_rows' => 0,
        'user_id' => User::factory()->create()->id,
    ]);

    $importer = $import->getImporter(
        $columnMap ?? array_combine(array_keys($row), array_keys($row)),
        array_merge([
            'mode' => 'upsert',
            'blank_update_behavior' => 'preserve',
        ], $options),
    );

    $importer($row);

    return $import;
}

function exportRecord(string $exporterClass, mixed $record, array $columnMap): array
{
    $export = Export::query()->create([
        'file_disk' => 'local',
        'file_name' => 'test.csv',
        'exporter' => $exporterClass,
        'processed_rows' => 0,
        'total_rows' => 1,
        'successful_rows' => 0,
        'user_id' => User::factory()->create()->id,
    ]);

    return $export->getExporter($columnMap, [])($record);
}

it('imports creates updates and generates author reference keys', function (): void {
    importRecord(AuthorImporter::class, [
        'reference_key' => '',
        'name' => 'Imported Author',
        'slug' => 'imported-author',
        'bio_markdown' => "First line\n\nSecond line",
    ]);

    $created = Author::query()->where('slug', 'imported-author')->firstOrFail();

    expect($created->reference_key)->not->toBeEmpty()
        ->and($created->bio_markdown)->toBe("First line\n\nSecond line");

    importRecord(AuthorImporter::class, [
        'reference_key' => $created->reference_key,
        'name' => 'Updated Author',
        'slug' => 'updated-author',
        'bio_markdown' => 'Updated biography',
    ]);

    expect($created->refresh())
        ->name->toBe('Updated Author')
        ->slug->toBe('updated-author')
        ->reference_key->not->toBeEmpty();
});

it('rejects invalid and duplicate author reference keys', function (): void {
    expect(fn () => importRecord(AuthorImporter::class, [
        'reference_key' => 'not-a-ulid',
        'name' => 'Invalid Key',
        'slug' => 'invalid-key',
    ]))->toThrow(ValidationException::class);

    $referenceKey = (string) Str::ulid();
    $import = Import::query()->create([
        'file_name' => 'authors.csv',
        'file_path' => 'imports/authors.csv',
        'importer' => AuthorImporter::class,
        'processed_rows' => 0,
        'total_rows' => 2,
        'successful_rows' => 0,
        'user_id' => User::factory()->create()->id,
    ]);

    $job = new ImportCsv($import, [
        ['reference_key' => $referenceKey, 'name' => 'First Author', 'slug' => 'first-author'],
        ['reference_key' => $referenceKey, 'name' => 'Second Author', 'slug' => 'second-author'],
    ], [
        'reference_key' => 'reference_key',
        'name' => 'name',
        'slug' => 'slug',
    ], [
        'mode' => 'upsert',
        'blank_update_behavior' => 'preserve',
    ]);

    $job->handle();

    expect(Author::query()->where('reference_key', $referenceKey)->count())->toBe(1)
        ->and($import->refresh()->successful_rows)->toBe(1)
        ->and($import->failedRows()->count())->toBe(1);
});

it('imports group defaults updates and rejects invalid statuses', function (): void {
    $referenceKey = (string) Str::ulid();

    importRecord(ContentGroupImporter::class, [
        'reference_key' => $referenceKey,
        'title' => 'Imported Group',
        'slug' => '',
        'group_type_label_singular' => '',
        'group_type_label_plural' => '',
        'default_item_type_label_singular' => '',
        'default_item_type_label_plural' => '',
        'description_markdown' => "תיאור\n\n**מודגש**",
        'original_language_code' => '',
        'status' => '',
        'published_at' => '',
    ]);

    $group = ContentGroup::query()->where('reference_key', $referenceKey)->firstOrFail();

    expect($group)
        ->title->toBe('Imported Group')
        ->group_type_label_singular->toBe('Podcast')
        ->group_type_label_plural->toBe('Podcasts')
        ->default_item_type_label_singular->toBe('Episode')
        ->default_item_type_label_plural->toBe('Episodes')
        ->original_language_code->toBe('he')
        ->status->toBe(PublicationStatus::Draft)
        ->description_markdown->toBe("תיאור\n\n**מודגש**");

    importRecord(ContentGroupImporter::class, [
        'reference_key' => $referenceKey,
        'title' => 'Updated Group',
        'slug' => '',
        'status' => PublicationStatus::Published->value,
    ]);

    expect($group->refresh())
        ->title->toBe('Updated Group')
        ->status->toBe(PublicationStatus::Published);

    expect(fn () => importRecord(ContentGroupImporter::class, [
        'reference_key' => (string) Str::ulid(),
        'title' => 'Invalid Status Group',
        'status' => 'archived',
    ]))->toThrow(ValidationException::class);
});

it('imports content items with group relationships', function (): void {
    $group = ContentGroup::factory()->create();

    $referenceKey = (string) Str::ulid();

    importRecord(ContentItemImporter::class, [
        'reference_key' => $referenceKey,
        'content_group_reference_key' => $group->reference_key,
        'title' => 'Imported Item',
        'slug' => 'imported-item',
        'type_label_singular_override' => '',
        'description_markdown' => 'Imported **description**',
        'media_url' => 'https://example.com/media/imported-item',
        'embed_url' => 'https://www.youtube.com/embed/imported-item',
        'duration_seconds' => '125',
        'original_published_at' => '2026-01-01 09:00:00',
        'status' => PublicationStatus::Published->value,
        'published_at' => '2026-01-01 10:00:00',
    ]);

    $item = ContentItem::query()->where('reference_key', $referenceKey)->firstOrFail();

    expect($item)
        ->content_group_id->toBe($group->id)
        ->title->toBe('Imported Item')
        ->duration_seconds->toBe(125)
        ->status->toBe(PublicationStatus::Published);
});

it('updates content items while ignoring removed item author columns', function (): void {
    $group = ContentGroup::factory()->create();
    $item = ContentItem::factory()->for($group)->create([
        'title' => 'Original Item',
        'media_url' => 'https://example.com/media/original',
    ]);

    importRecord(ContentItemImporter::class, [
        'reference_key' => $item->reference_key,
        'content_group_reference_key' => $group->reference_key,
        'title' => 'Updated Item',
        'media_url' => 'https://example.com/media/updated',
    ], [
        'reference_key' => 'reference_key',
        'content_group_reference_key' => 'content_group_reference_key',
        'title' => 'title',
        'media_url' => 'media_url',
    ]);

    expect($item->refresh())
        ->title->toBe('Updated Item');

    importRecord(ContentItemImporter::class, [
        'reference_key' => $item->reference_key,
        'content_group_reference_key' => $group->reference_key,
        'title' => 'Updated Again',
        'media_url' => 'https://example.com/media/updated-again',
        'author_reference_keys' => '',
    ]);

    expect($item->refresh()->title)->toBe('Updated Again');
});

it('fails content item rows with unresolved relationships or invalid embed urls', function (): void {
    $group = ContentGroup::factory()->create();

    expect(fn () => importRecord(ContentItemImporter::class, [
        'reference_key' => (string) Str::ulid(),
        'content_group_reference_key' => (string) Str::ulid(),
        'title' => 'Missing Group',
        'media_url' => 'https://example.com/media/missing-group',
    ]))->toThrow(RowImportFailedException::class);

    expect(fn () => importRecord(ContentItemImporter::class, [
        'reference_key' => (string) Str::ulid(),
        'content_group_reference_key' => $group->reference_key,
        'title' => 'Bad Media',
        'media_url' => 'http://example.com/media/bad-media',
    ]))->toThrow(ValidationException::class);

    expect(fn () => importRecord(ContentItemImporter::class, [
        'reference_key' => (string) Str::ulid(),
        'content_group_reference_key' => $group->reference_key,
        'title' => 'Bad Embed',
        'media_url' => 'https://example.com/media/bad-embed',
        'embed_url' => 'https://unapproved.example/embed',
    ]))->toThrow(ValidationException::class);
});

it('continues importing valid rows when another row fails', function (): void {
    $import = Import::query()->create([
        'file_name' => 'groups.csv',
        'file_path' => 'imports/groups.csv',
        'importer' => ContentGroupImporter::class,
        'processed_rows' => 0,
        'total_rows' => 2,
        'successful_rows' => 0,
        'user_id' => User::factory()->create()->id,
    ]);

    $job = new ImportCsv($import, [
        [
            'reference_key' => (string) Str::ulid(),
            'title' => 'Valid Imported Group',
            'slug' => 'valid-imported-group',
            'status' => PublicationStatus::Draft->value,
        ],
        [
            'reference_key' => (string) Str::ulid(),
            'title' => 'Invalid Imported Group',
            'slug' => 'invalid-imported-group',
            'status' => 'invalid',
        ],
    ], [
        'reference_key' => 'reference_key',
        'title' => 'title',
        'slug' => 'slug',
        'status' => 'status',
    ], [
        'mode' => 'upsert',
        'blank_update_behavior' => 'preserve',
    ]);

    $job->handle();

    expect(ContentGroup::query()->where('title', 'Valid Imported Group')->exists())->toBeTrue()
        ->and(ContentGroup::query()->where('title', 'Invalid Imported Group')->exists())->toBeFalse()
        ->and($import->refresh()->processed_rows)->toBe(2)
        ->and($import->successful_rows)->toBe(1)
        ->and($import->failedRows()->count())->toBe(1);
});

it('defines expected export columns and disables large optional content by default', function (): void {
    $authorColumns = collect(AuthorExporter::getColumns());
    $groupColumns = collect(ContentGroupExporter::getColumns());
    $itemColumns = collect(ContentItemExporter::getColumns());
    $descriptionMarkdownColumn = $groupColumns->first(
        fn (ExportColumn $column): bool => $column->getName() === 'description_markdown',
    );

    expect($descriptionMarkdownColumn)->toBeInstanceOf(ExportColumn::class);
    assert($descriptionMarkdownColumn instanceof ExportColumn);

    expect($authorColumns->map->getName()->all())->toBe([
        'reference_key',
        'name',
        'slug',
        'bio_markdown',
        'created_at',
        'updated_at',
    ])
        ->and($groupColumns->map->getName()->all())->toContain('description_markdown', 'cover_path')
        ->and($descriptionMarkdownColumn->isEnabledByDefault())->toBeFalse()
        ->and($itemColumns->map->getName()->all())->toContain('content_group_reference_key')
        ->and($itemColumns->map->getName()->all())->not->toContain('author_reference_keys')
        ->and($itemColumns->map->getName()->all())->not->toContain('transcript_markdown');
});

it('eager loads content group export category paths for queued records', function (): void {
    $parent = Category::factory()->create([
        'slug' => 'parent-category',
    ]);
    $child = Category::factory()->for($parent, 'parent')->create([
        'slug' => 'child-category',
    ]);
    $group = ContentGroup::factory()->create();
    $group->categories()->attach($child);

    $record = ContentGroupExporter::modifyQuery(ContentGroup::query())->findOrFail($group->getKey());

    Model::preventLazyLoading();

    try {
        $exported = exportRecord(ContentGroupExporter::class, $record, [
            'category_paths' => 'category_paths',
        ]);
    } finally {
        Model::preventLazyLoading(! app()->isProduction());
    }

    expect($exported)->toBe(['parent-category/child-category']);
});

it('exports relationship reference keys and escapes spreadsheet formula text', function (): void {
    $group = ContentGroup::factory()->create();
    $item = ContentItem::factory()->for($group)->create([
        'title' => '=HYPERLINK("https://example.com")',
    ]);

    $exportedItem = exportRecord(ContentItemExporter::class, $item->load(['contentGroup']), [
        'content_group_reference_key' => 'content_group_reference_key',
        'title' => 'title',
    ]);

    expect($exportedItem)->toBe([
        $group->reference_key,
        '\'=HYPERLINK("https://example.com")',
    ]);

    $author = Author::factory()->create(['name' => '@SUM(1,1)']);

    expect(exportRecord(AuthorExporter::class, $author, [
        'name' => 'name',
    ]))->toBe(["'@SUM(1,1)"]);
});

it('denies public access to admin import and export surfaces and protects generated files by owner', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    $export = Export::query()->create([
        'file_disk' => 'local',
        'file_name' => 'exports/test.csv',
        'exporter' => AuthorExporter::class,
        'processed_rows' => 1,
        'total_rows' => 1,
        'successful_rows' => 1,
        'user_id' => $owner->id,
    ]);
    $import = Import::query()->create([
        'file_name' => 'imports/test.csv',
        'file_path' => 'imports/test.csv',
        'importer' => AuthorImporter::class,
        'processed_rows' => 1,
        'total_rows' => 1,
        'successful_rows' => 0,
        'user_id' => $owner->id,
    ]);

    $this->get('/admin/authors')->assertRedirect();
    $this->get('/')->assertDontSee('Import')->assertDontSee('Export');

    $this->actingAs($other)
        ->get(route('filament.exports.download', $export))
        ->assertForbidden();

    $this->actingAs($other)
        ->get(route('filament.imports.failed-rows.download', $import))
        ->assertForbidden();
});
