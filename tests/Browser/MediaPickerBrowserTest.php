<?php

use App\Filament\Resources\ContentGroups\ContentGroupResource;
use App\Models\ContentGroup;
use App\Models\Media;
use App\Models\MediaAsset;
use App\Models\MediaProviderBinding;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Http::preventStrayRequests();
    Storage::fake('local');
    Storage::fake('public');
    $this->actingAs(User::factory()->admin()->create());
});

function setMediaPickerBrowserOffline(object $webpage, bool $offline): void
{
    $webpageReflection = new ReflectionClass($webpage);
    $pageProperty = $webpageReflection->getProperty('page');
    $playwrightPage = $pageProperty->getValue($webpage);
    $context = $playwrightPage->context();
    $sendMessage = new ReflectionMethod($context, 'sendMessage');
    $response = $sendMessage->invoke($context, 'setOffline', ['offline' => $offline]);

    iterator_to_array($response, false);
}

function trackMediaPickerBrowserRequests(object $webpage): void
{
    $webpage->page()->evaluate(<<<'JS'
        () => {
            window.__mediaPickerPendingRequests = 0;
            window.__mediaPickerCompletedRequests = 0;
            window.__mediaPickerFailedRequests = 0;
            window.Livewire.interceptRequest(({ onError, onFailure, onFinish }) => {
                window.__mediaPickerPendingRequests++;
                let settled = false;
                let failed = false;
                const finish = () => {
                    if (settled) {
                        return;
                    }

                    settled = true;
                    requestAnimationFrame(() => {
                        queueMicrotask(() => {
                            window.__mediaPickerPendingRequests--;
                            window.__mediaPickerCompletedRequests++;

                            if (failed) {
                                window.__mediaPickerFailedRequests++;
                            }
                        });
                    });
                };

                onError(() => failed = true);
                onFailure(() => failed = true);
                onFinish(finish);
            });
        }
        JS);
}

it('keeps the acquisition workspace accessible responsive and stateful', function (
    string $locale,
    string $direction,
): void {
    app()->setLocale($locale);
    Storage::disk('public')->put(
        'media-imports/browser-storage.jpg',
        base64_decode((string) file_get_contents(base_path('tests/Fixtures/media/valid.jpg.base64')), true),
    );
    $group = ContentGroup::factory()->create([
        'title' => "Media picker {$locale}",
    ]);

    $page = visit(ContentGroupResource::getUrl('edit', ['record' => $group]))
        ->resize(1280, 900);

    // A late Livewire commit from the previous dataset's page can restore its
    // snapshot locale between setLocale() and this render; re-serve once.
    if ($page->page()->evaluate('document.documentElement.dir') !== $direction) {
        app()->setLocale($locale);
        $page->refresh();
    }

    trackMediaPickerBrowserRequests($page);

    $wide = $page->page()->evaluate(<<<'JS'
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
            const waitFor = async (callback, step = null, timeout = 7000) => {
                const started = performance.now();

                while (performance.now() - started < timeout) {
                    const result = callback();

                    if (result) {
                        return result;
                    }

                    await new Promise((resolve) => setTimeout(resolve, 25));
                }

                throw new Error(step
                    ? `timeout at step: ${step}`
                    : 'Timed out while waiting for media picker browser evidence.');
            };
            const dialog = () => document.querySelector('[aria-modal="true"].fi-modal-open');
            const picker = () => dialog()?.querySelector('[data-testid="media-picker"]');
            const modalRoot = picker;
            const opener = document.querySelector('[data-testid="media-picker-open"]');
            opener.focus();
            opener.click();

            await waitFor(() => picker());
            await new Promise((resolve) => setTimeout(resolve, 250));

            const activeDialog = dialog();
            const modalWindow = activeDialog?.querySelector('.fi-modal-window');
            const storageFilename = 'browser-storage.jpg';
            const storageWasLazy = ! picker()?.textContent.includes(storageFilename);

            picker()?.querySelector('[data-testid="media-picker-source-url"]')?.click();
            const urlInput = await waitFor(() => picker()?.querySelector('#media-picker-url-input'));
            const inputValueSetter = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value').set;
            const retainedUrl = 'https://cdn.example.test/preserved-picker.png';
            inputValueSetter.call(urlInput, retainedUrl);
            urlInput.dispatchEvent(new Event('input', { bubbles: true }));
            await new Promise((resolve) => setTimeout(resolve, 700));

            picker()?.querySelector('[data-testid="media-picker-source-storage"]')?.click();
            await waitFor(() => picker()?.querySelector('[data-testid="media-picker-panel-storage"]'));
            await waitFor(() => picker()?.textContent.includes(storageFilename));
            const storageLoaded = picker()?.textContent.includes(storageFilename) ?? false;
            const inactiveUploadPanel = picker()?.querySelector('[data-testid="media-picker-panel-upload"]');
            const uploadPanelPersisted = Boolean(
                inactiveUploadPanel
                && inactiveUploadPanel.classList.contains('hidden')
                && inactiveUploadPanel.inert
                && picker()?.querySelector('#media-picker-upload-input'),
            );

            picker()?.querySelector('[data-testid="media-picker-source-url"]')?.click();
            const restoredUrlInput = await waitFor(() => {
                const input = picker()?.querySelector('#media-picker-url-input');

                return input?.value === retainedUrl ? input : null;
            });

            activeDialog?.querySelector('.fi-modal-window-ctn')?.click();
            await new Promise((resolve) => setTimeout(resolve, 150));
            const backdropKeptModalOpen = Boolean(modalRoot());

            window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
            await new Promise((resolve) => setTimeout(resolve, 150));
            const escapeKeptModalOpen = Boolean(modalRoot());

            window.dispatchEvent(new Event('offline'));
            await waitFor(() => {
                const fieldset = picker()?.querySelector('[data-testid="media-picker-source-form"]');
                const notice = picker()?.querySelector('[wire\\:offline]');

                return fieldset?.disabled
                    && notice
                    && getComputedStyle(notice).display !== 'none';
            });
            const offlineDisabledSource = picker()?.querySelector(
                '[data-testid="media-picker-source-form"]',
            )?.disabled ?? false;
            const offlineKeptCloseAvailable = ! picker()?.querySelector(
                '[data-testid="media-picker-close"]',
            )?.disabled;

            window.dispatchEvent(new Event('online'));
            await waitFor(() => ! picker()?.querySelector(
                '[data-testid="media-picker-source-form"]',
            )?.disabled);
            const onlineRestoredSource = ! picker()?.querySelector(
                '[data-testid="media-picker-source-form"]',
            )?.disabled;

            picker()?.querySelector('[data-testid="media-picker-source-upload"]')?.click();
            const uploadInput = await waitFor(() => picker()?.querySelector('#media-picker-upload-input'));
            const uploadDetail = {
                id: picker()?.closest('[wire\\:id]')?.getAttribute('wire:id'),
                property: 'panelData.uploads',
            };
            uploadInput.focus();
            picker().dispatchEvent(new CustomEvent('livewire-upload-start', {
                detail: uploadDetail,
            }));
            const uploadSourceButtons = () => [
                picker()?.querySelector('[data-testid="media-picker-source-upload"]'),
                picker()?.querySelector('[data-testid="media-picker-source-url"]'),
                picker()?.querySelector('[data-testid="media-picker-source-storage"]'),
            ];
            // Mirror of the settle split below: each engage condition waits
            // under its own label so a timeout names the guard that never
            // engaged.
            await waitFor(() => uploadSourceButtons().every((button) => button?.disabled),
                'upload-start: source buttons disabled');
            await waitFor(() => picker()?.querySelector('[data-testid="media-picker-header"]')?.inert,
                'upload-start: header inert engaged');
            await waitFor(() => picker()?.querySelector('[data-testid="media-picker-upload-action-guard"]')?.inert,
                'upload-start: action guard inert engaged');
            await waitFor(() => picker()?.querySelector('[data-testid="media-picker-close"]')
                ?.getAttribute('aria-disabled') === 'true',
                'upload-start: close aria-disabled engaged');
            await waitFor(() => picker()?.getAttribute('aria-busy') === 'true',
                'upload-start: aria-busy engaged');
            // The engage contract is the conjunction: re-wait it whole so the
            // snapshot below sees every guard engaged at one instant.
            await waitFor(() => uploadSourceButtons().every((button) => button?.disabled)
                && picker()?.querySelector('[data-testid="media-picker-header"]')?.inert
                && picker()?.querySelector('[data-testid="media-picker-upload-action-guard"]')?.inert
                && picker()?.querySelector('[data-testid="media-picker-close"]')
                    ?.getAttribute('aria-disabled') === 'true'
                && picker()?.getAttribute('aria-busy') === 'true',
                'upload-start: all guard conditions hold simultaneously');
            const uploadGuardedWorkspace = [
                picker()?.querySelector('[data-testid="media-picker-source-upload"]'),
                picker()?.querySelector('[data-testid="media-picker-source-url"]'),
                picker()?.querySelector('[data-testid="media-picker-source-storage"]'),
            ].every((button) => button?.disabled)
                && picker()?.querySelector('[data-testid="media-picker-header"]')?.inert
                && picker()?.querySelector('[data-testid="media-picker-upload-action-guard"]')?.inert
                && picker()?.querySelector('[data-testid="media-picker-close"]')
                    ?.getAttribute('aria-disabled') === 'true';
            const uploadKeptTransferControls = ! picker()?.querySelector(
                '[data-testid="media-picker-source-form"]',
            )?.disabled && ! picker()?.querySelector(
                '[data-testid="media-picker-source-form"]',
            )?.closest('[inert]');

            picker().dispatchEvent(new CustomEvent('livewire-upload-finish', {
                detail: uploadDetail,
            }));
            // Settle Livewire before snapshotting: the guard reads below are
            // only meaningful once the workspace is quiet, and the blocks after
            // this one assume the same. Each condition is waited under its own
            // label so a timeout names the condition that never settled,
            // instead of hiding it inside a compound predicate.
            await waitFor(() => window.__mediaPickerPendingRequests === 0,
                'upload-finish: pending requests drained');
            await waitFor(() => ! picker()?.querySelector('[data-testid="media-picker-source-upload"]')?.disabled,
                'upload-finish: source buttons re-enabled');
            await waitFor(() => ! picker()?.querySelector('[data-testid="media-picker-header"]')?.inert,
                'upload-finish: header inert released');
            await waitFor(() => ! picker()?.querySelector('[data-testid="media-picker-upload-action-guard"]')?.inert,
                'upload-finish: action guard inert released');
            await waitFor(() => picker()?.querySelector('[data-testid="media-picker-close"]')
                ?.getAttribute('aria-disabled') !== 'true',
                'upload-finish: close aria-disabled released');
            await waitFor(() => picker()?.getAttribute('aria-busy') !== 'true',
                'upload-finish: aria-busy released');
            // KNOWN registered app defect (contention investigation, 9318e62,
            // docs/research/browser-timeout-contention-investigation.md): the
            // one-shot focus restore can lose to Filament's wire:loading
            // disabled writer, so this condition may time out (~4% quiesced)
            // until the verify-after-focus app fix lands. A timeout here is
            // that defect, not this test — do not paper over it.
            await waitFor(() => picker()?.contains(document.activeElement),
                'upload-finish: focus returned inside the picker');
            // The settle contract is the conjunction: re-wait it whole so the
            // snapshot below sees every condition true at one instant.
            await waitFor(() =>
                window.__mediaPickerPendingRequests === 0
                && ! picker()?.querySelector('[data-testid="media-picker-source-upload"]')?.disabled
                && ! picker()?.querySelector('[data-testid="media-picker-header"]')?.inert
                && ! picker()?.querySelector('[data-testid="media-picker-upload-action-guard"]')?.inert
                && picker()?.querySelector('[data-testid="media-picker-close"]')
                    ?.getAttribute('aria-disabled') !== 'true'
                && picker()?.getAttribute('aria-busy') !== 'true'
                && picker()?.contains(document.activeElement),
                'upload-finish: all settle conditions hold simultaneously');
            const uploadGuardReleased = ! picker()?.querySelector('[data-testid="media-picker-source-upload"]')?.disabled
                && ! picker()?.querySelector('[data-testid="media-picker-header"]')?.inert
                && picker()?.querySelector('[data-testid="media-picker-close"]')
                    ?.getAttribute('aria-disabled') !== 'true';

            // The selection-return guard wiring: the live behavior (held
            // request => inert root, disabled close) is pinned by the
            // dedicated parent-handoff guard test; this snapshot pins that the
            // Alpine bindings implementing it are present on the workspace.
            const guardProbeClose = picker()?.querySelector('[data-testid="media-picker-close"]');
            const requestGuardedClose = picker()?.getAttribute('x-bind:inert') === 'returningSelection'
                && (picker()?.getAttribute('x-bind:aria-busy') ?? '').includes('returningSelection')
                && (guardProbeClose?.getAttribute('x-bind:disabled') ?? '').includes('returningSelection')
                && (guardProbeClose?.getAttribute('x-bind:aria-disabled') ?? '').includes('returningSelection');
            // Read `aria-disabled`, never the `disabled` property: Filament's
            // icon-button ships its own `wire:loading.attr="disabled"`, so that
            // property is also written for any in-flight request on this
            // component and says nothing about the guard. `aria-disabled` is
            // bound only by the picker, so it answers the question being asked.
            const returnGuardReleased = ! picker()?.inert
                && guardProbeClose?.getAttribute('aria-disabled') !== 'true'
                && picker()?.getAttribute('aria-busy') !== 'true';

            return {
                direction: document.documentElement.dir,
                modal_roots: document.querySelectorAll(
                    '[aria-modal="true"].fi-modal-open [data-testid="media-picker"]',
                ).length,
                dialog_roots: document.querySelectorAll('[aria-modal="true"].fi-modal-open').length,
                active_inside_modal: Boolean(dialog()?.contains(document.activeElement)),
                horizontal_overflow: await stableRead(() => document.documentElement.scrollWidth > document.documentElement.clientWidth + 1),
                picker_horizontal_overflow: await stableRead(() => (picker()?.scrollWidth ?? 0) > (picker()?.clientWidth ?? 0) + 1),
                modal_within_viewport: await stableRead(() => {
                    const modalRect = modalWindow?.getBoundingClientRect();

                    return (modalRect?.left ?? -1) >= -1
                        && (modalRect?.right ?? window.innerWidth + 1) <= window.innerWidth + 1;
                }),
                storage_was_lazy: storageWasLazy,
                storage_loaded: storageLoaded,
                upload_panel_persisted: uploadPanelPersisted,
                url_state_preserved: restoredUrlInput.value === retainedUrl,
                backdrop_kept_modal_open: backdropKeptModalOpen,
                escape_kept_modal_open: escapeKeptModalOpen,
                offline_disabled_source: offlineDisabledSource,
                offline_kept_close_available: offlineKeptCloseAvailable,
                online_restored_source: onlineRestoredSource,
                request_guarded_close: requestGuardedClose,
                return_guard_released: returnGuardReleased,
                upload_guarded_workspace: uploadGuardedWorkspace,
                upload_kept_transfer_controls: uploadKeptTransferControls,
                upload_guard_released: uploadGuardReleased,
            };
        }
        JS);

    expect($wide['direction'])->toBe($direction)
        ->and($wide['modal_roots'])->toBe(1, json_encode($wide, JSON_THROW_ON_ERROR))
        ->and($wide['dialog_roots'])->toBe(1)
        ->and($wide['active_inside_modal'])->toBeTrue(json_encode($wide, JSON_THROW_ON_ERROR))
        ->and($wide['horizontal_overflow'])->toBeFalse()
        ->and($wide['picker_horizontal_overflow'])->toBeFalse()
        ->and($wide['modal_within_viewport'])->toBeTrue(json_encode($wide, JSON_THROW_ON_ERROR))
        ->and($wide['storage_was_lazy'])->toBeTrue()
        ->and($wide['storage_loaded'])->toBeTrue()
        ->and($wide['upload_panel_persisted'])->toBeTrue()
        ->and($wide['url_state_preserved'])->toBeTrue()
        ->and($wide['backdrop_kept_modal_open'])->toBeTrue()
        ->and($wide['escape_kept_modal_open'])->toBeTrue()
        ->and($wide['offline_disabled_source'])->toBeTrue()
        ->and($wide['offline_kept_close_available'])->toBeTrue()
        ->and($wide['online_restored_source'])->toBeTrue()
        ->and($wide['request_guarded_close'])->toBeTrue(json_encode($wide, JSON_THROW_ON_ERROR))
        ->and($wide['return_guard_released'])->toBeTrue(json_encode($wide, JSON_THROW_ON_ERROR))
        ->and($wide['upload_guarded_workspace'])->toBeTrue()
        ->and($wide['upload_kept_transfer_controls'])->toBeTrue()
        ->and($wide['upload_guard_released'])->toBeTrue();

    $page->resize(390, 844);
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
            await new Promise((resolve) => setTimeout(resolve, 300));
            const dialog = document.querySelector('[aria-modal="true"].fi-modal-open');
            const picker = dialog?.querySelector('[data-testid="media-picker"]');
            const modalWindow = dialog?.querySelector('.fi-modal-window');
            const grid = picker?.querySelector('.min-h-0.flex-1');
            const gallery = grid?.querySelector('main');
            const sourceRail = grid?.querySelector('aside');
            const pickerRect = picker?.getBoundingClientRect();
            const overflowingElements = Array.from(picker?.querySelectorAll('*') ?? [])
                .filter((element) => {
                    const rect = element.getBoundingClientRect();

                    return rect.left < (pickerRect?.left ?? 0) - 1
                        || rect.right > (pickerRect?.right ?? window.innerWidth) + 1;
                })
                .slice(0, 10)
                .map((element) => ({
                    tag: element.tagName,
                    testid: element.getAttribute('data-testid'),
                    classes: element.className,
                    left: element.getBoundingClientRect().left,
                    right: element.getBoundingClientRect().right,
                    scroll_width: element.scrollWidth,
                    client_width: element.clientWidth,
                }));

            return {
                viewport_width: window.innerWidth,
                direction: document.documentElement.dir,
                modal_roots: document.querySelectorAll(
                    '[aria-modal="true"].fi-modal-open [data-testid="media-picker"]',
                ).length,
                active_inside_modal: Boolean(dialog?.contains(document.activeElement)),
                horizontal_overflow: await stableRead(() => document.documentElement.scrollWidth > document.documentElement.clientWidth + 1),
                picker_horizontal_overflow: await stableRead(() => (picker?.scrollWidth ?? 0) > (picker?.clientWidth ?? 0) + 1),
                picker_scroll_width: picker?.scrollWidth ?? null,
                picker_client_width: picker?.clientWidth ?? null,
                overflowing_elements: overflowingElements,
                modal_within_viewport: await stableRead(() => {
                    const modalRect = modalWindow?.getBoundingClientRect();

                    return (modalRect?.left ?? -1) >= -1
                        && (modalRect?.right ?? window.innerWidth + 1) <= window.innerWidth + 1;
                }),
                owner_single_active_area: Boolean(gallery && sourceRail)
                    && (gallery.classList.contains('hidden') !== sourceRail.classList.contains('hidden')),
                header_sticky: getComputedStyle(
                    picker?.querySelector('[data-testid="media-picker-header"]'),
                ).position === 'sticky',
                owner_footer_absent: picker?.querySelector('[data-testid="media-picker-footer"]') === null,
            };
        }
        JS);

    expect($narrow['viewport_width'])->toBe(390)
        ->and($narrow['direction'])->toBe($direction)
        ->and($narrow['modal_roots'])->toBe(1)
        ->and($narrow['active_inside_modal'])->toBeTrue(json_encode($narrow, JSON_THROW_ON_ERROR))
        ->and($narrow['horizontal_overflow'])->toBeFalse()
        ->and($narrow['picker_horizontal_overflow'])->toBeFalse(json_encode($narrow, JSON_THROW_ON_ERROR))
        ->and($narrow['modal_within_viewport'])->toBeTrue(json_encode($narrow, JSON_THROW_ON_ERROR))
        ->and($narrow['owner_single_active_area'])->toBeTrue(json_encode($narrow, JSON_THROW_ON_ERROR))
        ->and($narrow['header_sticky'])->toBeTrue()
        ->and($narrow['owner_footer_absent'])->toBeTrue();

    $closed = $page->page()->evaluate(<<<'JS'
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
            const started = performance.now();
            document.querySelector(
                '[aria-modal="true"].fi-modal-open [data-testid="media-picker-close"]',
            )?.click();

            while (document.querySelector(
                '[aria-modal="true"].fi-modal-open [data-testid="media-picker"]',
            ) !== null
                && performance.now() - started < 7000) {
                await new Promise((resolve) => setTimeout(resolve, 25));
            }

            while (! document.activeElement?.closest('[data-testid="media-picker-open"]')
                && performance.now() - started < 7000) {
                await new Promise((resolve) => setTimeout(resolve, 25));
            }

            return {
                modal_closed: document.querySelector(
                    '[aria-modal="true"].fi-modal-open [data-testid="media-picker"]',
                ) === null,
                focus_returned: Boolean(document.activeElement?.closest('[data-testid="media-picker-open"]')),
                horizontal_overflow: await stableRead(() => document.documentElement.scrollWidth > document.documentElement.clientWidth + 1),
            };
        }
        JS);

    // Before the js_errors diagnostic below reads the accumulator: that read is
    // capped, so an unstripped artifact could evict a real error from it.
    $resizeObserverArtifacts = stripKnownResizeObserverArtifacts($page);

    expect($closed['modal_closed'])->toBeTrue(json_encode($closed, JSON_THROW_ON_ERROR))
        ->and($closed['focus_returned'])->toBeTrue(json_encode($closed, JSON_THROW_ON_ERROR))
        ->and($closed['horizontal_overflow'])->toBeFalse();

    $acquired = $page->page()->evaluate(<<<'JS'
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

                throw new Error('Timed out while waiting for nested Storage acquisition.');
            };
            const opener = document.querySelector('[data-testid="media-picker-open"]');
            opener.click();
            const picker = await waitFor(() => document.querySelector(
                '[aria-modal="true"].fi-modal-open [data-testid="media-picker"]',
            ));
            await waitFor(() => {
                const childId = picker.closest('[wire\\:id]')?.getAttribute('wire:id');

                return childId && window.Livewire.find(childId);
            });
            await new Promise((resolve) => setTimeout(resolve, 250));
            const storageSource = await waitFor(() => {
                const button = picker.querySelector('[data-testid="media-picker-source-storage"]');

                return button && ! button.disabled ? button : null;
            });
            storageSource.click();
            await waitFor(() => {
                const panel = picker.querySelector('[data-testid="media-picker-panel-storage"]');

                return panel && ! panel.hidden ? panel : null;
            });
            const acquire = await waitFor(() => document.querySelector(
                '[aria-modal="true"].fi-modal-open [data-testid="media-picker-storage-acquire"]:not([disabled])',
            ));
            acquire.click();
            await waitFor(() => document.querySelector(
                '[aria-modal="true"].fi-modal-open [data-testid="media-picker"]',
            ) === null);
            const ownerForm = document.querySelector('[data-testid="media-picker-open"]')
                ?.closest('form');
            const pendingCard = await waitFor(() => ownerForm?.querySelector(
                '[data-testid="owner-image-pending-state"][class*="border-warning"]',
            ));
            await waitFor(() => document.activeElement?.closest('[data-testid="media-picker-open"]'));
            await waitFor(() => window.__mediaPickerPendingRequests === 0);

            return {
                modal_closed: document.querySelector(
                    '[aria-modal="true"].fi-modal-open [data-testid="media-picker"]',
                ) === null,
                focus_returned: Boolean(document.activeElement?.closest('[data-testid="media-picker-open"]')),
                owner_field_updated: Boolean(Array.from(pendingCard.querySelectorAll('button[title]'))
                    .find((button) => button.title.includes('browser-storage.jpg'))),
            };
        }
        JS);

    expect($acquired['modal_closed'])->toBeTrue()
        ->and($acquired['focus_returned'])->toBeTrue()
        ->and($acquired['owner_field_updated'])->toBeTrue(json_encode($acquired, JSON_THROW_ON_ERROR));

    $media = Media::query()->where('path', 'media-imports/browser-storage.jpg')->sole();

    expect($media->mediaAsset)->toBeInstanceOf(MediaAsset::class)
        ->and($media->providerBinding)->toBeInstanceOf(MediaProviderBinding::class)
        ->and($group->refresh()->coverMediaAttachment()->exists())->toBeFalse();

    $saved = $page->page()->evaluate(<<<'JS'
        async () => {
            const ownerRoot = document.querySelector('[data-testid="media-picker-open"]')?.closest('[wire\\:id]');
            const ownerId = ownerRoot?.getAttribute('wire:id');
            const ownerForm = document.querySelector('[data-testid="media-picker-open"]')?.closest('form');

            if (! ownerId) {
                throw new Error('Unable to find the owner Livewire component.');
            }

            const settleStart = performance.now();

            while (window.__mediaPickerPendingRequests !== 0 && performance.now() - settleStart < 5000) {
                await new Promise((resolve) => setTimeout(resolve, 25));
            }

            await window.Livewire.find(ownerId).$call('save');

            const savedStateShown = () => ! ownerForm?.querySelector(
                '[data-testid="owner-image-pending-state"][class*="border-warning"]',
            )
                && Boolean(Array.from(ownerForm?.querySelectorAll(
                    '[data-testid="owner-image-direct-state"] button[title]',
                ) ?? []).find((button) => button.title.includes('browser-storage.jpg')));
            const started = performance.now();

            while (! savedStateShown() && performance.now() - started < 2000) {
                await new Promise((resolve) => setTimeout(resolve, 25));
            }

            return {
                saved: true,
                pending_cleared: ! ownerForm?.querySelector(
                    '[data-testid="owner-image-pending-state"][class*="border-warning"]',
                ),
                direct_shows_media: Boolean(Array.from(ownerForm?.querySelectorAll(
                    '[data-testid="owner-image-direct-state"] button[title]',
                ) ?? []).find((button) => button.title.includes('browser-storage.jpg'))),
                field_errors: Array.from(document.querySelectorAll(
                    '[class*="error-message"], [data-validation-error]',
                )).map((element) => element.textContent.replace(/\s+/g, ' ').trim()).slice(0, 3),
                notifications: Array.from(document.querySelectorAll('.fi-no-notification'))
                    .map((element) => element.textContent.replace(/\s+/g, ' ').trim())
                    .slice(0, 3),
                pending_card: ownerForm?.querySelector('[data-testid="owner-image-pending-state"]')
                    ?.textContent.replace(/\s+/g, ' ').trim() ?? null,
                pending_copies: document.querySelectorAll('[data-testid="owner-image-pending-state"]').length,
                js_errors: (window.__pestBrowser?.jsErrors ?? [])
                    .map((error) => String(error.message ?? error)).slice(0, 5),
            };
        }
        JS);

    if (! ($saved['pending_cleared'] && $saved['direct_shows_media'])) {
        // The save response was rendered post-persist (pinned by the
        // owner-image lifecycle feature test), but the client morph can lag
        // under load; a fresh page serve shows the authoritative state.
        $saved['required_refresh'] = true;
        $page->refresh();
        $reloaded = $page->page()->evaluate(<<<'JS'
            async () => {
                const started = performance.now();

                while (! document.querySelector('form [data-testid="owner-image-direct-state"]')
                    && performance.now() - started < 7000) {
                    await new Promise((resolve) => setTimeout(resolve, 25));
                }

                const ownerForm = document.querySelector('[data-testid="media-picker-open"]')?.closest('form');

                return {
                    pending_cleared: ! ownerForm?.querySelector(
                        '[data-testid="owner-image-pending-state"][class*="border-warning"]',
                    ),
                    direct_shows_media: Boolean(Array.from(ownerForm?.querySelectorAll(
                        '[data-testid="owner-image-direct-state"] button[title]',
                    ) ?? []).find((button) => button.title.includes('browser-storage.jpg'))),
                };
            }
            JS);
        $saved['pending_cleared'] = $reloaded['pending_cleared'];
        $saved['direct_shows_media'] = $reloaded['direct_shows_media'];
    }

    $saved['resize_observer_artifacts'] = $resizeObserverArtifacts;

    expect($saved['saved'])->toBeTrue()
        ->and($saved['pending_cleared'])->toBeTrue(json_encode($saved, JSON_THROW_ON_ERROR))
        ->and($saved['direct_shows_media'])->toBeTrue(json_encode($saved, JSON_THROW_ON_ERROR))
        ->and($group->refresh()->coverMediaAttachment()->value('media_id'))->toBe($media->getKey());

    assertNoUnexpectedJavaScriptErrors($page);
})->with([
    'Hebrew RTL' => ['he', 'rtl'],
    'English LTR' => ['en', 'ltr'],
]);

it('guards close while the parent accepts a returned selection', function (): void {
    app()->setLocale('he');
    $media = Media::factory()->create([
        'title' => 'Delayed parent handoff',
    ]);
    Storage::disk('public')->put(
        $media->path,
        base64_decode((string) file_get_contents(base_path('tests/Fixtures/media/valid.jpg.base64')), true),
    );
    $group = ContentGroup::factory()->create([
        'title' => 'Parent handoff guard',
    ]);
    $page = visit(ContentGroupResource::getUrl('edit', ['record' => $group]))
        ->resize(1280, 900);
    $script = str_replace('__MEDIA_ID__', (string) $media->getKey(), <<<'JS'
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

                throw new Error('Timed out while testing the parent selection handoff.');
            };
            const picker = () => document.querySelector(
                '[aria-modal="true"].fi-modal-open [data-testid="media-picker"]',
            );
            const opener = document.querySelector('[data-testid="media-picker-open"]');
            opener.click();
            await waitFor(() => picker());
            await new Promise((resolve) => setTimeout(resolve, 250));

            const originalFetch = window.fetch.bind(window);
            let releaseRequest = null;
            let requestHeld = false;
            window.fetch = (...arguments_) => {
                if (! requestHeld) {
                    requestHeld = true;

                    return new Promise((resolve, reject) => {
                        releaseRequest = () => {
                            window.fetch = originalFetch;
                            originalFetch(...arguments_).then(resolve, reject);
                        };
                    });
                }

                return originalFetch(...arguments_);
            };
            picker().dispatchEvent(new CustomEvent('insert-media', {
                bubbles: true,
                detail: {
                    mediaId: __MEDIA_ID__,
                    mediaIds: [__MEDIA_ID__],
                },
            }));
            await waitFor(() => requestHeld && releaseRequest);

            const pickerRoot = picker();
            const close = pickerRoot.querySelector('[data-testid="media-picker-close"]');
            const guarded = pickerRoot.inert
                && close.disabled
                && close.getAttribute('aria-disabled') === 'true'
                && pickerRoot.getAttribute('aria-busy') === 'true';
            let remainedOpenAfterCloseAttempt = false;

            if (guarded) {
                close.click();
                await new Promise((resolve) => setTimeout(resolve, 100));
                remainedOpenAfterCloseAttempt = Boolean(picker());
            }

            releaseRequest();
            await waitFor(() => picker() === null);
            await waitFor(() => document.querySelector(
                '[data-testid="owner-image-pending-state"][class*="border-warning"]',
            ));
            await waitFor(
                () => document.activeElement?.closest('[data-testid="media-picker-open"]'),
            );

            return {
                close_guarded: guarded,
                focus_returned: Boolean(document.activeElement?.closest('[data-testid="media-picker-open"]')),
                modal_closed_after_handoff: picker() === null,
                remained_open_after_close_attempt: remainedOpenAfterCloseAttempt,
                selection_returned: Boolean(document.querySelector(
                    '[data-testid="owner-image-pending-state"][class*="border-warning"]',
                )),
            };
        }
        JS);

    $result = $page->page()->evaluate($script);

    expect($result['close_guarded'])->toBeTrue(json_encode($result, JSON_THROW_ON_ERROR))
        ->and($result['remained_open_after_close_attempt'])->toBeTrue(json_encode($result, JSON_THROW_ON_ERROR))
        ->and($result['modal_closed_after_handoff'])->toBeTrue(json_encode($result, JSON_THROW_ON_ERROR))
        ->and($result['selection_returned'])->toBeTrue(json_encode($result, JSON_THROW_ON_ERROR))
        ->and($result['focus_returned'])->toBeTrue(json_encode($result, JSON_THROW_ON_ERROR));

    assertNoUnexpectedJavaScriptErrors($page);
});

it('closes a field picker locally while truly offline and reconciles the action on reconnect', function (): void {
    app()->setLocale('he');
    $group = ContentGroup::factory()->create([
        'title' => 'Offline media picker close',
    ]);
    $page = visit(ContentGroupResource::getUrl('edit', ['record' => $group]))
        ->resize(1280, 900);

    $openPicker = <<<'JS'
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

                throw new Error('Timed out while opening the offline media picker.');
            };
            const visibleOpener = () => Array.from(document.querySelectorAll(
                '[data-testid="media-picker-open"]',
            )).find((element) => (element.offsetParent !== null) && ! element.closest('[inert]'));
            const started = performance.now();
            const opener = await waitFor(() => visibleOpener());

            opener.click();

            while (performance.now() - started < 7000) {
                const close = document.querySelector(
                    '[aria-modal="true"].fi-modal-open [data-testid="media-picker-close"]',
                );
                const wrapper = close?.closest('[x-data*="offlineClosePending"]');

                if (
                    wrapper
                    && window.Alpine
                    && (typeof window.Alpine.$data(wrapper).offlineClosePending === 'boolean')
                ) {
                    await new Promise((resolve) => setTimeout(resolve, 250));

                    return;
                }

                await new Promise((resolve) => setTimeout(resolve, 25));
            }

            throw new Error('Timed out while waiting for the offline close handler.');
        }
        JS;
    $page->page()->evaluate($openPicker);

    setMediaPickerBrowserOffline($page, true);

    $offline = $page->page()->evaluate(<<<'JS'
        async () => {
            const waitFor = async (callback, step, timeout = 7000) => {
                const started = performance.now();

                while (performance.now() - started < timeout) {
                    const result = callback();

                    if (result) {
                        return result;
                    }

                    await new Promise((resolve) => setTimeout(resolve, 25));
                }

                throw new Error(`timeout at step: ${step}`);
            };
            const picker = () => document.querySelector(
                '[aria-modal="true"].fi-modal-open [data-testid="media-picker"]',
            );

            await waitFor(() => {
                const notice = picker()?.querySelector('[wire\\:offline]');

                return notice && getComputedStyle(notice).display !== 'none';
            }, 'offline-close: wire:offline notice visible');

            const close = picker()?.querySelector('[data-testid="media-picker-close"]');
            const modal = close?.closest('[data-fi-modal-id]');
            const modalId = modal?.id ?? null;
            const actionMatch = modalId?.match(/^(.*-action-)(\d+)$/);
            const parentId = actionMatch && (Number(actionMatch[2]) > 0)
                ? actionMatch[1] + (Number(actionMatch[2]) - 1)
                : null;
            const modalState = modal && window.Alpine ? window.Alpine.$data(modal) : null;
            let offlineRequestCount = 0;
            const stopRecordingRequests = window.Livewire.hook('request', () => {
                offlineRequestCount++;
            });
            close?.click();

            await waitFor(() => picker() === null,
                'offline-close: picker closed');
            await waitFor(() => parentId === null
                || document.getElementById(parentId)?.classList.contains('fi-modal-open'),
                'offline-close: parent modal restored');
            await waitFor(() => document.activeElement?.closest('[data-testid="media-picker-open"]'),
                'offline-close: focus returned to the opener');

            await new Promise((resolve) => setTimeout(resolve, 100));
            stopRecordingRequests();
            const parent = parentId ? document.getElementById(parentId) : null;
            const parentState = parent && window.Alpine ? window.Alpine.$data(parent) : null;

            return {
                child_scroll_lock_released: modalState?.isHoldingScrollLock === false,
                child_trap_released: modalState?.isTrapActive === false,
                closed_while_offline: picker() === null,
                correct_remaining_scroll_lock: parentId === null
                    ? document.documentElement.style.overflow !== 'hidden'
                    : (
                        parentState?.isHoldingScrollLock === true
                        && document.documentElement.style.overflow === 'hidden'
                    ),
                focus_returned: Boolean(document.activeElement?.closest('[data-testid="media-picker-open"]')),
                navigator_offline: navigator.onLine === false,
                offline_request_count: offlineRequestCount,
                parent_open: parentId === null
                    || document.getElementById(parentId)?.classList.contains('fi-modal-open'),
            };
        }
        JS);

    trackMediaPickerBrowserRequests($page);

    setMediaPickerBrowserOffline($page, false);

    $reconnected = $page->page()->evaluate(<<<'JS'
        async () => {
            const waitFor = async (callback, step, timeout = 7000) => {
                const started = performance.now();

                while (performance.now() - started < timeout) {
                    const result = callback();

                    if (result) {
                        return result;
                    }

                    await new Promise((resolve) => setTimeout(resolve, 25));
                }

                throw new Error(`timeout at step: ${step}`);
            };
            const openPicker = () => document.querySelector(
                '[aria-modal="true"].fi-modal-open [data-testid="media-picker"]',
            );
            const visibleOpener = () => Array.from(document.querySelectorAll(
                '[data-testid="media-picker-open"]',
            )).find((element) => (element.offsetParent !== null) && ! element.closest('[inert]'));
            const requestsSettledAfter = (completed) => (
                window.__mediaPickerCompletedRequests > completed
                && window.__mediaPickerPendingRequests === 0
            );

            await waitFor(() => navigator.onLine, 'reconnect: navigator back online');
            await waitFor(() => requestsSettledAfter(0), 'reconnect: initial requests settled');
            await waitFor(() => document.querySelector('[data-testid="media-picker"]') === null,
                'reconnect: previous picker fully closed');
            const opener = await waitFor(() => visibleOpener(), 'reconnect: visible opener available');
            let completed = window.__mediaPickerCompletedRequests;
            opener.click();
            await waitFor(() => openPicker(), 'reconnect: picker reopened');
            await waitFor(() => requestsSettledAfter(completed), 'reconnect: reopen requests settled');
            await new Promise((resolve) => setTimeout(resolve, 250));
            const reopenedCount = document.querySelectorAll(
                '[aria-modal="true"].fi-modal-open [data-testid="media-picker"]',
            ).length;
            completed = window.__mediaPickerCompletedRequests;
            openPicker().querySelector('[data-testid="media-picker-close"]')?.click();
            await waitFor(() => openPicker() === null, 'reconnect: picker closed again');
            await waitFor(() => requestsSettledAfter(completed), 'reconnect: close requests settled');
            await waitFor(
                () => document.activeElement?.closest('[data-testid="media-picker-open"]'),
                'reconnect: focus returned to the opener',
            );
            const focusReturned = Boolean(
                document.activeElement?.closest('[data-testid="media-picker-open"]'),
            );
            const parent = Array.from(document.querySelectorAll(
                '[aria-modal="true"].fi-modal-open',
            )).find((dialog) => dialog.querySelector('[data-testid="media-picker-open"]'));

            if (parent) {
                const cancel = Array.from(parent.querySelectorAll(
                    '.fi-modal-footer-actions button',
                )).find((button) => button.type !== 'submit');

                if (! cancel) {
                    throw new Error('Unable to find the restored parent action cancel button.');
                }

                completed = window.__mediaPickerCompletedRequests;
                cancel.click();
                await waitFor(() => document.querySelector('[aria-modal="true"].fi-modal-open') === null,
                    'reconnect: parent action modal closed');
                await waitFor(() => requestsSettledAfter(completed), 'reconnect: parent close requests settled');
            }

            await waitFor(() => document.documentElement.style.overflow !== 'hidden',
                'reconnect: scroll lock released');

            return {
                parent_closed: document.querySelector('[aria-modal="true"].fi-modal-open') === null,
                reopened_count: reopenedCount,
                focus_returned: focusReturned,
                scroll_lock_released: document.documentElement.style.overflow !== 'hidden',
            };
        }
        JS);

    expect($offline['navigator_offline'])->toBeTrue()
        ->and($offline['offline_request_count'])->toBe(0, json_encode($offline, JSON_THROW_ON_ERROR))
        ->and($offline['closed_while_offline'])->toBeTrue(json_encode($offline, JSON_THROW_ON_ERROR))
        ->and($offline['child_trap_released'])->toBeTrue(json_encode($offline, JSON_THROW_ON_ERROR))
        ->and($offline['child_scroll_lock_released'])->toBeTrue(json_encode($offline, JSON_THROW_ON_ERROR))
        ->and($offline['correct_remaining_scroll_lock'])->toBeTrue(json_encode($offline, JSON_THROW_ON_ERROR))
        ->and($offline['parent_open'])->toBeTrue(json_encode($offline, JSON_THROW_ON_ERROR))
        ->and($offline['focus_returned'])->toBeTrue(json_encode($offline, JSON_THROW_ON_ERROR))
        ->and($reconnected['reopened_count'])->toBe(1)
        ->and($reconnected['focus_returned'])->toBeTrue()
        ->and($reconnected['parent_closed'])->toBeTrue()
        ->and($reconnected['scroll_lock_released'])->toBeTrue();

    assertNoUnexpectedJavaScriptErrors($page);
});

it('returns acquired media to the inline owner action while attachment stays pending until save', function (): void {
    app()->setLocale('he');
    Storage::disk('public')->put(
        'media-imports/browser-nested-storage.jpg',
        base64_decode((string) file_get_contents(base_path('tests/Fixtures/media/valid.jpg.base64')), true),
    );
    $group = ContentGroup::factory()->create([
        'title' => 'Nested media picker action',
    ]);

    $page = visit(ContentGroupResource::getUrl('edit', ['record' => $group]))
        ->resize(1280, 900);

    trackMediaPickerBrowserRequests($page);

    $firstSelection = $page->page()->evaluate(<<<'JS'
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

                throw new Error('Timed out while waiting for the nested media action.');
            };
            const triggerFor = (actionName) => Array.from(document.querySelectorAll('button, a'))
                .find((element) => Array.from(element.attributes)
                    .some((attribute) => attribute.value.includes(actionName)));
            const picker = () => document.querySelector(
                '[aria-modal="true"].fi-modal-open [data-testid="media-picker"]',
            );
            const currentWorkspace = () => {
                const workspace = picker();
                const childId = workspace?.getAttribute('wire:id');
                const component = childId ? window.Livewire.find(childId) : null;

                return workspace?.isConnected
                    && ! workspace.inert
                    && component?.el === workspace
                    ? workspace
                    : null;
            };
            const requestsSettledAfter = (completed) => (
                window.__mediaPickerCompletedRequests > completed
                && window.__mediaPickerPendingRequests === 0
            );
            const outerDialog = () => Array.from(document.querySelectorAll(
                '[aria-modal="true"].fi-modal-open',
            )).find((dialog) => dialog.querySelector('[data-testid="media-picker"]'));

            let completed = window.__mediaPickerCompletedRequests;
            triggerFor('chooseContentGroupCover')?.click();
            await waitFor(() => outerDialog());
            await waitFor(() => currentWorkspace());
            await waitFor(() => requestsSettledAfter(completed));
            completed = window.__mediaPickerCompletedRequests;
            currentWorkspace()?.querySelector('[data-testid="media-picker-source-storage"]')?.click();
            await waitFor(() => currentWorkspace()?.textContent.includes('browser-nested-storage.jpg'));
            await waitFor(() => requestsSettledAfter(completed));
            const acquire = await waitFor(() => currentWorkspace()?.querySelector(
                '[data-testid="media-picker-storage-acquire"]:not([disabled])',
            ));
            completed = window.__mediaPickerCompletedRequests;
            acquire.click();

            const restoredOuter = await waitFor(() => outerDialog());
            const pending = await waitFor(() => {
                const state = restoredOuter.querySelector('[data-testid="owner-image-pending-state"]');

                return state?.querySelector('button[title*="browser-nested-storage.jpg"]')
                    ? state
                    : null;
            });
            await waitFor(() => requestsSettledAfter(completed));

            return {
                parent_remained_open: restoredOuter.classList.contains('fi-modal-open'),
                selection_returned: Boolean(pending.querySelector('button[title*="browser-nested-storage.jpg"]')),
                single_dialog: document.querySelectorAll('[aria-modal="true"].fi-modal-open').length === 1,
                picker_remained_inline: document.querySelectorAll('[data-testid="media-picker"]').length === 1,
                picker_close_absent: ! restoredOuter.querySelector('[data-testid="media-picker-close"]'),
                generic_selected_summary_absent: ! restoredOuter.querySelector(
                    '[data-testid="media-picker-selected-item"]',
                ),
                failed_requests: window.__mediaPickerFailedRequests,
            };
        }
        JS);

    expect($firstSelection['parent_remained_open'])->toBeTrue(json_encode($firstSelection, JSON_THROW_ON_ERROR))
        ->and($firstSelection['selection_returned'])->toBeTrue(json_encode($firstSelection, JSON_THROW_ON_ERROR))
        ->and($firstSelection['single_dialog'])->toBeTrue(json_encode($firstSelection, JSON_THROW_ON_ERROR))
        ->and($firstSelection['picker_remained_inline'])->toBeTrue(json_encode($firstSelection, JSON_THROW_ON_ERROR))
        ->and($firstSelection['picker_close_absent'])->toBeTrue()
        ->and($firstSelection['generic_selected_summary_absent'])->toBeTrue()
        ->and($firstSelection['failed_requests'])->toBe(0, json_encode($firstSelection, JSON_THROW_ON_ERROR));

    $media = Media::query()->where('path', 'media-imports/browser-nested-storage.jpg')->sole();

    expect($media->mediaAsset)->toBeInstanceOf(MediaAsset::class)
        ->and($media->providerBinding)->toBeInstanceOf(MediaProviderBinding::class)
        ->and($group->refresh()->coverMediaAttachment()->exists())->toBeFalse();

    $cancelled = $page->page()->evaluate(<<<'JS'
        async () => {
            const started = performance.now();
            const completed = window.__mediaPickerCompletedRequests;
            const outer = Array.from(document.querySelectorAll(
                '[aria-modal="true"].fi-modal-open',
            )).find((dialog) => dialog.querySelector('[data-testid="media-picker"]'));
            const cancel = Array.from(outer?.querySelectorAll(
                '.fi-modal-footer-actions button',
            ) ?? []).find((button) => button.type !== 'submit');

            if (! cancel) {
                throw new Error('Unable to find the outer image action cancel button.');
            }

            cancel.click();

            while (document.querySelector('[aria-modal="true"].fi-modal-open') !== null
                && performance.now() - started < 7000) {
                await new Promise((resolve) => setTimeout(resolve, 25));
            }

            while ((window.__mediaPickerCompletedRequests <= completed
                || window.__mediaPickerPendingRequests !== 0)
                && performance.now() - started < 7000) {
                await new Promise((resolve) => setTimeout(resolve, 25));
            }

            return {
                outer_closed: document.querySelector('[aria-modal="true"].fi-modal-open') === null,
                request_settled: window.__mediaPickerCompletedRequests > completed
                    && window.__mediaPickerPendingRequests === 0,
            };
        }
        JS);

    expect($cancelled['outer_closed'])->toBeTrue(json_encode($cancelled, JSON_THROW_ON_ERROR))
        ->and($cancelled['request_settled'])->toBeTrue(json_encode($cancelled, JSON_THROW_ON_ERROR))
        ->and($group->refresh()->coverMediaAttachment()->exists())->toBeFalse()
        ->and(Media::query()->whereKey($media)->exists())->toBeTrue();

    $saved = $page->page()->evaluate(<<<'JS'
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

                throw new Error('Timed out while saving the nested media action.');
            };
            const triggerFor = (actionName) => Array.from(document.querySelectorAll('button, a'))
                .find((element) => Array.from(element.attributes)
                    .some((attribute) => attribute.value.includes(actionName)));
            const picker = () => document.querySelector(
                '[aria-modal="true"].fi-modal-open [data-testid="media-picker"]',
            );
            const currentWorkspace = () => {
                const workspace = picker();
                const childId = workspace?.getAttribute('wire:id');
                const component = childId ? window.Livewire.find(childId) : null;

                return workspace?.isConnected
                    && ! workspace.inert
                    && component?.el === workspace
                    ? workspace
                    : null;
            };
            const requestsSettledAfter = (completed) => (
                window.__mediaPickerCompletedRequests > completed
                && window.__mediaPickerPendingRequests === 0
            );
            const outerDialog = () => Array.from(document.querySelectorAll(
                '[aria-modal="true"].fi-modal-open',
            )).find((dialog) => dialog.querySelector('[data-testid="media-picker"]'));

            let completed = window.__mediaPickerCompletedRequests;
            triggerFor('chooseContentGroupCover')?.click();
            await waitFor(() => outerDialog());
            await waitFor(() => currentWorkspace());
            await waitFor(() => requestsSettledAfter(completed));
            completed = window.__mediaPickerCompletedRequests;
            currentWorkspace()?.querySelector('[data-testid="media-picker-source-storage"]')?.click();
            await waitFor(() => currentWorkspace()?.textContent.includes('browser-nested-storage.jpg'));
            await waitFor(() => requestsSettledAfter(completed));
            const acquire = await waitFor(() => currentWorkspace()?.querySelector(
                '[data-testid="media-picker-storage-acquire"]:not([disabled])',
            ));
            completed = window.__mediaPickerCompletedRequests;
            acquire.click();
            const restoredOuter = await waitFor(() => outerDialog());
            await waitFor(() => restoredOuter.querySelector(
                '[data-testid="owner-image-pending-state"]',
            )?.querySelector('button[title*="browser-nested-storage.jpg"]'));
            await waitFor(() => requestsSettledAfter(completed));
            const submit = restoredOuter.querySelector('.fi-modal-footer-actions button[type="submit"]');

            if (! submit) {
                throw new Error('Unable to find the outer image action submit button.');
            }

            completed = window.__mediaPickerCompletedRequests;
            submit.click();
            await waitFor(() => outerDialog() === undefined);
            await waitFor(() => requestsSettledAfter(completed));

            return {
                outer_closed: outerDialog() === undefined,
                failed_requests: window.__mediaPickerFailedRequests,
            };
        }
        JS);

    expect($saved['outer_closed'])->toBeTrue(json_encode($saved, JSON_THROW_ON_ERROR))
        ->and($saved['failed_requests'])->toBe(0, json_encode($saved, JSON_THROW_ON_ERROR))
        ->and($group->refresh()->coverMediaAttachment()->value('media_id'))->toBe($media->getKey());

    assertNoUnexpectedJavaScriptErrors($page);
});

it('keeps a nested media item action owned by the picker across repeated modal lifecycles', function (): void {
    app()->setLocale('he');
    $media = Media::factory()->create([
        'directory' => 'content-groups/covers',
        'path' => 'content-groups/covers/browser-nested-edit.jpg',
        'name' => 'browser-nested-edit',
        'title' => 'Nested editable media',
    ]);
    Storage::disk('public')->put(
        $media->path,
        base64_decode((string) file_get_contents(base_path('tests/Fixtures/media/valid.jpg.base64')), true),
    );
    $group = ContentGroup::factory()->create([
        'title' => 'Nested item action owner',
    ]);

    $page = visit(ContentGroupResource::getUrl('edit', ['record' => $group]))
        ->resize(1280, 900);

    trackMediaPickerBrowserRequests($page);

    $result = $page->page()->evaluate(<<<'JS'
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

                throw new Error('Timed out while waiting for a nested picker item action.');
            };
            const picker = () => document.querySelector(
                '[aria-modal="true"].fi-modal-open [data-testid="media-picker"]',
            );
            const currentWorkspace = () => {
                const workspace = picker();
                const childId = workspace?.getAttribute('wire:id');
                const component = childId ? window.Livewire.find(childId) : null;

                return workspace?.isConnected
                    && ! workspace.inert
                    && component?.el === workspace
                    ? workspace
                    : null;
            };
            const requestsSettledAfter = (completed) => (
                window.__mediaPickerCompletedRequests > completed
                && window.__mediaPickerPendingRequests === 0
            );
            const childDialog = () => Array.from(document.querySelectorAll(
                '[aria-modal="true"].fi-modal-open',
            )).find((dialog) => ! dialog.querySelector('[data-testid="media-picker"]'));
            const closeChild = async () => {
                const dialog = await waitFor(() => childDialog());
                const completed = window.__mediaPickerCompletedRequests;
                const cancel = Array.from(dialog.querySelectorAll(
                    '.fi-modal-footer-actions button',
                )).find((button) => button.type !== 'submit');

                if (! cancel) {
                    throw new Error('Unable to find the nested item action cancel button.');
                }

                cancel.click();
                await waitFor(() => childDialog() === undefined);
                await waitFor(() => picker());
                await waitFor(() => requestsSettledAfter(completed));
            };
            const openDetails = async () => {
                await waitFor(() => currentWorkspace());
                const detailsTrigger = await waitFor(() => currentWorkspace()?.querySelector(
                    '[data-testid^="media-picker-owner-details-"]',
                ));
                const completed = window.__mediaPickerCompletedRequests;
                detailsTrigger.click();

                const dialog = await waitFor(() => {
                    const dialog = childDialog();

                    return dialog?.textContent.includes('Nested editable media')
                        || dialog?.textContent.includes('browser-nested-edit')
                        ? dialog
                        : null;
                });
                await waitFor(() => requestsSettledAfter(completed));

                return dialog;
            };

            let completed = window.__mediaPickerCompletedRequests;
            document.querySelector('[data-testid="media-picker-open"]')?.click();
            await waitFor(() => currentWorkspace());
            await waitFor(() => requestsSettledAfter(completed));

            const firstDialog = await openDetails();
            const firstChildOpened = firstDialog.classList.contains('fi-modal-open');
            const firstOpenDialogCount = document.querySelectorAll(
                '[aria-modal="true"].fi-modal-open',
            ).length;
            await closeChild();
            const pickerSurvivedFirstClose = Boolean(picker());

            const secondDialog = await openDetails();
            const secondChildOpened = secondDialog.classList.contains('fi-modal-open');
            const secondOpenDialogCount = document.querySelectorAll(
                '[aria-modal="true"].fi-modal-open',
            ).length;
            await closeChild();
            const pickerSurvivedSecondClose = Boolean(picker());

            completed = window.__mediaPickerCompletedRequests;
            picker()?.querySelector('[data-testid="media-picker-close"]')?.click();
            await waitFor(() => picker() === null);
            await waitFor(() => requestsSettledAfter(completed));

            return {
                first_child_opened: firstChildOpened,
                second_child_opened: secondChildOpened,
                first_open_dialog_count: firstOpenDialogCount,
                second_open_dialog_count: secondOpenDialogCount,
                picker_survived_first_close: pickerSurvivedFirstClose,
                picker_survived_second_close: pickerSurvivedSecondClose,
                picker_closed_explicitly: picker() === null,
            };
        }
        JS);

    expect($result['first_child_opened'])->toBeTrue(json_encode($result, JSON_THROW_ON_ERROR))
        ->and($result['second_child_opened'])->toBeTrue(json_encode($result, JSON_THROW_ON_ERROR))
        ->and($result['first_open_dialog_count'])->toBe(2)
        ->and($result['second_open_dialog_count'])->toBe(2)
        ->and($result['picker_survived_first_close'])->toBeTrue()
        ->and($result['picker_survived_second_close'])->toBeTrue()
        ->and($result['picker_closed_explicitly'])->toBeTrue();

    assertNoUnexpectedJavaScriptErrors($page);
});
