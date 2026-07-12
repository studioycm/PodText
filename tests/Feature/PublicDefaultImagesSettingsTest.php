<?php

use App\Filament\Pages\PublicContentSettings as PublicContentSettingsPage;
use App\Models\Author;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\Transcription;
use App\Models\User;
use App\Settings\PublicContentSettings;
use App\Support\PublicContent\PublicTranscriptionAggregates;
use App\Support\PublicContent\PublicTranscriptionPolicy;
use App\Support\PublicContent\PublicTranscriptionSelector;
use App\Support\PublicFront\PublicFrontConfigRegistry;
use App\Support\PublicFront\PublicFrontConfigValidator;
use App\Support\PublicFront\PublicFrontRenderContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\LaravelSettings\SettingsContainer;

uses(RefreshDatabase::class);

function clearStep10V1aPublicFrontSettingsCache(): void
{
    app()->forgetInstance(PublicContentSettings::class);
    app()->forgetInstance(PublicFrontRenderContext::class);
    app()->forgetInstance(PublicTranscriptionPolicy::class);
    app()->forgetInstance(PublicTranscriptionSelector::class);
    app()->forgetInstance(PublicTranscriptionAggregates::class);
    app(SettingsContainer::class)->clearCache();
}

function saveStep10V1aPublicFrontSettings(array $settings): void
{
    foreach ($settings as $name => $payload) {
        DB::table('settings')->updateOrInsert(
            [
                'group' => PublicContentSettings::group(),
                'name' => $name,
            ],
            [
                'locked' => false,
                'payload' => json_encode($payload),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    clearStep10V1aPublicFrontSettingsCache();
}

/**
 * @return array{group: ContentGroup, item: ContentItem, author: Author, transcription: Transcription}
 */
function createStep10V1aPublicItem(
    string $title,
    array $groupAttributes = [],
    array $itemAttributes = [],
    ?Author $author = null,
): array {
    $group = ContentGroup::factory()->published()->create([
        'title' => "{$title} Podcast",
        'slug' => str($title)->slug()->append('-podcast')->toString(),
        ...$groupAttributes,
    ]);
    $item = ContentItem::factory()
        ->for($group)
        ->published()
        ->create([
            'title' => "{$title} Episode",
            'slug' => str($title)->slug()->append('-episode')->toString(),
            ...$itemAttributes,
        ]);
    $author ??= Author::factory()->create([
        'name' => "{$title} Contributor",
        'slug' => str($title)->slug()->append('-contributor')->toString(),
    ]);
    $transcription = Transcription::factory()
        ->for($item)
        ->forAuthor($author)
        ->published()
        ->create([
            'title' => "{$title} Transcript",
            'transcript_markdown' => "Transcript for {$title}.",
        ]);

    $item->update(['featured_transcription_id' => $transcription->id]);

    return [
        'group' => $group->refresh(),
        'item' => $item->refresh(),
        'author' => $author->refresh(),
        'transcription' => $transcription->refresh(),
    ];
}

function step10V1aDefaultImages(array $overrides = []): array
{
    return array_replace_recursive(
        PublicFrontConfigRegistry::defaults()['default_images'],
        $overrides,
    );
}

/**
 * @return array<string, mixed>
 */
function step10V1aContributorImageTemplate(): array
{
    return [
        'key' => 'default_contributor',
        'label' => 'V1A contributor image card',
        'family' => 'contributor',
        'layout' => 'cards',
        'density' => 'comfortable',
        'image_size' => 'medium',
        'title_size' => 'base',
        'parts' => [
            ['type' => 'image', 'source' => 'author', 'attribute' => 'name', 'visible' => true, 'order' => 10],
            ['type' => 'title', 'source' => 'author', 'attribute' => 'name', 'visible' => true, 'order' => 20],
            ['type' => 'metadata_row', 'source' => 'author', 'attribute' => 'transcription_count', 'visible' => true, 'order' => 30],
        ],
    ];
}

it('normalizes default image settings and backfills the settings row', function (): void {
    $result = app(PublicFrontConfigValidator::class)->validate([
        'default_images' => [
            'content_item' => [
                'mode' => 'custom',
                'path' => 'default-images/item.webp',
                'class' => 'rounded-full',
            ],
            'content_group' => [
                'mode' => 'unsafe',
                'path' => '../group.jpg',
            ],
            'contributor' => 'bad-value',
            'global' => [
                'mode' => 'none',
                'path' => 'header/logo.svg',
            ],
            'unexpected' => [],
        ],
    ]);

    $defaultImages = $result->group('default_images');
    $paths = collect($result->invalidConfig())
        ->map(fn ($invalidConfig): string => $invalidConfig->path)
        ->all();

    expect($defaultImages['content_item'])->toBe([
        'mode' => 'custom',
        'path' => 'default-images/item.webp',
    ])->and($defaultImages['content_group'])->toBe([
        'mode' => 'inherit',
        'path' => null,
    ])->and($defaultImages['contributor'])->toBe([
        'mode' => 'inherit',
        'path' => null,
    ])->and($defaultImages['global'])->toBe([
        'mode' => 'none',
        'path' => null,
    ])->and($paths)->toContain(
        'default_images.content_item.class',
        'default_images.content_group.mode',
        'default_images.content_group.path',
        'default_images.contributor',
        'default_images.global.path',
        'default_images.unexpected',
    );

    DB::table('settings')
        ->where('group', PublicContentSettings::group())
        ->where('name', 'default_images')
        ->delete();

    $migration = include base_path('database/settings/2026_07_09_000005_add_public_default_image_settings.php');
    $migration->up();

    $payload = json_decode(
        DB::table('settings')
            ->where('group', PublicContentSettings::group())
            ->where('name', 'default_images')
            ->value('payload'),
        true,
    );

    expect($payload)->toMatchArray(PublicFrontConfigRegistry::defaults()['default_images']);
});

it('saves no-image mode through the public settings page', function (): void {
    $this->actingAs(User::factory()->create());

    Livewire::test(PublicContentSettingsPage::class)
        ->set('data.default_images.content_item.mode', 'none')
        ->call('save')
        ->assertHasNoFormErrors();

    clearStep10V1aPublicFrontSettingsCache();

    expect(app(PublicContentSettings::class)->default_images['content_item']['mode'])->toBe('none');
});

it('renders content item custom inherit and none fallbacks on cards and item pages', function (): void {
    $custom = createStep10V1aPublicItem('V1A Item Custom');
    saveStep10V1aPublicFrontSettings([
        'default_images' => step10V1aDefaultImages([
            'content_item' => ['mode' => 'custom', 'path' => 'default-images/item-custom.jpg'],
        ]),
    ]);

    $this->get('/search?q=V1A%20Item%20Custom')
        ->assertSuccessful()
        ->assertSee('/storage/default-images/item-custom.jpg', false)
        ->assertSee('data-card-image-source="content_item_default"', false);
    $this->get("/items/{$custom['group']->slug}/{$custom['item']->slug}")
        ->assertSuccessful()
        ->assertSee('/storage/default-images/item-custom.jpg', false)
        ->assertSee('data-item-page-image-source="content_item_default"', false);

    $global = createStep10V1aPublicItem('V1A Item Global');
    saveStep10V1aPublicFrontSettings([
        'default_images' => step10V1aDefaultImages([
            'global' => ['mode' => 'custom', 'path' => 'default-images/global.jpg'],
            'content_item' => ['mode' => 'inherit', 'path' => null],
        ]),
    ]);

    $this->get('/search?q=V1A%20Item%20Global')
        ->assertSuccessful()
        ->assertSee('/storage/default-images/global.jpg', false)
        ->assertSee('data-card-image-source="global_default"', false);
    $this->get("/items/{$global['group']->slug}/{$global['item']->slug}")
        ->assertSuccessful()
        ->assertSee('/storage/default-images/global.jpg', false)
        ->assertSee('data-item-page-image-source="global_default"', false);

    $none = createStep10V1aPublicItem('V1A Item None', [
        'cover_path' => 'content-groups/covers/should-not-render.jpg',
    ]);
    saveStep10V1aPublicFrontSettings([
        'default_images' => step10V1aDefaultImages([
            'global' => ['mode' => 'custom', 'path' => 'default-images/global-hidden.jpg'],
            'content_item' => ['mode' => 'none', 'path' => null],
        ]),
    ]);

    $this->get('/search?q=V1A%20Item%20None')
        ->assertSuccessful()
        ->assertSee('data-card-image-source="fallback"', false)
        ->assertDontSee('content-groups/covers/should-not-render.jpg', false)
        ->assertDontSee('default-images/global-hidden.jpg', false);
    $this->get("/items/{$none['group']->slug}/{$none['item']->slug}")
        ->assertSuccessful()
        ->assertDontSee('data-test="item-page-image"', false)
        ->assertDontSee('content-groups/covers/should-not-render.jpg', false)
        ->assertDontSee('default-images/global-hidden.jpg', false);
});

it('keeps local item images external thumbnails and podcast covers ahead of configured item fallbacks', function (): void {
    $local = createStep10V1aPublicItem('V1A Item Local', [
        'cover_path' => 'content-groups/covers/ignored-local-cover.jpg',
    ], [
        'external_thumbnail_url' => 'https://cdn.example.test/v1a-local-external.jpg',
        'image_path' => 'content-items/images/v1a-local.jpg',
    ]);
    $external = createStep10V1aPublicItem('V1A Item Explicit', [
        'cover_path' => 'content-groups/covers/ignored-cover.jpg',
    ], [
        'external_thumbnail_url' => 'https://cdn.example.test/v1a-explicit.jpg',
    ]);
    $cover = createStep10V1aPublicItem('V1A Item Cover', [
        'cover_path' => 'content-groups/covers/preferred-cover.jpg',
    ]);

    saveStep10V1aPublicFrontSettings([
        'default_images' => step10V1aDefaultImages([
            'content_item' => ['mode' => 'custom', 'path' => 'default-images/item-ignored.jpg'],
        ]),
    ]);

    $this->get('/search?q=V1A%20Item%20Local')
        ->assertSuccessful()
        ->assertSee('/storage/content-items/images/v1a-local.jpg', false)
        ->assertSee('data-card-image-source="item"', false)
        ->assertDontSee('v1a-local-external.jpg', false)
        ->assertDontSee('default-images/item-ignored.jpg', false);
    $this->get("/items/{$local['group']->slug}/{$local['item']->slug}")
        ->assertSuccessful()
        ->assertSee('/storage/content-items/images/v1a-local.jpg', false)
        ->assertSee('data-item-page-image-source="item"', false)
        ->assertDontSee('v1a-local-external.jpg', false);

    $this->get('/search?q=V1A%20Item%20Explicit')
        ->assertSuccessful()
        ->assertSee('https://cdn.example.test/v1a-explicit.jpg', false)
        ->assertSee('data-card-image-source="item_external"', false)
        ->assertDontSee('default-images/item-ignored.jpg', false);
    $this->get("/items/{$external['group']->slug}/{$external['item']->slug}")
        ->assertSuccessful()
        ->assertSee('https://cdn.example.test/v1a-explicit.jpg', false)
        ->assertSee('data-item-page-image-source="item_external"', false);

    $this->get('/search?q=V1A%20Item%20Cover')
        ->assertSuccessful()
        ->assertSee('/storage/content-groups/covers/preferred-cover.jpg', false)
        ->assertSee('data-card-image-source="group"', false)
        ->assertDontSee('default-images/item-ignored.jpg', false);
    $this->get("/items/{$cover['group']->slug}/{$cover['item']->slug}")
        ->assertSuccessful()
        ->assertSee('/storage/content-groups/covers/preferred-cover.jpg', false)
        ->assertSee('data-item-page-image-source="group"', false);
});

it('renders content group custom inherit and none fallbacks on cards and detail pages', function (): void {
    $custom = createStep10V1aPublicItem('V1A Group Custom');
    saveStep10V1aPublicFrontSettings([
        'default_images' => step10V1aDefaultImages([
            'content_group' => ['mode' => 'custom', 'path' => 'default-images/group-custom.jpg'],
        ]),
    ]);

    $this->get('/podcasts')
        ->assertSuccessful()
        ->assertSee('/storage/default-images/group-custom.jpg', false)
        ->assertSee('data-card-image-source="content_group_default"', false);
    $this->get("/podcasts/{$custom['group']->slug}")
        ->assertSuccessful()
        ->assertSee('/storage/default-images/group-custom.jpg', false)
        ->assertSee('data-content-group-image-source="content_group_default"', false);

    $global = createStep10V1aPublicItem('V1A Group Global');
    saveStep10V1aPublicFrontSettings([
        'default_images' => step10V1aDefaultImages([
            'global' => ['mode' => 'custom', 'path' => 'default-images/group-global.jpg'],
            'content_group' => ['mode' => 'inherit', 'path' => null],
        ]),
    ]);

    $this->get('/podcasts')
        ->assertSuccessful()
        ->assertSee('/storage/default-images/group-global.jpg', false)
        ->assertSee('data-card-image-source="global_default"', false);
    $this->get("/podcasts/{$global['group']->slug}")
        ->assertSuccessful()
        ->assertSee('/storage/default-images/group-global.jpg', false)
        ->assertSee('data-content-group-image-source="global_default"', false);

    $none = createStep10V1aPublicItem('V1A Group None');
    saveStep10V1aPublicFrontSettings([
        'default_images' => step10V1aDefaultImages([
            'global' => ['mode' => 'custom', 'path' => 'default-images/group-hidden.jpg'],
            'content_group' => ['mode' => 'none', 'path' => null],
            'content_item' => ['mode' => 'none', 'path' => null],
        ]),
    ]);

    $this->get('/podcasts')
        ->assertSuccessful()
        ->assertSee('data-test="content-group-fallback"', false)
        ->assertDontSee('default-images/group-hidden.jpg', false);
    $this->get("/podcasts/{$none['group']->slug}")
        ->assertSuccessful()
        ->assertSee('data-test="content-group-detail-fallback"', false)
        ->assertDontSee('default-images/group-hidden.jpg', false);
});

it('renders contributor custom inherit and none fallbacks on cards and detail pages', function (): void {
    $custom = createStep10V1aPublicItem('V1A Contributor Custom');
    saveStep10V1aPublicFrontSettings([
        'card_templates' => [
            step10V1aContributorImageTemplate(),
        ],
        'default_images' => step10V1aDefaultImages([
            'contributor' => ['mode' => 'custom', 'path' => 'default-images/contributor-custom.jpg'],
        ]),
    ]);

    $this->get('/contributors')
        ->assertSuccessful()
        ->assertSee('/storage/default-images/contributor-custom.jpg', false)
        ->assertSee('data-contributor-image-source="contributor_default"', false);
    $this->get("/contributors/{$custom['author']->slug}")
        ->assertSuccessful()
        ->assertSee('/storage/default-images/contributor-custom.jpg', false)
        ->assertSee('data-contributor-image-source="contributor_default"', false);

    $global = createStep10V1aPublicItem('V1A Contributor Global');
    saveStep10V1aPublicFrontSettings([
        'card_templates' => [
            step10V1aContributorImageTemplate(),
        ],
        'default_images' => step10V1aDefaultImages([
            'global' => ['mode' => 'custom', 'path' => 'default-images/contributor-global.jpg'],
            'contributor' => ['mode' => 'inherit', 'path' => null],
        ]),
    ]);

    $this->get('/contributors')
        ->assertSuccessful()
        ->assertSee('/storage/default-images/contributor-global.jpg', false)
        ->assertSee('data-contributor-image-source="global_default"', false);
    $this->get("/contributors/{$global['author']->slug}")
        ->assertSuccessful()
        ->assertSee('/storage/default-images/contributor-global.jpg', false)
        ->assertSee('data-contributor-image-source="global_default"', false);

    $none = createStep10V1aPublicItem('V1A Contributor None');
    saveStep10V1aPublicFrontSettings([
        'card_templates' => [
            step10V1aContributorImageTemplate(),
        ],
        'default_images' => step10V1aDefaultImages([
            'global' => ['mode' => 'custom', 'path' => 'default-images/contributor-hidden.jpg'],
            'contributor' => ['mode' => 'none', 'path' => null],
            'content_item' => ['mode' => 'none', 'path' => null],
        ]),
    ]);

    $this->get('/contributors')
        ->assertSuccessful()
        ->assertSee('data-test="contributor-card"', false)
        ->assertDontSee('default-images/contributor-hidden.jpg', false);
    $this->get("/contributors/{$none['author']->slug}")
        ->assertSuccessful()
        ->assertSee('data-test="contributor-page-fallback"', false)
        ->assertDontSee('default-images/contributor-hidden.jpg', false);
});
