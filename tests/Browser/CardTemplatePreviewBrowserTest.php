<?php

use App\Enums\HomepageSectionType;
use App\Filament\Pages\CardTemplateSettings;
use App\Filament\Pages\EditCardTemplate;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\HomepageSection;
use App\Models\Transcription;
use App\Models\User;
use App\Settings\PublicContentSettings;
use App\Support\PublicFront\Cards\PublicFrontCardTemplateRegistry;
use App\Support\PublicFront\Cards\PublicFrontCardTemplateResolver;
use App\Support\PublicFront\PublicDefaultImageResolver;
use App\Support\PublicFront\PublicFrontConfigCache;
use App\Support\PublicFront\PublicFrontRenderContext;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\LaravelSettings\SettingsContainer;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(User::factory()->admin()->create());

    $template = PublicFrontCardTemplateRegistry::defaultTemplateForFamily('content_item');
    $template['key'] = 'preview_browser';
    $template['label'] = 'Browser preview template';
    $template['parts'][] = [
        'type' => 'custom_text',
        'source' => 'custom',
        'attribute' => 'text',
        'text' => 'STEP5B BROWSER PART BEFORE',
        'visible' => true,
        'order' => 100,
        'layout' => 'inline',
    ];
    DB::table('settings')->updateOrInsert(
        [
            'group' => PublicContentSettings::group(),
            'name' => 'card_templates',
        ],
        [
            'locked' => false,
            'payload' => json_encode([$template], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ],
    );
    app()->forgetInstance(PublicContentSettings::class);
    app()->forgetInstance(PublicFrontRenderContext::class);
    app(PublicFrontConfigCache::class)->forget();
    app(SettingsContainer::class)->clearCache();

    $group = ContentGroup::factory()->published()->create(['title' => 'Browser Preview Group']);
    $item = ContentItem::factory()->for($group)->published()->create([
        'title' => str_repeat('Browser Preview Item ', 10),
    ]);
    $transcription = Transcription::factory()
        ->for($item)
        ->published(now()->subMinute())
        ->create(['title' => 'Browser Preview Transcription']);
    $item->update(['featured_transcription_id' => $transcription->getKey()]);

    $alternate = ContentItem::factory()->for($group)->published()->create([
        'title' => 'Alternate Browser Sample',
    ]);
    $alternateTranscription = Transcription::factory()
        ->for($alternate)
        ->published(now()->subDay())
        ->create(['title' => 'Alternate Browser Transcription']);
    $alternate->update(['featured_transcription_id' => $alternateTranscription->getKey()]);
});

afterEach(function (): void {
    Storage::disk('public')->delete([
        'default-images/o2-item-default.jpg',
        'default-images/o2-group-default.jpg',
    ]);
});

/**
 * @param  array<int, array<string, mixed>>  $parts
 * @return array<string, mixed>
 */
function step5bO2BrowserTemplate(string $family, string $key, string $layout, array $parts): array
{
    return [
        'key' => $key,
        'label' => "O2 browser {$key}",
        'family' => $family,
        'layout' => $layout,
        'density' => 'compact',
        'image_size' => 'small',
        'title_size' => 'base',
        'parts' => $parts,
    ];
}

/**
 * @return array{item: ContentItem, group: ContentGroup}
 */
function step5bO2BrowserSurfaces(): array
{
    $item = ContentItem::query()->where('title', 'like', 'Browser Preview Item%')->firstOrFail();
    $group = $item->contentGroup;
    $templates = [];

    foreach (['content_item', 'content_group'] as $family) {
        $source = $family;
        $prefix = $family === 'content_item' ? 'item' : 'group';
        $templates[] = step5bO2BrowserTemplate($family, "o2_browser_{$prefix}_leading", 'rows', [
            ['type' => 'image', 'source' => $source, 'attribute' => 'image', 'visible' => true, 'order' => 10],
            ['type' => 'title', 'source' => $source, 'attribute' => 'title', 'visible' => true, 'order' => 20],
        ]);
        $templates[] = step5bO2BrowserTemplate($family, "o2_browser_{$prefix}_body", 'rows', [
            ['type' => 'custom_text', 'source' => 'custom', 'attribute' => 'text', 'text' => "O2 {$prefix} body only", 'visible' => true, 'order' => 10],
            ['type' => 'title', 'source' => $source, 'attribute' => 'title', 'visible' => true, 'order' => 20],
        ]);
        $templates[] = step5bO2BrowserTemplate($family, "o2_browser_{$prefix}_ordered", 'rows', [
            ['type' => 'custom_text', 'source' => 'custom', 'attribute' => 'text', 'text' => "O2 {$prefix} before image", 'visible' => true, 'order' => 10],
            ['type' => 'image', 'source' => $source, 'attribute' => 'image', 'visible' => true, 'order' => 20],
            ['type' => 'title', 'source' => $source, 'attribute' => 'title', 'visible' => true, 'order' => 30],
            ['type' => 'image', 'source' => $source, 'attribute' => 'image', 'visible' => true, 'order' => 40],
        ]);
        $templates[] = step5bO2BrowserTemplate($family, "o2_browser_{$prefix}_card", 'cards', [
            ['type' => 'image', 'source' => $source, 'attribute' => 'image', 'visible' => true, 'order' => 10],
            ['type' => 'title', 'source' => $source, 'attribute' => 'title', 'visible' => true, 'order' => 20],
        ]);
    }

    foreach ([
        'card_templates' => $templates,
        'default_images' => [
            'global' => ['mode' => 'inherit', 'path' => null],
            'content_item' => ['mode' => 'custom', 'path' => 'default-images/o2-item-default.jpg'],
            'content_group' => ['mode' => 'custom', 'path' => 'default-images/o2-group-default.jpg'],
            'contributor' => ['mode' => 'inherit', 'path' => null],
        ],
    ] as $name => $payload) {
        DB::table('settings')->updateOrInsert(
            ['group' => PublicContentSettings::group(), 'name' => $name],
            [
                'locked' => false,
                'payload' => json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    $defaultImageFixture = file_get_contents(public_path('images/podtext-logo.jpg'));
    Storage::disk('public')->put('default-images/o2-item-default.jpg', $defaultImageFixture);
    Storage::disk('public')->put('default-images/o2-group-default.jpg', $defaultImageFixture);

    foreach (['leading', 'body', 'ordered', 'card'] as $index => $variant) {
        HomepageSection::factory()->create([
            'name' => "O2 browser item {$variant}",
            'type' => HomepageSectionType::Latest,
            'sort_order' => ($index * 2) + 1,
            'source_config' => ['source_type' => 'manual_content_items'],
            'selection_config' => ['include_ids' => [$item->getKey()]],
            'display_config' => [
                'template_family' => 'content_item',
                'template_key' => "o2_browser_item_{$variant}",
            ],
        ]);
        HomepageSection::factory()->create([
            'name' => "O2 browser group {$variant}",
            'type' => HomepageSectionType::Latest,
            'sort_order' => ($index * 2) + 2,
            'source_config' => ['source_type' => 'content_groups'],
            'selection_config' => ['include_ids' => [$group->getKey()]],
            'display_config' => [
                'template_family' => 'content_group',
                'template_key' => "o2_browser_group_{$variant}",
            ],
        ]);
    }

    app()->forgetScopedInstances();
    app()->forgetInstance(PublicContentSettings::class);
    app()->forgetInstance(PublicFrontRenderContext::class);
    app(PublicFrontConfigCache::class)->forget();
    app(SettingsContainer::class)->clearCache();

    return compact('item', 'group');
}

it('renders content-aware public item and group geometry in both directions', function (string $locale, string $direction): void {
    app()->setLocale($locale);
    $surfaces = step5bO2BrowserSurfaces();
    $renderContext = app(PublicFrontRenderContext::class);
    $resolver = app(PublicFrontCardTemplateResolver::class);

    expect($renderContext->cardTemplates())->toHaveCount(8)
        ->and($renderContext->defaultImages()['content_item']['mode'])->toBe('custom')
        ->and($renderContext->defaultImages()['content_group']['mode'])->toBe('custom')
        ->and(app(PublicDefaultImageResolver::class)->contentItemImage($surfaces['item'])['source'])->toBe('content_item_default')
        ->and(app(PublicDefaultImageResolver::class)->contentGroupImage($surfaces['group'])['source'])->toBe('content_group_default')
        ->and($resolver->resolve('content_item', 'o2_browser_item_leading')->key)->toBe('o2_browser_item_leading')
        ->and($resolver->resolve('content_group', 'o2_browser_group_leading')->key)->toBe('o2_browser_group_leading');

    $page = visit('/')->resize(1280, 1100);
    $matrix = [];

    foreach ([767, 768, 1024, 1280] as $width) {
        $page->resize($width, 1100);
        $measurement = $page->script(<<<'JS'
            async () => {
                await new Promise((resolve) => setTimeout(resolve, 250));
                const knownResizeObserverMessage = 'ResizeObserver loop completed with undelivered notifications.';
                const browserErrors = window.__pestBrowser?.jsErrors ?? [];
                window.__pestBrowser.jsErrors = browserErrors.filter((error) => error.message !== knownResizeObserverMessage);
                const card = (key) => document.querySelector(`[data-card-template-key="${key}"]`);
                const measure = (prefix) => {
                    const leading = card(`o2_browser_${prefix}_leading`);
                    const body = card(`o2_browser_${prefix}_body`);
                    const ordered = card(`o2_browser_${prefix}_ordered`);
                    const cardMode = card(`o2_browser_${prefix}_card`);
                    const leadingStyle = leading ? getComputedStyle(leading) : null;
                    const bodyStyle = body ? getComputedStyle(body) : null;
                    const leadingImage = leading?.querySelector('img');
                    const orderedImages = Array.from(ordered?.querySelectorAll('[data-card-part="image"]') ?? []);
                    const orderedRect = ordered?.getBoundingClientRect();
                    const fullBleed = orderedImages.every((image) => {
                        const rect = image.getBoundingClientRect();

                        return Math.abs(rect.left - orderedRect.left) <= 2
                            && Math.abs(rect.right - orderedRect.right) <= 2;
                    });

                    return {
                        found: [leading, body, ordered, cardMode].every(Boolean),
                        leading_flow: leading?.dataset.cardPartFlow,
                        leading_display: leadingStyle?.display,
                        leading_columns: leadingStyle?.gridTemplateColumns?.split(' ').length ?? 0,
                        leading_image_source: leading?.querySelector('[data-card-part="image"]')?.dataset.cardImageSource,
                        leading_image_loaded: Boolean(leadingImage?.complete && leadingImage.naturalWidth > 0),
                        body_flow: body?.dataset.cardPartFlow,
                        body_display: bodyStyle?.display,
                        body_grid: bodyStyle?.gridTemplateColumns,
                        body_gap: bodyStyle?.gap,
                        body_padding: bodyStyle?.padding,
                        body_images: body?.querySelectorAll('[data-card-part="image"]').length ?? -1,
                        ordered_flow: ordered?.dataset.cardPartFlow,
                        ordered_parts: ordered?.dataset.cardRendererParts,
                        ordered_images: orderedImages.length,
                        ordered_full_bleed: fullBleed,
                        card_mode_display: cardMode ? getComputedStyle(cardMode).display : null,
                        card_mode_layout: cardMode?.dataset.resultLayout,
                        nested_anchors: Array.from(ordered?.querySelectorAll('a') ?? []).some((anchor) => anchor.querySelector('a')),
                        public_hrefs: ordered?.querySelectorAll('a[href]').length ?? 0,
                    };
                };

                return {
                    viewport_width: window.innerWidth,
                    direction: document.documentElement.dir,
                    horizontal_overflow: document.documentElement.scrollWidth > document.documentElement.clientWidth + 1,
                    item: measure('item'),
                    group: measure('group'),
                    resize_observer_errors: browserErrors.filter((error) => error.message === knownResizeObserverMessage).length,
                    js_errors: browserErrors
                        .map((error) => error.message)
                        .filter((message) => message !== knownResizeObserverMessage),
                };
            }
            JS);
        $matrix[$width] = $measurement;

        expect($measurement['viewport_width'])->toBe($width)
            ->and($measurement['direction'])->toBe($direction)
            ->and($measurement['horizontal_overflow'])->toBeFalse()
            ->and($measurement['js_errors'])->toBe([]);

        foreach (['item', 'group'] as $family) {
            $card = $measurement[$family];

            expect($card['found'])->toBeTrue(json_encode($measurement, JSON_THROW_ON_ERROR))
                ->and($card['leading_flow'])->toBe('media-leading')
                ->and($card['leading_display'])->toBe('grid')
                ->and($card['leading_columns'])->toBe($width >= 768 ? 2 : 1)
                ->and($card['leading_image_source'])->toBe($family === 'item' ? 'content_item_default' : 'content_group_default')
                ->and($card['leading_image_loaded'])->toBeTrue(json_encode($measurement, JSON_THROW_ON_ERROR))
                ->and($card['body_flow'])->toBe('body-only')
                ->and($card['body_display'])->toBe('flex')
                ->and($card['body_grid'])->toBe('none')
                ->and($card['body_gap'])->toBe('normal')
                ->and($card['body_padding'])->toBe('0px')
                ->and($card['body_images'])->toBe(0)
                ->and($card['ordered_flow'])->toBe('ordered-stack')
                ->and($card['ordered_parts'])->toBe('custom_text,image,title,image')
                ->and($card['ordered_images'])->toBe(2)
                ->and($card['ordered_full_bleed'])->toBeTrue(json_encode($measurement, JSON_THROW_ON_ERROR))
                ->and($card['card_mode_display'])->toBe('flex')
                ->and($card['card_mode_layout'])->toBe('cards')
                ->and($card['nested_anchors'])->toBeFalse()
                ->and($card['public_hrefs'])->toBeGreaterThan(0);
        }
    }

    if (getenv('STEP5B_O2_BROWSER_REPORT') === '1') {
        fwrite(STDERR, json_encode([
            'locale' => $locale,
            'direction' => $direction,
            'public_matrix' => $matrix,
            'measurement_plane' => 'Chromium DOM and computed style after fixture-backed server rendering.',
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES).PHP_EOL);
    }

    $page->assertNoSmoke()->assertNoJavaScriptErrors();
})->with([
    'Hebrew RTL' => ['he', 'rtl'],
    'English LTR' => ['en', 'ltr'],
]);

it('renders every ordered-flow preview variant responsively for item and group', function (string $locale, string $direction): void {
    app()->setLocale($locale);
    step5bO2BrowserSurfaces();
    $matrix = [];
    $expectations = [
        'leading' => ['flow' => 'media-leading', 'parts' => 'image,title', 'layout' => 'rows', 'images' => 1],
        'body' => ['flow' => 'body-only', 'parts' => 'custom_text,title', 'layout' => 'cards', 'images' => 0],
        'ordered' => ['flow' => 'ordered-stack', 'parts' => 'custom_text,image,title,image', 'layout' => 'cards', 'images' => 2],
        'card' => ['flow' => 'media-leading', 'parts' => 'image,title', 'layout' => 'cards', 'images' => 1],
    ];

    foreach (['item' => 'content_item', 'group' => 'content_group'] as $prefix => $family) {
        foreach ($expectations as $variant => $expected) {
            $page = visit(EditCardTemplate::getUrl([
                'family' => $family,
                'key' => "o2_browser_{$prefix}_{$variant}",
            ]))->resize(1280, 900);

            foreach ([1280, 1024, 768, 767] as $width) {
                $page->resize($width, 900);
                $measurement = $page->script(<<<'JS'
                    async () => {
                        const isNarrow = window.innerWidth < 1024;
                        let started = performance.now();

                        if (isNarrow) {
                            while (document.querySelector('[data-card-template-preview-root]') !== null && performance.now() - started < 5000) {
                                await new Promise((resolve) => setTimeout(resolve, 25));
                            }

                            const trigger = document.querySelector('[data-test="card-template-preview-open"]');
                            trigger?.focus();
                            trigger?.click();
                            started = performance.now();

                            while (document.querySelector('[data-card-template-preview-modal]') === null && performance.now() - started < 5000) {
                                await new Promise((resolve) => setTimeout(resolve, 25));
                            }
                        } else {
                            while (document.querySelector('[data-card-template-preview-adjacent]') === null && performance.now() - started < 5000) {
                                await new Promise((resolve) => setTimeout(resolve, 25));
                            }
                        }

                        await new Promise((resolve) => setTimeout(resolve, 250));
                        const knownResizeObserverMessage = 'ResizeObserver loop completed with undelivered notifications.';
                        const browserErrors = window.__pestBrowser?.jsErrors ?? [];
                        window.__pestBrowser.jsErrors = browserErrors.filter((error) => error.message !== knownResizeObserverMessage);
                        const root = document.querySelector('[data-card-template-preview-root]');
                        const ready = root?.querySelector('[data-test="card-template-preview-ready"]');
                        const card = ready?.querySelector('[data-card-template-key]');
                        const style = card ? getComputedStyle(card) : null;
                        const images = Array.from(card?.querySelectorAll('[data-card-part="image"]') ?? []);
                        const rect = card?.getBoundingClientRect();

                        return {
                            viewport_width: window.innerWidth,
                            direction: document.documentElement.dir,
                            preview_roots: document.querySelectorAll('[data-card-template-preview-root]').length,
                            adjacent_roots: document.querySelectorAll('[data-card-template-preview-adjacent]').length,
                            modal_roots: document.querySelectorAll('[data-card-template-preview-modal]').length,
                            flow: card?.dataset.cardPartFlow,
                            parts: card?.dataset.cardRendererParts,
                            layout: card?.dataset.resultLayout,
                            display: style?.display,
                            columns: style?.gridTemplateColumns?.split(' ').length ?? 0,
                            grid: style?.gridTemplateColumns,
                            gap: style?.gap,
                            padding: style?.padding,
                            image_source: images[0]?.dataset.cardImageSource ?? null,
                            images: images.length,
                            full_bleed: images.every((image) => {
                                const imageRect = image.getBoundingClientRect();

                                return Math.abs(imageRect.left - rect.left) <= 2
                                    && Math.abs(imageRect.right - rect.right) <= 2;
                            }),
                            interactions: ready?.querySelectorAll('a[href], [wire\\:click], button, input, select, textarea').length ?? -1,
                            horizontal_overflow: document.documentElement.scrollWidth > document.documentElement.clientWidth + 1,
                            resize_observer_errors: browserErrors.filter((error) => error.message === knownResizeObserverMessage).length,
                            js_errors: browserErrors
                                .map((error) => error.message)
                                .filter((message) => message !== knownResizeObserverMessage),
                        };
                    }
                    JS);
                $matrix[$prefix][$variant][$width] = $measurement;

                expect($measurement['viewport_width'])->toBe($width)
                    ->and($measurement['direction'])->toBe($direction)
                    ->and($measurement['preview_roots'])->toBe(1)
                    ->and($measurement['adjacent_roots'])->toBe($width >= 1024 ? 1 : 0)
                    ->and($measurement['modal_roots'])->toBe($width < 1024 ? 1 : 0)
                    ->and($measurement['flow'])->toBe($expected['flow'])
                    ->and($measurement['parts'])->toBe($expected['parts'])
                    ->and($measurement['layout'])->toBe($expected['layout'])
                    ->and($measurement['images'])->toBe($expected['images'])
                    ->and($measurement['interactions'])->toBe(0)
                    ->and($measurement['horizontal_overflow'])->toBeFalse()
                    ->and($measurement['js_errors'])->toBe([]);

                if ($variant === 'leading') {
                    expect($measurement['display'])->toBe('grid')
                        ->and($measurement['columns'])->toBe($width >= 768 ? 2 : 1)
                        ->and($measurement['image_source'])->toBe('fallback');
                }

                if ($variant === 'body') {
                    expect($measurement['display'])->toBe('flex')
                        ->and($measurement['grid'])->toBe('none')
                        ->and($measurement['gap'])->toBe('normal')
                        ->and($measurement['padding'])->toBe('0px')
                        ->and($measurement['image_source'])->toBeNull();
                }

                if ($variant === 'ordered') {
                    expect($measurement['display'])->toBe('flex')
                        ->and($measurement['image_source'])->toBe('fallback')
                        ->and($measurement['full_bleed'])->toBeTrue(json_encode($measurement, JSON_THROW_ON_ERROR));
                }

                if ($variant === 'card') {
                    expect($measurement['display'])->toBe('flex')
                        ->and($measurement['image_source'])->toBe('fallback');
                }

                if ($width < 1024) {
                    $closed = $page->script(<<<'JS'
                        async () => {
                            window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
                            const started = performance.now();

                            while (document.querySelector('[data-card-template-preview-modal]') !== null && performance.now() - started < 5000) {
                                await new Promise((resolve) => setTimeout(resolve, 25));
                            }

                            return document.querySelectorAll('[data-card-template-preview-root]').length;
                        }
                        JS);

                    expect($closed)->toBe(0);
                }
            }

            $page->assertNoSmoke()->assertNoJavaScriptErrors();
        }
    }

    if (getenv('STEP5B_O2_BROWSER_REPORT') === '1') {
        fwrite(STDERR, json_encode([
            'locale' => $locale,
            'direction' => $direction,
            'preview_matrix' => $matrix,
            'measurement_plane' => 'Authenticated Chromium preview DOM and computed style at 767, 768, 1024, and 1280px.',
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES).PHP_EOL);
    }
})->with([
    'Hebrew RTL' => ['he', 'rtl'],
    'English LTR' => ['en', 'ltr'],
]);

it('keeps one inert responsive preview root with focus and dirty navigation protection', function (): void {
    app()->setLocale('he');
    $page = visit(EditCardTemplate::getUrl([
        'family' => 'content_item',
        'key' => 'preview_browser',
    ]))->resize(1280, 900);

    $wide = $page->script(<<<'JS'
        async () => {
            await new Promise((resolve) => setTimeout(resolve, 250));
            const root = document.querySelector('[data-card-template-preview-root]');
            const ready = root?.querySelector('[data-test="card-template-preview-ready"]');
            const previewColumn = document.querySelector('[data-card-template-preview-column]');
            const editorColumn = document.querySelector('[data-card-template-editor-column]');
            const previewRect = previewColumn?.getBoundingClientRect();
            const editorRect = editorColumn?.getBoundingClientRect();
            const draftSection = editorColumn?.querySelector('[data-sp3c-template-editor]');
            const draftRect = draftSection?.getBoundingClientRect();
            const headerMetadata = document.querySelector('.fi-header [data-card-template-import-lock-metadata]');
            const focusableSelector = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';
            const trigger = document.querySelector('[data-test="card-template-preview-open"]');

            return {
                viewport_width: window.innerWidth,
                dom_elements: document.querySelectorAll('*').length,
                preview_roots: document.querySelectorAll('[data-card-template-preview-root]').length,
                adjacent_roots: document.querySelectorAll('[data-card-template-preview-adjacent]').length,
                modal_roots: document.querySelectorAll('[data-card-template-preview-modal]').length,
                preview_focusables: root?.querySelectorAll(focusableSelector).length ?? 0,
                public_interactions: ready?.querySelectorAll('a[href], [wire\\:click], button, input, select, textarea').length ?? 0,
                direction: document.documentElement.dir,
                key_direction: document.querySelector('[data-sp3c-template-editor] input[dir="ltr"]')?.dir ?? null,
                horizontal_overflow: document.documentElement.scrollWidth > document.documentElement.clientWidth + 1,
                shell_overflow: getComputedStyle(document.querySelector('[data-card-template-preview-wide-shell]')).overflowY,
                preview_overflow: getComputedStyle(document.querySelector('[data-card-template-preview-scroll]')).overflowY,
                preview_is_logical_end: previewRect?.right <= editorRect?.left,
                columns_do_not_overlap: previewRect?.right <= editorRect?.left,
                editor_width: editorRect?.width ?? null,
                preview_width: previewRect?.width ?? null,
                opener_hidden: Boolean(trigger && (getComputedStyle(trigger).display === 'none' || trigger.offsetParent === null)),
                draft_is_first_editor_section: Math.abs((draftRect?.top ?? -1) - (editorRect?.top ?? -3)) < 2,
                import_metadata_in_header: Boolean(headerMetadata),
                import_metadata_in_editor: Boolean(editorColumn?.querySelector('[data-card-template-import-lock-metadata]')),
                livewire_components: window.Livewire?.all?.().length ?? null,
                used_js_heap_size: performance.memory?.usedJSHeapSize ?? null,
            };
        }
        JS);

    expect($wide['viewport_width'])->toBe(1280)
        ->and($wide['preview_roots'])->toBe(1)
        ->and($wide['adjacent_roots'])->toBe(1)
        ->and($wide['modal_roots'])->toBe(0)
        ->and($wide['public_interactions'])->toBe(0)
        ->and($wide['direction'])->toBe('rtl')
        ->and($wide['key_direction'])->toBe('ltr')
        ->and($wide['horizontal_overflow'])->toBeFalse()
        ->and($wide['shell_overflow'])->toBe('auto')
        ->and($wide['preview_overflow'])->toBe('auto')
        ->and($wide['preview_is_logical_end'])->toBeTrue(json_encode($wide, JSON_THROW_ON_ERROR))
        ->and($wide['columns_do_not_overlap'])->toBeTrue(json_encode($wide, JSON_THROW_ON_ERROR))
        ->and($wide['editor_width'])->toBeGreaterThan(200)
        ->and($wide['preview_width'])->toBeGreaterThan(250)
        ->and($wide['opener_hidden'])->toBeTrue()
        ->and($wide['draft_is_first_editor_section'])->toBeTrue()
        ->and($wide['import_metadata_in_header'])->toBeTrue()
        ->and($wide['import_metadata_in_editor'])->toBeFalse();

    $wideMatrix = [];

    foreach ([1024, 1279, 1280] as $width) {
        $page->resize($width, 900);
        $measurement = $page->script(<<<'JS'
            async () => {
                await new Promise((resolve) => setTimeout(resolve, 200));
                const preview = document.querySelector('[data-card-template-preview-column]');
                const editor = document.querySelector('[data-card-template-editor-column]');
                const trigger = document.querySelector('[data-test="card-template-preview-open"]');
                const previewRect = preview?.getBoundingClientRect();
                const editorRect = editor?.getBoundingClientRect();

                return {
                    viewport_width: window.innerWidth,
                    preview_roots: document.querySelectorAll('[data-card-template-preview-root]').length,
                    adjacent_roots: document.querySelectorAll('[data-card-template-preview-adjacent]').length,
                    modal_roots: document.querySelectorAll('[data-card-template-preview-modal]').length,
                    opener_hidden: Boolean(trigger && (getComputedStyle(trigger).display === 'none' || trigger.offsetParent === null)),
                    horizontal_overflow: document.documentElement.scrollWidth > document.documentElement.clientWidth + 1,
                    preview_is_logical_end: previewRect?.right <= editorRect?.left,
                    columns_do_not_overlap: previewRect?.right <= editorRect?.left,
                    editor_width: editorRect?.width ?? null,
                    preview_width: previewRect?.width ?? null,
                };
            }
            JS);
        $wideMatrix[$width] = $measurement;

        expect($measurement['viewport_width'])->toBe($width)
            ->and($measurement['preview_roots'])->toBe(1)
            ->and($measurement['adjacent_roots'])->toBe(1)
            ->and($measurement['modal_roots'])->toBe(0)
            ->and($measurement['opener_hidden'])->toBeTrue(json_encode($measurement, JSON_THROW_ON_ERROR))
            ->and($measurement['horizontal_overflow'])->toBeFalse()
            ->and($measurement['preview_is_logical_end'])->toBeTrue(json_encode($measurement, JSON_THROW_ON_ERROR))
            ->and($measurement['columns_do_not_overlap'])->toBeTrue(json_encode($measurement, JSON_THROW_ON_ERROR))
            ->and($measurement['editor_width'])->toBeGreaterThan(200)
            ->and($measurement['preview_width'])->toBeGreaterThan(250);
    }

    $refresh = $page->script(<<<'JS'
        async () => {
            performance.clearResourceTimings();
            const beforeDom = document.querySelectorAll('*').length;
            const beforeHeap = performance.memory?.usedJSHeapSize ?? null;
            const originalFetch = window.fetch;
            const requestUrls = [];
            window.fetch = (...arguments_) => {
                requestUrls.push(String(arguments_[0]?.url ?? arguments_[0]));

                return originalFetch(...arguments_);
            };
            const started = performance.now();
            document.querySelector('[data-test="card-template-preview-refresh"]').click();

            while (requestUrls.length === 0 && performance.now() - started < 5000) {
                await new Promise((resolve) => setTimeout(resolve, 25));
            }

            await new Promise((resolve) => setTimeout(resolve, 100));
            window.fetch = originalFetch;
            const resources = performance.getEntriesByType('resource');

            return {
                duration_ms: Math.round(performance.now() - started),
                network_requests: requestUrls.filter((url) => url.includes('/livewire')).length,
                performance_network_requests: resources.filter((entry) => entry.name.includes('/livewire')).length,
                dom_delta: document.querySelectorAll('*').length - beforeDom,
                preview_roots: document.querySelectorAll('[data-card-template-preview-root]').length,
                heap_delta: beforeHeap === null ? null : (performance.memory.usedJSHeapSize - beforeHeap),
            };
        }
        JS);

    expect($refresh['network_requests'])->toBe(1)
        ->and($refresh['preview_roots'])->toBe(1);

    $narrowMatrix = [];

    foreach ([767, 768, 1023] as $width) {
        $page->resize($width, 800);
        $closed = $page->script(<<<'JS'
            async () => {
                await new Promise((resolve) => setTimeout(resolve, 200));
                const trigger = document.querySelector('[data-test="card-template-preview-open"]');

                return {
                    viewport_width: window.innerWidth,
                    preview_roots: document.querySelectorAll('[data-card-template-preview-root]').length,
                    adjacent_roots: document.querySelectorAll('[data-card-template-preview-adjacent]').length,
                    modal_roots: document.querySelectorAll('[data-card-template-preview-modal]').length,
                    opener_visible: Boolean(trigger && getComputedStyle(trigger).display !== 'none' && trigger.getBoundingClientRect().width > 0),
                    horizontal_overflow: document.documentElement.scrollWidth > document.documentElement.clientWidth + 1,
                };
            }
            JS);

        expect($closed['viewport_width'])->toBe($width)
            ->and($closed['preview_roots'])->toBe(0)
            ->and($closed['adjacent_roots'])->toBe(0)
            ->and($closed['modal_roots'])->toBe(0)
            ->and($closed['opener_visible'])->toBeTrue(json_encode($closed, JSON_THROW_ON_ERROR))
            ->and($closed['horizontal_overflow'])->toBeFalse();

        $open = $page->script(<<<'JS'
            async () => {
                const beforeDom = document.querySelectorAll('*').length;
                const started = performance.now();
                const trigger = document.querySelector('[data-test="card-template-preview-open"]');
                trigger.focus();
                trigger.click();

                while (document.querySelector('[data-card-template-preview-modal]') === null && performance.now() - started < 5000) {
                    await new Promise((resolve) => setTimeout(resolve, 25));
                }

                await new Promise((resolve) => setTimeout(resolve, 350));
                const modal = document.querySelector('[data-card-template-preview-modal]');
                const dialog = modal?.closest('[aria-modal="true"]');
                const modalWindow = dialog?.querySelector('.fi-modal-window');
                const modalRect = modalWindow?.getBoundingClientRect();

                return {
                    viewport_width: window.innerWidth,
                    dom_delta: document.querySelectorAll('*').length - beforeDom,
                    preview_roots: document.querySelectorAll('[data-card-template-preview-root]').length,
                    adjacent_roots: document.querySelectorAll('[data-card-template-preview-adjacent]').length,
                    modal_roots: document.querySelectorAll('[data-card-template-preview-modal]').length,
                    active_inside_modal: Boolean(dialog?.contains(document.activeElement)),
                    active_element: document.activeElement?.id || document.activeElement?.tagName || null,
                    horizontal_overflow: document.documentElement.scrollWidth > document.documentElement.clientWidth + 1,
                    modal_is_logical_end: (modalRect?.left ?? Infinity) <= 4,
                    modal_public_interactions: modal?.querySelectorAll('[data-test="card-template-preview-ready"] a[href], [data-test="card-template-preview-ready"] [wire\\:click], [data-test="card-template-preview-ready"] button').length ?? 0,
                };
            }
            JS);

        expect($open['viewport_width'])->toBe($width)
            ->and($open['preview_roots'])->toBe(1)
            ->and($open['adjacent_roots'])->toBe(0)
            ->and($open['modal_roots'])->toBe(1)
            ->and($open['active_inside_modal'])->toBeTrue(json_encode($open, JSON_THROW_ON_ERROR))
            ->and($open['horizontal_overflow'])->toBeFalse()
            ->and($open['modal_is_logical_end'])->toBeTrue(json_encode($open, JSON_THROW_ON_ERROR))
            ->and($open['modal_public_interactions'])->toBe(0);

        $page->keys('#card-template-preview-heading', ['Tab', 'Tab', 'Tab', 'Tab']);
        expect($page->script("Boolean(document.activeElement?.closest('[aria-modal=true]'))"))->toBeTrue();

        $escapeRestored = $page->script(<<<'JS'
            async () => {
                const started = performance.now();
                window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));

                while (document.querySelector('[data-card-template-preview-modal]') !== null && performance.now() - started < 5000) {
                    await new Promise((resolve) => setTimeout(resolve, 25));
                }

                await new Promise((resolve) => setTimeout(resolve, 50));

                return document.querySelector('[data-card-template-preview-modal]') === null
                    && Boolean(document.activeElement?.closest('[data-test="card-template-preview-open"]'));
            }
            JS);
        expect($escapeRestored)->toBeTrue();
        $narrowMatrix[$width] = compact('closed', 'open', 'escapeRestored');
    }

    $dirty = $page->script(<<<'JS'
        async () => {
            const input = Array.from(document.querySelectorAll('[data-sp3c-template-editor] input'))
                .find((candidate) => candidate.getAttribute('wire:model')?.includes('data.label'));
            const setter = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value').set;
            const value = `${input.value} dirty boundary`;
            setter.call(input, value);
            input.dispatchEvent(new Event('input', { bubbles: true }));
            await new Promise((resolve) => setTimeout(resolve, 150));
            const event = new Event('beforeunload', { bubbles: false, cancelable: true });
            window.dispatchEvent(event);

            return {
                value,
                current_value: input.value,
                protected: event.defaultPrevented,
            };
        }
        JS);

    expect($dirty['current_value'])->toBe($dirty['value'])
        ->and($dirty['protected'])->toBeTrue();

    $modalBeforeResize = $page->script(<<<'JS'
        async () => {
            const trigger = document.querySelector('[data-test="card-template-preview-open"]');
            const started = performance.now();
            trigger.focus();
            trigger.click();

            while (document.querySelector('[data-card-template-preview-modal]') === null && performance.now() - started < 5000) {
                await new Promise((resolve) => setTimeout(resolve, 25));
            }

            await new Promise((resolve) => setTimeout(resolve, 350));
            document.querySelector('#card-template-preview-heading')?.focus();
            const rootsIn = (nodes) => Array.from(nodes).reduce((count, node) => {
                if (! (node instanceof Element)) {
                    return count;
                }

                return count
                    + (node.matches('[data-card-template-preview-root]') ? 1 : 0)
                    + node.querySelectorAll('[data-card-template-preview-root]').length;
            }, 0);
            window.__step5bRootCount = document.querySelectorAll('[data-card-template-preview-root]').length;
            window.__step5bRootPeak = window.__step5bRootCount;
            window.__step5bRootObserver = new MutationObserver((mutations) => {
                for (const mutation of mutations) {
                    window.__step5bRootCount -= rootsIn(mutation.removedNodes);
                    window.__step5bRootCount += rootsIn(mutation.addedNodes);
                    window.__step5bRootPeak = Math.max(window.__step5bRootPeak, window.__step5bRootCount);
                }
            });
            window.__step5bRootObserver.observe(document.body, { childList: true, subtree: true });
            window.__step5bOriginalFetch = window.fetch;
            window.__step5bRequestUrls = [];
            window.fetch = (...arguments_) => {
                window.__step5bRequestUrls.push(String(arguments_[0]?.url ?? arguments_[0]));

                return window.__step5bOriginalFetch(...arguments_);
            };

            return {
                viewport_width: window.innerWidth,
                preview_roots: document.querySelectorAll('[data-card-template-preview-root]').length,
                modal_roots: document.querySelectorAll('[data-card-template-preview-modal]').length,
                focus_on_heading: document.activeElement?.id === 'card-template-preview-heading',
            };
        }
        JS);

    expect($modalBeforeResize['viewport_width'])->toBe(1023)
        ->and($modalBeforeResize['preview_roots'])->toBe(1)
        ->and($modalBeforeResize['modal_roots'])->toBe(1)
        ->and($modalBeforeResize['focus_on_heading'])->toBeTrue();

    $page->resize(1024, 800);
    $wideRestored = $page->script(<<<'JS'
        async () => {
            const started = performance.now();

            while (
                (document.querySelector('[data-card-template-preview-adjacent]') === null
                    || document.querySelector('[data-card-template-preview-modal]') !== null)
                && performance.now() - started < 7000
            ) {
                await new Promise((resolve) => setTimeout(resolve, 25));
            }

            await new Promise((resolve) => setTimeout(resolve, 150));
            window.__step5bRootPeak = Math.max(
                window.__step5bRootPeak,
                document.querySelectorAll('[data-card-template-preview-root]').length,
            );
            window.__step5bRootObserver?.disconnect();
            window.fetch = window.__step5bOriginalFetch;
            const input = Array.from(document.querySelectorAll('[data-sp3c-template-editor] input'))
                .find((candidate) => candidate.getAttribute('wire:model')?.includes('data.label'));
            const previewRect = document.querySelector('[data-card-template-preview-column]')?.getBoundingClientRect();
            const editorRect = document.querySelector('[data-card-template-editor-column]')?.getBoundingClientRect();
            const trigger = document.querySelector('[data-test="card-template-preview-open"]');
            const event = new Event('beforeunload', { bubbles: false, cancelable: true });
            window.dispatchEvent(event);
            const result = {
                viewport_width: window.innerWidth,
                peak_preview_roots: window.__step5bRootPeak,
                preview_roots: document.querySelectorAll('[data-card-template-preview-root]').length,
                adjacent_roots: document.querySelectorAll('[data-card-template-preview-adjacent]').length,
                modal_roots: document.querySelectorAll('[data-card-template-preview-modal]').length,
                focus_on_heading: document.activeElement?.id === 'card-template-preview-heading',
                livewire_requests: window.__step5bRequestUrls.filter((url) => url.includes('/livewire')).length,
                dirty_value: input?.value ?? null,
                dirty_protected: event.defaultPrevented,
                opener_hidden: Boolean(trigger && (getComputedStyle(trigger).display === 'none' || trigger.offsetParent === null)),
                horizontal_overflow: document.documentElement.scrollWidth > document.documentElement.clientWidth + 1,
                preview_is_logical_end: previewRect?.right <= editorRect?.left,
                columns_do_not_overlap: previewRect?.right <= editorRect?.left,
                editor_width: editorRect?.width ?? null,
                preview_width: previewRect?.width ?? null,
            };
            delete window.__step5bRootCount;
            delete window.__step5bRootPeak;
            delete window.__step5bRootObserver;
            delete window.__step5bOriginalFetch;
            delete window.__step5bRequestUrls;

            return result;
        }
        JS);

    expect($wideRestored['viewport_width'])->toBe(1024)
        ->and($wideRestored['peak_preview_roots'])->toBeLessThanOrEqual(1)
        ->and($wideRestored['preview_roots'])->toBe(1)
        ->and($wideRestored['adjacent_roots'])->toBe(1)
        ->and($wideRestored['modal_roots'])->toBe(0)
        ->and($wideRestored['focus_on_heading'])->toBeTrue(json_encode($wideRestored, JSON_THROW_ON_ERROR))
        ->and($wideRestored['livewire_requests'])->toBe(1)
        ->and($wideRestored['dirty_value'])->toBe($dirty['value'])
        ->and($wideRestored['dirty_protected'])->toBeTrue()
        ->and($wideRestored['opener_hidden'])->toBeTrue()
        ->and($wideRestored['horizontal_overflow'])->toBeFalse()
        ->and($wideRestored['preview_is_logical_end'])->toBeTrue(json_encode($wideRestored, JSON_THROW_ON_ERROR))
        ->and($wideRestored['columns_do_not_overlap'])->toBeTrue(json_encode($wideRestored, JSON_THROW_ON_ERROR))
        ->and($wideRestored['editor_width'])->toBeGreaterThan(200)
        ->and($wideRestored['preview_width'])->toBeGreaterThan(250);

    $page->resize(1023, 800);
    $narrowRestored = $page->script(<<<'JS'
        async () => {
            await new Promise((resolve) => setTimeout(resolve, 250));
            const trigger = document.querySelector('[data-test="card-template-preview-open"]');
            const input = Array.from(document.querySelectorAll('[data-sp3c-template-editor] input'))
                .find((candidate) => candidate.getAttribute('wire:model')?.includes('data.label'));
            const event = new Event('beforeunload', { bubbles: false, cancelable: true });
            window.dispatchEvent(event);

            return {
                viewport_width: window.innerWidth,
                preview_roots: document.querySelectorAll('[data-card-template-preview-root]').length,
                adjacent_roots: document.querySelectorAll('[data-card-template-preview-adjacent]').length,
                modal_roots: document.querySelectorAll('[data-card-template-preview-modal]').length,
                opener_visible: Boolean(trigger && getComputedStyle(trigger).display !== 'none' && trigger.getBoundingClientRect().width > 0),
                focus_on_opener: Boolean(document.activeElement?.closest('[data-test="card-template-preview-open"]')),
                dirty_value: input?.value ?? null,
                dirty_protected: event.defaultPrevented,
                horizontal_overflow: document.documentElement.scrollWidth > document.documentElement.clientWidth + 1,
            };
        }
        JS);

    expect($narrowRestored['viewport_width'])->toBe(1023)
        ->and($narrowRestored['preview_roots'])->toBe(0)
        ->and($narrowRestored['adjacent_roots'])->toBe(0)
        ->and($narrowRestored['modal_roots'])->toBe(0)
        ->and($narrowRestored['opener_visible'])->toBeTrue()
        ->and($narrowRestored['focus_on_opener'])->toBeTrue(json_encode($narrowRestored, JSON_THROW_ON_ERROR))
        ->and($narrowRestored['dirty_value'])->toBe($dirty['value'])
        ->and($narrowRestored['dirty_protected'])->toBeTrue()
        ->and($narrowRestored['horizontal_overflow'])->toBeFalse();

    $rapidModal = $page->script(<<<'JS'
        async () => {
            const trigger = document.querySelector('[data-test="card-template-preview-open"]');
            const started = performance.now();
            trigger.focus();
            trigger.click();

            while (document.querySelector('[data-card-template-preview-modal]') === null && performance.now() - started < 5000) {
                await new Promise((resolve) => setTimeout(resolve, 25));
            }

            await new Promise((resolve) => setTimeout(resolve, 350));
            document.querySelector('#card-template-preview-heading')?.focus();
            const rootsIn = (nodes) => Array.from(nodes).reduce((count, node) => {
                if (! (node instanceof Element)) {
                    return count;
                }

                return count
                    + (node.matches('[data-card-template-preview-root]') ? 1 : 0)
                    + node.querySelectorAll('[data-card-template-preview-root]').length;
            }, 0);
            window.__step5bRapidRootCount = document.querySelectorAll('[data-card-template-preview-root]').length;
            window.__step5bRapidRootPeak = window.__step5bRapidRootCount;
            window.__step5bRapidRootObserver = new MutationObserver((mutations) => {
                for (const mutation of mutations) {
                    window.__step5bRapidRootCount -= rootsIn(mutation.removedNodes);
                    window.__step5bRapidRootCount += rootsIn(mutation.addedNodes);
                    window.__step5bRapidRootPeak = Math.max(window.__step5bRapidRootPeak, window.__step5bRapidRootCount);
                }
            });
            window.__step5bRapidRootObserver.observe(document.body, { childList: true, subtree: true });
            window.__step5bRapidOriginalFetch = window.fetch;
            window.__step5bRapidRequestUrls = [];
            window.fetch = (...arguments_) => {
                window.__step5bRapidRequestUrls.push(String(arguments_[0]?.url ?? arguments_[0]));

                return window.__step5bRapidOriginalFetch(...arguments_);
            };

            return {
                preview_roots: document.querySelectorAll('[data-card-template-preview-root]').length,
                modal_roots: document.querySelectorAll('[data-card-template-preview-modal]').length,
                focus_on_heading: document.activeElement?.id === 'card-template-preview-heading',
            };
        }
        JS);

    expect($rapidModal)->toBe([
        'preview_roots' => 1,
        'modal_roots' => 1,
        'focus_on_heading' => true,
    ]);

    $page->resize(1024, 800);
    $page->resize(1023, 800);
    $rapidResizeBack = $page->script(<<<'JS'
        async () => {
            const started = performance.now();

            while (document.querySelector('[data-card-template-preview-modal]') !== null && performance.now() - started < 7000) {
                await new Promise((resolve) => setTimeout(resolve, 25));
            }

            await new Promise((resolve) => setTimeout(resolve, 200));
            window.__step5bRapidRootPeak = Math.max(
                window.__step5bRapidRootPeak,
                document.querySelectorAll('[data-card-template-preview-root]').length,
            );
            window.__step5bRapidRootObserver?.disconnect();
            window.fetch = window.__step5bRapidOriginalFetch;
            const trigger = document.querySelector('[data-test="card-template-preview-open"]');
            const input = Array.from(document.querySelectorAll('[data-sp3c-template-editor] input'))
                .find((candidate) => candidate.getAttribute('wire:model')?.includes('data.label'));
            const result = {
                viewport_width: window.innerWidth,
                peak_preview_roots: window.__step5bRapidRootPeak,
                preview_roots: document.querySelectorAll('[data-card-template-preview-root]').length,
                adjacent_roots: document.querySelectorAll('[data-card-template-preview-adjacent]').length,
                modal_roots: document.querySelectorAll('[data-card-template-preview-modal]').length,
                livewire_requests: window.__step5bRapidRequestUrls.filter((url) => url.includes('/livewire')).length,
                opener_visible: Boolean(trigger && getComputedStyle(trigger).display !== 'none' && trigger.getBoundingClientRect().width > 0),
                focus_on_opener: Boolean(document.activeElement?.closest('[data-test="card-template-preview-open"]')),
                dirty_value: input?.value ?? null,
                horizontal_overflow: document.documentElement.scrollWidth > document.documentElement.clientWidth + 1,
            };
            delete window.__step5bRapidRootCount;
            delete window.__step5bRapidRootPeak;
            delete window.__step5bRapidRootObserver;
            delete window.__step5bRapidOriginalFetch;
            delete window.__step5bRapidRequestUrls;

            return result;
        }
        JS);

    expect($rapidResizeBack['viewport_width'])->toBe(1023)
        ->and($rapidResizeBack['peak_preview_roots'])->toBeLessThanOrEqual(1)
        ->and($rapidResizeBack['preview_roots'])->toBe(0)
        ->and($rapidResizeBack['adjacent_roots'])->toBe(0)
        ->and($rapidResizeBack['modal_roots'])->toBe(0)
        ->and($rapidResizeBack['livewire_requests'])->toBe(1)
        ->and($rapidResizeBack['opener_visible'])->toBeTrue()
        ->and($rapidResizeBack['focus_on_opener'])->toBeTrue(json_encode($rapidResizeBack, JSON_THROW_ON_ERROR))
        ->and($rapidResizeBack['dirty_value'])->toBe($dirty['value'])
        ->and($rapidResizeBack['horizontal_overflow'])->toBeFalse();

    $page->resize(1024, 800);
    $repeatWide = $page->script(<<<'JS'
        async () => {
            await new Promise((resolve) => setTimeout(resolve, 200));

            return {
                preview_roots: document.querySelectorAll('[data-card-template-preview-root]').length,
                adjacent_roots: document.querySelectorAll('[data-card-template-preview-adjacent]').length,
                modal_roots: document.querySelectorAll('[data-card-template-preview-modal]').length,
            };
        }
        JS);
    $page->resize(1023, 800);
    $repeatNarrow = $page->script(<<<'JS'
        async () => {
            await new Promise((resolve) => setTimeout(resolve, 200));

            return {
                preview_roots: document.querySelectorAll('[data-card-template-preview-root]').length,
                adjacent_roots: document.querySelectorAll('[data-card-template-preview-adjacent]').length,
                modal_roots: document.querySelectorAll('[data-card-template-preview-modal]').length,
            };
        }
        JS);

    expect($repeatWide)->toBe([
        'preview_roots' => 1,
        'adjacent_roots' => 1,
        'modal_roots' => 0,
    ])->and($repeatNarrow)->toBe([
        'preview_roots' => 0,
        'adjacent_roots' => 0,
        'modal_roots' => 0,
    ]);

    $resizeObserverErrors = $page->script(<<<'JS'
        () => {
            const knownMessage = 'ResizeObserver loop completed with undelivered notifications.';
            const errors = window.__pestBrowser.jsErrors ?? [];
            const messages = errors.map((error) => error.message);
            window.__pestBrowser.jsErrors = errors.filter((error) => error.message !== knownMessage);

            return {
                count: messages.filter((message) => message === knownMessage).length,
                unexpected: messages.filter((message) => message !== knownMessage),
            };
        }
        JS);
    expect($resizeObserverErrors['unexpected'])->toBe([]);

    if (getenv('STEP5B_BROWSER_REPORT') === '1') {
        fwrite(STDERR, json_encode([
            'wide' => $wide,
            'wide_matrix' => $wideMatrix,
            'refresh' => $refresh,
            'narrow_matrix' => $narrowMatrix,
            'narrow_to_wide' => $wideRestored,
            'wide_to_narrow' => $narrowRestored,
            'rapid_resize_back' => $rapidResizeBack,
            'repeat_cycle' => compact('repeatWide', 'repeatNarrow'),
            'resize_observer_errors' => $resizeObserverErrors,
            'listener_observation' => 'Repeated root-cycle behavior measured; listener enumeration is not exposed by the runner.',
            'heap_observation' => $wide['used_js_heap_size'] === null
                ? 'performance.memory unsupported by this browser runtime.'
                : 'performance.memory usedJSHeapSize recorded.',
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES).PHP_EOL);
    }

    $page->assertNoSmoke()->assertNoJavaScriptErrors();
});

it('renders the focused preview shell in English LTR', function (): void {
    app()->setLocale('en');
    $page = visit(EditCardTemplate::getUrl([
        'family' => 'content_item',
        'key' => 'preview_browser',
    ]))->resize(1023, 900);

    $narrow = $page->script(<<<'JS'
        async () => {
            await new Promise((resolve) => setTimeout(resolve, 200));
            const trigger = document.querySelector('[data-test="card-template-preview-open"]');
            trigger.focus();
            trigger.click();
            const started = performance.now();

            while (document.querySelector('[data-card-template-preview-modal]') === null && performance.now() - started < 5000) {
                await new Promise((resolve) => setTimeout(resolve, 25));
            }

            await new Promise((resolve) => setTimeout(resolve, 350));
            const modal = document.querySelector('[data-card-template-preview-modal]');
            const dialog = modal?.closest('[aria-modal="true"]');
            const modalRect = dialog?.querySelector('.fi-modal-window')?.getBoundingClientRect();

            return {
                viewport_width: window.innerWidth,
                preview_roots: document.querySelectorAll('[data-card-template-preview-root]').length,
                adjacent_roots: document.querySelectorAll('[data-card-template-preview-adjacent]').length,
                modal_roots: document.querySelectorAll('[data-card-template-preview-modal]').length,
                active_inside_modal: Boolean(dialog?.contains(document.activeElement)),
                modal_is_logical_end: (modalRect?.right ?? 0) >= window.innerWidth - 4,
                horizontal_overflow: document.documentElement.scrollWidth > document.documentElement.clientWidth + 1,
            };
        }
        JS);

    expect($narrow['viewport_width'])->toBe(1023)
        ->and($narrow['preview_roots'])->toBe(1)
        ->and($narrow['adjacent_roots'])->toBe(0)
        ->and($narrow['modal_roots'])->toBe(1)
        ->and($narrow['active_inside_modal'])->toBeTrue(json_encode($narrow, JSON_THROW_ON_ERROR))
        ->and($narrow['modal_is_logical_end'])->toBeTrue(json_encode($narrow, JSON_THROW_ON_ERROR))
        ->and($narrow['horizontal_overflow'])->toBeFalse();

    $escapeRestored = $page->script(<<<'JS'
        async () => {
            const started = performance.now();
            window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));

            while (document.querySelector('[data-card-template-preview-modal]') !== null && performance.now() - started < 5000) {
                await new Promise((resolve) => setTimeout(resolve, 25));
            }

            await new Promise((resolve) => setTimeout(resolve, 50));

            return document.querySelector('[data-card-template-preview-modal]') === null
                && Boolean(document.activeElement?.closest('[data-test="card-template-preview-open"]'));
        }
        JS);
    expect($escapeRestored)->toBeTrue();

    $page->resize(1024, 900);

    $geometry = $page->script(<<<'JS'
        async () => {
            await new Promise((resolve) => setTimeout(resolve, 250));
            const previewRect = document.querySelector('[data-card-template-preview-column]')?.getBoundingClientRect();
            const editorRect = document.querySelector('[data-card-template-editor-column]')?.getBoundingClientRect();
            const trigger = document.querySelector('[data-test="card-template-preview-open"]');

            return {
                viewport_width: window.innerWidth,
                preview_roots: document.querySelectorAll('[data-card-template-preview-root]').length,
                adjacent_roots: document.querySelectorAll('[data-card-template-preview-adjacent]').length,
                modal_roots: document.querySelectorAll('[data-card-template-preview-modal]').length,
                preview_is_logical_end: editorRect?.right <= previewRect?.left,
                columns_do_not_overlap: editorRect?.right <= previewRect?.left,
                editor_width: editorRect?.width ?? null,
                preview_width: previewRect?.width ?? null,
                opener_hidden: Boolean(trigger && (getComputedStyle(trigger).display === 'none' || trigger.offsetParent === null)),
                horizontal_overflow: document.documentElement.scrollWidth > document.documentElement.clientWidth + 1,
                header_metadata: Boolean(document.querySelector('.fi-header [data-card-template-import-lock-metadata]')),
            };
        }
        JS);

    $resizeObserverErrors = $page->script(<<<'JS'
        () => {
            const knownMessage = 'ResizeObserver loop completed with undelivered notifications.';
            const errors = window.__pestBrowser.jsErrors ?? [];
            const messages = errors.map((error) => error.message);
            window.__pestBrowser.jsErrors = errors.filter((error) => error.message !== knownMessage);

            return {
                count: messages.filter((message) => message === knownMessage).length,
                unexpected: messages.filter((message) => message !== knownMessage),
            };
        }
        JS);

    $page->assertScript('document.documentElement.dir', 'ltr')
        ->assertSee(__('admin.settings_sp3c.preview.title'))
        ->assertCount('[data-card-template-preview-root]', 1)
        ->assertNoSmoke()
        ->assertNoJavaScriptErrors();

    expect($geometry['viewport_width'])->toBe(1024)
        ->and($geometry['preview_roots'])->toBe(1)
        ->and($geometry['adjacent_roots'])->toBe(1)
        ->and($geometry['modal_roots'])->toBe(0)
        ->and($geometry['preview_is_logical_end'])->toBeTrue(json_encode($geometry, JSON_THROW_ON_ERROR))
        ->and($geometry['columns_do_not_overlap'])->toBeTrue(json_encode($geometry, JSON_THROW_ON_ERROR))
        ->and($geometry['editor_width'])->toBeGreaterThan(200)
        ->and($geometry['preview_width'])->toBeGreaterThan(250)
        ->and($geometry['opener_hidden'])->toBeTrue()
        ->and($geometry['horizontal_overflow'])->toBeFalse()
        ->and($geometry['header_metadata'])->toBeTrue()
        ->and($resizeObserverErrors['unexpected'])->toBe([]);
});

it('keeps card width and sample choice transient inside the compact preview controls', function (): void {
    app()->setLocale('en');
    $settingsBefore = DB::table('settings')
        ->where('group', PublicContentSettings::group())
        ->where('name', 'card_templates')
        ->value('payload');
    $page = visit(EditCardTemplate::getUrl([
        'family' => 'content_item',
        'key' => 'preview_browser',
    ]))->resize(1440, 900);

    $interaction = $page->script(<<<'JS'
        async () => {
            await new Promise((resolve) => setTimeout(resolve, 250));
            const width = document.querySelector('[data-test="card-template-preview-width"]');
            const plane = document.querySelector('[data-card-template-preview-width-plane]');
            const parentRect = plane?.parentElement?.getBoundingClientRect();
            const beforeRect = plane?.getBoundingClientRect();
            const title = plane?.querySelector('[data-card-part="title"]');
            const fontBefore = title ? getComputedStyle(title).fontSize : null;
            const widthSetter = Object.getOwnPropertyDescriptor(HTMLSelectElement.prototype, 'value').set;
            widthSetter.call(width, '60');
            width.dispatchEvent(new Event('change', { bubbles: true }));
            await new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(resolve)));
            const afterRect = plane?.getBoundingClientRect();
            const leftGap = afterRect && parentRect ? afterRect.left - parentRect.left : null;
            const rightGap = afterRect && parentRect ? parentRect.right - afterRect.right : null;
            const widthState = {
                value: width?.value,
                options: Array.from(width?.options ?? []).map((option) => option.value),
                ratio: beforeRect && afterRect ? afterRect.width / beforeRect.width : null,
                centered_delta: leftGap === null || rightGap === null ? null : Math.abs(leftGap - rightGap),
                zoom: plane ? getComputedStyle(plane).zoom : null,
                transform: plane ? getComputedStyle(plane).transform : null,
                font_unchanged: title ? getComputedStyle(title).fontSize === fontBefore : false,
            };

            const selectShell = document.querySelector('[data-card-template-preview-sample-select]');
            selectShell?.querySelector('.fi-select-input-btn')?.click();
            const searchInput = selectShell?.querySelector('.fi-select-input-search-ctn input');
            const setter = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value').set;
            setter.call(searchInput, 'Alternate Browser Sample');
            searchInput.dispatchEvent(new Event('input', { bubbles: true }));
            await new Promise((resolve) => setTimeout(resolve, 1200));

            const searchStarted = performance.now();
            let option = null;
            while (option === null && performance.now() - searchStarted < 5000) {
                option = Array.from(selectShell?.querySelectorAll('.fi-select-input-option') ?? [])
                    .find((candidate) => candidate.textContent.includes('Alternate Browser Sample')) ?? null;
                await new Promise((resolve) => setTimeout(resolve, 50));
            }
            option?.click();

            const selectionStarted = performance.now();
            while (! selectShell?.querySelector('.fi-select-input-value-label')?.textContent.includes('Alternate Browser Sample') && performance.now() - selectionStarted < 5000) {
                await new Promise((resolve) => setTimeout(resolve, 50));
            }

            while (! document.querySelector('[data-test="card-template-preview-ready"]')?.textContent.includes('Alternate Browser Sample') && performance.now() - selectionStarted < 5000) {
                await new Promise((resolve) => setTimeout(resolve, 50));
            }

            const controlsRow = document.querySelector('[data-card-template-preview-controls-row]');
            const controlsChildren = Array.from(controlsRow?.children ?? []).map((child) => child.getBoundingClientRect());
            const refresh = document.querySelector('[data-test="card-template-preview-refresh"]');
            const statusRow = document.querySelector('[data-card-template-preview-status-row]');
            const refreshed = document.querySelector('[data-test="card-template-preview-refreshed"]');
            const toggle = document.querySelector('[data-test="card-template-preview-controls-toggle"]');
            toggle?.click();
            await new Promise((resolve) => setTimeout(resolve, 500));
            const controls = document.querySelector('[data-card-template-preview-controls]');
            const ready = document.querySelector('[data-test="card-template-preview-ready"]');

            return {
                width: widthState,
                search_input_found: Boolean(searchInput),
                option_found: Boolean(option),
                selected_alternate: selectShell?.querySelector('.fi-select-input-value-label')?.textContent.includes('Alternate Browser Sample') ?? false,
                preview_updated: ready?.textContent.includes('Alternate Browser Sample') ?? false,
                controls_collapsed: toggle?.getAttribute('aria-expanded') === 'false'
                    && (getComputedStyle(controls).display === 'none' || controls.getBoundingClientRect().height <= 1),
                controls_display: getComputedStyle(controls).display,
                controls_height: controls.getBoundingClientRect().height,
                controls_expanded: toggle?.getAttribute('aria-expanded'),
                canvas_visible: getComputedStyle(ready).display !== 'none',
                preview_roots: document.querySelectorAll('[data-card-template-preview-root]').length,
                horizontal_overflow: document.documentElement.scrollWidth > document.documentElement.clientWidth + 1,
                controls_share_row: controlsChildren.length === 3
                    && Math.max(...controlsChildren.map((rect) => rect.top + (rect.height / 2)))
                        - Math.min(...controlsChildren.map((rect) => rect.top + (rect.height / 2))) < 4,
                refresh_icon_only: refresh?.textContent.trim() === '',
                refresh_label: refresh?.getAttribute('aria-label'),
                status_one_row: statusRow?.getBoundingClientRect().height < 24,
                refreshed_direction: refreshed?.dir,
            };
        }
        JS);

    expect($interaction['width']['value'])->toBe('60')
        ->and($interaction['width']['options'])->toBe(['100', '90', '80', '70', '60'])
        ->and($interaction['width']['ratio'])->toBeGreaterThan(0.59)
        ->and($interaction['width']['ratio'])->toBeLessThan(0.61)
        ->and($interaction['width']['centered_delta'])->toBeLessThan(2)
        ->and((float) $interaction['width']['zoom'])->toBe(1.0)
        ->and($interaction['width']['transform'])->toBe('none')
        ->and($interaction['width']['font_unchanged'])->toBeTrue()
        ->and($interaction['search_input_found'])->toBeTrue()
        ->and($interaction['option_found'])->toBeTrue(json_encode($interaction, JSON_THROW_ON_ERROR))
        ->and($interaction['selected_alternate'])->toBeTrue(json_encode($interaction, JSON_THROW_ON_ERROR))
        ->and($interaction['preview_updated'])->toBeTrue()
        ->and($interaction['controls_collapsed'])->toBeTrue(json_encode($interaction, JSON_THROW_ON_ERROR))
        ->and($interaction['canvas_visible'])->toBeTrue()
        ->and($interaction['preview_roots'])->toBe(1)
        ->and($interaction['horizontal_overflow'])->toBeFalse()
        ->and($interaction['controls_share_row'])->toBeTrue(json_encode($interaction, JSON_THROW_ON_ERROR))
        ->and($interaction['refresh_icon_only'])->toBeTrue()
        ->and($interaction['refresh_label'])->toBe(__('admin.settings_sp3c.preview.refresh'))
        ->and($interaction['status_one_row'])->toBeTrue()
        ->and($interaction['refreshed_direction'])->toBe('ltr');

    $page->refresh();
    $transient = $page->script(<<<'JS'
        async () => {
            await new Promise((resolve) => setTimeout(resolve, 300));

            return {
                sample_reset: ! document.querySelector('[data-card-template-preview-sample-select] .fi-select-input-value-label')?.textContent.includes('Alternate Browser Sample'),
                width_reset: document.querySelector('[data-test="card-template-preview-width"]')?.value === '100',
            };
        }
        JS);

    expect($transient['sample_reset'])->toBeTrue()
        ->and($transient['width_reset'])->toBeTrue()
        ->and(DB::table('settings')
            ->where('group', PublicContentSettings::group())
            ->where('name', 'card_templates')
            ->value('payload'))->toBe($settingsBefore);

    $page->assertNoSmoke()->assertNoJavaScriptErrors();
});

it('refreshes a changed template part and keeps the wide preview below the topbar', function (): void {
    app()->setLocale('he');
    $page = visit(EditCardTemplate::getUrl([
        'family' => 'content_item',
        'key' => 'preview_browser',
    ]))->resize(1440, 900);
    $cancelUrl = json_encode(CardTemplateSettings::getUrl(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    $layoutScript = str_replace('__CANCEL_URL__', $cancelUrl, <<<'JS'
        async () => {
            await new Promise((resolve) => setTimeout(resolve, 250));
            window.scrollTo({ top: 400, behavior: 'instant' });
            await new Promise((resolve) => setTimeout(resolve, 150));
            const topbar = document.querySelector('.fi-topbar');
            const shell = document.querySelector('[data-card-template-preview-wide-shell]');
            const cancelUrl = __CANCEL_URL__;
            const form = document.querySelector('form#form');
            const pageHeader = document.querySelector('.fi-header');

            return {
                scroll_y: window.scrollY,
                shell_top: shell?.getBoundingClientRect().top ?? null,
                topbar_bottom: topbar?.getBoundingClientRect().bottom ?? null,
                cancel_in_form: Boolean(form?.querySelector(`a[href="${cancelUrl}"]`)),
                cancel_in_header: Boolean(pageHeader?.querySelector(`a[href="${cancelUrl}"]`)),
                raw_cancel_key: document.body.innerText.includes('admin.actions.cancel'),
            };
        }
        JS);
    $layout = $page->script($layoutScript);

    expect($layout['scroll_y'])->toBeGreaterThan(0)
        ->and($layout['shell_top'])->toBeGreaterThanOrEqual($layout['topbar_bottom'])
        ->and($layout['cancel_in_form'])->toBeTrue()
        ->and($layout['cancel_in_header'])->toBeFalse()
        ->and($layout['raw_cancel_key'])->toBeFalse();

    $interaction = $page->script(<<<'JS'
        async () => {
            const summary = Array.from(document.querySelectorAll('[data-sp3c-part-summary]'))
                .find((candidate) => candidate.textContent.includes('STEP5B BROWSER PART BEFORE'));
            const item = summary?.closest('.fi-fo-builder-item');
            const edit = item?.querySelector('.fi-fo-builder-item-preview-edit-overlay');
            edit?.click();

            const started = performance.now();
            while (document.querySelector('.fi-modal.fi-modal-open') === null && performance.now() - started < 5000) {
                await new Promise((resolve) => setTimeout(resolve, 25));
            }
            await new Promise((resolve) => setTimeout(resolve, 350));

            let modal = document.querySelector('.fi-modal.fi-modal-open');
            const panel = modal?.querySelector('form.fi-modal-window');
            const preview = document.querySelector('[data-card-template-preview-wide-shell]');
            const panelRect = panel?.getBoundingClientRect();
            const previewRect = preview?.getBoundingClientRect();
            const previewCenter = previewRect
                ? { x: previewRect.left + (previewRect.width / 2), y: previewRect.top + Math.min(100, previewRect.height / 2) }
                : null;
            const maxGridColumns = Math.max(0, ...Array.from(panel?.querySelectorAll('.fi-grid') ?? [])
                .map((grid) => getComputedStyle(grid).gridTemplateColumns.split(' ').length));
            const hasTwoColumnGrid = Array.from(panel?.querySelectorAll('.fi-grid') ?? [])
                .some((grid) => getComputedStyle(grid).gridTemplateColumns.split(' ').length === 2);
            const slideOverAtLogicalStart = panelRect?.left >= previewRect?.right;
            const slideOverOverlapsPreview = ! (
                panelRect?.right <= previewRect?.left
                || panelRect?.left >= previewRect?.right
                || panelRect?.bottom <= previewRect?.top
                || panelRect?.top >= previewRect?.bottom
            );
            const previewInteractiveBehindOverlay = previewCenter
                ? preview.contains(document.elementFromPoint(previewCenter.x, previewCenter.y))
                : null;
            const activeInsideSlideOver = Boolean(panel?.contains(document.activeElement));
            const textInput = Array.from(modal?.querySelectorAll('input') ?? []).find((candidate) =>
                Array.from(candidate.attributes).some((attribute) =>
                    attribute.name.startsWith('wire:model') && attribute.value.endsWith('.text'),
                ),
            );
            const binding = Array.from(textInput?.attributes ?? [])
                .find((attribute) => attribute.name.startsWith('wire:model'))?.name ?? null;
            const setter = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value').set;
            setter.call(textInput, 'STEP5B BROWSER PART AFTER');
            textInput.dispatchEvent(new Event('input', { bubbles: true }));
            await new Promise((resolve) => setTimeout(resolve, 700));
            const previewUpdatedBeforeApply = document.querySelector('[data-test="card-template-preview-ready"]')
                ?.textContent.includes('STEP5B BROWSER PART AFTER') ?? false;

            performance.clearResourceTimings();
            const originalFetch = window.fetch;
            const requestUrls = [];
            window.fetch = (...arguments_) => {
                requestUrls.push(String(arguments_[0]?.url ?? arguments_[0]));

                return originalFetch(...arguments_);
            };
            modal = document.querySelector('.fi-modal.fi-modal-open');
            const submit = modal?.querySelector('form.fi-modal-window');
            submit?.requestSubmit();

            const submitted = performance.now();
            while (document.querySelector('.fi-modal.fi-modal-open') !== null && performance.now() - submitted < 5000) {
                await new Promise((resolve) => setTimeout(resolve, 25));
            }
            await new Promise((resolve) => setTimeout(resolve, 150));
            window.fetch = originalFetch;

            return {
                binding,
                edit_found: Boolean(edit),
                input_found: Boolean(textInput),
                submit_found: Boolean(submit),
                slide_over_start_class: modal?.classList.contains('fi-modal-slide-over-from-start') ?? false,
                slide_over_at_logical_start: slideOverAtLogicalStart,
                slide_over_overlaps_preview: slideOverOverlapsPreview,
                panel_left: panelRect?.left,
                panel_right: panelRect?.right,
                preview_left: previewRect?.left,
                preview_right: previewRect?.right,
                preview_visible_behind_overlay: Boolean(previewRect && previewRect.width > 0 && previewRect.height > 0),
                preview_interactive_behind_overlay: previewInteractiveBehindOverlay,
                active_inside_slide_over: activeInsideSlideOver,
                max_grid_columns: maxGridColumns,
                has_two_column_grid: hasTwoColumnGrid,
                preview_updated_before_apply: previewUpdatedBeforeApply,
                network_requests: requestUrls.filter((url) => url.includes('/livewire')).length,
                preview_updated: document.querySelector('[data-test="card-template-preview-ready"]')
                    ?.textContent.includes('STEP5B BROWSER PART AFTER') ?? false,
                preview_roots: document.querySelectorAll('[data-card-template-preview-root]').length,
            };
        }
        JS);

    expect($interaction['edit_found'])->toBeTrue(json_encode($interaction, JSON_THROW_ON_ERROR))
        ->and($interaction['input_found'])->toBeTrue(json_encode($interaction, JSON_THROW_ON_ERROR))
        ->and($interaction['submit_found'])->toBeTrue(json_encode($interaction, JSON_THROW_ON_ERROR))
        ->and($interaction['binding'])->toStartWith('wire:model')
        ->and($interaction['slide_over_start_class'])->toBeTrue()
        ->and($interaction['slide_over_at_logical_start'])->toBeTrue(json_encode($interaction, JSON_THROW_ON_ERROR))
        ->and($interaction['slide_over_overlaps_preview'])->toBeFalse(json_encode($interaction, JSON_THROW_ON_ERROR))
        ->and($interaction['preview_visible_behind_overlay'])->toBeTrue()
        ->and($interaction['preview_interactive_behind_overlay'])->toBeFalse()
        ->and($interaction['active_inside_slide_over'])->toBeTrue()
        ->and($interaction['max_grid_columns'])->toBeGreaterThanOrEqual(2)
        ->and($interaction['has_two_column_grid'])->toBeTrue()
        ->and($interaction['preview_updated_before_apply'])->toBeFalse()
        ->and($interaction['network_requests'])->toBe(1, json_encode($interaction, JSON_THROW_ON_ERROR))
        ->and($interaction['preview_updated'])->toBeTrue(json_encode($interaction, JSON_THROW_ON_ERROR))
        ->and($interaction['preview_roots'])->toBe(1);

    $page->assertNoSmoke()->assertNoJavaScriptErrors();
});

it('remembers inline Builder mode locally and live refreshes authoritative part state', function (): void {
    app()->setLocale('en');
    $settingsBefore = DB::table('settings')
        ->where('group', PublicContentSettings::group())
        ->where('name', 'card_templates')
        ->value('payload');
    $page = visit(EditCardTemplate::getUrl([
        'family' => 'content_item',
        'key' => 'preview_browser',
    ]))->resize(1440, 900);

    $selected = $page->script(<<<'JS'
        async () => {
            document.querySelector('[data-test="card-template-builder-mode-inline"]')?.click();
            const started = performance.now();

            while (document.querySelectorAll('[data-sp3c-part-summary]').length > 0 && performance.now() - started < 5000) {
                await new Promise((resolve) => setTimeout(resolve, 50));
            }

            return {
                remembered: window.localStorage.getItem('podtext.card-template-builder-display-mode'),
                inline_pressed: document.querySelector('[data-test="card-template-builder-mode-inline"]')?.getAttribute('aria-pressed'),
                summaries: document.querySelectorAll('[data-sp3c-part-summary]').length,
                modal_open: Boolean(document.querySelector('.fi-modal.fi-modal-open')),
            };
        }
        JS);

    expect($selected['remembered'])->toBe('inline')
        ->and($selected['inline_pressed'])->toBe('true')
        ->and($selected['summaries'])->toBe(0)
        ->and($selected['modal_open'])->toBeFalse();

    $page->refresh();
    $interaction = $page->script(<<<'JS'
        async () => {
            const restoreStarted = performance.now();
            while (document.querySelectorAll('[data-sp3c-part-summary]').length > 0 && performance.now() - restoreStarted < 5000) {
                await new Promise((resolve) => setTimeout(resolve, 50));
            }

            const findCustomItem = () => Array.from(document.querySelectorAll('[data-sp3c-template-parts] input'))
                .find((candidate) => candidate.value === 'STEP5B BROWSER PART BEFORE')
                ?.closest('.fi-fo-builder-item');
            let customItem = findCustomItem();
            const header = customItem?.querySelector('.fi-fo-builder-item-header');
            header?.click();
            await new Promise((resolve) => setTimeout(resolve, 50));
            const headerCollapsed = customItem?.classList.contains('fi-collapsed') ?? false;
            header?.click();
            await new Promise((resolve) => setTimeout(resolve, 50));
            const headerExpanded = ! (customItem?.classList.contains('fi-collapsed') ?? true);
            customItem?.querySelector('[data-test="card-template-part-move"]')?.click();

            const modalStarted = performance.now();
            while (document.querySelector('.fi-modal.fi-modal-open') === null && performance.now() - modalStarted < 5000) {
                await new Promise((resolve) => setTimeout(resolve, 25));
            }
            const moveModalOpened = document.querySelector('.fi-modal.fi-modal-open') !== null;
            const actionKeptExpanded = ! (customItem?.classList.contains('fi-collapsed') ?? true);
            window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
            await new Promise((resolve) => setTimeout(resolve, 300));

            customItem = findCustomItem();
            let showLabel = customItem?.querySelector('[data-test="card-template-part-show-label"]');
            showLabel?.click();
            const labelStarted = performance.now();
            let labelInput = null;
            while (labelInput === null && performance.now() - labelStarted < 5000) {
                customItem = findCustomItem();
                labelInput = Array.from(customItem?.querySelectorAll('input') ?? []).find((candidate) =>
                    Array.from(candidate.attributes).some((attribute) =>
                        attribute.name.startsWith('wire:model') && attribute.value.endsWith('.label'),
                    ),
                ) ?? null;
                await new Promise((resolve) => setTimeout(resolve, 50));
            }
            customItem = findCustomItem();
            showLabel = customItem?.querySelector('[data-test="card-template-part-show-label"]');
            showLabel?.click();
            await new Promise((resolve) => setTimeout(resolve, 500));
            customItem = findCustomItem();
            const toggleReachableAfterOff = Boolean(customItem?.querySelector('[data-test="card-template-part-show-label"]'));
            const labelHiddenAfterOff = ! Array.from(customItem?.querySelectorAll('input') ?? []).some((candidate) =>
                Array.from(candidate.attributes).some((attribute) =>
                    attribute.name.startsWith('wire:model') && attribute.value.endsWith('.label'),
                ),
            );

            const textInput = Array.from(document.querySelectorAll('[data-sp3c-template-parts] input'))
                .find((candidate) => candidate.value === 'STEP5B BROWSER PART BEFORE');
            const binding = Array.from(textInput?.attributes ?? [])
                .find((attribute) => attribute.name.startsWith('wire:model'))?.name ?? null;
            performance.clearResourceTimings();
            const originalFetch = window.fetch;
            const requestUrls = [];
            window.fetch = (...arguments_) => {
                requestUrls.push(String(arguments_[0]?.url ?? arguments_[0]));

                return originalFetch(...arguments_);
            };
            const setter = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value').set;
            setter.call(textInput, 'STEP5B INLINE PART AFTER');
            textInput.dispatchEvent(new Event('input', { bubbles: true }));

            const updateStarted = performance.now();
            while (! document.querySelector('[data-test="card-template-preview-ready"]')?.textContent.includes('STEP5B INLINE PART AFTER') && performance.now() - updateStarted < 5000) {
                await new Promise((resolve) => setTimeout(resolve, 50));
            }
            window.fetch = originalFetch;

            return {
                restored_inline: document.querySelector('[data-test="card-template-builder-mode-inline"]')?.getAttribute('aria-pressed'),
                summaries: document.querySelectorAll('[data-sp3c-part-summary]').length,
                header_collapsed: headerCollapsed,
                header_expanded: headerExpanded,
                move_modal_opened: moveModalOpened,
                action_kept_expanded: actionKeptExpanded,
                label_input_revealed: Boolean(labelInput),
                toggle_reachable_after_off: toggleReachableAfterOff,
                label_hidden_after_off: labelHiddenAfterOff,
                input_found: Boolean(textInput),
                binding,
                network_requests: requestUrls.filter((url) => url.includes('/livewire')).length,
                preview_updated: document.querySelector('[data-test="card-template-preview-ready"]')
                    ?.textContent.includes('STEP5B INLINE PART AFTER') ?? false,
                modal_open: Boolean(document.querySelector('.fi-modal.fi-modal-open')),
                remembered: window.localStorage.getItem('podtext.card-template-builder-display-mode'),
            };
        }
        JS);

    expect($interaction['restored_inline'])->toBe('true')
        ->and($interaction['summaries'])->toBe(0)
        ->and($interaction['header_collapsed'])->toBeTrue(json_encode($interaction, JSON_THROW_ON_ERROR))
        ->and($interaction['header_expanded'])->toBeTrue(json_encode($interaction, JSON_THROW_ON_ERROR))
        ->and($interaction['move_modal_opened'])->toBeTrue(json_encode($interaction, JSON_THROW_ON_ERROR))
        ->and($interaction['action_kept_expanded'])->toBeTrue(json_encode($interaction, JSON_THROW_ON_ERROR))
        ->and($interaction['label_input_revealed'])->toBeTrue(json_encode($interaction, JSON_THROW_ON_ERROR))
        ->and($interaction['toggle_reachable_after_off'])->toBeTrue(json_encode($interaction, JSON_THROW_ON_ERROR))
        ->and($interaction['label_hidden_after_off'])->toBeTrue(json_encode($interaction, JSON_THROW_ON_ERROR))
        ->and($interaction['input_found'])->toBeTrue(json_encode($interaction, JSON_THROW_ON_ERROR))
        ->and($interaction['binding'])->toStartWith('wire:model.live')
        ->and($interaction['network_requests'])->toBe(1, json_encode($interaction, JSON_THROW_ON_ERROR))
        ->and($interaction['preview_updated'])->toBeTrue(json_encode($interaction, JSON_THROW_ON_ERROR))
        ->and($interaction['modal_open'])->toBeFalse()
        ->and($interaction['remembered'])->toBe('inline')
        ->and(DB::table('settings')
            ->where('group', PublicContentSettings::group())
            ->where('name', 'card_templates')
            ->value('payload'))->toBe($settingsBefore);

    $page->assertNoSmoke()->assertNoJavaScriptErrors();
});

it('renders an image at the position selected through the native move modal', function (): void {
    app()->setLocale('en');
    $settingsBefore = DB::table('settings')
        ->where('group', PublicContentSettings::group())
        ->where('name', 'card_templates')
        ->value('payload');
    $page = visit(EditCardTemplate::getUrl([
        'family' => 'content_item',
        'key' => 'preview_browser',
    ]))->resize(1440, 900);

    $result = $page->script(<<<'JS'
        async () => {
            document.querySelector('[data-test="card-template-builder-mode-inline"]')?.click();
            const inlineStarted = performance.now();

            while (document.querySelectorAll('[data-sp3c-part-summary]').length > 0 && performance.now() - inlineStarted < 5000) {
                await new Promise((resolve) => setTimeout(resolve, 50));
            }

            const imageItem = Array.from(document.querySelectorAll('[data-sp3c-template-parts] .fi-fo-builder-item'))
                .find((item) => item.querySelector('[data-sp3c-part-heading]')?.textContent.includes('Image'));
            imageItem?.querySelector('[data-test="card-template-part-move"]')?.click();

            const modalStarted = performance.now();
            while (document.querySelector('.fi-modal.fi-modal-open') === null && performance.now() - modalStarted < 5000) {
                await new Promise((resolve) => setTimeout(resolve, 25));
            }

            const modal = document.querySelector('.fi-modal.fi-modal-open');
            const input = modal?.querySelector('input');
            const setter = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value').set;
            input?.focus();

            if (input) {
                setter.call(input, '10');
                input.dispatchEvent(new Event('input', { bubbles: true }));
            }

            modal?.querySelector('form.fi-modal-window')?.requestSubmit();

            const movedStarted = performance.now();
            while (
                document.querySelector('.fi-modal.fi-modal-open') !== null
                && performance.now() - movedStarted < 5000
            ) {
                await new Promise((resolve) => setTimeout(resolve, 25));
            }

            const previewStarted = performance.now();
            let ready = document.querySelector('[data-test="card-template-preview-ready"]');
            while (ready?.querySelector('[data-card-part-flow="ordered-stack"]') === null && performance.now() - previewStarted < 5000) {
                await new Promise((resolve) => setTimeout(resolve, 50));
                ready = document.querySelector('[data-test="card-template-preview-ready"]');
            }

            const image = ready?.querySelector('[data-card-part="image"]');
            const title = ready?.querySelector('[data-card-part="title"]');
            const custom = Array.from(ready?.querySelectorAll('[data-card-part="custom_text"]') ?? [])
                .find((part) => part.textContent.includes('STEP5B BROWSER PART BEFORE'));
            const movedImageItem = Array.from(document.querySelectorAll('[data-sp3c-template-parts] .fi-fo-builder-item'))
                .find((item) => item.querySelector('[data-sp3c-part-heading]')?.textContent.includes('Image'));

            return {
                image_item_found: Boolean(imageItem),
                modal_found: Boolean(modal),
                input_found: Boolean(input),
                ordered_stack: ready?.querySelector('[data-card-part-flow="ordered-stack"]') !== null,
                title_before_image: Boolean(title && image && (title.compareDocumentPosition(image) & Node.DOCUMENT_POSITION_FOLLOWING)),
                custom_before_image: Boolean(custom && image && (custom.compareDocumentPosition(image) & Node.DOCUMENT_POSITION_FOLLOWING)),
                image_position_badge: movedImageItem?.querySelector('[data-sp3c-part-position-badge]')?.textContent.trim() ?? null,
                modal_open: Boolean(document.querySelector('.fi-modal.fi-modal-open')),
            };
        }
        JS);

    expect($result['image_item_found'])->toBeTrue(json_encode($result, JSON_THROW_ON_ERROR))
        ->and($result['modal_found'])->toBeTrue(json_encode($result, JSON_THROW_ON_ERROR))
        ->and($result['input_found'])->toBeTrue(json_encode($result, JSON_THROW_ON_ERROR))
        ->and($result['ordered_stack'])->toBeTrue(json_encode($result, JSON_THROW_ON_ERROR))
        ->and($result['title_before_image'])->toBeTrue(json_encode($result, JSON_THROW_ON_ERROR))
        ->and($result['custom_before_image'])->toBeTrue(json_encode($result, JSON_THROW_ON_ERROR))
        ->and($result['image_position_badge'])->toBe('10')
        ->and($result['modal_open'])->toBeFalse()
        ->and(DB::table('settings')
            ->where('group', PublicContentSettings::group())
            ->where('name', 'card_templates')
            ->value('payload'))->toBe($settingsBefore);

    $page->assertNoSmoke()->assertNoJavaScriptErrors();
});
