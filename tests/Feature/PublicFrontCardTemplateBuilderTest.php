<?php

use App\Enums\HomepageSectionType;
use App\Filament\Pages\PublicContentSettings as PublicContentSettingsPage;
use App\Filament\Resources\HomepageSections\Pages\CreateHomepageSection;
use App\Livewire\Public\TopTranscribersSection;
use App\Models\Author;
use App\Models\Category;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\ContentTag;
use App\Models\HomepageSection;
use App\Models\Transcription;
use App\Models\User;
use App\Settings\PublicContentSettings;
use App\Support\PublicFront\Cards\PublicFrontCardTemplateResolver;
use App\Support\PublicFront\PublicFrontConfigReader;
use App\Support\PublicFront\PublicFrontConfigValidator;
use App\Support\PublicFront\PublicFrontRenderContext;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\LaravelSettings\SettingsContainer;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

function clearStep3PublicFrontSettingsCache(): void
{
    app()->forgetInstance(PublicContentSettings::class);
    app()->forgetInstance(PublicFrontRenderContext::class);
    app(SettingsContainer::class)->clearCache();
}

function saveStep3PublicFrontConfig(array $config): void
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

    clearStep3PublicFrontSettingsCache();
}

function makeStep3CardTemplate(string $family, string $key): array
{
    $source = match ($family) {
        'content_group' => 'content_group',
        'contributor' => 'author',
        default => 'content_item',
    };

    $attribute = match ($family) {
        'content_group' => 'title',
        'contributor' => 'name',
        default => 'title',
    };

    return [
        'key' => $key,
        'label' => "Template {$key}",
        'family' => $family,
        'layout' => 'rows',
        'density' => 'compact',
        'image_size' => 'small',
        'title_size' => 'lg',
        'parts' => [
            [
                'type' => 'title',
                'source' => $source,
                'attribute' => $attribute,
                'visible' => true,
                'order' => 10,
                'layout' => 'inline',
                'url_target' => 'self',
            ],
        ],
    ];
}

function makeStep10rB2ContentItemTemplate(string $key, array $parts, array $overrides = []): array
{
    return [
        'key' => $key,
        'label' => "Template {$key}",
        'family' => 'content_item',
        'layout' => 'cards',
        'density' => 'comfortable',
        'image_size' => 'hidden',
        'title_size' => 'base',
        'parts' => $parts,
        ...$overrides,
    ];
}

function makeStep10rB3ContentGroupTemplate(string $key, array $parts, array $overrides = []): array
{
    return [
        'key' => $key,
        'label' => "Template {$key}",
        'family' => 'content_group',
        'layout' => 'cards',
        'density' => 'comfortable',
        'image_size' => 'hidden',
        'title_size' => 'base',
        'parts' => $parts,
        ...$overrides,
    ];
}

function makeStep10rB3ContributorTemplate(string $key, array $parts, array $overrides = []): array
{
    return [
        'key' => $key,
        'label' => "Template {$key}",
        'family' => 'contributor',
        'layout' => 'cards',
        'density' => 'comfortable',
        'image_size' => 'hidden',
        'title_size' => 'base',
        'parts' => $parts,
        ...$overrides,
    ];
}

function createStep3PublicItem(array $itemAttributes = [], ?ContentGroup $group = null): ContentItem
{
    $group ??= ContentGroup::factory()->published()->create();

    return ContentItem::factory()
        ->for($group)
        ->published()
        ->withTranscription(['published_at' => now()->subMinute()])
        ->create($itemAttributes);
}

function createStep10rB3PublicContributorItem(Author $author, array $itemAttributes = [], ?ContentGroup $group = null): ContentItem
{
    $group ??= ContentGroup::factory()->published()->create();

    $item = ContentItem::factory()
        ->for($group)
        ->published()
        ->create([
            'title' => 'B3 Contributor Item',
            ...$itemAttributes,
        ]);

    $transcription = Transcription::factory()
        ->for($item)
        ->forAuthor($author)
        ->published(now()->subMinute())
        ->create([
            'title' => $item->title,
        ]);

    $item->update(['featured_transcription_id' => $transcription->id]);

    return $item->refresh();
}

function step10rB1SelectHasOptions(Select $field, array $expected, array $unexpected = []): bool
{
    $options = $field->getOptions();

    foreach ($expected as $key => $label) {
        if (! array_key_exists($key, $options) || $options[$key] !== $label) {
            return false;
        }
    }

    foreach ($unexpected as $key) {
        if (array_key_exists($key, $options)) {
            return false;
        }
    }

    return true;
}

function step10rB1SelectByStatePath(mixed $component, string $statePath): ?Select
{
    $absoluteStatePath = str_starts_with($statePath, 'data.')
        ? $statePath
        : "data.{$statePath}";

    return collect($component->instance()->getSchema('form')->getFlatComponents(withActions: false, withHidden: true, withAbsoluteKeys: true))
        ->first(fn (mixed $schemaComponent): bool => $schemaComponent instanceof Select
            && $schemaComponent->getStatePath() === $absoluteStatePath);
}

it('loads default card templates when card templates are empty', function (): void {
    saveStep3PublicFrontConfig(['card_templates' => []]);

    $resolver = app(PublicFrontCardTemplateResolver::class);

    expect(app(PublicFrontConfigReader::class)->read()->group('card_templates'))->toBe([])
        ->and($resolver->resolve('content_item')->key)->toBe('default_content_item')
        ->and($resolver->resolve('content_group')->key)->toBe('default_content_group')
        ->and($resolver->resolve('contributor')->key)->toBe('default_contributor')
        ->and($resolver->resolve('content_item')->partTypes())->toContain(
            'image',
            'transcriber_line',
            'date_read_time',
            'group_identity',
            'title',
            'description',
            'taxonomy',
        );
});

it('accepts and normalizes valid card templates for every supported family', function (string $family): void {
    $result = app(PublicFrontConfigValidator::class)->validate([
        'card_templates' => [
            makeStep3CardTemplate($family, "{$family}_custom"),
        ],
    ]);

    expect($result->hasInvalidConfig())->toBeFalse()
        ->and($result->group('card_templates'))->toHaveCount(1)
        ->and($result->group('card_templates')[0])->toMatchArray([
            'key' => "{$family}_custom",
            'family' => $family,
            'layout' => 'rows',
            'density' => 'compact',
            'image_size' => 'small',
            'title_size' => 'lg',
        ])
        ->and($result->group('card_templates')[0]['parts'][0])->not->toHaveKey('data');
})->with([
    'content item' => ['content_item'],
    'content group' => ['content_group'],
    'contributor' => ['contributor'],
]);

it('reports invalid family and falls back to the family default template', function (): void {
    $result = app(PublicFrontConfigValidator::class)->validate([
        'card_templates' => [
            [
                ...makeStep3CardTemplate('content_item', 'bad_family'),
                'family' => 'podcast',
            ],
        ],
    ]);

    $paths = collect($result->invalidConfig())->map(fn ($invalidConfig): string => $invalidConfig->path);
    $template = app(PublicFrontCardTemplateResolver::class)
        ->resolveFromTemplates($result->group('card_templates'), 'content_item', 'bad_family');

    expect($result->group('card_templates'))->toBe([])
        ->and($paths)->toContain('card_templates.0.family')
        ->and($template->key)->toBe('default_content_item');
});

it('skips invalid part types sources and attributes with invalid config reports', function (): void {
    $result = app(PublicFrontConfigValidator::class)->validate([
        'card_templates' => [
            [
                ...makeStep3CardTemplate('content_item', 'invalid_parts'),
                'parts' => [
                    [
                        'type' => 'raw_html',
                        'source' => 'content_item',
                        'attribute' => 'title',
                    ],
                    [
                        'type' => 'title',
                        'source' => 'database',
                        'attribute' => 'title',
                    ],
                    [
                        'type' => 'title',
                        'source' => 'content_item',
                        'attribute' => 'unknown_column',
                    ],
                    [
                        'type' => 'title',
                        'source' => 'content_item',
                        'attribute' => 'title',
                    ],
                ],
            ],
        ],
    ]);

    $paths = collect($result->invalidConfig())->map(fn ($invalidConfig): string => $invalidConfig->path);
    $parts = $result->group('card_templates')[0]['parts'];

    expect($parts)->toHaveCount(1)
        ->and($parts[0]['type'])->toBe('title')
        ->and($paths)->toContain('card_templates.0.parts.0.type')
        ->and($paths)->toContain('card_templates.0.parts.1.source')
        ->and($paths)->toContain('card_templates.0.parts.2.attribute');
});

it('rejects unsafe css tailwind blade php html script and iframe looking values', function (): void {
    $result = app(PublicFrontConfigValidator::class)->validate([
        'card_templates' => [
            [
                ...makeStep3CardTemplate('content_item', 'unsafe_values'),
                'label' => 'App\\Filament\\Pages\\PublicContentSettings::class',
                'layout' => 'grid grid-cols-2',
                'parts' => [
                    [
                        'type' => 'title',
                        'source' => 'content_item',
                        'attribute' => 'resources/views/public/card.blade.php',
                    ],
                    [
                        'type' => 'custom_text',
                        'source' => 'custom',
                        'attribute' => 'text',
                        'text' => '<script>alert(1)</script>',
                    ],
                    [
                        'type' => 'description',
                        'source' => 'content_item',
                        'attribute' => 'description',
                        'label' => '<iframe src="https://example.com"></iframe>',
                        'layout' => 'font-size: 12px;',
                    ],
                ],
            ],
        ],
    ]);

    $paths = collect($result->invalidConfig())->map(fn ($invalidConfig): string => $invalidConfig->path);
    $template = $result->group('card_templates')[0];

    expect($template['label'])->toBe('unsafe_values')
        ->and($template['layout'])->toBe('cards')
        ->and($template['parts'])->toHaveCount(1)
        ->and($template['parts'][0])->toMatchArray([
            'type' => 'description',
            'source' => 'content_item',
            'attribute' => 'description',
            'layout' => 'inline',
        ])
        ->and($paths)->toContain('card_templates.0.label')
        ->and($paths)->toContain('card_templates.0.layout')
        ->and($paths)->toContain('card_templates.0.parts.0.attribute')
        ->and($paths)->toContain('card_templates.0.parts.1.text')
        ->and($paths)->toContain('card_templates.0.parts.2.label')
        ->and($paths)->toContain('card_templates.0.parts.2.layout');
});

it('falls back to a family default when a requested template key is missing', function (): void {
    $template = app(PublicFrontCardTemplateResolver::class)->resolveFromTemplates([
        makeStep3CardTemplate('content_item', 'available_template'),
    ], 'content_item', 'missing_template');

    expect($template->key)->toBe('default_content_item')
        ->and($template->family)->toBe('content_item');
});

it('exposes saved custom templates in family scoped admin selects', function (): void {
    $this->actingAs(User::factory()->create());

    saveStep3PublicFrontConfig([
        'card_templates' => [
            makeStep3CardTemplate('content_item', 'saved_episode_card'),
            makeStep3CardTemplate('content_group', 'saved_podcast_card'),
            makeStep3CardTemplate('contributor', 'saved_contributor_card'),
        ],
    ]);

    $settingsPage = Livewire::test(PublicContentSettingsPage::class);
    $podcastItemSelect = step10rB1SelectByStatePath($settingsPage, 'podcasts_page.item_template_key');
    $podcastGroupSelect = step10rB1SelectByStatePath($settingsPage, 'podcasts_page.template_key');

    expect($podcastItemSelect)->toBeInstanceOf(Select::class)
        ->and(step10rB1SelectHasOptions($podcastItemSelect, [
            'default_content_item' => 'Default content item card',
            'saved_episode_card' => 'Template saved_episode_card',
        ], [
            'saved_podcast_card',
            'saved_contributor_card',
        ]))->toBeTrue()
        ->and($podcastGroupSelect)->toBeInstanceOf(Select::class)
        ->and(step10rB1SelectHasOptions($podcastGroupSelect, [
            'default_content_group' => 'Default content group card',
            'saved_podcast_card' => 'Template saved_podcast_card',
        ], [
            'saved_episode_card',
            'saved_contributor_card',
        ]))->toBeTrue();

    $homepageSection = Livewire::test(CreateHomepageSection::class)
        ->set('data.display_config.template_family', 'content_item');
    $homepageTemplateSelect = step10rB1SelectByStatePath($homepageSection, 'display_config.template_key');

    expect($homepageTemplateSelect)->toBeInstanceOf(Select::class)
        ->and(step10rB1SelectHasOptions($homepageTemplateSelect, [
            'saved_episode_card' => 'Template saved_episode_card',
        ], [
            'saved_podcast_card',
            'saved_contributor_card',
        ]))->toBeTrue();

    $homepageSection->set('data.display_config.template_family', 'content_group');
    $homepageTemplateSelect = step10rB1SelectByStatePath($homepageSection, 'display_config.template_key');

    expect($homepageTemplateSelect)->toBeInstanceOf(Select::class)
        ->and(step10rB1SelectHasOptions($homepageTemplateSelect, [
            'saved_podcast_card' => 'Template saved_podcast_card',
        ], [
            'saved_episode_card',
            'saved_contributor_card',
        ]))->toBeTrue();

    $homepageSection->set('data.display_config.template_family', 'contributor');
    $homepageTemplateSelect = step10rB1SelectByStatePath($homepageSection, 'display_config.template_key');

    expect($homepageTemplateSelect)->toBeInstanceOf(Select::class)
        ->and(step10rB1SelectHasOptions($homepageTemplateSelect, [
            'saved_contributor_card' => 'Template saved_contributor_card',
        ], [
            'saved_episode_card',
            'saved_podcast_card',
        ]))->toBeTrue();
});

it('exposes unsaved same session settings page card templates after safe normalization', function (): void {
    $this->actingAs(User::factory()->create());

    $settingsPage = Livewire::test(PublicContentSettingsPage::class)
        ->set('data.card_templates', [
            makeStep3CardTemplate('content_item', 'same_session_episode_card'),
            makeStep3CardTemplate('content_group', 'same_session_podcast_card'),
            [
                ...makeStep3CardTemplate('content_item', 'unsafe_label_card'),
                'label' => '<script>alert(1)</script>',
            ],
            [
                ...makeStep3CardTemplate('content_item', 'bad_key_card'),
                'key' => 'bad key',
            ],
        ]);

    $podcastItemSelect = step10rB1SelectByStatePath($settingsPage, 'podcasts_page.item_template_key');
    $podcastGroupSelect = step10rB1SelectByStatePath($settingsPage, 'podcasts_page.template_key');

    expect($podcastItemSelect)->toBeInstanceOf(Select::class)
        ->and(step10rB1SelectHasOptions($podcastItemSelect, [
            'same_session_episode_card' => 'Template same_session_episode_card',
            'unsafe_label_card' => 'unsafe_label_card',
        ], [
            'same_session_podcast_card',
            'bad key',
        ]))->toBeTrue()
        ->and($podcastItemSelect->getOptions())->not->toContain('<script>alert(1)</script>')
        ->and($podcastGroupSelect)->toBeInstanceOf(Select::class)
        ->and(step10rB1SelectHasOptions($podcastGroupSelect, [
            'same_session_podcast_card' => 'Template same_session_podcast_card',
        ], [
            'same_session_episode_card',
            'unsafe_label_card',
            'bad key',
        ]))->toBeTrue();
});

it('keeps public card rendering working when templates are empty', function (): void {
    $item = createStep3PublicItem(['title' => 'Empty Template Episode']);

    saveStep3PublicFrontConfig(['card_templates' => []]);

    $this->get('/')
        ->assertSuccessful()
        ->assertSee($item->title)
        ->assertSee('data-test="content-item-card"', false)
        ->assertSee('data-card-template-key="default_content_item"', false)
        ->assertSee('data-card-template-family="content_item"', false);
});

it('keeps public card rendering working when configured template parts are invalid', function (): void {
    $item = createStep3PublicItem(['title' => 'Invalid Template Episode']);

    saveStep3PublicFrontConfig([
        'card_templates' => [
            [
                ...makeStep3CardTemplate('content_item', 'default_content_item'),
                'parts' => [
                    [
                        'type' => 'raw_html',
                        'source' => 'content_item',
                        'attribute' => 'title',
                    ],
                    [
                        'type' => 'title',
                        'source' => 'content_item',
                        'attribute' => 'App\\Cards\\UnsafeCard::class',
                    ],
                ],
            ],
        ],
    ]);

    $this->get('/')
        ->assertSuccessful()
        ->assertSee($item->title)
        ->assertSee('data-test="content-item-card"', false)
        ->assertSee('data-card-template-key="default_content_item"', false)
        ->assertSee('data-card-template-parts=""', false);
});

it('renders homepage content item template parts in configured order and hides disabled parts safely', function (): void {
    $item = createStep3PublicItem([
        'title' => 'B2 Homepage Template Episode',
        'description_markdown' => 'B2 hidden description text.',
        'duration_seconds' => 245,
    ]);

    saveStep3PublicFrontConfig([
        'card_templates' => [
            makeStep10rB2ContentItemTemplate('b2_home_content_item', [
                [
                    'type' => 'custom_text',
                    'source' => 'custom',
                    'attribute' => 'text',
                    'text' => '<strong>B2 unsafe marker</strong>',
                    'visible' => true,
                    'order' => 5,
                    'font_size' => 'sm',
                ],
                [
                    'type' => 'custom_text',
                    'source' => 'custom',
                    'attribute' => 'text',
                    'text' => 'B2 custom marker',
                    'visible' => true,
                    'order' => 10,
                    'font_size' => 'sm',
                ],
                [
                    'type' => 'title',
                    'source' => 'content_item',
                    'attribute' => 'title',
                    'visible' => true,
                    'order' => 20,
                    'url_target' => 'self',
                ],
                [
                    'type' => 'description',
                    'source' => 'content_item',
                    'attribute' => 'description',
                    'visible' => false,
                    'order' => 30,
                ],
                [
                    'type' => 'metadata_row',
                    'source' => 'content_item',
                    'attribute' => 'duration',
                    'visible' => true,
                    'order' => 40,
                ],
            ]),
        ],
    ]);

    HomepageSection::factory()->create([
        'name' => 'B2 Homepage Template',
        'type' => HomepageSectionType::Latest,
        'source_config' => ['source_type' => 'manual_content_items'],
        'selection_config' => ['include_ids' => [$item->id]],
        'display_config' => [
            'template_family' => 'content_item',
            'template_key' => 'b2_home_content_item',
        ],
    ]);

    $this->get('/')
        ->assertSuccessful()
        ->assertSee('data-card-template-key="b2_home_content_item"', false)
        ->assertSee('data-card-renderer-parts="custom_text,title,metadata_row"', false)
        ->assertSeeInOrder([
            'B2 custom marker',
            'B2 Homepage Template Episode',
            '04:05',
        ])
        ->assertDontSee('<strong>B2 unsafe marker</strong>', false)
        ->assertDontSee('B2 unsafe marker')
        ->assertDontSee('B2 hidden description text.');
});

it('renders custom content item parts on search category and tag pages', function (): void {
    $category = Category::factory()->create(['name' => 'B2 Template Category']);
    $tag = ContentTag::findOrCreate('B2 Template Tag', 'content')->enable();
    $item = createStep3PublicItem(['title' => 'B2 Search Template Episode']);

    $item->categories()->attach($category);
    $item->attachTag($tag);

    saveStep3PublicFrontConfig([
        'card_templates' => [
            makeStep10rB2ContentItemTemplate('default_content_item', [
                [
                    'type' => 'custom_text',
                    'source' => 'custom',
                    'attribute' => 'text',
                    'text' => 'B2 search/category/tag marker',
                    'visible' => true,
                    'order' => 10,
                ],
                [
                    'type' => 'title',
                    'source' => 'content_item',
                    'attribute' => 'title',
                    'visible' => true,
                    'order' => 20,
                    'url_target' => 'self',
                ],
                [
                    'type' => 'taxonomy',
                    'source' => 'categories',
                    'attribute' => 'links',
                    'visible' => true,
                    'order' => 30,
                ],
                [
                    'type' => 'taxonomy',
                    'source' => 'tags',
                    'attribute' => 'links',
                    'visible' => true,
                    'order' => 40,
                ],
            ]),
        ],
    ]);

    foreach (['/search', "/categories/{$category->slug}", "/tags/{$tag->slug}"] as $path) {
        $this->get($path)
            ->assertSuccessful()
            ->assertSee('B2 search/category/tag marker')
            ->assertSee('B2 Search Template Episode')
            ->assertSee('data-card-part="custom_text"', false)
            ->assertSee('data-card-part="taxonomy"', false)
            ->assertSee('B2 Template Category')
            ->assertSee('B2 Template Tag');
    }
});

it('renders podcast detail item cards with the configured item template parts', function (): void {
    $group = ContentGroup::factory()->published()->create([
        'title' => 'B2 Template Podcast',
        'slug' => 'b2-template-podcast',
    ]);
    $item = createStep3PublicItem(['title' => 'B2 Podcast Template Episode'], $group);

    saveStep3PublicFrontConfig([
        'card_templates' => [
            makeStep10rB2ContentItemTemplate('b2_podcast_item_template', [
                [
                    'type' => 'custom_text',
                    'source' => 'custom',
                    'attribute' => 'text',
                    'text' => 'B2 podcast item template marker',
                    'visible' => true,
                    'order' => 10,
                ],
                [
                    'type' => 'title',
                    'source' => 'content_item',
                    'attribute' => 'title',
                    'visible' => true,
                    'order' => 20,
                    'url_target' => 'self',
                ],
            ]),
        ],
        'podcasts_page' => [
            'item_template_key' => 'b2_podcast_item_template',
        ],
    ]);

    $this->get("/podcasts/{$group->slug}")
        ->assertSuccessful()
        ->assertSee($item->title)
        ->assertSee('data-card-template-key="b2_podcast_item_template"', false)
        ->assertSee('B2 podcast item template marker')
        ->assertSeeInOrder([
            'B2 podcast item template marker',
            'B2 Podcast Template Episode',
        ]);
});

it('renders custom content group templates on podcast index and homepage group sections', function (): void {
    $group = ContentGroup::factory()->published()->create([
        'title' => 'B3 Template Podcast',
        'slug' => 'b3-template-podcast',
        'description_markdown' => 'B3 hidden group description.',
    ]);
    createStep3PublicItem(['title' => 'B3 Template Group Episode'], $group);
    $groupCountLabel = __('public.labels.public_group_items_count', [
        'count' => 1,
        'label' => $group->default_item_type_label_singular,
    ]);

    saveStep3PublicFrontConfig([
        'card_templates' => [
            makeStep10rB3ContentGroupTemplate('b3_podcast_group_template', [
                [
                    'type' => 'custom_text',
                    'source' => 'custom',
                    'attribute' => 'text',
                    'text' => '<strong>B3 unsafe group marker</strong>',
                    'visible' => true,
                    'order' => 5,
                ],
                [
                    'type' => 'custom_text',
                    'source' => 'custom',
                    'attribute' => 'text',
                    'text' => 'B3 podcast group marker',
                    'visible' => true,
                    'order' => 10,
                ],
                [
                    'type' => 'title',
                    'source' => 'content_group',
                    'attribute' => 'title',
                    'visible' => true,
                    'order' => 20,
                    'url_target' => 'self',
                ],
                [
                    'type' => 'description',
                    'source' => 'content_group',
                    'attribute' => 'description',
                    'visible' => false,
                    'order' => 30,
                ],
                [
                    'type' => 'metadata_row',
                    'source' => 'content_group',
                    'attribute' => 'item_count',
                    'visible' => true,
                    'order' => 40,
                ],
                [
                    'type' => 'action_link',
                    'source' => 'content_group',
                    'attribute' => 'url',
                    'label' => 'Open B3 podcast',
                    'visible' => true,
                    'order' => 50,
                    'url_target' => 'self',
                ],
            ]),
            makeStep10rB3ContentGroupTemplate('b3_home_group_template', [
                [
                    'type' => 'custom_text',
                    'source' => 'custom',
                    'attribute' => 'text',
                    'text' => 'B3 homepage group marker',
                    'visible' => true,
                    'order' => 10,
                ],
                [
                    'type' => 'title',
                    'source' => 'content_group',
                    'attribute' => 'title',
                    'visible' => true,
                    'order' => 20,
                    'url_target' => 'self',
                ],
            ]),
        ],
        'podcasts_page' => [
            'template_key' => 'b3_podcast_group_template',
        ],
    ]);

    HomepageSection::factory()->create([
        'name' => 'B3 Homepage Groups',
        'type' => HomepageSectionType::Latest,
        'source_config' => ['source_type' => 'content_groups'],
        'selection_config' => ['include_ids' => [$group->id]],
        'display_config' => [
            'template_family' => 'content_group',
            'template_key' => 'b3_home_group_template',
        ],
    ]);

    $this->get('/podcasts')
        ->assertSuccessful()
        ->assertSee('data-card-template-key="b3_podcast_group_template"', false)
        ->assertSee('data-card-renderer-parts="custom_text,title,metadata_row,action_link"', false)
        ->assertSeeInOrder([
            'B3 podcast group marker',
            'B3 Template Podcast',
            $groupCountLabel,
            'Open B3 podcast',
        ])
        ->assertDontSee('<strong>B3 unsafe group marker</strong>', false)
        ->assertDontSee('B3 unsafe group marker')
        ->assertDontSee('B3 hidden group description.')
        ->assertDontSee('fi-ta-table', false);

    $this->get('/')
        ->assertSuccessful()
        ->assertSee('data-card-template-key="b3_home_group_template"', false)
        ->assertSee('data-card-renderer-parts="custom_text,title"', false)
        ->assertSeeInOrder([
            'B3 homepage group marker',
            'B3 Template Podcast',
        ])
        ->assertDontSee('fi-ta-table', false);
});

it('renders custom contributor templates on contributor cards and top transcriber selectors', function (): void {
    $author = Author::factory()->create([
        'name' => 'B3 Template Contributor',
        'slug' => 'b3-template-contributor',
        'bio_markdown' => 'B3 contributor hidden bio.',
    ]);
    createStep10rB3PublicContributorItem($author, ['title' => 'B3 Contributor Public Item']);

    saveStep3PublicFrontConfig([
        'card_templates' => [
            makeStep10rB3ContributorTemplate('default_contributor', [
                [
                    'type' => 'custom_text',
                    'source' => 'custom',
                    'attribute' => 'text',
                    'text' => '<strong>B3 unsafe contributor marker</strong>',
                    'visible' => true,
                    'order' => 5,
                ],
                [
                    'type' => 'custom_text',
                    'source' => 'custom',
                    'attribute' => 'text',
                    'text' => 'B3 contributor marker',
                    'visible' => true,
                    'order' => 10,
                ],
                [
                    'type' => 'title',
                    'source' => 'author',
                    'attribute' => 'name',
                    'visible' => true,
                    'order' => 20,
                    'url_target' => 'self',
                ],
                [
                    'type' => 'metadata_row',
                    'source' => 'author',
                    'attribute' => 'transcription_count',
                    'visible' => true,
                    'order' => 30,
                ],
                [
                    'type' => 'metadata_row',
                    'source' => 'author',
                    'attribute' => 'content_item_count',
                    'visible' => true,
                    'order' => 40,
                ],
                [
                    'type' => 'description',
                    'source' => 'author',
                    'attribute' => 'bio',
                    'visible' => false,
                    'order' => 50,
                ],
                [
                    'type' => 'action_link',
                    'source' => 'author',
                    'attribute' => 'url',
                    'label' => 'Open B3 contributor',
                    'visible' => true,
                    'order' => 60,
                    'url_target' => 'self',
                ],
            ]),
            makeStep10rB3ContributorTemplate('b3_home_contributor_template', [
                [
                    'type' => 'custom_text',
                    'source' => 'custom',
                    'attribute' => 'text',
                    'text' => 'B3 homepage contributor marker',
                    'visible' => true,
                    'order' => 10,
                ],
                [
                    'type' => 'title',
                    'source' => 'author',
                    'attribute' => 'name',
                    'visible' => true,
                    'order' => 20,
                    'url_target' => 'self',
                ],
                [
                    'type' => 'action_link',
                    'source' => 'author',
                    'attribute' => 'url',
                    'label' => 'Open B3 homepage contributor',
                    'visible' => true,
                    'order' => 30,
                    'url_target' => 'self',
                ],
            ]),
        ],
        'contributors_page' => [
            'cards' => [
                'preview_show_bio' => false,
            ],
        ],
    ]);

    HomepageSection::factory()->create([
        'name' => 'B3 Homepage Contributors',
        'type' => HomepageSectionType::Latest,
        'source_config' => ['source_type' => 'contributors'],
        'selection_config' => ['include_ids' => [$author->id]],
        'display_config' => [
            'template_family' => 'contributor',
            'template_key' => 'b3_home_contributor_template',
        ],
    ]);

    $this->get('/contributors')
        ->assertSuccessful()
        ->assertSee('data-card-template-key="default_contributor"', false)
        ->assertSee('data-card-renderer-parts="custom_text,title,metadata_row"', false)
        ->assertSeeInOrder([
            'B3 contributor marker',
            'B3 Template Contributor',
            trans_choice('public.labels.public_transcriptions_count', 1, ['count' => 1]),
            trans_choice('public.labels.public_content_items_count', 1, ['count' => 1]),
        ])
        ->assertDontSee('<strong>B3 unsafe contributor marker</strong>', false)
        ->assertDontSee('B3 unsafe contributor marker')
        ->assertDontSee('Open B3 contributor')
        ->assertDontSee('B3 contributor hidden bio.')
        ->assertDontSee('fi-ta-table', false);

    $this->get('/')
        ->assertSuccessful()
        ->assertSee('data-card-template-key="b3_home_contributor_template"', false)
        ->assertSee('data-card-renderer-parts="custom_text,title,action_link"', false)
        ->assertSeeInOrder([
            'B3 homepage contributor marker',
            'B3 Template Contributor',
            'Open B3 homepage contributor',
        ])
        ->assertDontSee('fi-ta-table', false);

    Livewire::test(TopTranscribersSection::class, [
        'contributorIds' => [$author->id],
    ])
        ->assertSee('data-test="top-transcribers-selector"', false)
        ->assertSee('data-card-renderer-parts="custom_text,title,metadata_row"', false)
        ->assertSee('B3 contributor marker')
        ->assertSee('B3 Template Contributor')
        ->assertSee(trans_choice('public.labels.public_transcriptions_count', 1, ['count' => 1]))
        ->assertSee(trans_choice('public.labels.public_content_items_count', 1, ['count' => 1]))
        ->assertDontSee('Open B3 contributor')
        ->assertDontSee('B3 contributor hidden bio.')
        ->assertDontSee('fi-ta-table', false);
});

it('saves a simple card template definition through the public content settings page', function (): void {
    $this->actingAs(User::factory()->create());

    Livewire::test(PublicContentSettingsPage::class)
        ->set('data.homepage_item_limit', 9)
        ->set('data.pinned_item_limit', 4)
        ->set('data.default_public_sort', 'latest_transcription')
        ->set('data.default_result_layout', 'cards')
        ->set('data.show_latest_section', true)
        ->set('data.item_page_layout', 'standard')
        ->set('data.homepage_card_image_size', 'medium')
        ->set('data.homepage_card_density', 'comfortable')
        ->set('data.homepage_card_title_size', 'base')
        ->set('data.homepage_show_group_badge', true)
        ->set('data.homepage_show_authors', true)
        ->set('data.homepage_show_categories', true)
        ->set('data.homepage_show_tags', true)
        ->set('data.homepage_show_duration', true)
        ->set('data.homepage_show_effective_date', true)
        ->set('data.homepage_show_description', true)
        ->set('data.homepage_description_lines', 3)
        ->set('data.homepage_cards_per_page', 12)
        ->set('data.menu_config.enabled', false)
        ->set('data.display_defaults.layout', 'cards')
        ->set('data.display_defaults.density', 'comfortable')
        ->set('data.display_defaults.image_size', 'medium')
        ->set('data.display_defaults.title_size', 'base')
        ->set('data.display_defaults.page_size', 12)
        ->set('data.route_labels', [])
        ->set('data.card_templates', [
            [
                'key' => 'admin_episode_card',
                'label' => 'Admin episode card',
                'family' => 'content_item',
                'layout' => 'rows',
                'density' => 'compact',
                'image_size' => 'small',
                'title_size' => 'lg',
                'parts' => [
                    [
                        'type' => 'title',
                        'data' => [
                            'source' => 'content_item',
                            'attribute' => 'title',
                            'visible' => true,
                            'order' => 10,
                            'layout' => 'inline',
                        ],
                    ],
                ],
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    clearStep3PublicFrontSettingsCache();

    $templates = app(PublicFrontConfigReader::class)
        ->read(app(PublicContentSettings::class))
        ->group('card_templates');

    expect($templates)->toHaveCount(1)
        ->and($templates[0])->toMatchArray([
            'key' => 'admin_episode_card',
            'family' => 'content_item',
            'layout' => 'rows',
            'density' => 'compact',
            'image_size' => 'small',
            'title_size' => 'lg',
        ])
        ->and($templates[0]['parts'][0])->toMatchArray([
            'type' => 'title',
            'source' => 'content_item',
            'attribute' => 'title',
        ])
        ->and($templates[0]['parts'][0])->not->toHaveKey('data');
});

it('keeps contributor card template settings deferred until a later renderer step adds a setting', function (): void {
    $this->actingAs(User::factory()->create());

    $settingsPage = Livewire::test(PublicContentSettingsPage::class);

    expect(step10rB1SelectByStatePath($settingsPage, 'contributors_page.template_key'))->toBeNull()
        ->and(step10rB1SelectByStatePath($settingsPage, 'contributors_page.card_template_key'))->toBeNull()
        ->and(step10rB1SelectByStatePath($settingsPage, 'contributors_page.cards.template_key'))->toBeNull();
});

it('does not introduce card template settings-only models', function (): void {
    expect(class_exists('App\\Models\\CardTemplate'))->toBeFalse()
        ->and(class_exists('App\\Models\\CardTemplatePart'))->toBeFalse()
        ->and(class_exists('App\\Models\\CardFamily'))->toBeFalse()
        ->and(class_exists('App\\Models\\PublicDisplaySection'))->toBeFalse()
        ->and(class_exists('App\\Models\\PublicLooper'))->toBeFalse();
});
