<?php

use App\Console\Commands\AlignmentOracle;

it('refuses the sqlite suite driver', function (): void {
    $this->artisan('db:alignment-oracle', ['mode' => 'capture'])
        ->expectsOutputToContain('only supports MySQL')
        ->assertFailed();
});

it('encodes NULL distinctly from empty string in the row expression', function (): void {
    $sql = AlignmentOracle::rowExpression(['created_at', 'title']);

    // CONCAT_WS silently SKIPS NULLs, which would make (NULL,'x') hash like
    // ('x') — every column must be COALESCE-wrapped with a sentinel.
    expect($sql)
        ->toContain("COALESCE(CAST(`created_at` AS CHAR), '␀')")
        ->toContain("COALESCE(CAST(`title` AS CHAR), '␀')")
        ->toContain("CONCAT_WS('|',");
});
