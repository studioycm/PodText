<?php

use App\Enums\MediaAttachmentRole;
use App\Enums\PublicationStatus;
use App\Filament\Exports\AuthorExporter;
use App\Filament\Exports\CategoryExporter;
use App\Filament\Exports\ContentGroupExporter;
use App\Filament\Exports\ContentItemExporter;
use App\Filament\Exports\TranscriptionExporter;
use App\Filament\Imports\AuthorImporter;
use App\Filament\Imports\ContentGroupImporter;
use App\Filament\Imports\ContentItemImporter;
use App\Models\Author;
use App\Models\Category;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\ContentTag;
use App\Models\Media;
use App\Models\MediaAttachment;
use App\Models\Transcription;
use App\Models\User;
use App\Support\Media\MediaAttachmentManager;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Models\Export;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\Jobs\ImportCsv;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('public');
});

function importRecord(string $importerClass, array $row, ?array $columnMap = null, array $options = [], ?User $user = null): Import
{
    $import = Import::query()->create([
        'file_name' => 'test.csv',
        'file_path' => 'imports/test.csv',
        'importer' => $importerClass,
        'processed_rows' => 0,
        'total_rows' => 1,
        'successful_rows' => 0,
        'user_id' => ($user ?? User::factory()->create())->id,
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

it('round trips group and item image attachments through portable media reference keys', function (): void {
    $admin = User::factory()->admin()->create();
    $cover = Media::factory()->create();
    $primaryName = (string) Str::ulid();
    $primary = Media::factory()->create([
        'directory' => MediaAttachmentRole::PrimaryImage->purpose()->root(),
        'name' => $primaryName,
        'path' => MediaAttachmentRole::PrimaryImage->purpose()->root()."/{$primaryName}.jpg",
    ]);
    Storage::disk('public')->put($cover->path, 'cover-image-bytes');
    Storage::disk('public')->put($primary->path, 'primary-image-bytes');
    $groupReference = (string) Str::ulid();
    $itemReference = (string) Str::ulid();

    importRecord(ContentGroupImporter::class, [
        'reference_key' => $groupReference,
        'title' => 'Portable Group',
        'cover_media_reference_key' => $cover->reference_key,
    ], options: [], user: $admin);
    $group = ContentGroup::query()->where('reference_key', $groupReference)->firstOrFail();

    importRecord(ContentItemImporter::class, [
        'reference_key' => $itemReference,
        'content_group_reference_key' => $group->reference_key,
        'title' => 'Portable Item',
        'media_url' => 'https://example.com/portable-item',
        'primary_image_media_reference_key' => $primary->reference_key,
    ], options: [], user: $admin);
    $item = ContentItem::query()->where('reference_key', $itemReference)->firstOrFail();

    expect($group->coverMediaAttachment()->firstOrFail()->media_id)->toBe($cover->getKey())
        ->and($group->coverMediaAttachment()->with('media')->first()?->media?->path)->toBe($cover->path)
        ->and($item->primaryImageMediaAttachment()->firstOrFail()->media_id)->toBe($primary->getKey())
        ->and($item->primaryImageMediaAttachment()->with('media')->first()?->media?->path)->toBe($primary->path);

    $exportedGroup = exportRecord(
        ContentGroupExporter::class,
        ContentGroupExporter::modifyQuery(ContentGroup::query())->findOrFail($group->getKey()),
        ['cover_media_reference_key' => 'cover_media_reference_key'],
    );
    $exportedItem = exportRecord(
        ContentItemExporter::class,
        ContentItemExporter::modifyQuery(ContentItem::query())->findOrFail($item->getKey()),
        ['primary_image_media_reference_key' => 'primary_image_media_reference_key'],
    );

    expect($exportedGroup)->toBe([$cover->reference_key])
        ->and($exportedItem)->toBe([$primary->reference_key])
        ->and($exportedGroup)->not->toContain($cover->path, $cover->getKey())
        ->and($exportedItem)->not->toContain($primary->path, $primary->getKey());

    importRecord(ContentGroupImporter::class, [
        'reference_key' => $group->reference_key,
        'title' => 'Preserved Portable Group',
        'cover_media_reference_key' => '',
    ], options: ['blank_update_behavior' => 'preserve'], user: $admin);

    expect($group->refresh()->coverMediaAttachment()->firstOrFail()->media_id)->toBe($cover->getKey());

    importRecord(ContentGroupImporter::class, [
        'reference_key' => $group->reference_key,
        'title' => 'Detached Portable Group',
        'cover_media_reference_key' => '',
    ], options: ['blank_update_behavior' => 'overwrite'], user: $admin);

    expect($group->refresh()->coverMediaAttachment()->exists())->toBeFalse()
        ->and($group->coverMediaAttachment()->exists())->toBeFalse();
});

it('allows cross-folder existing media while rejecting unresolved and nonselectable portable identities', function (): void {
    $admin = User::factory()->admin()->create();
    $wrongName = (string) Str::ulid();
    $wrongPurpose = Media::factory()->create([
        'directory' => MediaAttachmentRole::PrimaryImage->purpose()->root(),
        'name' => $wrongName,
        'path' => MediaAttachmentRole::PrimaryImage->purpose()->root()."/{$wrongName}.jpg",
    ]);
    $disallowed = Media::factory()->create(['visibility' => 'private']);
    Storage::disk('public')->put($wrongPurpose->path, 'image-bytes');

    importRecord(ContentGroupImporter::class, [
        'reference_key' => (string) Str::ulid(),
        'title' => 'Cross-folder portable group',
        'cover_media_reference_key' => $wrongPurpose->reference_key,
    ], options: [], user: $admin);

    $crossFolder = ContentGroup::query()->where('title', 'Cross-folder portable group')->firstOrFail();
    expect($crossFolder->coverMediaAttachment()->value('media_id'))->toBe($wrongPurpose->getKey())
        ->and($crossFolder->coverMediaAttachment()->with('media')->first()?->media?->path)->toBe($wrongPurpose->path);

    foreach ([(string) Str::ulid(), $disallowed->reference_key] as $referenceKey) {
        expect(fn () => importRecord(ContentGroupImporter::class, [
            'reference_key' => (string) Str::ulid(),
            'title' => 'Rejected portable group',
            'cover_media_reference_key' => $referenceKey,
        ], options: [], user: $admin))->toThrow(ValidationException::class);
    }

    expect(ContentGroup::query()->where('title', 'Rejected portable group')->exists())->toBeFalse();
});

it('exports the authoritative attachment reference key and rejects a null-key attachment identity', function (): void {
    $admin = User::factory()->admin()->create();
    $media = Media::factory()->create();
    $group = ContentGroup::factory()->create();
    Storage::disk('public')->put($media->path, 'authoritative export fixture');
    app(MediaAttachmentManager::class)->attach($group, $media, MediaAttachmentRole::Cover, $admin);

    expect(exportRecord(
        ContentGroupExporter::class,
        $group->load('coverMediaAttachment.media'),
        ['cover_media_reference_key' => 'cover_media_reference_key'],
    ))->toBe([$media->reference_key]);

    $nullKeyMedia = Media::factory()->create();
    DB::table('curator')->where('id', $nullKeyMedia->getKey())->update(['reference_key' => null]);
    $nullKeyGroup = ContentGroup::factory()->create();
    MediaAttachment::query()->create([
        'media_id' => $nullKeyMedia->getKey(),
        'attachable_type' => 'content_group',
        'attachable_id' => $nullKeyGroup->getKey(),
        'role' => MediaAttachmentRole::Cover,
        'position' => 0,
    ]);

    expect(fn () => exportRecord(
        ContentGroupExporter::class,
        $nullKeyGroup,
        ['cover_media_reference_key' => 'cover_media_reference_key'],
    ))->toThrow(InvalidArgumentException::class);
});

it('rolls back owner changes when a post-validation media attachment write fails', function (): void {
    $admin = User::factory()->admin()->create();
    $group = ContentGroup::factory()->create(['title' => 'Before race']);
    $media = Media::factory()->create();
    Storage::disk('public')->put($media->path, 'image-bytes');
    $manager = Mockery::mock(MediaAttachmentManager::class);
    $manager->shouldReceive('attachByReferenceKey')->once()->andThrow(new RuntimeException('simulated media race'));
    app()->instance(MediaAttachmentManager::class, $manager);

    expect(fn () => importRecord(ContentGroupImporter::class, [
        'reference_key' => $group->reference_key,
        'title' => 'After race',
        'cover_media_reference_key' => $media->reference_key,
    ], options: [], user: $admin))->toThrow(RuntimeException::class, 'simulated media race');

    expect($group->refresh()->title)->toBe('Before race')
        ->and($group->coverMediaAttachment()->exists())->toBeFalse();
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
        ->and($groupColumns->map->getName()->all())->toContain('description_markdown', 'cover_media_reference_key')
        ->and($groupColumns->map->getName()->all())->not->toContain('cover_path')
        ->and($descriptionMarkdownColumn->isEnabledByDefault())->toBeFalse()
        ->and($itemColumns->map->getName()->all())->toContain('content_group_reference_key', 'primary_image_media_reference_key')
        ->and($itemColumns->map->getName()->all())->not->toContain('primary_image_path')
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

it('eager loads content item export relations for queued records', function (): void {
    $root = Category::factory()->create([
        'slug' => 'root-category',
    ]);
    $parent = Category::factory()->for($root, 'parent')->create([
        'slug' => 'parent-category',
    ]);
    $child = Category::factory()->for($parent, 'parent')->create([
        'slug' => 'child-category',
    ]);
    $tag = ContentTag::findOrCreateFromString('Queued Export Tag', 'content')->enable();
    $group = ContentGroup::factory()->create();
    $item = ContentItem::factory()->for($group)->create();
    $transcription = Transcription::factory()->for($item)->create();

    $item->categories()->attach($child);
    $item->tags()->sync([$tag->id]);
    $item->update(['featured_transcription_id' => $transcription->id]);

    $record = ContentItemExporter::modifyQuery(ContentItem::query())->findOrFail($item->getKey());

    Model::preventLazyLoading();

    try {
        $exported = exportRecord(ContentItemExporter::class, $record, [
            'content_group_reference_key' => 'content_group_reference_key',
            'category_paths' => 'category_paths',
            'content_tag_slugs' => 'content_tag_slugs',
            'featured_transcription_reference_key' => 'featured_transcription_reference_key',
        ]);
    } finally {
        Model::preventLazyLoading(! app()->isProduction());
    }

    expect($exported)->toBe([
        $group->reference_key,
        'root-category/parent-category/child-category',
        $tag->getTranslation('slug', app()->getLocale(), false),
        $transcription->reference_key,
    ]);
});

it('eager loads transcription export relations for queued records', function (): void {
    $item = ContentItem::factory()->create();
    $primary = Author::factory()->create(['name' => 'Primary Exporter']);
    $secondary = Author::factory()->create(['name' => 'Secondary Exporter']);
    $transcription = Transcription::factory()
        ->for($item)
        ->forAuthor($primary)
        ->create();
    $transcription->syncTranscribers([$primary, $secondary]);

    $record = TranscriptionExporter::modifyQuery(Transcription::query())->findOrFail($transcription->getKey());

    Model::preventLazyLoading();

    try {
        $exported = exportRecord(TranscriptionExporter::class, $record, [
            'content_item_reference_key' => 'content_item_reference_key',
            'author_reference_key' => 'author_reference_key',
            'primary_transcriber_reference_key' => 'primary_transcriber_reference_key',
            'transcriber_reference_keys' => 'transcriber_reference_keys',
            'transcriber_names' => 'transcriber_names',
        ]);
    } finally {
        Model::preventLazyLoading(! app()->isProduction());
    }

    expect($exported)->toBe([
        $item->reference_key,
        $primary->reference_key,
        $primary->reference_key,
        "{$primary->reference_key}|{$secondary->reference_key}",
        "{$primary->name}|{$secondary->name}",
    ]);
});

it('eager loads category export parent path without relying on path column order', function (): void {
    $root = Category::factory()->create([
        'slug' => 'root-category',
    ]);
    $parent = Category::factory()->for($root, 'parent')->create([
        'slug' => 'parent-category',
    ]);
    $child = Category::factory()->for($parent, 'parent')->create([
        'slug' => 'child-category',
    ]);

    $record = CategoryExporter::modifyQuery(Category::query())->findOrFail($child->getKey());

    Model::preventLazyLoading();

    try {
        $exported = exportRecord(CategoryExporter::class, $record, [
            'parent_slug' => 'parent_slug',
        ]);
    } finally {
        Model::preventLazyLoading(! app()->isProduction());
    }

    expect($exported)->toBe(['root-category/parent-category']);
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

it('localizes completed notification bodies for import export completions', function (): void {
    app()->setLocale('he');

    $export = Export::query()->create([
        'file_disk' => 'local',
        'file_name' => 'exports/test.csv',
        'exporter' => ContentGroupExporter::class,
        'processed_rows' => 3,
        'total_rows' => 3,
        'successful_rows' => 2,
        'user_id' => User::factory()->create()->id,
    ]);
    $import = Import::query()->create([
        'file_name' => 'imports/test.csv',
        'file_path' => 'imports/test.csv',
        'importer' => ContentGroupImporter::class,
        'processed_rows' => 3,
        'total_rows' => 3,
        'successful_rows' => 2,
        'user_id' => User::factory()->create()->id,
    ]);

    $exportBody = ContentGroupExporter::getCompletedNotificationBody($export);
    $importBody = ContentGroupImporter::getCompletedNotificationBody($import);

    expect($exportBody)
        ->toContain('הייצוא')
        ->toContain('שורות יוצאו')
        ->toContain('שורה אחת נכשלה בייצוא')
        ->not->toContain('Your content group export has completed')
        ->not->toContain('failed to export')
        ->and($importBody)
        ->toContain('הייבוא')
        ->toContain('שורות יובאו')
        ->toContain('שורה אחת נכשלה בייבוא')
        ->not->toContain('Your content group import has completed')
        ->not->toContain('failed to import');
});
