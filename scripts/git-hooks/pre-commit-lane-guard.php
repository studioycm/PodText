<?php

use App\Support\Testing\TestLaneContract;

/*
|--------------------------------------------------------------------------
| pre-commit — no commits while a pest run holds the test lane
|--------------------------------------------------------------------------
|
| Invoked by the `pre-commit` launcher beside this file. It lives in a .php so
| Pint formats it and static analysis can reach it; git's hook filename cannot
| carry an extension, which is the only reason there are two files.
|
| pest's Test Impact Analysis stamps its graph with HEAD read at the END of a
| run (Tia.php:623 into :638), so a commit that lands mid-run becomes that
| run's recorded baseline while the results still describe pre-commit code:
| the next run replays green for code it never executed
| (https://github.com/pestphp/pest/issues/1856). No lock upstream or here can
| reach it, because committing is not a pest run — the lane's own flock never
| sees a `git commit`.
|
| The window is not TIA-only. T23b ("no tracked-file edits or commits while a
| suite is in flight") exists because tracked files are test input in this
| repo — composer.json, phpstan.neon, .env.example and four documentation
| files are all read at runtime, and .env twice per test boot. That rule was
| breached twice in one evening by careful sessions, which is the argument for
| a mechanism rather than a discipline.
|
| The lane's flock is the probe, and it is the right one: it is machine-global
| (HOME-anchored, keyed by lane identity — TestLaneContract::runLockPath), and
| a pest run holds it for its whole process lifetime. This hook takes it
| non-blocking and releases it immediately; it is a probe, never a holder, so
| a run starting a microsecond later is not refused because of this check.
|
| Install (repository-wide, and deliberately so — the lane is machine-global,
| so every worktree of this repo should refuse alike):
|
|     git config core.hooksPath scripts/git-hooks
|
| That also means `.git/hooks/*` is bypassed entirely: a hook dropped in there
| will silently never run while this config stands.
|
| Escape hatch:  PODTEXT_ALLOW_COMMIT_DURING_RUN=1 git commit ...
|
| Know what the hatch costs before using it. During a `pest --tia` run it
| poisons the graph exactly as #1856 describes, and the poison is STICKY: the
| false green survives every subsequent --tia run and clears only when
| something actually executes the tests — i.e. a full run WITHOUT --tia.
| During a plain run it is T23b's own hazard: a tracked file read mid-suite.
|
| Fail-open is deliberate in every "cannot tell" branch (no php — handled by
| the launcher; contract file missing; lock file unopenable), but never
| silent: a guard that blocks every commit when its own plumbing breaks is
| worse than one that says so loudly and steps aside. Each branch is covered
| by tests/Feature/PreCommitLaneGuardTest.php.
*/

$root = dirname(__DIR__, 2);

if ((string) getenv('PODTEXT_ALLOW_COMMIT_DURING_RUN') === '1') {
    fwrite(STDERR, "pre-commit: test-lane check SKIPPED via PODTEXT_ALLOW_COMMIT_DURING_RUN=1.\n"
        ."If a `pest --tia` run is in flight, this commit becomes its recorded baseline and later runs replay green for code they never executed (pestphp/pest#1856).\n");

    exit(0);
}

/*
 * Required directly rather than through vendor/autoload.php: a hook must work
 * in a worktree that has never run `composer install`, and runLockPath() is
 * pre-boot safe by contract — getenv(), sha1() and string work only, no
 * config(), no app(), no framework.
 */
$contractPath = $root.'/app/Support/Testing/TestLaneContract.php';

if (! is_file($contractPath)) {
    fwrite(STDERR, "pre-commit: WARNING — {$contractPath} is missing, so the test-lane check could not run. Allowing the commit.\n");

    exit(0);
}

require_once $contractPath;

/*
 * The same pre-boot lane identity resolution tests/Pest.php performs, and it
 * must stay the same: getenv() first so an exported variable wins, then the
 * raw .env file, because .env has not been parsed at the point Pest.php locks
 * and a getenv()-only identity silently hashes to a different lock file than
 * every post-boot caller computes.
 */
$rawPreBootEnv = static function (string $key, string $default) use ($root): string {
    $fromEnv = getenv($key);

    if ($fromEnv !== false && $fromEnv !== '') {
        return $fromEnv;
    }

    $envFile = $root.'/.env';

    if (is_file($envFile) && preg_match('/^'.preg_quote($key, '/').'=(.*)$/m', (string) file_get_contents($envFile), $matches) === 1) {
        return trim($matches[1], "\"' \r");
    }

    return $default;
};

$lockPath = TestLaneContract::runLockPath(
    $rawPreBootEnv('DB_TESTING_HOST', '127.0.0.1'),
    $rawPreBootEnv('DB_TESTING_PORT', '3307'),
    $rawPreBootEnv('DB_TESTING_DATABASE', ''),
);

// Nothing has ever taken this lane on this machine, so nothing holds it now.
if (! is_file($lockPath)) {
    exit(0);
}

$handle = @fopen($lockPath, 'r');

if ($handle === false) {
    fwrite(STDERR, "pre-commit: WARNING — the test-lane run-lock at {$lockPath} could not be opened, so no check was performed. Allowing the commit.\n");

    exit(0);
}

if (flock($handle, LOCK_EX | LOCK_NB)) {
    flock($handle, LOCK_UN);
    fclose($handle);

    exit(0);
}

/*
 * Refused, which is the one branch describeRunLockHolder() may be called in:
 * its documented precondition is that flock has ALREADY refused you, because
 * that is what licenses it to read a `released` record as stale rather than
 * as free.
 */
$raw = @file_get_contents($lockPath);

$holder = TestLaneContract::describeRunLockHolder(
    $raw,
    time(),
    function_exists('posix_kill')
        ? static fn (int $pid): bool => posix_kill($pid, 0)
        : static fn (int $pid): bool => true,
);

fclose($handle);

fwrite(STDERR, "REFUSED: a pest run holds the MySQL test lane, so this commit was NOT made.\n"
    .'Held by '.$holder.".\n"
    .'Holder record: '.(is_string($raw) ? trim($raw) : '(unreadable)')."\n"
    ."A commit landing mid-run is stamped as that run's TIA baseline (pestphp/pest#1856), and tracked files are test input here (T23b).\n"
    ."Wait for the run to finish, then commit.\n"
    ."Override, knowing it can poison the TIA graph: PODTEXT_ALLOW_COMMIT_DURING_RUN=1 git commit ...\n");

exit(1);
