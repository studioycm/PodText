<?php

use App\Enums\HomepageSectionType;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\HomepageSection;
use App\Settings\PublicContentSettings;
use App\Support\PublicFront\PublicFrontRenderContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\LaravelSettings\SettingsContainer;

uses(RefreshDatabase::class);

/**
 * Seed a homepage where the SAME enabled public form is mounted twice:
 * once by the header (menu_config form action) and once by a homepage
 * content-block section button. Each parent dedupes internally, so this
 * cross-parent pair is the smallest real duplicate-mount page.
 */
function seedFormModalDedupeHomepage(): void
{
    foreach ([
        'public_forms' => [
            'definitions' => [
                [
                    'key' => 'request_transcription',
                    'name' => 'Request transcription',
                    'heading' => 'Request a transcription',
                    'submit_label' => 'Send request',
                    'success_message' => 'Request received.',
                    'enabled' => true,
                    'display_mode_default' => 'modal',
                    'fields' => [
                        [
                            'key' => 'name',
                            'type' => 'text',
                            'label' => 'Name',
                            'required' => true,
                        ],
                    ],
                    'settings' => [
                        'rate_limit_attempts' => 5,
                        'rate_limit_decay_seconds' => 600,
                    ],
                ],
            ],
        ],
        'menu_config' => [
            'enabled' => true,
            'items' => [
                ['key' => 'home', 'type' => 'route', 'route_key' => 'home', 'label' => 'Home', 'visible' => true, 'sort' => 10],
                ['key' => 'request', 'type' => 'public_form', 'form_key' => 'request_transcription', 'label' => 'Request transcription', 'display_mode' => 'modal', 'visible' => true, 'sort' => 20],
            ],
        ],
    ] as $name => $value) {
        DB::table('settings')->updateOrInsert(
            [
                'group' => PublicContentSettings::group(),
                'name' => $name,
            ],
            [
                'locked' => false,
                'payload' => json_encode($value),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    app()->forgetInstance(PublicContentSettings::class);
    app()->forgetInstance(PublicFrontRenderContext::class);
    app(SettingsContainer::class)->clearCache();

    HomepageSection::factory()->create([
        'name' => 'Form CTA Block',
        'type' => HomepageSectionType::Latest,
        'sort_order' => 10,
        'source_config' => [
            'source_type' => 'content_block',
        ],
        'display_config' => [
            'heading' => 'Form CTA Block',
            'body' => 'Ask us for a transcription.',
            'content_style' => 'callout',
            'button_label' => 'Open request form',
            'button_form_key' => 'request_transcription',
        ],
    ]);

    ContentItem::factory()
        ->for(ContentGroup::factory()->published())
        ->published()
        ->withTranscription()
        ->create([
            'title' => 'Form Dedupe Item',
            'slug' => 'form-dedupe-item',
        ]);
}

it('opens a single dialog when the header and a homepage section mount the same form key', function (): void {
    seedFormModalDedupeHomepage();

    $page = visit('/');

    $state = $page->script(<<<'JS'
        async () => {
            const waitFor = async (callback, step, timeout = 8000) => {
                const started = performance.now();
                while (performance.now() - started < timeout) {
                    const value = callback();
                    if (value) { return value; }
                    await new Promise((resolve) => setTimeout(resolve, 100));
                }
                throw new Error(`timeout at step: ${step}`);
            };

            const overlays = () => Array.from(document.querySelectorAll('[data-test="public-form-overlay"]'));
            const visibleOverlays = () => overlays().filter((el) => getComputedStyle(el).display !== 'none');

            await waitFor(() => overlays().length === 2 && visibleOverlays().length === 0, 'initial-settle');

            document.querySelector('[data-test="homepage-content-block-form-button"]').click();
            await waitFor(() => visibleOverlays().length > 0, 'dialog-open');
            await new Promise((resolve) => setTimeout(resolve, 250));

            return {
                mountedRoots: document.querySelectorAll('[data-test="public-form-modal"][data-form-key="request_transcription"]').length,
                openDialogs: visibleOverlays().length,
            };
        }
        JS);

    expect($state['mountedRoots'])->toBe(2)
        ->and($state['openDialogs'])->toBe(1);

    $page->assertNoJavaScriptErrors();
});

it('keeps the open event key-scoped and reusable across open and close cycles', function (): void {
    seedFormModalDedupeHomepage();

    $page = visit('/');

    $state = $page->script(<<<'JS'
        async () => {
            const waitFor = async (callback, step, timeout = 8000) => {
                const started = performance.now();
                while (performance.now() - started < timeout) {
                    const value = callback();
                    if (value) { return value; }
                    await new Promise((resolve) => setTimeout(resolve, 100));
                }
                throw new Error(`timeout at step: ${step}`);
            };

            const overlays = () => Array.from(document.querySelectorAll('[data-test="public-form-overlay"]'));
            const visibleOverlays = () => overlays().filter((el) => getComputedStyle(el).display !== 'none');
            const dispatch = (detail) => window.dispatchEvent(new CustomEvent('open-public-form', { detail }));
            const settle = () => new Promise((resolve) => setTimeout(resolve, 250));

            await waitFor(() => overlays().length === 2 && visibleOverlays().length === 0, 'initial-settle');

            dispatch({});
            await settle();
            const afterKeyless = visibleOverlays().length;

            dispatch({ formKey: 'unknown_form' });
            await settle();
            const afterForeign = visibleOverlays().length;

            dispatch({ formKey: 'request_transcription' });
            await waitFor(() => visibleOverlays().length > 0, 'match-open');
            const afterMatch = visibleOverlays().length;

            visibleOverlays().forEach((el) => el.querySelector('[data-test="public-form-close"]').click());
            await waitFor(() => visibleOverlays().length === 0, 'close-settle');

            dispatch({ formKey: 'request_transcription' });
            await waitFor(() => visibleOverlays().length > 0, 'reopen');
            const afterReopen = visibleOverlays().length;

            return { afterKeyless, afterForeign, afterMatch, afterReopen };
        }
        JS);

    expect($state['afterKeyless'])->toBe(0)
        ->and($state['afterForeign'])->toBe(0)
        ->and($state['afterMatch'])->toBe(1)
        ->and($state['afterReopen'])->toBe(1);

    $page->assertNoJavaScriptErrors();
});
