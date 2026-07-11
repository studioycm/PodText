# Codex Prompt — Step 10R-HF3: Imports-Exports Queue Hotfix (adopt + complete)

Work in the current local clone of `studioycm/PodText`.

## What this run is

A recent session fixed a production-grade bug from a manual ad-hoc prompt, OUTSIDE
the mini-step ledger: Horizon was only watching the `default` queue while every
Filament importer and exporter dispatches to `imports-exports`, and once the queue
was consumed, export row processing failed under `Model::preventLazyLoading()`
because exporter columns lazy-load relations. That work was committed and pushed by
Yoni as `7d80c99`. IMPORTANT: `7d80c99` carries a `feat:` commit message because it
was committed manually — treat it as the FIRST HALF of this hotfix, not as a
completed feature step, and do not assume anything beyond its diff exists.

This run ADOPTS that commit retroactively as `Step 10R-HF3`, COMPLETES the fix
across the whole bug class (the pushed commit fixed one exporter of four affected),
and writes the missing documentation. Standing runner rules apply: full sequential
quality gate incl. `git diff --check`, no push unless asked, no `filacheck --fix`,
fixture-owned tests. The audit evidence below (verified at file:line on the pushed
code by Fable) replaces a separate research doc for this hotfix completion; no
separate research/plan docs are required. The handoff ends with `## Commit hash`
and a numbered `## Local Front Check Report`.

## Preflight

```bash
git status --short --branch
git log --oneline -3
```

Confirm clean tree and HEAD at `7d80c99`. Stop on unexpected dirt.

## Audit evidence (verified — build on it, re-verify only if contradicted)

- `Model::preventLazyLoading(! isProduction())` is active (AppServiceProvider:58),
  so every exporter whose column closures touch unloaded relations fails EVERY row
  locally — exactly what produced the 0/6-rows export before `7d80c99`.
- `ContentItemExporter` has NO `modifyQuery` and four lazy relation accesses in
  `->state()` closures: `contentGroup?->reference_key` (line 26),
  `categoryPaths($record->categories)` (96), `$record->contentTags` (99),
  `featuredTranscription?->reference_key` (104). An episode export still fails
  every row locally today.
- `TranscriptionExporter` has NO `modifyQuery`: `contentItem?->reference_key`
  (line 26) is a lazy access (throws locally); `primaryTranscriber()` /
  `transcriberNames()` fall back to explicit per-row author queries (no exception,
  but N+1 per row).
- `CategoryExporter` line 33 (`parent_path`) does bare `$record->parent`; it only
  survives when the `path` column (line 24, which `loadMissing`s) is enabled and
  runs first — deselecting the path column in the export modal makes parent_path
  throw.
- `ConfiguresContentImports::categoryPath()` is a byte-duplicate of the concern
  method fixed in `7d80c99` and still bare-walks `->parent` chains.
- The tracer (`ImportExportQueueTracer`) decodes the full job payload BEFORE the
  cheap queue check on BOTH paths: `traceQueuedEvent` calls `decodePayload()` and
  `traceWorkerEvent` calls `jobPayload()` before `shouldTrace()` — every
  default-queue job (incl. the two Playwright snapshot jobs per settings save)
  pays a JSON decode per listened event.
- `ContentGroupExporter::getJobQueue()` logs a "dispatch queue resolved"
  breadcrumb — redundant with the tracer's `queueing`/`queued` events, and a side
  effect inside a getter.
- The lifecycle-logging block (named batch, Horizon tags, notification breadcrumb,
  counters log) lives ONLY in ContentGroupExporter (~60 lines) — copy-pasting it
  into four more exporters is the wrong move.

## Job 1 — exporter sweep (the same bug class, three exporters)

- `ContentItemExporter`: add `modifyQuery` eager-loading `contentGroup`,
  `categories.parent`, `contentTags`, `featuredTranscription`.
- `TranscriptionExporter`: add `modifyQuery` eager-loading `contentItem` and the
  transcriber `authors` relation (plus the legacy `author` fallback relation if
  `transcriberNames()` reads it) so `primaryTranscriber()`/`transcriberNames()`
  hit loaded relations instead of per-row queries.
- `CategoryExporter`: add `modifyQuery` eager-loading `parent` (the loadMissing
  walk from `7d80c99` covers deeper ancestors), removing the parent_path
  dependence on column order.
- `AuthorExporter` needs nothing (no relation access) — state that in the handoff.
- Fix the duplicated `ConfiguresContentImports::categoryPath()` the same way as
  the export concern (loadMissing at each level), or delete it if nothing reaches
  it — check reachability first and record the answer.

## Job 2 — shared lifecycle concern instead of copy-paste

Extract ContentGroupExporter's lifecycle additions into ONE shared exporter
concern (e.g. `app/Filament/Exports/Concerns/TracksExportLifecycle.php`): named
batch (`{kebab exporter}-export-{id}`), Horizon tags (`filament-export` +
per-exporter tag), `modifyCompletedNotification` breadcrumb, and the counters log
helper writing to the `import_export` channel. Apply it to ALL FIVE exporters.
While extracting, DROP the `getJobQueue()` side-effect log — the tracer's
queueing/queued events already record dispatch; getters should not log.
ContentGroupExporter's observable behavior (queue, batch name, tags, notification)
must stay identical — the `7d80c99` breadcrumbs test keeps passing unchanged
except for any class/trait moves.

## Job 3 — tracer ordering

Reorder `shouldTrace` so the queue-name check happens BEFORE any payload
decode/fetch on both the dispatch path and the worker path; decode the payload
only when the queue check misses and the class-prefix fallback is actually needed,
and when tracing proceeds. Add one small test proving filtering: a
`default`-queue non-Filament job event writes nothing to the `import_export`
channel while an `imports-exports` event writes (construct the queue event
objects directly or use `Log::shouldReceive`/spy on the channel).

## Tests

Per swept exporter, mirror the `7d80c99` eager-load regression test (reuse the
existing `exportRecord()` helper + the `Model::preventLazyLoading()` force-on /
finally-restore pattern): a queued-shape record with real relations exports its
relation-derived columns successfully under strict mode — items with a nested
category (depth >= 3 once, to prove the walk), enabled tags, group reference and a
featured transcription; transcriptions with content item + transcribers;
categories exporting `parent_path` WITHOUT the `path` column in the column map.
Plus the tracer filtering test (Job 3) and the existing suite green. Full
sequential gate.

## Docs and handoff (the retrospective record — the missing half of the hotfix)

- Ledger: insert a `Step 10R-HF3 - Imports-exports queue and export row loading
  hotfix` row after the latest completed row (WB1), noting it was adopted
  retroactively: first half shipped in `7d80c99` from a manual fix session, second
  half is this run's commit.
- `current-project-state.md`: HF3 entry — the `imports-exports` queue was never in
  Horizon's supervisor config anywhere (production included), so every Filament
  import/export sat unconsumed in Redis; export row processing lazy-loaded
  relations and failed all rows locally under preventLazyLoading; tracer +
  `import_export` daily log channel added; all five exporters now eager-load their
  column relations.
- `docs/phase-02/public-front-v2-step10r-hf3-handoff.md`: root causes, BOTH commit
  hashes, and the deploy notes — clear the stale production backlog with
  `php artisan queue:clear redis --queue=imports-exports` during the deploy window
  (stale jobs insta-fail anyway: dispatch-time `retryUntil` of +1h has long
  passed, so Laravel fails them before execution — clearing avoids failed-job
  spam and confusing notifications); `horizon:terminate` in the deploy script
  loads the new supervisor config; re-run any old stuck exports for valid files.
  Include `## Commit hash` and a numbered `## Local Front Check Report` (run one
  export per exporter from the admin — all rows successful + notification
  received; one import round-trip; Horizon dashboard shows imports-exports
  consuming; breadcrumbs appear in storage/logs/import-export-*.log; a settings
  save with snapshots writes NOTHING to the import_export log).
- `ai-development-lessons`: three entries — (1) a queue name used by jobs must
  appear in Horizon's supervisor config and `waits`, and the config test now
  guards it; (2) ad-hoc fix sessions must still end in ledger/docs adoption — an
  unnamed pushed fix is invisible to the project record until adopted (this run is
  the correction); (3) known order-dependent flake: `PublicFrontIconRegistryTest`
  'normalizes saved icon aliases' failed only in a full-suite run and passed alone
  and on rerun — suspected static/memoized registry or render-context state across
  tests; recorded, NOT fixed in this run.

## Out of scope

Fixing the icon-registry flake; importer-side lifecycle logging beyond what the
tracer already captures; Horizon supervisor topology changes (single supervisor
with both queues stands); Importer Workbench; images/media track; any UI work.

Commit: `fix: complete imports-exports hotfix across exporters with shared lifecycle tracing`

End with exactly:

```text
Public Front v2 hotfix HF3 is complete. Waiting for Yoni review before continuing.
```
