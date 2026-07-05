<?php

use App\Enums\PublicationStatus;
use App\Livewire\Public\ContentItemBrowser;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\Transcription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('public item scope requires an effective published transcription', function (): void {
    $group = ContentGroup::factory()->published()->create();
    $visible = ContentItem::factory()->for($group)->published()->withTranscription()->create();
    $withoutTranscription = ContentItem::factory()->for($group)->published()->create();
    $draftTranscription = ContentItem::factory()->for($group)->published()->withTranscription([
        'status' => PublicationStatus::Draft,
        'published_at' => null,
    ])->create();
    $futureTranscription = ContentItem::factory()->for($group)->published()->withTranscription([
        'published_at' => now()->addDay(),
    ])->create();

    expect(ContentItem::published()->pluck('id')->all())->toBe([$visible->id])
        ->and(ContentItem::published()->whereKey($withoutTranscription)->exists())->toBeFalse()
        ->and(ContentItem::published()->whereKey($draftTranscription)->exists())->toBeFalse()
        ->and(ContentItem::published()->whereKey($futureTranscription)->exists())->toBeFalse();
});

it('hides items without effective published transcriptions from public group pages', function (): void {
    $group = ContentGroup::factory()->published()->create(['slug' => 'visibility-group']);
    $visible = ContentItem::factory()->for($group)->published()->withTranscription()->create([
        'title' => 'Visible With Transcript',
        'slug' => 'visible-with-transcript',
    ]);
    $hidden = ContentItem::factory()->for($group)->published()->create([
        'title' => 'Hidden Without Transcript',
        'slug' => 'hidden-without-transcript',
    ]);

    $this->get("/podcasts/{$group->slug}")
        ->assertSuccessful()
        ->assertSee($visible->title)
        ->assertDontSee($hidden->title);
});

it('returns not found for direct item pages without effective published transcriptions', function (): void {
    $group = ContentGroup::factory()->published()->create(['slug' => 'direct-visibility-group']);
    $item = ContentItem::factory()->for($group)->published()->create(['slug' => 'no-effective-transcript']);

    $this->get("/items/{$group->slug}/{$item->slug}")
        ->assertNotFound();
});

it('renders sanitized markdown from the effective transcription', function (): void {
    $group = ContentGroup::factory()->published()->create(['slug' => 'transcript-render-group']);
    $item = ContentItem::factory()->for($group)->published()->withTranscription([
        'transcript_markdown' => "## Transcript\n\nשלום <script>alert('x')</script>",
    ])->create(['slug' => 'transcript-render-item']);

    $this->get("/items/{$group->slug}/{$item->slug}")
        ->assertSuccessful()
        ->assertSee('<h2>Transcript</h2>', false)
        ->assertDontSee("alert('x')");
});

it('sorts public items by effective transcription publication date', function (): void {
    $group = ContentGroup::factory()->published()->create();
    $older = ContentItem::factory()->for($group)->published(now()->subHour())->withTranscription([
        'published_at' => now()->subDays(2),
    ])->create(['title' => 'Older Transcript']);
    $newer = ContentItem::factory()->for($group)->published(now()->subDays(2))->withTranscription([
        'published_at' => now()->subDay(),
    ])->create(['title' => 'Newer Transcript']);

    Livewire::test(ContentItemBrowser::class, ['contentGroup' => $group])
        ->set('sort', 'newest')
        ->assertSeeInOrder([$newer->title, $older->title]);
});

it('ignores an unpublished featured transcription when resolving public content', function (): void {
    $group = ContentGroup::factory()->published()->create();
    $item = ContentItem::factory()->for($group)->published()->create();
    $published = Transcription::factory()->for($item)->published(now()->subDay())->create([
        'transcript_markdown' => 'Published transcript',
    ]);
    $draft = Transcription::factory()->for($item)->create([
        'transcript_markdown' => 'Draft transcript',
    ]);

    $item->update(['featured_transcription_id' => $draft->id]);

    expect($item->refresh()->effectiveTranscription()->is($published))->toBeTrue()
        ->and(ContentItem::published()->whereKey($item)->exists())->toBeTrue();
});
