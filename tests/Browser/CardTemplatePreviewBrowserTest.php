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
        ->and($wide['preview_overflow'])->toBe('auto');

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

    $page->assertScript('document.documentElement.dir', 'ltr')
        ->assertSee(__('admin.settings_sp3c.preview.title'))
        ->assertCount('[data-card-template-preview-root]', 1)
        ->assertNoSmoke()
        ->assertNoJavaScriptErrors();
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

            let modal = document.querySelector('.fi-modal.fi-modal-open');
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
        ->and($interaction['network_requests'])->toBe(1, json_encode($interaction, JSON_THROW_ON_ERROR))
        ->and($interaction['preview_updated'])->toBeTrue(json_encode($interaction, JSON_THROW_ON_ERROR))
        ->and($interaction['preview_roots'])->toBe(1);

    $page->assertNoSmoke()->assertNoJavaScriptErrors();
});
