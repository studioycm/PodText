<?php

use App\Enums\MediaAttachmentRole;
use App\Filament\Resources\Media\MediaResource;
use App\Models\ContentGroup;
use App\Models\Media;
use App\Models\MediaAttachment;
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
    $openDetails = json_encode(__('admin.media_library.open_edit_page'), JSON_THROW_ON_ERROR);
    $moreActions = json_encode(__('admin.media_library.more_actions'), JSON_THROW_ON_ERROR);
    $menuLabels = json_encode([
        __('admin.actions.view'),
        __('admin.actions.download'),
        __('admin.media_library.rename'),
        __('admin.media_library.swap'),
        __('admin.media_library.delete_permanently'),
    ], JSON_THROW_ON_ERROR);
    $wide = $page->page()->evaluate(<<<JS
        async () => {
            // stable-read (R4): a layout value counts only when two consecutive
            // animation-frame reads agree (cap 10 frames) — a mid-reflow transient
            // can no longer become an assertion input.
            const stableRead = async (fn) => {
                // 250ms fallback: an occluded renderer must degrade to last-read, never hang (S1b review).
                const frame = () => new Promise((resolve) => {
                    requestAnimationFrame(resolve);
                    setTimeout(resolve, 250);
                });
                let previous = fn();
                for (let i = 0; i < 10; i++) {
                    await frame();
                    const current = fn();
                    if (JSON.stringify(current) === JSON.stringify(previous)) return current;
                    previous = current;
                }
                return previous;
            };
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
            const healthyCard = cards.find((card) => ! card.closest('.fi-ta-record').textContent.includes(
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
                    && missingCard.closest('.fi-ta-record').textContent.includes({$needsAttention}),
                primary_issue_persistent: Boolean(
                    missingCard?.querySelector('[data-testid="media-library-card-primary-issue"]'),
                ),
                details_visible: records.every((record) => Array.from(
                    record?.querySelectorAll('.fi-ta-actions a') ?? [],
                ).some((action) => action.getAttribute('aria-label') === {$openDetails}
                    && action.getClientRects().length > 0)),
                action_group_accessible: actionGroupTriggers.every(Boolean),
                action_menu_labels_visible: expectedMenuLabels.every(
                    (label) => openMenu.textContent.includes(label),
                ),
                healthy_card_found: Boolean(healthyCard),
                bulk_selection_available: Boolean(document.querySelector(
                    '.fi-ta input[type="checkbox"]',
                )),
                horizontal_overflow: await stableRead(() => document.documentElement.scrollWidth
                    > document.documentElement.clientWidth + 1),
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
    $narrow = $page->page()->evaluate(<<<JS
        async () => {
            // stable-read (R4): a layout value counts only when two consecutive
            // animation-frame reads agree (cap 10 frames) — a mid-reflow transient
            // can no longer become an assertion input.
            const stableRead = async (fn) => {
                // 250ms fallback: an occluded renderer must degrade to last-read, never hang (S1b review).
                const frame = () => new Promise((resolve) => {
                    requestAnimationFrame(resolve);
                    setTimeout(resolve, 250);
                });
                let previous = fn();
                for (let i = 0; i < 10; i++) {
                    await frame();
                    const current = fn();
                    if (JSON.stringify(current) === JSON.stringify(previous)) return current;
                    previous = current;
                }
                return previous;
            };
            await new Promise((resolve) => setTimeout(resolve, 250));
            const cards = Array.from(document.querySelectorAll(
                '[data-testid="media-library-card"]',
            ));
            const records = cards.map((card) => card.closest('.fi-ta-record'));
            const isShown = (element) => Boolean(element) && element.getClientRects().length > 0;
            const withinViewport = (element) => {
                const rect = element.getBoundingClientRect();

                return rect.left >= -1 && rect.right <= window.innerWidth + 1;
            };
            const lefts = new Set(cards.map((card) => Math.round(
                card.getBoundingClientRect().left,
            )));
            const viewportWidth = window.innerWidth;

            return {
                viewport_width: viewportWidth,
                direction: document.documentElement.dir,
                card_count: cards.length,
                columns: lefts.size,
                every_card_within_viewport: await stableRead(() => cards.every((card) => {
                    const rect = card.getBoundingClientRect();

                    return rect.left >= -1 && rect.right <= viewportWidth + 1;
                })),
                metadata_visible: cards.every((card) => {
                    const metadata = card.querySelector(
                        '[data-testid="media-library-card-file-summary"]',
                    );

                    return metadata && metadata.getClientRects().length > 0;
                }),
                details_visible: records.every((record) => Array.from(
                    record?.querySelectorAll('.fi-ta-actions a') ?? [],
                ).some((action) => action.getAttribute('aria-label') === {$openDetails}
                    && isShown(action))),
                edit_visible: records.every((record) => isShown(
                    record?.querySelector('[id^="media-record-"]'),
                )),
                action_group_visible: records.every((record) => Array.from(
                    record?.querySelectorAll('button[aria-label]') ?? [],
                ).some((button) => button.getAttribute('aria-label') === {$moreActions}
                    && isShown(button))),
                actions_within_viewport: await stableRead(() => records.every((record) => Array.from(
                    record?.querySelectorAll('.fi-ta-actions a, .fi-ta-actions button') ?? [],
                ).every((action) => ! isShown(action) || withinViewport(action)))),
                horizontal_overflow: await stableRead(() => document.documentElement.scrollWidth
                    > document.documentElement.clientWidth + 1),
            };
        }
        JS);

    expect($narrow['viewport_width'])->toBe(390)
        ->and($narrow['direction'])->toBe($direction)
        ->and($narrow['card_count'])->toBe(6)
        ->and($narrow['columns'])->toBe(1, json_encode($narrow, JSON_THROW_ON_ERROR))
        ->and($narrow['every_card_within_viewport'])->toBeTrue()
        ->and($narrow['metadata_visible'])->toBeTrue()
        ->and($narrow['details_visible'])->toBeTrue(json_encode($narrow, JSON_THROW_ON_ERROR))
        ->and($narrow['edit_visible'])->toBeTrue(json_encode($narrow, JSON_THROW_ON_ERROR))
        ->and($narrow['action_group_visible'])->toBeTrue(json_encode($narrow, JSON_THROW_ON_ERROR))
        ->and($narrow['actions_within_viewport'])->toBeTrue(json_encode($narrow, JSON_THROW_ON_ERROR))
        ->and($narrow['horizontal_overflow'])->toBeFalse();

    assertNoUnexpectedJavaScriptErrors($page);
})->with([
    'Hebrew RTL' => ['he', 'rtl'],
    'English LTR' => ['en', 'ltr'],
]);

it('keeps canonical Media task context keyboard reachable across Edit and safe fallback', function (
    string $locale,
    string $direction,
): void {
    app()->setLocale($locale);
    $records = collect(range(1, 25))->map(function (int $index): Media {
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
            'created_at' => now()->subSeconds(26 - $index),
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
        'page' => 3,
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
    $initial = $page->page()->evaluate(<<<JS
        async () => {
            // stable-read (R4): a layout value counts only when two consecutive
            // animation-frame reads agree (cap 10 frames) — a mid-reflow transient
            // can no longer become an assertion input.
            const stableRead = async (fn) => {
                // 250ms fallback: an occluded renderer must degrade to last-read, never hang (S1b review).
                const frame = () => new Promise((resolve) => {
                    requestAnimationFrame(resolve);
                    setTimeout(resolve, 250);
                });
                let previous = fn();
                for (let i = 0; i < 10; i++) {
                    await frame();
                    const current = fn();
                    if (JSON.stringify(current) === JSON.stringify(previous)) return current;
                    previous = current;
                }
                return previous;
            };
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
                description: document.querySelector('.fi-header-subheading')?.textContent.trim(),
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
                horizontal_overflow: await stableRead(() => document.documentElement.scrollWidth
                    > document.documentElement.clientWidth + 1),
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
        ->and($initial['page'])->toBe('3')
        ->and($initial['card_count'])->toBe(1)
        ->and($initial['horizontal_overflow'])->toBeFalse();

    $page
        ->click('#media-record-'.$origin->getKey())
        ->wait(0.25)
        ->assertSee(__('admin.media_library.back_to_media_library'));
    $editAccessibility = $page->page()->evaluate(<<<'JS'
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
    $backState = $page->page()->evaluate(<<<'JS'
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
        ->and($backState['query'])->toContain('page=3')
        ->and($backState['hash'])->toBe('#media-record-'.$origin->getKey())
        ->and($backState['focused_id'])->toBe('media-record-'.$origin->getKey());

    $page
        ->click('#media-record-'.$origin->getKey())
        ->wait(0.25)
        ->click(__('filament-panels::resources/pages/edit-record.form.actions.cancel.label'))
        ->wait(0.35);
    $cancelState = $page->page()->evaluate(<<<'JS'
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
        ->click('#media-record-'.$origin->getKey())
        ->wait(0.25);
    DB::table($origin->getTable())
        ->where($origin->getKeyName(), $origin->getKey())
        ->update(['type' => 'image/png', 'ext' => 'png']);
    $page
        ->click(__('admin.media_library.back_to_media_library'))
        ->wait(0.35)
        ->assertSee(__('admin.media_library.empty_view_heading'))
        ->assertSee(__('admin.media_library.reset_view'));
    $missingFocus = $page->page()->evaluate(<<<'JS'
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
    $resetState = $page->page()->evaluate(<<<'JS'
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

    $page->page()->evaluate(<<<'JS'
        () => {
            document.querySelector('.fi-tabs-item')?.focus();
        }
        JS);
    $page->keys('.fi-tabs-item:first-of-type', ['Tab']);
    $keyboardState = $page->page()->evaluate(<<<'JS'
        () => ({
            label: document.activeElement?.textContent.replace(/\s+/g, ' ').trim(),
            tag: document.activeElement?.tagName,
        })
        JS);

    expect($keyboardState['tag'])->toBe('BUTTON')
        ->and($keyboardState['label'])->toContain(__('admin.media_library.tasks.in_use'));

    $page->keys('.fi-tabs-item:nth-of-type(2)', ['Enter'])->wait(0.35);
    $activatedTask = $page->page()->evaluate(<<<'JS'
        () => ({
            tab: new URL(window.location.href).searchParams.get('tab'),
            selected: Array.from(document.querySelectorAll('.fi-tabs-item'))
                .find((tab) => tab.classList.contains('fi-active'))
                ?.textContent.replace(/\s+/g, ' ').trim(),
        })
        JS);

    expect($activatedTask['tab'])->toBe('in_use')
        ->and($activatedTask['selected'])->toContain(__('admin.media_library.tasks.in_use'));

    assertNoUnexpectedJavaScriptErrors($page);
})->with([
    'Hebrew canonical context' => ['he', 'rtl'],
    'English canonical context' => ['en', 'ltr'],
]);

it('delivers a responsive accessible issue review and preserves its exact task origin', function (
    string $locale,
    string $direction,
): void {
    app()->setLocale($locale);
    $current = Media::factory()->create([
        'disk' => 'public',
        'directory' => 'content-groups/covers',
        'visibility' => 'public',
        'name' => 'browser-issue-current',
        'path' => 'content-groups/covers/browser-issue-current.jpg',
        'width' => 640,
        'height' => 360,
        'size' => 2048,
        'type' => 'image/jpeg',
        'ext' => 'jpg',
        'title' => 'Browser issue current',
        'exif' => ['original_filename' => 'Browser issue current original.jpg'],
        'created_at' => now()->subMinute(),
    ]);
    $next = Media::factory()->create([
        'disk' => 'public',
        'directory' => 'content-groups/covers',
        'visibility' => 'public',
        'name' => 'browser-issue-next',
        'path' => 'content-groups/covers/browser-issue-next.jpg',
        'width' => 640,
        'height' => 360,
        'size' => 4096,
        'type' => 'image/jpeg',
        'ext' => 'jpg',
        'title' => 'Browser issue next',
        'exif' => ['original_filename' => 'Browser issue next original.jpg'],
        'created_at' => now(),
    ]);
    $owner = ContentGroup::factory()->create(['title' => 'Browser issue owner']);
    MediaAttachment::factory()->create([
        'media_id' => $current,
        'attachable_type' => 'content_group',
        'attachable_id' => $owner,
        'role' => MediaAttachmentRole::Cover,
    ]);
    $indexUrl = MediaResource::getUrl('index', [
        'tab' => 'needs_attention',
        'filters' => [
            'type' => ['value' => 'image/jpeg'],
            'reason' => ['value' => 'missing_file'],
        ],
        'search' => 'Browser issue',
        'sort' => 'created_at:asc',
        'focus' => $current->getKey(),
    ]).'#media-record-'.$current->getKey();
    $page = visit($indexUrl)->resize(1280, 960);

    $page
        ->click('#media-record-'.$current->getKey())
        ->wait(0.25)
        ->assertSee('Browser issue current')
        ->assertSee('Browser issue current original.jpg')
        ->assertSee(__('admin.media_issue_review.details.describe_not_repair'))
        ->click(__('admin.media_issue_review.review_issues'))
        ->wait(0.35)
        ->assertSee(__('admin.media_issue_review.heading'))
        ->assertSee(__('admin.media_issue_review.reasons.missing_file.cause'))
        ->assertSee(__('admin.media_issue_review.reasons.missing_file.consequence'))
        ->assertSee(__('admin.media_issue_review.reasons.missing_file.evidence_limit'))
        // RECON2 R5: the missing-file reason now carries real resolutions, so
        // the honest-blocker copy no longer renders for this record.
        ->assertSee(__('admin.media_issue_review.missing_file.restore_action'))
        ->assertSee(__('admin.media_issue_review.missing_file.detach_delete_action'))
        ->assertSee(__('admin.media_issue_review.owners.presentation_only'));

    $reviewState = $page->page()->evaluate(<<<'JS'
        async () => {
            // stable-read (R4): a layout value counts only when two consecutive
            // animation-frame reads agree (cap 10 frames) — a mid-reflow transient
            // can no longer become an assertion input.
            const stableRead = async (fn) => {
                // 250ms fallback: an occluded renderer must degrade to last-read, never hang (S1b review).
                const frame = () => new Promise((resolve) => {
                    requestAnimationFrame(resolve);
                    setTimeout(resolve, 250);
                });
                let previous = fn();
                for (let i = 0; i < 10; i++) {
                    await frame();
                    const current = fn();
                    if (JSON.stringify(current) === JSON.stringify(previous)) return current;
                    previous = current;
                }
                return previous;
            };
            const visible = (element) => element?.getClientRects().length > 0;
            const action = (testId) => document.querySelector(`[data-testid="${testId}"]`);
            const ownerRoute = action('media-issue-owner-route');

            return {
                direction: document.documentElement.dir,
                path: window.location.pathname,
                identity_visible: visible(document.querySelector('[data-testid="media-issue-review"]')),
                issue_visible: visible(document.querySelector('[data-testid="media-issue-reason-missing_file"]')),
                resolution_zone_visible: visible(action('media-issue-resolution-action-missing_file')),
                restore_trigger_visible: visible(action('media-swapFile-trigger')),
                detach_delete_trigger_visible: visible(action('media-detachAndDeleteFile-trigger')),
                blocker_present: Boolean(action('media-issue-blocker')),
                owner_route_visible: visible(ownerRoute),
                owner_route_new_tab: ownerRoute?.getAttribute('target') === '_blank'
                    && ownerRoute?.getAttribute('rel')?.includes('noopener'),
                close_visible: visible(action('media-issue-close')),
                next_visible: visible(action('media-issue-next')),
                return_visible: visible(action('media-issue-return')),
                generic_fix_present: Boolean(action('media-issue-fix')),
                recheck_present: Boolean(action('media-issue-recheck')),
                retry_present: Boolean(action('media-issue-retry')),
                files_discovery_present: Boolean(action('media-files-discovery')),
                trash_present: Boolean(action('media-trash')),
                horizontal_overflow: await stableRead(() => document.documentElement.scrollWidth
                    > document.documentElement.clientWidth + 1),
            };
        }
        JS);

    expect($reviewState['direction'])->toBe($direction)
        ->and($reviewState['path'])->toContain('/admin/media/'.$current->getKey().'/review-issues')
        ->and($reviewState['identity_visible'])->toBeTrue()
        ->and($reviewState['issue_visible'])->toBeTrue()
        ->and($reviewState['resolution_zone_visible'])->toBeTrue()
        ->and($reviewState['restore_trigger_visible'])->toBeTrue()
        ->and($reviewState['detach_delete_trigger_visible'])->toBeTrue()
        ->and($reviewState['blocker_present'])->toBeFalse()
        ->and($reviewState['owner_route_visible'])->toBeTrue()
        ->and($reviewState['owner_route_new_tab'])->toBeTrue()
        ->and($reviewState['close_visible'])->toBeTrue()
        ->and($reviewState['next_visible'])->toBeTrue()
        ->and($reviewState['return_visible'])->toBeTrue()
        ->and($reviewState['generic_fix_present'])->toBeFalse()
        ->and($reviewState['recheck_present'])->toBeFalse()
        ->and($reviewState['retry_present'])->toBeFalse()
        ->and($reviewState['files_discovery_present'])->toBeFalse()
        ->and($reviewState['trash_present'])->toBeFalse()
        ->and($reviewState['horizontal_overflow'])->toBeFalse();

    if ($locale === 'he') {
        $page->screenshot(
            fullPage: true,
            filename: 'media-operations-ux3-mini3-issue-review-he-desktop',
        );
    } else {
        $page->screenshot(
            fullPage: true,
            filename: 'media-operations-ux3-mini3-issue-review-en-desktop',
        );
    }

    $page->resize(390, 844)->wait(0.25);
    $narrow = $page->page()->evaluate(<<<'JS'
        async () => {
            // stable-read (R4): a layout value counts only when two consecutive
            // animation-frame reads agree (cap 10 frames) — a mid-reflow transient
            // can no longer become an assertion input.
            const stableRead = async (fn) => {
                // 250ms fallback: an occluded renderer must degrade to last-read, never hang (S1b review).
                const frame = () => new Promise((resolve) => {
                    requestAnimationFrame(resolve);
                    setTimeout(resolve, 250);
                });
                let previous = fn();
                for (let i = 0; i < 10; i++) {
                    await frame();
                    const current = fn();
                    if (JSON.stringify(current) === JSON.stringify(previous)) return current;
                    previous = current;
                }
                return previous;
            };
            const review = document.querySelector('[data-testid="media-issue-review"]');

            return {
                viewport_width: window.innerWidth,
                direction: document.documentElement.dir,
                review_within_viewport: await stableRead(() => {
                    const rect = review?.getBoundingClientRect();

                    return Boolean(rect)
                        && rect.left >= -1
                        && rect.right <= window.innerWidth + 1;
                }),
                horizontal_overflow: await stableRead(() => document.documentElement.scrollWidth
                    > document.documentElement.clientWidth + 1),
            };
        }
        JS);

    expect($narrow['viewport_width'])->toBe(390)
        ->and($narrow['direction'])->toBe($direction)
        ->and($narrow['review_within_viewport'])->toBeTrue()
        ->and($narrow['horizontal_overflow'])->toBeFalse();

    if ($locale === 'he') {
        $page->screenshot(
            fullPage: true,
            filename: 'media-operations-ux3-mini3-issue-review-he-narrow',
        );
    }

    $page->resize(1280, 960)->wait(0.25);
    $page->page()->evaluate(<<<'JS'
        () => document.querySelector('[data-testid="media-issue-close"]')?.focus()
        JS);
    $page->keys('[data-testid="media-issue-close"]', ['Tab']);
    $firstTab = $page->page()->evaluate(<<<'JS'
        () => document.activeElement?.getAttribute('data-testid')
        JS);
    $page->keys('[data-testid="media-issue-next"]', ['Tab']);
    $secondTab = $page->page()->evaluate(<<<'JS'
        () => document.activeElement?.getAttribute('data-testid')
        JS);

    expect($firstTab)->toBe('media-issue-next')
        ->and($secondTab)->toBe('media-issue-return');

    stripKnownResizeObserverArtifacts($page);
    $accessibilityIssues = $page->page()->evaluate(<<<'JS'
        async () => {
            const root = document.querySelector('[data-testid="media-issue-review"]');
            const violations = root ? (await window.axe.run(root)).violations : [{
                id: 'missing-review-root',
                impact: 'critical',
                nodes: [],
            }];

            return violations
                .filter((violation) => ['critical', 'serious'].includes(violation.impact))
                .map((violation) => ({
                    id: violation.id,
                    impact: violation.impact,
                    targets: violation.nodes.flatMap((node) => node.target),
                }));
        }
        JS);

    expect($accessibilityIssues)->toBeEmpty();

    $page
        ->assertNoSmoke()
        ->click(__('admin.media_issue_review.actions.next'))
        ->wait(0.35)
        ->assertSee('Browser issue next')
        ->click(__('admin.media_issue_review.actions.return'))
        ->wait(0.35);
    $returnState = $page->page()->evaluate(<<<'JS'
        () => ({
            path: window.location.pathname,
            tab: new URL(window.location.href).searchParams.get('tab'),
            mime: new URL(window.location.href).searchParams.get('filters[type][value]'),
            reason: new URL(window.location.href).searchParams.get('filters[reason][value]'),
            search: new URL(window.location.href).searchParams.get('search'),
            sort: new URL(window.location.href).searchParams.get('sort'),
            hash: window.location.hash,
            focused_id: document.activeElement?.id ?? null,
        })
        JS);

    expect($returnState)->toBe([
        'path' => '/admin/media',
        'tab' => 'needs_attention',
        'mime' => 'image/jpeg',
        'reason' => 'missing_file',
        'search' => 'Browser issue',
        'sort' => 'created_at:asc',
        'hash' => '#media-record-'.$current->getKey(),
        'focused_id' => 'media-record-'.$current->getKey(),
    ]);
})->with([
    'Hebrew issue review' => ['he', 'rtl'],
    'English issue review' => ['en', 'ltr'],
]);
