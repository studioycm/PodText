<?php

use App\Models\Author;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\Transcription;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createHotfixPublicTranscriptItem(string $markdown): array
{
    $group = ContentGroup::factory()->published()->create([
        'slug' => 'hotfix-transcript-group',
    ]);
    $item = ContentItem::factory()
        ->for($group)
        ->published()
        ->create([
            'slug' => 'hotfix-transcript-item',
        ]);
    $transcription = Transcription::factory()
        ->for($item)
        ->forAuthor(Author::factory()->create())
        ->published()
        ->create([
            'title' => 'Hotfix transcript',
            'transcript_markdown' => $markdown,
        ]);

    $item->update(['featured_transcription_id' => $transcription->getKey()]);

    return [$group->refresh(), $item->refresh(), $transcription->refresh()];
}

it('PublicTranscriptRenderingTest renders a long fallback transcript with markdown formatting', function (): void {
    $longTail = str_repeat("שורה ארוכה לבדיקה\n", 1400).'PUBLIC_FALLBACK_FINAL_TOKEN';
    [$group, $item] = createHotfixPublicTranscriptItem(<<<MARKDOWN
Fallback paragraph with **bold** and *italic*.
single line break

{$longTail}
MARKDOWN);

    $this->get("/items/{$group->slug}/{$item->slug}")
        ->assertSuccessful()
        ->assertSee('data-test="transcript-fallback-content"', false)
        ->assertSee('<strong>bold</strong>', false)
        ->assertSee('<em>italic</em>', false)
        ->assertSee('<br', false)
        ->assertSee('PUBLIC_FALLBACK_FINAL_TOKEN');
});

it('PublicTranscriptRenderingTest renders parsed segment transcript markdown formatting', function (): void {
    [$group, $item] = createHotfixPublicTranscriptItem(<<<'MARKDOWN'
[00:02:03] Speaker: Parsed segment with **bold**.
visible soft break
MARKDOWN);

    $this->get("/items/{$group->slug}/{$item->slug}")
        ->assertSuccessful()
        ->assertSee('data-test="transcript-segment-content"', false)
        ->assertSee('<strong>bold</strong>', false)
        ->assertSee('<br', false)
        ->assertSee('id="t-123"', false)
        ->assertSee('dir="ltr"', false);
});
