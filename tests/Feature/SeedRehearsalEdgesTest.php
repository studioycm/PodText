<?php

use App\Console\Commands\SeedRehearsalEdges;

it('refuses a database name that is not a rehearsal copy', function (): void {
    // The lane is mysql, so the driver check now passes — podtext_test
    // still correctly refuses the name check before any table is touched,
    // which is the guard that actually matters here (a rehearsal-only
    // command must never run against a database it wasn't built for).
    $this->artisan('db:seed-rehearsal-edges')
        ->expectsOutputToContain('is not a rehearsal database')
        ->assertFailed();
});

it('carries the full edge matrix from the spec', function (): void {
    $edges = SeedRehearsalEdges::DateEdges;

    expect($edges)->toContain('2026-01-15 10:00:00')  // winter, +02 frame
        ->toContain('2026-07-15 10:00:00')            // summer, +03 frame
        ->toContain('2026-03-27 01:59:00')            // last instant before the spring gap
        ->toContain('2026-03-27 03:00:00')            // first instant after it
        ->toContain('2026-10-25 01:30:00')            // the October fold wall
        ->toContain('1970-01-01 02:00:01')            // TIMESTAMP minimum under +02
        ->toContain('2038-01-19 05:14:07');           // max epoch as +02 wall

    expect(SeedRehearsalEdges::CollationPayloads)
        ->toContain('שָׁלוֹם')                          // niqqud
        ->toContain('שלום')                            // its unpointed twin
        ->toContain('טעם ')                            // trailing space (B5 fodder)
        ->toContain('🎧')->toContain('🎤');            // the unicode_ci emoji collapse
});

it('marks a composite-integer-pivot unique index as not insert-safe', function (): void {
    // author_transcription's own shape: a surrogate PRIMARY that varies
    // (fresh id per insert) alongside a composite unique over two FK columns
    // that a blind clone would copy verbatim — the measured duplicate-entry abort.
    $uniqueIndexes = [
        'PRIMARY' => ['id'],
        'author_transcription_author_id_transcription_id_unique' => ['author_id', 'transcription_id'],
    ];
    $varying = ['id', 'created_at', 'updated_at'];

    expect(SeedRehearsalEdges::insertSafe($uniqueIndexes, $varying))->toBeFalse();
});

it('treats a unique index containing a timestamp column as insert-safe', function (): void {
    $uniqueIndexes = ['PRIMARY' => ['id'], 'edge_marker_unique' => ['edge_marker_at']];
    $varying = ['id', 'edge_marker_at'];

    expect(SeedRehearsalEdges::insertSafe($uniqueIndexes, $varying))->toBeTrue();
});

it('treats a unique index containing a varied collated column as insert-safe', function (): void {
    $uniqueIndexes = ['content_items_content_group_id_slug_unique' => ['content_group_id', 'slug']];
    $varying = ['slug'];

    expect(SeedRehearsalEdges::insertSafe($uniqueIndexes, $varying))->toBeTrue();
});

it('treats a table with no unique indexes at all as insert-safe', function (): void {
    expect(SeedRehearsalEdges::insertSafe([], []))->toBeTrue();
});
