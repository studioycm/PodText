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
 * The tests below drive the script through a FIXTURE graph rather than the
 * machine-global one, so they neither depend on a recording existing nor
 * disturb it. Every branch that could hand back a confident wrong answer is
 * pinned: an unknown path must not read as "nothing to run", and an
 * unreachable recorded sha must not read as "current".
 */
function whichTests(array $arguments): array
{
    $result = Process::path(base_path())->run('php '.base_path('scripts/which-tests.php').' '.implode(' ', $arguments));

    return ['exit' => $result->exitCode(), 'out' => $result->output().$result->errorOutput()];
}

function whichTestsFixtureGraph(string $sha): string
{
    $path = base_path('storage/framework/testing/which-tests-'.getmypid().'.json');

    File::ensureDirectoryExists(dirname($path));
    File::put($path, json_encode([
        'schema' => 1,
        'files' => ['app/Covered.php', 'app/Lonely.php'],
        'edges' => [
            'tests/Feature/AlphaTest.php' => [0],
            'tests/Feature/BetaTest.php' => [0, 1],
        ],
        'baselines' => [
            'main' => [
                'sha' => $sha,
                'tree' => [],
                'results' => [
                    'a' => ['status' => 0, 'time' => 2.5, 'file' => 'tests/Feature/AlphaTest.php'],
                    'b' => ['status' => 0, 'time' => 1.5, 'file' => 'tests/Feature/BetaTest.php'],
                ],
            ],
        ],
    ], JSON_THROW_ON_ERROR));

    return $path;
}

it('ships an executable lookup script', function (): void {
    expect(is_file(base_path('scripts/which-tests.php')))->toBeTrue()
        ->and(is_executable(base_path('scripts/which-tests.php')))->toBeTrue();
});

it('names the test files that cover a source file, and prices them', function (): void {
    $graph = whichTestsFixtureGraph(trim(shell_exec('git -C '.escapeshellarg(base_path()).' rev-parse HEAD') ?? ''));

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
        // Small enough to paste, so it offers the command.
        ->toContain('vendor/bin/pest');
});

it('refuses to let an unknown path read as "nothing to run"', function (): void {
    // The dangerous case. A file with no recorded coverage means UNKNOWN, and
    // a tool that prints nothing invites the opposite conclusion.
    $graph = whichTestsFixtureGraph(trim(shell_exec('git -C '.escapeshellarg(base_path()).' rev-parse HEAD') ?? ''));

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
