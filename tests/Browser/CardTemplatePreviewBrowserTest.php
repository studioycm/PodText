<?php

use App\Filament\Pages\CardTemplateSettings;
use App\Filament\Pages\EditCardTemplate;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\Transcription;
use App\Models\User;
use App\Settings\PublicContentSettings;
use App\Support\PublicFront\Cards\PublicFrontCardTemplateRegistry;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

it('keeps one inert responsive preview root with focus and dirty navigation protection', function (): void {
    app()->setLocale('he');
    $page = visit(EditCardTemplate::getUrl([
        'family' => 'content_item',
        'key' => 'preview_browser',
    ]))->resize(1440, 900);

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

            return {
                dom_elements: document.querySelectorAll('*').length,
                preview_roots: document.querySelectorAll('[data-card-template-preview-root]').length,
                preview_focusables: root?.querySelectorAll(focusableSelector).length ?? 0,
                public_interactions: ready?.querySelectorAll('a[href], [wire\\:click], button, input, select, textarea').length ?? 0,
                direction: document.documentElement.dir,
                key_direction: document.querySelector('[data-sp3c-template-editor] input[dir="ltr"]')?.dir ?? null,
                horizontal_overflow: document.documentElement.scrollWidth > document.documentElement.clientWidth + 1,
                shell_overflow: getComputedStyle(document.querySelector('[data-card-template-preview-wide-shell]')).overflowY,
                preview_overflow: getComputedStyle(document.querySelector('[data-card-template-preview-scroll]')).overflowY,
                preview_is_logical_end: previewRect?.right <= editorRect?.left,
                draft_is_first_editor_section: Math.abs((draftRect?.top ?? -1) - (editorRect?.top ?? -3)) < 2,
                import_metadata_in_header: Boolean(headerMetadata),
                import_metadata_in_editor: Boolean(editorColumn?.querySelector('[data-card-template-import-lock-metadata]')),
                livewire_components: window.Livewire?.all?.().length ?? null,
                used_js_heap_size: performance.memory?.usedJSHeapSize ?? null,
            };
        }
        JS);

    expect($wide['preview_roots'])->toBe(1)
        ->and($wide['public_interactions'])->toBe(0)
        ->and($wide['direction'])->toBe('rtl')
        ->and($wide['key_direction'])->toBe('ltr')
        ->and($wide['horizontal_overflow'])->toBeFalse()
        ->and($wide['shell_overflow'])->toBe('auto')
        ->and($wide['preview_overflow'])->toBe('auto')
        ->and($wide['preview_is_logical_end'])->toBeTrue(json_encode($wide, JSON_THROW_ON_ERROR))
        ->and($wide['draft_is_first_editor_section'])->toBeTrue()
        ->and($wide['import_metadata_in_header'])->toBeTrue()
        ->and($wide['import_metadata_in_editor'])->toBeFalse();

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

    $page->resize(1024, 800);
    $narrow = $page->script(<<<'JS'
        async () => {
            await new Promise((resolve) => setTimeout(resolve, 150));
            const beforeDom = document.querySelectorAll('*').length;
            const started = performance.now();
            const trigger = document.querySelector('[data-test="card-template-preview-open"]');
            trigger.focus();
            trigger.click();

            while (document.querySelector('[data-card-template-preview-modal]') === null && performance.now() - started < 5000) {
                await new Promise((resolve) => setTimeout(resolve, 25));
            }

            await new Promise((resolve) => setTimeout(resolve, 300));
            const modal = document.querySelector('[data-card-template-preview-modal]');

            return {
                dom_delta: document.querySelectorAll('*').length - beforeDom,
                preview_roots: document.querySelectorAll('[data-card-template-preview-root]').length,
                active_inside_modal: Boolean(modal?.closest('[aria-modal="true"]')?.contains(document.activeElement)),
                active_element: document.activeElement?.id || document.activeElement?.tagName || null,
                horizontal_overflow: document.documentElement.scrollWidth > document.documentElement.clientWidth + 1,
                modal_public_interactions: modal?.querySelectorAll('[data-test="card-template-preview-ready"] a[href], [data-test="card-template-preview-ready"] [wire\\:click], [data-test="card-template-preview-ready"] button').length ?? 0,
            };
        }
        JS);

    expect($narrow['preview_roots'])->toBe(1)
        ->and($narrow['active_inside_modal'])->toBeTrue(json_encode($narrow, JSON_THROW_ON_ERROR))
        ->and($narrow['horizontal_overflow'])->toBeFalse()
        ->and($narrow['modal_public_interactions'])->toBe(0);

    $page->keys('#card-template-preview-heading', ['Tab', 'Tab', 'Tab', 'Tab']);
    expect($page->script("Boolean(document.activeElement?.closest('[aria-modal=true]'))"))->toBeTrue();

    $escapeRestored = $page->script(<<<'JS'
        async () => {
            window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
            await new Promise((resolve) => setTimeout(resolve, 300));

            return document.querySelector('[data-card-template-preview-modal]') === null
                && Boolean(document.activeElement?.closest('[data-test="card-template-preview-open"]'));
        }
        JS);
    expect($escapeRestored)->toBeTrue();

    $page->script(<<<'JS'
        async () => {
            const trigger = document.querySelector('[data-test="card-template-preview-open"]');
            trigger.focus();
            trigger.click();
            await new Promise((resolve) => setTimeout(resolve, 200));
        }
        JS);
    $page->resize(1440, 900);
    $wideRestored = $page->script(<<<'JS'
        async () => {
            await new Promise((resolve) => setTimeout(resolve, 250));

            return {
                preview_roots: document.querySelectorAll('[data-card-template-preview-root]').length,
                modal_roots: document.querySelectorAll('[data-card-template-preview-modal]').length,
                focus_on_heading: document.activeElement?.id === 'card-template-preview-heading',
            };
        }
        JS);

    expect($wideRestored['preview_roots'])->toBe(1)
        ->and($wideRestored['modal_roots'])->toBe(0)
        ->and($wideRestored['focus_on_heading'])->toBeTrue();

    $dirtyProtected = $page->script(<<<'JS'
        async () => {
            const input = Array.from(document.querySelectorAll('[data-sp3c-template-editor] input'))
                .find((candidate) => candidate.getAttribute('wire:model')?.includes('data.label'));
            const setter = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value').set;
            setter.call(input, `${input.value} dirty`);
            input.dispatchEvent(new Event('input', { bubbles: true }));
            await new Promise((resolve) => setTimeout(resolve, 100));
            const event = new Event('beforeunload', { bubbles: false, cancelable: true });
            window.dispatchEvent(event);

            return event.defaultPrevented;
        }
        JS);

    expect($dirtyProtected)->toBeTrue();

    if (getenv('STEP5B_BROWSER_REPORT') === '1') {
        fwrite(STDERR, json_encode([
            'wide' => $wide,
            'refresh' => $refresh,
            'narrow_open' => $narrow,
            'wide_restore' => $wideRestored,
            'listener_observation' => 'Runner exposes Livewire component count, not listener enumeration.',
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
    ]))->resize(1440, 900);

    $geometry = $page->script(<<<'JS'
        async () => {
            await new Promise((resolve) => setTimeout(resolve, 250));
            const previewRect = document.querySelector('[data-card-template-preview-column]')?.getBoundingClientRect();
            const editorRect = document.querySelector('[data-card-template-editor-column]')?.getBoundingClientRect();

            return {
                preview_is_logical_end: editorRect?.right <= previewRect?.left,
                header_metadata: Boolean(document.querySelector('.fi-header [data-card-template-import-lock-metadata]')),
            };
        }
        JS);

    $page->assertScript('document.documentElement.dir', 'ltr')
        ->assertSee(__('admin.settings_sp3c.preview.title'))
        ->assertCount('[data-card-template-preview-root]', 1)
        ->assertNoSmoke()
        ->assertNoJavaScriptErrors();

    expect($geometry['preview_is_logical_end'])->toBeTrue(json_encode($geometry, JSON_THROW_ON_ERROR))
        ->and($geometry['header_metadata'])->toBeTrue();
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
