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

it('navigates card selection checkboxes with arrow keys and toggles with space', function (): void {
    app()->setLocale('he');

    foreach (range(1, 8) as $index) {
        $name = "probe-card-{$index}";
        $media = Media::factory()->create([
            'reference_key' => (string) Str::ulid(),
            'disk' => 'public',
            'directory' => 'content-groups/covers',
            'visibility' => 'public',
            'name' => $name,
            'path' => "content-groups/covers/{$name}.jpg",
            'width' => 640,
            'height' => 360,
            'size' => 2048,
            'type' => 'image/jpeg',
            'ext' => 'jpg',
            'title' => "Probe {$index}",
        ]);
        Storage::disk('public')->put(
            $media->path,
            base64_decode((string) file_get_contents(base_path('tests/Fixtures/media/valid.jpg.base64')), true),
        );
    }

    $page = visit(MediaResource::getUrl('index'))->resize(1280, 900);

    $setup = $page->page()->evaluate(<<<'JS'
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

            const listPage = await waitFor(() => window.Livewire?.all()?.find(
                (component) => component.el?.querySelector('.fi-ta'),
            ), 'setup: gallery list page component found');
            await listPage.$wire.call('setCardsPerRow', 4);
            await waitFor(() => document.querySelectorAll('.podtext-card-dense').length === 8,
                'setup: eight dense cards rendered');

            document.querySelectorAll('.fi-ta-record-checkbox')[0].focus();

            return {
                scriptArmed: Boolean(window.podtextGalleryKeyboardNav),
                direction: getComputedStyle(document.querySelector('.fi-ta-content-grid')).direction,
            };
        }
        JS);

    expect($setup['scriptArmed'])->toBeTrue()
        ->and($setup['direction'])->toBe('rtl');

    $focusedIndex = <<<'JS'
        () => Array.from(document.querySelectorAll('.fi-ta-record-checkbox'))
            .indexOf(document.activeElement)
        JS;

    $page->keys('.fi-ta-record-checkbox:focus', 'ArrowLeft');
    $afterLeft = $page->page()->evaluate($focusedIndex);

    $page->keys('.fi-ta-record-checkbox:focus', 'Space');
    $afterSpace = $page->page()->evaluate(<<<'JS'
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
            const boxes = () => document.querySelectorAll('.fi-ta-record-checkbox');
            await waitFor(() => boxes()[1].checked, 'selection: second checkbox checked');
            await waitFor(() => boxes()[1].closest('.fi-ta-record').classList.contains('fi-selected'),
                'selection: second record marked selected');

            return {
                secondChecked: boxes()[1].checked,
                secondCardSelected: boxes()[1].closest('.fi-ta-record').classList.contains('fi-selected'),
            };
        }
        JS);

    $page->keys('.fi-ta-record-checkbox:focus', 'ArrowDown');
    $afterDown = $page->page()->evaluate($focusedIndex);

    $page->keys('.fi-ta-record-checkbox:focus', 'ArrowRight');
    $afterRight = $page->page()->evaluate($focusedIndex);

    $page->keys('.fi-ta-record-checkbox:focus', 'ArrowUp');
    $afterUp = $page->page()->evaluate($focusedIndex);

    expect($afterLeft)->toBe(1)
        ->and($afterSpace['secondChecked'])->toBeTrue()
        ->and($afterSpace['secondCardSelected'])->toBeTrue()
        ->and($afterDown)->toBe(5)
        ->and($afterRight)->toBe(4)
        ->and($afterUp)->toBe(0);
});
