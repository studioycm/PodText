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
 * T23b SURFACE. This guard reads every installed skill file at runtime, so
 * editing one while a suite is in flight can flip it. Note the ordering hazard
 * that follows: `composer boost:sync` writes exactly those files and then runs
 * a FILTERED test that does not include this guard, so a sync cannot detect
 * its own damage. It surfaces on the next full run instead.
 *
 * This guard exists because a diff gate under-covered its own command —
 * `boost:sync` writes skills as well as CLAUDE.md/AGENTS.md, and a diff scoped
 * to the two named files never saw them. Nobody was careless; the gate was
 * narrower than the command.
 */

/**
 * boost.json — the DECLARATION of what should be installed, and the only
 * source in this file that is independent of the filesystem being measured.
 *
 * @return array<string, mixed>
 */
function boostConfig(): array
{
    $decoded = json_decode((string) file_get_contents(base_path('boost.json')), true);

    return is_array($decoded) ? $decoded : [];
}

/**
 * boost.json names agents, not paths. This is the only mapping between them,
 * and `it('maps every agent …')` below refuses to let it fall behind.
 *
 * @return array<string, string>
 */
function agentDirectoryMap(): array
{
    return [
        'junie' => '.junie',
        'claude_code' => '.claude',
        'codex' => '.agents',
    ];
}

/**
 * The install directories for the agents boost.json actually selects.
 *
 * @return list<string>
 */
function agentSkillDirectories(): array
{
    $map = agentDirectoryMap();

    $directories = [];

    foreach (boostConfig()['agents'] ?? [] as $agent) {
        if (isset($map[$agent])) {
            $directories[] = $map[$agent];
        }
    }

    return $directories;
}

/**
 * Agents boost.json selects that the map above does not know about.
 *
 * @return list<string>
 */
function unmappedBoostAgents(): array
{
    $map = agentDirectoryMap();

    return array_values(array_filter(
        boostConfig()['agents'] ?? [],
        static fn (string $agent): bool => ! isset($map[$agent]),
    ));
}

/**
 * Every installed SKILL.md across the selected agent directories.
 *
 * glob() resolves symlinks, which matters: six of the skills are symlinked
 * DIRECTORIES into .ai/skills/. `find -name SKILL.md` does not descend those
 * without -L and reports 8 per directory instead of 14 — the discrepancy that
 * produced a wrong hardcoded floor before this file derived its expectation.
 *
 * @return list<string> absolute paths
 */
function installedSkillFiles(): array
{
    $found = [];

    foreach (agentSkillDirectories() as $directory) {
        $found = [...$found, ...(glob(base_path($directory.'/skills/*/SKILL.md')) ?: [])];
    }

    sort($found);

    return $found;
}

it('maps every agent boost.json declares to an install directory', function (): void {
    // Without this, a fourth agent added to boost.json would be silently
    // skipped by agentSkillDirectories(), shrinking the expectation in every
    // test below so they pass while covering less. Same vacuous-pass shape as
    // a missing canary, one level up — the expectation itself going quiet.
    expect(unmappedBoostAgents())->toBe([]);
});

it('installs exactly the skills boost.json declares', function (): void {
    // This is the canary, and its expectation is DERIVED from boost.json — a
    // declaration independent of the filesystem it is checked against.
    // Deriving it from the same glob would make the assertion
    // count($x) === count($x): a tautology that can never fail.
    //
    // It replaces a hardcoded floor. A literal was defensible for independence
    // but cannot follow boost.json, and the first literal shipped as 24
    // because it was calibrated with `find`, which sees 8 of the 14 skills per
    // directory. A derived expectation cannot drift that way, and it catches
    // strictly more: a declared skill that failed to install, and an installed
    // skill nobody declared.
    $declared = boostConfig()['skills'] ?? [];

    // Residual canary on the SOURCE itself: an empty boost.json would produce
    // an empty expectation, and an empty expectation against an empty scan
    // would pass while proving nothing.
    expect(count($declared))->toBeGreaterThanOrEqual(10);

    $mismatches = [];

    foreach (agentSkillDirectories() as $directory) {
        $installed = array_map(
            static fn (string $path): string => basename(dirname($path)),
            glob(base_path($directory.'/skills/*/SKILL.md')) ?: [],
        );

        foreach (array_diff($declared, $installed) as $missing) {
            $mismatches[] = "{$directory}: declared but not installed — {$missing}";
        }

        foreach (array_diff($installed, $declared) as $undeclared) {
            $mismatches[] = "{$directory}: installed but not declared — {$undeclared}";
        }
    }

    expect($mismatches)->toBe([]);
});

it('ships no installed skill that advises running pest with --parallel', function (): void {
    // Command-shaped: `pest` and `--parallel` on the SAME line. Prose that
    // documents the ban keeps them on separate lines, so explaining the rule
    // never trips it. Case-insensitive on `pest` so `Pest --parallel` is caught.
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

it('keeps every installed copy of each skill byte-identical', function (): void {
    // They are generated from one source. Divergence means something wrote to
    // one agent directory directly instead of going through boost:sync.
    $directories = agentSkillDirectories();

    $diverged = [];

    foreach (boostConfig()['skills'] ?? [] as $skill) {
        $hashes = [];

        foreach ($directories as $directory) {
            $path = base_path($directory.'/skills/'.$skill.'/SKILL.md');

            $hashes[$directory] = is_file($path) ? hash_file('sha256', $path) : 'missing';
        }

        if (count(array_unique($hashes)) !== 1) {
            $diverged[] = $skill;
        }
    }

    expect($diverged)->toBe([]);
});
