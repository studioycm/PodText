<?php

use App\Enums\SparklineTrend;
use Illuminate\Support\Facades\File;

/**
 * The sparkline partial's geometry contract: min/max normalisation over a
 * 2px-inset band, so a series' own variation fills the box instead of being
 * squeezed against the peak, and degenerate series sit on the midline.
 *
 * Coordinates are asserted exactly — the y-maths is the behaviour under test,
 * and a loose "contains polyline" assertion would have passed against the
 * peak-only defect this file exists to pin down.
 */
function renderSparkline(array $points): string
{
    return view('filament.widgets.partials.sparkline', [
        'points' => $points,
        'testid' => 'spark-under-test',
    ])->render();
}

it('normalises points over the min-max span so small variation keeps its shape', function (): void {
    // Peak-only normalisation rendered [8, 9, 10] almost flat near the top
    // (y 6.40 → 2.20); over max−min the same series spans the full band.
    expect(renderSparkline([8, 9, 10]))
        ->toContain('points="0.00,22.00 50.00,12.00 100.00,2.00"');
});

it('keeps the time axis left to right inside the rtl board', function (): void {
    expect(renderSparkline([10, 2]))
        ->toContain('points="0.00,2.00 100.00,22.00"')
        ->toContain('dir="ltr"');
});

it('renders an all-equal series on the midline, not against an edge', function (): void {
    // Peak-only maths pinned [5, 5, 5] to the top edge (y 2.00): a healthy
    // steady series looked like a ceiling instead of a calm middle.
    expect(renderSparkline([5, 5, 5]))
        ->toContain('points="0.00,12.00 50.00,12.00 100.00,12.00"');
});

it('renders an all-zero series on the midline, not along the bottom edge', function (): void {
    expect(renderSparkline([0, 0, 0]))
        ->toContain('points="0.00,12.00 50.00,12.00 100.00,12.00"');
});

it('draws no line for a single point or an empty series', function (): void {
    // A one-point polyline paints nothing anyway; the guard makes the empty
    // svg deliberate, and the y-maths must not divide by the zero span.
    expect(renderSparkline([7]))->not->toContain('<polyline')
        ->and(renderSparkline([]))->not->toContain('<polyline');
});

it('strokes the polyline with the trend class the caller supplies', function (): void {
    $html = view('filament.widgets.partials.sparkline', [
        'points' => [1, 2],
        'testid' => 'spark-under-test',
        'stroke' => SparklineTrend::Up->strokeClass(),
    ])->render();

    // The class must land on the polyline itself, where it overrides the
    // currentColor fallback; without a caller-supplied trend the line keeps
    // inheriting its wrapper's colour.
    expect($html)->toContain('class="'.SparklineTrend::Up->strokeClass().'"')
        ->and(renderSparkline([1, 2]))->not->toContain('class="stroke-');
});

it('keeps the trend palette out of widget view sources', function (): void {
    // The trend colours live in SparklineTrend alone. A hand-written copy in
    // a view would render identically today and drift silently later, so this
    // scans the widget view sources rather than any rendered output.
    $literals = ['stroke-success', 'stroke-danger', 'stroke-gray', 'text-success-600', 'text-danger-600'];

    foreach (File::allFiles(resource_path('views/filament/widgets')) as $file) {
        $source = $file->getContents();

        foreach ($literals as $literal) {
            expect(str_contains($source, $literal))->toBeFalse(
                "views/filament/widgets/{$file->getRelativePathname()} hand-writes {$literal};"
                .' trend colours must come from SparklineTrend.',
            );
        }
    }
});
