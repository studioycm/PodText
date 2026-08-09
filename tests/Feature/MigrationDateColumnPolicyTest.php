<?php

/**
 * Future migrations must not reintroduce TIMESTAMP or DB-generated time
 * (alignment spec §10.2/§11). Old files are history; the alignment migration
 * neutralizes them at replay. Everything after it must be clean.
 */
const AlignmentMigration = '2026_08_09_000000';

const BannedCalls = [
    '->timestamp(' => 'use ->dateTime() — TIMESTAMP converts through the session clock',
    '->timestamps(' => 'use ->datetimes() (Laravel 13 DATETIME pair) or two ->dateTime() columns',
    '->softDeletes(' => 'use ->softDeletesDatetime()',
    '->softDeletesTz(' => 'use ->softDeletesDatetime()',
    '->timestampTz(' => 'use ->dateTime()',
    '->timestampsTz(' => 'use ->datetimes()',
    '->useCurrent(' => 'DB-generated time disagrees with the app clock by design — write the value from PHP',
    '->useCurrentOnUpdate(' => 'same class — write the value from PHP',
    'CURRENT_TIMESTAMP' => 'same class — write the value from PHP',
];

it('keeps post-alignment migrations free of TIMESTAMP and DB-generated time', function (): void {
    $violations = [];

    foreach (glob(database_path('migrations/*.php')) ?: [] as $file) {
        // Lexicographic compare on the 17-char date stamp: the alignment
        // migration itself and everything before it are history — the
        // alignment neutralizes them at replay. Everything after must be clean.
        if (substr(basename($file), 0, 17) <= AlignmentMigration) {
            continue;
        }

        $source = (string) file_get_contents($file);

        foreach (BannedCalls as $needle => $fix) {
            if (str_contains($source, $needle)) {
                $violations[] = basename($file).": {$needle} — {$fix}";
            }
        }
    }

    expect($violations)->toBeEmpty(implode("\n", $violations));
});

it('is not vacuous: the scan sees the migration directory', function (): void {
    expect(glob(database_path('migrations/*.php')))->not->toBeEmpty();
});
