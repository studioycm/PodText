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

it('passes a full sanctioned run: timestamp to datetime, unicode_ci to 0900_ai_ci, sanctioned default/extra drop, identical hashes', function (): void {
    $before = [
        'hashes' => [
            'jobs' => ['rows' => 2, 'sha1' => 'deadbeefdeadbeefdeadbeefdeadbeefdeadbeef'],
            'failed_jobs' => ['rows' => 1, 'sha1' => 'cafebabecafebabecafebabecafebabecafebabe'],
        ],
        'properties' => [
            'jobs.created_at' => ['type' => 'timestamp', 'nullable' => 'YES', 'default' => null, 'collation' => null, 'extra' => ''],
            'jobs.title' => ['type' => 'varchar(255)', 'nullable' => 'NO', 'default' => null, 'collation' => 'utf8mb4_unicode_ci', 'extra' => ''],
            'failed_jobs.failed_at' => ['type' => 'timestamp', 'nullable' => 'NO', 'default' => 'CURRENT_TIMESTAMP', 'collation' => null, 'extra' => 'DEFAULT_GENERATED'],
        ],
    ];

    $after = [
        'hashes' => [
            'jobs' => ['rows' => 2, 'sha1' => 'deadbeefdeadbeefdeadbeefdeadbeefdeadbeef'],
            'failed_jobs' => ['rows' => 1, 'sha1' => 'cafebabecafebabecafebabecafebabecafebabe'],
        ],
        'properties' => [
            'jobs.created_at' => ['type' => 'datetime', 'nullable' => 'YES', 'default' => null, 'collation' => null, 'extra' => ''],
            'jobs.title' => ['type' => 'varchar(255)', 'nullable' => 'NO', 'default' => null, 'collation' => 'utf8mb4_0900_ai_ci', 'extra' => ''],
            'failed_jobs.failed_at' => ['type' => 'datetime', 'nullable' => 'NO', 'default' => null, 'collation' => null, 'extra' => ''],
        ],
    ];

    expect(AlignmentOracle::compareStates($before, $after))->toBe([]);
});

it('fails a no-op run without any special-casing: unconverted timestamp and unicode_ci report as ordinary rule violations', function (): void {
    $state = [
        'hashes' => [
            'jobs' => ['rows' => 2, 'sha1' => 'deadbeefdeadbeefdeadbeefdeadbeefdeadbeef'],
        ],
        'properties' => [
            'jobs.created_at' => ['type' => 'timestamp', 'nullable' => 'YES', 'default' => null, 'collation' => null, 'extra' => ''],
            'jobs.title' => ['type' => 'varchar(255)', 'nullable' => 'NO', 'default' => null, 'collation' => 'utf8mb4_unicode_ci', 'extra' => ''],
            'failed_jobs.failed_at' => ['type' => 'timestamp', 'nullable' => 'NO', 'default' => 'CURRENT_TIMESTAMP', 'collation' => null, 'extra' => 'DEFAULT_GENERATED'],
        ],
    ];

    // Same array on both sides: nothing moved.
    $failures = AlignmentOracle::compareStates($state, $state);

    expect($failures)->toHaveCount(5)
        ->and(implode("\n", $failures))
        ->toContain('jobs.created_at: timestamp column not converted')
        ->toContain('jobs.title: column not converted')
        ->toContain('failed_jobs.failed_at: timestamp column not converted')
        ->toContain('failed_jobs.failed_at: sanctioned default was not dropped')
        ->toContain('failed_jobs.failed_at: sanctioned extra was not dropped');
});

it('fails on value drift: sha1 differs while rows stay the same, naming the table', function (): void {
    $before = ['hashes' => ['jobs' => ['rows' => 2, 'sha1' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa']], 'properties' => []];
    $after = ['hashes' => ['jobs' => ['rows' => 2, 'sha1' => 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb']], 'properties' => []];

    $failures = AlignmentOracle::compareStates($before, $after);

    expect($failures)->toHaveCount(1)
        ->and($failures[0])->toContain('jobs')->toContain('drift');
});

it('fails a row-count change even when the sha1 matches', function (): void {
    $before = ['hashes' => ['jobs' => ['rows' => 2, 'sha1' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa']], 'properties' => []];
    $after = ['hashes' => ['jobs' => ['rows' => 3, 'sha1' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa']], 'properties' => []];

    $failures = AlignmentOracle::compareStates($before, $after);

    expect($failures)->toHaveCount(1)
        ->and($failures[0])->toContain('jobs');
});

it('fails when a non-sanctioned column drops its default', function (): void {
    $before = ['hashes' => [], 'properties' => [
        'widgets.published_at' => ['type' => 'datetime', 'nullable' => 'YES', 'default' => '2020-01-01 00:00:00', 'collation' => null, 'extra' => ''],
    ]];
    $after = ['hashes' => [], 'properties' => [
        'widgets.published_at' => ['type' => 'datetime', 'nullable' => 'YES', 'default' => null, 'collation' => null, 'extra' => ''],
    ]];

    $failures = AlignmentOracle::compareStates($before, $after);

    expect($failures)->toHaveCount(1)
        ->and($failures[0])->toContain('widgets.published_at')->toContain('default');
});

it('fails when a non-sanctioned column loses its ON UPDATE extra clause', function (): void {
    $before = ['hashes' => [], 'properties' => [
        'items.updated_at' => ['type' => 'timestamp', 'nullable' => 'NO', 'default' => 'CURRENT_TIMESTAMP', 'collation' => null, 'extra' => 'DEFAULT_GENERATED on update CURRENT_TIMESTAMP'],
    ]];
    $after = ['hashes' => [], 'properties' => [
        'items.updated_at' => ['type' => 'datetime', 'nullable' => 'NO', 'default' => 'CURRENT_TIMESTAMP', 'collation' => null, 'extra' => ''],
    ]];

    $failures = AlignmentOracle::compareStates($before, $after);

    expect($failures)->toHaveCount(1)
        ->and($failures[0])->toContain('items.updated_at')->toContain('extra');
});

it('fails a timestamp(6) old column even when it becomes datetime — precision was never sanctioned', function (): void {
    $before = ['hashes' => [], 'properties' => [
        'events.happened_at' => ['type' => 'timestamp(6)', 'nullable' => 'YES', 'default' => null, 'collation' => null, 'extra' => ''],
    ]];
    $after = ['hashes' => [], 'properties' => [
        'events.happened_at' => ['type' => 'datetime', 'nullable' => 'YES', 'default' => null, 'collation' => null, 'extra' => ''],
    ]];

    $failures = AlignmentOracle::compareStates($before, $after);

    expect($failures)->toHaveCount(1)
        ->and($failures[0])->toContain('events.happened_at')->toContain('timestamp(6)');
});

it('fails when a column vanishes', function (): void {
    $before = ['hashes' => [], 'properties' => [
        'jobs.legacy_at' => ['type' => 'timestamp', 'nullable' => 'YES', 'default' => null, 'collation' => null, 'extra' => ''],
    ]];
    $after = ['hashes' => [], 'properties' => []];

    $failures = AlignmentOracle::compareStates($before, $after);

    expect($failures)->toHaveCount(1)
        ->and($failures[0])->toContain('jobs.legacy_at');
});

it('fails when a new hashed table appears', function (): void {
    $before = ['hashes' => ['jobs' => ['rows' => 1, 'sha1' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa']], 'properties' => []];
    $after = ['hashes' => [
        'jobs' => ['rows' => 1, 'sha1' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'],
        'sessions' => ['rows' => 0, 'sha1' => 'da39a3ee5e6b4b0d3255bfef95601890afd80709'],
    ], 'properties' => []];

    $failures = AlignmentOracle::compareStates($before, $after);

    expect($failures)->toHaveCount(1)
        ->and($failures[0])->toContain('sessions');
});

it('fails when a column collation is still utf8mb4_unicode_ci after the run', function (): void {
    $before = ['hashes' => [], 'properties' => [
        'jobs.queue' => ['type' => 'varchar(255)', 'nullable' => 'NO', 'default' => null, 'collation' => 'utf8mb4_unicode_ci', 'extra' => ''],
    ]];
    $after = ['hashes' => [], 'properties' => [
        'jobs.queue' => ['type' => 'varchar(255)', 'nullable' => 'NO', 'default' => null, 'collation' => 'utf8mb4_unicode_ci', 'extra' => ''],
    ]];

    $failures = AlignmentOracle::compareStates($before, $after);

    expect($failures)->toHaveCount(1)
        ->and($failures[0])->toContain('jobs.queue')->toContain('not converted');
});
