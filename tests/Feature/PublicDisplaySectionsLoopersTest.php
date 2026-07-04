<?php

use App\Enums\HomepageSectionType;
use App\Enums\PublicationStatus;
use App\Filament\Resources\HomepageSections\HomepageSectionResource;
use App\Filament\Resources\HomepageSections\Pages\CreateHomepageSection;
use App\Livewire\Public\ContentItemSearch;
use App\Models\Author;
use App\Models\Category;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\ContentTag;
use App\Models\HomepageSection;
use App\Models\Transcription;
use App\Models\User;
use App\Settings\PublicContentSettings;
use App\Support\PublicFront\Sections\PublicDisplaySectionConfigValidator;
use App\Support\PublicFront\Sections\PublicDisplaySectionResolver;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Spatie\LaravelSettings\SettingsContainer;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Testable::macro('fillForm', function (array|Closure $state = [], ?string $form = null): Testable {
        if ($state instanceof Closure) {
            $state = $state([]);
        }

        $schemaStatePath = 'data';

        if (method_exists($this->instance(), 'getDefaultTestingSchemaName')) {
            $form ??= $this->instance()->getDefaultTestingSchemaName();
            $schemaStatePath = $this->instance()->{$form}->getStatePath();
        }

        foreach ($state as $key => $value) {
            $this->set(filled($schemaStatePath) ? "{$schemaStatePath}.{$key}" : $key, $value);
        }

        return $this;
    });
});

function createStep4PublicItem(
    array $itemAttributes = [],
    array $transcriptionAttributes = [],
    ?ContentGroup $group = null,
    ?Author $author = null,
): ContentItem {
    $group ??= ContentGroup::factory()->published()->create();

    $contentItem = ContentItem::factory()
        ->for($group)
        ->published($itemAttributes['published_at'] ?? now()->subMinute())
        ->create($itemAttributes);

    $transcriptionFactory = Transcription::factory()
        ->for($contentItem)
        ->published($transcriptionAttributes['published_at'] ?? now()->subMinute());

    if ($author !== null) {
        $transcriptionFactory = $transcriptionFactory->forAuthor($author);
    }

    $transcription = $transcriptionFactory->create([
        'title' => $contentItem->title,
        ...$transcriptionAttributes,
    ]);

    $contentItem->update(['featured_transcription_id' => $transcription->id]);

    return $contentItem->refresh();
}

function saveStep4PublicFrontConfig(array $config): void
{
    foreach ($config as $key => $value) {
        DB::table('settings')->updateOrInsert(
            [
                'group' => PublicContentSettings::group(),
                'name' => $key,
            ],
            [
                'locked' => false,
                'payload' => json_encode($value),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    app()->forgetInstance(PublicContentSettings::class);
    app(SettingsContainer::class)->clearCache();
}

function makeStep4CardTemplate(string $key = 'compact_episode'): array
{
    return [
        'key' => $key,
        'label' => 'Compact episode',
        'family' => 'content_item',
        'layout' => 'cards',
        'density' => 'comfortable',
        'image_size' => 'medium',
        'title_size' => 'base',
        'parts' => [
            [
                'type' => 'title',
                'source' => 'content_item',
                'attribute' => 'title',
                'visible' => true,
                'order' => 10,
                'layout' => 'inline',
                'url_target' => 'self',
            ],
        ],
    ];
}

it('casts homepage section json columns safely and keeps empty config backward compatible', function (): void {
    $section = HomepageSection::factory()->create();

    expect($section->refresh()->source_config)->toBe([])
        ->and($section->selection_config)->toBe([])
        ->and($section->display_config)->toBe([])
        ->and($section->pagination_config)->toBe([]);

    $section->forceFill([
        'source_config' => null,
        'selection_config' => null,
        'display_config' => null,
        'pagination_config' => null,
    ])->save();

    expect($section->refresh()->sourceConfig())->toBe([])
        ->and($section->selectionConfig())->toBe([])
        ->and($section->displayConfig())->toBe([])
        ->and($section->paginationConfig())->toBe([]);
});

it('renders existing homepage sections with empty json config through custom public rendering', function (): void {
    $category = Category::factory()->create(['name' => 'Legacy Section Category']);
    $item = createStep4PublicItem(['title' => 'Legacy Section Item']);
    $item->categories()->attach($category);

    HomepageSection::factory()->create([
        'name' => 'Legacy Category',
        'type' => HomepageSectionType::Category,
        'category_id' => $category->id,
        'source_config' => [],
        'selection_config' => [],
        'display_config' => [],
        'pagination_config' => [],
    ]);

    $this->get('/')
        ->assertSuccessful()
        ->assertSee('data-test="homepage-section"', false)
        ->assertSee('data-section-type="category"', false)
        ->assertSee($item->title)
        ->assertDontSee('fi-ta-table', false);
});

it('resolves latest content items with public visibility constraints only', function (): void {
    $visible = createStep4PublicItem(['title' => 'Step4 Visible Latest']);
    createStep4PublicItem(['title' => 'Step4 Draft Group Latest'], [], ContentGroup::factory()->create());
    ContentItem::factory()
        ->for(ContentGroup::factory()->published())
        ->withTranscription()
        ->create(['title' => 'Step4 Draft Item Latest']);
    ContentItem::factory()
        ->for(ContentGroup::factory()->published())
        ->published()
        ->create(['title' => 'Step4 No Transcript Latest']);
    ContentItem::factory()
        ->for(ContentGroup::factory()->published())
        ->published()
        ->withTranscription([
            'status' => PublicationStatus::Draft,
            'published_at' => null,
        ])
        ->create(['title' => 'Step4 Draft Transcript Latest']);

    HomepageSection::factory()->create([
        'name' => 'Latest JSON',
        'type' => HomepageSectionType::Latest,
        'source_config' => [
            'source_type' => 'latest_content_items',
            'sort' => 'latest_transcription',
            'total_limit' => 50,
        ],
        'limit' => 10,
    ]);

    Livewire::test(ContentItemSearch::class)
        ->assertSee($visible->title)
        ->assertDontSee('Step4 Draft Group Latest')
        ->assertDontSee('Step4 Draft Item Latest')
        ->assertDontSee('Step4 No Transcript Latest')
        ->assertDontSee('Step4 Draft Transcript Latest');
});

it('supports category descendants enabled tags content groups and manual include exclude sources', function (): void {
    $parent = Category::factory()->create(['name' => 'Step4 Parent']);
    $child = Category::factory()->for($parent, 'parent')->create(['name' => 'Step4 Child']);
    $childItem = createStep4PublicItem(['title' => 'Step4 Child Category Item']);
    $childItem->categories()->attach($child);

    $enabledTag = ContentTag::findOrCreateFromString('Step4 Enabled Tag', 'content')->enable();
    $taggedItem = createStep4PublicItem(['title' => 'Step4 Enabled Tag Item']);
    $taggedItem->attachTag($enabledTag);
    $disabledTag = ContentTag::findOrCreateFromString('Step4 Disabled Tag', 'content');
    $disabledTaggedItem = createStep4PublicItem(['title' => 'Step4 Disabled Tag Item']);
    $disabledTaggedItem->attachTag($disabledTag);

    $group = ContentGroup::factory()->published()->create(['title' => 'Step4 Group Source']);
    $groupItem = createStep4PublicItem(['title' => 'Step4 Group Item'], [], $group);
    $excludedManual = createStep4PublicItem(['title' => 'Step4 Excluded Manual']);
    $manualHidden = ContentItem::factory()
        ->for(ContentGroup::factory()->published())
        ->create(['title' => 'Step4 Hidden Manual']);

    HomepageSection::factory()->create([
        'name' => 'Category JSON',
        'type' => HomepageSectionType::Latest,
        'sort_order' => 1,
        'source_config' => [
            'source_type' => 'category_content_items',
            'category_id' => $parent->id,
            'include_descendants' => true,
        ],
    ]);
    HomepageSection::factory()->create([
        'name' => 'Tag JSON',
        'type' => HomepageSectionType::Latest,
        'sort_order' => 2,
        'source_config' => [
            'source_type' => 'tag_content_items',
            'tag_id' => $enabledTag->id,
        ],
    ]);
    HomepageSection::factory()->create([
        'name' => 'Group JSON',
        'type' => HomepageSectionType::Latest,
        'sort_order' => 3,
        'source_config' => [
            'source_type' => 'content_group_items',
            'content_group_id' => $group->id,
        ],
    ]);
    HomepageSection::factory()->create([
        'name' => 'Manual JSON',
        'type' => HomepageSectionType::Latest,
        'sort_order' => 4,
        'source_config' => [
            'source_type' => 'manual_content_items',
        ],
        'selection_config' => [
            'include_ids' => [$groupItem->id, $manualHidden->id, $excludedManual->id],
            'exclude_ids' => [$excludedManual->id],
        ],
    ]);

    Livewire::test(ContentItemSearch::class)
        ->assertSee($childItem->title)
        ->assertSee($taggedItem->title)
        ->assertSee($groupItem->title)
        ->assertDontSee($disabledTaggedItem->title)
        ->assertDontSee($excludedManual->title)
        ->assertDontSee($manualHidden->title);
});

it('renders content group category contributor and top transcriber sources safely', function (): void {
    $category = Category::factory()->create(['name' => 'Step4 Category Source']);
    $categoryItem = createStep4PublicItem(['title' => 'Step4 Category Source Item']);
    $categoryItem->categories()->attach($category);

    $group = ContentGroup::factory()->published()->create(['title' => 'Step4 Public Group']);
    createStep4PublicItem(['title' => 'Step4 Public Group Item'], [], $group);

    $topAuthor = Author::factory()->create(['name' => 'Step4 Top Author']);
    createStep4PublicItem(['title' => 'Step4 Author Item'], [], null, $topAuthor);

    HomepageSection::factory()->create([
        'name' => 'Groups JSON',
        'type' => HomepageSectionType::Latest,
        'sort_order' => 1,
        'source_config' => ['source_type' => 'content_groups'],
    ]);
    HomepageSection::factory()->create([
        'name' => 'Categories JSON',
        'type' => HomepageSectionType::Latest,
        'sort_order' => 2,
        'source_config' => ['source_type' => 'categories'],
    ]);
    HomepageSection::factory()->create([
        'name' => 'Contributors JSON',
        'type' => HomepageSectionType::Latest,
        'sort_order' => 3,
        'source_config' => ['source_type' => 'contributors'],
    ]);
    HomepageSection::factory()->create([
        'name' => 'Top JSON',
        'type' => HomepageSectionType::TopTranscribers,
        'sort_order' => 4,
        'source_config' => ['source_type' => 'top_transcribers'],
    ]);

    Livewire::test(ContentItemSearch::class)
        ->assertSee('data-test="content-groups-grid"', false)
        ->assertSee('data-test="categories-grid"', false)
        ->assertSee('data-test="top-transcribers-grid"', false)
        ->assertSee($group->title)
        ->assertSee($category->name)
        ->assertSee($topAuthor->name)
        ->assertSee(trans_choice('public.labels.public_transcriptions_count', 1, ['count' => 1]))
        ->assertSee(trans_choice('public.labels.public_content_items_count', 1, ['count' => 1]));
});

it('uses Step 3 template resolver compatibility attributes and safe overrides', function (): void {
    saveStep4PublicFrontConfig([
        'card_templates' => [
            makeStep4CardTemplate('compact_episode'),
        ],
    ]);

    $item = createStep4PublicItem(['title' => 'Step4 Template Item']);

    HomepageSection::factory()->create([
        'name' => 'Template JSON',
        'type' => HomepageSectionType::Latest,
        'source_config' => [
            'source_type' => 'manual_content_items',
        ],
        'selection_config' => [
            'include_ids' => [$item->id],
        ],
        'display_config' => [
            'template_family' => 'content_item',
            'template_key' => 'compact_episode',
            'template_overrides' => [
                'layout' => 'rows',
                'density' => 'compact',
                'image_size' => 'small',
                'title_size' => 'lg',
            ],
            'heading' => 'Template Heading',
            'show_heading' => true,
            'show_view_all_link' => true,
            'view_all_route_key' => 'search',
        ],
    ]);

    $this->get('/')
        ->assertSuccessful()
        ->assertSee('Template Heading')
        ->assertSee($item->title)
        ->assertSee('data-card-template-key="compact_episode"', false)
        ->assertSee('data-card-template-layout="rows"', false)
        ->assertSee('data-result-layout="rows"', false);

    $result = app(PublicDisplaySectionResolver::class)
        ->resolve(HomepageSection::query()->firstOrFail());

    expect($result?->cardTemplate?->density)->toBe('compact')
        ->and($result?->cardTemplate?->imageSize)->toBe('small')
        ->and($result?->cardTemplate?->titleSize)->toBe('lg');
});

it('falls back for missing template keys and reports invalid section config safely', function (): void {
    $item = createStep4PublicItem(['title' => 'Step4 Fallback Template Item']);

    $fallbackSection = HomepageSection::factory()->create([
        'type' => HomepageSectionType::Latest,
        'source_config' => ['source_type' => 'manual_content_items'],
        'selection_config' => ['include_ids' => [$item->id]],
        'display_config' => [
            'template_family' => 'content_item',
            'template_key' => 'missing_template',
        ],
    ]);

    $resolved = app(PublicDisplaySectionResolver::class)->resolve($fallbackSection);

    expect($resolved?->cardTemplate?->key)->toBe('default_content_item');

    $invalidSource = HomepageSection::factory()->make([
        'type' => HomepageSectionType::Latest,
        'source_config' => ['source_type' => 'curated_query'],
    ]);
    $invalidSourceResult = app(PublicDisplaySectionConfigValidator::class)->validate($invalidSource);

    expect($invalidSourceResult->isRenderable())->toBeFalse()
        ->and(collect($invalidSourceResult->invalidConfigArray())->pluck('path'))->toContain('source_config.source_type')
        ->and(app(PublicDisplaySectionResolver::class)->resolve($invalidSource))->toBeNull();
});

it('normalizes pagination modes and rejects unsafe semantic config values', function (string $mode): void {
    $section = HomepageSection::factory()->make([
        'type' => HomepageSectionType::Latest,
        'source_config' => ['source_type' => 'latest_content_items'],
        'display_config' => [
            'heading' => '<script>alert(1)</script>',
            'template_family' => 'database',
            'template_overrides' => [
                'layout' => 'grid grid-cols-2',
                'density' => 'compact',
            ],
        ],
        'pagination_config' => [
            'mode' => $mode,
            'per_page' => 6,
            'page_size_options' => [6, 12, 18],
            'total_limit' => 50,
        ],
    ]);

    $result = app(PublicDisplaySectionConfigValidator::class)->validate($section);

    expect($result->paginationConfig['mode'])->toBe($mode)
        ->and($result->displayConfig['template_overrides'])->toBe(['density' => 'compact'])
        ->and(collect($result->invalidConfigArray())->pluck('path'))->toContain(
            'display_config.heading',
            'display_config.template_family',
            'display_config.template_overrides.layout',
        );
})->with([
    'none',
    'simple',
    'load_more',
    'next_previous',
]);

it('falls back when pagination mode is invalid', function (): void {
    $section = HomepageSection::factory()->make([
        'type' => HomepageSectionType::Latest,
        'source_config' => ['source_type' => 'latest_content_items'],
        'pagination_config' => [
            'mode' => 'infinite_scroll',
            'per_page' => 6,
        ],
    ]);

    $result = app(PublicDisplaySectionConfigValidator::class)->validate($section);

    expect($result->paginationConfig['mode'])->toBe('none')
        ->and(collect($result->invalidConfigArray())->pluck('path'))->toContain('pagination_config.mode');
});

it('allows the homepage section admin form to save nested section config arrays', function (): void {
    $this->actingAs(User::factory()->create());

    $include = createStep4PublicItem(['title' => 'Admin Include Item']);
    $exclude = createStep4PublicItem(['title' => 'Admin Exclude Item']);

    Livewire::test(CreateHomepageSection::class)
        ->fillForm([
            'name' => 'Admin JSON Section',
            'slug' => 'admin-json-section',
            'type' => HomepageSectionType::Latest->value,
            'limit' => 6,
            'sort_order' => 1,
            'is_visible' => true,
            'source_config.source_type' => 'manual_content_items',
            'source_config.sort' => 'latest_transcription',
            'source_config.direction' => 'desc',
            'source_config.total_limit' => 50,
            'selection_config.include_ids' => [$include->id],
            'selection_config.exclude_ids' => [$exclude->id],
            'display_config.heading' => 'Admin Heading',
            'display_config.show_heading' => true,
            'display_config.show_view_all_link' => true,
            'display_config.view_all_route_key' => 'search',
            'display_config.template_family' => 'content_item',
            'display_config.template_key' => 'default_content_item',
            'display_config.template_overrides.layout' => 'rows',
            'display_config.template_overrides.density' => 'compact',
            'display_config.template_overrides.image_size' => 'small',
            'display_config.template_overrides.title_size' => 'lg',
            'pagination_config.mode' => 'load_more',
            'pagination_config.per_page' => 6,
            'pagination_config.page_size_options' => [6, 12],
            'pagination_config.total_limit' => 50,
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertRedirect(HomepageSectionResource::getUrl('index'));

    $section = HomepageSection::query()->where('slug', 'admin-json-section')->firstOrFail();

    expect($section->sourceConfig()['source_type'])->toBe('manual_content_items')
        ->and((int) $section->sourceConfig()['total_limit'])->toBe(50)
        ->and(array_map('intval', $section->selectionConfig()['include_ids']))->toBe([$include->id])
        ->and(array_map('intval', $section->selectionConfig()['exclude_ids']))->toBe([$exclude->id])
        ->and($section->displayConfig()['template_overrides']['layout'])->toBe('rows')
        ->and($section->paginationConfig()['mode'])->toBe('load_more')
        ->and(array_map('intval', $section->paginationConfig()['page_size_options']))->toBe([6, 12]);
});

it('does not introduce generic public display section or looper models', function (): void {
    expect(file_exists(app_path('Models/PublicDisplaySection.php')))->toBeFalse()
        ->and(file_exists(app_path('Models/PublicLooper.php')))->toBeFalse();
});
