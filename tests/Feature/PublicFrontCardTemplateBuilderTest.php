<?php

use App\Filament\Pages\PublicContentSettings as PublicContentSettingsPage;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\User;
use App\Settings\PublicContentSettings;
use App\Support\PublicFront\Cards\PublicFrontCardTemplateResolver;
use App\Support\PublicFront\PublicFrontConfigReader;
use App\Support\PublicFront\PublicFrontConfigValidator;
use App\Support\PublicFront\PublicFrontRenderContext;
use Filament\Facades\Filament;
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

function createStep3PublicItem(array $itemAttributes = [], ?ContentGroup $group = null): ContentItem
{
    $group ??= ContentGroup::factory()->published()->create();

    return ContentItem::factory()
        ->for($group)
        ->published()
        ->withTranscription(['published_at' => now()->subMinute()])
        ->create($itemAttributes);
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

it('does not introduce card template settings-only models', function (): void {
    expect(class_exists('App\\Models\\CardTemplate'))->toBeFalse()
        ->and(class_exists('App\\Models\\CardTemplatePart'))->toBeFalse()
        ->and(class_exists('App\\Models\\CardFamily'))->toBeFalse()
        ->and(class_exists('App\\Models\\PublicDisplaySection'))->toBeFalse()
        ->and(class_exists('App\\Models\\PublicLooper'))->toBeFalse();
});
