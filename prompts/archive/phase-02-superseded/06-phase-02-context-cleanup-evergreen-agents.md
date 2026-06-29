# Prompt 06R — Repository Context Cleanup and Phase 02 Planning Reset

You are working inside the current PhpStorm project repository for PodText.

This task cleans the AI context before implementation continues.

It must remove or archive stale Phase 0 / Phase 1 / old Phase 02 planning context, replace `AGENTS.md` with an evergreen version, and regenerate the active Phase 02 planning pack using the current tooling:

- Laravel Boost
- Filament Blueprint
- FilaCheck and FilaCheck Pro
- configured `filament-examples` MCP server

This is a **documentation, guideline, blueprint, and prompt cleanup task only**.

Do not implement application features.

---

## 1. Why this cleanup is needed

The current `AGENTS.md` still contains old Bootstrap Slice 0 language, including:

- current objective: “Implement only Bootstrap Slice 0”;
- stale scope exclusions such as categories, tags, timestamp parsing, URL metadata extraction, synchronized players, and transcript studio;
- stale `.ai/bootstrap-slice-0` rules injected into the Boost guidelines block;
- generated FilaCheck instructions that use `--fix` / `--fix --dirty` automatically.

Those rules conflict with the current Phase 02 roadmap.

`AGENTS.md` must become evergreen. Phase-specific details must live in prompts, docs, blueprints, and archived planning files, not in `AGENTS.md`.

---

## 2. Non-negotiable rules

- Work sequentially.
- Do not use worktrees.
- Do not launch parallel agents.
- Do not push or create a remote.
- Do not implement application features.
- Do not install packages unless the user explicitly asks in a separate implementation prompt.
- Do not run migrations.
- Do not edit application code except if absolutely necessary to remove generated docs-only references; prefer docs-only changes.
- Do not write tokens, secrets, FilaCheck Pro license data, MCP headers, Composer auth data, or local private paths into tracked files.
- Leave all changes uncommitted for human review unless explicitly told to commit.

---

## 3. Read and inspect first

Read:

- current `AGENTS.md`;
- `composer.json`;
- `composer.lock`;
- all files under `.ai/`;
- all files under `docs/`;
- all files under `prompts/`;
- all generated Phase 02 docs/guidelines/prompts;
- current models, migrations, panels, Resources, Pages, Livewire components, Blade views, tests.

Run:

```bash
git status --short --branch
git log --oneline --decorate -10
composer show laravel/boost filament/filament filament/blueprint livewire/livewire laraveldaily/filacheck
vendor/bin/filacheck --detailed
```

If FilaCheck Pro rules are not visible, report it. Do not attempt to activate licenses or write license data.

---

## 4. Replace `AGENTS.md` with evergreen instructions

Rewrite `AGENTS.md` so it is stable across phases.

Rules for the new `AGENTS.md`:

- No “current objective.”
- No “Bootstrap Slice 0.”
- No “Phase 02” as a scope directive.
- No one-time feature exclusions.
- No stale “do not add categories/tags/timestamp parsing” restrictions.
- No generated auto-fix instruction that tells agents to run `filacheck --fix` automatically.
- It may mention the active prompt, blueprints, and docs decide scope.
- It may define stable architecture, tooling, security, quality gates, domain names, and coding conventions.
- It may reference Boost, Blueprint, FilaCheck, FilamentExamples MCP, Pest, Pint, and build checks.
- It should say archived docs/guidelines/prompts are historical and not active instructions.

Use this structure:

```md
# Repository Instructions for Codex

## Purpose
## Project context
## Instruction priority
## Documentation lifecycle
## Read before changing code
## Sequential execution
## Secret and local configuration safety
## Tooling expectations
## Domain terminology
## Core content rules
## Security rules
## Architecture boundaries
## Filament conventions
## Public UI conventions
## Localization and RTL
## Testing
## Quality gates
## Final report
```

---

## 5. Clean `.ai/guidelines`

Audit every active file under `.ai/`.

Classify files into:

1. evergreen active guidelines;
2. feature/domain guidelines that should remain active but be renamed to non-phase names;
3. obsolete one-time guidelines;
4. generated package guidelines that should remain but be overridden by project-level safety rules.

Required cleanup:

- Remove/archive any `bootstrap-slice-0` guideline from active `.ai/guidelines`.
- Remove/archive old Phase 0 and Phase 1-only guidelines that are no longer true.
- Rename active `phase-02-*` guideline files into durable names where possible, for example:
  - `transcriptions.md`
  - `public-panel.md`
  - `search-filters.md`
  - `taxonomy-tags.md`
  - `media-embeds.md`
  - `import-export.md`
  - `settings-dashboard.md`
  - `viewer-studio.md`
  - `tooling-quality.md`
- If a guideline is only temporary planning material, move it to `docs/archive/ai-guidelines/`.
- Add a short archive note for every moved file.
- Ensure no active guideline contradicts the current roadmap.

Do not leave both old and renamed versions active.

---

## 6. Clean docs

Audit `docs/`.

Classify docs into:

1. evergreen product/project docs;
2. active Phase 02 docs/specs/blueprints;
3. completed historical Phase 0/Phase 1 docs;
4. obsolete or contradicted docs;
5. scratch/research notes.

Required cleanup:

- Move completed Phase 0 / Bootstrap Slice 0 docs to `docs/archive/bootstrap-slice-0/`.
- Move completed Phase 1 docs to `docs/archive/phase-01/`.
- Move obsolete prior Phase 02 drafts to `docs/archive/phase-02-superseded/`.
- Keep active Phase 02 docs under `docs/phase-02/`.
- Keep active FilamentExamples research under `docs/research/`.
- Create or update `docs/README.md` that explains which docs are active and which archives are historical.
- Create or update `docs/archive/README.md` that says archived files are not active instructions.

Do not delete historical docs unless they contain secrets. Prefer archiving.

If an existing root doc such as `docs/project-phases.md`, `docs/import-export-spec.md`, or `docs/architecture-decisions.md` is stale, either rewrite it as evergreen or move it to archive and replace it with a pointer to active docs.

---

## 7. Clean prompts

Audit `prompts/`.

Classify prompts into:

1. completed historical prompts;
2. active next prompts;
3. superseded Phase 02 drafts;
4. one-time bootstrap prompts;
5. obsolete prompts that should not be run.

Required cleanup:

- Move completed bootstrap/Phase 0 prompts to `docs/archive/prompts/bootstrap-slice-0/` or `prompts/archive/bootstrap-slice-0/`.
- Move completed Phase 1 prompts to `docs/archive/prompts/phase-01/` or `prompts/archive/phase-01/`.
- Move superseded Phase 02 prompt drafts to `prompts/archive/phase-02-superseded/`.
- Keep only the current next active prompt sequence in `prompts/`.
- Add `prompts/README.md` explaining which prompt should run next and which directories are historical archives.

Do not delete prompt history unless a file contains secrets.

---

## 8. Rebuild Phase 02 planning pack

After cleaning old context, regenerate active Phase 02 planning files using:

- Laravel Boost;
- Filament Blueprint;
- FilaCheck/FilaCheck Pro;
- FilamentExamples MCP deeper research.

The current active Phase 02 order must be:

1. reset research/specs/guidelines/prompts/blueprints only;
2. transcriptions model revision;
3. categories, Spatie tags, item pinning, settings, and media field foundation;
4. admin management for transcriptions/categories/tags/pinning/settings/media fields;
5. import/export for finalized Phase 02 schema;
6. public homepage/search/category/tag landing pages;
7. public item page, safe media player rendering, transcription tabs, timestamp parser, viewer hide/show preferences;
8. editorial dashboard metrics;
9. future sync viewer and transcription studio plan only;
10. Filament Blueprint security audit after implementation.

The active files must be self-consistent.

---

## 9. Boost and Blueprint handling

Use Laravel Boost tools where available.

Use Filament Blueprint to create concrete implementation blueprints under:

```text
docs/phase-02/blueprints/
```

If running `php artisan boost:update --discover` would overwrite custom files or re-inject stale archived guidelines, do not run it. Instead, document the manual action needed.

If safe to run, run it only after stale active `.ai/guidelines` files are removed or renamed.

After any Boost-generated context update, re-check `AGENTS.md` to ensure old archived guidelines were not re-injected.

---

## 10. FilamentExamples MCP deep research

Before regenerating the research file, prove the MCP can do deep research.

Use the configured `filament-examples` MCP server.

Do not write the token.

Required:

1. Use the search tool.
2. Fetch/read/open at least one example beyond search summary.
3. Record the exact MCP tool names.
4. Record whether access was source-level, README-level, summary-only, or search-only.
5. If only search is available, stop and report that deeper MCP research is not available.

The research file must clearly separate:

- search-only findings;
- source/read/detail findings;
- official docs;
- inferred adaptation notes.

---

## 11. Regenerate active Phase 02 docs

Create or update:

```text
docs/phase-02/current-project-state.md
docs/phase-02/tooling-and-quality-gates.md
docs/phase-02/feature-map.md
docs/phase-02/answers-coverage-matrix.md
docs/phase-02/transcriptions-model-spec.md
docs/phase-02/taxonomy-tags-spec.md
docs/phase-02/search-and-filters-spec.md
docs/phase-02/media-embed-spec.md
docs/phase-02/public-panel-ux-spec.md
docs/phase-02/homepage-settings-spec.md
docs/phase-02/import-export-revision-spec.md
docs/phase-02/dashboard-metrics-spec.md
docs/phase-02/transcript-viewer-and-studio-future-plan.md
docs/research/filament-examples-phase-02.md
```

Create or update Blueprint-style files under:

```text
docs/phase-02/blueprints/
```

---

## 12. Regenerate active prompts

Regenerate the active prompt sequence after cleanup.

Keep only the current active sequence in `prompts/`, with historical versions archived.

Expected active sequence:

```text
06-phase-02-reset-research-blueprint-cleanup.md
07-phase-02-transcriptions-model-revision.md
08-phase-02-taxonomy-tags-pinning-settings-media-foundation.md
09-phase-02-admin-content-management.md
10-phase-02-import-export.md
11-phase-02-public-homepage-search.md
12-phase-02-media-embed-item-page-parser.md
13-phase-02-dashboard-metrics.md
14-phase-02-viewer-studio-future-plan.md
15-phase-02-filament-blueprint-security-audit.md
```

Every implementation prompt must reference:

- `AGENTS.md`;
- relevant specs;
- relevant blueprints;
- relevant evergreen guidelines;
- Boost docs search;
- FilaCheck quality gate.

Every implementation prompt must run:

```bash
php artisan test
vendor/bin/pint --test
vendor/bin/filacheck
npm run build
```

---

## 13. Required current semantics

The regenerated docs/prompts must preserve these decisions:

- Public results are `ContentItem` records.
- `Transcription` is a child of `ContentItem`.
- Pinning belongs to `ContentItem` only.
- “Latest transcriptions” means `ContentItem` ordered by effective/main transcription `published_at`.
- Featured transcription must belong to the same item.
- Draft/unpublished transcriptions are hidden publicly.
- Categories are custom hierarchical records.
- Tags use Spatie tags with type `content`.
- Enabled tags only are public.
- Media fields exist before import/export.
- Media embeds are URL-only and allowlisted.
- Public item page parses timestamps/speakers when present, with no sync.
- Future studio/sync remains planning-only.
- No Shield in this phase.
- No search logging now.
- Dashboards are editorial only.

---

## 14. Verification

Run:

```bash
git diff --check
git status --short
vendor/bin/filacheck --detailed
```

Do not run migrations.

If FilaCheck finds existing app-code issues, do not fix them in this docs cleanup unless they are caused by this task. Record them in `docs/phase-02/tooling-and-quality-gates.md`.

---

## 15. Final report

Return:

1. Files archived.
2. Files rewritten.
3. Active docs list.
4. Active prompt list.
5. Active guideline list.
6. Whether Boost was available.
7. Whether Blueprint guidance was used.
8. Whether FilamentExamples MCP deep access was proven.
9. FilaCheck/FilaCheck Pro status.
10. Any stale files intentionally left in place and why.
11. Current `git status`.

End with exactly:

```text
Repository AI context cleanup is ready for human review. No application features were implemented.
```
