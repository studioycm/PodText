<?php

use App\Filament\Resources\Media\MediaResource;
use App\Models\Media;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
    $needsRepair = json_encode(__('admin.media_library.needs_repair'), JSON_THROW_ON_ERROR);
    $wide = $page->script(<<<JS
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
                {$needsRepair},
            ));

            return {
                direction: document.documentElement.dir,
                card_count: cards.length,
                desktop_columns: desktopColumns,
                metadata_visible: cards.every((card) =>
                    card.querySelector('[data-testid="media-library-card-stored-filename"]')
                    && card.querySelector('[data-testid="media-library-card-file-summary"]')
                ),
                image_object_fits: images.map((image) => getComputedStyle(image).objectFit),
                lazy_images: images.every((image) => image.getAttribute('loading') === 'lazy'),
                needs_repair_visible: Boolean(missingCard),
                bulk_selection_available: Boolean(document.querySelector(
                    '.fi-ta input[type="checkbox"]',
                )),
                horizontal_overflow: document.documentElement.scrollWidth
                    > document.documentElement.clientWidth + 1,
            };
        }
        JS);

    expect($wide['direction'])->toBe($direction)
        ->and($wide['card_count'])->toBe(6)
        ->and($wide['desktop_columns'])->toBe(3, json_encode($wide, JSON_THROW_ON_ERROR))
        ->and($wide['metadata_visible'])->toBeTrue()
        ->and($wide['image_object_fits'])->each->toBe('contain')
        ->and($wide['lazy_images'])->toBeTrue()
        ->and($wide['needs_repair_visible'])->toBeTrue()
        ->and($wide['bulk_selection_available'])->toBeTrue()
        ->and($wide['horizontal_overflow'])->toBeFalse();

    $page->resize(390, 844);
    $narrow = $page->script(<<<'JS'
        async () => {
            await new Promise((resolve) => setTimeout(resolve, 250));
            const cards = Array.from(document.querySelectorAll(
                '[data-testid="media-library-card"]',
            ));
            const lefts = new Set(cards.map((card) => Math.round(
                card.getBoundingClientRect().left,
            )));
            const viewportWidth = window.innerWidth;

            return {
                viewport_width: viewportWidth,
                direction: document.documentElement.dir,
                card_count: cards.length,
                columns: lefts.size,
                every_card_within_viewport: cards.every((card) => {
                    const rect = card.getBoundingClientRect();

                    return rect.left >= -1 && rect.right <= viewportWidth + 1;
                }),
                metadata_visible: cards.every((card) => {
                    const metadata = card.querySelector(
                        '[data-testid="media-library-card-file-summary"]',
                    );

                    return metadata && metadata.getClientRects().length > 0;
                }),
                horizontal_overflow: document.documentElement.scrollWidth
                    > document.documentElement.clientWidth + 1,
            };
        }
        JS);

    expect($narrow['viewport_width'])->toBe(390)
        ->and($narrow['direction'])->toBe($direction)
        ->and($narrow['card_count'])->toBe(6)
        ->and($narrow['columns'])->toBe(1, json_encode($narrow, JSON_THROW_ON_ERROR))
        ->and($narrow['every_card_within_viewport'])->toBeTrue()
        ->and($narrow['metadata_visible'])->toBeTrue()
        ->and($narrow['horizontal_overflow'])->toBeFalse();

    $page->assertNoJavaScriptErrors();
})->with([
    'Hebrew RTL' => ['he', 'rtl'],
    'English LTR' => ['en', 'ltr'],
]);
