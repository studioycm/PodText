<?php

use App\Enums\HomepageSectionType;
use App\Models\Category;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\ContentTag;
use App\Models\HomepageSection;
use App\Settings\PublicContentSettings;
use App\Support\PublicFront\PublicFrontConfigRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

it('stores category hierarchy and resolves descendants', function (): void {
    $parent = Category::factory()->create(['name' => 'Parent']);
    $child = Category::factory()->for($parent, 'parent')->create(['name' => 'Child']);
    $grandchild = Category::factory()->for($child, 'parent')->create(['name' => 'Grandchild']);
    $sibling = Category::factory()->create(['name' => 'Sibling']);

    expect($parent->children)->toHaveCount(1)
        ->and($child->parent->is($parent))->toBeTrue()
        ->and($parent->descendantIds()->all())->toEqualCanonicalizing([$parent->id, $child->id, $grandchild->id])
        ->and($parent->descendantIds()->all())->not->toContain($sibling->id);
});

it('filters items by direct and inherited category descendants', function (): void {
    $parent = Category::factory()->create();
    $child = Category::factory()->for($parent, 'parent')->create();
    $directCategory = Category::factory()->create();
    $otherCategory = Category::factory()->create();

    $group = ContentGroup::factory()->create();
    $group->categories()->attach($child);

    $inherited = ContentItem::factory()->for($group)->create();
    $direct = ContentItem::factory()->create();
    $direct->categories()->attach($directCategory);

    $other = ContentItem::factory()->create();
    $other->categories()->attach($otherCategory);

    expect(ContentItem::inCategoryTree($parent)->pluck('id')->all())->toBe([$inherited->id])
        ->and(ContentItem::inCategoryTree($directCategory)->pluck('id')->all())->toBe([$direct->id])
        ->and($inherited->effectiveCategories()->pluck('id')->all())->toBe([$child->id])
        ->and($direct->effectiveCategories()->pluck('id')->all())->toBe([$directCategory->id]);
});

it('uses Spatie content tags with enabled-only public behavior', function (): void {
    $item = ContentItem::factory()->create();
    $enabled = ContentTag::findOrCreate('Visible Tag', 'content');
    $disabled = ContentTag::findOrCreate('Hidden Tag', 'content');
    $otherType = ContentTag::findOrCreate('Other Type', 'internal');

    $enabled->enable();

    $item->attachTags([$enabled, $disabled, $otherType]);

    expect($enabled->type)->toBe('content')
        ->and($enabled->is_enabled)->toBeTrue()
        ->and($disabled->refresh()->is_enabled)->toBeFalse()
        ->and($item->publicTags()->pluck('id')->all())->toBe([$enabled->id])
        ->and(ContentItem::withEnabledContentTag($enabled)->pluck('id')->all())->toBe([$item->id])
        ->and(ContentItem::withEnabledContentTag($disabled)->pluck('id')->all())->toBe([])
        ->and(Schema::hasTable('taggables'))->toBeTrue()
        ->and(Schema::hasTable('content_item_tag'))->toBeFalse();
});

it('orders only currently valid pinned items', function (): void {
    $now = Carbon::parse('2026-06-30 12:00:00');

    $first = ContentItem::factory()->pinned(order: 1, pinnedAt: $now->copy()->subHour())->create();
    $second = ContentItem::factory()->pinned(order: 2, pinnedAt: $now->copy()->subHours(2))->create();
    $unordered = ContentItem::factory()->create([
        'is_pinned' => true,
        'pinned_at' => $now->copy()->subMinutes(30),
        'pin_order' => null,
    ]);

    ContentItem::factory()->create([
        'is_pinned' => true,
        'pinned_at' => $now->copy()->addMinute(),
        'pin_order' => 0,
    ]);
    ContentItem::factory()->create([
        'is_pinned' => true,
        'pinned_at' => $now->copy()->subDay(),
        'pinned_until' => $now->copy()->subMinute(),
        'pin_order' => 0,
    ]);
    ContentItem::factory()->create(['is_pinned' => false]);

    expect($first->isCurrentlyPinned($now))->toBeTrue()
        ->and(ContentItem::currentlyPinned($now)->orderedForPins()->pluck('id')->all())->toBe([
            $first->id,
            $second->id,
            $unordered->id,
        ]);
});

it('loads public content settings defaults', function (): void {
    $settings = app(PublicContentSettings::class);

    expect($settings->homepage_item_limit)->toBe(12)
        ->and($settings->pinned_item_limit)->toBe(6)
        ->and($settings->default_public_sort)->toBe('latest_transcription')
        ->and($settings->default_result_layout)->toBe('cards')
        ->and($settings->show_latest_section)->toBeTrue()
        ->and($settings->item_page_layout)->toBe('standard')
        ->and($settings->homepage_card_image_size)->toBe('medium')
        ->and($settings->homepage_card_density)->toBe('comfortable')
        ->and($settings->homepage_card_title_size)->toBe('base')
        ->and($settings->homepage_show_group_badge)->toBeTrue()
        ->and($settings->homepage_show_authors)->toBeTrue()
        ->and($settings->homepage_show_categories)->toBeTrue()
        ->and($settings->homepage_show_tags)->toBeTrue()
        ->and($settings->homepage_show_duration)->toBeTrue()
        ->and($settings->homepage_show_effective_date)->toBeTrue()
        ->and($settings->homepage_show_description)->toBeTrue()
        ->and($settings->homepage_description_lines)->toBe(3)
        ->and($settings->homepage_cards_per_page)->toBe(12)
        ->and([
            'card_templates' => $settings->card_templates,
            'menu_config' => $settings->menu_config,
            'about_page' => $settings->about_page,
            'public_forms' => $settings->public_forms,
            'route_labels' => $settings->route_labels,
            'display_defaults' => $settings->display_defaults,
        ])->toBe(PublicFrontConfigRegistry::defaults());
});

it('stores homepage sections with finite type casts and visible ordering', function (): void {
    $category = Category::factory()->create();
    $tag = ContentTag::findOrCreate('Featured', 'content');
    $group = ContentGroup::factory()->create();

    $second = HomepageSection::factory()->create([
        'name' => 'Second',
        'type' => HomepageSectionType::Category,
        'category_id' => $category->id,
        'tag_id' => $tag->id,
        'content_group_id' => $group->id,
        'sort_order' => 2,
    ]);
    $first = HomepageSection::factory()->create([
        'name' => 'First',
        'sort_order' => 1,
    ]);
    HomepageSection::factory()->create([
        'name' => 'Hidden',
        'is_visible' => false,
        'sort_order' => 0,
    ]);

    expect($second->refresh()->type)->toBe(HomepageSectionType::Category)
        ->and($second->category->is($category))->toBeTrue()
        ->and($second->tag->is($tag))->toBeTrue()
        ->and($second->contentGroup->is($group))->toBeTrue()
        ->and(HomepageSection::visible()->ordered()->pluck('id')->all())->toBe([$first->id, $second->id]);
});

it('validates media foundation URLs and metadata shape', function (): void {
    $valid = Validator::make([
        'media_url' => 'https://example.com/source',
        'embed_url' => 'https://www.youtube.com/embed/demo',
        'external_thumbnail_url' => 'https://example.com/thumb.jpg',
        'direct_media_url' => 'https://cdn.example.com/audio.mp3',
        'media_metadata' => ['duration' => 120],
    ], ContentItem::mediaValidationRules());

    $invalid = Validator::make([
        'media_url' => 'http://example.com/source',
        'embed_url' => '<iframe src="https://www.youtube.com/embed/demo"></iframe>',
        'external_thumbnail_url' => 'https://example.com/thumb.jpg',
        'direct_media_url' => 'http://cdn.example.com/audio.mp3',
        'media_metadata' => 'not-json-array',
    ], ContentItem::mediaValidationRules());

    expect($valid->passes())->toBeTrue()
        ->and($invalid->passes())->toBeFalse()
        ->and($invalid->errors()->keys())->toEqualCanonicalizing([
            'media_url',
            'embed_url',
            'direct_media_url',
            'media_metadata',
        ]);
});
