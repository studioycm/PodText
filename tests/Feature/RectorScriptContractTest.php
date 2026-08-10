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
        ->toContain("withPHPStanConfigs([__DIR__.'/phpstan.neon', __DIR__.'/vendor/larastan/larastan/extension.neon'])");
});

it('boots the real PHPStan container behind that wiring without the extension-installer schema crash', function (): void {
    $result = Process::path(base_path())->run('vendor/bin/rector process --dry-run --ansi --output-format=json');

    $combined = $result->output().$result->errorOutput();

    expect($combined)->not->toContain('PHPStanServicesFactory')
        ->and($combined)->not->toContain('ValidationException')
        ->and($combined)->not->toContain('fatal_errors');
});
