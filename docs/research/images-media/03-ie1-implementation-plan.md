# IE-1 Implementation Plan - Relation Import Modes And Tag Export Scope

Date: 2026-07-13

## Guardrails

- Execute only `prompts/pre-13-prompts/import-relations-ie1-codex-prompt.md`.
- Do not make Composer changes.
- Do not run `filacheck --fix`.
- Use targeted tests while iterating.
- Final gate order is requirements sweep, `vendor/bin/pint --test`,
  `vendor/bin/filacheck`, `npm run build`, and full `php artisan test` last.
- If any final-gate command requires a code/doc change, re-enter from Pint and run
  the full ordered gate again.

## Implementation Steps

1. Add relation import mode support.
   - Extend `ConfiguresContentImports::getOptionsFormComponents()` with a
     translated `relation_mode` select.
   - Default to `replace` when the option is absent.
   - Add shared helpers for relation mode, blank relation state detection, and
     relation syncing.

2. Update category relation imports.
   - `ContentGroupImporter::category_paths`: blank state returns without changes.
   - `ContentItemImporter::category_paths`: blank state returns without changes.
   - `replace` uses sync.
   - `add_only` uses attach/sync-without-detaching semantics.

3. Update content tag imports.
   - Resolve content tags separately from enabled tags.
   - Missing or wrong-type tags still fail validation.
   - Disabled content tags are recorded as skipped warnings and are not attached.
   - `replace` replaces only enabled content-tag membership while preserving
     disabled content tags and unrelated tag types.
   - `add_only` attaches enabled tags without detaching existing tags.

4. Update transcription transcriber imports.
   - Treat all transcriber reference/name columns as one relation input surface.
   - Blank input on existing rows returns without syncing in both modes.
   - `replace` uses resolved transcribers as the complete set.
   - `add_only` appends missing transcribers while preserving existing order and
     legacy `author_id` compatibility.

5. Update content item tag export scope.
   - Add a translated exporter `tag_scope` option with `enabled_only` default and
     `all_tags` option.
   - Make `content_tag_slugs` read from `enabledContentTags` by default and
     `contentTags` when `all_tags` is selected.
   - Eager-load both relations in `modifyQuery()` to avoid N+1.

6. Apply Job 0 carried corrections.
   - Change Curator Glide token config to fall back to `APP_KEY`.
   - Add a minimal tracked `.env.example` entry with `CURATOR_GLIDE_TOKEN=`.
   - Add deploy/lessons documentation lines.
   - Mark the MP2 gate gap closed-as-documented.
   - Backfill TOOLS1 commit hash `a6d6408` in the current-state/handoff docs.

7. Add and update tests.
   - Extend existing import/export feature tests rather than creating isolated
     class-existence tests.
   - Cover relation matrices, skipped disabled tags, exporter options, N+1 guards,
     default relation mode, default export/import round trip, and Glide fallback.

8. Update required docs and handoff.
   - Update `docs/phase-02/current-project-state.md`.
   - Update `docs/phase-02/images-media-track-plan.md` IE-1 status/decisions.
   - Update the D-IMG/IE ledger in
     `docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md` if it is the
     active mini-step ledger for this track.
   - Add `docs/phase-02/import-relations-ie1-handoff.md` with gate outcomes before
     commit, `## Commit hash`, and a numbered `## Local Front Check Report`.

## Targeted Iteration Commands

- `php artisan test --compact tests/Feature/Phase02ImportExportTest.php`
- `php artisan test --compact tests/Feature/ImportExportTest.php`
- `php artisan test --compact tests/Feature/ImportExportQueueConfigurationTest.php`
- Narrow `--filter` runs are allowed while debugging.

## Final Gate

1. Requirements sweep: `git diff --check`, `git status --short`, no Composer
   changes, prompt requirement classification, and handoff gate placeholders ready.
2. `vendor/bin/pint --test`
3. `vendor/bin/filacheck`
4. `npm run build`
5. Full `php artisan test` last, once green on the final code state.

Every run and outcome will be recorded in
`docs/phase-02/import-relations-ie1-handoff.md` before the final commit.
