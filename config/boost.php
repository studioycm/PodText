<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Boost Guidelines
    |--------------------------------------------------------------------------
    |
    | Second line of defence only. Read this before "simplifying" it.
    |
    | The unsafe text is not Boost's. Both FilaCheck packages ship it
    | themselves, in
    | `vendor/laraveldaily/filacheck{,-pro}/resources/boost/guidelines/core.blade.php`,
    | which literally say "you must run `filacheck --fix`". Boost only
    | discovers and renders them. That contradicts this project's rule that
    | fixes are written only with explicit approval, and the binary
    | force-enables `--fix` on its own whenever it detects an AI agent.
    | PodText's own FilaCheck rules live in .ai/guidelines/tooling-quality.md.
    |
    | PRIMARY cancellation is at the source: neither package is selected in
    | `boost.json` "packages". GuidelineComposer::keyBelongsToSelectedPackage()
    | drops every guideline whose key is not under a selected package, and it
    | matches by PREFIX, so it survives the key renames this exclude list
    | cannot. Deselecting costs nothing else — the FilaCheck packages ship no
    | skills, and `filament-security-audit` comes from filament/blueprint.
    |
    | This list stays as a backstop for the one case deselection misses:
    | someone re-ticking the packages in the interactive `boost:install`
    | dialog. Boost matches these keys with a strict `in_array`, so a rename
    | silently drops the exclusion rather than erroring — Boost 2.5 did
    | exactly that by suffixing keys with `/core`, which is why both spellings
    | are listed. FilacheckAgentModeGuardTest asserts on the composed output
    | rather than on either mechanism, so it catches a failure of both.
    |
    */

    'guidelines' => [
        'exclude' => [
            'laraveldaily/filacheck',
            'laraveldaily/filacheck-pro',
            'laraveldaily/filacheck/core',
            'laraveldaily/filacheck-pro/core',
        ],
    ],

];
