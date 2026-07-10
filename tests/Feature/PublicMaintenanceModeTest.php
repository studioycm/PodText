<?php

use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\User;
use App\Settings\PublicContentSettings;
use App\Support\PublicFront\PublicFrontConfigCache;
use App\Support\PublicFront\PublicFrontConfigReader;
use App\Support\PublicFront\PublicFrontConfigRegistry;
use App\Support\PublicFront\PublicFrontRenderContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\LaravelSettings\SettingsContainer;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['settings.cache.enabled' => true]);

    Cache::flush();
    step10rMp1ForgetPublicFrontState();
});

function step10rMp1ForgetPublicFrontState(): void
{
    app()->forgetInstance(PublicContentSettings::class);
    app()->forgetInstance(PublicFrontRenderContext::class);
    app(PublicFrontConfigCache::class)->forget();
    app(SettingsContainer::class)->clearCache();
}

/**
 * @param  array<string, mixed>  $overrides
 */
function step10rMp1SaveMaintenance(array $overrides): void
{
    $settings = app(PublicContentSettings::class);
    $settings->maintenance = [
        ...PublicFrontConfigRegistry::defaults()['maintenance'],
        ...$overrides,
    ];
    $settings->save();
}

function step10rMp1PublicContent(): array
{
    $group = ContentGroup::factory()->published()->create([
        'slug' => 'mp1-podcast',
        'title' => 'MP1 Podcast',
    ]);
    $item = ContentItem::factory()
        ->for($group)
        ->published()
        ->withTranscription()
        ->create([
            'slug' => 'mp1-episode',
            'title' => 'MP1 Public Episode',
        ]);

    return [$group, $item];
}

it('serves public urls as maintenance responses with retry-after when enabled', function (): void {
    [$group, $item] = step10rMp1PublicContent();

    step10rMp1SaveMaintenance([
        'enabled' => true,
        'title' => 'אתר בהכנה',
        'rich_html' => '<p data-maintenance-marker="mp1">MP1 maintenance marker</p>',
        'retry_after_hours' => 6,
    ]);

    foreach ([
        '/',
        '/search',
        '/podcasts',
        "/podcasts/{$group->slug}",
        "/items/{$group->slug}/{$item->slug}",
    ] as $uri) {
        $this->get($uri)
            ->assertStatus(503)
            ->assertHeader('Retry-After', '21600')
            ->assertSee('lang="he"', false)
            ->assertSee('dir="rtl"', false)
            ->assertSee('data-maintenance-marker="mp1"', false)
            ->assertSee('MP1 maintenance marker')
            ->assertDontSee($item->title);
    }
});

it('lets admin users bypass maintenance while admin routes remain reachable for guests', function (): void {
    [$group, $item] = step10rMp1PublicContent();

    step10rMp1SaveMaintenance([
        'enabled' => true,
        'rich_html' => '<p data-maintenance-marker="mp1">Hidden from admins</p>',
    ]);

    $this->get('/admin')
        ->assertRedirect('/admin/login');

    $this->get('/admin/login')
        ->assertOk()
        ->assertDontSee('data-maintenance-marker="mp1"', false);

    $this->actingAs(User::factory()->create())
        ->get("/items/{$group->slug}/{$item->slug}")
        ->assertOk()
        ->assertSee($item->title)
        ->assertDontSee('data-maintenance-marker="mp1"', false);
});

it('leaves public routes normal when maintenance is disabled', function (): void {
    [, $item] = step10rMp1PublicContent();

    step10rMp1SaveMaintenance([
        'enabled' => false,
        'rich_html' => '<p data-maintenance-marker="mp1">Disabled marker</p>',
    ]);

    foreach (['/', '/search', '/podcasts'] as $uri) {
        $this->get($uri)
            ->assertOk()
            ->assertDontSee('data-maintenance-marker="mp1"', false);
    }

    $this->get('/')
        ->assertSee($item->title);
});

it('renders raw html override verbatim instead of the maintenance shell', function (): void {
    $rawHtml = '<!doctype html><html><body><main data-raw-maintenance="mp1"><script>window.mp1 = true;</script>Raw override</main></body></html>';

    step10rMp1SaveMaintenance([
        'enabled' => true,
        'title' => 'Ignored title',
        'rich_html' => '<p data-maintenance-marker="mp1">Ignored rich content</p>',
        'raw_html_override' => $rawHtml,
    ]);

    $this->get('/search')
        ->assertStatus(503)
        ->assertSee('<!doctype html>', false)
        ->assertSee('data-raw-maintenance="mp1"', false)
        ->assertSee('<script>window.mp1 = true;</script>', false)
        ->assertDontSee('data-maintenance-content', false)
        ->assertDontSee('Ignored rich content');
});

it('falls back to translated maintenance content when no content is configured', function (): void {
    step10rMp1SaveMaintenance(['enabled' => true]);

    $this->get('/')
        ->assertStatus(503)
        ->assertSee(__('public.maintenance.title'))
        ->assertSee(__('public.maintenance.body'));
});

it('keeps trusted maintenance html byte-identical during validation', function (): void {
    $title = "  <span>Title</span>\n ";
    $richHtml = '<p data-x="1">שלום <script>alert("rich")</script></p>';
    $rawHtml = "<!doctype html>\n<html><body><script>alert('raw')</script></body></html>";

    $maintenance = app(PublicFrontConfigReader::class)->fromArray([
        'maintenance' => [
            'enabled' => true,
            'title' => $title,
            'rich_html' => $richHtml,
            'raw_html_override' => $rawHtml,
            'retry_after_hours' => 48,
        ],
    ])->group('maintenance');

    expect($maintenance['title'])->toBe($title)
        ->and($maintenance['rich_html'])->toBe($richHtml)
        ->and($maintenance['raw_html_override'])->toBe($rawHtml)
        ->and($maintenance['retry_after_hours'])->toBe(48);
});

it('invalidates cached config immediately when the toggle is saved', function (): void {
    step10rMp1SaveMaintenance(['enabled' => false]);

    expect(app(PublicFrontConfigReader::class)->group('maintenance')['enabled'])->toBeFalse();

    step10rMp1SaveMaintenance([
        'enabled' => true,
        'rich_html' => '<p data-maintenance-marker="mp1-cache">Fresh maintenance</p>',
    ]);

    $this->get('/')
        ->assertStatus(503)
        ->assertSee('data-maintenance-marker="mp1-cache"', false);
});
