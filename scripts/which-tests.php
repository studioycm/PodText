#!/usr/bin/env php
<?php

/*
|--------------------------------------------------------------------------
| which-tests — which test files cover these source files?
|--------------------------------------------------------------------------
|
| Answers the question TIA answers, without running anything: it reads the
| graph pest's Test Impact Analysis already recorded and prints the test files
| that were observed executing the given source files.
|
|     php scripts/which-tests.php app/Models/ContentItem.php
|     php scripts/which-tests.php app/Support/Slugs/HebrewSlugger.php config/tags.php
|
| Why this exists: "only run the tests related to what I touched" is a good
| instinct that human memory cannot serve here. 207 of 745 source files reach
| CardTemplatePreviewBrowserTest, every config file among them, because every
| test boot loads every config. The graph knows; nobody can.
|
| STALENESS IS THE WHOLE RISK, so it is reported first and loudly. Edges are
| only refreshed by a run with a coverage driver enabled. An ordinary gate
| writes results back but NOT edges, so this map ages every time code changes
| without a recording. A lookup tool that cannot say "I might be out of date"
| is a false-confidence machine, which is the failure family this repo has
| documented five times (see docs/research/defect-cause-patterns.md).
|
| Re-record with:  XDEBUG_MODE=coverage php -d memory_limit=2G vendor/bin/pest --tia
|
| Exit codes: 0 found coverage · 1 usage or no usable graph · 3 a path has no
| recorded coverage (which means "unknown", never "no tests need running").
*/

$argv = $_SERVER['argv'];
array_shift($argv);

$graphOverride = null;
$paths = [];

foreach ($argv as $argument) {
    if (str_starts_with($argument, '--graph=')) {
        $graphOverride = substr($argument, 8);

        continue;
    }
    if (str_starts_with($argument, '-')) {
        fwrite(STDERR, "Unknown option {$argument}\n");

        exit(1);
    }
    $paths[] = $argument;
}

if ($paths === []) {
    fwrite(STDERR, "Usage: php scripts/which-tests.php <source-path> [<source-path>...] [--graph=<file>]\n");

    exit(1);
}

$root = dirname(__DIR__);

/**
 * Locate the graph. Pest keys its cache directory by project identity, so the
 * directory name is not guessable — glob for it rather than recomputing pest's
 * internal hashing, which would silently drift from the vendor.
 */
$graphPath = $graphOverride;

if ($graphPath === null) {
    $home = (string) (getenv('HOME') ?: '');
    $candidates = glob($home.'/.pest/tia/*/graph.json') ?: [];

    if ($candidates === []) {
        fwrite(STDERR, "No TIA graph found under {$home}/.pest/tia/.\n"
            ."Record one first:  XDEBUG_MODE=coverage php -d memory_limit=2G vendor/bin/pest --tia\n");

        exit(1);
    }

    if (count($candidates) > 1) {
        fwrite(STDERR, "Several TIA graphs found; pass --graph=<file> to choose:\n  ".implode("\n  ", $candidates)."\n");

        exit(1);
    }

    $graphPath = $candidates[0];
}

if (! is_file($graphPath)) {
    fwrite(STDERR, "Graph not readable: {$graphPath}\n");

    exit(1);
}

$graph = json_decode((string) file_get_contents($graphPath), true);

if (! is_array($graph) || ! isset($graph['files'], $graph['edges'])) {
    fwrite(STDERR, "Graph is not in the expected shape: {$graphPath}\n");

    exit(1);
}

$files = $graph['files'];
$edges = $graph['edges'];

/**
 * Run git and return [output, exitCode]. The exit code is the point: a failed
 * command returns an empty string, and an empty string read as data says
 * "nothing changed" — the confident-wrong-answer shape this script exists to
 * avoid reporting. Caught while testing an unreachable sha, which claimed
 * "current with HEAD".
 *
 * @return array{0: string, 1: int}
 */
$git = static function (string $arguments) use ($root): array {
    $output = [];
    $status = 1;
    exec('git -C '.escapeshellarg($root).' '.$arguments.' 2>/dev/null', $output, $status);

    return [implode("\n", $output), $status];
};

/*
 * Which baseline answers the question, and SAY SO. The graph can hold one per
 * branch, so picking silently is a quiet wrong answer waiting for a second
 * branch to exist: the fallback chain would hand back another branch's
 * coverage map with nothing in the output to show it had. The chosen baseline
 * and the reason are printed in the header instead of assumed.
 */
[$branch, $branchStatus] = $git('rev-parse --abbrev-ref HEAD');
$baselines = is_array($graph['baselines'] ?? null) ? $graph['baselines'] : [];

if ($branchStatus !== 0) {
    $branch = '';
}

if ($branch !== '' && isset($baselines[$branch])) {
    $baselineName = $branch;
    $baselineWhy = 'current branch';
} elseif (isset($baselines['main'])) {
    $baselineName = 'main';
    $baselineWhy = $branch === ''
        ? 'FALLBACK — could not read the current branch'
        : "FALLBACK — no baseline recorded for branch {$branch}";
} else {
    $baselineName = (string) (array_key_first($baselines) ?? '');
    $baselineWhy = 'FALLBACK — first baseline in the graph';
}

$baseline = $baselines[$baselineName] ?? [];
$baseline = is_array($baseline) ? $baseline : [];

/** Per-test-file wall time, so a selection can be priced before it is run. */
$secondsByTestFile = [];
$graphTotalSeconds = 0.0;

foreach (($baseline['results'] ?? []) as $result) {
    $file = $result['file'] ?? null;

    if (! is_string($file)) {
        continue;
    }

    $secondsByTestFile[$file] = ($secondsByTestFile[$file] ?? 0.0) + (float) ($result['time'] ?? 0);
    $graphTotalSeconds += (float) ($result['time'] ?? 0);
}

echo "graph        {$graphPath}\n";
echo 'baseline     '.($baselineName === '' ? '(none)' : $baselineName)."  ({$baselineWhy})\n";

$recordedSha = (string) ($baseline['sha'] ?? '');

if ($recordedSha !== '') {
    $short = substr($recordedSha, 0, 7);
    [, $reachable] = $git('merge-base --is-ancestor '.escapeshellarg($recordedSha).' HEAD');

    if ($reachable !== 0) {
        echo "recorded at  {$short}\n\n";
        echo "!! UNUSABLE: that commit is not an ancestor of HEAD — the graph describes a tree\n";
        echo "!! this checkout cannot reach, and pest would discard and rebuild it. Every answer\n";
        echo "!! below is from that other tree. Re-record before trusting any of it.\n";
    } else {
        [$behind] = $git('rev-list --count '.escapeshellarg($recordedSha).'..HEAD');
        [$changed] = $git('diff --name-only '.escapeshellarg($recordedSha).'..HEAD');
        [$dirty] = $git('status --porcelain --untracked-files=all');
        $changedCount = $changed === '' ? 0 : count(explode("\n", $changed));
        $dirtyCount = $dirty === '' ? 0 : count(explode("\n", $dirty));

        echo "recorded at  {$short}".($behind !== '' && $behind !== '0' ? ", {$behind} commit(s) behind HEAD" : ', current with HEAD')."\n";

        if ($changedCount > 0 || $dirtyCount > 0) {
            echo "\n!! STALE: {$changedCount} committed file(s) changed since, {$dirtyCount} uncommitted.\n";
            echo "!! Coverage for changed files is unknown — this map predates them.\n";
            echo "!! Re-record: XDEBUG_MODE=coverage php -d memory_limit=2G vendor/bin/pest --tia\n";
        }
    }
}

echo "\n";

$indexByPath = array_flip($files);
$selected = [];
$missing = [];

foreach ($paths as $path) {
    $relative = ltrim(str_replace($root.'/', '', $path), '/');
    $index = $indexByPath[$relative] ?? null;

    if ($index === null) {
        $missing[] = $relative;
        echo "{$relative}\n    NO RECORDED COVERAGE — unknown, not \"nothing to run\".\n"
            ."    Either no test executes it, or the graph predates the file. Run the full suite.\n\n";

        continue;
    }

    $covering = [];

    foreach ($edges as $testFile => $indexes) {
        if (in_array($index, $indexes, true)) {
            $covering[] = $testFile;
            $selected[$testFile] = true;
        }
    }

    sort($covering);
    $seconds = array_sum(array_map(fn (string $f): float => $secondsByTestFile[$f] ?? 0.0, $covering));

    echo "{$relative}\n    ".count($covering).' test file(s), ~'.round($seconds)."s\n";

    foreach ($covering as $testFile) {
        echo '      '.$testFile.'  ('.round($secondsByTestFile[$testFile] ?? 0, 1)."s)\n";
    }

    echo "\n";
}

$selectedFiles = array_keys($selected);
sort($selectedFiles);

if ($selectedFiles === []) {
    exit($missing === [] ? 0 : 3);
}

$selectionSeconds = array_sum(array_map(fn (string $f): float => $secondsByTestFile[$f] ?? 0.0, $selectedFiles));

echo '=== '.count($selectedFiles).' test file(s) total, ~'.round($selectionSeconds).'s of a ~'.round($graphTotalSeconds)."s suite\n";

/*
 * The recommendation is the point. A selection that costs more than the whole
 * suite is worth knowing about BEFORE running it: measured this session, a
 * one-line change to a broad model selected 96% of the suite.
 */
if ($graphTotalSeconds > 0 && $selectionSeconds > $graphTotalSeconds * 0.6) {
    echo '    That is '.round($selectionSeconds / $graphTotalSeconds * 100)."% of the suite — just run the whole thing.\n";
} elseif (count($selectedFiles) <= 12) {
    echo "\n".'php -d memory_limit=2G vendor/bin/pest '.implode(' ', $selectedFiles)."\n";
} else {
    echo '    '.count($selectedFiles)." files is too many to paste; run the full suite or narrow the input.\n";
}

exit($missing === [] ? 0 : 3);
