# PodText Importer Workbench Plan (WB Track, v1)

Date: 09/07/2026. Owner: Yoni. This is a NEW parallel work track ("Importer Workbench",
steps WB1-WB7) alongside the Public Front v2 queue. The central ledger remains the
per-run selector; this plan is the WB track's detailed spec.

## Purpose

A dedicated custom import studio: recurring synchronization from the production Google
Sheet (plus manual sources) into PodText — podcasts, episodes, transcriptions,
transcribers — through a Colab-style pipeline of configurable steps ("cells") with
staged datasets, resolution dictionaries, format-profile transcript transformation,
diff/apply with a change journal, and conflict-guarded rollback.

## Decisions (D-WB1..D-WB16) — approved by Yoni

- D-WB1 Builder UX: Colab-style step cells (no JS graph library). Multiple cells may be
  expanded at once, with expand-all / collapse-all controls and a per-card
  "close all others" action.
- D-WB2 Progressive disclosure everywhere: collapsed cards show one summary line
  (step #, tool icon+name, status chip, rows in→out); config fields appear only when
  their prerequisites are chosen (`live()` + `visible()` chains); advanced options sit
  in a collapsed section; the "add step" tool picker lists only tools whose input ports
  match datasets that exist upstream.
- D-WB3 Drive auth: BOTH connection auth types from day one — `service_account`
  (share sheet/folder with the SA email) and `oauth` (Socialite, offline refresh
  tokens). Credentials encrypted at rest (encrypted casts), never logged, never in
  tracked files.
- D-WB4 Providers: `google_drive`, `spotify` (client-credentials metadata fetch),
  `manual` (file upload / pasted lists as source steps).
- D-WB5 Rollback: per-row before/after change journal written on Apply; rollback
  reverse-replays in a transaction with a conflict guard (refuse/warn when a record
  changed after the run touched it).
- D-WB6 Recurring sync is the design center: natural-key upserts, delta detection
  (new/changed rows since last run), and saved reusable runs named **"import recipe"**
  (Hebrew: "תבנית ייבוא" or Yoni's preferred label via translations).
- D-WB7 No Filament import framework anywhere in the workbench: fully custom pipeline
  on Filament pages with Livewire/Alpine. The existing Prompt-10 native
  importers/exporters STAY UNTOUCHED for resource-dedicated simple import/export.
  Shared domain rules (portable identifiers, no silent taxonomy creation, formula
  escaping on any export) are extracted into support classes both paths use.
  RECORDED GUIDELINE AMENDMENT: the old "no custom import wizard" rule is superseded
  for the workbench by Yoni; its security clauses remain absolute.
- D-WB8 Remote media exception (recorded amendment to "no remote fetch during
  imports"): media/doc fetching happens only inside explicit admin-triggered import-run
  steps, from allowlisted providers, queued via Horizon, never during public rendering.
- D-WB9 Transcript format profiles: `transcript_format_profiles` — named profiles,
  each = detection signatures + transformation rules (Google Doc structure → PodText
  Markdown). Auto-detect with confidence scoring or forced profile per step; unmatched
  docs are flagged with a sample viewer where a new profile can be defined; profiles
  are managed/extended in importer settings. Default transformation PRESERVES
  everything (openers/closers included); stripping/cleanup exists only as optional
  modifier steps.
- D-WB10 Publication rule for the on-air sheet: tab membership = published (podcast +
  episode + transcription) with exceptions `4. מוכן להעלאה` → draft and
  `תקלה בהעלאה` → published-but-flagged. The original status is preserved as staged
  metadata + journal data, never discarded. Mapping UI = default outcome + per-value
  overrides.
- D-WB11 Transcriber Authors are `{name, unique slug}` only — taxonomy-style; the
  people classifier verdicts are person / ignore / note, persisted in the dictionary.
- D-WB12 v1 source scope: ONLY the `פרקים שעלו לאוויר` tab. Other tabs are ignored for
  now (no podcast-master seed, no censored/embed tabs). The podcast dictionary is built
  interactively; the resolver review UI therefore MUST confirm by group (one click per
  podcast prefix), never per row.
- D-WB13 Natural keys: episodes ↔ `spotify_id` (fallback podcast+title for the 3 rows
  without it, via `content_items.external_id`); transcriptions ↔ Google Doc ID;
  podcasts ↔ dictionary-resolved name. Dictionaries double as the permanent
  external↔internal key map.
- D-WB14 Smart updates: Apply supports per-field update policies —
  `always_overwrite | fill_if_empty | never` — so re-syncs update safely without
  clobbering local edits.
- D-WB15 Caching/infra reuse: any WB cache aligns with `PublicFrontConfigCache`
  conventions (versioned keys, settings-migration watermark where config-derived);
  WB admin UI reuses `IconSelect`/`PublicFrontIconRegistry` (V1b) and the V1a
  FileUpload constraint patterns.
- D-WB16 Gate and interleave: the WB track OPENS ONLY AFTER Step 10R-S1 (settings
  import/export) is complete. WB runs then interleave with P2/P3/AX/SL at Yoni's
  per-run choice; the main-queue guardrails (AX1 before SL1, etc.) are unaffected.

## Verified sheet facts (probe basis)

Workbook "Podtext transcription management", 11 tabs. Target tab `פרקים שעלו לאוויר`:
row 1 = counts/links, row 2 = Hebrew headers (21 columns), 3,423 data rows.
`raw_link` transcript Google Docs: 3,423/3,423 (3,330 with parseable doc IDs).
`spotify_id`: 3,420. Upload dates 100% (17/07/2023 → 2026). Transcriber column 100%,
282 distinct people (top: הילית בירנבוים מדבדייב 310, מיכל כץ 271, מיכל שקד 140).
Statuses (7 finite values): `5. באוויר!` 2,090 · `3. בקריאה אחרונה` 1,171 ·
`מעודכן!` 130 · `4. מוכן להעלאה` 25 · `מוכן לעדכון באתר` 5 · `תקלה בהעלאה` 1 ·
`כתוביות מוכנות` 1. Naive " - " split yields 579 podcast prefixes (359 singletons) —
splitting must be dictionary-driven longest-prefix. `מזהה פרק חלק א/ב` = old Wix
split-post ids (length limit); metadata only, no redirects needed.
Row-1 counters can be parsed as a free audit cross-check against staged counts.

Format probe: a stratified 20-doc sample (top-8 podcasts × earliest/latest year +
singletons) is prepared (`probe-sample.json`). WB1 ships an in-app
`importer:probe-formats` command that fetches samples through the app's own connection
and writes the findings doc that seeds WB4's initial profiles.

## Architecture

Tables: `import_connections`, `import_runs`, `import_steps` (tool key + validated
config JSON + status/stats/log), `import_datasets` + `import_dataset_rows` (staged
rows: payload JSON, row status, messages), `import_changes` (journal), 
`import_resolutions` (dictionaries), `transcript_format_profiles`, `import_recipes`.
All MySQL+SQLite compatible.

Execution: each tool = a `StepHandler` class declaring input ports, config schema
(finite tokens, validator-normalized), output ports, `handle()`. Steps run as queued
Horizon jobs; "run all" = chained; doc fetching runs throttled resumable batches
(~50-100/chunk, backoff, per-row checkpoint) — 3,423 docs is an hours-long batch by
design. Re-running a step marks downstream datasets stale. Dataset previews paginate;
diff views summarize by change type. Retention: prune old runs' datasets, keep the
journal forever.

UI: admin nav group "ייבוא" (after Settings): Runs, Run Builder, Importer Settings
(connections + format profiles + recipes). Heavy interactions (resolver review, audit
panel) are dedicated pages launched from step cards. All labels en+he, RTL-safe.

## WB steps

| Step | Scope | Commit |
|---|---|---|
| WB1 | Connections foundation: `import_connections` (encrypted), GD service-account + OAuth, Spotify client, manual placeholder; Importer nav group + settings page with test buttons and per-auth-type progressive disclosure; Drive/Sheets client services (list tabs, read range); `importer:probe-formats` command + findings doc; Google Cloud setup doc (SA + OAuth consent) in handoff. NEW DEPENDENCIES APPROVED FOR WB1 ONLY: `google/apiclient`, `laravel/socialite`. | `feat: add importer connections foundation` |
| WB2 | Studio skeleton: run/step/dataset schema + recipes; Runs list + Run Builder cells UI (multi-expand + expand/collapse-all + close-others, D-WB1/2); queued execution with status polling; tools: Sheet source (connection→spreadsheet→tab→header_row/data_start progressive config), manual CSV/paste source, column mapper (auto-map headers). | `feat: add importer studio pipeline skeleton` |
| WB3 | Resolution layer: dictionaries, longest-prefix splitter tool, people classifier (person/ignore/note), group-wise bulk review page, orphan candidates listing. | `feat: add import resolution dictionaries and review` |
| WB4 | Transcript fetch + format profiles: throttled resumable doc fetcher; profile registry + auto-detection + transformer (preserve-all default); profile management UI in importer settings; validator step. | `feat: add transcript fetch and format profiles` |
| WB5 | Diff + Apply + rollback (MILESTONE 1): natural-key upserts for podcasts/episodes/transcriptions; publication rule default+exceptions (D-WB10); per-field update policies (D-WB14); change journal; conflict-guarded rollback UI. Proof: one podcast's episodes live and reversible. | `feat: apply imports with journal and rollback` |
| WB6 | Audit + recurring sync: orphan finder both directions, row-1 counters cross-check, delta detection since last recipe run, dataset retention pruning. | `feat: add import audit and delta sync` |
| WB7 | Enrichment: Spotify links-list source → draft episodes; Drive images-folder media fetch (match via resolver, queued download to public disk); optional modifier nodes (e.g. opener/closer stripper). | `feat: add spotify and media import sources` |

Every WB run follows house process: Boost + FilamentExamples MCP research (anchors:
custom import wizard/column-mapping starters on filamentexamples.com, LaravelDaily
custom-import tutorials), implementation plan doc, focused tests + full gate
(incl. `git diff --check` and the bounded public harness), ledger row, current-state,
handoff ending with `## Commit hash` and `## Local Front Check Report` sections.

## Stop conditions

- Stop if the WB gate (S1 complete) is not satisfied.
- Stop if credentials would land in tracked files or logs.
- Stop if a step would fetch remote content outside an explicit queued import-run step.
- Stop if Apply would create categories/tags/podcasts silently without a dictionary or
  user decision.
- Stop if any workbench surface would reintroduce Filament ImportAction/Importer
  classes (D-WB7) or break the native importers.
- Stop if dataset/journal writes would run unbatched per-row queries at 3.4k scale
  without chunking.
