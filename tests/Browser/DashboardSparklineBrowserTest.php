<?php

use App\Enums\TranscriptionMode;
use App\Filament\Pages\Dashboard;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\Transcription;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    setTestTranscriptionMode(TranscriptionMode::Multi);
    app()->setLocale('he');
    // Frozen Jerusalem wall clock: the served requests share this process, so
    // the range's latest day is 31/07 and the fixture day 30/07 — the exact
    // labels the keyboard walk asserts.
    Carbon::setTestNow(Carbon::parse('2026-07-31 10:00:00', 'Asia/Jerusalem'));
    $this->actingAs(User::factory()->admin()->create());
});

afterEach(function (): void {
    Carbon::setTestNow();
});

/** The labelled waitFor helper every driver script shares. */
function sparklineHoverHelpers(): string
{
    return <<<'JS'
        const waitFor = async (callback, label, timeout = 8000) => {
            const started = performance.now();
            while (performance.now() - started < timeout) {
                const value = callback();
                if (value) { return value; }
                await new Promise((resolve) => setTimeout(resolve, 100));
            }
            throw new Error('timeout: ' + label);
        };
        const hoverLayer = () => document.querySelector('[data-testid="funnel-spark-published-hover"]');
        const tooltip = () => document.querySelector('[data-testid="funnel-spark-published-tooltip"]');
        const crosshair = () => document.querySelector('[data-testid="funnel-spark-published-crosshair"]');
        const liveRegion = () => hoverLayer()?.parentElement.querySelector('[aria-live="polite"]');
        const tooltipText = () => (tooltip()?.textContent ?? '').replace(/\s+/g, ' ').trim();
        const tooltipVisible = () => tooltip()?.offsetParent !== null;
        JS;
}

it('shows a hover tooltip, walks points with arrow keys and dismisses on escape', function (): void {
    $group = ContentGroup::factory()->published()->create(['title' => 'Alpha Podcast']);
    $item = ContentItem::factory()->for($group)
        ->published(Carbon::parse('2026-07-30 09:00', 'Asia/Jerusalem'))
        ->create(['embed_url' => 'https://open.spotify.com/episode/spark1']);
    Transcription::factory()->for($item)
        ->published(Carbon::parse('2026-07-30 10:00', 'Asia/Jerusalem'))
        ->create();

    $page = visit(Dashboard::getUrl())->resize(1280, 900);

    $helpers = sparklineHoverHelpers();

    $setup = $page->page()->evaluate(<<<JS
        async () => {
            {$helpers}

            // The funnel widget is lazy: bring it into the viewport so the
            // intersection observer hydrates it, then let Alpine claim the
            // hover layer before any interaction.
            const shell = await waitFor(
                () => document.querySelector('[wire\\\\:name\$="publication-funnel-widget"]'),
                'the funnel widget shell is on the page',
            );
            shell.scrollIntoView({ block: 'center' });
            await waitFor(() => hoverLayer(), 'the funnel sparkline hover layer hydrated');
            // Alpine attaches the data stack to the x-data root — the hover
            // layer's parent — so the boot probe must look there.
            await waitFor(
                () => window.Alpine && hoverLayer().closest('[x-data]')._x_dataStack,
                'alpine booted on the hover layer',
            );

            return {
                boardDirection: getComputedStyle(document.documentElement).direction,
                axisDirection: getComputedStyle(hoverLayer()).direction,
                tooltipHiddenBeforeAnyInteraction: ! tooltipVisible(),
            };
        }
        JS);

    // The board is RTL while the time axis and its crosshair stay LTR.
    expect($setup['boardDirection'])->toBe('rtl')
        ->and($setup['axisDirection'])->toBe('ltr')
        ->and($setup['tooltipHiddenBeforeAnyInteraction'])->toBeTrue();

    $page->hover('[data-testid="funnel-spark-published-hover"]');

    $afterHover = $page->page()->evaluate(<<<JS
        async () => {
            {$helpers}

            await waitFor(() => tooltipVisible(), 'the tooltip is visible after pointer hover');

            return {
                text: tooltipText(),
                crosshairShown: getComputedStyle(crosshair()).display !== 'none',
            };
        }
        JS);

    // The pointer sits mid-axis, so the exact day depends on pixel rounding;
    // the shape proves the server-formatted day-first label reached the
    // browser untouched. The keyboard walk below pins exact days.
    expect($afterHover['text'])->toMatch('/^\d{2}\/\d{2}\/\d{4} · \d+$/')
        ->and($afterHover['crosshairShown'])->toBeTrue();

    // Keys target the focusable layer itself: focus lands on the latest day
    // (31/07), one ArrowLeft reaches the fixture's publication day.
    $page->keys('[data-testid="funnel-spark-published-hover"]', 'ArrowLeft');

    $afterArrowLeft = $page->page()->evaluate(<<<JS
        async () => {
            {$helpers}

            await waitFor(
                () => tooltipText() === '30/07/2026 · 1',
                'arrow-left walked the crosshair to the fixture day',
            );

            return { live: liveRegion()?.textContent ?? '' };
        }
        JS);

    // The live region mirrors the walked point for screen readers.
    expect($afterArrowLeft['live'])->toBe('30/07/2026 — 1');

    $page->keys('[data-testid="funnel-spark-published-hover"]', 'ArrowRight');

    $page->page()->evaluate(<<<JS
        async () => {
            {$helpers}

            await waitFor(
                () => tooltipText() === '31/07/2026 · 0',
                'arrow-right walked the crosshair back to the latest day',
            );

            return true;
        }
        JS);

    $page->keys('[data-testid="funnel-spark-published-hover"]', 'Escape');

    $afterEscape = $page->page()->evaluate(<<<JS
        async () => {
            {$helpers}

            await waitFor(() => ! tooltipVisible(), 'escape dismissed the tooltip');
            // x-show applies on Alpine's own frame cadence, so the crosshair
            // is waited on like every other visibility change, never single-read.
            await waitFor(
                () => getComputedStyle(crosshair()).display === 'none',
                'escape dismissed the crosshair',
            );
            await waitFor(
                () => (liveRegion()?.textContent ?? '') === '',
                'escape cleared the live region',
            );

            return true;
        }
        JS);

    expect($afterEscape)->toBeTrue();

    $page->assertNoJavascriptErrors();
});
