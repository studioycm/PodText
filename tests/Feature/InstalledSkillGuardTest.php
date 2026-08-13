<?php

declare(strict_types=1);

/*
 * Installed agent-skill guard.
 *
 * WHY `--parallel` IS BANNED HERE, so nobody deletes this guard as pedantry:
 *
 * tests/Pest.php:101 takes a machine-global flock (LOCK_EX | LOCK_NB) in
 * bootstrap — fail fast, never queue. paratest's workers run that SAME
 * bootstrap, so the lock the parent already holds refuses every one of them.
 * The observed result is "REFUSED … executed ZERO tests" with a non-zero exit.
 *
 * `--parallel` is therefore not slow here, and not merely discouraged: it runs
 * NOTHING. That is a lock property, not a cost property — do not conflate it
 * with the Xdebug instrumentation tax measured separately on this suite. The
 * two findings are unrelated.
 *
 * `--parallel --tia` is additionally unsafe: Tia keys worker partials by
 * TEST_TOKEN else getmypid() and collects them by globbing `worker-edges-*`
 * (Tia.php:83, :1340), so they are not session-scoped and concurrent runs can
 * read each other's partials.
 *
 * WHY A TEST AND NOT AN EDIT. The skill files under .agents/, .claude/ and
 * .junie/ are INSTALLED ARTEFACTS — `composer boost:sync` rewrites them from
 * the vendor package. An edit removing bad advice is silently undone by the
 * next sync; this guard turns that silent regression into a red test. Expect
 * it to go red after a sync that reintroduces the advice. That is the guard
 * working, not a flake: re-apply the fix, do not weaken the assertion.
 *
 * This guard exists because a diff gate under-covered its own command —
 * `boost:sync` writes skills as well as CLAUDE.md/AGENTS.md, and a diff scoped
 * to the two named files never saw them. Nobody was careless; the gate was
 * narrower than the command.
 */

/**
 * Every installed SKILL.md across the three agent directories.
 *
 * @return list<string> absolute paths
 */
function installedSkillFiles(): array
{
    $found = [];

    foreach (['.agents', '.claude', '.junie'] as $agentDirectory) {
        $matches = glob(base_path($agentDirectory.'/skills/*/SKILL.md'));

        if ($matches === false) {
            continue;
        }

        $found = [...$found, ...$matches];
    }

    sort($found);

    return $found;
}

it('discovers the installed skill files', function (): void {
    // The canary. Without a floor, a glob that matches nothing makes the
    // --parallel assertion below pass while proving nothing at all. 24 is the
    // measured count: 8 skills across 3 agent directories.
    expect(count(installedSkillFiles()))->toBeGreaterThanOrEqual(24);
});

it('ships no installed skill that advises running pest with --parallel', function (): void {
    // Command-shaped: `pest` and `--parallel` on the SAME line. Prose that
    // documents the ban keeps them on separate lines, so explaining the rule
    // never trips it.
    $advising = [];

    foreach (installedSkillFiles() as $path) {
        foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $number => $line) {
            if (str_contains($line, '--parallel') && stripos($line, 'pest') !== false) {
                $advising[] = str_replace(base_path().'/', '', $path).':'.($number + 1);
            }
        }
    }

    expect($advising)->toBe([]);
});

it('keeps the three installed copies of each skill byte-identical', function (): void {
    // They are generated from one source. Divergence means something wrote to
    // one agent directory directly instead of going through boost:sync.
    $diverged = [];

    foreach (glob(base_path('.claude/skills/*/SKILL.md')) ?: [] as $claudeCopy) {
        $skill = basename(dirname($claudeCopy));

        $hashes = [];

        foreach (['.agents', '.claude', '.junie'] as $agentDirectory) {
            $sibling = base_path($agentDirectory.'/skills/'.$skill.'/SKILL.md');

            $hashes[$agentDirectory] = is_file($sibling) ? hash_file('sha256', $sibling) : 'missing';
        }

        if (count(array_unique($hashes)) !== 1) {
            $diverged[] = $skill;
        }
    }

    expect($diverged)->toBe([]);
});
