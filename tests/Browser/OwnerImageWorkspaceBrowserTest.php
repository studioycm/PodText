<?php

use App\Enums\MediaAttachmentRole;
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

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Http::preventStrayRequests();
    Storage::fake('local');
    Storage::fake('public');
    $this->actingAs(User::factory()->admin()->create());
});

function ownerImageBrowserMedia(string $path): Media
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
        'title' => 'Browser owner image',
        'exif' => ['original_filename' => 'Browser original filename.jpg'],
    ]);

    return $media;
}

it('keeps owner image details bounded accessible and same-page', function (
    string $locale,
    string $direction,
): void {
    app()->setLocale($locale);
    $actor = auth()->user();
    $group = ContentGroup::factory()->create();
    $item = ContentItem::factory()->for($group)->create(['title' => "Owner image {$locale}"]);
    $media = ownerImageBrowserMedia('content-items/images/browser-owner-image.jpg');
    app(MediaAttachmentManager::class)->attach(
        $item,
        $media,
        MediaAttachmentRole::PrimaryImage,
        $actor,
    );

    $page = visit(ContentItemResource::getUrl('index'))->resize(1280, 900);

    $wide = $page->script(<<<'JS'
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

                throw new Error('Timed out while waiting for owner image workspace evidence.');
            };
            const thumbnailImage = await waitFor(() => document.querySelector(
                '[data-testid="owner-image-thumbnail"] img',
            ));
            const opener = thumbnailImage.closest('button');
            const initialPath = window.location.pathname;

            opener.focus();
            const keyboardFocusReachedThumbnail = document.activeElement === opener;
            thumbnailImage.dispatchEvent(new MouseEvent('mouseenter', { bubbles: true }));
            const tooltipImage = await waitFor(() => document.querySelector(
                '[data-tippy-root] img',
            ));
            const tooltipRect = tooltipImage.getBoundingClientRect();

            window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
            await new Promise((resolve) => setTimeout(resolve, 100));
            opener.click();

            const workspace = await waitFor(() => document.querySelector(
                '[aria-modal="true"].fi-modal-open [data-testid="owner-image-workspace"]',
            ));
            const dialog = workspace.closest('[aria-modal="true"]');
            const modalWindow = dialog.querySelector('.fi-modal-window');
            const preview = workspace.querySelector('[data-testid="owner-image-effective-preview"]');
            const previewRect = preview.getBoundingClientRect();
            const copyButton = workspace.querySelector('[data-testid="owner-image-copy-stored-filename"]');
            copyButton.click();
            const copied = await waitFor(() => Array.from(
                workspace.querySelectorAll('[role="status"]'),
            ).find((status) => getComputedStyle(status).display !== 'none'));
            const download = workspace.querySelector('[data-testid="owner-image-download"]');
            const storedFilename = workspace.querySelector(
                '[data-testid="owner-image-metadata-stored-filename"]',
            );

            return {
                direction: document.documentElement.dir,
                keyboard_focus_reached_thumbnail: keyboardFocusReachedThumbnail,
                thumbnail_is_button: opener?.tagName === 'BUTTON',
                thumbnail_alt_present: Boolean(thumbnailImage.getAttribute('alt')),
                tooltip_width: tooltipRect.width,
                tooltip_height: tooltipRect.height,
                same_path: window.location.pathname === initialPath,
                open_dialogs: document.querySelectorAll('[aria-modal="true"].fi-modal-open').length,
                nested_forms: dialog.querySelectorAll('form form').length,
                preview_width: previewRect.width,
                preview_height: previewRect.height,
                copied_feedback_visible: Boolean(copied?.textContent.trim()),
                stored_filename_direction: storedFilename?.getAttribute('dir'),
                safe_download: download?.getAttribute('href')?.includes('/admin/media-files/')
                    && download?.getAttribute('href')?.includes('/download')
                    && ! download?.getAttribute('href')?.includes('/storage/'),
                modal_within_viewport: modalWindow.getBoundingClientRect().left >= -1
                    && modalWindow.getBoundingClientRect().right <= window.innerWidth + 1,
                workspace_horizontal_overflow: workspace.scrollWidth > workspace.clientWidth + 1,
            };
        }
        JS);

    expect($wide['direction'])->toBe($direction)
        ->and($wide['keyboard_focus_reached_thumbnail'])->toBeTrue(json_encode($wide, JSON_THROW_ON_ERROR))
        ->and($wide['thumbnail_is_button'])->toBeTrue()
        ->and($wide['thumbnail_alt_present'])->toBeTrue()
        ->and($wide['tooltip_width'])->toBeLessThanOrEqual(300)
        ->and($wide['tooltip_height'])->toBeLessThanOrEqual(300)
        ->and($wide['same_path'])->toBeTrue()
        ->and($wide['open_dialogs'])->toBe(1)
        ->and($wide['nested_forms'])->toBe(0)
        ->and($wide['preview_width'])->toBeLessThanOrEqual(300)
        ->and($wide['preview_height'])->toBeLessThanOrEqual(300)
        ->and($wide['copied_feedback_visible'])->toBeTrue()
        ->and($wide['stored_filename_direction'])->toBe('ltr')
        ->and($wide['safe_download'])->toBeTrue()
        ->and($wide['modal_within_viewport'])->toBeTrue()
        ->and($wide['workspace_horizontal_overflow'])->toBeFalse();

    $closed = $page->script(<<<'JS'
        async () => {
            const started = performance.now();
            const opener = document.querySelector(
                '[data-testid="owner-image-thumbnail"] img',
            )?.closest('button');

            window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));

            while (document.querySelector('[data-testid="owner-image-workspace"]')
                && performance.now() - started < 7000) {
                await new Promise((resolve) => setTimeout(resolve, 25));
            }

            while (document.activeElement !== opener && performance.now() - started < 7000) {
                await new Promise((resolve) => setTimeout(resolve, 25));
            }

            return {
                closed: ! document.querySelector('[data-testid="owner-image-workspace"]'),
                focus_restored: document.activeElement === opener,
            };
        }
        JS);

    expect($closed['closed'])->toBeTrue(json_encode($closed, JSON_THROW_ON_ERROR))
        ->and($closed['focus_restored'])->toBeTrue(json_encode($closed, JSON_THROW_ON_ERROR));

    $page->resize(390, 844);
    $narrow = $page->script(<<<'JS'
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

                throw new Error('Timed out while waiting for narrow owner image workspace.');
            };
            const opener = document.querySelector(
                '[data-testid="owner-image-thumbnail"] img',
            )?.closest('button');
            opener.dispatchEvent(new PointerEvent('pointerdown', {
                bubbles: true,
                pointerType: 'touch',
            }));
            opener.click();
            const workspace = await waitFor(() => document.querySelector(
                '[aria-modal="true"].fi-modal-open [data-testid="owner-image-workspace"]',
            ));
            const modalWindow = workspace.closest('[aria-modal="true"]').querySelector('.fi-modal-window');
            const rect = await waitFor(() => {
                const candidate = modalWindow.getBoundingClientRect();
                const style = getComputedStyle(modalWindow);
                const transformSettled = style.transform === 'none'
                    || style.transform === 'matrix(1, 0, 0, 1, 0, 0)';

                return transformSettled && Number.parseFloat(style.opacity) === 1
                    ? candidate
                    : null;
            });
            const modalStyle = getComputedStyle(modalWindow);
            const widestChild = Array.from(workspace.querySelectorAll('*'))
                .map((element) => ({
                    tag: element.tagName,
                    testid: element.getAttribute('data-testid'),
                    classes: element.className,
                    width: element.getBoundingClientRect().width,
                    scroll_width: element.scrollWidth,
                    client_width: element.clientWidth,
                }))
                .sort((left, right) => right.scroll_width - left.scroll_width)[0];
            const previewRect = workspace
                .querySelector('[data-testid="owner-image-effective-preview"]')
                .getBoundingClientRect();

            window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));

            return {
                viewport_width: window.innerWidth,
                direction: document.documentElement.dir,
                modal_left: rect.left,
                modal_right: rect.right,
                modal_width: rect.width,
                modal_max_width: modalStyle.maxWidth,
                modal_css_width: modalStyle.width,
                widest_child: widestChild,
                modal_within_viewport: rect.left >= -1 && rect.right <= window.innerWidth + 1,
                document_horizontal_overflow: document.documentElement.scrollWidth
                    > document.documentElement.clientWidth + 1,
                workspace_horizontal_overflow: workspace.scrollWidth > workspace.clientWidth + 1,
                preview_width: previewRect.width,
                preview_height: previewRect.height,
            };
        }
        JS);

    expect($narrow['viewport_width'])->toBe(390)
        ->and($narrow['direction'])->toBe($direction)
        ->and($narrow['modal_within_viewport'])->toBeTrue(json_encode($narrow, JSON_THROW_ON_ERROR))
        ->and($narrow['document_horizontal_overflow'])->toBeFalse()
        ->and($narrow['workspace_horizontal_overflow'])->toBeFalse()
        ->and($narrow['preview_width'])->toBeLessThanOrEqual(300)
        ->and($narrow['preview_height'])->toBeLessThanOrEqual(300);

    $page->assertNoJavaScriptErrors();
})->with([
    'Hebrew RTL' => ['he', 'rtl'],
    'English LTR' => ['en', 'ltr'],
]);
