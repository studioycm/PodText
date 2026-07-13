<?php

use App\Settings\PublicContentSettings;
use App\Support\PublicFront\Colors\PublicFrontColor;
use App\Support\PublicFront\ItemPage\PublicItemPagePodcastPalette;
use App\Support\PublicFront\PublicFrontConfigCache;
use App\Support\PublicFront\PublicFrontConfigReader;
use App\Support\PublicFront\PublicFrontConfigRegistry;
use App\Support\PublicFront\PublicFrontConfigResult;
use App\Support\PublicFront\PublicFrontConfigValidator;
use App\Support\PublicFront\PublicFrontRenderContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Spatie\LaravelSettings\SettingsContainer;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['settings.cache.enabled' => true]);

    Cache::flush();
    clearStep10P1SettingsCache();
});

function clearStep10P1SettingsCache(): void
{
    app()->forgetInstance(PublicContentSettings::class);
    app()->forgetInstance(PublicFrontRenderContext::class);
    app(PublicFrontConfigCache::class)->forget();
    app(SettingsContainer::class)->clearCache();
}

function step10P1CountingValidator(): PublicFrontConfigValidator
{
    return new class extends PublicFrontConfigValidator
    {
        public int $calls = 0;

        public function validate(array $config): PublicFrontConfigResult
        {
            $this->calls++;

            return parent::validate($config);
        }
    };
}

it('caches validated public front config and skips revalidation on warm reads', function (): void {
    $validator = step10P1CountingValidator();
    $cache = app(PublicFrontConfigCache::class);
    $reader = new PublicFrontConfigReader($validator, $cache);

    $first = $reader->read();
    $second = $reader->read();

    expect($second->config())->toBe($first->config())
        ->and($validator->calls)->toBe(1)
        ->and(Cache::get($cache->key()))->toHaveKeys(['version', 'key', 'watermark', 'config', 'invalid_config']);
});

it('makes saved public content settings visible after cache invalidation', function (): void {
    $reader = app(PublicFrontConfigReader::class);

    expect($reader->read()->group('podcasts_page')['title'])->not->toBe('P1 cached podcasts');

    $settings = app(PublicContentSettings::class);
    $podcastsPage = $settings->podcasts_page;
    $podcastsPage['title'] = 'P1 cached podcasts';
    $settings->podcasts_page = $podcastsPage;
    $settings->save();

    app()->forgetInstance(PublicContentSettings::class);
    app(SettingsContainer::class)->clearCache();

    expect(app(PublicFrontConfigReader::class)->read()->group('podcasts_page')['title'])
        ->toBe('P1 cached podcasts');
});

it('invalidates the spatie settings cache when public content settings are saved', function (): void {
    $settings = app(PublicContentSettings::class);
    $podcastsPage = $settings->podcasts_page;
    $podcastsPage['title'] = 'Spatie cache before save';
    $settings->podcasts_page = $podcastsPage;
    $settings->save();

    app()->forgetInstance(PublicContentSettings::class);

    expect(app(PublicContentSettings::class)->podcasts_page['title'])->toBe('Spatie cache before save');

    $settings = app(PublicContentSettings::class);
    $podcastsPage = $settings->podcasts_page;
    $podcastsPage['title'] = 'Spatie cache after save';
    $settings->podcasts_page = $podcastsPage;
    $settings->save();

    app()->forgetInstance(PublicContentSettings::class);

    expect(app(PublicContentSettings::class)->podcasts_page['title'])->toBe('Spatie cache after save');
});

it('rotates the cache key when a settings migration watermark changes', function (): void {
    $validator = step10P1CountingValidator();
    $cache = app(PublicFrontConfigCache::class);
    $reader = new PublicFrontConfigReader($validator, $cache);
    $initialKey = $cache->key();

    $reader->read();
    $reader->read();

    expect($validator->calls)->toBe(1);

    $temporaryMigration = database_path('settings/9999_12_31_235959_public_front_config_cache_test.php');

    try {
        file_put_contents($temporaryMigration, "<?php\n\nreturn new class {};\n");

        expect($cache->key())->not->toBe($initialKey);

        $reader->read();

        expect($validator->calls)->toBe(2);
    } finally {
        if (is_file($temporaryMigration)) {
            unlink($temporaryMigration);
        }
    }
});

it('falls back to fresh validation and rewrites corrupted cache payloads', function (): void {
    $cache = app(PublicFrontConfigCache::class);
    $validator = step10P1CountingValidator();
    $reader = new PublicFrontConfigReader($validator, $cache);

    Cache::forever($cache->key(), [
        'version' => 1,
        'key' => PublicFrontConfigCache::CONFIG_KEY,
        'config' => 'corrupted',
        'invalid_config' => [],
    ]);

    $result = $reader->read();
    $payload = Cache::get($cache->key());

    expect($result->config())->toHaveKeys(PublicFrontConfigRegistry::settingsKeys())
        ->and($validator->calls)->toBe(1)
        ->and($payload)->toBeArray()
        ->and($payload['config'])->toBeArray()
        ->and($payload['invalid_config'])->toBeArray();
});

it('keeps podcast palette cache entries content-addressed through the shared key helper', function (): void {
    Storage::fake('public');

    $coverPath = 'content-groups/covers/p1-palette-cache-source.png';
    Storage::disk('public')->put($coverPath, 'not-an-image');

    $mtime = filemtime(Storage::disk('public')->path($coverPath));
    $cacheKey = app(PublicFrontConfigCache::class)->podcastPaletteKey($coverPath, (int) $mtime);
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
        ->and($palette->computations)->toBe(1)
        ->and(Cache::has($cacheKey))->toBeTrue();
});
