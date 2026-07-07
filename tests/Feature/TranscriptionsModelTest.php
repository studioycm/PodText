<?php

use App\Enums\PublicationStatus;
use App\Models\Author;
use App\Models\ContentItem;
use App\Models\Transcription;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

it('defines transcription relationships and casts', function (): void {
    $author = Author::factory()->create();
    $item = ContentItem::factory()->create();

    $transcription = Transcription::factory()
        ->for($item)
        ->forAuthor($author)
        ->published()
        ->create([
            'speakers' => ['host', 'guest'],
            'parsed_segments' => [
                ['speaker' => 'host', 'text' => 'שלום'],
            ],
        ]);

    expect($transcription->contentItem->is($item))->toBeTrue()
        ->and($transcription->author->is($author))->toBeTrue()
        ->and($transcription->status)->toBe(PublicationStatus::Published)
        ->and($transcription->published_at)->not->toBeNull()
        ->and($transcription->speakers)->toBe(['host', 'guest'])
        ->and($transcription->parsed_segments)->toBe([
            ['speaker' => 'host', 'text' => 'שלום'],
        ])
        ->and($transcription->authors()->pluck('authors.id')->all())->toBe([$author->id])
        ->and($transcription->primaryTranscriber()?->is($author))->toBeTrue()
        ->and($transcription->transcriberNames())->toBe([$author->name])
        ->and($item->refresh()->transcriptions)->toHaveCount(1)
        ->and($author->refresh()->transcriptions)->toHaveCount(1)
        ->and($author->authoredTranscriptions)->toHaveCount(1);
});

it('creates the ordered transcription transcriber pivot schema', function (): void {
    expect(Schema::hasTable('author_transcription'))->toBeTrue()
        ->and(Schema::hasColumns('author_transcription', [
            'id',
            'author_id',
            'transcription_id',
            'sort_order',
            'created_at',
            'updated_at',
        ]))->toBeTrue();
});

it('backfills existing transcription author ids into the transcriber pivot migration', function (): void {
    Schema::dropIfExists('author_transcription');

    $author = Author::factory()->create();
    $item = ContentItem::factory()->create();
    $now = now();

    $transcriptionId = DB::table('transcriptions')->insertGetId([
        'reference_key' => (string) Str::ulid(),
        'content_item_id' => $item->id,
        'author_id' => $author->id,
        'title' => 'Legacy single-author transcription',
        'language_code' => 'he',
        'transcript_markdown' => 'Legacy body',
        'status' => PublicationStatus::Published->value,
        'published_at' => $now,
        'word_count' => 2,
        'speakers' => null,
        'parsed_segments' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $migration = include collect(glob(database_path('migrations/*_create_author_transcription_table.php')))->first();
    $migration->up();

    expect(DB::table('author_transcription')
        ->where('author_id', $author->id)
        ->where('transcription_id', $transcriptionId)
        ->value('sort_order'))->toBe(0);
});

it('syncs ordered transcription transcribers and keeps the compatibility author primary', function (): void {
    $primary = Author::factory()->create(['name' => 'Primary Transcriber']);
    $secondary = Author::factory()->create(['name' => 'Secondary Transcriber']);
    $legacy = Author::factory()->create(['name' => 'Legacy Transcriber']);
    $transcription = Transcription::factory()->forAuthor($legacy)->create();

    $transcription->syncTranscribers([
        $primary,
        $secondary->id,
        $primary->id,
        null,
    ]);

    $transcription->refresh();

    expect($transcription->author_id)->toBe($primary->id)
        ->and($transcription->author->is($primary))->toBeTrue()
        ->and($transcription->primaryAuthor()?->is($primary))->toBeTrue()
        ->and($transcription->primaryTranscriber()?->is($primary))->toBeTrue()
        ->and($transcription->transcriberNames())->toBe([
            'Primary Transcriber',
            'Secondary Transcriber',
        ])
        ->and($transcription->authors()->pluck('authors.id')->all())->toBe([
            $primary->id,
            $secondary->id,
        ])
        ->and($primary->refresh()->authoredTranscriptions()->pluck('transcriptions.id')->all())->toBe([
            $transcription->id,
        ]);

    expect(DB::table('author_transcription')
        ->where('transcription_id', $transcription->id)
        ->orderBy('sort_order')
        ->pluck('sort_order', 'author_id')
        ->all())->toBe([
            $primary->id => 0,
            $secondary->id => 1,
        ]);
});

it('prevents duplicate transcription transcriber pivot pairs', function (): void {
    $author = Author::factory()->create();
    $transcription = Transcription::factory()->create();

    $transcription->syncTranscribers([$author]);

    expect(fn () => DB::table('author_transcription')->insert([
        'author_id' => $author->id,
        'transcription_id' => $transcription->id,
        'sort_order' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('keeps author id backed transcription creation compatible with the new pivot', function (): void {
    $author = Author::factory()->create();
    $item = ContentItem::factory()->create(['featured_transcription_id' => null]);

    $transcription = Transcription::factory()
        ->for($item)
        ->forAuthor($author)
        ->create();

    expect($transcription->author->is($author))->toBeTrue()
        ->and($transcription->authors()->pluck('authors.id')->all())->toBe([$author->id])
        ->and($item->refresh()->featured_transcription_id)->toBe($transcription->id);
});

it('generates immutable transcription reference keys', function (): void {
    $transcription = Transcription::factory()->create(['reference_key' => null]);
    $originalReferenceKey = $transcription->reference_key;

    $transcription->update(['reference_key' => (string) Str::ulid()]);

    expect($originalReferenceKey)->toMatch('/^[0-9A-HJKMNP-TV-Z]{26}$/')
        ->and($transcription->refresh()->reference_key)->toBe($originalReferenceKey);
});

it('drops legacy item author pivot after multi transcriber migration', function (): void {
    expect(Schema::hasTable('author_content_item'))->toBeFalse()
        ->and(Schema::hasTable('author_transcription'))->toBeTrue()
        ->and(method_exists(ContentItem::class, 'authors'))->toBeFalse()
        ->and(method_exists(Author::class, 'contentItems'))->toBeFalse();
});

it('resolves the effective transcription by featured published record then latest published record', function (): void {
    $item = ContentItem::factory()->published()->create();
    $older = Transcription::factory()->for($item)->published(now()->subDays(3))->create(['title' => 'Older']);
    $newer = Transcription::factory()->for($item)->published(now()->subDay())->create(['title' => 'Newer']);
    $draft = Transcription::factory()->for($item)->create(['title' => 'Draft']);

    $item->refresh()->update(['featured_transcription_id' => null]);

    expect($item->refresh()->effectiveTranscription()->is($newer))->toBeTrue();

    $item->update(['featured_transcription_id' => $older->id]);

    expect($item->refresh()->effectiveTranscription()->is($older))->toBeTrue();

    $item->update(['featured_transcription_id' => $draft->id]);

    expect($item->refresh()->effectiveTranscription()->is($newer))->toBeTrue();
});

it('rejects a featured transcription from another item', function (): void {
    $item = ContentItem::factory()->published()->withTranscription()->create();
    $otherItem = ContentItem::factory()->published()->withTranscription()->create();
    $otherTranscription = $otherItem->transcriptions()->firstOrFail();

    expect(fn () => $item->update(['featured_transcription_id' => $otherTranscription->id]))
        ->toThrow(ValidationException::class);
});
