<?php

use App\Enums\PublicationStatus;
use App\Models\Author;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\Transcription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
        ->and($item->refresh()->transcriptions)->toHaveCount(1)
        ->and($author->refresh()->transcriptions)->toHaveCount(1);
});

it('generates immutable transcription reference keys', function (): void {
    $transcription = Transcription::factory()->create(['reference_key' => null]);
    $originalReferenceKey = $transcription->reference_key;

    $transcription->update(['reference_key' => (string) Str::ulid()]);

    expect($originalReferenceKey)->toMatch('/^[0-9A-HJKMNP-TV-Z]{26}$/')
        ->and($transcription->refresh()->reference_key)->toBe($originalReferenceKey);
});

it('backfills legacy item transcript markdown into canonical transcriptions', function (): void {
    $group = ContentGroup::factory()->published()->create();
    $author = Author::factory()->create();
    $item = ContentItem::factory()->for($group)->published(now()->subDay())->create([
        'title' => 'Legacy Transcript Item',
    ]);
    $item->authors()->attach($author);

    DB::table('content_items')
        ->where('id', $item->id)
        ->update(['transcript_markdown' => "## Legacy\n\nשלום"]);

    $migration = include collect(glob(database_path('migrations/*_backfill_transcriptions_from_content_items_table.php')))->first();
    $migration->up();

    $transcription = Transcription::query()->whereBelongsTo($item)->firstOrFail();

    expect($transcription->transcript_markdown)->toBe("## Legacy\n\nשלום")
        ->and($transcription->author_id)->toBe($author->id)
        ->and($transcription->status)->toBe(PublicationStatus::Published)
        ->and($transcription->published_at?->toDateTimeString())->toBe($item->published_at?->toDateTimeString())
        ->and($item->refresh()->featured_transcription_id)->toBe($transcription->id);
});

it('resolves the effective transcription by featured published record then latest published record', function (): void {
    $item = ContentItem::factory()->published()->create();
    $older = Transcription::factory()->for($item)->published(now()->subDays(3))->create(['title' => 'Older']);
    $newer = Transcription::factory()->for($item)->published(now()->subDay())->create(['title' => 'Newer']);
    $draft = Transcription::factory()->for($item)->create(['title' => 'Draft']);

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
