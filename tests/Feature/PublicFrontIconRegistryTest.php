<?php

use App\Filament\Pages\PublicContentSettings as PublicContentSettingsPage;
use App\Models\User;
use App\Settings\PublicContentSettings;
use App\Support\PublicFront\Cards\PublicFrontCardIconResolver;
use App\Support\PublicFront\Icons\PublicFrontIconRegistry;
use App\Support\PublicFront\PublicFrontConfigReader;
use App\Support\PublicFront\PublicFrontConfigValidator;
use App\Support\PublicFront\PublicFrontRenderContext;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Support\Icons\Heroicon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\LaravelSettings\SettingsContainer;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

function clearStep10V1bPublicFrontSettingsCache(): void
{
    app()->forgetInstance(PublicContentSettings::class);
    app()->forgetInstance(PublicFrontRenderContext::class);
    app(SettingsContainer::class)->clearCache();
}

function step10V1bSelectByStatePath(mixed $component, string $statePath): ?Select
{
    $absoluteStatePath = str_starts_with($statePath, 'data.')
        ? $statePath
        : "data.{$statePath}";

    return collect($component->instance()->getSchema('form')->getFlatComponents(withActions: false, withHidden: true, withAbsoluteKeys: true))
        ->first(fn (mixed $schemaComponent): bool => $schemaComponent instanceof Select
            && $schemaComponent->getStatePath() === $absoluteStatePath);
}

it('resolves every legacy icon alias through the card icon resolver', function (): void {
    foreach (PublicFrontIconRegistry::legacyAliases() as $alias => $token) {
        $resolved = PublicFrontCardIconResolver::resolve($alias);

        if ($token === PublicFrontIconRegistry::NONE) {
            expect($resolved)->toBeNull();

            continue;
        }

        expect($resolved)
            ->toBeInstanceOf(Heroicon::class)
            ->and($resolved?->name)->toBe($token);
    }

    expect(PublicFrontCardIconResolver::resolve('document-text')?->name)
        ->toBe(PublicFrontIconRegistry::DEFAULT_CONTENT)
        ->and(PublicFrontCardIconResolver::resolve(PublicFrontIconRegistry::DEFAULT_CALENDAR)?->name)
        ->toBe(PublicFrontIconRegistry::DEFAULT_CALENDAR)
        ->and(PublicFrontCardIconResolver::resolve('heroicon-o-calendar'))->toBeNull();
});

it('normalizes icon settings to heroicon enum case names with invalid fallbacks', function (): void {
    $result = app(PublicFrontConfigValidator::class)->validate([
        'card_templates' => [
            [
                'key' => 'icon_test',
                'family' => 'content_item',
                'parts' => [
                    [
                        'type' => 'custom_text',
                        'text' => 'Calendar',
                        'icon' => 'calendar',
                        'icon_position' => 'inline_before',
                    ],
                    [
                        'type' => 'custom_text',
                        'text' => 'Unsafe',
                        'icon' => '<svg></svg>',
                        'icon_position' => 'inline_after',
                    ],
                ],
            ],
        ],
        'item_page' => [
            'podcast_identity' => [
                'icon' => 'podcast',
            ],
            'info_fields' => [
                [
                    'field' => 'categories',
                    'label_mode' => 'hidden',
                    'icon' => 'folder',
                ],
                [
                    'field' => 'duration',
                    'label_mode' => 'hidden',
                    'icon' => 'heroicon-o-clock',
                ],
            ],
            'dates' => [
                'site_published' => [
                    'icon' => 'calendar',
                ],
                'transcription_date' => [
                    'icon' => 'document',
                ],
            ],
        ],
        'contributors_page' => [
            'cards' => [
                'compact_count_icon' => 'document-text',
            ],
        ],
    ]);

    $cardTemplates = $result->group('card_templates');
    $itemPage = $result->group('item_page');
    $contributorsPage = $result->group('contributors_page');
    $paths = collect($result->invalidConfig())
        ->map(fn ($invalidConfig): string => $invalidConfig->path)
        ->all();

    expect($cardTemplates[0]['parts'][0]['icon'])->toBe(PublicFrontIconRegistry::DEFAULT_CALENDAR)
        ->and($cardTemplates[0]['parts'][1]['icon'] ?? null)->toBeNull()
        ->and($itemPage['podcast_identity']['icon'])->toBe(PublicFrontIconRegistry::DEFAULT_PODCAST)
        ->and($itemPage['info_fields'][0]['icon'])->toBe('OutlinedFolder')
        ->and($itemPage['info_fields'][1]['icon'])->toBe(PublicFrontIconRegistry::DEFAULT_CONTENT)
        ->and($itemPage['dates']['site_published']['icon'])->toBe(PublicFrontIconRegistry::DEFAULT_CALENDAR)
        ->and($itemPage['dates']['transcription_date']['icon'])->toBe(PublicFrontIconRegistry::DEFAULT_CONTENT)
        ->and($contributorsPage['cards']['compact_count_icon'])->toBe(PublicFrontIconRegistry::DEFAULT_CONTENT)
        ->and($paths)->toContain('card_templates.0.parts.1.icon')
        ->and($paths)->toContain('item_page.info_fields.1.icon');
});

it('returns lazy searchable icon picker results without legacy keys as stored values', function (): void {
    $calendarResults = PublicFrontIconRegistry::searchResults('calendar');
    $podcastResults = PublicFrontIconRegistry::searchResults('podcast');
    $blankResults = PublicFrontIconRegistry::searchResults('');

    expect($calendarResults)
        ->toHaveKey(PublicFrontIconRegistry::DEFAULT_CALENDAR)
        ->not->toHaveKey('calendar')
        ->and($calendarResults[PublicFrontIconRegistry::DEFAULT_CALENDAR])
        ->toContain('<svg')
        ->toContain(PublicFrontIconRegistry::DEFAULT_CALENDAR)
        ->and($podcastResults)->toHaveKey(PublicFrontIconRegistry::DEFAULT_PODCAST)
        ->and(count($blankResults))->toBeLessThan(25)
        ->and(PublicFrontIconRegistry::optionLabel('calendar'))->toContain(PublicFrontIconRegistry::DEFAULT_CALENDAR)
        ->and(PublicFrontIconRegistry::optionLabel('heroicon-o-calendar'))->toBeNull();
});

it('normalizes saved icon aliases through the settings page', function (): void {
    $this->actingAs(User::factory()->create());

    Livewire::test(PublicContentSettingsPage::class)
        ->set('data.item_page.podcast_identity.icon', 'podcast')
        ->set('data.item_page.info_fields', [
            [
                'field' => 'categories',
                'label_mode' => 'long',
                'label_override' => null,
                'icon' => 'folder',
                'icon_position' => 'inline_before',
                'size' => 'sm',
                'color' => 'info',
            ],
        ])
        ->set('data.item_page.dates.site_published.icon', 'calendar')
        ->set('data.contributors_page.cards.compact_count_icon', 'document-text')
        ->call('save')
        ->assertHasNoFormErrors();

    clearStep10V1bPublicFrontSettingsCache();

    $settings = app(PublicContentSettings::class);
    $config = app(PublicFrontConfigReader::class)->read($settings);
    $itemPage = $config->group('item_page');
    $contributorsPage = $config->group('contributors_page');

    expect($settings->item_page['podcast_identity']['icon'])->toBe(PublicFrontIconRegistry::DEFAULT_PODCAST)
        ->and($itemPage['info_fields'][0]['icon'])->toBe('OutlinedFolder')
        ->and($itemPage['dates']['site_published']['icon'])->toBe(PublicFrontIconRegistry::DEFAULT_CALENDAR)
        ->and($contributorsPage['cards']['compact_count_icon'])->toBe(PublicFrontIconRegistry::DEFAULT_CONTENT);
});

it('normalizes stored legacy icon aliases through the settings migration', function (): void {
    DB::table('settings')->updateOrInsert(
        [
            'group' => PublicContentSettings::group(),
            'name' => 'card_templates',
        ],
        [
            'locked' => false,
            'payload' => json_encode([
                [
                    'key' => 'legacy_icon_card',
                    'family' => 'content_item',
                    'parts' => [
                        [
                            'type' => 'part_group',
                            'icon' => 'sparkles',
                            'children' => [
                                [
                                    'type' => 'metadata',
                                    'source' => 'content_item',
                                    'attribute' => 'title',
                                    'icon' => 'arrow_right',
                                ],
                            ],
                        ],
                    ],
                ],
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ],
    );
    DB::table('settings')->updateOrInsert(
        [
            'group' => PublicContentSettings::group(),
            'name' => 'item_page',
        ],
        [
            'locked' => false,
            'payload' => json_encode([
                'podcast_identity' => [
                    'icon' => 'podcast',
                ],
                'info_fields' => [
                    [
                        'field' => 'duration',
                        'icon' => 'clock',
                    ],
                ],
                'dates' => [
                    'site_published' => ['icon' => 'calendar'],
                    'transcription_date' => ['icon' => 'document'],
                ],
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ],
    );
    DB::table('settings')->updateOrInsert(
        [
            'group' => PublicContentSettings::group(),
            'name' => 'contributors_page',
        ],
        [
            'locked' => false,
            'payload' => json_encode([
                'cards' => [
                    'compact_count_icon' => 'document-text',
                ],
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ],
    );

    $migration = include base_path('database/settings/2026_07_09_000006_normalize_public_icon_tokens.php');
    $migration->up();

    $cardTemplates = json_decode(DB::table('settings')
        ->where('group', PublicContentSettings::group())
        ->where('name', 'card_templates')
        ->value('payload'), true);
    $itemPage = json_decode(DB::table('settings')
        ->where('group', PublicContentSettings::group())
        ->where('name', 'item_page')
        ->value('payload'), true);
    $contributorsPage = json_decode(DB::table('settings')
        ->where('group', PublicContentSettings::group())
        ->where('name', 'contributors_page')
        ->value('payload'), true);

    expect($cardTemplates[0]['parts'][0]['icon'])->toBe('OutlinedSparkles')
        ->and($cardTemplates[0]['parts'][0]['children'][0]['icon'])->toBe('OutlinedArrowRight')
        ->and($itemPage['podcast_identity']['icon'])->toBe(PublicFrontIconRegistry::DEFAULT_PODCAST)
        ->and($itemPage['info_fields'][0]['icon'])->toBe('OutlinedClock')
        ->and($itemPage['dates']['site_published']['icon'])->toBe(PublicFrontIconRegistry::DEFAULT_CALENDAR)
        ->and($itemPage['dates']['transcription_date']['icon'])->toBe(PublicFrontIconRegistry::DEFAULT_CONTENT)
        ->and($contributorsPage['cards']['compact_count_icon'])->toBe(PublicFrontIconRegistry::DEFAULT_CONTENT);
});

it('uses the lazy shared icon picker without preloading the full heroicon enum payload', function (): void {
    $this->actingAs(User::factory()->create());

    $settingsPage = Livewire::test(PublicContentSettingsPage::class);
    $podcastIconSelect = step10V1bSelectByStatePath($settingsPage, 'item_page.podcast_identity.icon');
    $partIconSelect = step10V1bSelectByStatePath($settingsPage, 'card_templates.0.icon');

    expect($podcastIconSelect)->toBeInstanceOf(Select::class)
        ->and($podcastIconSelect?->getOptions())->toBe([])
        ->and($settingsPage->html())->not->toContain('OutlinedAcademicCap')
        ->and($settingsPage->html())->not->toContain('OutlinedArchiveBoxXMark')
        ->and(substr_count($settingsPage->html(), 'Outlined'))->toBeLessThan(80);

    expect($partIconSelect === null || $partIconSelect->getOptions() === [])->toBeTrue();
});
