<?php

use App\Enums\MediaAttachmentRole;
use App\Filament\Resources\ContentGroups\ContentGroupResource;
use App\Filament\Resources\ContentItems\ContentItemResource;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\Media;
use App\Models\User;
use App\Support\Media\MediaAttachmentManager;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Pest\Browser\Api\PendingAwaitablePage;
use Pest\Browser\Playwright\Page as PlaywrightPage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Http::preventStrayRequests();
    Storage::fake('local');
    Storage::fake('public');
    $this->actingAs(User::factory()->admin()->create());
});

/**
 * @return array<string, array{0: string, 1: string, 2: string, 3: bool}>
 */
function ownerImageBrowserSurfaceCases(): array
{
    return [
        'Hebrew RTL MacBook Air' => ['he', 'rtl', 'macbook', false],
        'Hebrew RTL iPhone 15' => ['he', 'rtl', 'iphone', true],
        'English LTR MacBook Air' => ['en', 'ltr', 'macbook', false],
        'English LTR iPhone 15' => ['en', 'ltr', 'iphone', true],
    ];
}

function ownerImageBrowserMedia(string $path, string $title): Media
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
 * @return array{
 *     group: ContentGroup,
 *     item: ContentItem,
 *     group_direct: Media,
 *     item_direct: Media,
 *     replacement: Media,
 *     concurrent: Media
 * }
 */
function ownerImageBrowserFixtures(): array
{
    $actor = auth()->user();
    $group = ContentGroup::factory()->create(['title' => 'Browser owner podcast']);
    $item = ContentItem::factory()->for($group)->create([
        'title' => 'Browser owner episode',
        'media_url' => 'https://cdn.example.test/browser-owner.mp3',
    ]);
    $groupDirect = ownerImageBrowserMedia(
        'content-groups/covers/browser-owner-podcast.jpg',
        'Browser podcast direct',
    );
    $itemDirect = ownerImageBrowserMedia(
        'content-items/images/browser-owner-episode.jpg',
        'Browser episode direct',
    );
    $replacement = ownerImageBrowserMedia(
        'content-items/images/browser-owner-replacement.jpg',
        'Browser pending replacement',
    );
    $concurrent = ownerImageBrowserMedia(
        'content-items/images/browser-owner-concurrent.jpg',
        'Browser concurrent saved image',
    );

    app(MediaAttachmentManager::class)->attach(
        $group,
        $groupDirect,
        MediaAttachmentRole::Cover,
        $actor,
    );
    app(MediaAttachmentManager::class)->attach(
        $item,
        $itemDirect,
        MediaAttachmentRole::PrimaryImage,
        $actor,
    );

    foreach (range(1, 14) as $index) {
        ownerImageBrowserMedia(
            "media/browser-owner-gallery-{$index}.jpg",
            "Browser gallery {$index}",
        );
    }

    Storage::disk('public')->put(
        'media-imports/browser-owner-admission.jpg',
        base64_decode((string) file_get_contents(base_path('tests/Fixtures/media/valid.jpg.base64')), true),
    );

    return [
        'group' => $group,
        'item' => $item,
        'group_direct' => $groupDirect,
        'item_direct' => $itemDirect,
        'replacement' => $replacement,
        'concurrent' => $concurrent,
    ];
}

function ownerImageBrowserPage(string $url, string $device): PendingAwaitablePage
{
    $page = visit($url);

    return $device === 'iphone'
        ? $page->on()->iPhone15()->inLightMode()
        : $page->on()->macbookAir()->inLightMode();
}

function trackOwnerImageBrowserRequests(PendingAwaitablePage $webpage): void
{
    $installer = <<<'JS'
        (() => {
            const install = () => {
                if (window.__ownerImageRequestTrackerInstalled || ! window.Livewire) {
                    return;
                }

                window.__ownerImageRequestTrackerInstalled = true;
                window.__ownerImagePendingRequests = 0;
                window.__ownerImageSucceededRequests = 0;
                window.__ownerImageFailedRequests = 0;
                window.__ownerImageUnhandledRejections = [];
                window.addEventListener('unhandledrejection', (event) => {
                    window.__ownerImageUnhandledRejections.push(String(event.reason));
                });
                window.Livewire.interceptRequest(({ onError, onFailure, onFinish }) => {
                    window.__ownerImagePendingRequests++;
                    let settled = false;
                    let failed = false;
                    const finish = () => {
                        if (settled) {
                            return;
                        }

                        settled = true;
                        requestAnimationFrame(() => queueMicrotask(() => {
                            window.__ownerImagePendingRequests--;

                            if (failed) {
                                window.__ownerImageFailedRequests++;
                            } else {
                                window.__ownerImageSucceededRequests++;
                            }
                        }));
                    };

                    onError(() => failed = true);
                    onFailure(() => failed = true);
                    onFinish(finish);
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

function ownerImageBrowserPlaywrightPage(PendingAwaitablePage $webpage): PlaywrightPage
{
    return $webpage->page();
}

function ownerImageBrowserCapture(
    PendingAwaitablePage $webpage,
    string $filename,
    string $selector = '[data-owner-image-modal="true"]',
): void {
    $configuredDirectory = getenv('PODTEXT_OWNER_IMAGE_EVIDENCE_DIR');

    if (! is_string($configuredDirectory) || $configuredDirectory === '') {
        return;
    }

    $directory = realpath($configuredDirectory);
    $base = realpath(base_path());

    if (
        $directory === false
        || ! is_dir($directory)
        || $base === false
        || $directory === $base
        || str_starts_with($directory.DIRECTORY_SEPARATOR, $base.DIRECTORY_SEPARATOR)
    ) {
        throw new RuntimeException('Owner-image evidence must use an existing directory outside the repository.');
    }

    $encoded = ownerImageBrowserPlaywrightPage($webpage)
        ->locator($selector)
        ->screenshot(['animations' => 'disabled']);
    $bytes = base64_decode($encoded, true);

    if ($bytes === false) {
        throw new RuntimeException('The browser returned invalid owner-image screenshot bytes.');
    }

    if (file_put_contents($directory.DIRECTORY_SEPARATOR.$filename, $bytes) === false) {
        throw new RuntimeException("Unable to write owner-image evidence {$filename}.");
    }
}

function ownerImageBrowserTap(PendingAwaitablePage $webpage, string $selector): void
{
    ownerImageBrowserPlaywrightPage($webpage)->locator($selector)->tap();
}

function ownerImageBrowserPress(PendingAwaitablePage $webpage, string $selector, string $key): void
{
    ownerImageBrowserPlaywrightPage($webpage)->locator($selector)->press($key);
}

function ownerImageBrowserJsValue(mixed $value): string
{
    return json_encode(
        $value,
        JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_THROW_ON_ERROR,
    );
}

/**
 * @return array<string, mixed>
 */
function ownerImageBrowserOpenDedicatedAction(
    object $page,
    string $actionName,
    string $expectedHeading,
    string $expectedSave,
    string $direction,
    bool $mobile,
): array {
    $currentFolderLabel = ownerImageBrowserJsValue(__('admin.media_library.context_media'));
    $genericActionLabels = ownerImageBrowserJsValue([
        __('admin.media_library.use_selected'),
        __('admin.actions.remove'),
        __('admin.actions.view'),
        __('admin.actions.edit'),
        __('admin.actions.download'),
    ]);
    $expectedHeadingValue = ownerImageBrowserJsValue($expectedHeading);
    $expectedSaveValue = ownerImageBrowserJsValue($expectedSave);
    $expectedDirectionValue = ownerImageBrowserJsValue($direction);
    $expectedMobileValue = ownerImageBrowserJsValue($mobile);

    return $page->script(<<<JS
        async () => {
            // stable-read (R4): a layout value counts only when two consecutive
            // animation-frame reads agree (cap 10 frames) — a mid-reflow transient
            // can no longer become an assertion input.
            const stableRead = async (fn) => {
                const frame = () => new Promise((resolve) => requestAnimationFrame(resolve));
                let previous = fn();
                for (let i = 0; i < 10; i++) {
                    await frame();
                    const current = fn();
                    if (JSON.stringify(current) === JSON.stringify(previous)) return current;
                    previous = current;
                }
                return previous;
            };
            const readinessDiagnostics = (stage) => {
                const dialogs = Array.from(document.querySelectorAll(
                    '[aria-modal="true"].fi-modal-open',
                ));
                const dialog = dialogs.find((candidate) =>
                    candidate.querySelector('[data-owner-image-modal="true"]')
                    || candidate.querySelector('[data-testid="media-picker"]')
                ) ?? dialogs[0] ?? null;
                const modal = dialog?.querySelector('[data-owner-image-modal="true"]') ?? null;
                const picker = dialog?.querySelector('[data-testid="media-picker"]') ?? null;
                const choice = dialog?.querySelector(
                    '[data-testid="owner-image-choice-state"]',
                ) ?? null;
                const pickerId = picker?.getAttribute('wire:id') ?? null;
                const pickerComponent = pickerId ? window.Livewire.find(pickerId) : null;

                return {
                    stage,
                    dialog_count: dialogs.length,
                    dialog_present: Boolean(dialog),
                    modal_present: Boolean(modal),
                    picker_present: Boolean(picker),
                    picker_connected: Boolean(picker?.isConnected),
                    picker_live_owned: Boolean(picker && pickerComponent?.el === picker),
                    choice_present: Boolean(choice),
                    choice_connected: Boolean(choice?.isConnected),
                    pending_requests: window.__ownerImagePendingRequests,
                    succeeded_requests: window.__ownerImageSucceededRequests,
                    failed_requests: window.__ownerImageFailedRequests,
                };
            };
            const waitFor = async (callback, stage, timeout = 10000) => {
                const started = performance.now();

                while (performance.now() - started < timeout) {
                    const value = callback();

                    if (value) {
                        return value;
                    }

                    await new Promise((resolve) => setTimeout(resolve, 25));
                }

                throw new Error(
                    'Timed out while ' + stage + ': '
                    + JSON.stringify(readinessDiagnostics(stage)),
                );
            };
            const visible = (element) => Boolean(element?.getClientRects().length);
            const trigger = Array.from(document.querySelectorAll('button, a'))
                .find((element) => Array.from(element.attributes)
                    .some((attribute) => attribute.value.includes('{$actionName}')));

            if (! trigger) {
                throw new Error('Unable to find canonical action {$actionName}.');
            }

            trigger.focus();
            const openerId = trigger.id || (trigger.id = 'task-nine-owner-opener');
            const completed = window.__ownerImageSucceededRequests;
            trigger.click();
            await waitFor(
                () => Array.from(document.querySelectorAll(
                    '[aria-modal="true"].fi-modal-open',
                )).find((candidate) => candidate.querySelector('[data-testid="media-picker"]')),
                'locating the active owner-image dialog with its picker',
            );
            await waitFor(
                () => Array.from(document.querySelectorAll(
                    '[aria-modal="true"].fi-modal-open',
                )).find((candidate) => candidate.querySelector('[data-owner-image-modal="true"]')),
                'locating the owner-image modal window',
            );
            await waitFor(() => {
                const dialog = Array.from(document.querySelectorAll(
                    '[aria-modal="true"].fi-modal-open',
                )).find((candidate) => candidate.querySelector('[data-testid="media-picker"]'));
                const modal = dialog?.querySelector('[data-owner-image-modal="true"]') ?? null;
                const picker = dialog?.querySelector('[data-testid="media-picker"]') ?? null;
                const choice = dialog?.querySelector(
                    '[data-testid="owner-image-choice-state"]',
                ) ?? null;
                const pickerId = picker?.getAttribute('wire:id') ?? null;
                const pickerComponent = pickerId ? window.Livewire.find(pickerId) : null;

                return dialog?.isConnected
                    && modal?.isConnected
                    && picker?.isConnected
                    && choice?.isConnected
                    && pickerComponent?.el === picker
                    && window.__ownerImageSucceededRequests > completed
                    && window.__ownerImagePendingRequests === 0
                    ? { dialog, modal, picker, choice }
                    : null;
            }, 'waiting for the connected owner-image workspace and settled requests');
            await new Promise((resolve) =>
                requestAnimationFrame(() => requestAnimationFrame(resolve))
            );
            const settled = await waitFor(() => {
                const dialog = Array.from(document.querySelectorAll(
                    '[aria-modal="true"].fi-modal-open',
                )).find((candidate) => candidate.querySelector('[data-owner-image-modal="true"]'));
                const modal = dialog?.querySelector('[data-owner-image-modal="true"]') ?? null;
                const picker = dialog?.querySelector('[data-testid="media-picker"]') ?? null;
                const choice = dialog?.querySelector(
                    '[data-testid="owner-image-choice-state"]',
                ) ?? null;
                const pickerId = picker?.getAttribute('wire:id') ?? null;
                const pickerComponent = pickerId ? window.Livewire.find(pickerId) : null;
                const submitButtons = Array.from(dialog?.querySelectorAll(
                    '.fi-modal-footer-actions button[type="submit"]',
                ) ?? []).filter(visible);
                const details = Array.from(dialog?.querySelectorAll(
                    '[data-testid^="media-picker-owner-details-"]',
                ) ?? []).find(visible) ?? null;
                const hasTransitionClass = Boolean(
                    dialog?.matches('.fi-transition-enter, .fi-transition-leave')
                    || dialog?.querySelector('.fi-transition-enter, .fi-transition-leave'),
                );
                const hasActiveAnimation = dialog?.getAnimations({ subtree: true }).some(
                    (animation) => ['pending', 'running'].includes(animation.playState),
                ) ?? true;

                return dialog?.isConnected
                    && modal?.isConnected
                    && picker?.isConnected
                    && choice?.isConnected
                    && pickerComponent?.el === picker
                    && window.__ownerImagePendingRequests === 0
                    && submitButtons.length === 1
                    && details
                    && ! hasTransitionClass
                    && ! hasActiveAnimation
                    ? { dialog, modal, picker, choice, submitButtons, details }
                    : null;
            }, 'waiting for accepted owner-image content and transition settlement');
            const { dialog, modal, picker, choice, submitButtons, details } = settled;
            const allMediaState = (() => {
                const id = picker.getAttribute('wire:id');
                const wire = id ? window.Livewire.find(id) : null;

                return wire?.\$get('allMedia') === true;
            })();
            const labels = Array.from(dialog.querySelectorAll(
                '[data-testid="media-picker-owner-source-navigation"] button',
            )).map((button) => button.textContent.trim());
            const axe = await window.axe.run(modal, {
                rules: {
                    'color-contrast': { enabled: false },
                },
            });
            const seriousAxe = axe.violations.filter((violation) =>
                ['critical', 'serious'].includes(violation.impact)
            );
            const modalRect = modal.getBoundingClientRect();
            const stateRect = choice.getBoundingClientRect();
            const gallery = picker.querySelector('[data-testid="media-picker-gallery"]');
            const galleryRect = gallery.getBoundingClientRect();
            const header = modal.querySelector(':scope > .fi-modal-header');
            const footer = modal.querySelector(':scope > .fi-modal-footer');
            const activeScrollOwners = Array.from(modal.querySelectorAll('*'))
                .filter((element) => {
                    const style = getComputedStyle(element);

                    return visible(element)
                        && ['auto', 'scroll'].includes(style.overflowY)
                        && element.scrollHeight > element.clientHeight + 1;
                })
                .map((element) => ({
                    classes: element.className,
                    testid: element.getAttribute('data-testid'),
                }));
            const grid = gallery.querySelector('ul');
            const columns = getComputedStyle(grid).gridTemplateColumns
                .split(' ')
                .filter(Boolean)
                .length;
            const detailsRect = details?.getBoundingClientRect();
            const sourceRects = Array.from(dialog.querySelectorAll(
                '[data-testid="media-picker-owner-source-navigation"] button',
            )).map((button) => button.getBoundingClientRect());
            const overflow = Array.from(modal.querySelectorAll(
                '[data-testid], h1, h2, h3, .fi-modal-footer',
            )).filter((element) => {
                const rect = element.getBoundingClientRect();

                return visible(element)
                    && (element.scrollWidth > element.clientWidth + 1
                        || rect.left < -1
                        || rect.right > window.innerWidth + 1);
            }).map((element) => ({
                testid: element.getAttribute('data-testid'),
                tag: element.tagName,
                left: element.getBoundingClientRect().left,
                right: element.getBoundingClientRect().right,
                scrollWidth: element.scrollWidth,
                clientWidth: element.clientWidth,
            }));

            modal.scrollTop = modal.scrollHeight;
            await new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(resolve)));

            return {
                direction: document.documentElement.dir,
                opener_id: openerId,
                heading: dialog.querySelector('.fi-modal-heading')?.textContent.trim(),
                submit_text: submitButtons[0]?.textContent.trim(),
                submit_count: submitButtons.length,
                dialog_count: document.querySelectorAll('[aria-modal="true"].fi-modal-open').length,
                modal_count: document.querySelectorAll('[data-owner-image-modal="true"]').length,
                direct_count: dialog.querySelectorAll('[data-testid="owner-image-direct-state"]').length,
                shown_count: dialog.querySelectorAll('[data-testid="owner-image-shown-now-state"]').length,
                pending_count: dialog.querySelectorAll('[data-testid="owner-image-pending-state"]').length,
                labels,
                all_media_state: allMediaState,
                current_folder_absent: ! dialog.textContent.includes(
                    {$currentFolderLabel}
                ),
                removed_tabs_absent: ! dialog.querySelector('[data-testid^="owner-image-tab-"]'),
                generic_summary_absent: ! dialog.querySelector('[data-testid="media-picker-selected-item"]'),
                generic_actions_absent: ! {$genericActionLabels}.some(
                    (label) => dialog.textContent.includes(label),
                ),
                details_is_button: details?.tagName === 'BUTTON',
                details_mounts_slide_over: Boolean(
                    details?.getAttribute('wire:click')?.includes("mountAction('mediaDetails'"),
                ),
                raw_key_absent: ! /\\b(?:admin\\.)[a-z0-9_.]+/i.test(modal.textContent),
                axe_serious: seriousAxe,
                viewport_width: innerWidth,
                viewport_height: innerHeight,
                touch_points: navigator.maxTouchPoints,
                coarse_pointer: matchMedia('(pointer: coarse)').matches,
                modal_left: modalRect.left,
                modal_top: modalRect.top,
                modal_width: modalRect.width,
                modal_height: modalRect.height,
                modal_scrollable: await stableRead(() => modal.scrollHeight > modal.clientHeight + 1),
                active_scroll_owners: activeScrollOwners,
                state_nested_scroll: await stableRead(() => choice.scrollHeight > choice.clientHeight + 1),
                gallery_nested_scroll: await stableRead(() => gallery.scrollHeight > gallery.clientHeight + 1),
                header_nested_scroll: await stableRead(() => header
                    ? header.scrollHeight > header.clientHeight + 1
                    : false),
                footer_nested_scroll: await stableRead(() => footer
                    ? footer.scrollHeight > footer.clientHeight + 1
                    : false),
                header_visible_after_scroll: await stableRead(() => {
                    const headerRect = header?.getBoundingClientRect();

                    return ! headerRect
                        || (headerRect.top >= -1 && headerRect.bottom <= innerHeight + 1);
                }),
                footer_visible_after_scroll: await stableRead(() => {
                    const footerRect = footer?.getBoundingClientRect();

                    return ! footerRect
                        || (footerRect.top >= -1 && footerRect.bottom <= innerHeight + 1);
                }),
                details_width: detailsRect?.width ?? null,
                details_height: detailsRect?.height ?? null,
                source_min_width: Math.min(...sourceRects.map((rect) => rect.width)),
                source_min_height: Math.min(...sourceRects.map((rect) => rect.height)),
                gallery_columns: columns,
                horizontal_overflow: await stableRead(() => document.documentElement.scrollWidth
                    > document.documentElement.clientWidth + 1),
                owned_overflow: overflow,
                failed_requests: window.__ownerImageFailedRequests,
                unhandled_rejections: window.__ownerImageUnhandledRejections,
                request_started: window.__ownerImageSucceededRequests > completed,
                expected_heading: {$expectedHeadingValue},
                expected_save: {$expectedSaveValue},
                expected_direction: {$expectedDirectionValue},
                expected_mobile: {$expectedMobileValue},
                gallery_width: galleryRect.width,
                state_width: stateRect.width,
            };
        }
        JS);
}

/**
 * @param  array<string, mixed>  $evidence
 */
function expectOwnerImageBrowserContract(array $evidence, bool $mobile): void
{
    expect($evidence['direction'])->toBe($evidence['expected_direction'])
        ->and($evidence['heading'])->toBe($evidence['expected_heading'])
        ->and($evidence['submit_text'])->toBe(
            $evidence['expected_save'],
            json_encode($evidence, JSON_THROW_ON_ERROR),
        )
        ->and($evidence['submit_count'])->toBe(1, json_encode($evidence, JSON_THROW_ON_ERROR))
        ->and($evidence['dialog_count'])->toBe(1)
        ->and($evidence['modal_count'])->toBe(1)
        ->and($evidence['direct_count'])->toBe(1)
        ->and($evidence['shown_count'])->toBe(1)
        ->and($evidence['pending_count'])->toBe(1)
        ->and($evidence['labels'])->toBe([
            __('admin.media_library.gallery_source'),
            __('admin.media_library.upload_source'),
            __('admin.media_library.url_source'),
            __('admin.media_library.storage_source'),
        ])
        ->and($evidence['all_media_state'])->toBeTrue(json_encode($evidence, JSON_THROW_ON_ERROR))
        ->and($evidence['current_folder_absent'])->toBeTrue()
        ->and($evidence['removed_tabs_absent'])->toBeTrue()
        ->and($evidence['generic_summary_absent'])->toBeTrue()
        ->and($evidence['generic_actions_absent'])->toBeTrue()
        ->and($evidence['details_is_button'])->toBeTrue(json_encode($evidence, JSON_THROW_ON_ERROR))
        ->and($evidence['details_mounts_slide_over'])->toBeTrue()
        ->and($evidence['raw_key_absent'])->toBeTrue()
        ->and($evidence['axe_serious'])->toBe([])
        ->and($evidence['failed_requests'])->toBe(0, json_encode($evidence, JSON_THROW_ON_ERROR))
        ->and($evidence['unhandled_rejections'])->toBe([]);

    if (! $mobile) {
        return;
    }

    expect($evidence['viewport_width'])->toBe(390)
        ->and($evidence['viewport_height'])->toBe(844)
        ->and($evidence['touch_points'])->toBeGreaterThan(0)
        ->and($evidence['coarse_pointer'])->toBeTrue()
        ->and(abs($evidence['modal_left']))->toBeLessThanOrEqual(1)
        ->and(abs($evidence['modal_top']))->toBeLessThanOrEqual(1)
        ->and(abs($evidence['modal_width'] - 390))->toBeLessThanOrEqual(1)
        ->and(abs($evidence['modal_height'] - 844))->toBeLessThanOrEqual(1)
        ->and($evidence['modal_scrollable'])->toBeTrue(json_encode($evidence, JSON_THROW_ON_ERROR))
        ->and($evidence['active_scroll_owners'])->toBe([])
        ->and($evidence['state_nested_scroll'])->toBeFalse()
        ->and($evidence['gallery_nested_scroll'])->toBeFalse()
        ->and($evidence['header_nested_scroll'])->toBeFalse()
        ->and($evidence['footer_nested_scroll'])->toBeFalse()
        ->and($evidence['header_visible_after_scroll'])->toBeTrue()
        ->and($evidence['footer_visible_after_scroll'])->toBeTrue()
        ->and($evidence['details_width'])->toBeGreaterThanOrEqual(44)
        ->and($evidence['details_height'])->toBeGreaterThanOrEqual(44)
        ->and($evidence['source_min_width'])->toBeGreaterThanOrEqual(44)
        ->and($evidence['source_min_height'])->toBeGreaterThanOrEqual(44)
        ->and($evidence['gallery_columns'])->toBe(2)
        ->and($evidence['horizontal_overflow'])->toBeFalse()
        ->and($evidence['owned_overflow'])->toBe([], json_encode($evidence, JSON_THROW_ON_ERROR));
}

it('proves canonical dedicated owner actions across locale and device contracts', function (
    string $locale,
    string $direction,
    string $device,
    bool $mobile,
): void {
    app()->setLocale($locale);
    $fixtures = ownerImageBrowserFixtures();
    $page = ownerImageBrowserPage(ContentGroupResource::getUrl('index'), $device);
    trackOwnerImageBrowserRequests($page);

    $podcast = ownerImageBrowserOpenDedicatedAction(
        $page,
        'chooseContentGroupCover',
        __('admin.owner_image.actions.change_podcast_cover'),
        __('admin.owner_image.actions.save_podcast_cover'),
        $direction,
        $mobile,
    );
    expectOwnerImageBrowserContract($podcast, $mobile);

    if ($locale === 'he' && ! $mobile) {
        ownerImageBrowserCapture($page, '01-podcast-modal-he-desktop.png');

        $admission = $page->script(<<<'JS'
            async () => {
                const visible = (element) => Boolean(element?.getClientRects().length);
                const activeDialog = () => Array.from(document.querySelectorAll(
                    '[aria-modal="true"].fi-modal-open',
                )).find((candidate) => candidate.querySelector('[data-owner-image-modal="true"]'));
                const activePicker = () => activeDialog()?.querySelector(
                    '[data-testid="media-picker"]',
                ) ?? null;
                const storageSourceButton = () => {
                    const button = activePicker()?.querySelector(
                        '[data-testid="media-picker-source-storage"]:not([disabled])',
                    ) ?? null;

                    return button?.isConnected && button.getAttribute('aria-disabled') !== 'true'
                        ? button
                        : null;
                };
                const targetStorageRow = () => Array.from(
                    activePicker()?.querySelectorAll('li') ?? [],
                ).find((row) => row.textContent.includes(
                    'browser-owner-admission.jpg',
                )) ?? null;
                const targetStorageAcquire = () => targetStorageRow()?.querySelector(
                    '[data-testid="media-picker-storage-acquire"]:not([disabled])',
                ) ?? null;
                const diagnostics = (stage) => {
                    const dialog = activeDialog();
                    const picker = activePicker();
                    const state = dialog?.querySelector(
                        '[data-testid="owner-image-pending-state"]',
                    ) ?? null;

                    return {
                        stage,
                        dialog_present: Boolean(dialog),
                        picker_present: Boolean(picker),
                        pending_state_present: Boolean(state),
                        storage_source_button_present: Boolean(storageSourceButton()),
                        target_storage_row_present: Boolean(targetStorageRow()),
                        target_storage_acquire_present: Boolean(targetStorageAcquire()),
                        cancel_present: Boolean(Array.from(dialog?.querySelectorAll(
                            '.fi-modal-footer-actions button',
                        ) ?? []).find((button) => button.type !== 'submit' && visible(button))),
                        pending_requests: window.__ownerImagePendingRequests,
                        succeeded_requests: window.__ownerImageSucceededRequests,
                        failed_requests: window.__ownerImageFailedRequests,
                    };
                };
                const waitFor = async (callback, stage, timeout = 10000) => {
                    const started = performance.now();

                    while (performance.now() - started < timeout) {
                        const value = callback();

                        if (value) {
                            return value;
                        }

                        await new Promise((resolve) => setTimeout(resolve, 25));
                    }

                    throw new Error(
                        'Timed out while ' + stage + ': '
                        + JSON.stringify(diagnostics(stage)),
                    );
                };
                let succeeded = window.__ownerImageSucceededRequests;
                await waitFor(() => {
                    const button = storageSourceButton();

                    if (! button) {
                        return false;
                    }

                    button.click();

                    return true;
                }, 'waiting for and clicking the current Storage source button');
                await waitFor(() => activePicker()?.isConnected
                    && window.__ownerImageSucceededRequests > succeeded
                    && window.__ownerImagePendingRequests === 0,
                'waiting for the Storage source request to settle');
                await waitFor(
                    () => targetStorageRow(),
                    'waiting for the target Storage candidate row',
                );
                succeeded = window.__ownerImageSucceededRequests;
                await waitFor(() => {
                    const row = targetStorageRow();
                    const acquire = row?.querySelector(
                        '[data-testid="media-picker-storage-acquire"]:not([disabled])',
                    ) ?? null;

                    if (! acquire?.isConnected) {
                        return false;
                    }

                    acquire.click();

                    return true;
                }, 'waiting for and clicking the target Storage candidate acquire button');
                const pendingText = await waitFor(() => {
                    const state = activeDialog()?.querySelector(
                        '[data-testid="owner-image-pending-state"]',
                    );

                    return state?.querySelector('button[title*="browser-owner-admission.jpg"]')
                        ? state.textContent
                        : null;
                }, 'waiting for the current owner pending state');
                await waitFor(() => activeDialog()?.querySelector(
                    '[data-testid="owner-image-pending-state"]',
                )?.querySelector('button[title*="browser-owner-admission.jpg"]')
                    && window.__ownerImageSucceededRequests > succeeded
                    && window.__ownerImagePendingRequests === 0,
                'waiting for the acquire request to settle');
                const cancel = await waitFor(() => Array.from(activeDialog()?.querySelectorAll(
                    '.fi-modal-footer-actions button',
                ) ?? []).find((button) => button.type !== 'submit' && visible(button)),
                'waiting for the current Cancel button');
                succeeded = window.__ownerImageSucceededRequests;
                cancel.click();
                await waitFor(() => ! activeDialog()
                    && Boolean(document.activeElement?.closest('[data-owner-image-action]')),
                'waiting for modal close and opener focus restoration');
                await waitFor(() => ! activeDialog()
                    && window.__ownerImageSucceededRequests > succeeded
                    && window.__ownerImagePendingRequests === 0,
                'waiting for the Cancel request to settle');

                return {
                    pending: Boolean(pendingText),
                    closed: ! activeDialog(),
                    failed_requests: window.__ownerImageFailedRequests,
                };
            }
            JS);
        $admitted = Media::query()->where('path', 'media-imports/browser-owner-admission.jpg')->sole();

        expect($admission['pending'])->toBeTrue(json_encode($admission, JSON_THROW_ON_ERROR))
            ->and($admission['closed'])->toBeTrue()
            ->and($admission['failed_requests'])->toBe(0)
            ->and($fixtures['group']->refresh()->coverMediaAttachment()->value('media_id'))
            ->toBe($fixtures['group_direct']->getKey())
            ->and($admitted->mediaAsset)->not->toBeNull()
            ->and($admitted->providerBinding)->not->toBeNull();
    } else {
        $closed = $page->script(<<<'JS'
            async () => {
                const started = performance.now();
                const dialog = document.querySelector('[aria-modal="true"].fi-modal-open');
                const cancel = Array.from(dialog.querySelectorAll(
                    '.fi-modal-footer-actions button',
                )).find((button) => button.type !== 'submit');
                cancel.click();

                while (document.querySelector('[aria-modal="true"].fi-modal-open')
                    && performance.now() - started < 10000) {
                    await new Promise((resolve) => setTimeout(resolve, 25));
                }

                return ! document.querySelector('[aria-modal="true"].fi-modal-open');
            }
            JS);

        expect($closed)->toBeTrue();
    }

    $page->navigate(ContentItemResource::getUrl('index'));
    $page->script('() => { window.__ownerImageUnhandledRejections = []; }');
    $episode = ownerImageBrowserOpenDedicatedAction(
        $page,
        'chooseContentItemImage',
        __('admin.owner_image.actions.change_episode_image'),
        __('admin.owner_image.actions.save_episode_image'),
        $direction,
        $mobile,
    );
    expectOwnerImageBrowserContract($episode, $mobile);

    $replacementSelector = '[wire\\:key="media-picker-'.$fixtures['replacement']->getKey().'"] button[wire\\:click^="toggleSelection"]';

    if ($mobile) {
        ownerImageBrowserTap($page, $replacementSelector);
    } else {
        ownerImageBrowserPress($page, $replacementSelector, 'Enter');
    }

    $pending = $page->script(<<<'JS'
        async () => {
            const started = performance.now();

            while (performance.now() - started < 10000) {
                const dialog = document.querySelector('[aria-modal="true"].fi-modal-open');
                const state = dialog?.querySelector('[data-testid="owner-image-pending-state"]');

                if (state?.querySelector('button[title*="Browser pending replacement"]')
                    && window.__ownerImagePendingRequests === 0) {
                    const modal = dialog.querySelector('[data-owner-image-modal="true"]');
                    const details = state.querySelector('button[data-media-details-id]') ?? null;

                    if (! details?.isConnected) {
                        await new Promise((resolve) => setTimeout(resolve, 25));

                        continue;
                    }

                    const before = {
                        path: location.pathname,
                        scroll: modal.scrollTop,
                        pending: state.textContent.trim(),
                    };
                    await new Promise((resolve) => requestAnimationFrame(
                        () => requestAnimationFrame(resolve),
                    ));
                    const currentDialog = document.querySelector(
                        '[aria-modal="true"].fi-modal-open',
                    );
                    const currentModal = currentDialog?.querySelector(
                        '[data-owner-image-modal="true"]',
                    ) ?? null;
                    const currentState = currentDialog?.querySelector(
                        '[data-testid="owner-image-pending-state"]',
                    ) ?? null;
                    const currentDetails = currentState?.querySelector('button[data-media-details-id]') ?? null;

                    return {
                        pending: Boolean(currentState?.querySelector(
                            'button[title*="Browser pending replacement"]',
                        )),
                        details_connected: Boolean(currentDetails?.isConnected),
                        details_media_id: currentDetails?.getAttribute('data-media-details-id') ?? null,
                        path_unchanged: location.pathname === before.path,
                        modal_unchanged: Boolean(currentModal?.isConnected)
                            && currentDialog.classList.contains('fi-modal-open'),
                        pending_unchanged: currentState?.textContent.trim() === before.pending,
                        scroll_unchanged: currentModal
                            ? Math.abs(currentModal.scrollTop - before.scroll) <= 1
                            : false,
                        failed_requests: window.__ownerImageFailedRequests,
                    };
                }

                await new Promise((resolve) => setTimeout(resolve, 25));
            }

            throw new Error('Timed out while proving pending owner choice.');
        }
        JS);

    if ($locale === 'he' && $mobile) {
        ownerImageBrowserCapture($page, '03-episode-pending-he-390.png');
        ownerImageBrowserCapture($page, '10-details-original-modal-he-390.png');
    }

    $slideOver = $page->script(<<<'JS'
        async () => {
            const waitUntil = async (predicate) => {
                const started = performance.now();

                while (performance.now() - started < 10000) {
                    const result = predicate();

                    if (result) {
                        return result;
                    }

                    await new Promise((resolve) => setTimeout(resolve, 25));
                }

                throw new Error('Timed out inside the media details slide-over flow.');
            };
            const ownerDialog = () => Array.from(document.querySelectorAll(
                '[aria-modal="true"].fi-modal-open',
            )).find((dialog) => dialog.querySelector('[data-owner-image-modal="true"]'));
            const detailsDialog = () => Array.from(document.querySelectorAll(
                '[aria-modal="true"].fi-modal-open',
            )).find((dialog) => dialog.querySelector('[data-testid="media-details-slide-over"]'));

            ownerDialog()?.querySelector(
                '[data-testid="owner-image-pending-state"] button[data-media-details-id]',
            )?.click();
            await waitUntil(() => detailsDialog());
            const opened = {
                slide_over_open: Boolean(detailsDialog()),
                owner_modal_open: Boolean(ownerDialog()),
                name_present: Boolean(detailsDialog()?.textContent.includes('Browser pending replacement')),
                path: location.pathname,
            };
            detailsDialog()?.querySelector('.fi-modal-footer-actions button')?.click();
            await waitUntil(() => ! detailsDialog());
            await waitUntil(() => window.__ownerImagePendingRequests === 0);

            return {
                ...opened,
                slide_over_closed: ! detailsDialog(),
                owner_modal_still_open: Boolean(ownerDialog()),
                pending_still_present: Boolean(ownerDialog()?.querySelector(
                    '[data-testid="owner-image-pending-state"] button[title*="Browser pending replacement"]',
                )),
                path_unchanged: location.pathname === opened.path,
            };
        }
        JS);

    $episodeOpenerId = ownerImageBrowserJsValue($episode['opener_id']);

    if ($mobile) {
        ownerImageBrowserTap(
            $page,
            '.fi-modal-footer-actions button:not([type="submit"])',
        );
    } else {
        ownerImageBrowserPress($page, 'body', 'Escape');
    }

    $closedOwnerAction = $page->script(<<<JS
        async () => {
            const started = performance.now();

            while (performance.now() - started < 10000) {
                const closed = ! document.querySelector(
                    '[aria-modal="true"].fi-modal-open',
                );
                const focusRestored = document.activeElement?.id === {$episodeOpenerId};
                const pageUsable = Boolean(document.getElementById(
                    {$episodeOpenerId},
                )?.isConnected);

                if (closed
                    && pageUsable
                    && window.__ownerImagePendingRequests === 0) {
                    return {
                        closed,
                        focus_restored: focusRestored,
                        page_usable: pageUsable,
                        failed_requests: window.__ownerImageFailedRequests,
                        unhandled_rejections: window.__ownerImageUnhandledRejections,
                    };
                }

                await new Promise((resolve) => setTimeout(resolve, 25));
            }

            throw new Error('Timed out while closing the owner action and returning to the page.');
        }
        JS);

    expect($pending['pending'])->toBeTrue(json_encode($pending, JSON_THROW_ON_ERROR))
        ->and($pending['details_connected'])->toBeTrue()
        ->and($pending['details_media_id'])->toBe((string) $fixtures['replacement']->getKey())
        ->and($pending['path_unchanged'])->toBeTrue()
        ->and($pending['modal_unchanged'])->toBeTrue()
        ->and($pending['pending_unchanged'])->toBeTrue()
        ->and($pending['scroll_unchanged'])->toBeTrue()
        ->and($slideOver['slide_over_open'])->toBeTrue(json_encode($slideOver, JSON_THROW_ON_ERROR))
        ->and($slideOver['owner_modal_open'])->toBeTrue()
        ->and($slideOver['name_present'])->toBeTrue()
        ->and($slideOver['slide_over_closed'])->toBeTrue()
        ->and($slideOver['owner_modal_still_open'])->toBeTrue()
        ->and($slideOver['pending_still_present'])->toBeTrue()
        ->and($slideOver['path_unchanged'])->toBeTrue()
        ->and($pending['failed_requests'])->toBe(0)
        ->and($closedOwnerAction['closed'])->toBeTrue(
            json_encode($closedOwnerAction, JSON_THROW_ON_ERROR),
        )
        ->and($closedOwnerAction['page_usable'])->toBeTrue()
        ->and($closedOwnerAction['failed_requests'])->toBe(0)
        ->and($closedOwnerAction['unhandled_rejections'])->toBe([])
        ->and($fixtures['item']->refresh()->primaryImageMediaAttachment()->value('media_id'))
        ->toBe($fixtures['item_direct']->getKey());

    if ($locale === 'en' && $mobile) {
        $page->navigate(ContentGroupResource::getUrl('index'));
        $page->script('() => { window.__ownerImageUnhandledRejections = []; }');
        ownerImageBrowserOpenDedicatedAction(
            $page,
            'chooseContentGroupCover',
            __('admin.owner_image.actions.change_podcast_cover'),
            __('admin.owner_image.actions.save_podcast_cover'),
            $direction,
            $mobile,
        );
        ownerImageBrowserCapture($page, '02-podcast-modal-en-390.png');
    }

    $page->assertNoBrokenImages()->assertNoSmoke();
})->with(ownerImageBrowserSurfaceCases());

it('proves embedded owner image forms across classic and workspace surfaces', function (
    string $locale,
    string $direction,
    string $device,
    bool $mobile,
): void {
    app()->setLocale($locale);
    $fixtures = ownerImageBrowserFixtures();
    $page = ownerImageBrowserPage(
        ContentGroupResource::getUrl('edit', ['record' => $fixtures['group']]),
        $device,
    );
    trackOwnerImageBrowserRequests($page);

    $routes = [
        ContentGroupResource::getUrl('edit', ['record' => $fixtures['group']]),
        ContentItemResource::getUrl('edit', ['record' => $fixtures['item']]),
        ContentItemResource::getUrl('workspace', ['record' => $fixtures['item']]),
    ];
    $surfaceEvidence = [];

    foreach ($routes as $index => $route) {
        if ($index > 0) {
            $page->navigate($route);
        }

        $surfaceEvidence[] = $page->script(<<<'JS'
            async () => {
                // stable-read (R4): a layout value counts only when two consecutive
                // animation-frame reads agree (cap 10 frames) — a mid-reflow transient
                // can no longer become an assertion input.
                const stableRead = async (fn) => {
                    const frame = () => new Promise((resolve) => requestAnimationFrame(resolve));
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

                while (performance.now() - started < 10000) {
                    const states = Array.from(document.querySelectorAll(
                        '[data-testid="owner-image-choice-state"]',
                    )).filter((element) => element.getClientRects().length);
                    const launchers = Array.from(document.querySelectorAll(
                        '[data-testid="media-picker-open"]',
                    )).filter((element) => element.getClientRects().length);

                    if (states.length && launchers.length) {
                        const ownedText = states.map((state) => state.textContent).join(' ');

                        return {
                            direction: document.documentElement.dir,
                            state_count: states.length,
                            launcher_count: launchers.length,
                            direct: states.every((state) => state.querySelector(
                                '[data-testid="owner-image-direct-state"]',
                            )),
                            shown: states.every((state) => state.querySelector(
                                '[data-testid="owner-image-shown-now-state"]',
                            )),
                            pending: states.every((state) => state.querySelector(
                                '[data-testid="owner-image-pending-state"]',
                            )),
                            raw_key_absent: ! /\badmin\.[a-z0-9_.]+/i.test(ownedText),
                            current_folder_absent: ! ownedText.includes('Current folder')
                                && ! ownedText.includes('התיקייה הנוכחית'),
                            horizontal_overflow: await stableRead(() => document.documentElement.scrollWidth
                                > document.documentElement.clientWidth + 1),
                        };
                    }

                    await new Promise((resolve) => setTimeout(resolve, 25));
                }

                throw new Error('Timed out while reading an embedded owner image form.');
            }
            JS);
    }

    foreach ($surfaceEvidence as $evidence) {
        expect($evidence['direction'])->toBe($direction)
            ->and($evidence['state_count'])->toBeGreaterThanOrEqual(1)
            ->and($evidence['launcher_count'])->toBeGreaterThanOrEqual(1)
            ->and($evidence['direct'])->toBeTrue(json_encode($evidence, JSON_THROW_ON_ERROR))
            ->and($evidence['shown'])->toBeTrue()
            ->and($evidence['pending'])->toBeTrue()
            ->and($evidence['raw_key_absent'])->toBeTrue()
            ->and($evidence['current_folder_absent'])->toBeTrue()
            ->and($evidence['horizontal_overflow'])->toBeFalse();
    }

    $episodeImageHeading = ownerImageBrowserJsValue(__('admin.sections.episode_image'));
    $playerEmbedHeading = ownerImageBrowserJsValue(__('admin.sections.player_embed'));
    $workspaceSeparation = $page->script(<<<JS
        () => {
            const text = document.body.innerText;
            const imageHeading = {$episodeImageHeading};
            const playerHeading = {$playerEmbedHeading};

            return {
                image_present: text.includes(imageHeading),
                player_present: text.includes(playerHeading),
                image_before_player: text.indexOf(imageHeading) < text.indexOf(playerHeading),
                raw_key_absent: ! /\\badmin\\.[a-z0-9_.]+/i.test(
                    document.querySelector('main')?.textContent ?? ''
                ),
            };
        }
        JS);

    expect($workspaceSeparation['image_present'])->toBeTrue()
        ->and($workspaceSeparation['player_present'])->toBeTrue()
        ->and($workspaceSeparation['image_before_player'])->toBeTrue()
        ->and($workspaceSeparation['raw_key_absent'])->toBeTrue();

    if ($locale === 'he' && ! $mobile) {
        ownerImageBrowserCapture(
            $page,
            '05-episode-workspace-image-vs-player-he-desktop.png',
            'main',
        );
    }

    $focus = $page->script(<<<'JS'
        async () => {
            const waitFor = async (callback, timeout = 10000) => {
                const started = performance.now();

                while (performance.now() - started < timeout) {
                    const value = callback();

                    if (value) {
                        return value;
                    }

                    await new Promise((resolve) => setTimeout(resolve, 25));
                }

                throw new Error('Timed out while proving embedded picker focus return.');
            };
            const launcher = Array.from(document.querySelectorAll(
                '[data-testid="media-picker-open"]',
            )).find((element) => element.getClientRects().length);
            const launcherId = launcher.id;
            launcher.focus();
            launcher.click();
            const picker = await waitFor(() => document.querySelector(
                '[aria-modal="true"].fi-modal-open [data-testid="media-picker"]',
            ));
            const childId = picker.getAttribute('wire:id');
            await waitFor(() => childId && window.Livewire.find(childId)?.el === picker);
            const close = picker.querySelector('[data-testid="media-picker-close"]');
            close.click();
            await waitFor(() => ! document.querySelector(
                '[aria-modal="true"].fi-modal-open [data-testid="media-picker"]',
            ));
            await waitFor(() => document.activeElement?.id === launcherId);

            return {
                launcher_id: launcherId,
                hashed_launcher_id: /^media-picker-open-[a-f0-9]{12}$/.test(launcherId),
                focus_restored: document.activeElement?.id === launcherId,
                failed_requests: window.__ownerImageFailedRequests,
                unhandled_rejections: window.__ownerImageUnhandledRejections,
            };
        }
        JS);

    expect($focus['hashed_launcher_id'])->toBeTrue(json_encode($focus, JSON_THROW_ON_ERROR))
        ->and($focus['focus_restored'])->toBeTrue()
        ->and($focus['failed_requests'])->toBe(0)
        ->and($focus['unhandled_rejections'])->toBe([]);

    $page->assertNoBrokenImages()->assertNoSmoke();
})->with(ownerImageBrowserSurfaceCases());

it('proves relation manager owner images on the create surface', function (
    string $locale,
    string $direction,
    string $device,
    bool $mobile,
): void {
    app()->setLocale($locale);
    $fixtures = ownerImageBrowserFixtures();
    $relationUrl = ContentGroupResource::getUrl(
        'edit',
        ['record' => $fixtures['group']],
    ).'?activeRelationManager=contentItems';
    $page = ownerImageBrowserPage($relationUrl, $device);
    trackOwnerImageBrowserRequests($page);

    $episodesLabel = ownerImageBrowserJsValue(__('admin.relations.episodes'));
    $classicCreateLabel = ownerImageBrowserJsValue(__('admin.actions.classic_create'));
    $create = $page->script(<<<JS
        async () => {
            // stable-read (R4): a layout value counts only when two consecutive
            // animation-frame reads agree (cap 10 frames) — a mid-reflow transient
            // can no longer become an assertion input.
            const stableRead = async (fn) => {
                const frame = () => new Promise((resolve) => requestAnimationFrame(resolve));
                let previous = fn();
                for (let i = 0; i < 10; i++) {
                    await frame();
                    const current = fn();
                    if (JSON.stringify(current) === JSON.stringify(previous)) return current;
                    previous = current;
                }
                return previous;
            };
            const waitFor = async (callback, timeout = 10000) => {
                const started = performance.now();

                while (performance.now() - started < timeout) {
                    const value = callback();

                    if (value) {
                        return value;
                    }

                    await new Promise((resolve) => setTimeout(resolve, 25));
                }

                throw new Error('Timed out while opening relation create owner surface.');
            };
            const relationTab = Array.from(document.querySelectorAll('button'))
                .find((button) => button.textContent.includes(
                    {$episodesLabel}
                ));
            relationTab?.click();
            const create = await waitFor(() => Array.from(document.querySelectorAll('button'))
                .find((button) => button.textContent.includes(
                    {$classicCreateLabel}
                )));
            const openerId = create.id || (create.id = 'task-nine-relation-create');
            create.focus();
            create.click();
            const ready = await waitFor(() => {
                const dialog = Array.from(document.querySelectorAll(
                    '[aria-modal="true"].fi-modal-open',
                )).find((candidate) => candidate.querySelector(
                    '[data-testid="owner-image-choice-state"]',
                ));
                const state = dialog?.querySelector(
                    '[data-testid="owner-image-choice-state"]',
                ) ?? null;
                const launcher = dialog?.querySelector(
                    '[data-testid="media-picker-open"]',
                ) ?? null;

                return dialog?.isConnected
                    && state?.isConnected
                    && launcher?.isConnected
                    && window.__ownerImagePendingRequests === 0
                        ? { dialog, state, launcher }
                        : null;
            });
            const { state, launcher } = ready;

            return {
                opener_id: openerId,
                direction: document.documentElement.dir,
                state: Boolean(state),
                direct: Boolean(state.querySelector('[data-testid="owner-image-direct-state"]')),
                shown: Boolean(state.querySelector('[data-testid="owner-image-shown-now-state"]')),
                pending: Boolean(state.querySelector('[data-testid="owner-image-pending-state"]')),
                launcher: Boolean(launcher),
                episode_count_before_create: document.querySelectorAll(
                    '[data-testid="owner-image-choice-state"]',
                ).length,
                raw_key_absent: ! /\\badmin\\.[a-z0-9_.]+/i.test(state.textContent),
                horizontal_overflow: await stableRead(() => document.documentElement.scrollWidth
                    > document.documentElement.clientWidth + 1),
            };
        }
        JS);

    expect($create['direction'])->toBe($direction)
        ->and($create['state'])->toBeTrue(json_encode($create, JSON_THROW_ON_ERROR))
        ->and($create['direct'])->toBeTrue()
        ->and($create['shown'])->toBeTrue()
        ->and($create['pending'])->toBeTrue()
        ->and($create['launcher'])->toBeTrue()
        ->and($create['raw_key_absent'])->toBeTrue()
        ->and($create['horizontal_overflow'])->toBeFalse()
        ->and($fixtures['group']->contentItems()->count())->toBe(1);

    if ($locale === 'he' && $mobile) {
        ownerImageBrowserCapture(
            $page,
            '06-relation-owner-field-he-390.png',
            '[aria-modal="true"].fi-modal-open',
        );
    }

    $page->assertNoBrokenImages()->assertNoSmoke();
})->with(ownerImageBrowserSurfaceCases());
