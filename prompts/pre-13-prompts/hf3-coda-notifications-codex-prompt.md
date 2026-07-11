# Codex Prompt — Step 10R-HF3 Coda: Localized Notifications + Hash Backfill

Work in the current local clone of `studioycm/PodText`.

ONE small coda run on the completed HF3 hotfix. It closes the last gap found in
the Fable audit that the HF3 run was not asked to fix — all ten import/export
completion-notification bodies are hardcoded ENGLISH in a Hebrew-first admin —
and it backfills the HF3 completion commit hash per the standing rule. Standing
runner rules apply: full sequential quality gate incl. `git diff --check`, no
push unless asked, no `filacheck --fix`, fixture-owned tests, en+he translations.

## Preflight

```bash
git status --short --branch
git log --oneline -4
```

Confirm clean tree and that the HF3 completion commit `8d24ce8` is in history
(HEAD may be it, or the committed copy of this prompt file directly above it).
Stop on unexpected dirt or if `8d24ce8` is absent.

## Audit evidence

Every exporter and every importer overrides `getCompletedNotificationBody` with
the stock Filament English sentence ("Your content group export has completed
and X rows exported." / "...import has completed and X rows imported.") plus the
English failed-rows sentence — ten near-identical copies, verified in
ContentGroupExporter, ContentItemExporter, and ContentGroupImporter; the rest
follow the same pattern. These bodies are what the admin actually reads in the
notification bell after the queue fix, so they are the user-visible surface of
HF3.

## Job 1 — localize the ten bodies (framework-native first)

1. Check vendor FIRST: does the installed Filament v5.6 ship Hebrew translations
   for the default import/export completed-notification bodies (the
   `filament-actions` lang files, `he` locale)? Cite the exact vendor lang file
   and keys in the handoff.
2. If vendor he coverage is complete for both import and export bodies including
   the failed-rows sentence: DELETE all ten overrides and let Filament's
   localized default speak — framework-native wins; the current overrides are
   copies of the default English text, so nothing is lost.
3. If vendor coverage is missing or partial: implement ONE shared translated
   body — exporter side in the `TracksExportLifecycle` concern, importer side in
   `ConfiguresContentImports` — using app translation keys (en+he) with
   `trans_choice` for row plurals, covering the failed-rows sentence, and delete
   the ten copies.
4. Either way the per-exporter/importer classes end up WITHOUT their own body
   overrides.

## Job 2 — test

Under the `he` locale, assert the completed-notification body for one exporter
and one importer is Hebrew (not the English sentence), including a variant with
failed rows. Optional, only if cheap: add a bounded query-count assertion around
the `transcriber_reference_keys` export column to lock in the loaded-collection
fix from the HF3 run.

## Job 3 — docs touch-ups on the HF3 record

- `docs/phase-02/public-front-v2-step10r-hf3-handoff.md`:
  - Backfill the HF3 completion commit hash `8d24ce8` into `## Commit hash` and
    into the `Commits` section (both currently say "final hash reported after
    local commit creation"), and add the same hash wherever the ledger row and
    `current-project-state.md` reference the completion half by message only.
  - Fix the `Verification` section wording: it currently lists the gate
    commands without outcomes — state that each passed in the HF3 run
    (handoffs record outcomes, not intentions).
  - Add three short notes: (a) retry semantics — Filament import/export jobs
    define `retryUntil` (+1h), which takes precedence over the supervisor's
    `tries => 1`, so failing chunk jobs retry with backoff [30, 120, 300] for up
    to an hour; (b) the redis queue connection has `after_commit => false` — any
    FUTURE dispatch inside a DB transaction races the worker and relies on
    `deleteWhenMissingModels`; (c) known round-trip asymmetry handed to the
    planned IE-1 step: the item exporter writes ALL `contentTags` including
    disabled ones while the importer resolves only enabled tags, so round-trips
    silently drop disabled tags.
  - Append a `## Coda` subsection: what this run changed (localized
    notifications), its commit message, hash to be backfilled by the next run.
- Ledger: extend the existing HF3 row with the coda commit note — do NOT add a
  new row.
- `current-project-state.md`: one line — HF3 coda localized import/export
  completion notifications.

## Out of scope

Any exporter/importer behavior beyond notification text; disabled-tags behavior
(IE-1); tracer changes; images/media track; Importer Workbench.

Commit: `fix: localize import export completion notifications`

End with exactly:

```text
Public Front v2 hotfix HF3 coda is complete. Waiting for Yoni review before continuing.
```
