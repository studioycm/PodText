<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/*
 * larastan discards every cast this app declares unless `parseModelCastsMethod`
 * is on, and it does so without a word.
 *
 * Laravel merges `casts()` into the cast map in
 * `HasAttributes::initializeHasAttributes()` — a trait initializer, so it runs
 * only from `Model::__construct()`. larastan builds its models with
 * `newInstanceWithoutConstructor()` (ModelCastHelper.php:257), so the
 * initializer never fires and `getCasts()` sees only the `$casts` property,
 * which is empty in every model here. With the flag off larastan falls back to
 * the DECLARED return type of `casts()` — plain `array`, not a constant array —
 * and skips the merge silently.
 *
 * The symptom is not "some types are missing". A cast attribute believed to be
 * a string makes guard clauses always-terminate, so PHPStan marks the code
 * after them unreachable and stops analysing it: real errors hide behind false
 * ones. It cost 107 wrong errors here, and it is invisible — nothing in the
 * output says a cast was dropped.
 *
 * So these tests pin both halves against the real binary, the same way the
 * FilaCheck guard does: the flag still does its job, and the hazard it defends
 * against is still real. If a larastan upgrade renames the parameter or changes
 * the merge, the first test fails loudly instead of the protection quietly
 * evaporating and the error count quietly climbing.
 *
 * See docs/research/larastan-playbook.md §1.
 */

/**
 * A `dumpType` probe kept OUTSIDE the configured `paths` and passed to PHPStan
 * as an explicit argument — CLI paths override config paths, so this analyses
 * the probe alone rather than the whole app.
 *
 * The three casts are chosen to show the failure is not enum-specific: an enum,
 * a datetime and an array, all declared in `ContentItem::casts()`.
 *
 * @return array{root: string, probe: string}
 */
function larastanCastProbe(): array
{
    $root = sys_get_temp_dir().'/podtext-larastan-'.bin2hex(random_bytes(6));
    File::ensureDirectoryExists($root);

    File::put($root.'/probe.php', <<<'PHP'
        <?php

        use App\Models\ContentItem;

        use function PHPStan\dumpType;

        $item = new ContentItem();

        dumpType($item->status);
        dumpType($item->published_at);
        dumpType($item->media_metadata);
        PHP);

    return ['root' => $root, 'probe' => $root.'/probe.php'];
}

/**
 * Analyse the probe with the repo's own config, optionally overridden.
 *
 * `tmpDir` is redirected into the throwaway root for two reasons: a shared
 * result cache would make this test order-dependent, and other sessions run
 * PHPStan against the same default temp directory.
 *
 * @param  array<string, string>  $overrides  extra `parameters:` lines
 */
function runLarastanProbe(array $probe, array $overrides = []): string
{
    $config = $probe['root'].'/phpstan.neon';

    $lines = ["    tmpDir: {$probe['root']}/tmp"];

    foreach ($overrides as $key => $value) {
        $lines[] = "    {$key}: {$value}";
    }

    File::put($config, implode("\n", [
        'includes:',
        '    - '.base_path('phpstan.neon'),
        '',
        'parameters:',
        ...$lines,
        '',
    ]));

    $result = Process::path(base_path())
        ->timeout(300)
        ->run([
            base_path('vendor/bin/phpstan'),
            'analyse',
            '-c', $config,
            $probe['probe'],
            '--no-progress',
            '--memory-limit=2G',
            '-v',
        ]);

    // PHPStan swaps to a JSON error format when it detects an agent, which
    // double-escapes namespace separators. Normalising both shapes to one keeps
    // these assertions independent of who is running the suite.
    return str_replace('\\\\', '\\', $result->output().$result->errorOutput());
}

it('resolves enum, datetime and array casts declared in the casts() method', function (): void {
    $probe = larastanCastProbe();

    try {
        $output = runLarastanProbe($probe);
    } finally {
        File::deleteDirectory($probe['root']);
    }

    expect($output)
        ->toContain('Dumped type: App\Enums\PublicationStatus')
        ->and($output)->toContain('Dumped type: Carbon\Carbon|null')
        ->and($output)->toContain('Dumped type: array|null');

    // The specific wrong answer, named so a regression is unmistakable: without
    // the cast map larastan falls back to the migration column type, and every
    // one of these three columns is a string column.
    expect($output)->not->toContain('Dumped type: string');
});

it('still resolves those casts to plain column types when the flag is off, which is why phpstan.neon sets it', function (): void {
    $probe = larastanCastProbe();

    try {
        $output = runLarastanProbe($probe, ['parseModelCastsMethod' => 'false']);
    } finally {
        File::deleteDirectory($probe['root']);
    }

    // The hazard, reproduced: an enum cast reads as `string`, and a datetime
    // and an array cast both read as the nullable column type. If this test
    // starts failing, larastan has fixed the default upstream (see
    // larastan/larastan#2512) and the parameter in phpstan.neon can be revisited.
    expect($output)
        ->toContain('Dumped type: string')
        ->and($output)->toContain('Dumped type: string|null')
        ->and($output)->not->toContain('Dumped type: App\Enums\PublicationStatus');
});

it('keeps the parameter in the checked-in config rather than only in this test', function (): void {
    expect(File::get(base_path('phpstan.neon')))
        ->toContain('parseModelCastsMethod: true');
});
