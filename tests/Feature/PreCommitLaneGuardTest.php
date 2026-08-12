<?php

use App\Support\Testing\TestLaneContract;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/*
 * The pre-commit hook refuses a commit while a pest run holds the MySQL test
 * lane. It exists because committing is not a pest run: no flock anywhere
 * covers pest-against-git, and a commit that lands mid-run becomes that run's
 * recorded TIA baseline (pestphp/pest#1856) while the results still describe
 * pre-commit code. T23b wanted the same rule long before TIA did, and was
 * breached twice in one evening while it was only a discipline.
 *
 * The refusal test needs no fixture and no arrangement, which is the point: a
 * Feature test IS a live pest run, tests/Pest.php holds the lane lock for the
 * process lifetime, so the hook must refuse right now and the holder it names
 * must be this very process. Anything that silently releases that lock
 * mid-run — the $GLOBALS garbage-collection trap Pest.php documents — turns
 * this test red rather than leaving the lane unguarded in the dark.
 *
 * The three fail-open branches are covered too, because a guard that steps
 * aside when its own plumbing breaks is only acceptable while it still says
 * so out loud — and "it warns" is a claim like any other. Each is driven
 * through the environment (PATH, HOME, a copy of the script), so none of them
 * needed a test-only seam in the hook.
 */

function preCommitHook(): string
{
    return base_path('scripts/git-hooks/pre-commit');
}

it('ships an executable pre-commit hook that probes the lane lock through the contract', function (): void {
    $launcher = preCommitHook();
    $guard = base_path('scripts/git-hooks/pre-commit-lane-guard.php');

    expect(is_file($launcher))->toBeTrue('scripts/git-hooks/pre-commit is missing.')
        ->and(is_executable($launcher))->toBeTrue('scripts/git-hooks/pre-commit is not executable, so git will skip it silently.')
        ->and(is_file($guard))->toBeTrue('The hook logic file is missing.');

    // The launcher must stay a launcher: no external binaries, because the one
    // case it exists to survive is a caller whose PATH cannot find them.
    $launcherSource = File::get($launcher);

    expect($launcherSource)
        ->toContain('pre-commit-lane-guard.php')
        // Shell parameter expansion, not a subprocess: `$(dirname …)` would be
        // the very external binary a minimal PATH cannot resolve.
        ->toContain('${0%/*}')
        ->not->toContain('$(dirname')
        ->not->toContain('$(basename');

    $source = File::get($guard);

    expect($source)
        // The lane's own path, resolved through the contract — never a second
        // path of the hook's own invention, which could drift from the one the
        // suite actually locks and guard nothing at all.
        ->toContain('TestLaneContract::runLockPath(')
        ->not->toContain('podtext-test-lane');

    expect($source)
        ->toContain('LOCK_EX | LOCK_NB')
        // Probe, never holder: the shared lock is released the instant it is
        // taken, so this hook can never refuse a starting pest run.
        ->toContain('flock($handle, LOCK_UN)')
        ->toContain('PODTEXT_ALLOW_COMMIT_DURING_RUN');
});

it('refuses a commit while this pest run holds the lane, and names the holder', function (): void {
    $result = Process::path(base_path())->run(preCommitHook());

    expect($result->exitCode())->toBe(1)
        ->and($result->errorOutput())
        ->toContain('REFUSED')
        ->toContain('pid '.getmypid())
        ->toContain('1856');
});

it('lets the escape hatch through, and never silently', function (): void {
    $result = Process::path(base_path())
        ->env(['PODTEXT_ALLOW_COMMIT_DURING_RUN' => '1'])
        ->run(preCommitHook());

    expect($result->exitCode())->toBe(0)
        ->and($result->errorOutput())
        ->toContain('SKIPPED')
        // The override must always state what it costs, or it becomes the
        // default the moment someone is in a hurry.
        ->toContain('1856');
});

it('allows the commit, loudly, when no php can be found', function (): void {
    // A git GUI can invoke hooks with a minimal PATH. Blocking every commit on
    // such a machine would be a worse failure than not checking.
    $result = Process::path(base_path())
        ->env(['PATH' => '/nonexistent'])
        ->run(preCommitHook());

    expect($result->exitCode())->toBe(0)
        ->and($result->errorOutput())->toContain('no php on PATH');
});

it('allows the commit, loudly, when the lane contract cannot be found', function (): void {
    // A copy of the scripts/ tree with no app/ beside it: the same shape as a
    // checkout that has the hook but not the class it reads the path from.
    $root = base_path('storage/framework/testing/pre-commit-fixture-'.getmypid());
    File::ensureDirectoryExists($root.'/scripts/git-hooks');

    try {
        File::copy(preCommitHook(), $root.'/scripts/git-hooks/pre-commit');
        File::copy(base_path('scripts/git-hooks/pre-commit-lane-guard.php'), $root.'/scripts/git-hooks/pre-commit-lane-guard.php');
        chmod($root.'/scripts/git-hooks/pre-commit', 0755);

        $result = Process::path($root)->run($root.'/scripts/git-hooks/pre-commit');

        expect($result->exitCode())->toBe(0)
            ->and($result->errorOutput())
            ->toContain('TestLaneContract.php is missing')
            ->toContain('Allowing the commit');
    } finally {
        File::deleteDirectory($root);
    }
});

it('allows the commit, loudly, when the lock file cannot be opened', function (): void {
    // The lane root is HOME-anchored, so a throwaway HOME gives a real
    // unopenable lock without touching the machine-global one.
    $home = base_path('storage/framework/testing/pre-commit-home-'.getmypid());
    $realHome = (string) getenv('HOME');
    [$host, $port, $database] = ['127.0.0.1', '3307', 'podtext_test'];

    putenv('HOME='.$home);

    try {
        $lockPath = TestLaneContract::runLockPath($host, $port, $database);
    } finally {
        putenv('HOME='.$realHome);
    }

    File::ensureDirectoryExists(dirname($lockPath));

    try {
        File::put($lockPath, "{}\n");
        chmod($lockPath, 0000);

        $result = Process::path(base_path())
            ->env([
                'HOME' => $home,
                'DB_TESTING_HOST' => $host,
                'DB_TESTING_PORT' => $port,
                'DB_TESTING_DATABASE' => $database,
            ])
            ->run(preCommitHook());

        expect($result->exitCode())->toBe(0)
            ->and($result->errorOutput())
            ->toContain('could not be opened')
            ->toContain('Allowing the commit');
    } finally {
        @chmod($lockPath, 0644);
        File::deleteDirectory($home);
    }
});
