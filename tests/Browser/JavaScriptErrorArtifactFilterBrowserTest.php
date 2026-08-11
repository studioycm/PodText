<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * Pins the shared artifact filter (tests/Pest.php) that the media-picker and
 * gallery browser tests route their JavaScript-error assertions through.
 *
 * Without this, the filter is only ever exercised by runs where the artifact
 * happens to fire — which is never in isolation and about half the time under
 * full-suite load. A filter that never fires proves nothing, so the count and
 * the strictness are asserted here directly rather than inferred from a green
 * suite.
 */

uses(RefreshDatabase::class);

function seedJavaScriptErrorAccumulator(object $page, array $messages): void
{
    $payload = json_encode($messages, JSON_THROW_ON_ERROR);

    $page->script(<<<JS
        () => {
            window.__pestBrowser.jsErrors = {$payload}.map((message) => ({ message }));
        }
        JS);
}

it('strips only the exact classified artifact and reports how many it removed', function (): void {
    $page = visit('/');

    $nearMiss = rtrim(knownResizeObserverArtifact(), '.');

    seedJavaScriptErrorAccumulator($page, [
        knownResizeObserverArtifact(),
        'TypeError: undefined is not a function',
        knownResizeObserverArtifact(),
        $nearMiss,
    ]);

    $stripped = stripKnownResizeObserverArtifacts($page);

    $survivors = $page->script(<<<'JS'
        () => (window.__pestBrowser?.jsErrors ?? []).map((error) => error.message)
        JS);

    // The near-miss differs from the classified message by its trailing period
    // alone: a substring match would swallow it, and swallowing unclassified
    // ResizeObserver variants is what this filter must never do.
    expect($stripped)->toBe(2)
        ->and($survivors)->toBe([
            'TypeError: undefined is not a function',
            $nearMiss,
        ]);
});

it('passes the assertion when the accumulator holds nothing but artifacts', function (): void {
    $page = visit('/');

    seedJavaScriptErrorAccumulator($page, [
        knownResizeObserverArtifact(),
        knownResizeObserverArtifact(),
        knownResizeObserverArtifact(),
    ]);

    // Three stripped, and the run is not a literal zero-message run — which is
    // why the count comes back rather than being discarded inside the filter.
    expect(assertNoUnexpectedJavaScriptErrors($page))->toBe(3);
});
