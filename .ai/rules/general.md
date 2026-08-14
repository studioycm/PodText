---
paths:
  - rector.php
  - '**/*.php'
---

# General

## Rector skips phpstan/extension-installer — wire extensions explicitly, measure serial with a cold cache
Rector never runs extension-installer discovery (rectorphp #8006/#8141), so every PHPStan extension this project relies on must be listed explicitly in withPHPStanConfigs — both phpstan.neon AND vendor/larastan/larastan/extension.neon; the symptom of forgetting is a boot fatal naming unknown phpstan.neon keys. Keep ->withoutParallel(): parallel mode was measured nondeterministic and lossy here (17 vs 8 changed files run-to-run; serial 69, byte-identical across four runs — a worker hitting the larastan boot gap loses its chunk). Before trusting any dry-run for a write decision, clear the cache (composer rector -- --clear-cache): a warm cache under-reported 11 then 6 vs the true 20. composer rector is dry-run-locked; composer rector:fix requires explicit operator approval (RectorScriptContractTest pins all of this). Full evidence: docs/research/rector-dry-run-reports/2026-08-10-laravel-code-quality.md.

## Use Laravel Boost's tools before shell or manual alternatives
This is a Laravel project and Boost is installed. Prefer its MCP tools over
guessing or shelling out:

- `search-docs` BEFORE writing framework/package code — it returns docs matched
  to the versions actually installed here, so it beats memory and beats the web.
- `database-schema` before writing a migration or model; `database-query` for
  read-only queries instead of tinker.
- `get-absolute-url` before sharing any project URL; `browser-logs`,
  `last-error`, `read-log-entries` when diagnosing.
- `record-rule` for every durable rule — never a hand-edit of `.ai/rules`, and
  never a personal/native memory, which is session-scoped and unshared.

Two traps specific to this repo:
- **Never hand-edit `CLAUDE.md` or `AGENTS.md`.** Both are generated wholesale;
  `CLAUDE.md` entirely. Durable text belongs in `.ai/guidelines/*.md` (composed
  into every agent's file) or in `.ai/rules/*.md` via `record-rule`.
- Use the composer scripts (`composer boost:sync`, `boost:refresh`), never the
  bare artisan commands, and read `git diff CLAUDE.md AGENTS.md` afterwards.
  Note `boost:sync` ends in a pest run, so it TAKES THE TEST LANE — announce it
  like any suite run.
