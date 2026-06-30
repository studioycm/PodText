<?php

use App\Enums\PublicationStatus;
use App\Filament\Exports\CategoryExporter;
use App\Filament\Exports\ContentGroupExporter;
use App\Filament\Exports\ContentItemExporter;
use App\Filament\Exports\TranscriptionExporter;
use App\Filament\Imports\CategoryImporter;
use App\Filament\Imports\ContentGroupImporter;
use App\Filament\Imports\ContentItemImporter;
use App\Filament\Imports\TranscriptionImporter;
use App\Filament\Resources\Categories\Pages\ListCategories;
use App\Filament\Resources\ContentGroups\Pages\ListContentGroups;
use App\Filament\Resources\ContentItems\Pages\ListContentItems;
use App\Filament\Resources\Transcriptions\Pages\ListTranscriptions;
use App\Models\Author;
use App\Models\Category;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\ContentTag;
use App\Models\Transcription;
use App\Models\User;
use Filament\Actions\Exports\Models\Export;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\Jobs\ImportCsv;
use Filament\Actions\Imports\Models\Import;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

function phase02ImportRecord(string $importerClass, array $row, ?array $columnMap = null, array $options = []): Import
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

function phase02ExportRecord(string $exporterClass, mixed $record, array $columnMap): array
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

it('imports transcriptions by reference key and never writes the legacy item transcript', function (): void {
    $item = ContentItem::factory()->create(['transcript_markdown' => null]);
    $author = Author::factory()->create();
    $referenceKey = (string) Str::ulid();

    phase02ImportRecord(TranscriptionImporter::class, [
        'reference_key' => $referenceKey,
        'content_item_reference_key' => $item->reference_key,
        'author_reference_key' => $author->reference_key,
        'title' => 'Imported transcript',
        'language_code' => 'he',
        'transcript_markdown' => 'Inline **Markdown** transcript',
        'status' => PublicationStatus::Published->value,
        'published_at' => '30/06/2026 13:45',
    ]);

    $transcription = Transcription::query()->where('reference_key', $referenceKey)->firstOrFail();

    expect($transcription)
        ->content_item_id->toBe($item->id)
        ->author_id->toBe($author->id)
        ->title->toBe('Imported transcript')
        ->status->toBe(PublicationStatus::Published)
        ->and($transcription->published_at->timezone('Asia/Jerusalem')->format('d/m/Y H:i'))->toBe('30/06/2026 13:45')
        ->and($item->refresh()->transcript_markdown)->toBeNull()
        ->and($item->featured_transcription_id)->toBe($transcription->id);

    phase02ImportRecord(TranscriptionImporter::class, [
        'reference_key' => $referenceKey,
        'content_item_reference_key' => $item->reference_key,
        'author_reference_key' => $author->reference_key,
        'title' => 'Updated transcript',
        'language_code' => 'en',
        'transcript_markdown' => 'Updated body',
        'status' => PublicationStatus::Draft->value,
        'published_at' => '',
    ]);

    expect($transcription->refresh())
        ->title->toBe('Updated transcript')
        ->language_code->toBe('en')
        ->transcript_markdown->toBe('Updated body')
        ->status->toBe(PublicationStatus::Draft)
        ->and($item->refresh()->transcript_markdown)->toBeNull();
});

it('resolves transcription fallback matching and fails missing item or author references', function (): void {
    $item = ContentItem::factory()->create();
    $author = Author::factory()->create();

    phase02ImportRecord(TranscriptionImporter::class, [
        'reference_key' => '',
        'content_item_reference_key' => $item->reference_key,
        'author_reference_key' => $author->reference_key,
        'title' => 'Fallback transcript',
        'language_code' => 'he',
        'transcript_markdown' => 'Original body',
        'status' => PublicationStatus::Draft->value,
        'published_at' => '01/07/2026 10:00',
    ]);

    phase02ImportRecord(TranscriptionImporter::class, [
        'reference_key' => '',
        'content_item_reference_key' => $item->reference_key,
        'author_reference_key' => $author->reference_key,
        'title' => 'Fallback transcript updated',
        'language_code' => 'he',
        'transcript_markdown' => 'Updated body',
        'status' => PublicationStatus::Draft->value,
        'published_at' => '01/07/2026 10:00',
    ]);

    expect(Transcription::query()->where('content_item_id', $item->id)->count())->toBe(1)
        ->and(Transcription::query()->first()->title)->toBe('Fallback transcript updated');

    expect(fn () => phase02ImportRecord(TranscriptionImporter::class, [
        'content_item_reference_key' => (string) Str::ulid(),
        'author_reference_key' => $author->reference_key,
        'language_code' => 'he',
        'transcript_markdown' => 'Body',
    ]))->toThrow(RowImportFailedException::class);

    expect(fn () => phase02ImportRecord(TranscriptionImporter::class, [
        'content_item_reference_key' => $item->reference_key,
        'author_reference_key' => (string) Str::ulid(),
        'language_code' => 'he',
        'transcript_markdown' => 'Body',
    ]))->toThrow(RowImportFailedException::class);
});

it('imports category hierarchy and fails missing parents', function (): void {
    phase02ImportRecord(CategoryImporter::class, [
        'path' => 'torah',
        'name' => 'Torah',
        'slug' => 'torah',
        'parent_slug' => '',
        'is_visible' => 'true',
        'sort_order' => '1',
        'description_markdown' => 'Parent category',
    ]);

    phase02ImportRecord(CategoryImporter::class, [
        'path' => 'torah/interviews',
        'name' => 'Interviews',
        'slug' => '',
        'parent_slug' => '',
        'is_visible' => 'false',
        'sort_order' => '2',
        'description_markdown' => 'Child category',
    ]);

    $parent = Category::query()->where('slug', 'torah')->firstOrFail();
    $child = Category::query()->where('slug', 'interviews')->firstOrFail();

    expect($child)
        ->parent_id->toBe($parent->id)
        ->is_visible->toBeFalse()
        ->sort_order->toBe(2)
        ->description_markdown->toBe('Child category');

    phase02ImportRecord(CategoryImporter::class, [
        'path' => 'torah/interviews',
        'name' => 'Updated Interviews',
        'slug' => '',
        'parent_slug' => '',
        'is_visible' => 'true',
        'sort_order' => '3',
    ]);

    expect($child->refresh())
        ->name->toBe('Updated Interviews')
        ->is_visible->toBeTrue()
        ->sort_order->toBe(3);

    expect(fn () => phase02ImportRecord(CategoryImporter::class, [
        'path' => 'missing/child',
        'name' => 'Missing Parent Child',
        'slug' => '',
        'parent_slug' => '',
    ]))->toThrow(RowImportFailedException::class);
});

it('imports content item taxonomy tags pinning media metadata and day first dates', function (): void {
    $group = ContentGroup::factory()->create();
    $author = Author::factory()->create();
    $category = Category::factory()->create(['slug' => 'media']);
    $tag = ContentTag::findOrCreateFromString('Enabled Tag', 'content')->enable();
    $wrongTypeTag = ContentTag::findOrCreateFromString('Wrong Type Tag', 'internal');
    $disabledTag = ContentTag::findOrCreateFromString('Disabled Tag', 'content');

    phase02ImportRecord(ContentItemImporter::class, [
        'reference_key' => (string) Str::ulid(),
        'content_group_reference_key' => $group->reference_key,
        'title' => 'Imported Item',
        'slug' => 'imported-item',
        'media_url' => 'https://example.com/media/item',
        'author_reference_keys' => $author->reference_key,
        'category_paths' => 'media',
        'content_tag_slugs' => $tag->getTranslation('slug', app()->getLocale(), false),
        'is_pinned' => 'true',
        'pinned_at' => '30/06/2026 13:45',
        'pinned_until' => '30/07/2026 13:45',
        'pin_order' => '7',
        'embed_provider' => 'youtube',
        'media_duration_seconds' => '360',
        'external_id' => 'yt-1',
        'external_title' => 'External title',
        'external_description' => 'External description',
        'external_thumbnail_url' => 'https://example.com/thumb.jpg',
        'external_published_at' => '29/06/2026 09:30',
        'media_metadata' => '{"source":"manual"}',
        'direct_media_url' => 'https://example.com/direct.mp3',
        'status' => PublicationStatus::Published->value,
        'published_at' => '01/07/2026 08:15',
        'original_published_at' => '30/06/2026 12:00',
    ]);

    $item = ContentItem::query()->where('slug', 'imported-item')->firstOrFail();

    expect($item)
        ->is_pinned->toBeTrue()
        ->pin_order->toBe(7)
        ->embed_provider->toBe('youtube')
        ->media_duration_seconds->toBe(360)
        ->external_id->toBe('yt-1')
        ->external_title->toBe('External title')
        ->external_description->toBe('External description')
        ->external_thumbnail_url->toBe('https://example.com/thumb.jpg')
        ->media_metadata->toBe(['source' => 'manual'])
        ->direct_media_url->toBe('https://example.com/direct.mp3')
        ->and($item->pinned_at->timezone('Asia/Jerusalem')->format('d/m/Y H:i'))->toBe('30/06/2026 13:45')
        ->and($item->published_at->timezone('Asia/Jerusalem')->format('d/m/Y H:i'))->toBe('01/07/2026 08:15')
        ->and($item->categories()->pluck('categories.id')->all())->toBe([$category->id])
        ->and($item->contentTags()->pluck('tags.id')->all())->toBe([$tag->id])
        ->and($item->transcript_markdown)->toBeNull();

    expect(fn () => phase02ImportRecord(ContentItemImporter::class, [
        'reference_key' => (string) Str::ulid(),
        'content_group_reference_key' => $group->reference_key,
        'title' => 'Missing Category',
        'media_url' => 'https://example.com/media/missing-category',
        'category_paths' => 'missing',
    ]))->toThrow(ValidationException::class);

    expect(fn () => phase02ImportRecord(ContentItemImporter::class, [
        'reference_key' => (string) Str::ulid(),
        'content_group_reference_key' => $group->reference_key,
        'title' => 'Missing Tag',
        'media_url' => 'https://example.com/media/missing-tag',
        'content_tag_slugs' => 'missing-tag',
    ]))->toThrow(ValidationException::class);

    expect(fn () => phase02ImportRecord(ContentItemImporter::class, [
        'reference_key' => (string) Str::ulid(),
        'content_group_reference_key' => $group->reference_key,
        'title' => 'Wrong Type Tag',
        'media_url' => 'https://example.com/media/wrong-type-tag',
        'content_tag_slugs' => $wrongTypeTag->getTranslation('slug', app()->getLocale(), false),
    ]))->toThrow(ValidationException::class);

    expect(fn () => phase02ImportRecord(ContentItemImporter::class, [
        'reference_key' => (string) Str::ulid(),
        'content_group_reference_key' => $group->reference_key,
        'title' => 'Disabled Tag',
        'media_url' => 'https://example.com/media/disabled-tag',
        'content_tag_slugs' => $disabledTag->getTranslation('slug', app()->getLocale(), false),
    ]))->toThrow(ValidationException::class);
});

it('imports item featured transcription references only when explicitly provided', function (): void {
    $group = ContentGroup::factory()->create();
    $item = ContentItem::factory()->for($group)->create(['featured_transcription_id' => null]);
    $author = Author::factory()->create();
    $first = Transcription::factory()->for($item)->forAuthor($author)->published()->create();
    $second = Transcription::factory()->for($item)->forAuthor($author)->published()->create();

    phase02ImportRecord(ContentItemImporter::class, [
        'reference_key' => $item->reference_key,
        'content_group_reference_key' => $group->reference_key,
        'title' => 'Featured Update',
        'media_url' => 'https://example.com/media/featured-update',
        'featured_transcription_reference_key' => $second->reference_key,
    ]);

    expect($item->refresh()->featured_transcription_id)->toBe($second->id);

    $other = ContentItem::factory()->for($group)->create();

    expect(fn () => phase02ImportRecord(ContentItemImporter::class, [
        'reference_key' => $item->reference_key,
        'content_group_reference_key' => $group->reference_key,
        'title' => 'Bad Featured Update',
        'media_url' => 'https://example.com/media/bad-featured-update',
        'featured_transcription_reference_key' => Transcription::factory()->for($other)->forAuthor($author)->published()->create()->reference_key,
    ]))->toThrow(RowImportFailedException::class);

    expect($first->contentItem->is($item))->toBeTrue();
});

it('imports content group category paths and homepage ordering', function (): void {
    $category = Category::factory()->create(['slug' => 'groups']);

    phase02ImportRecord(ContentGroupImporter::class, [
        'reference_key' => (string) Str::ulid(),
        'title' => 'Group Import',
        'slug' => 'group-import',
        'category_paths' => 'groups',
        'homepage_order' => '4',
        'published_at' => '30/06/2026 14:00',
    ]);

    $group = ContentGroup::query()->where('slug', 'group-import')->firstOrFail();

    expect($group)
        ->homepage_order->toBe(4)
        ->and($group->published_at->timezone('Asia/Jerusalem')->format('d/m/Y H:i'))->toBe('30/06/2026 14:00')
        ->and($group->categories()->pluck('categories.id')->all())->toBe([$category->id]);

    expect(fn () => phase02ImportRecord(ContentGroupImporter::class, [
        'reference_key' => (string) Str::ulid(),
        'title' => 'Missing Group Category',
        'slug' => 'missing-group-category',
        'category_paths' => 'missing',
    ]))->toThrow(ValidationException::class);
});

it('exports portable prompt ten columns without numeric identifiers', function (): void {
    $group = ContentGroup::factory()->create(['homepage_order' => 3]);
    $category = Category::factory()->create(['slug' => 'portable']);
    $tag = ContentTag::findOrCreateFromString('Portable Tag', 'content')->enable();
    $author = Author::factory()->create();
    $item = ContentItem::factory()->for($group)->create([
        'published_at' => Carbon::parse('2026-06-30 10:45:00', 'UTC'),
        'is_pinned' => true,
        'pinned_at' => Carbon::parse('2026-06-30 10:45:00', 'UTC'),
        'media_metadata' => ['source' => 'manual'],
    ]);
    $item->categories()->attach($category);
    $item->authors()->attach($author);
    $item->tags()->sync([$tag->id]);

    $transcription = Transcription::factory()
        ->for($item)
        ->forAuthor($author)
        ->published(Carbon::parse('2026-06-30 10:45:00', 'UTC'))
        ->create();

    $item->update(['featured_transcription_id' => $transcription->id]);

    expect(collect(ContentItemExporter::getColumns())->map->getName()->all())->toContain(
        'category_paths',
        'content_tag_slugs',
        'is_pinned',
        'pinned_at',
        'media_metadata',
        'featured_transcription_reference_key',
    )
        ->and(collect(TranscriptionExporter::getColumns())->map->getName()->all())->toContain('content_item_reference_key', 'author_reference_key')
        ->and(collect(CategoryExporter::getColumns())->map->getName()->all())->toContain('path', 'parent_slug')
        ->and(collect(ContentGroupExporter::getColumns())->map->getName()->all())->toContain('category_paths', 'homepage_order');

    $exportedItem = phase02ExportRecord(ContentItemExporter::class, $item->load(['authors', 'categories.parent', 'contentTags', 'featuredTranscription']), [
        'content_group_reference_key' => 'content_group_reference_key',
        'category_paths' => 'category_paths',
        'content_tag_slugs' => 'content_tag_slugs',
        'published_at' => 'published_at',
        'media_metadata' => 'media_metadata',
        'featured_transcription_reference_key' => 'featured_transcription_reference_key',
    ]);

    expect($exportedItem)->toBe([
        $group->reference_key,
        'portable',
        $tag->getTranslation('slug', app()->getLocale(), false),
        '30/06/2026 13:45',
        '{"source":"manual"}',
        $transcription->reference_key,
    ])
        ->and($exportedItem)->not->toContain((string) $group->id, (string) $category->id, (string) $tag->id);

    expect(phase02ExportRecord(TranscriptionExporter::class, $transcription->load(['contentItem', 'author']), [
        'content_item_reference_key' => 'content_item_reference_key',
        'author_reference_key' => 'author_reference_key',
        'published_at' => 'published_at',
    ]))->toBe([
        $item->reference_key,
        $author->reference_key,
        '30/06/2026 13:45',
    ]);
});

it('continues valid rows and stores failed rows for prompt ten relationship failures', function (): void {
    $group = ContentGroup::factory()->create();
    $tag = ContentTag::findOrCreateFromString('Valid Import Tag', 'content')->enable();

    $import = Import::query()->create([
        'file_name' => 'items.csv',
        'file_path' => 'imports/items.csv',
        'importer' => ContentItemImporter::class,
        'processed_rows' => 0,
        'total_rows' => 2,
        'successful_rows' => 0,
        'user_id' => User::factory()->create()->id,
    ]);

    $job = new ImportCsv($import, [
        [
            'reference_key' => (string) Str::ulid(),
            'content_group_reference_key' => $group->reference_key,
            'title' => 'Valid Row',
            'media_url' => 'https://example.com/media/valid-row',
            'content_tag_slugs' => $tag->getTranslation('slug', app()->getLocale(), false),
        ],
        [
            'reference_key' => (string) Str::ulid(),
            'content_group_reference_key' => $group->reference_key,
            'title' => 'Invalid Row',
            'media_url' => 'https://example.com/media/invalid-row',
            'content_tag_slugs' => 'missing-tag',
        ],
    ], [
        'reference_key' => 'reference_key',
        'content_group_reference_key' => 'content_group_reference_key',
        'title' => 'title',
        'media_url' => 'media_url',
        'content_tag_slugs' => 'content_tag_slugs',
    ], [
        'mode' => 'upsert',
        'blank_update_behavior' => 'preserve',
    ]);

    $job->handle();

    expect(ContentItem::query()->where('title', 'Valid Row')->exists())->toBeTrue()
        ->and(ContentItem::query()->where('title', 'Invalid Row')->exists())->toBeFalse()
        ->and($import->refresh()->successful_rows)->toBe(1)
        ->and($import->failedRows()->count())->toBe(1);
});

it('registers native import export actions on prompt ten admin tables', function (): void {
    $this->actingAs(User::factory()->create());

    Livewire::test(ListCategories::class)
        ->assertActionVisible(TestAction::make('import')->table())
        ->assertActionVisible(TestAction::make('export')->table());

    Livewire::test(ListTranscriptions::class)
        ->assertActionVisible(TestAction::make('import')->table())
        ->assertActionVisible(TestAction::make('export')->table());

    Livewire::test(ListContentItems::class)
        ->assertActionVisible(TestAction::make('import')->table())
        ->assertActionVisible(TestAction::make('export')->table());

    Livewire::test(ListContentGroups::class)
        ->assertActionVisible(TestAction::make('import')->table())
        ->assertActionVisible(TestAction::make('export')->table());
});
