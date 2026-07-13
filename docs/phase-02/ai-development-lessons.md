# Phase 02 AI Development Lessons

## Purpose

This file captures durable AI-development lessons from Phase 02 prompts and repairs. It is guidance for future prompt execution, not a progress log.

For current prompt completion/progress state, see `docs/phase-02/current-project-state.md`.

## Lessons from Prompt 07 transcriptions

- Verify the real repository and database state before coding. Prompt assumptions are not enough.
- Treat child `Transcription` records as the canonical transcript storage; do not reintroduce new writes to legacy `content_items.transcript_markdown`.
- Public results remain `ContentItem` records and must require a published group, published item, and effective/main published transcription.
- Tests should prove effective/main transcription resolution, draft hiding, same-item featured validation, and safe rendering behavior.

## Lessons from Prompt 08 taxonomy/settings/media foundation

- Keep categories hierarchical and tags flat through Spatie tags scoped to `content`.
- Preserve enabled-only public tag behavior; disabled tags are admin-only.
- Settings and homepage sections have separate responsibilities: settings provide global defaults, and sections define visible ordered homepage slices.
- Prompt 11 must consume `PublicContentSettings` and visible ordered `HomepageSection` records rather than leaving the foundation unused.
- Media storage remains URL and metadata based; safe rendering belongs to the owned public media component.

## Lessons from Prompt 09 admin management and repair

- Admin UI tests must prove forms, actions, relation managers, table behavior, redirects, and create/edit flows, not only Resource registration.
- Browser tests are useful for real visible UI regressions, especially when Filament page overrides can accidentally remove form content.
- Prefer item-scoped transcript editing through the `ContentItemResource` transcriptions relation manager while keeping standalone `TranscriptionResource` useful for global maintenance.
- Do not use broad form-tab overrides unless the installed Filament API and visible UI behavior are verified.

## Lessons from Prompt 10 import/export

- Preserve native Filament import/export behavior in later prompts unless an active blueprint explicitly requires a compatible change.
- Keep portable identifiers as the import/export boundary: reference keys, category paths, and typed tag slugs.
- `reference_key` values are opaque portable identifiers, not human-readable labels. Seeders, demo data, and import fixtures must use contract-valid `char(26)` ULID-shaped keys; descriptive strings can pass unnoticed on SQLite but fail on MySQL with column-length errors.
- Transcript imports write to `Transcription` records only.
- Missing categories, missing tags, wrong-type tags, and disabled-public tags should fail rows by default.
- `transcript_file` support remains deferred until a safe import package structure is specified and tested.
- Preserve formula-injection protection and native failed-row behavior.
- Any queue name returned by a Filament importer/exporter must appear in both Horizon supervisor queue config and Horizon `waits`. The import/export queue configuration test now guards `imports-exports` so native import/export jobs cannot sit unconsumed in Redis again.

## Cross-cutting implementation workflow lessons

- Blueprints are implementation contracts, not optional context.
- Start with local preflight: git status, recent commits, expected prior commits, and whether the next prompt has already started.
- Stop on unexpected app-code dirt before implementation unless the active user request explicitly resolves it.
- Use Laravel Boost and FilamentExamples when the prompt requires them or package behavior is uncertain, and report the exact tool access level.
- Final reports must classify meaningful blueprint requirements as implemented, already existed, deferred by blueprint, not applicable, or blocked.
- Docs-only tasks must stay docs-only.
- Do not run `vendor/bin/filacheck --fix` without explicit approval.
- If FilaCheck rewrites app or test files during a docs task, revert those app/test diffs immediately and keep only intended Markdown changes.
- Ad-hoc production-fix sessions still need ledger/current-state/handoff adoption. A pushed fix with an unnamed or misleading commit message is invisible to the project record until a follow-up run adopts it and records the remaining scope.

## Testing lessons

- Tests must prove behavior, not only class existence or static registration.
- Livewire and Filament tests should prove actions, forms, relation managers, tables, filters, imports, and exports.
- Public tests should prove visibility constraints, URL-backed state, sorting, filters, RTL markers where practical, and draft exclusion.
- Browser tests are appropriate when a workflow can pass component tests while failing visibly in a real page.
- Keep tests aligned with actual user workflows and existing package APIs.
- Any test file that can write, assert, prune, delete, or stream app storage paths must fake the relevant disk at file-level setup before helpers or tests touch storage. Per-test fakes are too easy to miss and can overwrite or delete real local artifacts when test IDs collide with real records.
- `phpunit.xml` environment entries can be too late for a Pest/Laravel stack when the invoking shell exports project DB variables. Force safe test env in the Pest bootstrap too, and keep the base TestCase canary that aborts unless `APP_ENV=testing`, `database.default=sqlite`, and SQLite database is `:memory:`.
- Test commands must run sequentially for this repository unless a prompt explicitly authorizes parallelism. Parallel or overlapping gates make DB/storage safety failures harder to attribute.
- Never run `migrate:fresh`, `db:wipe`, or seeders against the dev database. Destructive artisan commands belong to tests only, where the canary has already forced SQLite `:memory:`.
- Composite indexes on string columns must use explicit bounded lengths. Finite-token columns get small explicit lengths; with `utf8mb4`, each character can cost 4 bytes and InnoDB's key limit is 3072 bytes. SQLite tests cannot catch MySQL key-length violations, so review index byte math for every new string composite index.
- PHP language files silently keep the last duplicate key at runtime. Use a token-level duplicate-key scan for both `lang/en` and `lang/he` after translation edits, especially when moving or merging nested admin arrays.
- `User::canAccessPanel()` currently admits every authenticated user to the admin panel and is the single load-bearing admin-auth decision. This is acceptable only while the only account is Yoni's; before any non-admin account type exists, add an `is_admin` gate first.
- Known order-dependent flake: `PublicFrontIconRegistryTest` test `normalizes saved icon aliases` failed only in a full-suite run and passed alone and on rerun. Suspect static or memoized icon-registry/render-context state leaking across tests. This is recorded for future isolation work and was not fixed in Step 10R-HF3.

## Documentation/state-management lessons

- `docs/phase-02/current-project-state.md` is the single source of truth for rolling prompt progress.
- Update progress state before the final implementation commit.
- A run is complete only when its handoff exists as a committed repository file. Chat output alone is not a handoff.
- Final gate outcomes must be written into the handoff file after the gate passes and before the implementation commit. A result mentioned only in the session final is chat history, not a durable project record.
- Patch `feature-map.md`, `answers-coverage-matrix.md`, `prompts/README.md`, specs, blueprints, and guidelines only when stable requirements, ownership, or scope changed.
- Do not duplicate rolling status in prompt files, specs, blueprints, guidelines, or indexes.
- Prompt final reports are not a substitute for updating the current state document.
- "Run the full suite exactly once" means once green. A failed full-suite run does not satisfy the gate; after fixes and targeted verification, the full suite must be run again and every full run must be recorded.
- When a prompt pins final gate order, run cheap deterministic checks first and keep the full suite last. For NAV-style prompts this means the pre-gate requirement sweep, then Pint, FilaCheck, frontend build, and only then the full profiled suite.
- Treat the pre-gate requirements sweep as a real audit against the prompt's job and test lists, not a vague readiness note. Record each implemented/deferred/not-applicable item before starting the final gate.
- A green full-suite result belongs only to the final code state. Any code, test, translation, or documentation change after that green result re-enters the gate from Pint and requires another full-suite run, with every full run recorded.
- A Local Front Check Report is a numbered list of manual operator steps, separate from automated coverage notes.
- When a run introduces or changes an env-dependent config key, the same run must update `.env.example`, add a handoff deploy note, and flag the production env expectation. Do not rely on a fallback such as `APP_KEY` without documenting the intended production override.

## Deferred-item handling lessons

- Deferred items must name the owner prompt or future decision point.
- Preserve Prompt 10 import/export behavior during Prompt 11.
- Public homepage/search results are `ContentItem` records only.
- Prompt 12 owns public item page, media rendering, and parser/viewer behavior.
- Prompt 13 owns editorial dashboard widgets.
- Prompt 14 is planning only for future viewer/studio work.

## Tooling lessons

- Use installed package versions as the source of truth.
- Use Boost `application_info` early when available.
- Use Boost docs before code changes when Laravel, Filament, Livewire, or Pest behavior is uncertain.
- Use FilamentExamples before writing or revising Filament Resources, tables, filters, actions, widgets, imports, or exports when the prompt calls for it.
- Do not use one broad FilamentExamples query as the whole research pass. Split work into short topic batches, use higher limits such as 8 to 10 when accepted, inspect result names/snippets/paths, run a refined second pass, and record which examples influenced the implementation.
- When FilamentExamples exposes only `search_examples`, describe the access as search/snippet access and do not imply a separate source fetch occurred.
- Documentation-only validation should normally be `git diff --check` and `git status --short` unless the active prompt asks for more.
- On a single server, multiple Horizon masters that share the same `APP_NAME`
  also share the Redis prefix and queue namespace. After release renames,
  topology changes, or deploy-script edits, inspect each suspected master before
  any kill decision: use `ls -l /proc/<pid>/cwd` to identify the owning release
  path, and treat the process as ours only when its cwd is this app's release
  path and its environment shares this app's `APP_NAME`. Extra masters can
  belong to other tenant sites; killing them can take those sites down.

## How future prompts should use this file

- Read this file during preflight when a prompt touches Phase 02 workflows, public UI, admin UX, import/export, dashboard metrics, or prompt-state documentation.
- Apply the lessons as guardrails while following the active prompt and blueprint.
- Update this file only when a new durable lesson is discovered; do not add prompt completion notes here.
