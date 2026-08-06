<?php

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;

/*
|--------------------------------------------------------------------------
| Enum match exhaustiveness
|--------------------------------------------------------------------------
|
| `match ($enum)` with no `default` arm is this repo's whole exhaustiveness
| argument for enums over strings — but with no static analyser installed, a
| missing arm is an `UnhandledMatchError` at execution rather than a failing
| build. This sweep makes it a failing build.
|
| It is purely syntactic and needs no type inference: an arm that literally
| names `App\…Enum::Case` identifies its own enum. So it needs the autoloader,
| not a booted application, which is why it belongs in tests/Unit.
|
| Blind spots, recorded rather than hidden: a match with a *wrong* `default`
| arm, arms that reach their cases indirectly (through a variable or a
| `tryFrom()` result), enums outside `App\`, and Blade. See
| docs/research/php-enums-playbook.md section 6a.
|
*/

/**
 * How much of the codebase the sweep must keep reaching.
 *
 * A sweep that silently stops resolving names still passes every
 * exhaustiveness assertion below — it just checks a fraction of the code while
 * reporting green. These floors are what make that fail instead. Measured at
 * 56 guarded matches: 39 named through `self::`/`static::` from inside the
 * enum, 17 through a `use` import in another file.
 */
$guardedMatchFloor = 56;
$selfResolvedFloor = 39;
$importResolvedFloor = 17;

/**
 * Matches known to be non-exhaustive, red-listed rather than fixed.
 *
 * `MediaMutationOperationType::LegacyOwnerRepair` has no arm in
 * `assertOperationShape()`, and the operation type is read back out of the
 * database with `tryFrom()`, so a legacy-owner-repair journal row that reaches
 * a committed status throws `UnhandledMatchError` inside the shape assertion.
 * The fix is a product decision — which paths such a journal is *required* to
 * carry — and belongs to the parked legacy owner-column retirement work, not
 * to this guard.
 *
 * Keyed without a line number on purpose: an edit anywhere above the match
 * would otherwise stale the entry and fail this file for the wrong reason.
 *
 * @var array<string, list<string>> "path :: Enum" => missing case names
 */
$knownUnhandled = [
    'app/Support/Media/MediaFilesystemMutationCoordinator.php :: App\Enums\MediaMutationOperationType' => ['LegacyOwnerRepair'],
];

/**
 * Walks `app/`, `database/` and `routes/` once, returning every `match` whose
 * arms literally name cases of an `App\` enum.
 *
 * @return list<array{enum: string, identity: string, description: string, missing: list<string>, hasDefault: bool, resolution: list<string>}>
 */
$sweepEnumMatches = function (): array {
    static $sweep = null;

    if ($sweep !== null) {
        return $sweep;
    }

    $root = dirname(__DIR__, 2);
    $parser = (new ParserFactory)->createForNewestSupportedVersion();

    $files = [];

    foreach (['app', 'database', 'routes'] as $directory) {
        $tree = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root.'/'.$directory, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($tree as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }
    }

    sort($files);

    $matches = [];

    foreach ($files as $file) {
        $ast = $parser->parse((string) file_get_contents($file));

        /*
         * `NameResolver` must own its traverser. Sharing one traverser with the
         * collector runs both visitors on `Match_` *before* the traverser
         * descends into the arms, so every enum reached through a `use` import
         * is still short-named when the collector reads it: the sweep then
         * finds 39 matches instead of 56 and passes, having checked two thirds
         * of what it claims. `preserveOriginalNames` is what lets the collector
         * tell an imported name from a written-out one afterwards.
         */
        (new NodeTraverser(new NameResolver(null, ['preserveOriginalNames' => true])))->traverse($ast);

        $collector = new class extends NodeVisitorAbstract
        {
            /** @var list<array{class: string, names: list<string>, line: int, hasDefault: bool, resolution: list<string>}> */
            public array $found = [];

            /**
             * `NameResolver` rewrites neither `self::` nor `static::`, so the
             * enclosing declaration is the only thing that can name them — and
             * most exhaustive matches here are `match ($this)` inside the enum.
             *
             * @var list<string>
             */
            private array $enclosingEnums = [];

            public function enterNode(Node $node): null
            {
                if ($node instanceof Node\Stmt\Enum_) {
                    $this->enclosingEnums[] = (string) ($node->namespacedName ?? $node->name);
                }

                if ($node instanceof Node\Expr\Match_) {
                    $this->collectMatch($node);
                }

                return null;
            }

            public function leaveNode(Node $node): null
            {
                if ($node instanceof Node\Stmt\Enum_) {
                    array_pop($this->enclosingEnums);
                }

                return null;
            }

            private function collectMatch(Node\Expr\Match_ $match): void
            {
                $hasDefault = false;
                $named = [];

                foreach ($match->arms as $arm) {
                    if ($arm->conds === null) {
                        $hasDefault = true;

                        continue;
                    }

                    foreach ($arm->conds as $condition) {
                        if (! $condition instanceof Node\Expr\ClassConstFetch) {
                            continue;
                        }

                        if (! $condition->name instanceof Node\Identifier || ! $condition->class instanceof Node\Name) {
                            continue;
                        }

                        [$class, $resolution] = $this->resolveClass($condition->class);

                        if ($class === null) {
                            continue;
                        }

                        $named[$class]['names'][] = $condition->name->toString();
                        $named[$class]['resolution'][$resolution] = true;
                    }
                }

                foreach ($named as $class => $arms) {
                    $this->found[] = [
                        'class' => $class,
                        'names' => array_values(array_unique($arms['names'])),
                        'line' => $match->getStartLine(),
                        'hasDefault' => $hasDefault,
                        'resolution' => array_keys($arms['resolution']),
                    ];
                }
            }

            /**
             * @return array{0: string|null, 1: string}
             */
            private function resolveClass(Node\Name $class): array
            {
                if (in_array($class->toLowerString(), ['self', 'static'], true)) {
                    return $this->enclosingEnums === []
                        ? [null, 'self']
                        : [end($this->enclosingEnums), 'self'];
                }

                $resolved = $class->toString();
                $original = $class->getAttribute('originalName');
                $wasImported = $original instanceof Node\Name && $original->toString() !== $resolved;

                return [$resolved, $wasImported ? 'import' : 'written'];
            }
        };

        (new NodeTraverser($collector))->traverse($ast);

        foreach ($collector->found as $candidate) {
            $enum = $candidate['class'];

            if (! str_starts_with($enum, 'App\\') || ! enum_exists($enum)) {
                continue;
            }

            /*
             * `SomeEnum::class` and an enum's own constants are
             * `ClassConstFetch` nodes too; only arms naming real cases make
             * this a match over an enum.
             */
            $cases = array_map(fn (UnitEnum $case): string => $case->name, $enum::cases());
            $present = array_intersect($cases, $candidate['names']);

            if ($present === []) {
                continue;
            }

            $relativePath = str_replace($root.'/', '', $file);
            $missing = array_values(array_diff($cases, $present));

            $matches[] = [
                'enum' => $enum,
                'identity' => $relativePath.' :: '.$enum,
                'description' => $relativePath.':'.$candidate['line'].' — '.$enum.' has no arm for '.implode(', ', $missing),
                'missing' => $missing,
                'hasDefault' => $candidate['hasDefault'],
                'resolution' => $candidate['resolution'],
            ];
        }
    }

    return $sweep = $matches;
};

/**
 * Every non-exhaustive match the sweep currently finds.
 *
 * @return array<string, list<string>> identity => missing case names
 */
$unhandledMatches = function () use ($sweepEnumMatches): array {
    $unhandled = [];

    foreach ($sweepEnumMatches() as $match) {
        if (! $match['hasDefault'] && $match['missing'] !== []) {
            $unhandled[$match['identity']] = $match['missing'];
        }
    }

    ksort($unhandled);

    return $unhandled;
};

test('every match over an App enum handles every case unless it declares a default arm', function () use ($sweepEnumMatches, $knownUnhandled) {
    $violations = [];

    foreach ($sweepEnumMatches() as $match) {
        if ($match['hasDefault'] || $match['missing'] === []) {
            continue;
        }

        if (($knownUnhandled[$match['identity']] ?? []) === $match['missing']) {
            continue;
        }

        $violations[] = $match['description'];
    }

    sort($violations);

    expect($violations)->toBe([], 'A match over an enum has no arm for every case and no default arm, so an UnhandledMatchError is reachable at runtime. Add the arm, or a default arm if the omission is deliberate.');
});

test('the red-listed non-exhaustive matches are still exactly the ones on the list', function () use ($unhandledMatches, $knownUnhandled) {
    ksort($knownUnhandled);

    /*
     * This fails when a red-listed match is *fixed*, too. The entry has to come
     * off the list along with the fix, or the list quietly stops describing the
     * code and starts excusing whatever lands there next.
     */
    expect($unhandledMatches())->toEqual($knownUnhandled, 'The red list of known non-exhaustive matches no longer matches the code. Either a listed match was fixed and its entry should go, or one changed which cases it misses.');
});

test('the sweep still sees every enum match it is supposed to see', function () use ($sweepEnumMatches, $guardedMatchFloor, $selfResolvedFloor, $importResolvedFloor) {
    $matches = $sweepEnumMatches();

    $resolvedVia = fn (string $how): int => count(array_filter(
        $matches,
        fn (array $match): bool => in_array($how, $match['resolution'], true),
    ));

    expect(count($matches))
        ->toBeGreaterThanOrEqual($guardedMatchFloor, 'The sweep found fewer enum matches than exist. A shortfall here means the sweep stopped seeing code, not that the code got safer — every assertion in this file is only worth the matches it reaches.')
        ->and($resolvedVia('self'))
        ->toBeGreaterThanOrEqual($selfResolvedFloor, 'Matches naming their cases with self:: or static:: are no longer being resolved. NameResolver leaves those alone, so the collector has to track the enclosing enum declaration itself.')
        ->and($resolvedVia('import'))
        ->toBeGreaterThanOrEqual($importResolvedFloor, 'Matches naming an enum imported with `use` are no longer being resolved. NameResolver has to run as its own traverser pass, before the pass that collects matches.');
});
