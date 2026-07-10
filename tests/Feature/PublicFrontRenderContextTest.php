<?php

use App\Settings\PublicContentSettings;
use App\Support\PublicFront\About\PublicAboutPageRenderer;
use App\Support\PublicFront\Cards\PublicFrontCardTemplateResolver;
use App\Support\PublicFront\Menu\PublicMenuConfigReader;
use App\Support\PublicFront\PublicFrontConfigReader;
use App\Support\PublicFront\PublicFrontConfigRegistry;
use App\Support\PublicFront\PublicFrontConfigResult;
use App\Support\PublicFront\PublicFrontRenderContext;
use App\Support\PublicFront\PublicFrontRenderContextFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\LaravelSettings\SettingsContainer;

uses(RefreshDatabase::class);

function clearStep10rA1SettingsCache(): void
{
    app()->forgetInstance(PublicContentSettings::class);
    app()->forgetInstance(PublicFrontRenderContext::class);
    app(SettingsContainer::class)->clearCache();
}

it('exposes normalized public front settings groups', function (): void {
    $context = app(PublicFrontRenderContext::class);

    expect($context->config())->toHaveKeys(PublicFrontConfigRegistry::settingsKeys())
        ->and($context->cardTemplates())->toBeArray()
        ->and($context->displayDefaults())->toMatchArray([
            'layout' => 'cards',
            'density' => 'comfortable',
            'page_size' => 12,
        ])
        ->and($context->itemPage())->toHaveKey('dates')
        ->and($context->menu())->toHaveKey('items')
        ->and($context->aboutPage())->toHaveKey('blocks')
        ->and($context->publicForms())->toHaveKey('definitions')
        ->and($context->routeLabels())->toBeArray()
        ->and($context->podcastsPage())->toHaveKey('group_page')
        ->and($context->contributorsPage())->toHaveKey('top_transcribers')
        ->and($context->maintenance())->toMatchArray([
            'enabled' => false,
            'retry_after_hours' => 24,
        ])
        ->and($context->footer())->toBe([]);
});

it('resolves the public front render context once per container lifecycle', function (): void {
    $reader = new class extends PublicFrontConfigReader
    {
        public int $reads = 0;

        public function read(?PublicContentSettings $settings = null): PublicFrontConfigResult
        {
            $this->reads++;

            return parent::read($settings);
        }
    };

    app()->instance(PublicFrontConfigReader::class, $reader);
    app()->forgetInstance(PublicFrontRenderContext::class);

    $first = app(PublicFrontRenderContext::class);
    $second = app(PublicFrontRenderContext::class);

    expect($first)->toBe($second)
        ->and($reader->reads)->toBe(1);
});

it('shares one normalized context across public front support consumers', function (): void {
    $reader = new class extends PublicFrontConfigReader
    {
        public int $reads = 0;

        public function read(?PublicContentSettings $settings = null): PublicFrontConfigResult
        {
            $this->reads++;

            return parent::read($settings);
        }
    };

    $settings = app(PublicContentSettings::class);
    $settings->public_forms = [
        'definitions' => [
            [
                'key' => 'request_transcription',
                'enabled' => true,
                'name' => 'Request transcription',
                'display_mode_default' => 'modal',
                'fields' => [
                    [
                        'key' => 'name',
                        'type' => 'text',
                        'label' => 'Name',
                        'required' => true,
                    ],
                ],
                'settings' => [
                    'rate_limit_attempts' => 5,
                    'rate_limit_decay_seconds' => 600,
                ],
            ],
        ],
    ];
    $settings->save();

    app()->instance(PublicFrontConfigReader::class, $reader);
    app()->forgetInstance(PublicFrontRenderContext::class);

    app(PublicFrontCardTemplateResolver::class)->resolve('content_item');
    app(PublicMenuConfigReader::class)->read();

    expect(app(PublicAboutPageRenderer::class)->hasEnabledForm('request_transcription'))->toBeTrue()
        ->and($reader->reads)->toBe(1);
});

it('keeps invalid config fallback behavior available through the context', function (): void {
    $result = app(PublicFrontConfigReader::class)->fromArray([
        'menu_config' => [
            'items_alignment' => 'not-valid',
        ],
    ]);

    $context = new PublicFrontRenderContext($result);

    expect($context->hasInvalidConfig())->toBeTrue()
        ->and($context->menu()['items_alignment'])->toBe(
            PublicFrontConfigRegistry::defaults()['menu_config']['items_alignment'],
        )
        ->and(collect($context->invalidConfig())->pluck('path'))->toContain('menu_config.items_alignment');
});

it('shows saved settings in a refreshed render context', function (): void {
    $settings = app(PublicContentSettings::class);
    $podcastsPage = $settings->podcasts_page;
    $podcastsPage['title'] = 'Step 10R A1 Podcasts';
    $settings->podcasts_page = $podcastsPage;
    $settings->save();

    clearStep10rA1SettingsCache();

    expect(app(PublicFrontRenderContext::class)->podcastsPage()['title'])->toBe('Step 10R A1 Podcasts');
});

it('forgets the scoped render context when public content settings are saved', function (): void {
    $first = app(PublicFrontRenderContext::class);

    $settings = app(PublicContentSettings::class);
    $podcastsPage = $settings->podcasts_page;
    $podcastsPage['title'] = 'Step 10R A2 Podcasts';
    $settings->podcasts_page = $podcastsPage;
    $settings->save();

    $second = app(PublicFrontRenderContext::class);

    expect(spl_object_id($second))->not->toBe(spl_object_id($first))
        ->and($second->podcastsPage()['title'])->toBe('Step 10R A2 Podcasts');
});

it('exposes legacy card option settings through the render context', function (): void {
    $settings = app(PublicContentSettings::class);
    $settings->default_public_sort = 'title_asc';
    $settings->homepage_card_image_size = 'large';
    $settings->homepage_card_density = 'compact';
    $settings->homepage_card_title_size = 'lg';
    $settings->homepage_cards_per_page = 24;
    $settings->save();

    clearStep10rA1SettingsCache();

    $context = app(PublicFrontRenderContext::class);
    $cardOptions = $context->cardOptions();

    expect($context->setting('default_public_sort'))->toBe('title_asc')
        ->and($cardOptions->imageSize)->toBe('large')
        ->and($cardOptions->density)->toBe('compact')
        ->and($cardOptions->titleSize)->toBe('lg')
        ->and($cardOptions->cardsPerPage)->toBe(24)
        ->and($context->cardOptions())->toBe($cardOptions);
});

it('passes context-backed contributor page settings into the public page view', function (): void {
    $settings = app(PublicContentSettings::class);
    $contributorsPage = $settings->contributors_page;
    $contributorsPage['title'] = 'Step 10R A2 Contributors';
    $settings->contributors_page = $contributorsPage;
    $settings->save();

    $this->get('/contributors')
        ->assertOk()
        ->assertSee('Step 10R A2 Contributors');
});

it('can build a context from an explicit settings instance', function (): void {
    $settings = app(PublicContentSettings::class);
    $settings->display_defaults = [
        ...$settings->display_defaults,
        'layout' => 'rows',
    ];
    $settings->save();

    clearStep10rA1SettingsCache();

    $context = app(PublicFrontRenderContextFactory::class)
        ->make(app(PublicContentSettings::class));

    expect($context->displayDefaults()['layout'])->toBe('rows');
});
