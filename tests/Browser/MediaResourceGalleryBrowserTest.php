<?php

use App\Filament\Resources\Media\MediaResource;
use App\Models\Media;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Http::preventStrayRequests();
    Storage::fake('local');
    Storage::fake('public');
    Storage::fake('public_assets');
    $this->actingAs(User::factory()->admin()->create());
});

it('renders the Media inventory as responsive accessible native cards', function (
    string $locale,
    string $direction,
): void {
    app()->setLocale($locale);

    foreach (range(1, 6) as $index) {
        $name = "browser-card-{$index}";
        $path = "content-groups/covers/{$name}.jpg";
        $media = Media::factory()->create([
            'reference_key' => (string) Str::ulid(),
            'disk' => 'public',
            'directory' => 'content-groups/covers',
            'visibility' => 'public',
            'name' => $name,
            'path' => $path,
            'width' => 640,
            'height' => 360,
            'size' => 2048 + $index,
            'type' => 'image/jpeg',
            'ext' => 'jpg',
            'title' => "Browser card {$index}",
            'exif' => ['original_filename' => "Original browser card {$index}.jpg"],
        ]);

        if ($index !== 6) {
            Storage::disk('public')->put(
                $media->path,
                base64_decode(
                    (string) file_get_contents(base_path('tests/Fixtures/media/valid.jpg.base64')),
                    true,
                ),
            );
        }
    }

    $page = visit(MediaResource::getUrl('index'))->resize(1280, 900);
    $needsAttention = json_encode(__('admin.media_library.needs_attention'), JSON_THROW_ON_ERROR);
    $missingFile = json_encode(__('admin.media_library.repair_missing_file'), JSON_THROW_ON_ERROR);
    $openDetails = json_encode(__('admin.media_library.open_details'), JSON_THROW_ON_ERROR);
    $moreActions = json_encode(__('admin.media_library.more_actions'), JSON_THROW_ON_ERROR);
    $menuLabels = json_encode([
        __('admin.actions.view'),
        __('admin.actions.download'),
        __('admin.media_library.rename'),
        __('admin.media_library.swap'),
        __('admin.media_library.delete_permanently'),
    ], JSON_THROW_ON_ERROR);
    $wide = $page->script(<<<JS
        async () => {
            const waitFor = async (callback, timeout = 7000) => {
                const started = performance.now();

                while (performance.now() - started < timeout) {
                    const result = callback();

                    if (result) {
                        return result;
                    }

                    await new Promise((resolve) => setTimeout(resolve, 25));
                }

                throw new Error('Timed out waiting for Media gallery cards.');
            };
            const cards = await waitFor(() => {
                const candidates = Array.from(document.querySelectorAll(
                    '[data-testid="media-library-card"]',
                ));

                return candidates.length === 6 ? candidates : null;
            });
            const firstRowTop = cards[0].getBoundingClientRect().top;
            const desktopColumns = new Set(
                cards
                    .filter((card) => Math.abs(card.getBoundingClientRect().top - firstRowTop) <= 2)
                    .map((card) => Math.round(card.getBoundingClientRect().left)),
            ).size;
            const images = Array.from(document.querySelectorAll(
                '[data-testid="media-library-card-image"]',
            ));
            const missingCard = cards.find((card) => card.textContent.includes(
                {$missingFile},
            ));
            const healthyCard = cards.find((card) => ! card.textContent.includes(
                {$needsAttention},
            ));
            const records = cards.map((card) => card.closest('.fi-ta-record'));
            const findActionGroupTrigger = (record) => Array.from(
                record?.querySelectorAll('button[aria-label]') ?? [],
            ).find((button) => button.getAttribute('aria-label') === {$moreActions});
            const actionGroupTriggers = records.map(findActionGroupTrigger);
            findActionGroupTrigger(healthyCard?.closest('.fi-ta-record'))?.dispatchEvent(
                new MouseEvent('mousedown', {
                    bubbles: true,
                    button: 0,
                }),
            );
            const expectedMenuLabels = {$menuLabels};
            const openMenu = await waitFor(() => Array.from(document.querySelectorAll(
                '.fi-dropdown-panel',
            )).find((panel) => {
                const styles = getComputedStyle(panel);
                const text = panel.textContent;

                return panel.getClientRects().length > 0
                    && styles.display !== 'none'
                    && styles.visibility !== 'hidden'
                    && expectedMenuLabels.every((label) => text.includes(label));
            }));

            return {
                direction: document.documentElement.dir,
                card_count: cards.length,
                desktop_columns: desktopColumns,
                metadata_visible: cards.every((card) =>
                    card.querySelector('[data-testid="media-library-card-stored-filename"]')
                    && card.querySelector('[data-testid="media-library-card-file-summary"]')
                ),
                known_references_visible: cards.every((card) => Boolean(
                    card.querySelector('[data-testid="media-library-card-known-references"]'),
                )),
                image_object_fits: images.map((image) => getComputedStyle(image).objectFit),
                lazy_images: images.every((image) => image.getAttribute('loading') === 'lazy'),
                needs_attention_visible: Boolean(missingCard)
                    && missingCard.textContent.includes({$needsAttention}),
                primary_issue_persistent: Boolean(
                    missingCard?.querySelector('[data-testid="media-library-card-primary-issue"]'),
                ),
                details_visible: records.every((record) => Array.from(
                    record?.querySelectorAll('.fi-ta-actions a') ?? [],
                ).some((action) => action.textContent.trim() === {$openDetails}
                    && action.getClientRects().length > 0)),
                action_group_accessible: actionGroupTriggers.every(Boolean),
                action_menu_labels_visible: expectedMenuLabels.every(
                    (label) => openMenu.textContent.includes(label),
                ),
                healthy_card_found: Boolean(healthyCard),
                bulk_selection_available: Boolean(document.querySelector(
                    '.fi-ta input[type="checkbox"]',
                )),
                horizontal_overflow: document.documentElement.scrollWidth
                    > document.documentElement.clientWidth + 1,
            };
        }
        JS);

    expect($wide['direction'])->toBe($direction)
        ->and($wide['card_count'])->toBe(6)
        ->and($wide['desktop_columns'])->toBe(3, json_encode($wide, JSON_THROW_ON_ERROR))
        ->and($wide['metadata_visible'])->toBeTrue()
        ->and($wide['known_references_visible'])->toBeTrue()
        ->and($wide['image_object_fits'])->each->toBe('contain')
        ->and($wide['lazy_images'])->toBeTrue()
        ->and($wide['needs_attention_visible'])->toBeTrue()
        ->and($wide['primary_issue_persistent'])->toBeTrue()
        ->and($wide['details_visible'])->toBeTrue()
        ->and($wide['action_group_accessible'])->toBeTrue()
        ->and($wide['action_menu_labels_visible'])->toBeTrue()
        ->and($wide['healthy_card_found'])->toBeTrue()
        ->and($wide['bulk_selection_available'])->toBeTrue()
        ->and($wide['horizontal_overflow'])->toBeFalse();

    $page->resize(390, 844);
    $narrow = $page->script(<<<'JS'
        async () => {
            await new Promise((resolve) => setTimeout(resolve, 250));
            const cards = Array.from(document.querySelectorAll(
                '[data-testid="media-library-card"]',
            ));
            const lefts = new Set(cards.map((card) => Math.round(
                card.getBoundingClientRect().left,
            )));
            const viewportWidth = window.innerWidth;

            return {
                viewport_width: viewportWidth,
                direction: document.documentElement.dir,
                card_count: cards.length,
                columns: lefts.size,
                every_card_within_viewport: cards.every((card) => {
                    const rect = card.getBoundingClientRect();

                    return rect.left >= -1 && rect.right <= viewportWidth + 1;
                }),
                metadata_visible: cards.every((card) => {
                    const metadata = card.querySelector(
                        '[data-testid="media-library-card-file-summary"]',
                    );

                    return metadata && metadata.getClientRects().length > 0;
                }),
                horizontal_overflow: document.documentElement.scrollWidth
                    > document.documentElement.clientWidth + 1,
            };
        }
        JS);

    expect($narrow['viewport_width'])->toBe(390)
        ->and($narrow['direction'])->toBe($direction)
        ->and($narrow['card_count'])->toBe(6)
        ->and($narrow['columns'])->toBe(1, json_encode($narrow, JSON_THROW_ON_ERROR))
        ->and($narrow['every_card_within_viewport'])->toBeTrue()
        ->and($narrow['metadata_visible'])->toBeTrue()
        ->and($narrow['horizontal_overflow'])->toBeFalse();

    $page->assertNoJavaScriptErrors();
})->with([
    'Hebrew RTL' => ['he', 'rtl'],
    'English LTR' => ['en', 'ltr'],
]);

it('keeps canonical Media task context keyboard reachable across Edit and safe fallback', function (
    string $locale,
    string $direction,
): void {
    app()->setLocale($locale);
    $records = collect(range(1, 26))->map(function (int $index): Media {
        $name = "browser-focus-{$index}";

        return Media::factory()->create([
            'reference_key' => (string) Str::ulid(),
            'disk' => 'public',
            'directory' => 'content-groups/covers',
            'visibility' => 'public',
            'name' => $name,
            'path' => "content-groups/covers/{$name}.jpg",
            'width' => 640,
            'height' => 360,
            'size' => 2048 + $index,
            'type' => 'image/jpeg',
            'ext' => 'jpg',
            'title' => "Browser focus {$index}",
            'created_at' => now()->subSeconds(27 - $index),
        ]);
    });
    $origin = $records->last();
    assert($origin instanceof Media);
    $indexUrl = MediaResource::getUrl('index', [
        'tab' => 'needs_attention',
        'filters' => [
            'type' => ['value' => 'image/jpeg'],
            'reason' => ['value' => 'missing_file'],
        ],
        'search' => 'Browser focus',
        'sort' => 'created_at:asc',
        'page' => 2,
        'focus' => $origin->getKey(),
    ]).'#media-record-'.$origin->getKey();
    $page = visit($indexUrl)->resize(1280, 900);
    $taskLabels = json_encode([
        __('admin.media_library.tasks.all'),
        __('admin.media_library.tasks.in_use'),
        __('admin.media_library.tasks.no_direct_attachment'),
        __('admin.media_library.tasks.needs_attention'),
        __('admin.media_library.tasks.recent'),
    ], JSON_THROW_ON_ERROR);
    $needsAttention = json_encode(
        __('admin.media_library.tasks.needs_attention'),
        JSON_THROW_ON_ERROR,
    );
    $missingFile = json_encode(
        __('admin.media_library.repair_missing_file'),
        JSON_THROW_ON_ERROR,
    );
    $initial = $page->script(<<<JS
        async () => {
            const waitFor = async (callback, timeout = 7000) => {
                const started = performance.now();

                while (performance.now() - started < timeout) {
                    const result = callback();

                    if (result) {
                        return result;
                    }

                    await new Promise((resolve) => setTimeout(resolve, 25));
                }

                throw new Error('Timed out waiting for the canonical Media task view.');
            };
            const cards = await waitFor(() => {
                const matches = Array.from(document.querySelectorAll(
                    '[data-testid="media-library-card"]',
                ));

                return matches.length === 1 ? matches : null;
            });
            await new Promise((resolve) => requestAnimationFrame(() => resolve()));
            const tabs = Array.from(document.querySelectorAll('.fi-tabs-item'));
            const indicators = document.querySelector('.fi-ta-filter-indicators');
            const sortSelects = Array.from(document.querySelectorAll(
                '.fi-ta-sorting-settings select',
            ));
            const accessibleName = (element) => (
                element?.getAttribute('aria-label')
                || element?.textContent
                || element?.closest('label')?.textContent
                || ''
            ).trim();
            const expectedTasks = {$taskLabels};

            return {
                direction: document.documentElement.dir,
                tasks: tabs.map((tab) => tab.textContent.replace(/\s+/g, ' ').trim()),
                all_task_labels_visible: expectedTasks.every((label) =>
                    tabs.some((tab) => tab.textContent.includes(label))
                ),
                selected_task: tabs.find((tab) => tab.classList.contains('fi-active'))
                    ?.textContent.replace(/\s+/g, ' ').trim(),
                description: document.querySelector('.fi-ta-header-description')?.textContent.trim(),
                indicator_text: indicators?.textContent.replace(/\s+/g, ' ').trim(),
                reason_visible: indicators?.textContent.includes({$missingFile}),
                sort_values: sortSelects.map((select) => select.value),
                sort_accessible: sortSelects.every((select) =>
                    accessibleName(select.closest('label')).length > 0
                ),
                filter_remove_accessible: Array.from(
                    indicators?.querySelectorAll('button') ?? [],
                ).every((button) => accessibleName(button).length > 0),
                focused_id: document.activeElement?.id ?? null,
                hash: window.location.hash,
                tab: new URL(window.location.href).searchParams.get('tab'),
                page: new URL(window.location.href).searchParams.get('page'),
                card_count: cards.length,
                horizontal_overflow: document.documentElement.scrollWidth
                    > document.documentElement.clientWidth + 1,
            };
        }
        JS);

    expect($initial['direction'])->toBe($direction)
        ->and($initial['all_task_labels_visible'])->toBeTrue()
        ->and($initial['selected_task'])->toContain(__('admin.media_library.tasks.needs_attention'))
        ->and($initial['description'])->toBe(__('admin.media_library.task_descriptions.needs_attention'))
        ->and($initial['reason_visible'])->toBeTrue()
        ->and($initial['indicator_text'])->toContain('image/jpeg')
        ->and($initial['sort_values'])->toBe(['created_at', 'asc'])
        ->and($initial['sort_accessible'])->toBeTrue()
        ->and($initial['filter_remove_accessible'])->toBeTrue()
        ->and($initial['focused_id'])->toBe('media-record-'.$origin->getKey())
        ->and($initial['hash'])->toBe('#media-record-'.$origin->getKey())
        ->and($initial['tab'])->toBe('needs_attention')
        ->and($initial['page'])->toBe('2')
        ->and($initial['card_count'])->toBe(1)
        ->and($initial['horizontal_overflow'])->toBeFalse();

    $page
        ->click(__('admin.media_library.open_details'))
        ->wait(0.25)
        ->assertSee(__('admin.media_library.back_to_media_library'));
    $editAccessibility = $page->script(<<<'JS'
        () => {
            const actions = Array.from(document.querySelectorAll('a, button'));
            const visible = (element) => element.getClientRects().length > 0;
            const names = actions
                .filter(visible)
                .map((element) => (
                    element.getAttribute('aria-label')
                    || element.textContent
                    || ''
                ).trim())
                .filter(Boolean);

            return {
                path: window.location.pathname,
                named_actions: names,
            };
        }
        JS);

    expect($editAccessibility['path'])->toContain('/admin/media/'.$origin->getKey().'/edit')
        ->and($editAccessibility['named_actions'])->toContain(
            __('admin.media_library.back_to_media_library'),
            __('filament-panels::resources/pages/edit-record.form.actions.cancel.label'),
        );

    $page
        ->click(__('admin.media_library.back_to_media_library'))
        ->wait(0.35);
    $backState = $page->script(<<<'JS'
        () => ({
            path: window.location.pathname,
            query: window.location.search,
            hash: window.location.hash,
            focused_id: document.activeElement?.id ?? null,
        })
        JS);

    expect($backState['path'])->toBe('/admin/media')
        ->and($backState['query'])->toContain('tab=needs_attention')
        ->and($backState['query'])->toContain('sort=created_at%3Aasc')
        ->and($backState['query'])->toContain('page=2')
        ->and($backState['hash'])->toBe('#media-record-'.$origin->getKey())
        ->and($backState['focused_id'])->toBe('media-record-'.$origin->getKey());

    $page
        ->click(__('admin.media_library.open_details'))
        ->wait(0.25)
        ->click(__('filament-panels::resources/pages/edit-record.form.actions.cancel.label'))
        ->wait(0.35);
    $cancelState = $page->script(<<<'JS'
        () => ({
            path: window.location.pathname,
            hash: window.location.hash,
            focused_id: document.activeElement?.id ?? null,
        })
        JS);

    expect($cancelState)->toBe([
        'path' => '/admin/media',
        'hash' => '#media-record-'.$origin->getKey(),
        'focused_id' => 'media-record-'.$origin->getKey(),
    ]);

    $page
        ->click(__('admin.media_library.open_details'))
        ->wait(0.25);
    DB::table($origin->getTable())
        ->where($origin->getKeyName(), $origin->getKey())
        ->update(['type' => 'image/png', 'ext' => 'png']);
    $page
        ->click(__('admin.media_library.back_to_media_library'))
        ->wait(0.35)
        ->assertSee(__('admin.media_library.empty_view_heading'))
        ->assertSee(__('admin.media_library.reset_view'));
    $missingFocus = $page->script(<<<'JS'
        () => ({
            tab: new URL(window.location.href).searchParams.get('tab'),
            mime: new URL(window.location.href).searchParams.get('filters[type][value]'),
            reason: new URL(window.location.href).searchParams.get('filters[reason][value]'),
            search: new URL(window.location.href).searchParams.get('search'),
            sort: new URL(window.location.href).searchParams.get('sort'),
            focused_id: document.activeElement?.id ?? null,
            origin_present: Boolean(document.querySelector(location.hash)),
        })
        JS);

    expect($missingFocus['tab'])->toBe('needs_attention')
        ->and($missingFocus['mime'])->toBe('image/jpeg')
        ->and($missingFocus['reason'])->toBe('missing_file')
        ->and($missingFocus['search'])->toBe('Browser focus')
        ->and($missingFocus['sort'])->toBe('created_at:asc')
        ->and($missingFocus['origin_present'])->toBeFalse()
        ->and($missingFocus['focused_id'])->not->toBe('media-record-'.$origin->getKey());

    $page
        ->click(__('admin.media_library.reset_view'))
        ->wait(0.35);
    $resetState = $page->script(<<<'JS'
        () => {
            const tabs = Array.from(document.querySelectorAll('.fi-tabs-item'));

            return {
                query: window.location.search,
                hash: window.location.hash,
                selected_task: tabs.find((tab) => tab.classList.contains('fi-active'))
                    ?.textContent.replace(/\s+/g, ' ').trim(),
                default_sort_label: document.querySelector(
                    '.fi-ta-sorting-settings select option:checked',
                )?.textContent.replace(/\s+/g, ' ').trim(),
            };
        }
        JS);

    expect($resetState['query'])->toBe('')
        ->and($resetState['hash'])->toBe('')
        ->and($resetState['selected_task'])->toContain(__('admin.media_library.tasks.all'))
        ->and($resetState['default_sort_label'])
        ->toBe(__('admin.media_library.added_newest_first'));

    $page->script(<<<'JS'
        () => {
            document.querySelector('.fi-tabs-item')?.focus();
        }
        JS);
    $page->keys('.fi-tabs-item:first-of-type', ['Tab']);
    $keyboardState = $page->script(<<<'JS'
        () => ({
            label: document.activeElement?.textContent.replace(/\s+/g, ' ').trim(),
            tag: document.activeElement?.tagName,
        })
        JS);

    expect($keyboardState['tag'])->toBe('BUTTON')
        ->and($keyboardState['label'])->toContain(__('admin.media_library.tasks.in_use'));

    $page->keys('.fi-tabs-item:nth-of-type(2)', ['Enter'])->wait(0.35);
    $activatedTask = $page->script(<<<'JS'
        () => ({
            tab: new URL(window.location.href).searchParams.get('tab'),
            selected: Array.from(document.querySelectorAll('.fi-tabs-item'))
                .find((tab) => tab.classList.contains('fi-active'))
                ?.textContent.replace(/\s+/g, ' ').trim(),
        })
        JS);

    expect($activatedTask['tab'])->toBe('in_use')
        ->and($activatedTask['selected'])->toContain(__('admin.media_library.tasks.in_use'));

    $page->assertNoJavaScriptErrors();
})->with([
    'Hebrew canonical context' => ['he', 'rtl'],
    'English canonical context' => ['en', 'ltr'],
]);
