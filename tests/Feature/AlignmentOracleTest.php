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

it('excludes only the migrations ledger from the hash pass', function (): void {
    // The migrate run under test inserts its own ledger row — that mutation
    // IS the act being certified, so `migrations` can never be value-stable
    // across capture -> migrate -> compare and must never be hashed. Every
    // other table stays eligible.
    expect(AlignmentOracle::hashableTable('migrations'))->toBeFalse()
        ->and(AlignmentOracle::hashableTable('jobs'))->toBeTrue()
        ->and(AlignmentOracle::hashableTable('failed_jobs'))->toBeTrue();
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

it('fails when a hashed table vanishes', function (): void {
    $before = ['hashes' => ['jobs' => ['rows' => 1, 'sha1' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa']], 'properties' => []];
    $after = ['hashes' => [], 'properties' => []];

    $failures = AlignmentOracle::compareStates($before, $after);

    expect($failures)->toHaveCount(1)
        ->and($failures[0])->toContain('jobs')->toContain('vanish');
});

it('fails when a new column appears', function (): void {
    $before = ['hashes' => [], 'properties' => [
        'jobs.title' => ['type' => 'varchar(255)', 'nullable' => 'NO', 'default' => null, 'collation' => 'utf8mb4_0900_ai_ci', 'extra' => ''],
    ]];
    $after = ['hashes' => [], 'properties' => [
        'jobs.title' => ['type' => 'varchar(255)', 'nullable' => 'NO', 'default' => null, 'collation' => 'utf8mb4_0900_ai_ci', 'extra' => ''],
        'jobs.priority' => ['type' => 'int', 'nullable' => 'YES', 'default' => null, 'collation' => null, 'extra' => ''],
    ]];

    $failures = AlignmentOracle::compareStates($before, $after);

    expect($failures)->toHaveCount(1)
        ->and($failures[0])->toContain('jobs.priority');
});

it('fails when a column nullable flag changes', function (): void {
    $before = ['hashes' => [], 'properties' => [
        'jobs.queue' => ['type' => 'varchar(255)', 'nullable' => 'NO', 'default' => null, 'collation' => 'utf8mb4_0900_ai_ci', 'extra' => ''],
    ]];
    $after = ['hashes' => [], 'properties' => [
        'jobs.queue' => ['type' => 'varchar(255)', 'nullable' => 'YES', 'default' => null, 'collation' => 'utf8mb4_0900_ai_ci', 'extra' => ''],
    ]];

    $failures = AlignmentOracle::compareStates($before, $after);

    expect($failures)->toHaveCount(1)
        ->and($failures[0])->toContain('jobs.queue')->toContain('nullable');
});

it('fails an unsanctioned collation change: unicode_ci to general_ci is not the sanctioned flip', function (): void {
    $before = ['hashes' => [], 'properties' => [
        'jobs.queue' => ['type' => 'varchar(255)', 'nullable' => 'NO', 'default' => null, 'collation' => 'utf8mb4_unicode_ci', 'extra' => ''],
    ]];
    $after = ['hashes' => [], 'properties' => [
        'jobs.queue' => ['type' => 'varchar(255)', 'nullable' => 'NO', 'default' => null, 'collation' => 'utf8mb4_general_ci', 'extra' => ''],
    ]];

    $failures = AlignmentOracle::compareStates($before, $after);

    expect($failures)->toHaveCount(1)
        ->and($failures[0])->toContain('jobs.queue')->toContain('collation');
});

it('captureRefusal refuses a state with empty hashes or properties', function (): void {
    $state = ['meta' => ['tables' => 0, 'columns' => 0], 'hashes' => [], 'properties' => [], 'row_failures' => []];

    expect(AlignmentOracle::captureRefusal($state, false, false))->toContain('empty');
});

it('captureRefusal refuses a state with unhashable rows', function (): void {
    $state = [
        'meta' => ['tables' => 1, 'columns' => 1],
        'hashes' => ['jobs' => ['rows' => 1, 'sha1' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa']],
        'properties' => [
            'jobs.created_at' => ['type' => 'timestamp', 'nullable' => 'YES', 'default' => null, 'collation' => null, 'extra' => ''],
        ],
        'row_failures' => ['unhashable row in `jobs` (CONCAT_WS returned NULL — value exceeds max_allowed_packet?)'],
    ];

    expect(AlignmentOracle::captureRefusal($state, false, false))->toContain('unhashable');
});

it('captureRefusal refuses a state with nothing left to convert (already post-migration)', function (): void {
    $state = [
        'meta' => ['tables' => 1, 'columns' => 2],
        'hashes' => ['jobs' => ['rows' => 1, 'sha1' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa']],
        'properties' => [
            'jobs.created_at' => ['type' => 'datetime', 'nullable' => 'YES', 'default' => null, 'collation' => null, 'extra' => ''],
            'jobs.title' => ['type' => 'varchar(255)', 'nullable' => 'NO', 'default' => null, 'collation' => 'utf8mb4_0900_ai_ci', 'extra' => ''],
        ],
        'row_failures' => [],
    ];

    expect(AlignmentOracle::captureRefusal($state, false, false))->toContain('nothing left to convert');
});

it('captureRefusal refuses to overwrite an existing baseline without --force', function (): void {
    $state = [
        'meta' => ['tables' => 1, 'columns' => 1],
        'hashes' => ['jobs' => ['rows' => 1, 'sha1' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa']],
        'properties' => [
            'jobs.created_at' => ['type' => 'timestamp', 'nullable' => 'YES', 'default' => null, 'collation' => null, 'extra' => ''],
        ],
        'row_failures' => [],
    ];

    expect(AlignmentOracle::captureRefusal($state, true, false))->toContain('force');
});

it('captureRefusal allows a well-formed pre-migration state with no existing baseline', function (): void {
    $state = [
        'meta' => ['tables' => 1, 'columns' => 1],
        'hashes' => ['jobs' => ['rows' => 1, 'sha1' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa']],
        'properties' => [
            'jobs.created_at' => ['type' => 'timestamp', 'nullable' => 'YES', 'default' => null, 'collation' => null, 'extra' => ''],
        ],
        'row_failures' => [],
    ];

    expect(AlignmentOracle::captureRefusal($state, false, false))->toBeNull();
});

it('captureRefusal allows overwriting an existing baseline when --force is passed', function (): void {
    $state = [
        'meta' => ['tables' => 1, 'columns' => 1],
        'hashes' => ['jobs' => ['rows' => 1, 'sha1' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa']],
        'properties' => [
            'jobs.title' => ['type' => 'varchar(255)', 'nullable' => 'NO', 'default' => null, 'collation' => 'utf8mb4_unicode_ci', 'extra' => ''],
        ],
        'row_failures' => [],
    ];

    expect(AlignmentOracle::captureRefusal($state, true, true))->toBeNull();
});

it('provenanceFailure refuses missing or malformed meta', function (): void {
    expect(AlignmentOracle::provenanceFailure([], 'podtext', ['session' => 'SYSTEM', 'system' => 'IDT']))
        ->toContain('missing or malformed');
});

it('provenanceFailure refuses a database mismatch', function (): void {
    $meta = ['database' => 'podtext_rehearsal', 'session_time_zone' => 'SYSTEM', 'system_time_zone' => 'IDT'];

    expect(AlignmentOracle::provenanceFailure($meta, 'podtext', ['session' => 'SYSTEM', 'system' => 'IDT']))
        ->toContain('database');
});

it('provenanceFailure refuses a session_time_zone drift', function (): void {
    $meta = ['database' => 'podtext', 'session_time_zone' => 'SYSTEM', 'system_time_zone' => 'IDT'];

    expect(AlignmentOracle::provenanceFailure($meta, 'podtext', ['session' => '+00:00', 'system' => 'IDT']))
        ->toContain('session_time_zone');
});

it('provenanceFailure refuses a system_time_zone drift', function (): void {
    $meta = ['database' => 'podtext', 'session_time_zone' => 'SYSTEM', 'system_time_zone' => 'IDT'];

    expect(AlignmentOracle::provenanceFailure($meta, 'podtext', ['session' => 'SYSTEM', 'system' => 'UTC']))
        ->toContain('system_time_zone');
});

it('provenanceFailure allows a matching database and unchanged timezones', function (): void {
    $meta = ['database' => 'podtext', 'session_time_zone' => 'SYSTEM', 'system_time_zone' => 'IDT'];

    expect(AlignmentOracle::provenanceFailure($meta, 'podtext', ['session' => 'SYSTEM', 'system' => 'IDT']))
        ->toBeNull();
});
