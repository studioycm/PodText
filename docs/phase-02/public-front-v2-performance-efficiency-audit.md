# Public Front v2 Performance And Efficiency Audit

Created in Step 10R-M4.

## Deployment Reality

- Production runs on Laravel Forge with MySQL, Redis, and Horizon.
- Local development runs on Laravel Herd.
- Local DB and test suite use SQLite.
- SQL used by public queries must run on both MySQL and SQLite.
- Prefer Builder-composed subqueries over dialect-specific raw SQL.
- Cache work must use one versioned key. Cache tags are not assumed available.
- Environment changes are deploy notes in handoffs, not tracked `.env` edits.
- Public rendering must not wait on queued work.

## Findings

| # | Finding | Status | Owner | Notes |
|---|---|---|---|---|
| F1 | Settings cache disabled; full validated config work can run every request. | scheduled | P1 | M4 records; P1 implements validated config caching. |
| F2 | Latest sections force a `max(50, ...)` hydrated fetch window and PHP-side pagination. | scheduled | P2 | M4 leaves fetch-window behavior untouched. |
| F3 | Transcript viewer repeatedly resolves published transcriptions and reparses Markdown segments. | scheduled | P3 | M4 resolved repeated public-transcription list resolution with a computed property. P3 owns stored parsed segments and render economy. |
| F4 | Top transcribers ranked query can run during mount normalization and render. | resolved | M4 | M4 memoizes contributors with a computed property. |
| F5 | `Transcription::saved` always attempts compatibility pivot sync. | resolved | M4 | M4 guards no-op saves; focused test asserts no `author_transcription` queries on no-op save. |
| F6 | Section resolver re-finds category/tag/group targets despite eager-loaded relations. | resolved | M4 | M4 uses relation-first target resolution with memo fallback. |
| F7 | Public search filter-option queries run on every search render. | scheduled | P2 | Not changed in M4. |
| F8 | Lazy-load fallbacks remain possible and no global lazy-loading guard exists. | resolved | M4 | M4 enables non-production lazy-loading prevention and fixes exposed eager-load gaps. |
| F9 | Contributor counts used `transcriptions.author_id`. | resolved | M3 | Pivot-backed in Step 10R-M3. |
| F10 | Podcast/group aggregates were query-side only. | resolved | M4 | M4 renders group aggregate attributes on cards and podcast detail headers. |
| F11 | Per-card Blade presenter resolution and legacy `authors` key remain. | scheduled | B4 | M4 hoists item/group card presentation per grid and renames the content-item internal key. B4 owns remaining `PublicContentCardOptions` convergence. |
| F12 | `PublicFormModal::definition()` is resolved in multiple component phases. | scheduled | P2 | Not changed in M4. |
| F13 | Grid class maps are duplicated. | scheduled | C2 | Not changed in M4. |
| F14 | Ledger 9F-A note still says "after Step 10R-A/B/C". | resolved | M4 | M4 ledger update fixes the note. |
| F15 | `PublicContentItemQueries::base()` adds correlated aggregate subselects to every public item listing. | scheduled | P2 | M4 consumes aggregate values on cards/pages where needed. P2 owns opt-in aggregate selects for surfaces that do not render them. |

## Query-Count Harness

Step 10R-M4 adds a fixture-owned public rendering query-count harness covering:

- homepage sections render;
- `/search` render;
- podcast detail render;
- contributor directory render;
- item page render.

The harness uses bounded query counts rather than exact counts so it can catch major regressions without depending on local-only seeded data or local settings values.

## Local Evaluation Snapshot

This is an informational snapshot against the local-only evaluation seed. Tests do not depend on these rows or local setting values.

| Route | Pre-M4 baseline from runner context | After M4 local snapshot |
|---|---:|---:|
| `/` | 24 | 24 |
| `/search` | 38 | 22 |
| `/podcasts` | not recorded | 10 |
| `/contributors` | 8-16 | 7 |
| `/podcasts/technology-in-hebrew` | 20 | 20 |
| `/items/technology-in-hebrew/ai-tools-for-editors` | 48 | 19 |
| `/contributors/yonatan-cohen` | 18 | 18 |

The tracked regression harness in `tests/Feature/PublicFrontMultiTranscriptionRenderingTest.php` covers fixture-owned homepage, search, podcast detail, contributors, and item page rendering with bounded query counts under `all_published`.
