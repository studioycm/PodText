<?php

use App\Enums\PublicationStatus;
use App\Models\Author;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\Transcription;
use App\Support\Markdown\SafeMarkdownRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('casts publication status enum values on groups and items', function (): void {
    $group = ContentGroup::factory()->create([
        'status' => PublicationStatus::Published,
    ]);

    $item = ContentItem::factory()->for($group)->create([
        'status' => PublicationStatus::Draft,
    ]);

    expect($group->refresh()->status)->toBe(PublicationStatus::Published)
        ->and($item->refresh()->status)->toBe(PublicationStatus::Draft);
});

it('defines content group item relationships', function (): void {
    $group = ContentGroup::factory()->create();
    $item = ContentItem::factory()->for($group)->create();

    expect($group->contentItems)->toHaveCount(1)
        ->and($group->contentItems->first()->is($item))->toBeTrue()
        ->and($item->contentGroup->is($group))->toBeTrue();
});

it('removes legacy content item author relationships', function (): void {
    expect(Schema::hasTable('author_content_item'))->toBeFalse()
        ->and(method_exists(ContentItem::class, 'authors'))->toBeFalse()
        ->and(method_exists(Author::class, 'contentItems'))->toBeFalse();
});

it('generates unique reference keys and prevents ordinary edits from replacing them', function (): void {
    $group = ContentGroup::factory()->create(['reference_key' => null]);
    $author = Author::factory()->create(['reference_key' => null]);
    $item = ContentItem::factory()->create(['reference_key' => null]);

    expect($group->reference_key)->toMatch('/^[0-9A-HJKMNP-TV-Z]{26}$/')
        ->and($author->reference_key)->toMatch('/^[0-9A-HJKMNP-TV-Z]{26}$/')
        ->and($item->reference_key)->toMatch('/^[0-9A-HJKMNP-TV-Z]{26}$/')
        ->and(array_unique([$group->reference_key, $author->reference_key, $item->reference_key]))->toHaveCount(3);

    $originalReferenceKey = $group->reference_key;

    $group->update(['reference_key' => (string) Str::ulid()]);

    expect($group->refresh()->reference_key)->toBe($originalReferenceKey);
});

it('allows supplied reference keys for new records', function (): void {
    $referenceKey = (string) Str::ulid();

    $author = Author::factory()->create([
        'reference_key' => $referenceKey,
    ]);

    expect($author->reference_key)->toBe($referenceKey);
});

it('applies group label defaults and item label inheritance', function (): void {
    $group = ContentGroup::factory()->create([
        'group_type_label_singular' => null,
        'group_type_label_plural' => null,
        'default_item_type_label_singular' => null,
        'default_item_type_label_plural' => null,
    ]);

    $item = ContentItem::factory()->for($group)->create([
        'type_label_singular_override' => null,
    ]);

    expect($group->group_type_label_singular)->toBe('Podcast')
        ->and($group->group_type_label_plural)->toBe('Podcasts')
        ->and($group->default_item_type_label_singular)->toBe('Episode')
        ->and($group->default_item_type_label_plural)->toBe('Episodes')
        ->and($item->effectiveTypeLabelSingular())->toBe('Episode');
});

it('uses an item type-label override when present', function (): void {
    $group = ContentGroup::factory()->create([
        'default_item_type_label_singular' => 'Lecture',
    ]);

    $item = ContentItem::factory()->for($group)->create([
        'type_label_singular_override' => 'Interview',
    ]);

    expect($item->effectiveTypeLabelSingular())->toBe('Interview');
});

it('scopes published groups by status and publication date', function (): void {
    $published = ContentGroup::factory()->published()->create();
    ContentGroup::factory()->create(['status' => PublicationStatus::Draft]);
    ContentGroup::factory()->published(now()->addDay())->create();

    expect(ContentGroup::published()->pluck('id')->all())->toBe([$published->id]);
});

it('scopes published items by item state and parent group visibility', function (): void {
    $publishedGroup = ContentGroup::factory()->published()->create();
    $draftGroup = ContentGroup::factory()->create(['status' => PublicationStatus::Draft]);

    $publishedItem = ContentItem::factory()->for($publishedGroup)->published()->withTranscription()->create();
    ContentItem::factory()->for($publishedGroup)->withTranscription()->create(['status' => PublicationStatus::Draft]);
    ContentItem::factory()->for($publishedGroup)->published(now()->addDay())->withTranscription()->create();
    ContentItem::factory()->for($draftGroup)->published()->withTranscription()->create();

    expect(ContentItem::published()->pluck('id')->all())->toBe([$publishedItem->id]);
});

it('deleting an author detaches transcription credits without deleting content items', function (): void {
    $author = Author::factory()->create();
    $item = ContentItem::factory()->create();
    $transcription = Transcription::factory()->for($item)->forAuthor($author)->create();

    $transcription->syncTranscribers([$author]);

    $author->delete();

    expect(ContentItem::query()->whereKey($item)->exists())->toBeTrue()
        ->and(Transcription::query()->whereKey($transcription)->exists())->toBeTrue()
        ->and($transcription->refresh()->author_id)->toBeNull()
        ->and($transcription->authors()->count())->toBe(0);
});

it('renders markdown formatting through the safe renderer', function (): void {
    $html = app(SafeMarkdownRenderer::class)->toHtml("# כותרת\n\nטקסט **מודגש**.");

    expect($html)->toContain('<h1>כותרת</h1>')
        ->and($html)->toContain('<strong>מודגש</strong>');
});

it('sanitizes executable markdown and html content', function (): void {
    $html = app(SafeMarkdownRenderer::class)->toHtml(<<<'MARKDOWN'
[unsafe](javascript:alert(1))

<img src="x" onerror="alert(1)">

<script>alert("xss")</script>
MARKDOWN);

    expect($html)->not->toContain('javascript:')
        ->and($html)->not->toContain('onerror')
        ->and($html)->not->toContain('<script')
        ->and($html)->not->toContain('alert(');
});
