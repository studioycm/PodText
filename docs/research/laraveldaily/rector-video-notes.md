# Laravel Daily: "Rector in Laravel: 79 Rules to Auto-Refactor Code" — notes

- **Source**: [youtube.com/watch?v=iwGGbAYQKgo](https://www.youtube.com/watch?v=iwGGbAYQKgo) —
  Laravel Daily channel, **2025-04-24**, 8:49.
- **Captured**: 2026-08-10 — full transcript (pot-URL interception; the transcript-panel
  scrape returned 0 segments on this page, so Method A carried it) + the demo repo's exact
  configs, archived in `dev code/laraveldaily/raw/youtube-iwGGbAYQKgo-rector.md`.
- **The find that beat screenshots**: the demo repo is **public** —
  [krekas/Laravel-KnowledgeBase-FAQ](https://github.com/krekas/Laravel-KnowledgeBase-FAQ)
  branch `rector`, six commits, one per run — so every `rector.php` was fetched from GitHub
  at its exact sha instead of being transcribed from video.
- **Verified against**: installed `rector/rector 2.6.1` + `driftingly/rector-laravel 2.5.0`,
  this repo's `rector.php`, and the standing rector memory (parallel-lossy/serial-honest).
- **Staleness verdict**: **passes with one dated number** — Apr 2025, modern
  `RectorConfig::configure()` fluent API throughout (current in 2.6), all six named rules
  still exist in the installed package; but "79 rules" is now **124** (measured
  `find vendor/driftingly/rector-laravel/src -name '*Rector.php' | wc -l`) — the package
  grew ~57% since the video.

**Headline: the video is a six-run field demo of exactly the tool PodText adopted this
week** (commit `7b6b52e`, "introduce Rector wired to larastan — dry-run-locked"), and its
progression model — one concern per run, cumulative config — is the useful transferable
shape. PodText's own setup is already *more* disciplined than the video's on the two points
that matter (§3).

---

## 1. The six-run progression (exact configs from the repo, per-run blast radius)

A Laravel-8 legacy app, `withPaths([app, database, routes, tests])`, each run adding one
concern to the previous config:

| run | added | files changed | what the video shows |
| --- | --- | --- | --- |
| 1 | `SetList::DEAD_CODE` + `LaravelLevelSetList::UP_TO_LARAVEL_120` + `LARAVEL_LEGACY_FACTORIES_TO_CLASSES` | 23 | old factories → class-based with `fake()`; unused variable dropped; `$dates` → `$casts`; old accessor syntax → new attributes |
| 2 | `withPreparedSets(deadCode, codeQuality, typeDeclarations, privatization, earlyReturn, strictBooleans)` | **107** | return types everywhere ("void void everywhere in the seeders… in places I wouldn't use them"), strict `!==`, `compact()` → arrays |
| 3 | `->withImportNames()` | 36 | inline FQCNs hoisted to `use` statements |
| 4 | `AnonymousMigrationsRector` | 16 | named migration classes → `return new class` |
| 5 | `EloquentMagicMethodToQueryBuilderRector` | 37 | `Model::where(...)` → `Model::query()->where(...)` — **his own verdict: "overkill… I don't really like"** applied blanket |
| 6 | `EloquentOrderByToLatestOrOldestRector` | 3 | `orderBy('created_at','desc')` → `latest()` |

The numbers are the honest part: the **prepared-sets run touched 107 files**, five times the
targeted Laravel-upgrade run. That is the video's implicit warning about broad sets, made
explicit by its own commit history.

Rules he highlights from the catalogue beyond the runs: `back()` for `redirect()->back()`,
`to_route()`, remove-`dd`/`dump`, validation strings → arrays, and — three weeks old at
recording — **the `#[Scope]` attribute rule** (`ScopeNamedClassMethodToScopeAttributedClassMethodRector`,
contributed by Peter Fox), automating exactly the `scopeX()` → `#[Scope]` migration that the
concurrent sessions performed on this repo's 21 scopes.

## 2. His when-to-use frame (the durable part)

Rector sits alongside Larastan/PHPStan (analysis), Pint/CS-Fixer (style), Shift (version
upgrades) — different angles of the same automation family. His usage rule:

- **Targeted rules, not full-codebase sweeps** — enforce/upgrade *specific* syntax
  (factories, attributes, migrations), once, on a legacy project;
- then keep Rector in **CI before deployment**;
- keep **Larastan for the global check**;
- `--dry-run` first, always; git as the undo; and *"please please rerun the automated tests"*
  after any refactoring tool.

## 3. PodText against the video — adopted this week, and stricter

`rector.php` landed here on 2026-08-10 (`7b6b52e`). Side by side:

| video's demo config | PodText's config |
| --- | --- |
| broad `withPreparedSets(...)` six-flag run (107 files) | **one targeted set**: `LaravelSetList::LARAVEL_CODE_QUALITY` — exactly his "specific rules, not full sweep" advice, which the demo itself did not follow |
| no PHPStan wiring | `withPHPStanConfigs([phpstan.neon, larastan extension])` — Rector reuses the larastan model knowledge |
| default cache | `withCache(storage/framework/cache/rector)` — isolated per the [rector-parallel memory] finding that overlapping sessions corrupt shared cache state |
| `--dry-run` as advice | **dry-run-locked** by the adopting commit's own framing |
| tests after, as a plea | the full gate is already mandatory house process |

Two additions the video contributes to the local picture:

- **`EloquentMagicMethodToQueryBuilderRector` exists and is blanket** — relevant because this
  repo already uses `Model::query()` deliberately in many places; if that style is ever to be
  *enforced*, the rule is the automation, with the video's own caveat that it converts
  single-call sites where `query()` adds nothing.
- **The `#[Scope]` rule** could have automated the 21-scope migration the other session did
  by hand — worth knowing for the *next* mechanical sweep of that kind: check
  rector-laravel's 124-rule catalogue before hand-editing.

**No proposals** — the tool is adopted, configured tighter than the demo, and owned by the
"Rector Laravel dry run" session. These notes are input to it, not competition.

## 4. Sources

- Transcript + six exact `rector.php` configs + per-commit file counts:
  `dev code/laraveldaily/raw/youtube-iwGGbAYQKgo-rector.md`; configs fetched from
  [krekas/Laravel-KnowledgeBase-FAQ](https://github.com/krekas/Laravel-KnowledgeBase-FAQ)
  at shas `6e06a88…a877a48` via `gh api`.
- Installed: `rector/rector 2.6.1`, `driftingly/rector-laravel 2.5.0`; rule-file count 124;
  all six video-named rules present (including the Scope-attribute rule).
- This repo: `rector.php` (commit `7b6b52e`), the rector parallel-lossy memory (2026-08-10).
- Video description links: getrector.com, the rector-laravel repo, his `query()` video
  (`yviVaX7-wT4`), the 2023 Larastan course (**still stale** — the description itself links
  it; the catalogue's ⛔ stands), the Testing course.

### What I could not obtain

- The video's before/after diffs beyond what the transcript describes — recoverable any time
  from the public repo's commit diffs (`gh api repos/krekas/Laravel-KnowledgeBase-FAQ/commits/<sha>`);
  fetched file lists only, not full patches, to keep the capture lean.
- Comment thread (not scraped — YouTube comments need scrolling hydration; nothing in the
  visible top comments suggested corrections).
