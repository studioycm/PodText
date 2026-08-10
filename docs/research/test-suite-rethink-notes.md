# Test-suite rethink — research notes (post-alignment)

Written 2026-08-10, the day the database-alignment program closed (55 commits
`94a3328..7d715c9`). Companions with distinct ownership:
[`pest5-rector-phpstan-notes.md`](pest5-rector-phpstan-notes.md) owns the
Pest 5 / Rector / plugin *package* facts, and
[`larastan-playbook.md`](larastan-playbook.md) owns the PHPStan/larastan side.
This doc owns the **suite** side: what the alignment program left underneath
the tests, where the cost sits, the option space for the rethink, and the
larastan↔Rector working loop the operator asked for. The decision record is
[`../phase-02/test-suite-rethink-spec.md`](../phase-02/test-suite-rethink-spec.md).

---

## 1. The suite as the alignment program left it

Measured/verified facts, each with its source:

- **Scale and wall time.** **1,953 tests / 20,393 assertions / 603.8s**,
  measured 2026-08-10 (this doc's own gate run). Earlier figures, in
  provenance order: SQLite green baseline **~557s** (rehearsal log §T19);
  first green lane run **615s / 1,929 tests** (same log); state-doc final
  full-gate figure **~635s / 1,934**; T23b clean record **609s / 1,949**.
  The lane costs **roughly +10%** over SQLite at equal test count. (T19's
  report says "34% slower (615s vs 458s)", but the 458s denominator was the
  *red* baseline run with 89 failures aborting early — use 557s.)
- **A live flake specimen, caught by that same 2026-08-10 run** (1952/1953;
  the failure re-ran green in isolation, 14/14):
  `CardTemplatePreviewBrowserTest` line 663 asserts
  `horizontal_overflow => false` from a **single read** of
  `document.documentElement.scrollWidth` taken right after a viewport
  change — under CPU contention (another session's worktree was active) the
  read lands mid-reflow and a one-frame transient overflow fails the run.
  Same race class the `browser-script-step-labels` memory documents
  (single-read vs condition-wait). Fix candidate for Phase S: settle-wait or
  condition-poll the overflow measurement; sweep the file for sibling
  single-reads while there.
- **Runner shape.** `RefreshDatabase` is globally OFF
  (`tests/Pest.php:126`, commented) with per-file opt-in. Counted 2026-08-10:
  **115 of 148 Feature files + all 12 Browser files** use it; **33 Feature +
  8 Unit files run without it**. Unit files never boot the app —
  `tests/TestCase.php` binds to Feature/Browser only, so unit tests *bypass
  the lane guard entirely* (the file says so at `:53`; latent while they stay
  DB-free, unguarded the day one doesn't).
- **Guard stack, in execution order:** `tests/Pest.php` env forcing
  (`putenv`) → per-process flock run-lock (handle persisted in `$GLOBALS` for
  the process lifetime — the GC trap fixed in `538d8d9`; see the
  `pest-lane-lock-gc-trap` memory) → `TestCase::refreshApplication()`
  re-forces env + config → `refusalFor()` 12-clause one-shape table →
  `assertDisposableSchema()`: first-use empty-schema fingerprint, then a
  per-boot `information_schema.COLUMNS` TIMESTAMP count while the connection
  pins `+00:00` (~1,900×/suite — the deliberate T17 trade-off that catches
  DDL-leak deadlocks at the *next* boot).
- **Fake-disk process tokens** + orphan sweep in `tests/Pest.php` (the
  browser-timeout contention fix — two concurrent suites no longer delete
  each other's fixtures).
- **Browser tests boot their own server** which inherits the env-level
  forcing; the public disk URL is forced relative so subresources hit the
  in-process server, not the Herd vhost.
- **T19's fallout taxonomy** (encoded in commit messages and test comments,
  counted in the T19 report addendum): native-JSON round-trips (14 sites —
  MySQL re-serializes JSON and does not preserve member order; `toEqual`, not
  `toBe`), ordering/rowid (2 — DATETIME has no fractional seconds, so
  same-second rows tie; fixed with `travel()` ticks + explicit tie-breaks),
  engine introspection (1 — `EXPLAIN` semantics), PRAGMA dialect (3), true
  strict-mode (1). Plus **two genuine app bugs only the real engine could
  surface**: `de83d26` (unspecified `ON UPDATE` reports `NO ACTION`, not
  `RESTRICT`) and `e031bcb` (querying a configured column that doesn't exist —
  parse-time error on MySQL, silently absorbed by sqlite's quoted-identifier
  fallback).
- **Cross-worktree lock gap** (found writing this doc, 2026-08-10): the
  run-lock file lives in each tree's `storage/framework/testing/`, but the
  lane is machine-global. Two worktrees hold *independent* locks; today only
  the fingerprint first-use refusal keeps a second (fresh) worktree off the
  lane. Any `lane:reset` helper that clears fingerprints without a
  lane-keyed concurrency probe reopens the collision — hence the
  live-connections check in the spec's F4 design.

## 2. Where the time goes — measurements Phase R owes

None of these exist yet; they decide the structure phase's scope:

1. **Per-file duration profile** (slowest 20 files, browser vs feature
   split) — `pest --profile` or the compact JSON timings.
2. **`migrate:fresh` share**: how much of the ~600s is the once-per-process
   migration plus per-test transactions, vs. test bodies.
3. **Guard cost** (F9): time the per-boot `COLUMNS` count in isolation
   (~1,900 × per-query cost), *measured on an idle lane*.
4. **Browser share**: 12 files; the suite's timing tail risk lives here.
5. **RefreshDatabase audit**: classify the 33 Feature + 8 Unit opt-out files
   — genuinely DB-free, manually-managed state, or accidental omission
   (coverage-honesty risk: a file that writes without cleanup relies on
   neighbors' transactions to hide it).

## 3. Speed levers (research-only until measured)

- **Parallelization is the big lever and was half-designed-for.** The lane
  guard's DB-name regex `/^[a-z][a-z0-9_]*_test(_[0-9]+)?$/` already admits
  Laravel's parallel-testing names (`podtext_test` → `podtext_test_test_1`).
  Open problems the spike must answer: (a) the **flock refusal fires per
  process** — paratest workers each include `tests/Pest.php`, so worker 2
  would refuse today; the lock needs a worker-aware shape (e.g. skip when
  `TEST_TOKEN` is a paratest token, with a parent-level lane lock instead);
  (b) the lane user's grant is schema-scoped to `podtext_test` only —
  per-worker DBs need a widened grant (`podtext_test%`) plus CREATE, an
  operator decision because the narrow grant is a deliberate barrier;
  (c) fingerprints are per-database (`sha1(host|port|database)`), so each
  worker DB gets its own first-use check — that part composes cleanly;
  (d) memory: each worker needs the 2G limit (how `-d` propagates to workers
  is unverified). Plausible payoff: 600s → 150–250s at 4–8 workers, browser
  files permitting.
- **TIA (Pest 5's headline) has a hard local prerequisite: no coverage
  driver exists.** Measured 2026-08-10 on Herd's PHP 8.4.23 CLI: neither
  `pcov` nor `xdebug` is loaded *and no .so for either exists in the
  extension dir* — baseline recording is impossible until one is installed.
  Also unresolved: where the TIA cache lives and how it behaves with two
  sessions sharing one worktree (the same class of hazard the fake-disk
  tokens fixed). Execution is Pest-5-gated anyway (§6).
- **Time-balanced sharding** — CI-day concern; there is no CI. Parked.
- **Guard trims** — only if measurement 2.3 registers against the wall time.

## 4. Correctness and honesty levers

- **Guard consolidation** (`open-findings-triage.md` §D3): one first-running
  check with named failures, absorbing the clause table, env forcing, floors
  — and the run-lock relocation that fixes §1's cross-worktree gap.
- **Unit-suite bypass**: bind the guard (or an arch rule: `tests/Unit` never
  touches DB facades) so the bypass stops being latent.
- **DATETIME same-second ties**: `ec47df7` fixed one `defaultSort` surface
  with `travel()` ticks; sweep for other order-sensitive assertions without
  tie-breaks instead of waiting for the next flake.
- **Fixture honesty**: F2 (nullability drift untested) and F3 (payloads that
  dodge the DST rule) in `open-findings-triage.md` §F.
- **Browser-wait discipline** (`browser-script-step-labels` memory) was
  calibrated on plugin 4.x; a major bump re-rolls those dice — shakedown
  belongs to the Pest 5 phase, the trap inventory to Phase R.

## 5. The larastan ↔ Rector working loop (operator ask, 2026-08-10)

What "introduce Rector and work it with larastan" concretely means here —
package facts in `pest5-rector-phpstan-notes.md` §1, policy in
`larastan-playbook.md` §5:

- **Install dev-only:** `rector/rector` (2.6.1) + `driftingly/rector-laravel`
  (2.5.0). No `pestphp/pest-plugin-rector` yet — that is a Pest-5-line
  package and rides the end phase (§6).
- **Wire Rector to read larastan's knowledge:** `rector.php` →
  `->withPHPStanConfigs(['phpstan.neon'])`. This is the whole pairing: the
  B1 cast fix and B5 relationship generics mean Rector's type-aware rules
  write the types larastan already proved, instead of `mixed`-litter.
- **The loop:** scope a rule (never a whole set blind) → `--dry-run` →
  review the diff like a PR → operator approves writing → write → `phpstan
  analyse` proves no new errors (no-baseline policy unchanged) → `pint` →
  targeted pest. Composer-script shape mirrors FilaCheck's:
  `composer rector` is dry-run-only; `composer rector:fix` exists but is
  approval-gated — Rector is the same writes-to-source hazard class.
- **Order of rule adoption** (from the notes doc §"adoption order", app-side
  now that the Pest set is deferred): `rector-laravel` sets one at a time,
  PHP 8.4 sets where safe, type-declaration sets last (smallest prize —
  ~98% typed already). Watch any rule touching Filament fluent chains:
  Rector has no more insight into Filament's `Macroable` than larastan does.

## 6. Pest 5 timing (operator decision, 2026-08-10)

**"Pest 5 + plugins only at the end and maybe in a separate session — wait
for approval."** So the upgrade is the rethink's *final* phase, behind its own
gate, possibly executed in a dedicated session.

- **Gated behind it:** `pest-plugin-phpstan` (the written-down level-6 path
  in `phpstan.neon`), `pest-plugin-rector` (test-style rules), TIA execution,
  `--agent`, `toBeUlid()`, time-balanced sharding.
- **Not gated (prep that lands earlier):** the PHPUnit 13 changelog read
  (the one genuinely unbounded upgrade item), the browser-trap inventory,
  and the run-lock lifetime re-probe design — the `$GLOBALS` fix is
  bootstrapper-shape-dependent (`BootFiles::load()` method scope), so the
  upgrade must re-prove it with the mid-run probe from
  `.superpowers/sdd/final-review-fixes.md` §1, with a longer sleep than its
  documented ~0.6s margin.

## 7. Operator decision points (carried into the spec)

| # | Decision | Default proposal |
|---|---|---|
| DP1 | Widen the lane grant to `podtext_test%` for parallel workers | Only if the spike's numbers justify it |
| DP2 | Parallel as default runner vs opt-in flag | Opt-in first |
| DP3 | Install a coverage driver (PCOV) on Herd PHP 8.4 for TIA | Defer to the Pest 5 phase |
| DP4 | Each Rector write pass | Per-rule approval, dry-run diff first |
| DP5 | Level-6 wiring timing | After `pest-plugin-phpstan` lands + re-measure |
| DP6 | `lane:reset` UX (name, refusals, who runs it) | Spec F4 design |
| DP7 | Run-lock relocation to a lane-keyed path | Decide with D3 consolidation |
| DP8 | Pest 5 upgrade session (this tree or dedicated) | Operator schedules; separate approval |

## 8. Sources

- `.superpowers/sdd/progress.md`, `task-19-report.md`, `task-23-report.md`,
  `t19-fallout.md`, `final-review-fixes.md` (gitignored program ledger)
- `docs/phase-02/database-alignment-rehearsal-log.md` (lane canaries, §3 matrix)
- `docs/phase-02/current-project-state.md` (four Database Alignment sections)
- `docs/phase-02/open-findings-triage.md` (§B4/§D3/§F)
- `docs/research/pest5-rector-phpstan-notes.md`, `docs/research/larastan-playbook.md`
- `tests/Pest.php`, `tests/TestCase.php` (read 2026-08-10)
- `php -m` on Herd PHP 8.4.23 CLI (coverage-driver check, 2026-08-10)

## Phase R measurements (2026-08-10)

Task 8 of the test-suite-rethink plan. No app-code changes — this section is
the only mutation. Every number below is measured on this machine today
unless explicitly marked as an estimate or a carried-forward figure; where a
fetch or measurement came back empty/blocked, that is stated rather than
filled in.

### R1. Per-file duration profile

Command: `php -d memory_limit=2G vendor/bin/pest --compact --log-junit storage/framework/testing/junit-profile.xml`
(scratch XML deleted after aggregating, per instructions).

Result: **1,969/1,969 passed, 20,825 assertions, 622.2s wall** (pest's own
`duration_ms`). No flake fired this run — the known
`CardTemplatePreviewBrowserTest:663` single-read race did not trip today.

**Methodology correction found and fixed:** the brief's aggregation script
reads `testcase["file"]`, but Pest's JUnit writer sets that attribute to
`"path::test name"` (unique per test, not per file), so the literal script
produces a "per-file" table that is actually per-test (confirmed: it reports
"1969 files", exactly the test count). The correct level is the **file-scoped
`<testsuite file="...">` wrapper**, whose own `time` attribute is JUnit's own
pre-aggregated per-file total — no manual summing needed. Re-ran with the
corrected xpath (`//testsuite[@file]`); the browser/feature *share* numbers
from the literal script were unaffected by the bug (summing-with-a-filter is
invariant to bucket granularity), only the top-20 breakdown needed the fix.

Top 20 files by wall time (corrected, file-level):

| Time | File |
|---|---|
| 148.70s | `tests/Feature/PublicMaintenanceModeTest.php` |
| 109.35s | `tests/Browser/CardTemplatePreviewBrowserTest.php` |
| 70.44s | `tests/Feature/SettingsSp3aTest.php` |
| 36.32s | `tests/Feature/PublicMenuHeaderUxFixesTest.php` |
| 15.84s | `tests/Feature/PublicFrontRenderContextTest.php` |
| 14.63s | `tests/Feature/PublicHomepageSearchTest.php` |
| 14.50s | `tests/Browser/OwnerImageWorkspaceBrowserTest.php` |
| 14.49s | `tests/Browser/MediaResourceGalleryBrowserTest.php` |
| 12.15s | `tests/Feature/OwnerImageWorkspaceTest.php` |
| 9.26s | `tests/Browser/MediaPickerBrowserTest.php` |
| 8.82s | `tests/Feature/AuthzLegacyRoleBackfillTest.php` |
| 8.82s | `tests/Feature/PublicFrontConfigCacheTest.php` |
| 8.51s | `tests/Feature/CardTemplateEditorPreviewTest.php` |
| 8.30s | `tests/Feature/PublicFrontJsonSettingsArchitectureTest.php` |
| 6.27s | `tests/Feature/AppOwnedMediaPickerTest.php` |
| 5.34s | `tests/Feature/LarastanCastResolutionGuardTest.php` |
| 5.29s | `tests/Feature/AdminPhase02ResourcesTest.php` |
| 4.75s | `tests/Feature/AppOwnedMediaResourceTest.php` |
| 4.50s | `tests/Feature/EpisodesTableR1Test.php` |
| 4.47s | `tests/Feature/PublicAboutPageContentTeamTest.php` |

**Browser share: 159.2s (25.6%)** across 11 counted browser files (see file-count
footnote). **Feature share: 459.3s (73.9%)**. **Unit share: 3.3s (0.5%)**.
159.2 + 459.3 + 3.3 = 621.8s ≈ the 621.9s file-summed total (rounding).

**File-count footnote (measured, not assumed):** the JUnit tree has 167
`testsuite[@file]` entries, not 169 (149 Feature + 8 Unit + 12 Browser on
disk). Diffed and fully explained, no gap left open:
- `tests/Feature/ExampleTest.php` and `tests/Unit/ExampleTest.php` (the two
  classic PHPUnit class-based stub files) appear under a non-path `file`
  value (`"Example (Tests\Feature\Example)"` / `"...Unit\Example)"`), so a
  substring match on `tests/Feature`/`tests/Unit`/`Browser` misses them —
  they exist and ran, just outside my three buckets (hence 11+147+7=165, not
  167).
- `tests/Browser/DashboardRtlBoardBrowserTest.php` and
  `tests/Feature/CompiledThemeSentinelTest.php` are genuinely **absent** —
  zero occurrences anywhere in the XML. Verified why: both carry
  `->group('rtl-board')` / `->group('compiled-sentinels')`, and
  `phpunit.xml:18-23` excludes exactly those two groups by default. This is
  pre-existing, deliberate suite configuration (matches
  `defect-cause-patterns.md`'s "rtl-board... default-targeting exclusion
  confirmed (0 tests found)"), not a gap in this run.

**Top-finding note (measured, root cause not chased — Phase S candidate):**
`PublicMaintenanceModeTest.php` is the single slowest file (148.70s / 18
tests ≈ 8.3s/test) with no `sleep()`/`usleep()`/explicit wait in the file and
`Mail::fake()` already in place at line 38 — something else in the OTP/email
verification flow under test is expensive. Root-causing this is Phase S
scope; flagging it here because at 148.7s it is bigger than the entire
browser suite's second-slowest file.

### R2. Boot + migrate share

Command as specified: `time php -d memory_limit=2G vendor/bin/pest tests/Feature/EnvironmentGuardsTest.php --compact`

Result: **506ms pest-internal / 0.734s wall** (4 tests, 10 assertions).

**The brief's premise does not hold for this file, and I am reporting that
rather than the number it predicted.** `EnvironmentGuardsTest.php` does
**not** invoke `RefreshDatabase` — confirmed by direct grep (its only mention
of the word is inside a comment) and now confirmed by timing: 0.5–0.7s is
nowhere near a real `migrate:fresh` cost against a ~40-table schema. This
file is one of R5's genuinely-DB-free files (it only asserts `config()`
values); its wall time is pure PHP/Laravel-boot overhead plus four cheap
guard queries, not a migration cost.

**Supplementary measurement (not in the brief's literal script, run to
actually answer the question R2 was reaching for):** the smallest *genuine*
`RefreshDatabase`-consuming Feature file is `DstPickerRoundTripTest.php` (1
test). Timed standalone: **1,801ms pest-internal / 1.920s wall**. Delta
against `EnvironmentGuardsTest.php`'s no-migration baseline (~0.5–0.7s) puts
the **one-time per-process `migrate:fresh` cost at roughly 1.2–1.5s** for the
current ~40-table schema. Against R1's 622.2s total, a single one-time ~1.3s
migration is **~0.2% of the wall clock** — not a lever worth pulling.

### R3. Guard-query cost on the idle lane (F9's permanent label)

Run strictly after R1 (schema populated, ~40 real tables, per the state
note), then confirmed still non-empty after R2's own runs:

```
0.524 ms/query -> ~1.0 s per suite at 1900 boots
```

Cross-check against the real boot count from R1's own JUnit data: Unit tests
never boot the guard (38 of them), so Feature+Browser boots = 1969 − 38 =
**1,931** — within rounding of the brief's "1900" convenience figure.
0.524ms × 1,931 = **1.01s**.

**F9 verdict: stays `accepted`, closed.** The per-boot
`information_schema.COLUMNS` query costs ~1 second out of 622 — 0.16% of
total wall time. F9's own text said "revisit only if it registers against the
~600s wall"; it does not. No action for Phase S.

### R4. Browser trap inventory

Grep: `waitFor|settle|_x_dataStack|scrollWidth|getBoundingClientRect` across
`tests/Browser/` (12 files). 10 of 12 files matched (`AdminContentItemBrowserTest.php`
and `PublicPanelBrowserTest.php` use none of these patterns at all — simpler
files with no wait/probe logic).

**Condition-waits (the correct pattern, present in every matched file):**
every file defines its own local `waitFor(callback, label, timeout)` JS
helper (a labelled polling loop) and uses it for state transitions —
`_x_dataStack` boot probes correctly target the `x-data` element via
`closest('[x-data]')` (the `single-read-race` ledger entry's own prior
lesson, applied correctly everywhere it appears).

**Single-reads (the risky pattern) — counted precisely, not estimated:**

```
grep -c "horizontal_overflow" tests/Browser/*.php
  CardTemplatePreviewBrowserTest.php:      28
  MediaResourceGalleryBrowserTest.php:     10
  MediaPickerBrowserTest.php:              10
  OwnerImageWorkspaceBrowserTest.php:       6
  ------------------------------------------
  total:                                   54
```

Every one of these 54 occurrences is the same shape: `horizontal_overflow:
document.documentElement.scrollWidth > document.documentElement.clientWidth +
1` computed **once**, inline in a returned measurement object, immediately
after (but not gated on) some *other* condition's `waitFor`. This is exactly
the `single-read-race` mechanism the ledger already named. The known flake —
`CardTemplatePreviewBrowserTest.php:663` — is
`->and($measurement['horizontal_overflow'])->toBeFalse()` consuming the
single read computed at line 645 (confirmed by direct read of the file, not
inferred).

`getBoundingClientRect()` also appears as bare, unwaited single reads for
layout measurements (rows/columns/alignment) throughout
`CardTemplatePreviewBrowserTest.php`, `OwnerImageWorkspaceBrowserTest.php`,
and `MediaResourceGalleryBrowserTest.php` — same race class, different
property.

**Browser plugin changelog (WebFetch):** installed `pestphp/pest-plugin-browser`
is **v4.3.1** (composer.lock). `gh api repos/pestphp/pest-plugin-browser/tags`
shows a **v5.0.0** tag exists. WebFetch on the Releases page returned "There
aren't any releases here"; confirmed via the raw API
(`gh api repos/pestphp/pest-plugin-browser/releases` → `0` release objects,
and the specific `releases/tags/v5.0.0` lookup → 404) and via a `CHANGELOG.md`
content check (404 — no such file in the repo). **Honest verdict: not
blocked-unreachable, but blocked-empty** — the fetch succeeded; there is no
changelog text published anywhere GitHub-side for the 5.x line. This is
Pest-5-phase-gated work anyway (per the plan), so it does not block Phase R
or S, but a real diff read (`git log` between the installed tag and v5.0.0)
is the only way to learn what changed when that phase starts.

**Phase U shakedown checklist (the brief's requested table):**

| # | Pattern | Files affected | Verdict |
|---|---|---|---|
| 1 | `horizontal_overflow` single-read | CardTemplatePreview (28), MediaResourceGallery (10), MediaPicker (10), OwnerImageWorkspace (6) — 54 total | Fix-candidate: wrap in `waitFor(() => <stable rect for N frames>, ...)` or accept a settle delay before the single read |
| 2 | `getBoundingClientRect()` bare reads | Same four files, additional occurrences beyond the overflow ones | Lower urgency — most are used for one-shot layout assertions taken after an already-waited condition, not raced against a live transition |
| 3 | `waitFor` labelled polling helper | All 10 matched files | Correct pattern, no action |
| 4 | `_x_dataStack` boot probe | `DashboardSparklineBrowserTest.php` | Correct (`closest('[x-data]')`), no action |
| 5 | pest-plugin-browser 4.3.1 → 5.0.0 | composer.lock | Deferred to the Pest 5 upgrade phase; no changelog text available to pre-read |

### R5. RefreshDatabase opt-out classification

`grep -rL "RefreshDatabase" tests/Feature tests/Unit --include='*Test.php'`
gave 42 files, but **two independent detector traps were found and corrected**
before trusting that number (both self-checked against the actual file
content, not assumed):

1. **Naive string search undercounts real "uses-it" files in the other
   direction first:** `tests/Feature/EnvironmentGuardsTest.php` mentions the
   word "RefreshDatabase" only in a comment — a `grep -L` (no-match) search
   correctly excludes it from "no match anywhere," but the *actual* opt-out
   set (files that don't invoke the trait) must add it back in, since the
   comment isn't an invocation. (+1 to the opt-out set)
2. **Pest-style detection undercounts the trait-*users* by missing classic
   PHPUnit syntax:** `tests/Feature/ExampleTest.php` (the class-based
   Laravel-default stub, `class ExampleTest extends TestCase`) uses
   `use RefreshDatabase;` as a genuine PHP trait-use statement inside the
   class body — a regex for Pest's functional `uses(RefreshDatabase::class)`
   never matches this syntax. Verified: this file is the **only** one in the
   whole suite using classic class-based trait syntax at all (`grep -rlE
   "^\s*class\s+\w+\s+extends\s+(Tests\\\\)?TestCase"` → only the two
   `ExampleTest.php` stubs are class-based; only the Feature one uses the
   trait). (−1 from the opt-out set, moved to "uses it")

**Corrected, measured counts (today, not the notes doc's prior 148/115/33
snapshot — the suite grew by one net Feature file since then):**

| | Total | Uses RefreshDatabase | Opts out |
|---|---|---|---|
| Feature | 149 | 114 (113 Pest-style + 1 classic-style) | 35 |
| Unit | 8 | 0 (architectural — `TestCase` binds Feature/Browser only) | 8 |
| Browser | 12 | 12 | 0 |

True classification set: **43 files** (35 Feature + 8 Unit), not 44.

**Classification (every file checked for direct DB signals — `DB::`,
`::factory(`, `->create(`/`::create(`, `Schema::`, `->save()/->insert()/
->update()/->delete()` — plus a second pass checking for *indirect* writes
via HTTP/Livewire/artisan invocation, since a test can write rows without
calling any DB API itself):**

- **(a) genuinely DB-free: 40 files** (32 Feature + all 8 Unit). Two grep
  hits were confirmed false positives on inspection:
  `AdminTableSearchabilityTest.php`'s `create(` is `Finder::create()`
  (Symfony Finder, not Eloquent); `FilamentLocalizationDefaultsTest.php`'s
  `Schema::` is `Filament\Schemas\Schema::make()` (form-schema builder, not
  the DB facade). `AlignmentMigrationTest.php`/`AlignmentOracleTest.php` only
  read connection/database *names* (`getDriverName()`, `getDatabaseName()`)
  for metadata, never rows. `LarastanCastResolutionGuardTest.php` and
  `FilacheckAgentModeGuardTest.php`'s `finally` blocks clean up **temp
  directories** for subprocess probes (phpstan/filacheck), not DB state.
  `CheckDatabaseSettingsTest.php` and `PreflightAlignmentTest.php` invoke
  real artisan commands (`db:check-settings`, `db:preflight-alignment`) that
  are read-only diagnostics by design. `SeedRehearsalEdgesTest.php` invokes
  `db:seed-rehearsal-edges` only in its refusal path (asserts the guard fires
  *before* any table is touched); its other tests assert plain PHP constants.
- **(b) manual state management: 3 files.** `DatabaseSnapshotCommandsTest.php`
  (real `.sql.gz`/`.json` files under `storage/app/db-snapshots/`, `try/finally`
  cleanup at every site); `NonMysqlRefusalTest.php` (deliberately swaps
  `database.default` to an isolated connection inside the test body, restores
  it in `finally` — self-documented in its own docblock as never touching the
  lane connection); `TestLaneResetCommandTest.php` (tests the reset command
  itself against the real lane, `finally`-protected PDO handle).
- **(c) accidental omission that writes rows: 0 files.** None of the 43 files
  call `::factory(`, `->create([`/`::create(` on an Eloquent model, or any row
  mutation method, and the app-invoking subset (`CheckDatabaseSettingsTest`,
  `PreflightAlignmentTest`, `SeedRehearsalEdgesTest`, plus the `ExampleTest.php`
  stub — now correctly reclassified as a RefreshDatabase user) all resolve to
  read-only or refusal-gated paths on inspection.

**R5 verdict: the notes doc's "coverage-honesty risk" — a file that writes
without cleanup, hidden by neighbors' transactions — did not materialize.**
Zero category-(c) files found. No Phase S action required from this
classification; the two detector traps above are worth remembering the next
time anyone greps this suite for trait usage.

### R6. PHPUnit 13 changelog risk memo

WebFetch of `https://github.com/sebastianbergmann/phpunit/blob/13.0.0/ChangeLog-13.0.md`
succeeded (not blocked). Entries relevant to this suite:

| Entry | Category | Touches this suite? | Verdict |
|---|---|---|---|
| PHP 8.3 support removed | Removed | No — this repo runs PHP 8.4.23 | No risk |
| `#[RunClassInSeparateProcess]` removed | Removed | Unchecked — suite uses `tests/Pest.php` extend/`in()`, not PHPUnit process-isolation attributes; grep found none in `tests/` | No risk found, worth a `grep -r "RunClassInSeparateProcess" tests` at actual upgrade time as a final check |
| `#[CoversNothing]` support removed | Removed | No coverage annotations found in this suite (no PCOV/Xdebug installed per the prior research doc) | No risk |
| `Configuration::include/excludeTestSuite()` removed | Removed | `phpunit.xml` uses `<groups><exclude>`, not `testsuite` include/exclude — different mechanism, unaffected | No risk |
| `--dont-report-useless-tests` CLI flag removed | Removed | Not referenced in any composer script or CI config in this repo | No risk |
| `TestCase::invokeTestMethod()` added | Added | New extension point, not currently used | No action, informational |
| `any()` matcher hard-deprecated | Deprecated | No PHPUnit mock objects (`$this->any()`) found in this Pest-style suite | No risk |
| Sealed mock objects / `withParameterSetsInOrder()` | Added | Not used (no PHPUnit-native mocks in this suite) | No action |
| `--test-files-file` CLI option added | Added | Could help if the suite ever needs to pass a very long file list (relevant to future sharding) | Informational, note for Phase S/CI sharding design |
| 8 new array-comparison assertions | Added | Not used yet | No action |

**No entry in this changelog blocks or threatens this suite's current
patterns** (data providers via Pest datasets, `tests/Pest.php`'s `extend()`/
`in()` binding, JUnit XML output, the custom `TestCase::refreshApplication()`
hook). The changelog itself does not mention JUnit-format or
setUp/tearDown-lifecycle changes at all — verified by reading the full
entry list, not assumed. **R6 verdict: no blockers found; not
blocked-needs-retry (the fetch worked and was read in full).**

### R7. Parallelization probes

**(a) `SHOW GRANTS` as the lane user** (measured):

```
GRANT USAGE ON *.* TO `podtext_test`@`127.0.0.1`
GRANT ALL PRIVILEGES ON `podtext\_test`.* TO `podtext_test`@`127.0.0.1`
```

Confirms the grant is scoped to the **exact literal schema** `podtext_test`
(the backslash before the underscore is MySQL's escaping of its own
single-character wildcard — this is an exact match, not a pattern). No
`podtext_test%` wildcard exists today. A parallel worker's database
(`podtext_test_test_1`, etc.) would get **Access denied** under this grant as
it stands — DP1 is a real, unaddressed prerequisite for parallel workers, not
a hypothetical.

**(b) Worker-name regex fit** (measured):

```php
preg_match("/^[a-z][a-z0-9_]*_test(_[0-9]+)?$/", "podtext_test_test_1")   // 1
preg_match("/^[a-z][a-z0-9_]*_test(_[0-9]+)?$/", "podtext_test")          // 1
preg_match("/^[a-z][a-z0-9_]*_test(_[0-9]+)?$/", "podtext_test_test_12")  // 1
preg_match("/^[a-z][a-z0-9_]*_test(_[0-9]+)?$/", "podtext_test_wrong")    // 0
```

The lane's own regex already admits Laravel's parallel-testing naming
convention (`{base}_test_{n}`) and correctly rejects a non-matching name.
This part of the guard needs no change for parallelization.

**(c) Flock conflict** (documented from `tests/Pest.php:39-49` and
`app/Console/Commands/ResetTestLane.php:49-55`, read directly, not
paraphrased from memory): every pest process — including every paratest
worker, since each worker independently bootstraps and includes
`tests/Pest.php` — opens
`storage/framework/testing/mysql-lane-run.lock` with `LOCK_EX | LOCK_NB` and
`exit(1)`s immediately if it can't get it. Today, worker 2 of any parallel
run dies on this line before a single test runs. Phase S's design must make
the lock worker-aware: skip acquisition when `TEST_TOKEN` looks like a
paratest token (paratest sets its own `TEST_TOKEN` per worker — `tests/Pest.php`
already only self-assigns a `p<pid>` token "when absent," so a paratest
token is already distinguishable) and move the *real* mutual-exclusion to a
parent-level lock so workers coordinate through one lock instead of each
grabbing their own. This is DP7's scope.

**(d) Memory estimate** (measured machine facts, estimated worker ceiling —
labelled as an estimate, not a measurement): this machine has **16 GiB RAM**
(`sysctl -n hw.memsize` = 17,179,869,184 bytes) and **8 logical CPUs**
(`sysctl -n hw.ncpu`). At the suite's `-d memory_limit=2G` ceiling, the pure
arithmetic maximum is **8 workers** (16GB / 2GB) — but that leaves zero
headroom for the OS, the MySQL daemon itself, Chrome instances for the
browser workers, and Herd's other services. A realistic recommendation is
**4-6 workers**, which lines up with the 8-core count without oversubscribing
and leaves 4-8GB of headroom. This is squarely an estimate: real workers
rarely saturate the full 2G ceiling, so the true safe number could be higher
in practice — only a real paratest run would measure it.

### R8. DATETIME tie-sensitivity sweep

Grep: `defaultSort|orderBy\(.*desc|assertCanSeeTableRecords` across
tests/Feature+Unit → 75 lines. Narrowed to genuinely order-*sensitive*
assertions (an unordered `assertCanSeeTableRecords([$x])` existence check is
not a tie risk; `inOrder: true` or an explicit `orderBy` comparison is):

- **`inOrder: true`** appears only in `AppOwnedMediaResourceTest.php` (4
  occurrences, lines 1372-1379). Checked the fixtures (`$bee`/`$aleph`,
  lines 1359-1368): sorted by `title` (Hebrew text, "אבטיח" vs "תפוח") and
  `card_stored_filename` ("aaa-file" vs "bbb-file") — **not timestamp
  columns**, and the two values are lexically distinct regardless of
  creation timing. **Not a DATETIME tie risk** — false positive from the
  broad grep, correctly excluded here.
- **`EpisodeListScopeTest.php`** is the file that originally taught this
  suite the tie-break lesson (`ec47df7`, read directly via `git show`): 11
  fixtures created back-to-back landed in the same MySQL `DATETIME` second
  (no fractional-second precision), racing `ListContentItems`'
  `->defaultSort('updated_at', 'desc')` with no secondary tie-break. **This
  file is already fixed**, confirmed by reading current source: the fixture
  helper now calls `test()->travel(1)->seconds()` between each of the 11
  creates, and the one test sampling the full unfiltered set now explicitly
  matches the table's own `orderBy('updated_at', 'desc')` so the comparison
  is order-consistent regardless of ties.
- Only **4 files in the entire Feature suite use `travel()` at all**
  (`FormVerificationManagerTest.php`, `PublicTranscriptionVisibilityTest.php`,
  `EpisodeListScopeTest.php`, `TranscriptionWordCountTest.php`). Cross-checked
  every file matching the order-sensitive grep against this list — after
  excluding the `inOrder: true` false positive above, **no other file both
  (a) creates multiple fixtures in a tight loop and (b) feeds them into a
  timestamp-ordered assertion without `travel()` ticks.**

**R8 verdict: zero new DATETIME-tie flake candidates found beyond the
already-fixed `EpisodeListScopeTest.php`.** The `ec47df7` pattern has no
known unaddressed sibling today. This does not guarantee none exist outside
the three grep patterns used (e.g., a table sorted by `created_at` with no
`defaultSort`/`orderBy` keyword visible in the *test* file because the sort
lives only in the Filament resource definition) — that residual gap is
recorded honestly rather than closed by assumption.

### R9. CI feasibility decision-support (for DP-CI)

**(a) One-shape clause table vs. a GitHub Actions `mysql:8.0` service
container — mapped clause by clause against `app/Support/Testing/TestLaneContract::refusalFor()`
(13 checks: the `default` connection check plus 12 `match` clauses, read
directly from source):**

| Clause | Requirement | CI mapping |
|---|---|---|
| `database.default` | must be `mysql_testing` | Set via the same env/config path as local — no CI-specific weakening |
| driver | must be `mysql` | Service image is `mysql:8.0.x` — ✓ |
| no `url` key | — | Never wired in `config/database.php` regardless of environment — ✓ |
| no `unix_socket` key | — | Same — ✓ |
| database non-empty | — | `MYSQL_DATABASE: podtext_test` on the service, `DB_TESTING_DATABASE=podtext_test` in the job env — ✓ |
| database matches `_test` regex | — | `podtext_test` matches — ✓ |
| database not a raw `.env`/`.env.example` `DB_DATABASE` | — | `.env.example:36` is `DB_DATABASE=podtext` (the app value) — distinct from `podtext_test` — ✓ (verified against the actual checked-in file, not assumed) |
| host is `127.0.0.1`/`::1` | — | GH Actions service containers on the same job are reachable at `127.0.0.1` when ports are mapped — standard, documented pattern — ✓ |
| port explicit numeric | — | Set `DB_TESTING_PORT` to the mapped port — ✓ |
| lane port ≠ app port | — | **Needs deliberate workflow authoring**: since the app's `mysql` connection is never actually queried in tests (only its config values are compared), CI can point `DB_TESTING_PORT` at the real service (e.g. 3306) and set `DB_PORT` (app) to any other number with nothing listening there — satisfiable, but it is a real detail an unwary workflow author could get wrong by leaving both at the `.env.example` default of 3306 |
| username non-empty, ≠ `root`, ≠ app username | — | `MYSQL_USER: podtext_ci` (non-root, distinct from the app's `podtext`) via the image's own init-user mechanism — ✓ |

**Verdict: yes, achievable without weakening a single clause** — every
requirement maps to a standard GH Actions `services:` block plus five
`DB_TESTING_*` env values, mirroring exactly how the local lane is already
configured (`.env.example:44-48` already carries the same five keys as
empty placeholders for this purpose). The one clause needing explicit
author attention (port collision) is a workflow-authoring detail, not a
guard weakening.

**(b) Runner prerequisite inventory:**

| Prerequisite | Status on this dev machine | CI note |
|---|---|---|
| `mysqldump` on PATH | ✓ (Herd's bin) | Herd is macOS-only — a GH-hosted Linux runner needs its own `mysql-client` package; commonly available on `ubuntu-latest` but not universally guaranteed across runner image revisions — **verify with a real dry run**, do not assume |
| `gzip` on PATH | ✓ (`/usr/bin/gzip`) | Present on every mainstream Linux runner image as a base utility — safe to assume |
| Browser deps for `tests/Browser` (12 files) | Chrome via Herd's macOS install | `pestphp/pest-plugin-browser` v4.3.1's own composer requirements are `amphp/http-server`, `amphp/websocket-client`, `ext-sockets` — no bundled browser-automation package, so it drives a **real Chrome/Chromium binary directly via a DevTools-protocol-shaped client**, launched through `symfony/process`. GH-hosted `ubuntu-latest` runners ship Google Chrome preinstalled, but the exact binary-discovery mechanism this plugin uses was not verified from composer metadata alone — **recommend a CI dry-run smoke test** before relying on this |
| `-d memory_limit=2G` | Set explicitly per the house convention | GH-hosted standard runners carry ~7GB RAM — trivially satisfiable |
| Fingerprint first-use flow | Empty schema + fresh fingerprint today (Task 7 reset) | Works by construction on CI: a fresh service container starts with zero tables, so every CI run takes the "first-use, verify 0 tables, write fingerprint" branch of `TestCase::assertDisposableSchema()` — no special handling needed |

**(c) Wall time and sharding:** R1 measured **622.2s single-threaded today**
(supersedes the brief's "~10 min" placeholder with a real number). A GH
Actions **job-level matrix** (N separate jobs, each with its own mysql
service container and a disjoint slice of test files/paths) sidesteps the
in-process flock/grant problems R7 documents entirely — no shared lane,
because each job is a separate machine. This is a materially simpler path to
CI parallelization than the in-repo paratest design R7 describes, and worth
recording as a design option distinct from DP1/DP2/DP7 (which are about
*local* paratest workers sharing one lane).

**(d) Recommendation table:**

| Item | Recommendation | Rationale |
|---|---|---|
| Guard clause mapping | **implement-in-S** | Fully satisfiable per (a); the workflow YAML itself is new code appropriately owned by whichever phase first writes a pipeline file (R produces no pipeline file per its own scope) |
| `mysqldump`/`gzip` prerequisite | **implement-in-S** (verify, don't assume) | Cheap to add an explicit `apt-get install -y mysql-client` step defensively; do not gate CI on an assumption |
| Browser/Chrome prerequisite | **defer-post-U, dry-run first** | The exact binary-discovery mechanism is unverified; a real smoke-test run is cheaper and more honest than a static-analysis guess, and browser tests are also the ones this task's own R4 findings say need shakedown work first |
| Job-level matrix sharding | **record local-only-decision** (design note for whenever CI lands) | Not measured or built this round — an architectural observation for whoever writes the pipeline, not a Phase R/S deliverable |

### R10. Deferred-items re-assessment

Every task-review Minor from the progress ledger (Tasks 1-6), F8/F9, DP9, the
browser single-read family, and the suite-touching `defect-cause-patterns.md`
entries — one table, one verdict each.

| Item | Source | Verdict | Reasoning |
|---|---|---|---|
| Contract comment staleness ("Same value on both drivers") | Task 1 Minor | **fix-in-S (opportunistic)** | Verified at `app/Auth/LegacyRoleBackfill/LegacyRoleBackfillSchemaContract.php:108` — a leftover reference to the pre-alignment sqlite-vs-mysql comparison; the suite is mysql-only now (`driver-lenient-fallback` closed 2026-08-09), so "both drivers" is stale. One-phrase edit, zero risk; bundle into whatever next touches this file rather than a dedicated task |
| `dropStatements` backtick escaping (strip vs. double) | Task 5 Minor | **keep-deferred** | Verified at `ResetTestLane.php:119-125`: `str_replace('`', '', ...)` strips rather than doubles embedded backticks. Currently unreachable — `$database` is pre-validated against `TestLaneContract`'s regex (no backticks possible) and `$tables` come from `information_schema.TABLES` (real MySQL identifiers, never backtick-bearing under this repo's naming conventions). Doubling would be more technically correct but nothing can trigger the gap today |
| Views survive reset + fingerprint-delete ordering | Task 5 Minor | **keep-deferred** | Verified at `ResetTestLane.php:79`: the DROP loop filters `TABLE_TYPE = 'BASE TABLE'` only, so a VIEW would survive a reset while the command still reports "emptied." Zero views exist today (measured fact, Task 5 of the alignment plan) — moot until the schema ever gains one. Fingerprint-delete ordering (tables dropped, then fingerprint removed) is safe as-is: even a failed mid-drop leaves a state that the next real test's own `migrate:fresh` unconditionally cleans regardless |
| `unlink()` result ignored | Task 5 Minor | **keep-deferred** | Verified at `ResetTestLane.php:96`: runs only after tables are already dropped, so a failed unlink leaves a stale fingerprint file with no functional consequence — worst case the next boot takes the slightly-more-thorough "fingerprint exists" branch instead of "first-use," both of which pass cleanly on a genuinely empty schema |
| `fopen`-failure message conflation | Task 5 Minor | **keep-deferred** | Diagnostic-message quality only (conflates two distinct failure causes into one string); no behavioral stake |
| Fixture fixed-filename in real snapshot dir | Task 6 Minor | **keep-deferred (revisit if DP2 flips)** | Named risk is real (glob/`--latest` interaction + parallel collision) but only reachable once parallel execution is actually live; DP2's own default is "opt-in first," so this is not live risk today |
| Describe-block placement (new tests outside the block) | Task 6 Minor | **keep-deferred** | Pure organizational nit, zero behavior |
| `LANE` const (repeated `'mysql_testing'` literal) | Task 5 Minor | **keep-deferred (cheap win if touched)** | Verified: `'mysql_testing'` appears 3 times un-extracted in `ResetTestLane.php` (lines 38, 76, 111). A real but low-probability drift class (the repo's own ledger names exactly this pattern as `one-home`) — cheap to fix whenever this file is next opened, not worth a dedicated task |
| `+00:00` literal coupling | Task 6 Minor | **keep-deferred** | Already has a mitigating canary per its own recorded description; risk is covered, not unaddressed |
| `handle()` guard extraction | Task 5 Minor | **keep-deferred** | Verified: `ResetTestLane::handle()` is ~70 lines of sequential guard checks; a readability refactor with zero behavior change. Not worth review overhead on its own |
| F8 (`possible_keys` vs `key`) | `open-findings-triage.md` | **keep-deferred (accepted-forever)** | Already labelled accepted-forever in its own text: asserting `key` would pin an optimizer tie-break MySQL does not guarantee on near-empty fixture tables. No new data this round changes that |
| F9 (per-boot COLUMNS count) | `open-findings-triage.md`, paired with R3 | **closed by this round's measurement** | R3 measured ~1.0s/suite (0.16% of 622.2s wall) — F9's own "pending one measurement" condition is now satisfied and negative. Stays `accepted`, no Phase S action |
| DP9 (`DB_CONNECTION` default `sqlite` → `mysql`) | `open-findings-triage.md` F11 | **fix-in-S — recommend `mysql`** | `config/database.php:20` is `env('DB_CONNECTION', 'sqlite')` today. A missing env key should fail loudly against a credentialed daemon it can reach, not silently open a throwaway file — nothing in this round's findings argues against the loud-beats-silent default |
| `CardTemplatePreviewBrowserTest:663` single-read flake | R4/state note | **fix-in-S** | Confirmed exact mechanism by reading the file: line 645 computes `horizontal_overflow` as a single unwaited read, line 663 asserts on it. Fix: wrap in a stability-waited condition (N consecutive equal reads, or a settle delay) rather than a bare post-condition read |
| R4's sibling single-reads (54 total `horizontal_overflow` occurrences across 4 files, plus adjacent bare `getBoundingClientRect()` reads) | R4 | **fix-in-S** | Same mechanism, same fix shape, concentrated in `CardTemplatePreviewBrowserTest.php` (28 of 54) — matches `single-read-race`'s own prior prediction (below) exactly |
| `single-read-race` (defect-cause-patterns.md) | ledger, closed 2026-08-04 with a named future candidate | **fix-in-S — the predicted sweep has now arrived** | The ledger's own closing note named "CardTemplatePreview presence-polls" as a later file-wide candidate when the pattern was first closed elsewhere. R4's 54-occurrence count is that prediction materializing; folds into the row above |
| `driver-lenient-fallback` (defect-cause-patterns.md) | ledger | **keep-deferred — already closed** | Closed 2026-08-09: the suite is mysql-only now, the guard learned the second shape. No suite action remains; carried here only to confirm it is not a live gap |
| `db-clock-coupling` (defect-cause-patterns.md) | ledger | **keep-deferred — already closed** | Closed 2026-08-09: alignment migration ran, connection pins `+00:00`. No suite action remains |
| `fake-root-purge` (defect-cause-patterns.md) | ledger | **keep-deferred — already fixed** | `TestDiskIsolationTest` pins the per-process fake-disk-root token; no residual |
| `test-residue` (defect-cause-patterns.md) | ledger | **keep-deferred — already closed** | Census executed, fixture renames landed; no residual |

**R10 gate note:** every row above got a verdict; none were silently
skipped. The operator's R-gate call turns the **fix-in-S** rows (contract
comment, DP9 default flip, the `CardTemplatePreviewBrowserTest` single-read
family) into Phase S's concrete checklist; everything else is recorded as
consciously deferred with a reason, not dropped.

### Decision points from this round

- **DP1** (widen the lane grant to `podtext_test%` for parallel workers):
  unchanged from the notes doc's default ("only if the spike's numbers
  justify it") — R7(a) confirms the grant is narrow today (exact-schema
  match, no wildcard), but R7(c)'s flock conflict means parallel workers
  cannot even start yet regardless of grant width. Grant-widening is the
  *second* blocker, not the first.
- **DP2** (parallel as default runner vs. opt-in flag): unchanged
  ("opt-in first"), now backed by R7's concrete confirmation that today the
  flock design kills worker 2 outright and the grant would deny worker
  connections even if it didn't.
- **DP3** (install a coverage driver for TIA): unchanged ("defer to the Pest
  5 phase") — this round's measurements (R1-R10) did not touch coverage
  tooling; no new data either way.
- **DP-CI** (CI feasibility): **implement-in-S** for the guard-clause mapping
  and the `mysqldump`/`gzip` prerequisite (both fully resolved, no
  weakening required); **defer-post-U, dry-run first** for the browser/Chrome
  prerequisite (genuinely unverified, and R4 already flags the browser
  suite for shakedown work before it should be trusted in a new
  environment); job-level matrix sharding recorded as a **local-only design
  note** for whoever eventually writes the pipeline file.
- **DP9** (config default `DB_CONNECTION` flip): **recommend `mysql`**,
  unchanged from the F11 proposal — nothing measured this round argues
  against loud-beats-silent, and `config/database.php:20`'s current
  `env('DB_CONNECTION', 'sqlite')` is the last live sqlite-shaped default in
  the config after F11's file/composer cleanup.

### Sources for this section

- `storage/framework/testing/junit-profile.xml` (R1, generated and deleted
  this session)
- `app/Support/Testing/TestLaneContract.php`, `tests/TestCase.php`,
  `tests/Pest.php`, `app/Console/Commands/ResetTestLane.php` (read directly)
- `docs/phase-02/open-findings-triage.md` (F8, F9, F11)
- `docs/research/defect-cause-patterns.md` (`single-read-race`,
  `driver-lenient-fallback`, `db-clock-coupling`, `fake-root-purge`,
  `test-residue`)
- `.superpowers/sdd/progress.md` (Test-Suite Rethink Tasks 1-7 Minors)
- `phpunit.xml`, `.env.example`, `config/database.php`, `composer.lock`
- WebFetch: `sebastianbergmann/phpunit` ChangeLog-13.0.md (succeeded, read in
  full); `pestphp/pest-plugin-browser` releases (succeeded — confirmed empty,
  cross-checked via `gh api` and a `CHANGELOG.md` existence check)
- `sysctl -n hw.memsize` / `hw.ncpu` on this machine (R7d)
