# Codex Master Prompt — Public Front v2 Post-M3 Multi-Transcription Rendering + Performance Continuation Runner

Work in the current local clone of `studioycm/PodText`.

This prompt is the authoritative continuation runner AFTER Step 10R-M3. It composes with
the final post-B3 multi-transcriber + card-template continuation prompt: every guardrail,
research rule, plan/handoff format, quality gate, ledger discipline, commit rule, and the
final-report format from that prompt still apply. This prompt adds:

1. verified current reality after M2/M3;
2. the multi-transcription/multi-transcriber product evaluation as planning input, with
   decided display defaults (D1-D8);
3. the performance/efficiency backlog (F1-F15) with verified post-M3 statuses and fixed
   step ownership;
4. three performance mini-steps (P1-P3) to insert into the ledger;
5. new Step 11 goals for promoting the local evaluation seed state.

Run this prompt repeatedly. Each run: preflight → select exactly the first pending
mini-step from the ledger → research (Boost + FilamentExamples MCP) → write the
implementation plan → implement only that mini-step → focused tests → full gate →
update ledger/current-state/handoff/backlog docs → commit → stop for Yoni/ChatGPT review.
Never batch mini-steps.

---

## Deployment reality (unchanged, authoritative)

- Production: Laravel Forge server with MySQL, Redis, Horizon.
- Local: Laravel Herd; local DB and test suite run on SQLite.
- All SQL (including the M3 aggregate subselects) must run on BOTH MySQL and SQLite;
  prefer Builder-composed subqueries; verify any raw fragment against both dialects.
- Caching must use one versioned key (no cache tags); production cache store is Redis,
  local/test may be database/file/array.
- Env changes (`SETTINGS_CACHE_ENABLED`, cache store) are deploy notes in handoffs,
  never `.env` edits.
- Horizon may run queued backfills (P3), but public rendering must never wait on a queue.

## Current reality to verify in preflight

- Step 10R-M1 complete: `800218a feat: add multi-transcriber relationship foundation`.
- Step 10R-M2 complete: `e813513 feat: replace episode authors with transcription transcribers`
  (`author_content_item` dropped; item-author relations removed).
- Step 10R-M3 complete: `825004c feat: add public transcription policy and aggregates`
  (`transcription_policy` settings; `PublicTranscriptionPolicy` / `PublicTranscriptionSelector`
  / `PublicTranscriptionAggregates`; pivot-backed contributor counts; item/group aggregate
  subselects wired into `PublicContentItemQueries::base()` and group queries).
- Ledger says next mini-step is Step 10R-M4. B4 paused until M1-M6 complete. C1 paused.
  Step 9F/10F, Step 11, Prompt 13 not started.
- LOCAL-ONLY untracked state exists and must not be assumed by tests:
    - seeded data: 8 episodes, 16 transcriptions (14 public), 6 episodes with multiple
      public transcriptions, 6 transcriptions with multiple transcribers, 1 draft,
      1 future-dated;
    - three episodes with multi-transcriber featured transcriptions:
      `/items/technology-in-hebrew/ai-tools-for-editors`,
      `/items/people-and-culture/local-culture-archive`,
      `/items/deep-talks/learning-through-conversation`;
    - local `transcription_policy` is `all_published` / `all_published` /
      `show_multiple_transcriptions_on_item_page: true` — NOT the shipped default
      (`featured_only`); do not commit these values;
    - DB-only card templates: `episode_transcription_focus` (active on podcast episode
      cards), `episode_transcribers_first`, `episode_compact_transcription_meta`,
      `contributor_counts_focus`, `transcriber_compact_counts`.
- Known current limitation this runner exists to fix: even under `all_published`,
  cards and pages still render only the effective/main transcription; grouped
  multi-transcription display does not exist yet.

If repository reality contradicts this, stop and report before code changes.

---

## Product display decisions D1-D8 (apply as defaults; Yoni may override in review)

These answer the multi-transcriber evaluation's eight audit questions. Apply them,
record them in `docs/phase-02/public-front-v2-transcription-display-decisions.md`
(create in the M4 run; update whenever a decision changes), and list any deviation
in the run's final report.

- **D1 (cards):** episode cards NEVER render full multi-transcription lists. Cards show
  the selected/effective transcription's title/transcribers/read time/word count/date,
  plus an optional "N transcriptions" count badge (template metadata part) when
  `count_mode = all_published` and N > 1.
- **D2 (item page):** the item page groups by transcription. Header shows effective
  transcription transcribers plus transcription-count metadata. The transcript viewer
  lists all published transcriptions as tabs when
  `show_multiple_transcriptions_on_item_page` is true (existing tab behavior), and each
  tab shows THAT transcription's ordered transcriber names. No merged-across-
  transcriptions transcriber list anywhere in M4; a merged mode is a possible future
  setting, not now.
- **D3 (contributor context):** contributor-context episode cards (directory preview,
  contributor page, top-transcriber preview) show only transcriptions involving the
  selected contributor: that contributor's transcription titles and those transcriptions'
  transcribers, not the global effective transcription when they differ.
- **D4 (podcast counts):** podcast/group cards default to public episode count + total
  read time. Transcription count and distinct transcriber count are available as
  template attributes, default-on for the podcast detail header, default-off on index
  cards. All counts follow `count_mode`.
- **D5 (all_published scope):** `all_published` affects counts (M3, done), the item-page
  viewer/tabs, and the D1 count badge. It does NOT turn cards, filters, or listings into
  multi-transcription lists. Filters match through all published transcriptions'
  transcribers in `all_published`, effective-only in `featured_only`.
- **D6 (per-surface settings):** keep minimal. One finite token
  `transcription_display: effective_only | effective_plus_count` on the surfaces that
  render item cards (`display_defaults`, `podcasts_page.group_page`,
  `contributors_page` preview/page), validator-normalized, defaulting to
  `effective_plus_count`. Item-page grouping stays governed by the existing
  `show_multiple_transcriptions_on_item_page`. Do not add per-surface policy modes.
- **D7 (templates and grouped data):** grouped transcription data in card templates is
  expressed ONLY through finite registered sources/attributes and, from M5 on, the
  `part_group` nested-part mechanism. Never raw HTML/Blade/classes in JSON.
- **D8 (labels/icons):** labels, icons, and grouped/nested rows wait for Step 10R-M5
  as planned. M4 must not partially implement them.

## Performance backlog F1-F15 (verified post-M3 statuses; ownership is fixed)

Verify each locally before acting; update statuses in
`docs/phase-02/public-front-v2-performance-efficiency-audit.md` (create in the M4 run
with this table, deployment reality, and a status column: open / resolved / scheduled).

| # | Finding (evidence) | Status after M3 | Owner |
|---|---|---|---|
| F1 | Settings cache disabled; full ~2,200-line validation every request | open | P1 |
| F2 | Latest sections force `max(50,…)` hydrated items, PHP-side pagination (`PublicDisplaySectionQueryResolver::resultLimit()`) | open | P2 |
| F3 | Transcript viewer: `publishedTranscriptions()` runs twice per request; full re-parse + per-segment `SafeMarkdownRenderer::toHtml()` every render (`content-item-transcript-viewer.blade.php`); `parsed_segments`/`word_count` unused | memoization → M4; derived segments → P3 | M4 + P3 |
| F4 | `TopTranscribersSection` runs ranked query twice (mount/normalize + render) | open | M4 |
| F5 | `Transcription::saved` runs `syncCompatibilityAuthorToTranscriberPivot()` unconditionally (`app/Models/Transcription.php` ~line 186) | open — NOT landed in M3 | M4 |
| F6 | Section resolver re-`find()`s category/tag/group targets twice per section despite eager loads (`PublicDisplaySectionResolver` targetLabel/viewMoreUrl) | open — NOT landed in M3 | M4 |
| F7 | Five filter-option queries every `ContentItemSearch` render incl. homepage | open | P2 |
| F8 | Lazy-load fallbacks in `effectiveTranscription()` / `publicTags()` / `primaryTranscriber()`; no `preventLazyLoading` guard | partially covered by M3 `PublicTranscriptionSelector::withPublicTranscriptionRelations()` — guard still missing | M4 |
| F9 | Contributor counts on `transcriptions.author_id` | RESOLVED in M3 (pivot-backed) | done |
| F10 | No podcast/group aggregates | query side RESOLVED in M3; rendering/template attributes open | M4 |
| F11 | Per-card `@php app(Presenter)->present()` re-resolves presenter + recomputes identical presentation per card; internal `authors` data key still legacy-named | open | M4 (hoist per grid, rename key) + B4 (options convergence) |
| F12 | `PublicFormModal::definition()` resolved in mount/submit/render | open | P2 |
| F13 | Duplicated grid class maps (item grid vs contributor item grid) | open | C2 |
| F14 | Ledger 9F-A note still says "after Step 10R-A/B/C" | open — fix in the M4 run's ledger update | M4 run |
| F15 | NEW: `base()` adds up to 4 correlated aggregate subselects to EVERY public item listing, consumed or not | open | M4 decides per-surface need; P2 makes them opt-in if unconsumed anywhere |

Query-count regression harness: never landed; it is now a REQUIRED M4 test deliverable.

## Ledger changes in the NEXT run (the M4 run's docs step)

1. Insert after Step 10R-M6, before Step 10R-B4:
    - `Step 10R-P1 - Validated public-front config caching`
    - `Step 10R-P2 - Public listing fetch-window and lazy options`
    - `Step 10R-P3 - Derived transcript segments and viewer economy`
2. Fix the 9F-A note to "Run only after Step 10R-M1 through M6, B4, and C2 are complete" (F14).
3. Add to the Step 11 row's notes: "Promote the local multi-transcription evaluation
   seed (episodes/transcriptions/transcriber distribution, the five card templates,
   and policy demo scenarios) into the tracked idempotent demo seeder."

---

## Step 10R-M4 — Public rendering, card templates, Livewire, Blade, aggregate attributes (AUGMENTED — next run)

Everything in the base prompt's M4 definition applies (rendering surfaces list, card
attribute expansion, "move logic out of Blade", no public Filament Tables). Additions
and precisions:

### Rendering per decisions
- Apply D1-D6 exactly. Item/episode cards: effective transcription transcribers via the
  M3 selector; count badge per D1/D6. Podcast detail + group cards: aggregate attributes
  per D4 (`content_group.total_reading_time` = ceil(total words/200) formatted in the
  presenter, `content_group.latest_transcription_date` day-first Asia/Jerusalem,
  `content_group.public_episode_count`, optional `content_group.transcriber_count` /
  `transcription_count`). Contributor contexts per D3. Item page header + viewer tabs
  per D2 (tabs show per-transcription ordered transcriber names).
- Expand card-template sources/attributes per the base prompt list
  (`content_item.transcribers`, `content_item.transcription_count`,
  `content_item.reading_time`, `transcription.transcribers`, `content_group.*`,
  `contributor.*`). The three seeded episode templates and two contributor templates
  must render correctly with the expanded sources — verify against the local settings,
  but tests create their own template fixtures.
- Rename the presenter's internal `authors` data key to transcriber-accurate naming
  (deferred from M3), keeping template compatibility mappings.

### Performance items owned by M4 (from the backlog)
- F5: guard the compat sync with `isDirty('author_id')` (plus cheap schema check);
  assert via query counts that a no-op save no longer syncs.
- F6: section resolver uses the section's eager-loaded `category`/`tag`/`contentGroup`
  (or one memoized find per section).
- F8: enable `Model::preventLazyLoading(! app()->isProduction())` in
  `AppServiceProvider::boot()`; fix every violation the suite exposes with eager loads
  (verify `withPublicTranscriptionRelations()` covers transcriber rendering on every
  surface, including contributor-context transcriptions with their `authors`).
- F4 + F3(memoization): `#[Computed]`/`once()` memoization for
  `TopTranscribersSection::contributors()` and
  `ContentItemTranscriptViewer::publishedTranscriptions()`.
- F11 (first half): resolve presenter + presentation once per grid in the Livewire
  layer and pass prepared card view-models to Blade; per-card Blade `@php` presenter
  calls are removed. Full `PublicContentCardOptions` convergence stays B4.
- F15: for each surface, either consume the aggregate selects or confirm they are
  needed; document any always-on-but-unconsumed select for P2 to make opt-in.

### Tests (in addition to the base M4 list)
- Every rendering assertion runs under BOTH `featured_only` and `all_published`
  (tests set policy explicitly; never rely on local DB settings).
- Multi-transcriber fixtures mirror the evaluation scenarios: episode with multiple
  public transcriptions, transcription with multiple ordered transcribers, draft and
  future-dated transcriptions stay invisible everywhere.
- Contributor-context card shows contributor-specific transcription titles/transcribers
  when they differ from the effective transcription (D3).
- Item page header/count badge and viewer tabs per D1/D2.
- Query-count regression harness (REQUIRED): bounded query counts
  (`expectsDatabaseQueryCount()` or DB::listen counter) for homepage sections render,
  /search render, podcast detail render, contributor directory render, and item page
  render; record baseline vs after in the handoff.
- No-op transcription save issues no pivot sync queries (F5).

Commit message: `feat: render public transcribers and transcription aggregates`

## Step 10R-M5 — grouped parts, labels, icons (AUGMENTED)

Base prompt M5 applies in full (label positions/alignment, finite icon registry,
`part_group` with inline/stacked/grid layouts, depth limit 1, admin Builder UX,
shared part partials). Additions:
- `part_group` is the D7 vehicle for grouped transcription rows on cards (e.g., one
  inline row: transcription title + transcriber badges + read time). Provide at least
  one default/seedable template demonstrating it against the evaluation data.
- Keep D1: even with grouped parts, cards render effective-transcription data plus
  counts — not per-transcription lists.
- Label/icon rendering must not regress the M4 query-count harness (assert).

Commit message: `feat: add card template grouped parts labels and icons`

## Step 10R-M6 — stabilization docs (AUGMENTED)

Base prompt M6 applies. Additions:
- Update `public-front-v2-performance-efficiency-audit.md` statuses; confirm F5/F6/F8/
  F9/F10/F14 resolved and P1-P3 correctly scheduled next.
- Fold final D1-D8 decisions (with any Yoni overrides) into the decisions doc and
  reference it from the M6 handoff.
- Confirm the evaluation-report scenarios are covered by tracked tests, not local data.
- Decide/record the C1 status (superseded or narrow cleanup).

Commit message: `docs: summarize multi-transcriber and card-template foundation`

## Step 10R-P1 / P2 / P3 (insert now, implement after M6, one per run)

- **P1 — Validated public-front config caching.** Cache the validated/normalized config
  behind the scoped context (versioned key `public_front.config.v1`; Redis in prod);
  optionally enable Spatie settings cache via env; invalidate through the existing
  `SettingsSaved` listener; tests for save-visibility, corrupted-cache fallback, and
  validator-skip on warm cache. Handoff includes the Forge env checklist.
  Commit: `perf: cache validated public front config`
- **P2 — Public listing fetch-window and lazy options.** Remove the latest-section
  `max(50,…)` floor / fetch only the visible window or real per-section pagination;
  make `ContentItemSearch` filter options `#[Computed]`/lazy and skipped for
  homepage-section renders; memoize `PublicFormModal::definition()`; make unconsumed
  aggregate selects opt-in per surface (F15). Query-count assertions before/after;
  URL state preserved. Commit: `perf: bound public listing queries and lazy filter options`
- **P3 — Derived transcript segments and viewer economy.** Persist `parsed_segments`
    + `word_count` on transcription save/import (Horizon backfill command allowed);
      viewer renders from stored segments with safe fallback; one decided strategy for
      per-segment Markdown HTML (cached per transcription version or request-memoized);
      `SafeMarkdownRenderer` remains the only Markdown boundary. Tests: identical output
      for a fixture transcript, stale-segment regeneration, parse/query counts drop.
      Commit: `perf: render transcripts from derived segments`

## Unchanged later steps (goal coverage map — nothing may be dropped)

- Step 10R-B4: card-options convergence per base prompt + F11 second half; still paused
  until M1-M6 complete (P1-P3 sit between M6 and B4 unless Yoni reorders).
- Step 10R-C1: paused; M6 records final status.
- Step 10R-C2: layout consistency + semantic tokens + F13 grid-map unification.
- Step 9F-A/B/C: rich columns, footer config/renderer, admin polish — only after
  M1-M6 + P1-P3 + B4 + C2.
- Step 11: seeders/demo/assets/cleanup + NEW promotion of the evaluation seed state
  (data, five templates, policy scenarios) into the tracked idempotent seeder —
  only after explicit Yoni approval.
- Prompt 13/14/15: only after explicit approval. Full Step 2 publication workflow
  stays deferred. `transcriptions.author_id` removal stays out of scope.

---

## Docs each run must keep current

- `docs/phase-02/current-project-state.md` (before commit, every run)
- `docs/phase-02/public-front-v2-step10r-9f-mini-step-ledger.md`
- `docs/phase-02/public-front-v2-<mini-step-id>-implementation-plan.md` (18-section format;
  M4's plan adds: decisions applied D1-D8, backlog items owned this run, query-count baseline)
- `docs/phase-02/public-front-v2-<mini-step-id>-handoff.md` (base format + backlog status
  delta + Forge/env notes where relevant)
- `docs/research/public-front-v2/17-<mini-step-id>-mcp-research.md`
- `docs/phase-02/public-front-v2-performance-efficiency-audit.md` (M4 run creates; every
  later run updates statuses)
- `docs/phase-02/public-front-v2-transcription-display-decisions.md` (M4 run creates;
  later runs update on any decision change)

## Research, gate, and report (per base prompt, unchanged)

- Boost `application_info`, `database_schema`, `database_query`, `search_docs` before
  touching queries, Livewire state, settings, card registries, or tests; Boost
  `search_docs` for Livewire `#[Computed]`/lazy components, `preventLazyLoading`,
  `expectsDatabaseQueryCount`, subquery selects.
- FilamentExamples MCP in short query batches + refined pass; honest access level;
  research note per step.
- Full gate every implementation run: `php artisan test`, `vendor/bin/pint --test`,
  `vendor/bin/filacheck`, `npm run build`, `git diff --check`; `vendor/bin/pint --dirty
  --format agent` after PHP edits. No `filacheck --fix`. No pushes unless explicitly
  asked. No worktrees, no parallel agents, no `php artisan model:show`.
- Review checklist must tell Yoni what to click: the three evaluation URLs above and
  `/`, `/search`, `/podcasts/{slug}`, `/contributors` — under BOTH policy modes and
  with the `episode_transcription_focus` template active — plus which admin settings
  to toggle (`transcription_policy`, new `transcription_display` tokens).

## Final report every run

Base-prompt report format, plus: decisions applied/overridden (D-numbers), backlog
items resolved (F-numbers) with query-count baseline vs after, ledger insertions done
(P1-P3, 9F-A note), confirmation that tests never depend on local-only seed data or
local settings values, and the next mini-step.

End with exactly:

```text
Public Front v2 post-B3 mini-step <MINI_STEP_ID> is complete. Waiting for Yoni/ChatGPT review before continuing.
```