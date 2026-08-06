<?php

use App\Enums\PublicationStatus;
use App\Enums\TranscriptionMode;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\Transcription;
use App\Support\Transcriptions\EffectiveTranscriptionResolver;
use Illuminate\Database\LazyLoadingViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    // The featured-versus-latest question only arises when an episode can
    // have more than one transcript; the single lens forbids the second.
    setTestTranscriptionMode(TranscriptionMode::Multi);
});

/** Every shape the rule has to decide between. */
function resolverScenarios(): array
{
    $group = ContentGroup::factory()->published()->create();
    $make = fn (array $attributes = []): ContentItem => ContentItem::factory()->for($group, 'contentGroup')->create($attributes);

    $featuredWins = $make();
    $older = Transcription::factory()->for($featuredWins)->create(['status' => PublicationStatus::Published, 'published_at' => now()->subYear(), 'title' => 'featured']);
    Transcription::factory()->for($featuredWins)->create(['status' => PublicationStatus::Published, 'published_at' => now()->subDay(), 'title' => 'newer']);
    $featuredWins->forceFill(['featured_transcription_id' => $older->getKey()])->save();

    $featuredDraft = $make();
    $draft = Transcription::factory()->for($featuredDraft)->create(['status' => PublicationStatus::Draft, 'published_at' => null, 'title' => 'draft featured']);
    $published = Transcription::factory()->for($featuredDraft)->create(['status' => PublicationStatus::Published, 'published_at' => now()->subDay(), 'title' => 'fallback']);
    $featuredDraft->forceFill(['featured_transcription_id' => $draft->getKey()])->save();

    $nonedPublished = $make();
    Transcription::factory()->for($nonedPublished)->create(['status' => PublicationStatus::Draft, 'published_at' => null]);

    return [
        // The featured transcript wins even when a newer one is published.
        'featured beats newer' => [$featuredWins, $older->getKey()],
        // A draft featured transcript loses to the latest published one.
        'draft featured falls back' => [$featuredDraft, $published->getKey()],
        'nothing published' => [$nonedPublished, null],
    ];
}

it('resolves the same transcription the model always did', function (): void {
    foreach (resolverScenarios() as $name => [$item, $expected]) {
        $resolved = app(EffectiveTranscriptionResolver::class)->for($item->fresh());

        expect($resolved?->getKey())->toBe($expected, "scenario [{$name}]");
    }
});

it('never touches the fallback relation when the featured transcript answers', function (): void {
    $item = ContentItem::factory()
        ->for(ContentGroup::factory()->published(), 'contentGroup')
        ->withTranscription(['status' => PublicationStatus::Published, 'published_at' => now()->subDay()])
        ->create();

    $fresh = ContentItem::query()->findOrFail($item->getKey());

    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    // Short-circuit: asking the featured branch first means the fallback and
    // its authors are never loaded on the episodes that production actually
    // has. Two relation queries, not four.
    expect(app(EffectiveTranscriptionResolver::class)->for($fresh))->not->toBeNull()
        ->and($queries)->toBe(2)
        ->and($fresh->relationLoaded('latestPublishedTranscription'))->toBeFalse();
});

it('raises rather than guessing when asked to resolve from unloaded relations', function (): void {
    ContentItem::factory()->count(2)
        ->for(ContentGroup::factory()->published(), 'contentGroup')
        ->withTranscription(['status' => PublicationStatus::Published, 'published_at' => now()->subDay()])
        ->create();

    // forLoaded() is the strict entry point: a caller that forgot the eager
    // load has a broken contract, not an episode without a transcript.
    $unprimed = ContentItem::query()->get();

    expect(fn (): ?Transcription => app(EffectiveTranscriptionResolver::class)->forLoaded($unprimed->first()))
        ->toThrow(LazyLoadingViolationException::class);
});
