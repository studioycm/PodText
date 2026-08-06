<?php

use App\Models\Author;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\ContentTag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/** Blanks the shadow columns the way rows written before the migration look. */
function unfoldedRow(string $table, int $id, array $shadows): void
{
    DB::table($table)->where('id', $id)->update(array_fill_keys($shadows, null));
}

it('backfills shadow columns for rows written before the migration', function (): void {
    $item = ContentItem::factory()->create([
        'title' => 'שָׁלוֹם',
        'description_markdown' => 'בְּרֵאשִׁית',
    ]);
    unfoldedRow('content_items', $item->getKey(), ['title_search', 'description_markdown_search']);

    $this->artisan('search:backfill-folds')->assertSuccessful();

    $row = DB::table('content_items')->where('id', $item->getKey())->first();

    expect($row->title_search)->toBe('שלום')
        ->and($row->description_markdown_search)->toBe('בראשית');
});

it('backfills every model that declares folded columns', function (): void {
    $group = ContentGroup::factory()->create(['title' => 'הַתּוֹרָה']);
    $author = Author::factory()->create(['name' => 'מַשֶּׁה']);
    $tag = ContentTag::query()->create(['name' => ['he' => 'גְּמָרָא'], 'type' => 'content']);

    unfoldedRow('content_groups', $group->getKey(), ['title_search']);
    unfoldedRow('authors', $author->getKey(), ['name_search']);
    unfoldedRow('tags', $tag->getKey(), ['name_search']);

    $this->artisan('search:backfill-folds')->assertSuccessful();

    expect(DB::table('content_groups')->where('id', $group->getKey())->value('title_search'))->toBe('התורה')
        ->and(DB::table('authors')->where('id', $author->getKey())->value('name_search'))->toBe('משה')
        ->and(DB::table('tags')->where('id', $tag->getKey())->value('name_search'))->toBe('גמרא');
});

it('is re-runnable, touching nothing on a second pass', function (): void {
    $item = ContentItem::factory()->create(['title' => 'שָׁלוֹם']);
    unfoldedRow('content_items', $item->getKey(), ['title_search']);

    $this->artisan('search:backfill-folds')
        ->expectsOutputToContain('1 row')
        ->assertSuccessful();

    $this->artisan('search:backfill-folds')
        ->expectsOutputToContain('0 rows')
        ->assertSuccessful();

    expect(DB::table('content_items')->where('id', $item->getKey())->value('title_search'))->toBe('שלום');
});

it('does not bump updated_at, so a backfill cannot masquerade as an edit', function (): void {
    $item = ContentItem::factory()->create(['title' => 'שָׁלוֹם']);
    unfoldedRow('content_items', $item->getKey(), ['title_search']);
    DB::table('content_items')->where('id', $item->getKey())->update(['updated_at' => '2020-01-01 00:00:00']);

    $this->artisan('search:backfill-folds')->assertSuccessful();

    expect(DB::table('content_items')->where('id', $item->getKey())->value('updated_at'))
        ->toStartWith('2020-01-01 00:00:00');
});

it('covers every row when chunked smaller than the table', function (): void {
    $items = ContentItem::factory()->count(7)->create(['title' => 'מַשֶּׁה']);
    $items->each(fn (ContentItem $item) => unfoldedRow('content_items', $item->getKey(), ['title_search']));

    $this->artisan('search:backfill-folds', ['--chunk' => 2])->assertSuccessful();

    expect(DB::table('content_items')->whereNull('title_search')->count())->toBe(0)
        ->and(DB::table('content_items')->where('title_search', 'משה')->count())->toBe(7);
});

it('can be narrowed to a single model', function (): void {
    $item = ContentItem::factory()->create(['title' => 'שָׁלוֹם']);
    $author = Author::factory()->create(['name' => 'מַשֶּׁה']);
    unfoldedRow('content_items', $item->getKey(), ['title_search']);
    unfoldedRow('authors', $author->getKey(), ['name_search']);

    $this->artisan('search:backfill-folds', ['--model' => Author::class])->assertSuccessful();

    expect(DB::table('authors')->where('id', $author->getKey())->value('name_search'))->toBe('משה')
        ->and(DB::table('content_items')->where('id', $item->getKey())->value('title_search'))->toBeNull();
});

it('repairs a shadow that drifted out of step with its source', function (): void {
    $item = ContentItem::factory()->create(['title' => 'שָׁלוֹם']);
    DB::table('content_items')->where('id', $item->getKey())->update(['title_search' => 'stale']);

    $this->artisan('search:backfill-folds')->assertSuccessful();

    expect(DB::table('content_items')->where('id', $item->getKey())->value('title_search'))->toBe('שלום');
});
