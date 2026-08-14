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

/**
 * PHPStan reports this should return `Webpage`, and PHPStan is WRONG — verified
 * by breaking it: declaring `Webpage` throws at runtime with
 * "Return value must be of type Webpage, AwaitableWebpage returned". The two are
 * unrelated classes, not parent and child, so the analyser's inference of the
 * `visit()->resize()` chain does not match what the chain actually produces.
 *
 * Left as `AwaitableWebpage`, which is what runs. The `return.type` finding on
 * this line is a false positive; do not "fix" it without running the test — a
 * PHP return type is enforced at runtime, so a wrong one is a crash, not a lint.
 */
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
            //
            // `disabled` is not the only thing that refuses focus here, and the
            // other one is invisible to this query: the close button lives in
            // the header, which carries x-bind:inert="uploading ||
            // returningSelection" — a DIFFERENT Alpine binding from the
            // aria-busy waited on above, dropped on its own tick. Under load
            // the header is still inert once aria-busy has gone, focus() on an
            // inert subtree silently no-ops, <body> stays active, and the
            // restore then correctly places focus on the upload source. The old
            // unverified focus() plus a fixed 600ms read recorded that as "the
            // choice was taken away" — a choice that was never made.
            const chosen = await waitFor(
                () => {
                    const close = picker()?.querySelector('[data-testid="media-picker-close"]');

                    return close && ! close.disabled ? close : null;
                },
                'waiting for a focusable control',
            );

            // Retried until it lands, because a person whose keypress hits an
            // inert control presses again rather than giving up. Until this
            // holds there is no deliberate choice for the restore to leave
            // alone, so asserting on one would assert on nothing.
            await waitFor(
                () => {
                    if (document.activeElement !== chosen) {
                        chosen.focus();
                    }

                    return document.activeElement === chosen;
                },
                'waiting for the deliberate focus choice to take effect',
            );

            // Anti-vacuity: the restore drives itself for 120 animation frames
            // from the settle, and only a choice made while that loop is still
            // running can be taken away. Read once, asserted by the caller, so
            // a run whose setup drifted past the window fails as a setup
            // problem instead of passing with nothing to prove.
            const windowLiveAtChoice = window.Alpine.$data(picker()).focusReturnFrames <= 120;

            // Frames, not milliseconds: the restore is frame-driven, so 150
            // frames outlast its 120 however slow the machine is, where a fixed
            // delay samples one instant of a window that is still live and
            // misses a steal on either side of it. Alpine and the counter are
            // read without optional chaining for the same reason
            // stripKnownResizeObserverArtifacts() is — a moved internal must
            // fail loudly here, not quietly turn this into a test that watches
            // nothing.
            //
            // What is asserted is the property returnUploadFocus() actually
            // promises, which is narrower than this test's name suggests: once
            // focus has come back into the workspace, the restore leaves a
            // deliberate choice alone — but it explicitly re-places focus when
            // `active === document.body`, treating that as a genuine drop. So a
            // choice that is itself invalidated (the second writer disables the
            // control, or re-inerts its header) blurs to <body> and IS
            // recovered, by design. Recording the difference instead of
            // collapsing it is what makes this test true under load: measured
            // 2026-08-14 at 48 spinners, the sequence was
            // `stolen_by: BODY` -> `active_element: media-picker-source-upload`,
            // i.e. the documented recovery, not a stolen choice.
            const canHoldFocus = (element) => Boolean(
                element?.isConnected
                && ! element.disabled
                && ! element.closest('[inert]')
                && element.getClientRects().length,
            );

            /*
             * Focusability is read in a `blur` handler, not on the next frame,
             * and that is the whole correctness of this test. The second writer
             * disables (or re-inerts) the control, the browser blurs it to
             * <body>, the restore recovers, and the writer clears the attribute
             * again — all inside one frame. A frame-granularity read therefore
             * finds the control focusable again and blames the restore for a
             * drop it only recovered from. Measured: a frame-sampled version of
             * this check reported `stolen_from_live_choice: true` with
             * `frames_held: 0` at 48 spinners. `blur` fires synchronously at the
             * instant focus is lost, which is the only moment the answer is
             * true.
             */
            let lostWhileFocusable = false;
            let losses = 0;

            chosen.addEventListener('blur', () => {
                losses += 1;
                lostWhileFocusable = lostWhileFocusable || canHoldFocus(chosen);
            });

            let stolenBy = null;
            let framesHeld = 0;

            for (let frame = 0; frame < 150 && picker()?.isConnected; frame++) {
                // 250ms escape hatch: an occluded renderer stops firing frames,
                // and this must degrade to a shorter watch, never hang (S1b).
                await new Promise((resolve) => {
                    requestAnimationFrame(resolve);
                    setTimeout(resolve, 250);
                });

                if (document.activeElement === chosen) {
                    framesHeld += 1;

                    continue;
                }

                if (stolenBy === null) {
                    // Read focusability at the instant of the steal, not after:
                    // the second writer's attribute is often gone again a frame
                    // later, and a late read would blame the restore for a drop
                    // it only recovered from.
                    stolenWhileFocusable = canHoldFocus(chosen);
                    stolenBy = document.activeElement?.getAttribute?.('data-testid')
                        || document.activeElement?.id
                        || document.activeElement?.tagName
                        || 'unknown';
                }
            }

            return {
                stolen_from_live_choice: stolenBy !== null && stolenWhileFocusable,
                choice_invalidated: stolenBy !== null && ! stolenWhileFocusable,
                kept_choice: stolenBy === null && document.activeElement === chosen,
                frames_held: framesHeld,
                stolen_by: stolenBy,
                window_live_at_choice: windowLiveAtChoice,
                restore_frames: window.Alpine.$data(picker()).focusReturnFrames,
                active_element: document.activeElement?.getAttribute?.('data-testid')
                    || document.activeElement?.id
                    || document.activeElement?.tagName,
            };
        }
        JS);

    expect($result['window_live_at_choice'])->toBeTrue(
        'The restore loop had already finished before the deliberate choice was made, so nothing could have taken it away and this run proved nothing: '
        .json_encode($result, JSON_THROW_ON_ERROR),
    )
        ->and($result['frames_held'])->toBeGreaterThan(0, // anti-vacuity: a choice invalidated on frame one proves nothing either
            'The deliberate choice never survived a single frame, so the restore was never given anything to leave alone: '
            .json_encode($result, JSON_THROW_ON_ERROR),
        )
        ->and($result['stolen_from_live_choice'])->toBeFalse(
            'The restore moved focus away from a control that could still hold it: '
            .json_encode($result, JSON_THROW_ON_ERROR),
        );

    assertNoUnexpectedJavaScriptErrors($page);
});

/*
 * The load-sensitive sibling above, made deterministic.
 *
 * `it_leaves_a_deliberate_focus_choice_alone_when_the_upload_settles` is one of
 * the four canaries that fail first whenever the machine is slow (R4 row 8).
 * Reproduced 2026-08-14 under CPU contention at 16 and 48 spinners on 8 cores —
 * `kept_choice: false, active_element: media-picker-source-upload`, the exact
 * historical message — and NOT reproduced at 32, twice, which is why a repeat
 * count is not a proof and this test exists instead. It holds the hostile
 * condition open by frame rather than waiting for a slow machine to produce it,
 * the same trick the disabled-target test above uses for the other writer.
 *
 * It fails on the pre-fix shape (a single unverified `chosen.focus()`) every
 * time, because that call cannot land while the header is inert.
 */
it('keeps a deliberate focus choice made the instant the header stops being inert', function (): void {
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
            const header = () => picker()?.querySelector('[data-testid="media-picker-header"]');

            picker().dispatchEvent(new CustomEvent('livewire-upload-start', {
                detail: window.__focusReturnDetail,
            }));
            await waitFor(() => picker()?.getAttribute('aria-busy') === 'true', 'waiting for the upload guard');

            // Hold the header inert by frame so Alpine's own binding cannot
            // drop it early. This is the state a slow machine produces by
            // accident: aria-busy already gone, the header not yet focusable.
            let holding = true;
            const hold = () => {
                if (! holding) {
                    return;
                }

                header()?.setAttribute('inert', '');
                requestAnimationFrame(hold);
            };
            hold();

            picker().dispatchEvent(new CustomEvent('livewire-upload-finish', {
                detail: window.__focusReturnDetail,
            }));
            await waitFor(() => picker()?.getAttribute('aria-busy') !== 'true', 'waiting for the guard to release');

            const chosen = await waitFor(
                () => {
                    const close = picker()?.querySelector('[data-testid="media-picker-close"]');

                    return close && ! close.disabled ? close : null;
                },
                'waiting for a focusable control',
            );

            // The pre-fix shape, run once against the held-inert header, to
            // record that a single focus() genuinely cannot land here — the
            // premise the whole fix rests on, asserted rather than assumed.
            chosen.focus();
            const singleAttemptLanded = document.activeElement === chosen;

            holding = false;
            header()?.removeAttribute('inert');

            await waitFor(
                () => {
                    if (document.activeElement !== chosen) {
                        chosen.focus();
                    }

                    return document.activeElement === chosen;
                },
                'waiting for the deliberate focus choice to take effect',
            );

            const windowLiveAtChoice = window.Alpine.$data(picker()).focusReturnFrames <= 120;
            const canHoldFocus = (element) => Boolean(
                element?.isConnected
                && ! element.disabled
                && ! element.closest('[inert]')
                && element.getClientRects().length,
            );

            /*
             * Focusability is read in a `blur` handler, not on the next frame,
             * and that is the whole correctness of this test. The second writer
             * disables (or re-inerts) the control, the browser blurs it to
             * <body>, the restore recovers, and the writer clears the attribute
             * again — all inside one frame. A frame-granularity read therefore
             * finds the control focusable again and blames the restore for a
             * drop it only recovered from. Measured: a frame-sampled version of
             * this check reported `stolen_from_live_choice: true` with
             * `frames_held: 0` at 48 spinners. `blur` fires synchronously at the
             * instant focus is lost, which is the only moment the answer is
             * true.
             */
            let lostWhileFocusable = false;
            let losses = 0;

            chosen.addEventListener('blur', () => {
                losses += 1;
                lostWhileFocusable = lostWhileFocusable || canHoldFocus(chosen);
            });

            let stolenBy = null;
            let framesHeld = 0;

            for (let frame = 0; frame < 150 && picker()?.isConnected; frame++) {
                await new Promise((resolve) => {
                    requestAnimationFrame(resolve);
                    setTimeout(resolve, 250);
                });

                if (document.activeElement === chosen) {
                    framesHeld += 1;

                    continue;
                }

                if (stolenBy === null) {
                    stolenWhileFocusable = canHoldFocus(chosen);
                    stolenBy = document.activeElement?.getAttribute?.('data-testid')
                        || document.activeElement?.id
                        || document.activeElement?.tagName
                        || 'unknown';
                }
            }

            return {
                single_attempt_landed: singleAttemptLanded,
                stolen_from_live_choice: stolenBy !== null && stolenWhileFocusable,
                choice_invalidated: stolenBy !== null && ! stolenWhileFocusable,
                kept_choice: stolenBy === null && document.activeElement === chosen,
                frames_held: framesHeld,
                stolen_by: stolenBy,
                window_live_at_choice: windowLiveAtChoice,
                active_element: document.activeElement?.getAttribute?.('data-testid')
                    || document.activeElement?.id
                    || document.activeElement?.tagName,
            };
        }
        JS);

    // The deterministic half is the FIRST assertion, and it is the one that
    // carries this test: an inert header must refuse a single unretried
    // focus(). That does not depend on load at all, and it is the premise the
    // canary's retry loop rests on — stated here rather than assumed there.
    expect($result['single_attempt_landed'])->toBeFalse(
        'An inert header accepted focus, so this test no longer reproduces the condition it was written for: '
        .json_encode($result, JSON_THROW_ON_ERROR),
    )
        ->and($result['window_live_at_choice'])->toBeTrue(json_encode($result, JSON_THROW_ON_ERROR))
        ->and($result['frames_held'])->toBeGreaterThan(0, json_encode($result, JSON_THROW_ON_ERROR))
        ->and($result['stolen_from_live_choice'])->toBeFalse(
            'The restore moved focus away from a control that could still hold it: '
            .json_encode($result, JSON_THROW_ON_ERROR),
        );

    assertNoUnexpectedJavaScriptErrors($page);
});
