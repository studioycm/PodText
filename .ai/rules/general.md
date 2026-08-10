---
paths:
  - rector.php
---

# General

## Rector skips phpstan/extension-installer — wire extensions explicitly, measure serial with a cold cache
Rector never runs extension-installer discovery (rectorphp #8006/#8141), so every PHPStan extension this project relies on must be listed explicitly in withPHPStanConfigs — both phpstan.neon AND vendor/larastan/larastan/extension.neon; the symptom of forgetting is a boot fatal naming unknown phpstan.neon keys. Keep ->withoutParallel(): parallel mode was measured nondeterministic and lossy here (17 vs 8 changed files run-to-run; serial 69, byte-identical across four runs — a worker hitting the larastan boot gap loses its chunk). Before trusting any dry-run for a write decision, clear the cache (composer rector -- --clear-cache): a warm cache under-reported 11 then 6 vs the true 20. composer rector is dry-run-locked; composer rector:fix requires explicit operator approval (RectorScriptContractTest pins all of this). Full evidence: docs/research/rector-dry-run-reports/2026-08-10-laravel-code-quality.md.
