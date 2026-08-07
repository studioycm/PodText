<?php

use FilacheckPro\FilacheckProServiceProvider;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Laravel\Boost\Install\GuidelineComposer;

/*
 * `vendor/bin/filacheck` is not safe to run bare from an AI coding agent.
 *
 * The binary asks `laravel/agent-detector` whether it is running inside an
 * agent and, when the answer is yes, force-enables `--fix` before any
 * reporter emits output (vendor/laraveldaily/filacheck/bin/filacheck, the
 * `RunContext::isAgent()` branch). It then rewrites source files without
 * being asked — which contradicts the project rule "do not run
 * `filacheck --fix` unless explicitly approved" — and swaps the exit code
 * from "any violation" to "unfixable violations only", so an agent's gate
 * silently passes on work a human's gate would fail.
 *
 * Detection reads `AI_AGENT` FIRST and `CLAUDECODE`/`CLAUDE_CODE` only
 * after, so clearing the CLAUDE* vars does not disable it — Claude Code
 * exports `AI_AGENT` too. Neither `config/filacheck.php` nor
 * `config/filacheck-pro.php` exposes an auto-fix setting; they only toggle
 * individual rules. The one lever is the package's own documented opt-out,
 * `FILACHECK_DISABLE_AGENT_MODE`, which the `composer filacheck` script
 * sets.
 *
 * These tests run the real binary against a throwaway fixture tree — never
 * the repo — and pin both halves of that contract: the opt-out still works,
 * and the hazard it defends against is still real. If a package upgrade
 * renames or drops the opt-out, the first test fails loudly instead of the
 * protection quietly evaporating.
 */

/**
 * A scannable project root holding one auto-fixable violation
 * (`->reactive()`, which FilaCheck rewrites to `->live()`).
 */
function filacheckFixture(): string
{
    $root = sys_get_temp_dir().'/podtext-filacheck-'.bin2hex(random_bytes(6));

    File::ensureDirectoryExists($root.'/app/Filament/Resources');
    File::put($root.'/app/Filament/Resources/ProbeResource.php', <<<'PHP'
        <?php

        namespace App\Filament\Resources;

        use Filament\Forms\Components\TextInput;
        use Filament\Resources\Resource;
        use Filament\Schemas\Schema;

        class ProbeResource extends Resource
        {
            public static function form(Schema $schema): Schema
            {
                return $schema->components([
                    TextInput::make('title')->reactive(),
                ]);
            }
        }
        PHP);

    return $root;
}

/**
 * Run the vendored binary against the fixture, always with an agent
 * environment present so the detector fires exactly as it does in a real
 * agent session.
 *
 * @param  array<string, string|false>  $env
 */
function runFilacheck(string $root, array $env = []): array
{
    $result = Process::path($root)
        ->env([...['AI_AGENT' => 'claude-code_test_agent'], ...$env])
        ->run(base_path('vendor/bin/filacheck'));

    return [
        'exitCode' => $result->exitCode(),
        'source' => File::get($root.'/app/Filament/Resources/ProbeResource.php'),
    ];
}

it('leaves source untouched and keeps the human exit code when the agent-mode opt-out is set', function (): void {
    $root = filacheckFixture();

    try {
        $run = runFilacheck($root, ['FILACHECK_DISABLE_AGENT_MODE' => '1']);
    } finally {
        File::deleteDirectory($root);
    }

    expect(str_contains($run['source'], '->reactive()'))
        ->toBeTrue('FilaCheck rewrote the fixture despite FILACHECK_DISABLE_AGENT_MODE — the opt-out no longer works, and `composer filacheck` is no longer safe to run.');

    expect($run['source'])->not->toContain('->live()');

    // Read-only semantics: one violation found, so a non-zero exit. Agent
    // mode would have fixed it and exited 0 (see the next test).
    expect($run['exitCode'])->toBe(1);
});

it('force-fixes source and reports success without the opt-out, which is why the composer script exists', function (): void {
    $root = filacheckFixture();

    try {
        // `false` unsets the variable for the child process, so this is the
        // bare `vendor/bin/filacheck` an agent would otherwise run.
        $run = runFilacheck($root, ['FILACHECK_DISABLE_AGENT_MODE' => false]);
    } finally {
        File::deleteDirectory($root);
    }

    expect($run['source'])->toContain('->live()')
        ->and($run['source'])->not->toContain('->reactive()');

    // The dangerous half: it wrote to a file and still exited 0, because
    // agent mode exits on `skipped > 0` rather than on violation count.
    expect($run['exitCode'])->toBe(0);
})->skip(
    fn (): bool => ! class_exists(FilacheckProServiceProvider::class),
    'Agent detection is installed by filacheck-pro, which is not present.',
);

it('routes every documented FilaCheck invocation through the composer script', function (): void {
    expect(json_decode(File::get(base_path('composer.json')), true, 512, JSON_THROW_ON_ERROR)['scripts']['filacheck'] ?? [])
        ->toContain('@putenv FILACHECK_DISABLE_AGENT_MODE=1');

    foreach (['CLAUDE.md', '.ai/guidelines/tooling-quality.md'] as $doc) {
        expect(str_contains(File::get(base_path($doc)), 'vendor/bin/filacheck --fix'))
            ->toBeFalse("{$doc} instructs an agent to run `vendor/bin/filacheck --fix`. Re-apply the PodText override.");
    }
});

it('keeps the unsafe vendor guidelines out of anything boost regenerates', function (): void {
    // Both FilaCheck packages ship guidelines telling agents to auto-fix with
    // the raw binary. Editing CLAUDE.md alone is not durable — the next
    // `php artisan boost:install` writes them straight back. config/boost.php
    // excludes them at the source, so this asserts the composer no longer
    // emits them at all rather than asserting today's file contents.
    //
    // Match on prefix, not on the exact key. Boost excludes with a strict
    // `in_array`, and Boost 2.5 renamed these keys to `<package>/core` — an
    // exact-key assertion here passed while the exclusion had already stopped
    // matching and the unsafe text was back in CLAUDE.md.
    $emitted = app(GuidelineComposer::class)->used();

    foreach (['laraveldaily/filacheck', 'laraveldaily/filacheck-pro'] as $package) {
        $leaked = array_values(array_filter(
            $emitted,
            fn (string $key): bool => $key === $package || str_starts_with($key, $package.'/'),
        ));

        expect($leaked)
            ->toBe([], 'Boost still emits ['.implode(', ', $leaked).'], which tells agents to auto-fix with the raw binary. Add the key to boost.guidelines.exclude in config/boost.php.');
    }
});
