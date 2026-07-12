<?php

use App\Enums\PublicationStatus;
use App\Filament\Exports\CategoryExporter;
use App\Filament\Exports\ContentGroupExporter;
use App\Filament\Exports\ContentItemExporter;
use App\Filament\Exports\TranscriptionExporter;
use App\Filament\Imports\AuthorImporter;
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
use Illuminate\Database\Eloquent\Model;
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

function phase02ExportRecord(string $exporterClass, mixed $record, array $columnMap, array $options = []): array
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

    return $export->getExporter($columnMap, $options)($record);
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
        ->and($transcription->authors()->pluck('authors.id')->all())->toBe([$author->id])
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

it('imports transcriptions with multiple transcriber reference keys and names', function (): void {
    $item = ContentItem::factory()->create();
    $primary = Author::factory()->create(['name' => 'Primary Import Transcriber']);
    $secondary = Author::factory()->create(['name' => 'Secondary Import Transcriber']);
    $named = Author::factory()->create(['name' => 'Named Import Transcriber']);

    phase02ImportRecord(TranscriptionImporter::class, [
        'reference_key' => (string) Str::ulid(),
        'content_item_reference_key' => $item->reference_key,
        'primary_transcriber_reference_key' => $primary->reference_key,
        'transcriber_reference_keys' => $secondary->reference_key,
        'transcriber_names' => $named->name,
        'title' => 'Multi transcriber transcript',
        'language_code' => 'he',
        'transcript_markdown' => 'Multi body',
        'status' => PublicationStatus::Draft->value,
    ]);

    $transcription = Transcription::query()->where('title', 'Multi transcriber transcript')->firstOrFail();

    expect($transcription->author_id)->toBe($primary->id)
        ->and($transcription->authors()->pluck('authors.id')->all())->toBe([
            $primary->id,
            $secondary->id,
            $named->id,
        ]);
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
    $category = Category::factory()->create(['slug' => 'media']);
    $tag = ContentTag::findOrCreateFromString('Enabled Tag', 'content')->enable();
    $wrongTypeTag = ContentTag::findOrCreateFromString('Wrong Type Tag', 'internal');

    phase02ImportRecord(ContentItemImporter::class, [
        'reference_key' => (string) Str::ulid(),
        'content_group_reference_key' => $group->reference_key,
        'title' => 'Imported Item',
        'slug' => 'imported-item',
        'media_url' => 'https://example.com/media/item',
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

it('imports category relation modes for groups and items while blank cells preserve links', function (): void {
    $categoryA = Category::factory()->create(['slug' => 'category-a']);
    $categoryB = Category::factory()->create(['slug' => 'category-b']);
    $categoryC = Category::factory()->create(['slug' => 'category-c']);
    $group = ContentGroup::factory()->create(['title' => 'Relation Mode Group']);
    $item = ContentItem::factory()->for($group)->create([
        'title' => 'Relation Mode Item',
        'media_url' => 'https://example.com/media/relation-mode-item',
    ]);

    $group->categories()->sync([$categoryA->id, $categoryB->id]);
    $item->categories()->sync([$categoryA->id, $categoryB->id]);

    phase02ImportRecord(ContentGroupImporter::class, [
        'reference_key' => $group->reference_key,
        'title' => 'Default Replace Group',
        'category_paths' => 'category-c',
    ]);
    phase02ImportRecord(ContentItemImporter::class, [
        'reference_key' => $item->reference_key,
        'content_group_reference_key' => $group->reference_key,
        'title' => 'Default Replace Item',
        'media_url' => 'https://example.com/media/default-replace-item',
        'category_paths' => 'category-c',
    ]);

    expect($group->refresh()->categories()->pluck('categories.slug')->sort()->values()->all())->toBe(['category-c'])
        ->and($item->refresh()->categories()->pluck('categories.slug')->sort()->values()->all())->toBe(['category-c']);

    phase02ImportRecord(ContentGroupImporter::class, [
        'reference_key' => $group->reference_key,
        'title' => 'Add Only Group',
        'category_paths' => 'category-a',
    ], null, ['relation_mode' => 'add_only']);
    phase02ImportRecord(ContentItemImporter::class, [
        'reference_key' => $item->reference_key,
        'content_group_reference_key' => $group->reference_key,
        'title' => 'Add Only Item',
        'media_url' => 'https://example.com/media/add-only-item',
        'category_paths' => 'category-a',
    ], null, ['relation_mode' => 'add_only']);

    expect($group->refresh()->categories()->pluck('categories.slug')->sort()->values()->all())->toBe(['category-a', 'category-c'])
        ->and($item->refresh()->categories()->pluck('categories.slug')->sort()->values()->all())->toBe(['category-a', 'category-c']);

    phase02ImportRecord(ContentGroupImporter::class, [
        'reference_key' => $group->reference_key,
        'title' => 'Blank Replace Group',
        'category_paths' => '',
    ], null, ['relation_mode' => 'replace']);
    phase02ImportRecord(ContentItemImporter::class, [
        'reference_key' => $item->reference_key,
        'content_group_reference_key' => $group->reference_key,
        'title' => 'Blank Replace Item',
        'media_url' => 'https://example.com/media/blank-replace-item',
        'category_paths' => '',
    ], null, ['relation_mode' => 'replace']);
    phase02ImportRecord(ContentGroupImporter::class, [
        'reference_key' => $group->reference_key,
        'title' => 'Blank Add Group',
        'category_paths' => '',
    ], null, ['relation_mode' => 'add_only']);
    phase02ImportRecord(ContentItemImporter::class, [
        'reference_key' => $item->reference_key,
        'content_group_reference_key' => $group->reference_key,
        'title' => 'Blank Add Item',
        'media_url' => 'https://example.com/media/blank-add-item',
        'category_paths' => '',
    ], null, ['relation_mode' => 'add_only']);

    expect($group->refresh()->categories()->pluck('categories.slug')->sort()->values()->all())->toBe(['category-a', 'category-c'])
        ->and($item->refresh()->categories()->pluck('categories.slug')->sort()->values()->all())->toBe(['category-a', 'category-c']);
});

it('imports content tag relation modes and exports enabled or all tag scopes without lazy loading', function (): void {
    $group = ContentGroup::factory()->create();
    $item = ContentItem::factory()->for($group)->create([
        'title' => 'Tag Mode Item',
        'media_url' => 'https://example.com/media/tag-mode-item',
    ]);
    $enabledOld = ContentTag::findOrCreateFromString('Enabled Old Tag', 'content')->enable();
    $enabledNew = ContentTag::findOrCreateFromString('Enabled New Tag', 'content')->enable();
    $enabledAdd = ContentTag::findOrCreateFromString('Enabled Add Tag', 'content')->enable();
    $disabledExisting = ContentTag::findOrCreateFromString('Disabled Existing Tag', 'content');
    $disabledInput = ContentTag::findOrCreateFromString('Disabled Input Tag', 'content');
    $internalTag = ContentTag::findOrCreateFromString('Internal Keep Tag', 'internal');
    $slug = fn (ContentTag $tag): string => $tag->getTranslation('slug', app()->getLocale(), false);
    $contentTagSlugs = fn (): array => $item->refresh()->contentTags()->get()
        ->map(fn (ContentTag $tag): string => $slug($tag))
        ->sort()
        ->values()
        ->all();
    $enabledTagSlugs = fn (): array => $item->refresh()->enabledContentTags()->get()
        ->map(fn (ContentTag $tag): string => $slug($tag))
        ->sort()
        ->values()
        ->all();

    $item->tags()->sync([$enabledOld->id, $disabledExisting->id, $internalTag->id]);

    $import = Import::query()->create([
        'file_name' => 'items.csv',
        'file_path' => 'imports/items.csv',
        'importer' => ContentItemImporter::class,
        'processed_rows' => 0,
        'total_rows' => 1,
        'successful_rows' => 0,
        'user_id' => User::factory()->create()->id,
    ]);
    $job = new ImportCsv($import, [
        [
            'reference_key' => $item->reference_key,
            'content_group_reference_key' => $group->reference_key,
            'title' => 'Default Replace With Disabled Warning',
            'media_url' => 'https://example.com/media/default-replace-with-disabled-warning',
            'content_tag_slugs' => $slug($enabledNew).'|'.$slug($disabledInput),
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

    expect($import->refresh())
        ->successful_rows->toBe(1)
        ->and($import->failedRows()->count())->toBe(0)
        ->and($enabledTagSlugs())->toBe([$slug($enabledNew)])
        ->and($contentTagSlugs())->toBe([$slug($disabledExisting), $slug($enabledNew)])
        ->and($item->refresh()->tags()->whereKey($internalTag->getKey())->exists())->toBeTrue()
        ->and($item->tags()->whereKey($disabledInput->getKey())->exists())->toBeFalse()
        ->and(ContentItemImporter::getCompletedNotificationBody($import))->toContain('Disabled Input Tag');

    phase02ImportRecord(ContentItemImporter::class, [
        'reference_key' => $item->reference_key,
        'content_group_reference_key' => $group->reference_key,
        'title' => 'Add Only Tag',
        'media_url' => 'https://example.com/media/add-only-tag',
        'content_tag_slugs' => $slug($enabledAdd),
    ], null, ['relation_mode' => 'add_only']);

    expect($enabledTagSlugs())->toBe([$slug($enabledAdd), $slug($enabledNew)])
        ->and($contentTagSlugs())->toBe([$slug($disabledExisting), $slug($enabledAdd), $slug($enabledNew)]);

    phase02ImportRecord(ContentItemImporter::class, [
        'reference_key' => $item->reference_key,
        'content_group_reference_key' => $group->reference_key,
        'title' => 'Blank Replace Tags',
        'media_url' => 'https://example.com/media/blank-replace-tags',
        'content_tag_slugs' => '',
    ], null, ['relation_mode' => 'replace']);
    phase02ImportRecord(ContentItemImporter::class, [
        'reference_key' => $item->reference_key,
        'content_group_reference_key' => $group->reference_key,
        'title' => 'Blank Add Tags',
        'media_url' => 'https://example.com/media/blank-add-tags',
        'content_tag_slugs' => '',
    ], null, ['relation_mode' => 'add_only']);

    expect($contentTagSlugs())->toBe([$slug($disabledExisting), $slug($enabledAdd), $slug($enabledNew)]);

    Model::preventLazyLoading();

    try {
        $exportRecord = ContentItemExporter::modifyQuery(ContentItem::query())->whereKey($item->getKey())->firstOrFail();
        $defaultExport = phase02ExportRecord(ContentItemExporter::class, $exportRecord, [
            'content_tag_slugs' => 'content_tag_slugs',
        ]);
        $allTagsExport = phase02ExportRecord(ContentItemExporter::class, $exportRecord, [
            'content_tag_slugs' => 'content_tag_slugs',
        ], ['tag_scope' => 'all_tags']);
    } finally {
        Model::preventLazyLoading(false);
    }

    $defaultTagSlugs = collect(explode('|', $defaultExport[0]))->sort()->values()->all();
    $allExportTagSlugs = collect(explode('|', $allTagsExport[0]))->sort()->values()->all();

    expect($defaultTagSlugs)->toBe([$slug($enabledAdd), $slug($enabledNew)])
        ->and($allExportTagSlugs)->toBe([$slug($disabledExisting), $slug($enabledAdd), $slug($enabledNew)]);

    phase02ImportRecord(ContentItemImporter::class, [
        'reference_key' => $item->reference_key,
        'content_group_reference_key' => $group->reference_key,
        'title' => 'Default Export Round Trip',
        'media_url' => 'https://example.com/media/default-export-round-trip',
        'content_tag_slugs' => $defaultExport[0],
    ]);

    expect($contentTagSlugs())->toBe([$slug($disabledExisting), $slug($enabledAdd), $slug($enabledNew)]);
});

it('imports transcriber relation modes while blank cells preserve existing transcribers', function (): void {
    $item = ContentItem::factory()->create();
    $first = Author::factory()->create(['name' => 'First Relation Transcriber']);
    $second = Author::factory()->create(['name' => 'Second Relation Transcriber']);
    $third = Author::factory()->create(['name' => 'Third Relation Transcriber']);
    $fourth = Author::factory()->create(['name' => 'Fourth Relation Transcriber']);
    $transcription = Transcription::factory()
        ->for($item)
        ->forAuthor($first)
        ->create(['title' => 'Relation Transcription']);
    $transcription->syncTranscribers([$first, $second]);
    $transcriberIds = fn (): array => $transcription->refresh()->authors()->pluck('authors.id')->all();

    phase02ImportRecord(TranscriptionImporter::class, [
        'reference_key' => $transcription->reference_key,
        'content_item_reference_key' => $item->reference_key,
        'primary_transcriber_reference_key' => $third->reference_key,
        'title' => 'Replace Relation Transcription',
        'language_code' => 'he',
        'transcript_markdown' => 'Updated body',
        'status' => PublicationStatus::Draft->value,
    ]);

    expect($transcription->refresh()->author_id)->toBe($third->id)
        ->and($transcriberIds())->toBe([$third->id]);

    phase02ImportRecord(TranscriptionImporter::class, [
        'reference_key' => $transcription->reference_key,
        'content_item_reference_key' => $item->reference_key,
        'transcriber_names' => $fourth->name,
        'title' => 'Add Only Relation Transcription',
        'language_code' => 'he',
        'transcript_markdown' => 'Updated body',
        'status' => PublicationStatus::Draft->value,
    ], null, ['relation_mode' => 'add_only']);

    expect($transcription->refresh()->author_id)->toBe($third->id)
        ->and($transcriberIds())->toBe([$third->id, $fourth->id]);

    phase02ImportRecord(TranscriptionImporter::class, [
        'reference_key' => $transcription->reference_key,
        'content_item_reference_key' => $item->reference_key,
        'author_reference_key' => '',
        'primary_transcriber_reference_key' => '',
        'transcriber_reference_keys' => '',
        'transcriber_names' => '',
        'title' => 'Blank Replace Relation Transcription',
        'language_code' => 'he',
        'transcript_markdown' => 'Updated body',
        'status' => PublicationStatus::Draft->value,
    ], null, ['relation_mode' => 'replace']);
    phase02ImportRecord(TranscriptionImporter::class, [
        'reference_key' => $transcription->reference_key,
        'content_item_reference_key' => $item->reference_key,
        'author_reference_key' => '',
        'primary_transcriber_reference_key' => '',
        'transcriber_reference_keys' => '',
        'transcriber_names' => '',
        'title' => 'Blank Add Relation Transcription',
        'language_code' => 'he',
        'transcript_markdown' => 'Updated body',
        'status' => PublicationStatus::Draft->value,
    ], null, ['relation_mode' => 'add_only']);

    expect($transcription->refresh()->author_id)->toBe($third->id)
        ->and($transcriberIds())->toBe([$third->id, $fourth->id]);
});

it('exports portable prompt ten columns without numeric identifiers', function (): void {
    $group = ContentGroup::factory()->create(['homepage_order' => 3]);
    $category = Category::factory()->create(['slug' => 'portable']);
    $tag = ContentTag::findOrCreateFromString('Portable Tag', 'content')->enable();
    $author = Author::factory()->create();
    $secondAuthor = Author::factory()->create(['name' => 'Second Portable Transcriber']);
    $item = ContentItem::factory()->for($group)->create([
        'published_at' => Carbon::parse('2026-06-30 10:45:00', 'UTC'),
        'is_pinned' => true,
        'pinned_at' => Carbon::parse('2026-06-30 10:45:00', 'UTC'),
        'media_metadata' => ['source' => 'manual'],
    ]);
    $item->categories()->attach($category);
    $item->tags()->sync([$tag->id]);

    $transcription = Transcription::factory()
        ->for($item)
        ->forAuthor($author)
        ->published(Carbon::parse('2026-06-30 10:45:00', 'UTC'))
        ->create();
    $transcription->syncTranscribers([$author, $secondAuthor]);

    $item->update(['featured_transcription_id' => $transcription->id]);

    expect(collect(ContentItemExporter::getColumns())->map->getName()->all())->toContain(
        'category_paths',
        'content_tag_slugs',
        'is_pinned',
        'pinned_at',
        'media_metadata',
        'featured_transcription_reference_key',
    )
        ->and(collect(TranscriptionExporter::getColumns())->map->getName()->all())->toContain(
            'content_item_reference_key',
            'author_reference_key',
            'primary_transcriber_reference_key',
            'transcriber_reference_keys',
            'transcriber_names',
        )
        ->and(collect(CategoryExporter::getColumns())->map->getName()->all())->toContain('path', 'parent_slug')
        ->and(collect(ContentGroupExporter::getColumns())->map->getName()->all())->toContain('category_paths', 'homepage_order');

    $exportedItem = phase02ExportRecord(ContentItemExporter::class, $item->load(['categories.parent', 'contentTags', 'featuredTranscription']), [
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

    expect(phase02ExportRecord(TranscriptionExporter::class, $transcription->load(['contentItem', 'authors']), [
        'content_item_reference_key' => 'content_item_reference_key',
        'author_reference_key' => 'author_reference_key',
        'primary_transcriber_reference_key' => 'primary_transcriber_reference_key',
        'transcriber_reference_keys' => 'transcriber_reference_keys',
        'transcriber_names' => 'transcriber_names',
        'published_at' => 'published_at',
    ]))->toBe([
        $item->reference_key,
        $author->reference_key,
        $author->reference_key,
        $author->reference_key.'|'.$secondAuthor->reference_key,
        $author->name.'|'.$secondAuthor->name,
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

it('stores failed rows for overlong importer reference keys', function (): void {
    $import = Import::query()->create([
        'file_name' => 'authors.csv',
        'file_path' => 'imports/authors.csv',
        'importer' => AuthorImporter::class,
        'processed_rows' => 0,
        'total_rows' => 1,
        'successful_rows' => 0,
        'user_id' => User::factory()->create()->id,
    ]);

    $job = new ImportCsv($import, [
        [
            'reference_key' => str_repeat('0', 30),
            'name' => 'Overlong Reference Author',
            'slug' => 'overlong-reference-author',
        ],
    ], [
        'reference_key' => 'reference_key',
        'name' => 'name',
        'slug' => 'slug',
    ], [
        'mode' => 'upsert',
        'blank_update_behavior' => 'preserve',
    ]);

    $job->handle();

    expect(Author::query()->where('name', 'Overlong Reference Author')->exists())->toBeFalse()
        ->and($import->refresh()->successful_rows)->toBe(0)
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
