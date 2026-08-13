<?php

declare(strict_types=1);

/*
 * Workstream ID registry guard.
 *
 * The registry lives in .ai/guidelines/ because Boost composes that directory
 * verbatim into CLAUDE.md and AGENTS.md, making the convention always-on
 * context rather than a path-matched rule. That placement is deliberate: IDs go
 * wrong when they travel into commit subjects and briefs under ~/.cache/, and a
 * docs/** rule never loads for either.
 *
 * These assertions keep the registry INTERNALLY honest. They detect no
 * collision at the moment one is issued — in this program detection has only
 * ever happened at the human. See §1.1 of
 * docs/superpowers/specs/2026-08-13-workstream-id-namespacing-design.md.
 */

/**
 * Parse the token table out of the registry source.
 *
 * Scoped to the "## Tokens" section so a second table added later cannot
 * silently become registry rows.
 *
 * @return list<array{token: string, globs: list<string>}>
 */
function workstreamRegistryRows(): array
{
    $path = base_path('.ai/guidelines/workstream-ids.md');

    if (! is_file($path)) {
        return [];
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES);

    if ($lines === false) {
        return [];
    }

    $rows = [];
    $inTokens = false;

    foreach ($lines as $line) {
        $trimmed = trim($line);

        if (str_starts_with($trimmed, '## ')) {
            $inTokens = $trimmed === '## Tokens';

            continue;
        }

        if (! $inTokens || ! str_starts_with($trimmed, '|')) {
            continue;
        }

        $cells = array_map(
            static fn (string $cell): string => trim(str_replace('`', '', $cell)),
            array_slice(explode('|', $trimmed), 1, -1),
        );

        if (count($cells) !== 2 || $cells[0] === 'token' || str_starts_with($cells[0], '---')) {
            continue;
        }

        $rows[] = [
            'token' => $cells[0],
            'globs' => array_map(static fn (string $glob): string => trim($glob), explode(',', $cells[1])),
        ];
    }

    return $rows;
}

it('parses at least the eight seeded workstream tokens', function (): void {
    // The canary. Without a floor, a parser that returns nothing makes every
    // other assertion in this file pass vacuously over an empty set.
    expect(count(workstreamRegistryRows()))->toBeGreaterThanOrEqual(8);
});

it('issues every workstream token exactly once', function (): void {
    $tokens = array_column(workstreamRegistryRows(), 'token');

    expect(array_unique($tokens))->toHaveCount(count($tokens));
});

it('points every workstream token at a path that exists', function (): void {
    $unmatched = [];

    foreach (workstreamRegistryRows() as $row) {
        foreach ($row['globs'] as $glob) {
            // rtrim the trailing slash so a directory row globs to the
            // directory itself. glob() returns false on error, [] on no match;
            // both mean the row points at nothing.
            $matches = glob(base_path(rtrim($glob, '/')));

            if ($matches === false || $matches === []) {
                $unmatched[] = "{$row['token']} => {$glob}";
            }
        }
    }

    expect($unmatched)->toBe([]);
});

it('has been composed into the generated agent files', function (): void {
    // CLAUDE.md and AGENTS.md are generated: Boost composes .ai/guidelines/*.md
    // into both verbatim. A token added to the source but never synced is
    // invisible to every agent, so the registry would fail silently. Fix by
    // running `composer boost:sync` — never by hand-editing either file, which
    // the next regeneration would discard.
    $rows = workstreamRegistryRows();

    expect($rows)->not->toBeEmpty();

    foreach (['CLAUDE.md', 'AGENTS.md'] as $generated) {
        $contents = (string) file_get_contents(base_path($generated));

        $missing = [];

        foreach ($rows as $row) {
            if (! str_contains($contents, "| `{$row['token']}` |")) {
                $missing[] = "{$generated}: {$row['token']}";
            }
        }

        expect($missing)->toBe([]);
    }
});
