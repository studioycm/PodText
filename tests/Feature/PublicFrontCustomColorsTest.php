<?php

use App\Filament\Pages\PublicContentSettings as PublicContentSettingsPage;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\Transcription;
use App\Models\User;
use App\Settings\PublicContentSettings;
use App\Support\PublicFront\Colors\PublicFrontColor;
use App\Support\PublicFront\ItemPage\PublicItemPagePodcastPalette;
use App\Support\PublicFront\ItemPage\PublicItemPageRegistry;
use App\Support\PublicFront\PublicFrontConfigReader;
use App\Support\PublicFront\PublicFrontConfigRegistry;
use App\Support\PublicFront\PublicFrontConfigValidator;
use App\Support\PublicFront\PublicFrontRenderContext;
use Filament\Facades\Filament;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\LaravelSettings\SettingsContainer;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

function clearStep10V1cPublicFrontSettingsCache(): void
{
    app()->forgetInstance(PublicContentSettings::class);
    app()->forgetInstance(PublicFrontRenderContext::class);
    app(SettingsContainer::class)->clearCache();
}

function saveStep10V1cPublicFrontSettings(array $settings): void
{
    foreach ($settings as $key => $value) {
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

    clearStep10V1cPublicFrontSettingsCache();
}

function step10V1cSelectByStatePath(mixed $component, string $statePath): ?Select
{
    $absoluteStatePath = str_starts_with($statePath, 'data.')
        ? $statePath
        : "data.{$statePath}";

    return collect($component->instance()->getSchema('form')->getFlatComponents(withActions: false, withHidden: true, withAbsoluteKeys: true))
        ->first(fn (mixed $schemaComponent): bool => $schemaComponent instanceof Select
            && $schemaComponent->getStatePath() === $absoluteStatePath);
}

function step10V1cColorPickerByStatePath(mixed $component, string $statePath): ?ColorPicker
{
    $absoluteStatePath = str_starts_with($statePath, 'data.')
        ? $statePath
        : "data.{$statePath}";

    return collect($component->instance()->getSchema('form')->getFlatComponents(withActions: false, withHidden: true, withAbsoluteKeys: true))
        ->first(fn (mixed $schemaComponent): bool => $schemaComponent instanceof ColorPicker
            && $schemaComponent->getStatePath() === $absoluteStatePath);
}

function createStep10V1cPublicItem(array $groupAttributes = [], array $itemAttributes = []): array
{
    $group = ContentGroup::factory()->published()->create([
        'title' => 'V1c Podcast',
        'slug' => 'v1c-podcast',
        ...$groupAttributes,
    ]);

    $item = ContentItem::factory()
        ->for($group)
        ->published($itemAttributes['published_at'] ?? now()->subDay())
        ->create([
            'title' => 'V1c Episode',
            'slug' => 'v1c-episode',
            ...$itemAttributes,
        ]);

    $transcription = Transcription::factory()
        ->for($item)
        ->published(now()->subHour())
        ->create([
            'title' => 'V1c Transcript',
            'transcript_markdown' => 'V1c transcript body',
        ]);

    $item->update(['featured_transcription_id' => $transcription->id]);

    return [$item->refresh(), $group->refresh(), $transcription->refresh()];
}

function putStep10V1cPaletteCover(string $path): void
{
    $image = imagecreatetruecolor(30, 30);

    imagefilledrectangle($image, 0, 0, 9, 29, imagecolorallocate($image, 37, 99, 235));
    imagefilledrectangle($image, 10, 0, 19, 29, imagecolorallocate($image, 22, 163, 74));
    imagefilledrectangle($image, 20, 0, 29, 29, imagecolorallocate($image, 220, 38, 38));

    ob_start();
    imagepng($image);
    $contents = ob_get_clean();

    imagedestroy($image);

    Storage::disk('public')->put($path, (string) $contents);
}

it('normalizes custom hex colors and rejects invalid custom color settings', function (): void {
    $result = app(PublicFrontConfigValidator::class)->validate([
        'item_page' => [
            'podcast_identity' => [
                'color' => PublicItemPageRegistry::CUSTOM_COLOR,
                'custom_color' => '#abc',
            ],
            'info_fields' => [
                [
                    'field' => 'duration',
                    'label_mode' => 'hidden',
                    'color' => PublicItemPageRegistry::CUSTOM_COLOR,
                    'custom_color' => '#12ABef',
                ],
            ],
            'badges' => [
                'info' => [
                    'color' => PublicItemPageRegistry::CUSTOM_COLOR,
                    'custom_color' => '#f0c',
                ],
            ],
        ],
    ]);

    $itemPage = $result->group('item_page');

    expect($itemPage['podcast_identity']['color'])->toBe(PublicItemPageRegistry::CUSTOM_COLOR)
        ->and($itemPage['podcast_identity']['custom_color'])->toBe('#aabbcc')
        ->and($itemPage['info_fields'][0]['custom_color'])->toBe('#12abef')
        ->and($itemPage['badges']['info']['custom_color'])->toBe('#ff00cc');

    $invalidResult = app(PublicFrontConfigValidator::class)->validate([
        'item_page' => [
            'podcast_identity' => [
                'color' => PublicItemPageRegistry::CUSTOM_COLOR,
                'custom_color' => 'not-a-color',
            ],
        ],
    ]);
    $invalidItemPage = $invalidResult->group('item_page');
    $paths = collect($invalidResult->invalidConfig())
        ->map(fn ($invalidConfig): string => $invalidConfig->path)
        ->all();

    expect($invalidItemPage['podcast_identity']['color'])->toBe('primary')
        ->and($invalidItemPage['podcast_identity']['custom_color'])->toBeNull()
        ->and($paths)->toContain('item_page.podcast_identity.custom_color');
});

it('exposes custom color options and revealable color pickers on the settings page', function (): void {
    $this->actingAs(User::factory()->create());
    ColorPicker::disableVisibilityCache();

    $settingsPage = Livewire::test(PublicContentSettingsPage::class)
        ->set('data.item_page.podcast_identity.color', PublicItemPageRegistry::CUSTOM_COLOR);

    $podcastColorSelect = step10V1cSelectByStatePath($settingsPage, 'item_page.podcast_identity.color');
    $podcastColorPicker = step10V1cColorPickerByStatePath($settingsPage, 'item_page.podcast_identity.custom_color');

    expect($podcastColorSelect)->toBeInstanceOf(Select::class)
        ->and($podcastColorSelect?->getOptions())->toHaveKey(PublicItemPageRegistry::CUSTOM_COLOR)
        ->and($podcastColorPicker)->toBeInstanceOf(ColorPicker::class)
        ->and($podcastColorPicker?->isVisible())->toBeTrue();
});

it('saves custom colors through the settings page and clears stale custom values for semantic tokens', function (): void {
    $this->actingAs(User::factory()->create());

    Livewire::test(PublicContentSettingsPage::class)
        ->set('data.item_page.podcast_identity.color', PublicItemPageRegistry::CUSTOM_COLOR)
        ->set('data.item_page.podcast_identity.custom_color', '#abc')
        ->set('data.item_page.badges.info.color', PublicItemPageRegistry::CUSTOM_COLOR)
        ->set('data.item_page.badges.info.custom_color', '#0f0')
        ->set('data.item_page.info_fields', [
            [
                'field' => 'duration',
                'label_mode' => 'hidden',
                'label_override' => null,
                'icon' => 'OutlinedClock',
                'icon_position' => 'inline_before',
                'size' => 'sm',
                'color' => PublicItemPageRegistry::CUSTOM_COLOR,
                'custom_color' => '#12ABef',
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    clearStep10V1cPublicFrontSettingsCache();

    $itemPage = app(PublicFrontConfigReader::class)
        ->read(app(PublicContentSettings::class))
        ->group('item_page');

    expect($itemPage['podcast_identity']['custom_color'])->toBe('#aabbcc')
        ->and($itemPage['badges']['info']['custom_color'])->toBe('#00ff00')
        ->and($itemPage['info_fields'][0]['custom_color'])->toBe('#12abef');

    Livewire::test(PublicContentSettingsPage::class)
        ->set('data.item_page.podcast_identity.color', 'primary')
        ->set('data.item_page.podcast_identity.custom_color', '#abcdef')
        ->call('save')
        ->assertHasNoFormErrors();

    clearStep10V1cPublicFrontSettingsCache();

    $itemPage = app(PublicFrontConfigReader::class)
        ->read(app(PublicContentSettings::class))
        ->group('item_page');

    expect($itemPage['podcast_identity']['color'])->toBe('primary')
        ->and($itemPage['podcast_identity']['custom_color'])->toBeNull();
});

it('returns theme safe podcast palette variants from deterministic cover samples', function (): void {
    if (! extension_loaded('gd')) {
        $this->markTestSkipped('GD extension is required for cover image sampling.');
    }

    Storage::fake('public');
    Cache::flush();

    $coverPath = 'content-groups/covers/v1c-palette.png';
    putStep10V1cPaletteCover($coverPath);

    $colors = app(PublicItemPagePodcastPalette::class)->colors($coverPath);

    expect($colors)->toHaveKeys(['image_1', 'image_2', 'image_3']);

    foreach ($colors as $variants) {
        expect($variants['light'])->toMatch('/^#[0-9a-f]{6}$/')
            ->and($variants['dark'])->toMatch('/^#[0-9a-f]{6}$/')
            ->and(PublicFrontColor::contrastRatio($variants['light'], PublicFrontColor::LIGHT_THEME_BACKGROUND))
            ->toBeGreaterThanOrEqual(4.5)
            ->and(PublicFrontColor::contrastRatio($variants['dark'], PublicFrontColor::DARK_THEME_BACKGROUND))
            ->toBeGreaterThanOrEqual(4.5)
            ->and(PublicFrontColor::lightness($variants['light']))
            ->toBeLessThanOrEqual(0.4)
            ->and(PublicFrontColor::lightness($variants['dark']))
            ->toBeGreaterThanOrEqual(0.65);
    }
});

it('caches podcast palette computation by cover path and mtime', function (): void {
    Storage::fake('public');
    Cache::flush();

    $coverPath = 'content-groups/covers/cache-source.png';
    Storage::disk('public')->put($coverPath, 'not-an-image');

    $palette = new class extends PublicItemPagePodcastPalette
    {
        public int $computations = 0;

        protected function computeColors(?string $coverPath): array
        {
            $this->computations++;

            return [
                'image_1' => PublicFrontColor::themeVariants('#123456'),
                'image_2' => PublicFrontColor::themeVariants('#abcdef'),
                'image_3' => PublicFrontColor::themeVariants('#654321'),
            ];
        }
    };

    expect($palette->colors($coverPath))->toBe($palette->colors($coverPath))
        ->and($palette->computations)->toBe(1);
});

it('does not compute or fetch remote cover palette paths', function (): void {
    $palette = new class extends PublicItemPagePodcastPalette
    {
        public int $computations = 0;

        protected function computeColors(?string $coverPath): array
        {
            $this->computations++;

            return [];
        }
    };

    $colors = $palette->colors('https://example.test/remote-cover.png');

    expect($palette->computations)->toBe(0)
        ->and($colors)->toHaveKeys(['image_1', 'image_2', 'image_3']);
});

it('renders custom colors as controlled public CSS variables', function (): void {
    [$item, $group] = createStep10V1cPublicItem();
    $defaults = PublicFrontConfigRegistry::defaults()['item_page'];

    saveStep10V1cPublicFrontSettings([
        'item_page' => [
            ...$defaults,
            'podcast_identity' => [
                ...$defaults['podcast_identity'],
                'mode' => 'badge',
                'color' => PublicItemPageRegistry::CUSTOM_COLOR,
                'custom_color' => '#abc',
            ],
            'info_fields' => [
                [
                    'field' => 'site_published_date',
                    'label_mode' => 'short',
                    'label_override' => null,
                    'icon' => 'OutlinedCalendar',
                    'icon_position' => 'inline_before',
                    'size' => 'sm',
                    'color' => PublicItemPageRegistry::CUSTOM_COLOR,
                    'custom_color' => '#123456',
                ],
            ],
        ],
    ]);

    $this->get("/items/{$group->slug}/{$item->slug}")
        ->assertSuccessful()
        ->assertSee('data-podcast-identity-color="custom"', false)
        ->assertSee('--podcast-identity-color: #aabbcc', false)
        ->assertSee('--podcast-identity-color-dark: #aabbcc', false)
        ->assertSee('--item-info-color: #123456', false)
        ->assertDontSee('bg-[#123456', false)
        ->assertDontSee('#abc', false);
});
