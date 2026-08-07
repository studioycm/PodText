# Tooling Quality Guideline

## Purpose

Keep AI/tooling quality gates consistent across planning and implementation tasks.

## Preferred architecture

Every implementation prompt uses Boost where available, reads its blueprint, checks FilamentExamples for relevant code patterns, and runs the full quality gate.

## Do

- Retry Laravel Boost MCP tools before implementation.
- Read the relevant blueprint first.
- Use FilamentExamples MCP before Filament code.
- For FilamentExamples MCP, decompose the feature into short search topics, run multiple query batches with a higher limit such as 8 to 10 when supported, inspect returned names/snippets/paths, then run a refined second pass. Record example names, paths/classes, copied patterns, rejected patterns, PodText adaptation notes, and whether only `search_examples` was available.
- Run full final quality gate.
- Record FilaCheck/FilaCheck Pro output.
- Preserve cross-cutting form, locale, and dashboard requirements from active specs/guidelines.
- Use current Filament 5 relation-manager APIs for relation manager work.
- Start implementation prompts with git status/log preflight and stop on unexpected app-code dirt unless the user explicitly resolves it.
- Update active state Markdown before the final commit for every successful implementation prompt.
- Classify blueprint requirements in the final report instead of silently skipping ambiguous or difficult items.

## Do not

- Do not claim Boost was used if MCP calls fail.
- Do not run `vendor/bin/filacheck` directly. It force-enables `--fix` whenever it detects an AI coding agent, so the plain command writes to source files.
- Do not run `filacheck --fix` without explicit approval.
- Do not write secrets, tokens, licenses, Composer auth, MCP headers, or machine paths to tracked files.

## Laravel Boost version and regeneration

- This project runs **Laravel Boost 2.5 or newer** (2.5.3 with `laravel/roster` 1.0 as of 2026-08-07). Do not assume 2.4.x behaviour or advice. Boost and roster move together: upgrade both in one `composer update laravel/boost laravel/roster`, because a partial update fails and misreports `filament/blueprint` as the conflict.
- **`CLAUDE.md` is entirely generated.** The `<laravel-boost-guidelines>` block spans the whole file, so anything hand-written into it is destroyed by the next `boost:install` or `boost:update`. `AGENTS.md` is the only exception — it has a hand-written region before the block that survives — but that reaches Codex alone. Never hand-edit either file.
- Durable text goes in the source Boost composes from, not its output:
  - **Always-on project policy → `.ai/guidelines/*.md`.** Composed into every agent's file, so it reaches Claude, Codex and Junie and survives every regeneration.
  - **Path-scoped traps → `.ai/rules/*.md`.** Glob-matched and read on demand, written with the `record-rule` MCP tool rather than by hand. Propose the glob, title and note text and get approval before recording.
  - **Anything that must not silently regress → a test.** Prose advises; only a test fails. `tests/Feature/FilacheckAgentModeGuardTest.php` is the worked example.
- Run `boost:install` with all three features (`--guidelines --skills --mcp`). A subset rewrites the agent files from the selected features alone and silently drops the sections belonging to the omitted ones.
- `boost:update` is deliberately not automated, and must not be put in a composer hook: it can silently reintroduce vendor guidelines this project excludes on purpose. Run it by hand, then immediately run `php artisan test --filter=FilacheckAgentModeGuard` and read `git diff CLAUDE.md` before committing. On 2.5+ `--discover` is the default; `--no-discover` opts out.
- Vendor guidelines are cancelled at the source by leaving the package out of `boost.json` "packages" — Boost filters those by prefix, so it survives guideline-key renames. `boost.guidelines.exclude` in `config/boost.php` is only a backstop; it matches exactly and fails open.

## Testing rules

- Each implementation prompt must add/update Pest tests.
- Prefer behavior tests over class-existence checks, especially for admin UI registration, import/export rows, public visibility, filters, and failed-row behavior.
- Final implementation gate:

```bash
php artisan test
vendor/bin/pint --test
composer filacheck
npm run build
```

## Prompt completion protocol

- `docs/phase-02/current-project-state.md` is the single source for rolling prompt progress.
- Successful implementation prompts must update `docs/phase-02/current-project-state.md` before commit.
- Patch other docs only when stable scope, ownership, requirements, or durable lessons changed.
- Avoid copy-pasting completion status into prompts, specs, blueprints, guidelines, or index files.
- Prompt final reports are not a substitute for updating current state.
- Tests must prove real behavior, not only class existence or static registration.

## Security rules

- Review diffs for secrets before final report.
- Keep `.env`, MCP config, Composer auth, and license files untouched.

## FilamentExamples MCP Research Protocol

- Use `filament-examples` MCP before changing Filament Resources, Pages, Settings pages, forms, tables, actions, widgets, Livewire public page patterns, or panel layout/header behavior.
- Do not run one broad query only. First decompose the feature into short topic phrases and scatter them across multiple query batches.
- Prefer multiple short queries over one long query. Use `limit` 8 to 10 when supported; if the MCP rejects the limit, retry with the maximum accepted limit or with `limit: 3`.
- After first results, inspect result names, snippets, source paths, and class names. Run a second pass with refined terms based on those results.
- Search direct goals and surrounding implementation patterns, such as tabbed settings, render hooks, page shells, FileUpload safety, card grids, Livewire state, and public Blade rendering.
- For each relevant example, record the example name, file/class/snippet found, pattern to copy, pattern to avoid, and PodText adaptation notes.
- If the MCP exposes a source/read/fetch/details tool, use it. If only `search_examples` exists, record that limitation honestly.
- Never write MCP token/header values to tracked docs.

## FilaCheck / FilaCheck Pro notes

- Run FilaCheck as `composer filacheck`. Never run `vendor/bin/filacheck`, which rewrites source files when an AI agent runs it, even without `--fix`.
- Local iteration may use `composer filacheck -- --dirty`; final verification uses `composer filacheck` with no arguments.
- `composer filacheck:fix` is the only approved way to write fixes, and still needs explicit approval.
- Treat remaining violations as blockers in implementation prompts.
- FilaCheck/FilaCheck Pro must pass; do not run `filacheck --fix` unless explicitly approved.
- If a prompt uses combined relation tabs with content, use the official Filament method names for the installed version.
- Prompt 09 final reports must state whether combined tabs, relation manager badges, redirect behavior, and create-another behavior were implemented.
- Prompt 10 established that successful prompts must leave active docs aligned with code before committing; future prompts should treat missing state-doc updates as incomplete work.

## Cross-cutting UI rules

- Slug fields should auto-generate from title/name fields but allow manual override.
- Technical fields must have helper text, hints, or descriptions.
- Date/date-time UI should use Hebrew/Israel locale behavior: `dd/mm/yyyy` for dates and `dd/mm/yyyy HH:mm` for date-times.
- Store dates normally with Laravel, but display/input date-times in the `Asia/Jerusalem` UI timezone.
- Public and admin table date columns must use day-first format.
- Use translation keys for labels, hints, helper text, and date labels.
- Admin dashboard widgets should include available editorial metrics and avoid polling unless needed.

## Related active docs

- `docs/phase-02/tooling-and-quality-gates.md`
- `docs/research/filament-examples-phase-02.md`
