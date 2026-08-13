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

it('links every project-owned skill, and backs every link with a project source', function (): void {
    // A project-owned skill under .ai/skills/ installs as a SYMLINK, and is the
    // only kind boost:sync will not rewrite. That is what makes the
    // pest-testing --parallel fix permanent instead of periodic maintenance.
    // If a copy ever shadows one of these links, precedence has changed and the
    // vendor text is silently back — this is the assertion that says so.
    //
    // It also separates two failures that otherwise look identical: a checkout
    // with core.symlinks=false materialises links as plain files, which would
    // otherwise surface as "declared but not installed" and read as a puzzle.
    //
    // No literal floor here, deliberately. The two directions close each
    // other's vacuous case: if the .ai/skills glob returned nothing, direction
    // one would assert over an empty set, and direction two would then report
    // every existing symlink as sourceless.
    $projectSkills = array_map(
        static fn (string $path): string => basename($path),
        glob(base_path('.ai/skills/*'), GLOB_ONLYDIR) ?: [],
    );

    $problems = [];

    foreach ($projectSkills as $skill) {
        foreach (agentSkillDirectories() as $directory) {
            if (! is_link(base_path($directory.'/skills/'.$skill))) {
                $problems[] = "{$directory}/skills/{$skill} is not a symlink — a copy has shadowed the project source";
            }
        }
    }

    foreach (agentSkillDirectories() as $directory) {
        foreach (glob(base_path($directory.'/skills/*')) ?: [] as $path) {
            if (is_link($path) && ! in_array(basename($path), $projectSkills, true)) {
                $problems[] = "{$directory}/skills/".basename($path).' is a symlink with no .ai/skills source';
            }
        }
    }

    expect($problems)->toBe([]);
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

/*
 * ---------------------------------------------------------------------------
 * Fork drift.
 *
 * Owning a skill buys a permanent fix and costs vendor updates. Nothing
 * rewrites .ai/skills/pest-testing/SKILL.md — which is exactly why the
 * --parallel correction survives a sync, and exactly why Pest 6's improvements
 * will never arrive there on their own.
 *
 * That failure is not hypothetical in this repo: the installed skill sat at
 * Pest 4 content while the code ran Pest 5.1, and nothing said so until a sync
 * happened to run. A fork reproduces that condition by design. These two tests
 * are what turn "the fork silently rots" into "the fork tells you when upstream
 * moved".
 * ---------------------------------------------------------------------------
 */

/**
 * Project-owned skills that are FORKS of a vendor-shipped skill, with the
 * vendor revision each was last reconciled against.
 *
 * @return array<string, array{source: string, reviewed: string, at: string}>
 */
function forkedVendorSkills(): array
{
    return [
        'pest-testing' => [
            'source' => 'vendor/pestphp/pest/resources/boost/skills/pest-testing/SKILL.blade.php',
            'reviewed' => '91e9392cb22c590e854d516555d4c676fde21320ccc7475e6ec8c1e9ca6d302f',
            'at' => 'pestphp/pest v5.1.0, reconciled 2026-08-14',
        ],
    ];
}

/**
 * The vendor Blade source a package ships for this skill name, if any.
 *
 * Boost renders these into the installed SKILL.md files; they are the upstream
 * text a fork is reconciled against.
 */
function vendorSkillSource(string $skill): ?string
{
    $matches = glob(base_path("vendor/*/*/resources/boost/skills/{$skill}/SKILL.blade.php")) ?: [];

    return $matches[0] ?? null;
}

it('lists every forked vendor skill in the reconciliation table', function (): void {
    // Derived, not a literal: a project skill IS a fork exactly when some
    // package also ships a vendor source under that name. Six of the seven
    // project skills are originals with no upstream; only pest-testing has one.
    // Fork a second skill and forget the table, and this test names it.
    $unlisted = [];

    foreach (glob(base_path('.ai/skills/*'), GLOB_ONLYDIR) ?: [] as $path) {
        $skill = basename($path);

        if (vendorSkillSource($skill) !== null && ! array_key_exists($skill, forkedVendorSkills())) {
            $unlisted[] = $skill;
        }
    }

    expect($unlisted)->toBe([]);
});

it('notices when a forked skill vendor source moves on', function (): void {
    // The pinned hash below IS a hardcoded literal, and here that is the
    // correct shape rather than the mistake a hardcoded canary floor was: it
    // records "a human read the vendor text at this revision". There is nothing
    // to derive that from — any derivation would read the very file it watches
    // and agree with itself.
    //
    // WHEN THIS GOES RED: diff the vendor source against the fork, fold
    // anything worthwhile into .ai/skills/<skill>/SKILL.md, confirm --parallel
    // has not returned, then update `reviewed` and `at`. Never update the hash
    // alone — that converts the guard into a rubber stamp.
    $problems = [];

    foreach (forkedVendorSkills() as $skill => $fork) {
        $path = base_path($fork['source']);

        if (! is_file($path)) {
            $problems[] = "{$skill}: vendor source is gone ({$fork['source']}) — the package moved or renamed it";

            continue;
        }

        $current = (string) hash_file('sha256', $path);

        if ($current !== $fork['reviewed']) {
            $problems[] = "{$skill}: vendor source changed since {$fork['at']} — reviewed "
                .substr($fork['reviewed'], 0, 12).', now '.substr($current, 0, 12);
        }
    }

    expect($problems)->toBe([]);
});
