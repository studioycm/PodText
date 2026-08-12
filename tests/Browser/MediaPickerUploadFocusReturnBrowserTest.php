<?php

declare(strict_types=1);

use App\Filament\Resources\ContentGroups\ContentGroupResource;
use App\Models\ContentGroup;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Pest\Browser\Api\AwaitableWebpage;

/*
 * The picker returns keyboard focus to the workspace when an upload settles.
 * The return target's `disabled` property has two independent writers — this
 * component's own upload guard (x-bind) and Filament's wire:loading.attr, which
 * every icon button carries while any request is in flight on the component —
 * so a single focus() attempt can address a control that cannot take focus and
 * silently do nothing, dropping the keyboard user on <body> for the rest of the
 * page's life. Diagnosis and measurements:
 * docs/research/browser-timeout-contention-investigation.md.
 */

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Http::preventStrayRequests();
    Storage::fake('local');
    Storage::fake('public');
    $this->actingAs(User::factory()->admin()->create());
});

function openUploadWorkspaceForFocusReturn(): AwaitableWebpage
{
    $group = ContentGroup::factory()->create(['title' => 'Upload focus return']);

    $page = visit(ContentGroupResource::getUrl('edit', ['record' => $group]))->resize(1280, 900);

    $page->page()->evaluate(<<<'JS'
        async () => {
            const waitFor = async (callback, stage, timeout = 10000) => {
                const started = performance.now();

                while (performance.now() - started < timeout) {
                    const result = callback();

                    if (result) {
                        return result;
                    }

                    await new Promise((resolve) => setTimeout(resolve, 25));
                }

                throw new Error(`Timed out while ${stage}.`);
            };

            window.__focusReturnPicker = () => document.querySelector(
                '[aria-modal="true"].fi-modal-open [data-testid="media-picker"]',
            );
            window.__focusReturnUploadButton = () => window.__focusReturnPicker()?.querySelector(
                '[data-testid="media-picker-source-upload"]',
            );

            document.querySelector('[data-testid="media-picker-open"]').click();
            const picker = await waitFor(() => window.__focusReturnPicker(), 'waiting for the picker to open');
            await waitFor(
                () => {
                    const childId = picker.closest('[wire\\:id]')?.getAttribute('wire:id');

                    return childId && window.Livewire.find(childId);
                },
                'waiting for the picker component to initialise',
            );

            window.__focusReturnUploadButton()?.click();
            await waitFor(
                () => window.__focusReturnPicker()?.querySelector('#media-picker-upload-input'),
                'waiting for the upload panel',
            );

            window.__focusReturnDetail = {
                id: window.__focusReturnPicker()?.closest('[wire\\:id]')?.getAttribute('wire:id'),
                property: 'panelData.uploads',
            };

            return true;
        }
        JS);

    return $page;
}

it('returns focus to the workspace when the upload settles', function (): void {
    $page = openUploadWorkspaceForFocusReturn();

    $result = $page->page()->evaluate(<<<'JS'
        async () => {
            const waitFor = async (callback, stage, timeout = 10000) => {
                const started = performance.now();

                while (performance.now() - started < timeout) {
                    const result = callback();

                    if (result) {
                        return result;
                    }

                    await new Promise((resolve) => setTimeout(resolve, 25));
                }

                throw new Error(`Timed out while ${stage}.`);
            };
            const picker = window.__focusReturnPicker;

            picker().dispatchEvent(new CustomEvent('livewire-upload-start', {
                detail: window.__focusReturnDetail,
            }));
            await waitFor(() => picker()?.getAttribute('aria-busy') === 'true', 'waiting for the upload guard');

            picker().dispatchEvent(new CustomEvent('livewire-upload-finish', {
                detail: window.__focusReturnDetail,
            }));
            await waitFor(() => picker()?.contains(document.activeElement), 'waiting for focus to return');

            return {
                focus_inside_picker: Boolean(picker()?.contains(document.activeElement)),
                active_element: document.activeElement?.getAttribute?.('data-testid')
                    || document.activeElement?.id
                    || document.activeElement?.tagName,
            };
        }
        JS);

    expect($result['focus_inside_picker'])->toBeTrue(json_encode($result, JSON_THROW_ON_ERROR));

    assertNoUnexpectedJavaScriptErrors($page);
});

it('returns focus even when the primary return target cannot take it', function (): void {
    $page = openUploadWorkspaceForFocusReturn();

    /*
     * Stands in for the second writer: an in-flight Livewire request keeps
     * wire:loading.attr="disabled" on the return target across the tick the
     * restore gets. Held by frame so Alpine's own binding cannot quietly undo
     * it, then released — a single unverified focus() call never recovers.
     */
    $result = $page->page()->evaluate(<<<'JS'
        async () => {
            const waitFor = async (callback, stage, timeout = 10000) => {
                const started = performance.now();

                while (performance.now() - started < timeout) {
                    const result = callback();

                    if (result) {
                        return result;
                    }

                    await new Promise((resolve) => setTimeout(resolve, 25));
                }

                return null;
            };
            const picker = window.__focusReturnPicker;

            picker().dispatchEvent(new CustomEvent('livewire-upload-start', {
                detail: window.__focusReturnDetail,
            }));
            await waitFor(() => picker()?.getAttribute('aria-busy') === 'true', 'waiting for the upload guard');

            let holding = true;
            const hold = () => {
                if (! holding) {
                    return;
                }

                const button = window.__focusReturnUploadButton();

                if (button) {
                    button.disabled = true;
                }

                requestAnimationFrame(hold);
            };
            hold();

            picker().dispatchEvent(new CustomEvent('livewire-upload-finish', {
                detail: window.__focusReturnDetail,
            }));

            const returned = await waitFor(
                () => picker()?.contains(document.activeElement),
                'waiting for focus to return past a disabled target',
                4000,
            );
            holding = false;

            return {
                focus_returned: Boolean(returned),
                focus_inside_picker: Boolean(picker()?.contains(document.activeElement)),
                active_element: document.activeElement?.getAttribute?.('data-testid')
                    || document.activeElement?.id
                    || document.activeElement?.tagName,
            };
        }
        JS);

    expect($result['focus_returned'])->toBeTrue(json_encode($result, JSON_THROW_ON_ERROR))
        ->and($result['active_element'])->not->toBe('BODY', json_encode($result, JSON_THROW_ON_ERROR));

    assertNoUnexpectedJavaScriptErrors($page);
});

it('leaves a deliberate focus choice alone when the upload settles', function (): void {
    $page = openUploadWorkspaceForFocusReturn();

    $result = $page->page()->evaluate(<<<'JS'
        async () => {
            const waitFor = async (callback, stage, timeout = 10000) => {
                const started = performance.now();

                while (performance.now() - started < timeout) {
                    const result = callback();

                    if (result) {
                        return result;
                    }

                    await new Promise((resolve) => setTimeout(resolve, 25));
                }

                throw new Error(`Timed out while ${stage}.`);
            };
            const picker = window.__focusReturnPicker;

            picker().dispatchEvent(new CustomEvent('livewire-upload-start', {
                detail: window.__focusReturnDetail,
            }));
            await waitFor(() => picker()?.getAttribute('aria-busy') === 'true', 'waiting for the upload guard');

            picker().dispatchEvent(new CustomEvent('livewire-upload-finish', {
                detail: window.__focusReturnDetail,
            }));
            await waitFor(() => picker()?.getAttribute('aria-busy') !== 'true', 'waiting for the guard to release');

            // A person moved focus themselves after the guard released; the
            // restore must not yank it away.
            const chosen = await waitFor(
                () => {
                    const close = picker()?.querySelector('[data-testid="media-picker-close"]');

                    return close && ! close.disabled ? close : null;
                },
                'waiting for a focusable control',
            );
            chosen.focus();
            await new Promise((resolve) => setTimeout(resolve, 600));

            return {
                kept_choice: document.activeElement === chosen,
                active_element: document.activeElement?.getAttribute?.('data-testid')
                    || document.activeElement?.id
                    || document.activeElement?.tagName,
            };
        }
        JS);

    expect($result['kept_choice'])->toBeTrue(json_encode($result, JSON_THROW_ON_ERROR));

    assertNoUnexpectedJavaScriptErrors($page);
});
