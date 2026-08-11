<?php

use App\Enums\MediaAttachmentRole;
use App\Filament\Resources\ContentGroups\ContentGroupResource;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\Media;
use App\Models\User;
use App\Support\Media\MediaAttachmentManager;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/*
 * M2 regression suite — the media picker's duplicate/uninitialised root defect.
 *
 * Mechanism (verified in vendor source and by instrumentation, 2026-08-01/02):
 * a nested Livewire schema component keeps a stable wire:key, Filament's
 * partial rendering makes the host skip render (keeping child keys in the
 * Livewire memo), and any remount whose request still carries the stale memo
 * entry makes Livewire skip the child and emit a snapshot-less stub, which
 * Filament's partials.js grafts via cloneNode(true) — an uninitialised copy.
 * Ordinary settled cycles self-heal because removing the child's DOM also
 * removes it from the client memo; the production failure is the RACE where a
 * remount fires before that cleanup (fast clicks batch unmountAction and
 * mountAction into one Livewire message).
 *
 * The fix gives each picker workspace a per-mount key (a nonce minted when the
 * owning action mounts, stable while it stays mounted), so a remount can never
 * collide with a stale memo entry: no stub, no clone, no morph roulette.
 */

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Http::preventStrayRequests();
    Storage::fake('local');
    Storage::fake('public');
    $this->actingAs(User::factory()->admin()->create());
});

function m2CloneReproMedia(string $path, string $title): Media
{
    Storage::disk('public')->put(
        $path,
        base64_decode((string) file_get_contents(base_path('tests/Fixtures/media/valid.jpg.base64')), true),
    );

    /** @var Media $media */
    $media = Media::factory()->create([
        'disk' => 'public',
        'directory' => dirname($path),
        'visibility' => 'public',
        'name' => pathinfo($path, PATHINFO_FILENAME),
        'path' => $path,
        'width' => 640,
        'height' => 360,
        'size' => 2048,
        'type' => 'image/jpeg',
        'ext' => 'jpg',
        'title' => $title,
        'exif' => ['original_filename' => "{$title}.jpg"],
    ]);

    return $media;
}

/**
 * @return array{group: ContentGroup, cover: Media}
 */
function m2CloneReproFixtures(): array
{
    $group = ContentGroup::factory()->create(['title' => 'Clone repro podcast']);
    ContentItem::factory()->for($group)->create([
        'title' => 'Clone repro episode',
        'media_url' => 'https://cdn.example.test/clone-repro.mp3',
    ]);
    $cover = m2CloneReproMedia('content-groups/covers/clone-repro-cover.jpg', 'Clone repro cover');

    foreach (range(1, 4) as $index) {
        m2CloneReproMedia("media/clone-repro-gallery-{$index}.jpg", "Clone repro gallery {$index}");
    }

    app(MediaAttachmentManager::class)->attach(
        $group,
        $cover,
        MediaAttachmentRole::Cover,
        auth()->user(),
    );

    return ['group' => $group, 'cover' => $cover];
}

function m2CloneReproInstrument(object $webpage): void
{
    $installer = <<<'JS'
        (() => {
            const install = () => {
                if (window.__m2Installed || ! window.Livewire) {
                    return;
                }

                window.__m2Installed = true;
                window.__m2 = {
                    pending: 0,
                    messages: [],
                    steps: [],
                    pageErrors: [],
                };
                const describeError = (value) => {
                    if (value instanceof Error) {
                        return value.message;
                    }

                    if (typeof value === 'string') {
                        return value;
                    }

                    try {
                        return JSON.stringify(value);
                    } catch {
                        return String(value);
                    }
                };
                window.addEventListener('error', (event) => {
                    window.__m2.pageErrors.push(describeError(event.error ?? event.message));
                });
                window.addEventListener('unhandledrejection', (event) => {
                    window.__m2.pageErrors.push(describeError(event.reason));
                });
                window.Livewire.interceptRequest(({ onFinish }) => {
                    window.__m2.pending++;
                    let settled = false;
                    onFinish(() => {
                        if (settled) {
                            return;
                        }

                        settled = true;
                        requestAnimationFrame(() => queueMicrotask(() => {
                            window.__m2.pending--;
                        }));
                    });
                });
                window.Livewire.interceptMessage(({ message, onSuccess }) => {
                    onSuccess(({ payload }) => {
                        const partials = payload.effects?.partials ?? {};
                        const stubs = [];

                        for (const [name, html] of Object.entries(partials)) {
                            for (const match of html.matchAll(/<[a-z][a-z0-9-]*\s[^>]*wire:id="([^"]+)"[^>]*>/gi)) {
                                if (! match[0].includes('wire:snapshot')) {
                                    stubs.push({ partial: name, id: match[1] });
                                }
                            }
                        }

                        if (Object.keys(partials).length || stubs.length) {
                            window.__m2.messages.push({
                                component: message.component.id,
                                calls: (message.calls ?? []).map((call) => call.method),
                                partials: Object.keys(partials),
                                stubs,
                            });
                        }
                    });
                });
            };

            if (window.Livewire) {
                install();
            } else {
                document.addEventListener('livewire:init', install, { once: true });
            }
        })();
        JS;
    $page = $webpage->page();

    $page->context()->addInitScript($installer);
    $page->evaluate($installer);
}

/**
 * Shared driver JS: waitFor/settle helpers plus the post-morph DOM scan that
 * detects the M2 fingerprint — duplicate wire:id elements and component roots
 * that lost their __livewire property (a cloneNode graft).
 */
function m2CloneReproDriverHelpers(): string
{
    return <<<'JS'
        const waitFor = async (callback, stage, timeout = 10000) => {
            const started = performance.now();

            while (performance.now() - started < timeout) {
                const value = callback();

                if (value) {
                    return value;
                }

                await new Promise((resolve) => setTimeout(resolve, 25));
            }

            throw new Error('Timed out while ' + stage);
        };
        const settled = () => window.__m2.pending === 0;
        const scan = (label) => {
            const seen = new Map();

            document.querySelectorAll('[wire\\:id]').forEach((el) => {
                const id = el.getAttribute('wire:id');

                if (! seen.has(id)) {
                    seen.set(id, []);
                }

                seen.get(id).push(el);
            });

            const problems = [];

            for (const [id, els] of seen) {
                const uninitialised = els.filter((el) => ! el.__livewire).length;

                if (els.length > 1 || uninitialised > 0) {
                    problems.push({
                        id,
                        name: els[0].getAttribute('wire:name'),
                        count: els.length,
                        uninitialised,
                    });
                }
            }

            window.__m2.steps.push({
                label,
                problems,
                errorCount: window.__m2.pageErrors.length,
            });

            return problems;
        };
        const openPicker = () => document.querySelector(
            '[aria-modal="true"].fi-modal-open [data-testid="media-picker"]',
        );
        const recordWorkspace = (label) => {
            const workspace = openPicker();
            const id = workspace?.getAttribute('wire:id') ?? null;

            window.__m2.steps.at(-1).workspacePresent = Boolean(workspace);
            window.__m2.steps.at(-1).workspaceLive = Boolean(
                id && window.Livewire.find(id)?.el === workspace,
            );
            window.__m2.steps.at(-1).workspaceId = id;
            window.__m2.steps.at(-1).workspaceKey = workspace?.getAttribute('wire:key') ?? null;
        };
        JS;
}

/**
 * Filter this test's OWN error accumulator — `window.__m2.pageErrors`, fed by
 * the listeners installed at m2CloneReproInstrument(), not Pest's
 * `window.__pestBrowser.jsErrors`. Same message text as the shared artifact
 * filter, a different channel, which is why this does not route through
 * assertNoUnexpectedJavaScriptErrors().
 *
 * Two suppressions, each narrow:
 *
 * - The classified ResizeObserver artifact, matched by exact equality against
 *   the one shared literal. Exact matching is safe across `describeError()`
 *   despite the transform in the path: it returns strings unchanged and an
 *   Error's `.message` verbatim, so Chromium's text reaches the accumulator
 *   unaltered either way.
 * - `isFromCancelledTransition` — Alpine cancels modal transitions when cycles
 *   overlap, and that rejection noise predates this test's defect and is
 *   unrelated to component-root integrity. It stays a substring match on the
 *   distinctive property name because it arrives on the unhandledrejection
 *   channel, where the reason object's serialized shape varies; and it stays
 *   scoped to this call site, which is the only place in the repo that
 *   observes it.
 *
 * @param  array<int, string>  $errors
 * @return array<int, string>
 */
function m2CloneReproMaterialErrors(array $errors): array
{
    return array_values(array_filter(
        $errors,
        fn (string $error): bool => ! str_contains($error, 'isFromCancelledTransition')
            && $error !== knownResizeObserverArtifact(),
    ));
}

/**
 * @param  array<string, mixed>  $result
 */
function m2CloneReproExpectClean(array $result, bool $expectNoStubs): void
{
    $violations = [
        'poisoned_roots' => collect($result['steps'])->flatMap(fn (array $step): array => $step['problems'])->all(),
        'dead_workspaces' => collect($result['steps'])
            ->filter(fn (array $step): bool => ($step['workspaceLive'] ?? true) === false)
            ->pluck('label')
            ->all(),
        'missing_workspaces' => collect($result['steps'])
            ->filter(fn (array $step): bool => ($step['workspacePresent'] ?? true) === false)
            ->pluck('label')
            ->all(),
        'page_errors' => m2CloneReproMaterialErrors($result['pageErrors']),
    ];

    if ($expectNoStubs) {
        $violations['stubs'] = collect($result['messages'])
            ->flatMap(fn (array $message): array => $message['stubs'])
            ->all();
    }

    expect(array_filter($violations))->toBeEmpty(
        "M2 fingerprint detected:\n"
        .json_encode($violations, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT)
        ."\nFull telemetry:\n"
        .json_encode($result, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT),
    );
}

it('mounts a fresh field picker workspace on every open without ever stubbing it', function (): void {
    ['group' => $group] = m2CloneReproFixtures();

    $page = visit(ContentGroupResource::getUrl('edit', ['record' => $group]))
        ->resize(1280, 900);

    m2CloneReproInstrument($page);

    $helpers = m2CloneReproDriverHelpers();
    $result = $page->script(<<<JS
        async () => {
            {$helpers}

            for (let cycle = 1; cycle <= 3; cycle++) {
                document.querySelector('[data-testid="media-picker-open"]')?.click();
                await waitFor(() => openPicker() && settled(), 'opening the picker, cycle ' + cycle);
                scan('after-open-' + cycle);
                recordWorkspace('after-open-' + cycle);

                const close = openPicker()?.querySelector('[data-testid="media-picker-close"]');

                if (! close) {
                    throw new Error('The workspace close button is missing in cycle ' + cycle);
                }

                close.click();
                await waitFor(
                    () => settled() && openPicker() === null,
                    'closing the picker, cycle ' + cycle,
                );
                scan('after-close-' + cycle);
            }

            return window.__m2;
        }
        JS);

    m2CloneReproExpectClean($result, expectNoStubs: true);

    $keys = collect($result['steps'])
        ->filter(fn (array $step): bool => str_starts_with($step['label'], 'after-open-'))
        ->pluck('workspaceKey');

    expect($keys)->toHaveCount(3)
        ->and($keys->unique())->toHaveCount(3, 'The workspace key must change per mount so a remount can never collide with a stale child memo entry: '.$keys->implode(' | '));
});

it('survives a rapid close/reopen race of the workspace picker inside a relation manager edit modal', function (): void {
    ['group' => $group] = m2CloneReproFixtures();

    $page = visit(ContentGroupResource::getUrl('edit', ['record' => $group]))
        ->resize(1280, 900);

    m2CloneReproInstrument($page);

    $helpers = m2CloneReproDriverHelpers();
    $result = $page->script(<<<JS
        async () => {
            {$helpers}

            const itemsTab = await waitFor(
                () => Array.from(document.querySelectorAll('[role="tab"]')).find((tab) =>
                    tab.textContent.includes('פרקים') || tab.textContent.includes('Episodes')),
                'finding the episodes tab',
            );
            itemsTab.click();

            const editTrigger = await waitFor(
                () => Array.from(document.querySelectorAll('button')).find((button) =>
                    (button.getAttribute('wire:click') ?? '').includes("mountAction('edit'")),
                'finding the relation manager classic edit trigger',
            );
            editTrigger.click();
            await waitFor(
                () => document.querySelector(
                    '[aria-modal="true"].fi-modal-open [data-testid="media-picker-open"]',
                ) && settled(),
                'opening the classic edit modal',
            );
            scan('after-edit-open');

            const opener = () => document.querySelector(
                '[aria-modal="true"].fi-modal-open [data-testid="media-picker-open"]',
            );

            // A polite cycle first: open, close, and let everything settle.
            opener()?.click();
            await waitFor(() => openPicker() && settled(), 'opening the picker politely');
            scan('after-polite-open');
            recordWorkspace('after-polite-open');
            openPicker()?.querySelector('[data-testid="media-picker-close"]')?.click();
            await waitFor(() => settled() && openPicker() === null, 'closing the picker politely');
            scan('after-polite-close');

            // The production race: close and reopen in the same tick, so both
            // calls land in one Livewire message and the remount request still
            // carries the stale child memo entry.
            opener()?.click();
            await waitFor(() => openPicker() && settled(), 'reopening the picker before the race');
            scan('race-before');
            recordWorkspace('race-before');

            openPicker()?.querySelector('[data-testid="media-picker-close"]')?.click();
            opener()?.click();
            await waitFor(() => settled(), 'settling the rapid close/reopen race');
            await new Promise((resolve) => setTimeout(resolve, 300));
            // The race may legitimately net out to "closed" (the reopen's
            // open-modal event can fire before the morph inserts the fresh
            // modal); the M2 contract is that the page is never poisoned and
            // the picker always recovers on the next ordinary open.
            scan('race-after');

            if (! openPicker()) {
                opener()?.click();
            }

            await waitFor(() => openPicker() && settled(), 'reopening the workspace after the race');
            scan('after-race-recovery');
            recordWorkspace('after-race-recovery');

            return window.__m2;
        }
        JS);

    m2CloneReproExpectClean($result, expectNoStubs: true);

    $keys = collect($result['steps'])
        ->pluck('workspaceKey')
        ->filter()
        ->values();

    expect($keys->count())->toBeGreaterThanOrEqual(3)
        ->and($keys->unique())->toHaveCount($keys->count(), 'Every workspace mount must carry a fresh key: '.$keys->implode(' | '));
});

it('keeps the inline owner workspace healthy across nested details cycles and a rapid owner reopen', function (): void {
    ['group' => $group] = m2CloneReproFixtures();

    $page = visit(ContentGroupResource::getUrl('edit', ['record' => $group]))
        ->resize(1280, 900);

    m2CloneReproInstrument($page);

    $helpers = m2CloneReproDriverHelpers();
    $result = $page->script(<<<JS
        async () => {
            {$helpers}

            const ownerTrigger = Array.from(document.querySelectorAll('button'))
                .find((button) => button.matches('[data-owner-image-action="cover"]'));

            if (! ownerTrigger) {
                throw new Error('Unable to find the owner-image action trigger.');
            }

            ownerTrigger.click();
            await waitFor(() => openPicker() && settled(), 'opening the owner-image modal');
            scan('after-owner-open');
            recordWorkspace('after-owner-open');

            const detailsDialogOpen = () => Array.from(document.querySelectorAll(
                '[aria-modal="true"].fi-modal-open',
            )).find((dialog) => ! dialog.querySelector('[data-testid="media-picker"]'));

            // Nested sibling-action cycles over the living inline workspace.
            // Livewire is DESIGNED to skip a living child when its container
            // re-renders (the snapshot-less stub), and Filament's partials.js
            // heals the stub by grafting the live root; the defect is only an
            // unhealed DOM, so these cycles assert root integrity, not
            // stub absence.
            for (let cycle = 1; cycle <= 3; cycle++) {
                const details = await waitFor(
                    () => document.querySelector('[data-media-details-id]'),
                    'finding the details trigger, cycle ' + cycle,
                );
                details.click();
                await waitFor(
                    () => detailsDialogOpen() && settled(),
                    'opening the details slide-over, cycle ' + cycle,
                );
                scan('after-details-open-' + cycle);

                const dialog = detailsDialogOpen();
                const cancel = Array.from(dialog.querySelectorAll('.fi-modal-footer-actions button'))
                    .find((button) => button.type !== 'submit');

                if (! cancel) {
                    throw new Error('Unable to find the details close button in cycle ' + cycle);
                }

                cancel.click();
                await waitFor(
                    () => settled() && detailsDialogOpen() === undefined,
                    'closing the details slide-over, cycle ' + cycle,
                );
                scan('after-details-close-' + cycle);
                recordWorkspace('after-details-close-' + cycle);
            }

            // The owner-level race: close the owner modal and reopen it in the
            // same tick. The remount request still carries the inline child in
            // the memo, so without a per-mount key it would be stubbed.
            const ownerDialogOpen = () => Boolean(document.querySelector(
                '[aria-modal="true"].fi-modal-open [data-testid="media-picker"]',
            ));
            const ownerCancel = Array.from(document.querySelectorAll(
                '[aria-modal="true"].fi-modal-open .fi-modal-footer-actions button',
            )).find((button) => button.type !== 'submit');

            if (! ownerCancel) {
                throw new Error('Unable to find the owner modal cancel button.');
            }

            ownerCancel.click();
            ownerTrigger.click();
            await waitFor(() => settled(), 'settling the rapid owner close/reopen race');
            await new Promise((resolve) => setTimeout(resolve, 300));
            await waitFor(() => ownerDialogOpen(), 'waiting for the reopened owner modal');
            scan('race-after');
            recordWorkspace('race-after');

            return window.__m2;
        }
        JS);

    m2CloneReproExpectClean($result, expectNoStubs: false);

    $raceStubs = collect($result['messages'])
        ->filter(fn (array $message): bool => in_array('unmountAction', $message['calls'], true)
            && in_array('mountAction', $message['calls'], true))
        ->flatMap(fn (array $message): array => $message['stubs'])
        ->all();

    expect($raceStubs)->toBeEmpty(
        'A remount batched with an unmount must never stub the inline workspace: '
        .json_encode($result['messages'], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT),
    );
});
