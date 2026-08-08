<?php

use App\Console\Commands\SeedRehearsalEdges;

it('refuses the sqlite suite driver', function (): void {
    $this->artisan('db:seed-rehearsal-edges')
        ->expectsOutputToContain('only supports MySQL')
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
