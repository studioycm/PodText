<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/*
 * Rector writes to source files — FilaCheck's hazard class. The composer
 * script is the only sanctioned entry point, and it must stay dry-run-only;
 * writing goes through rector:fix, which needs explicit operator approval
 * (same contract FilacheckAgentModeGuardTest pins for FilaCheck).
 */

it('keeps composer rector dry-run-only and the write path separate', function (): void {
    $scripts = json_decode(File::get(base_path('composer.json')), true, 512, JSON_THROW_ON_ERROR)['scripts'];

    expect(implode(' ', (array) ($scripts['rector'] ?? [])))->toContain('--dry-run')
        ->and($scripts)->toHaveKey('rector:fix')
        ->and(implode(' ', (array) ($scripts['rector:fix'] ?? [])))->not->toContain('--dry-run');
});

/*
 * The task brief's original wiring — withPHPStanConfigs([__DIR__.'/phpstan.neon'])
 * alone — throws before Rector ever reaches a file to process. Confirmed by hand
 * (standalone reproduction against Rector\Bootstrap\RectorConfigsResolver +
 * Rector\DependencyInjection\RectorContainerFactory): PHPStanServicesFactory
 * builds its own PHPStan container straight from
 * PHPStan\DependencyInjection\ContainerFactory, which does NOT replicate
 * phpstan/extension-installer's auto-discovery. That auto-discovery is the
 * only reason `vendor/bin/phpstan` accepts phpstan.neon's
 * `databaseMigrationsPath`/`parseModelCastsMethod` keys — they are schema
 * larastan's own extension.neon registers, and Rector never loads it, by
 * documented design ("Extensions are ignored on purpose" —
 * getrector.com/documentation/config-configuration). Same trap reported
 * upstream: rectorphp/rector#8006 (Larastan-through-Rector), #8141 (the
 * feature request that made withPHPStanConfigs()/phpstanConfigs() accept
 * more than one path — the fix below depends on that).
 *
 * The fix is additive and stays inside this file: give Rector larastan's
 * extension.neon in the same array, so its container has the schema that
 * makes phpstan.neon's overrides legal. Reproduced against both forms —
 * one path throws Nette\Schema\ValidationException: "Unexpected item
 * 'parameters › databaseMigrationsPath'."; two paths boot clean — so this
 * is pinned as the two-path form rather than the brief's single path.
 */
it('keeps rector wired to larastan through phpstan.neon and larastan\'s own extension.neon', function (): void {
    expect(File::get(base_path('rector.php')))
        ->toContain("withPHPStanConfigs([__DIR__.'/phpstan.neon', __DIR__.'/vendor/larastan/larastan/extension.neon'])")
        ->toContain('withoutParallel');
});

it('boots the real PHPStan container behind that wiring without the extension-installer schema crash', function (): void {
    /*
     * ONE file, not the whole project, and the saving is the point: processing
     * all ~628 configured files took 15.1s — 4% of the entire suite — to prove
     * something that happens before any file is read. The container is built
     * from the config, so the crash this guards cannot depend on the corpus.
     *
     * Verified rather than assumed, because a narrowed probe that stops proving
     * anything is worse than a slow one: with larastan's extension.neon removed
     * from rector.php's withPHPStanConfigs(), BOTH the full run and this
     * one-file run emit `PHPStanServicesFactory` and `fatal_errors`, and neither
     * emits `totals`. Same detection, 15.1s → under 1s. Re-run that experiment
     * before ever widening this back.
     *
     * The path is asserted first so a rename fails legibly here instead of as a
     * confusing Rector error, and it must stay inside rector.php's configured
     * paths (app, database, routes) or Rector processes nothing at all.
     */
    $target = 'app/Support/Slugs/HebrewSlugger.php';

    expect(base_path($target))->toBeFile();

    $result = Process::path(base_path())
        ->timeout(120)
        ->run("vendor/bin/rector process {$target} --dry-run --ansi --output-format=json");

    $combined = $result->output().$result->errorOutput();

    // Positive canary first: Rector's JsonOutputFormatter always emits a "totals" key
    // (see JsonOutputFactory::create()) on any real, completed run — reportable or not.
    // Without this, three "not->toContain" assertions alone would pass just as happily
    // if Rector silently never ran at all (binary missing, path wrong, process killed).
    expect($combined)->toContain('totals')
        ->and($combined)->not->toContain('PHPStanServicesFactory')
        ->and($combined)->not->toContain('ValidationException')
        ->and($combined)->not->toContain('fatal_errors');
});

/*
 * Everything above pins the wiring string and proves the container boots — neither
 * proves the actual hazard is contained. FilaCheck's own guard test (see
 * FilacheckAgentModeGuardTest) doesn't stop at "the opt-out flag is set"; it runs the
 * real binary against a fixture with a genuine violation and checks the file on disk
 * afterward. This test is that second half for Rector: a rule that DOES match, run for
 * real against a throwaway file, with --dry-run in the command — then the file is read
 * back and must be byte-identical to what was written, proving the dry run reported a
 * change without ever writing one.
 */
it('withholds the write during --dry-run even when a rule genuinely matches', function (): void {
    $fixtureRoot = sys_get_temp_dir().'/podtext-rector-'.bin2hex(random_bytes(6));
    $fixtureSourceDir = $fixtureRoot.'/src';
    $fixtureFile = $fixtureSourceDir.'/probe.php';
    $fixtureConfig = $fixtureRoot.'/rector-fixture.php';

    File::ensureDirectoryExists($fixtureRoot);
    File::ensureDirectoryExists($fixtureSourceDir);

    // app('translator') is AppToResolveRector's own documented example (its
    // RuleDefinition code sample is literally `app('foo'); -> resolve('foo');`) — a
    // string-literal abstract, no ::class constant needed, confirmed against the
    // rule's source rather than assumed.
    $originalSource = <<<'PHP'
        <?php
        $instance = app('translator');
        PHP;

    File::put($fixtureFile, $originalSource);

    // Minimal standalone config: same fluent RectorConfig API and the same
    // LaravelSetList import path rector.php uses, scoped to only the fixture
    // directory so this never touches the real app tree.
    File::put($fixtureConfig, <<<PHP
        <?php

        use Rector\Config\RectorConfig;
        use RectorLaravel\Set\LaravelSetList;

        return RectorConfig::configure()
            ->withPaths(['{$fixtureSourceDir}'])
            ->withSets([LaravelSetList::LARAVEL_CODE_QUALITY])
            ->withCache('{$fixtureRoot}/cache');
        PHP);

    try {
        $result = Process::path(base_path())
            ->timeout(120)
            ->run("vendor/bin/rector process --dry-run --config {$fixtureConfig} --output-format=json");

        $decoded = json_decode($result->output(), true);

        // Rector reports changed_files relative to CWD (e.g. "../../../tmp/.../probe.php"),
        // not absolute — match on the fixture's own basename rather than a full-path
        // string, so this doesn't depend on where the suite happens to run from.
        $reportedFiles = array_map(fn (string $path): string => basename($path), $decoded['changed_files'] ?? []);

        expect($decoded)
            ->not->toBeNull('Rector did not return valid JSON: '.$result->output().$result->errorOutput())
            ->and($decoded['totals']['changed_files'] ?? null)->toBe(1)
            ->and($reportedFiles)->toContain('probe.php')
            ->and(File::get($fixtureFile))
            ->toBe($originalSource, 'The dry run wrote to the fixture file — --dry-run no longer withholds writes.');
    } finally {
        File::deleteDirectory($fixtureRoot);
    }
});
