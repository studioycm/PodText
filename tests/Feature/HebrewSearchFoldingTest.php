<?php

use App\Enums\SettingsBackupSource;
use App\Filament\Imports\ContentItemImporter;
use App\Models\Author;
use App\Models\Category;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\ContentTag;
use App\Models\HomepageSection;
use App\Models\Media;
use App\Models\PublicFormSubmission;
use App\Models\SettingsBackupVersion;
use App\Models\Transcription;
use App\Models\User;
use App\Support\Search\HebrewSearchFold;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| The shadow columns are written by setAttribute()
|--------------------------------------------------------------------------
|
| setAttribute() rather than a model event, because Model::fill() routes
| through it and forceFill() delegates to fill() — so the folded value is
| written even by the saveQuietly() paths live in app/ today, which model
| events never see.
|
*/

it('writes a folded shadow when a pointed title is saved', function (): void {
    $item = ContentItem::factory()->create(['title' => 'שָׁלוֹם עוֹלָם']);

    expect(DB::table('content_items')->where('id', $item->getKey())->value('title_search'))
        ->toBe('שלום עולם');
});

it('writes shadows through forceFill and saveQuietly, which model events would miss', function (): void {
    $item = ContentItem::factory()->create(['title' => 'ראשון']);

    $item->forceFill(['title' => 'מַשֶּׁה'])->saveQuietly();

    expect(DB::table('content_items')->where('id', $item->getKey())->value('title_search'))
        ->toBe('משה');
});

it('writes shadows through the __set path that Filament importers use', function (): void {
    $item = ContentItem::factory()->create(['title' => 'ראשון']);

    // Filament's ImportColumn::fillRecord() ends in data_set($record, $name,
    // $state), which reaches Model::__set() and therefore setAttribute().
    data_set($item, 'title', 'בְּרֵאשִׁית');
    $item->save();

    expect(DB::table('content_items')->where('id', $item->getKey())->value('title_search'))
        ->toBe('בראשית');
});

it('nulls the shadow when the source is nulled', function (): void {
    $item = ContentItem::factory()->create(['description_markdown' => 'מַשֶּׁה']);

    expect(DB::table('content_items')->where('id', $item->getKey())->value('description_markdown_search'))
        ->toBe('משה');

    $item->update(['description_markdown' => null]);

    expect(DB::table('content_items')->where('id', $item->getKey())->value('description_markdown_search'))
        ->toBeNull();
});

it('folds every declared shadow column on every model that declares one', function (
    string $model,
    string $source,
    array $extra = [],
): void {
    /** @var class-string<Model> $model */
    $record = $model::factory()->create([$source => 'הַתּוֹרָה', ...$extra]);
    $shadow = $model::foldedSearchColumns()[$source];

    expect(DB::table($record->getTable())->where('id', $record->getKey())->value($shadow))
        ->toBe('התורה');
})->with([
    'content item title' => [ContentItem::class, 'title'],
    'content item description' => [ContentItem::class, 'description_markdown'],
    'content group title' => [ContentGroup::class, 'title'],
    'content group description' => [ContentGroup::class, 'description_markdown'],
    'content group type label' => [ContentGroup::class, 'group_type_label_singular'],
    'content group item type label' => [ContentGroup::class, 'default_item_type_label_singular'],
    'category name' => [Category::class, 'name'],
    'author name' => [Author::class, 'name'],
    'transcription title' => [Transcription::class, 'title'],
    'media title' => [Media::class, 'title'],
    'media filename' => [Media::class, 'name'],
    'homepage section name' => [HomepageSection::class, 'name'],
    'user name' => [User::class, 'name'],
    'form submission name snapshot' => [PublicFormSubmission::class, 'form_name_snapshot'],
]);

/*
 * `tags.name` is Spatie's translatable JSON, and the shipped predicate ORs the
 * raw blob against `name->{locale}`. The shadow flattens every locale into one
 * newline-joined string instead of mirroring the JSON: MySQL normalizes JSON
 * columns on insert while SQLite stores PHP's bytes verbatim, so a JSON shadow
 * would have to match two different encodings. A flat column has one.
 */
it('flattens every locale of the translatable tag name into one folded shadow', function (): void {
    $tag = ContentTag::query()->create([
        'name' => ['he' => 'מַשֶּׁה', 'en' => 'Moses'],
        'type' => 'content',
    ]);

    $shadow = (string) DB::table('tags')->where('id', $tag->getKey())->value('name_search');

    expect(explode("\n", $shadow))->toEqualCanonicalizing(['משה', 'moses']);
});

it('folds a tag name set as a bare string through spatie current-locale handling', function (): void {
    $tag = ContentTag::query()->create(['name' => 'הַתּוֹרָה', 'type' => 'content']);

    expect((string) DB::table('tags')->where('id', $tag->getKey())->value('name_search'))
        ->toBe('התורה');
});

/* SettingsBackupVersion has no factory, so it is covered on its own. */
it('folds the settings backup label', function (): void {
    $version = SettingsBackupVersion::query()->create([
        'scope' => 'public',
        'label' => 'גִּבּוּי',
        'payload_json' => '{}',
        'checksum' => str_repeat('a', 64),
        'payload_hash' => str_repeat('b', 64),
        'source' => SettingsBackupSource::Manual,
    ]);

    expect(DB::table('settings_backup_versions')->where('id', $version->getKey())->value('label_search'))
        ->toBe('גבוי');
});

/*
 * Filament's importers never call fill(). ImportColumn::fillRecord() ends in
 * data_set($record, $name, $state), which reaches Model::__set() and so
 * setAttribute() — the same seam. Asserted against a real import rather than
 * inferred from the vendor source.
 */
it('writes shadows when a record arrives through a filament importer', function (): void {
    $group = ContentGroup::factory()->create();
    $referenceKey = (string) Str::ulid();

    $import = Import::query()->create([
        'file_name' => 'folding.csv',
        'file_path' => 'imports/folding.csv',
        'importer' => ContentItemImporter::class,
        'processed_rows' => 0,
        'total_rows' => 1,
        'successful_rows' => 0,
        'user_id' => User::factory()->create()->getKey(),
    ]);

    $row = [
        'reference_key' => $referenceKey,
        'content_group_reference_key' => $group->reference_key,
        'title' => 'שָׁלוֹם עוֹלָם',
        'description_markdown' => 'בְּרֵאשִׁית',
        'media_url' => 'https://example.com/media/folding',
    ];

    $importer = $import->getImporter(
        array_combine(array_keys($row), array_keys($row)),
        ['mode' => 'upsert', 'blank_update_behavior' => 'preserve'],
    );
    $importer($row);

    $imported = DB::table('content_items')->where('reference_key', $referenceKey)->first();

    expect($imported->title_search)->toBe('שלום עולם')
        ->and($imported->description_markdown_search)->toBe('בראשית');
});

it('leaves the source column byte-identical, folding only the shadow', function (): void {
    $pointed = 'שָׁלוֹם';
    $item = ContentItem::factory()->create(['title' => $pointed]);

    expect(DB::table('content_items')->where('id', $item->getKey())->value('title'))
        ->toBe($pointed)
        ->and(HebrewSearchFold::fold($pointed))->not->toBe($pointed);
});
