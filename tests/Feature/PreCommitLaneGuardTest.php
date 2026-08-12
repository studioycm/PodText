<?php

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
 */

it('ships an executable pre-commit hook that probes the lane lock through the contract', function (): void {
    $hook = base_path('scripts/git-hooks/pre-commit');

    expect(is_file($hook))->toBeTrue('scripts/git-hooks/pre-commit is missing.')
        ->and(is_executable($hook))->toBeTrue('scripts/git-hooks/pre-commit is not executable, so git will skip it silently.');

    $source = File::get($hook);

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
    $result = Process::path(base_path())->run('scripts/git-hooks/pre-commit');

    expect($result->exitCode())->toBe(1)
        ->and($result->errorOutput())
        ->toContain('REFUSED')
        ->toContain('pid '.getmypid())
        ->toContain('1856');
});

it('lets the escape hatch through, and never silently', function (): void {
    $result = Process::path(base_path())
        ->env(['PODTEXT_ALLOW_COMMIT_DURING_RUN' => '1'])
        ->run('scripts/git-hooks/pre-commit');

    expect($result->exitCode())->toBe(0)
        ->and($result->errorOutput())
        ->toContain('SKIPPED')
        // The override must always state what it costs, or it becomes the
        // default the moment someone is in a hurry.
        ->toContain('1856');
});
