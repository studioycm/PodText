# Hebrew search folding — spec

**Status:** approved for implementation in a dedicated session. Scope:
**everything, including admin.**

**Goal:** a search for `שלום` finds text stored as `שָׁלוֹם`, everywhere text is
searched.

Everything marked **[MEASURED]** was run during the investigation and its
output is quoted. Production was never contacted.

---

## 1. Why the obvious fix was rejected

The first proposal was `LOCATE(needle COLLATE …, haystack COLLATE …)`, which
does fold niqqud where `LIKE` does not. **Three independent judges disqualified
it**, on one fact:

> **The test suite cannot execute a MySQL-only fix.**

`tests/TestCase.php:29-42` force-writes `DB_DATABASE=':memory:'` into
`putenv`/`$_ENV`/`$_SERVER`, and `config/database.php:52` reads
`env('DB_DATABASE')` for the mysql connection — so even a deliberate
`->connection('mysql')` resolves to a MySQL database **literally named
`:memory:`**. A MySQL-only arm ships with zero coverage and cannot be
smoke-tested for SQL syntax. In-repo precedent for the rot:
`app/Support/Media/MediaRecordScope.php:340-348` has the identical shape and
its mysql arm is executed by no test.

Supporting facts, all measured:

- `LIKE` folds nothing ignorable in **any** of six utf8mb4 collations. `=` and
  `LOCATE` do. Niqqud carries zero primary weight, so `WEIGHT_STRING` is
  byte-identical for both spellings; `=` compares whole weight strings, `LIKE`
  walks character-by-character and cannot consume a zero-weight character with
  no counterpart in the pattern.
- The folding is **collation-dependent**: `LOCATE` folds under
  `utf8mb4_unicode_ci` and `utf8mb4_0900_ai_ci`, but **not** under
  `utf8mb4_general_ci`, `utf8mb4_bin` or `utf8mb4_0900_as_cs`. And
  `config/database.php:57` reads `env('DB_COLLATION', 'utf8mb4_unicode_ci')`
  with **no `DB_COLLATION` in `.env`** — so the fix would ride on an unpinned
  default.
- **SQLite has no analogue.** `instr()` is codepoint-exact, `locate()` doesn't
  exist, `collate utf8mb4_unicode_ci` errors with *no such collation
  sequence*, and `LIKE` ignores `COLLATE` entirely.
- **Filament's `search_collation` hook is a no-op.**
  `vendor/filament/support/src/helpers.php:303-307` appends `collate {$x}` to
  the search **column** — and `LIKE` ignores it. Measured: still `0`.

## 2. Scope — 13 implementations, not one

A fix patching only `ContentItemSearch::applyPublicSearch` leaves group-episode
search, podcasts, contributors, media library, the whole admin table search and
the in-PHP homepage filter broken. **The fix must be one shared helper every
call site routes through, or it diverges immediately.**

- **9 public** search paths, incl. `ContentItemSearch.php:468,472-483`,
  `ContentItemBrowser.php:112-113`, `PublicContentGroupQueries.php:40`
- **~80 Filament `->searchable()` columns**, all funnelling through one vendor
  emitter. No Scout, no Filament global search configured — blast radius is
  table search only.
- **One call site is not SQL at all**: `ContentItemSearch::latestFilteredItems`
  (`:829-855`) filters a hydrated Collection with
  `Str::of($haystack)->lower()->contains($search)`. Driver-independent, fully
  testable, and needs the same PHP normalizer on both sides.

### The live defect, precisely

[MEASURED] `content_items` id 56 carries a **U+05BF RAFE** in
`description_markdown` (`…D794 D6BF 21`):

```
title LIKE '%זה למה!%'                 → 0
description_markdown LIKE '%זה למה!%'  → 0
LOCATE('זה למה!', description_markdown) → 1
```

⚠️ **Correction to earlier reporting:** the homepage search does *not* search
`description_markdown`. This record is reachable only through
`ContentItemBrowser`, `PublicContentGroupQueries`, and the in-PHP
`latestFilteredItems`. A homepage-scoped fix would not have moved it.

## 3. The design — folded shadow columns, written by `setAttribute()`

**Scored 16 against 6 and 5**, winning all three lenses (correctness,
testability, maintenance cost).

**Shape:** each searchable text column gains a `*_search` sibling holding the
niqqud-stripped fold. Searches run a plain, portable `LIKE` against the shadow
column. **SQLite executes the shipped predicate**, so the suite proves the
thing that ships.

**The fold** strips `\p{Mn}` (combining marks, U+0591–U+05C7) and lowercases.
`app/Support/.../HebrewSlugger.php:15` already does the strip —
`preg_replace('/\p{Mn}+/u', '', $slug)` — and is the starting point.
[MEASURED] fold cost **0.00066 ms per title** (100k iterations in 0.066 s).

**Write hook: override `setAttribute()`, not model events.** The deciding fact:
`Model::fill()` calls `setAttribute()` (`Model.php:686`) and `forceFill()`
delegates to `fill()` (`:725`), so the override fires on all **five
`saveQuietly()` writes live in `app/` today** — which model events miss.

| | portable | indexable | tests exercise shipped code |
| --- | --- | --- | --- |
| driver-branched `LOCATE` | ✗ | ✗ | **✗ — disqualified** |
| shadow column via events | ✓ | ✓ | ✓ |
| **shadow column via `setAttribute()`** | ✓ | ✓ | ✓ |

## 4. What implementation must cover

1. **The folder** — one class, one public method, its own unit tests over
   niqqud, final forms, geresh, maqaf, mixed Latin/Hebrew, emoji.
2. **Migration + backfill** for every searched column. Backfill must be
   chunked and re-runnable.
3. **`setAttribute()` override**, applied via a trait to every model with a
   searched column.
4. **Import paths** — importers may write without `fill()`. Verify each.
5. **All 9 public search paths** routed through one helper.
6. **Admin**: a shared `->searchable(query: …)` macro rather than ~80
   hand-edits. `InteractsWithTableQuery.php:55-71` short-circuits on
   `$this->searchQuery`, so the macro must set it.
7. **`latestFilteredItems`** — same folder, both sides, in PHP.
8. **Indexes** on the shadow columns. [MEASURED] there is **no** FULLTEXT and
   **no** index on any title/name column today, so search is a full scan
   already; this is the first chance to index it.
9. **Regression canaries** — `AppOwnedMediaResourceTest.php:1250,1256,1268` and
   `AppOwnedMediaPickerTest.php:1884,1888` already search unpointed Hebrew on
   SQLite and must stay green.
10. **A test that id 56 becomes findable** by the phrase as it renders.

## 5. Deliberately out of scope

- Changing the database collation. See
  `hebrew-collation-and-clock-plan.md` — the null option is live, and **§1
  proves the collation was never what decided this**.
- Scout / FULLTEXT. A different project.
- The clock. Separable; see the same doc.

## 6. Open questions for the implementing session

- Does the fold belong on **every** searched column, or only free-text ones?
  Slugs and reference keys are ASCII and gain nothing.
- Storage cost on `transcript_markdown` — potentially doubling the largest
  column in the schema. Measure before committing to it; a transcript-body
  search may want a different mechanism entirely.
- Does any importer bypass `fill()`? If so the trait needs a second seam.
