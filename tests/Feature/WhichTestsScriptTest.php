<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/*
 * `scripts/which-tests.php` answers "which test files cover this source file?"
 * from the graph pest's TIA already recorded, without running anything. The
 * instinct it serves — only run what relates to what I touched — is sound and
 * unservable by memory: 207 of 745 source files reach
 * CardTemplatePreviewBrowserTest, every config file among them.
 *
 * THIS FILE'S FIRST VERSION WAS GREEN FOR THE WRONG REASON, and the way it
 * failed is worth more than the feature. It asserted the output
 * `toContain('vendor/bin/pest')` against a fixture whose selection was 100% of
 * its own suite — so the script took its "just run the whole thing" branch and
 * never printed a command at all. The assertion passed anyway, because when
 * the tree is dirty the staleness banner prints
 * `Re-record: … vendor/bin/pest --tia`, which contains that substring. A
 * fixture graph isolated the DATA and left the ENVIRONMENT ambient: the script
 * also consults the real repository for staleness, so tree cleanliness decided
 * the result. It passed while the files were uncommitted and went red the
 * moment they were committed.
 *
 * Three faults, all fixed below: a degenerate fixture that could never reach
 * the interesting branch, an assertion loose enough to match a different line,
 * and a test that read ambient git state while looking hermetic. Assertions
 * here now match the command's ARGUMENTS, which no banner can satisfy, and
 * both sides of the threshold are exercised.
 */
function whichTests(array $arguments): array
{
    $result = Process::path(base_path())->run('php '.base_path('scripts/which-tests.php').' '.implode(' ', $arguments));

    return ['exit' => $result->exitCode(), 'out' => $result->output().$result->errorOutput()];
}

/**
 * @param  bool  $withUnrelatedBulk  adds slow, unrelated test files so a selection
 *                                   is a MINORITY of the fixture suite. Without it the selection is 100%
 *                                   of its own suite by construction and the command branch is unreachable.
 */
function whichTestsFixtureGraph(string $sha, bool $withUnrelatedBulk = true): string
{
    $path = base_path('storage/framework/testing/which-tests-'.getmypid().'.json');

    $edges = [
        'tests/Feature/AlphaTest.php' => [0],
        'tests/Feature/BetaTest.php' => [0, 1],
    ];

    $results = [
        'a' => ['status' => 0, 'time' => 2.5, 'file' => 'tests/Feature/AlphaTest.php'],
        'b' => ['status' => 0, 'time' => 1.5, 'file' => 'tests/Feature/BetaTest.php'],
    ];

    if ($withUnrelatedBulk) {
        $edges['tests/Feature/UnrelatedTest.php'] = [1];
        $results['c'] = ['status' => 0, 'time' => 96.0, 'file' => 'tests/Feature/UnrelatedTest.php'];
    }

    File::ensureDirectoryExists(dirname($path));
    File::put($path, json_encode([
        'schema' => 1,
        'files' => ['app/Covered.php', 'app/Lonely.php'],
        'edges' => $edges,
        'baselines' => ['main' => ['sha' => $sha, 'tree' => [], 'results' => $results]],
    ], JSON_THROW_ON_ERROR));

    return $path;
}

function whichTestsHeadSha(): string
{
    $output = [];
    exec('git -C '.escapeshellarg(base_path()).' rev-parse HEAD 2>/dev/null', $output);

    return trim(implode('', $output));
}

it('ships an executable lookup script', function (): void {
    expect(is_file(base_path('scripts/which-tests.php')))->toBeTrue()
        ->and(is_executable(base_path('scripts/which-tests.php')))->toBeTrue();
});

it('names the test files that cover a source file, prices them, and offers the command', function (): void {
    $graph = whichTestsFixtureGraph(whichTestsHeadSha());

    try {
        $run = whichTests(['app/Covered.php', '--graph='.$graph]);
    } finally {
        File::delete($graph);
    }

    expect($run['exit'])->toBe(0)
        ->and($run['out'])
        ->toContain('tests/Feature/AlphaTest.php')
        ->toContain('tests/Feature/BetaTest.php')
        // Priced, so a selection can be judged before it is run.
        ->toContain('~4s')
        // The command WITH ITS ARGUMENTS. Asserting the bare binary path was
        // the original defect: the staleness banner contains it too.
        ->toContain('vendor/bin/pest tests/Feature/AlphaTest.php tests/Feature/BetaTest.php')
        // Unrelated bulk must not be dragged in.
        ->not->toContain('UnrelatedTest');
});

it('tells you to run everything instead when the selection is most of the suite', function (): void {
    // The branch that fired accidentally before, now exercised on purpose.
    $graph = whichTestsFixtureGraph(whichTestsHeadSha(), withUnrelatedBulk: false);

    try {
        $run = whichTests(['app/Covered.php', '--graph='.$graph]);
    } finally {
        File::delete($graph);
    }

    expect($run['exit'])->toBe(0)
        ->and($run['out'])
        ->toContain('100% of the suite')
        // No command offered — and the assertion names the argument form, so a
        // staleness banner mentioning the binary cannot satisfy it either way.
        ->not->toContain('vendor/bin/pest tests/');
});

it('refuses to let an unknown path read as "nothing to run"', function (): void {
    // The dangerous case. A file with no recorded coverage means UNKNOWN, and
    // a tool that prints nothing invites the opposite conclusion.
    $graph = whichTestsFixtureGraph(whichTestsHeadSha());

    try {
        $run = whichTests(['app/NeverHeardOf.php', '--graph='.$graph]);
    } finally {
        File::delete($graph);
    }

    expect($run['exit'])->toBe(3)
        ->and($run['out'])
        ->toContain('NO RECORDED COVERAGE')
        ->toContain('Run the full suite');
});

it('says a graph from an unreachable commit is unusable rather than current', function (): void {
    // Caught by testing rather than reasoning: reading git's empty output as
    // data made a bogus sha print "current with HEAD" — a failed command
    // reported as a reassuring fact.
    $graph = whichTestsFixtureGraph('0000000000000000000000000000000000000000');

    try {
        $run = whichTests(['app/Covered.php', '--graph='.$graph]);
    } finally {
        File::delete($graph);
    }

    expect($run['out'])
        ->toContain('UNUSABLE')
        ->not->toContain('current with HEAD');
});

it('fails loudly on a missing graph and on no arguments', function (): void {
    expect(whichTests([])['exit'])->toBe(1)
        ->and(whichTests(['app/Covered.php', '--graph=/nonexistent/graph.json'])['exit'])->toBe(1)
        ->and(whichTests(['app/Covered.php', '--graph=/nonexistent/graph.json'])['out'])->toContain('not readable');
});
