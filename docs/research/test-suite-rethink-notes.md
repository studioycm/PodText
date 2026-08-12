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
- **The run-lock is no longer opaque** (`d32da0d`, 2026-08-12). Its holder
  stamps one JSON line into the lock file on acquiring —
  `{state, pid, label, lane, started_at, released_at}` — and rewrites it as
  `released` on normal exit; the label carries the TREE as well as the argv,
  because the lock is machine-global and two worktrees produce identical
  command lines. `cat ~/.cache/podtext-test-lane/*.lock` now answers "who has
  the lane" with no hash to compute and no `pgrep`. flock remains the
  authority and the record is advisory: a dead holder's lock is released by
  the OS so there is nothing to reap, and because the holder stamps `held`
  before doing anything else, a stale record can only be wrong in the safe
  direction. A blocked run now exits **75** (EX_TEMPFAIL) and prints a banner
  plus a `"result":"refused"` JSON line on STDOUT — closing the hazard where
  an agent filtering stdout read a STDERR-only refusal as a silent pass.
  The sibling "could not open the lock file" branch was closed the same day
  (`3330fe7`) with a DIFFERENT code — 73 (EX_CANTCREAT), not 75 — because 75
  invites a retry and retrying an unwritable lock root loops on a condition
  waiting cannot fix; a test pins the separation.
  Design lineage, since the shape was argued rather than obvious: the F13
  session proposed a separate holder-registry file, and the settings-backup
  session refined it to what shipped — no second artifact, enrich the
  existing lock, name the holder, make the refusal unmissable. Both had hit
  the gap from opposite sides the same evening, one as a writer who could not
  see the lane was busy, one as a runner who could not tell a refusal from a
  silent pass.
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
- **TIA (Pest 5's headline) had a hard local prerequisite: no coverage
  driver.** ~~Measured 2026-08-10: neither pcov nor xdebug loaded, no .so~~ —
  since closed (Phase U, 2026-08-12): the operator chose Xdebug (DP3) and it
  is installed from Herd's bundled `xdebug-84-arm64.so`, `mode=off` default,
  coverage verified under `XDEBUG_MODE=coverage` (see `## Phase U record`
  §U6). The cache-location question is answered (machine-global
  `~/.pest/tia/<project-key>`, 3.9b) — the two-sessions-one-graph *behavior*
  remains the open pre-adoption experiment.
- **Time-balanced sharding** — CI-day concern; there is no CI. Parked — with
  one status correction (2026-08-10, Pest 5 research session): it is **not
  Pest-5-gated**. The installed 4.7.8 already ships it (`Shard.php`:
  `--shard`, `--update-shards`, the `tests/.pest/shards.json` staleness
  warning; `laraveldaily/pest5-notes.md` §1f). Idle by choice, not by gate.
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

## 6. Pest 5 timing (operator decision, 2026-08-10) — EXECUTED 2026-08-12

**"Pest 5 + plugins only at the end and maybe in a separate session — wait
for approval."** The upgrade ran as the rethink's final phase in a dedicated
session, gate opened by the operator's direct answer on 2026-08-12
(`5eaa7bb`/`8617bb2`/`bf605db`; full record in `## Phase U record` below).
The gated list that follows is preserved as the historical scoping record:

- **Gated behind it:** `pest-plugin-phpstan` (the written-down level-6 path
  in `phpstan.neon`), `pest-plugin-rector` (test-style rules), TIA execution,
  `--agent`, `toBeUlid()`. (Time-balanced sharding was originally listed here
  too, but it turned out to ship in the installed 4.7.8 — see §3's parked
  note; not v5-gated.)
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
| 5 | `pest-plugin-browser` major bump (4.3.1 → 5.0.x) | `composer.lock`; vendor `Playwright/InitScript.php` and `Playwright/Page.php` | Zero upstream release notes exist for this bump (verified three ways), so the browser shakedown is the only net. The `__pestBrowser` accumulator touchpoint is nevertheless PROVEN UNMOVED: `InitScript.php` hashes sha256 `fb322d0c4c48…` identically across the installed 4.3.1, upstream v4.3.1 and upstream v5.0.1 — the three-source check also proving the installed copy carries no local vendor drift — and `Page.php`'s readers are unmoved at `:459`/`:493`. Phase U's plan (`7d09614`) re-derives that hash from the INSTALLED artifact post-upgrade (Task 1 Step 4b, unconditional); a mismatch routes to reviewing the test-side lines before the shakedown. The pin compares against a constant, so it survives resolution past 5.0.1 |
| 6 | Classified ResizeObserver artifact vs. bare `assertNoJavaScriptErrors()` | MediaPickerUploadFocusReturn (3 bare sites, the ~1-in-2 first-run failure), MediaPicker (6), MediaResourceGallery (3), MediaPickerCloneRepro (1, a *different* accumulator) | **Fixed 2026-08-12 (`d84b1c7`)** — the filter now has one home in `tests/Pest.php`, strict exact-equality and counted, pinned by `JavaScriptErrorArtifactFilterBrowserTest` (isolation exercises the filter zero times, so the suite alone could never prove it). `CardTemplatePreviewBrowserTest` is untouched (4 literal declarations under 2 variable names) because it *measures* the artifact into asserted payloads rather than strip-then-asserting — a stripping helper would break it; a measurement-shaped consolidation there stays open. See `open-findings-triage.md` §F13 |
| 7 | `visit()`'s return type is not what call sites suggest | Every `tests/Browser/` call site; shared browser helpers in `tests/Pest.php` | `visit()` returns `PendingAwaitablePage`; it becomes `AwaitableWebpage` only after a call such as `->resize()`. Every existing browser call site happens to arrive post-resize, so a narrowly-typed shared helper passes the entire suite and then TypeErrors on the first new caller who writes a plain `visit()`. Cost one failing run to find during F13 and is invisible to grep-based auditing — shared browser helpers must accept both types |
| 8 | **The four tests that fail FIRST under load — a canary list, not a defect list** | `CardTemplatePreviewBrowserTest` (2 tests), `MediaPickerUploadFocusReturnBrowserTest` (1), `MediaResourceGalleryBrowserTest` (1 test × Hebrew and English datasets = 2 failures) | Surfaced by the TIA/3.9b session under a **4.73× Xdebug slowdown**, and each passed immediately on re-execution without it — so these are **load-sensitive, not broken**. The failure shapes map exactly onto the families above: navigation race (`Execution context was destroyed, most likely because of a navigation`), Alpine state-read (`label_hidden_after_off: false`), focus choice (`kept_choice: false, active_element: media-picker-source-upload`), and hard timeout (`Timeout 30000ms exceeded`, both datasets). Cross-referenced here rather than left inside the TIA section so it is found by someone debugging browser flake, not only by someone evaluating TIA — full names and messages in `~/.cache/podtext-coord/upstream-pest/LEFTOVERS.md` (L2). **Consequence for any future TIA adoption (L9):** a recording run is the only way to build a graph, costs 28.5 min, and exits non-zero on exactly these — so an adopter's first experience is a red gate whose correct response is "ignore those four". That trains people to disregard browser reds. Harden these before adopting, rather than maintaining an expected-failure list that rots |

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
| `dropStatements` backtick escaping (strip vs. double) | Task 5 Minor | ~~keep-deferred~~ → **SHIPPED `e9ac5cf`** (now doubles) | Verified at `ResetTestLane.php:119-125`: `str_replace('`', '', ...)` strips rather than doubles embedded backticks. Currently unreachable — `$database` is pre-validated against `TestLaneContract`'s regex (no backticks possible) and `$tables` come from `information_schema.TABLES` (real MySQL identifiers, never backtick-bearing under this repo's naming conventions). Doubling would be more technically correct but nothing can trigger the gap today |
| Views survive reset + fingerprint-delete ordering | Task 5 Minor | **keep-deferred** | Verified at `ResetTestLane.php:79`: the DROP loop filters `TABLE_TYPE = 'BASE TABLE'` only, so a VIEW would survive a reset while the command still reports "emptied." Zero views exist today (measured fact, Task 5 of the alignment plan) — moot until the schema ever gains one. Fingerprint-delete ordering (tables dropped, then fingerprint removed) is safe as-is: even a failed mid-drop leaves a state that the next real test's own `migrate:fresh` unconditionally cleans regardless |
| `unlink()` result ignored | Task 5 Minor | ~~keep-deferred~~ → **SHIPPED `e9ac5cf`** (`is_file() && ! unlink()` guard, `ResetTestLane.php:110`) | Verified at `ResetTestLane.php:96`: runs only after tables are already dropped, so a failed unlink leaves a stale fingerprint file with no functional consequence — worst case the next boot takes the slightly-more-thorough "fingerprint exists" branch instead of "first-use," both of which pass cleanly on a genuinely empty schema |
| `fopen`-failure message conflation | Task 5 Minor | ~~keep-deferred~~ → **SHIPPED `e9ac5cf`** (honest failure messages) | Diagnostic-message quality only (conflates two distinct failure causes into one string); no behavioral stake |
| Fixture fixed-filename in real snapshot dir | Task 6 Minor | **keep-deferred (revisit if DP2 flips)** | Named risk is real (glob/`--latest` interaction + parallel collision) but only reachable once parallel execution is actually live; DP2's own default is "opt-in first," so this is not live risk today |
| Describe-block placement (new tests outside the block) | Task 6 Minor | **keep-deferred** | Pure organizational nit, zero behavior |
| `LANE` const (repeated `'mysql_testing'` literal) | Task 5 Minor | ~~keep-deferred~~ → **SHIPPED `e9ac5cf`** (`private const LANE` extracted) | Verified: `'mysql_testing'` appears 3 times un-extracted in `ResetTestLane.php` (lines 38, 76, 111). A real but low-probability drift class (the repo's own ledger names exactly this pattern as `one-home`) — cheap to fix whenever this file is next opened, not worth a dedicated task |
| `+00:00` literal coupling | Task 6 Minor | **keep-deferred** | Already has a mitigating canary per its own recorded description; risk is covered, not unaddressed |
| `handle()` guard extraction | Task 5 Minor | **keep-deferred** | Verified: `ResetTestLane::handle()` is ~70 lines of sequential guard checks; a readability refactor with zero behavior change. Not worth review overhead on its own |
| F8 (`possible_keys` vs `key`) | `open-findings-triage.md` | **keep-deferred (accepted-forever)** | Already labelled accepted-forever in its own text: asserting `key` would pin an optimizer tie-break MySQL does not guarantee on near-empty fixture tables. No new data this round changes that |
| F9 (per-boot COLUMNS count) | `open-findings-triage.md`, paired with R3 | **keep-deferred (closed: R3 measured ~1.0s/suite = 0.16% of wall — the accepted-forever label is now evidence-backed)** | R3 measured ~1.0s/suite (0.16% of 622.2s wall) — F9's own "pending one measurement" condition is now satisfied and negative. Stays `accepted`, no Phase S action |
| DP9 (`DB_CONNECTION` default `sqlite` → `mysql`) | `open-findings-triage.md` F11 | **fix-in-S — recommend `mysql`** | `config/database.php:20` is `env('DB_CONNECTION', 'sqlite')` today. A missing env key should fail loudly against a credentialed daemon it can reach, not silently open a throwaway file — nothing in this round's findings argues against the loud-beats-silent default |
| `CardTemplatePreviewBrowserTest:663` single-read flake | R4/state note | **fix-in-S** | Confirmed exact mechanism by reading the file: line 645 computes `horizontal_overflow` as a single unwaited read, line 663 asserts on it. Fix: wrap in a stability-waited condition (N consecutive equal reads, or a settle delay) rather than a bare post-condition read |
| R4's sibling single-reads (54 total `horizontal_overflow` occurrences across 4 files, plus adjacent bare `getBoundingClientRect()` reads) | R4 | **fix-in-S** | Same mechanism, same fix shape, concentrated in `CardTemplatePreviewBrowserTest.php` (28 of 54) — matches `single-read-race`'s own prior prediction (below) exactly |
| `single-read-race` (defect-cause-patterns.md) | ledger, closed 2026-08-04 with a named future candidate | **fix-in-S — the predicted sweep has now arrived** | The ledger's own closing note named "CardTemplatePreview presence-polls" as a later file-wide candidate when the pattern was first closed elsewhere. R4's 54-occurrence count is that prediction materializing; folds into the row above |
| `driver-lenient-fallback` (defect-cause-patterns.md) | ledger | **keep-deferred — already closed** | Closed 2026-08-09: the suite is mysql-only now, the guard learned the second shape. No suite action remains; carried here only to confirm it is not a live gap |
| `db-clock-coupling` (defect-cause-patterns.md) | ledger | **keep-deferred — already closed** | Closed 2026-08-09: alignment migration ran, connection pins `+00:00`. No suite action remains |
| `fake-root-purge` (defect-cause-patterns.md) | ledger | **keep-deferred — already fixed** | `TestDiskIsolationTest` pins the per-process fake-disk-root token; no residual |
| `test-residue` (defect-cause-patterns.md) | ledger | **keep-deferred — already closed** | Census executed, fixture renames landed; no residual |
| Task 1 test asymmetry — `issues()` short-circuit exercised with one non-mysql value vs `expected()`'s two | Task 1 Minor | **keep-deferred (both guards reduce to the same $driver !== 'mysql' comparison; negligible marginal risk)** | |
| Task 5 — session settings (`lock_wait_timeout`, FK checks) lost if Laravel reconnects mid-drop | Task 5 Minor | **keep-deferred (informational: the finally re-enable is harmless, the process exits right after; recorded in the Task 5 review)** | |
| Task 6 — newline-free dump accumulates the whole file in the carry buffer | Task 6 Minor | **keep-deferred (mysqldump's net_buffer_length caps physical lines near 1MB; unbounded only for hand-built dumps, which the typed confirmation already gates)** | |

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

## Phase U record (2026-08-12)

Executed by the dedicated Phase U session (operator-gated open, 2026-08-12).
Commits: `5eaa7bb` (browser evidence-script fix) → `8617bb2` (the composer
batch) → `bf605db` (pest-rector dry-run report). Every number below was
measured by that session on this machine; provenance named per item.

### U1 — composer batch

`pestphp/pest` ^4.7→**^5.1** (5.1.0), `pest-plugin-browser` **5.0.1**,
`pest-plugin-laravel` **5.0.1**, `pest-plugin-drift` **5.0.0**; new dev deps
`pest-plugin-phpstan` **5.0.2**, `pest-plugin-rector` **5.0.3**. PHPUnit
**13.3.0**, paratest 7.24.0, pao 1.1.4 (the phpunit-13-compatible line).
Resolution was conflict-free in both dry-run forms; the narrow
`--with-dependencies` form was chosen — framework 13.24, commonmark 2.9 and
carbon deliberately held (a test-tooling upgrade should not carry a framework
minor as a passenger). blueprint 2.2.0 / FilaCheck 1.2.5 / FilaCheck Pro
1.2.7 / larastan 3.10.0 untouched. The feared constraint conflicts (spec risk
5) did not exist. Plan-sequencing lesson: the meaningful dry-run needs the
pins already edited into composer.json — a dry-run against the old `^4.7`
pins cannot move pest and silently reports only the non-pest ride-alongs.

### U2 — run-lock lifetime re-proven

`BootFiles.php` is code-identical to v4 (docblock deletions only; the
`tests/Pest.php` include still runs in the `load()` method scope, so the GC
trap and the `$GLOBALS` fix both stand). Mid-run two-process probe
(final-review-fixes §1 pattern, 20s sleep): lock held at T+20s of a 31.1s
five-file suite (`false` + `alive`), second pest refused exit 1 with the lane
message, suite exit 0, lock released after (`true`). One probe-design
correction en route: the original single-file filter (`SettingsSp3aTest`,
70.4s in R1) now runs in **2.3s** — S2b's snapshot-queue fake collapsed it
after R1's measurement — so the first probe run went inconclusive
(suite ended before the 20s sleep) and the background suite was restacked
from five S2b-untouched files. R1 durations are stale as probe-sizing input.

### U3 — browser shakedown: one real regression, found, diagnosed, fixed

First full `tests/Browser` run: 48/58, 10 failures across three shapes
(Illegal-invocation TypeErrors, a `ReferenceError: uploading` flood with
evidence timeout, and state/measurement failures incl. `network_requests: 85`
where 1 is asserted). Attribution was free — the pre-upgrade baseline ran the
same 58 green on the same machine/node/app an hour earlier. Diagnosis
(refuted-theory trail in the session log: asset truncation → eval-world
strictness → page visibility, each killed by direct evidence):
**`Execution::waitForExpectation` caps each retry attempt at a hardcoded
1,000ms (`Playwright::usingTimeout(1_000, $callback)`); under 4.3.1 +
Playwright ≥1.62 that cap was dead (timeout rode in protocol params the
server ignores) and plugin 5.0.1's metadata fix revived it.** Every evidence
script longer than 1s was killed PHP-side mid-run — while its page effects
landed — re-executed for the whole 30s window, and the loop's final
*uncapped* `return $callback()` returned measurements of a page mangled by up
to 30 partial predecessors. Proven with an in-page attempt counter:
`diag_attempt: 31`, no JS error ever thrown. Fix (`5eaa7bb`): all **109**
`->script(` sites → `->page()->evaluate(` — semantically identical
(`Webpage::script()` is literally `page->evaluate()`), single attempt, real
30s action budget, and backward-compatible with 4.3.1. Verified:
CardTemplatePreview 14/14 ×3 (113.2/113.3/113.3s; was 7 failures at 311s),
browser suite 58/58 at 164s (R1 share was 159s), excluded groups green
(rtl-board 1/1, compiled-sentinels 6/6). **Upstream: FILED as
[pestphp/pest#1852](https://github.com/pestphp/pest/issues/1852)** (by the
orchestrator session, 2026-08-12, claims re-verified in vendor source before
posting; the plugin repo has issues disabled, hence the core repo). The
filing adds a consequence this record had not named: the per-attempt cap is
constant while the loop bound is the configured timeout, so **raising the
timeout buys more 1s-truncated retries, not more time, and setting it
≤1000ms disables the retry wrapper entirely** (both verified against
`AwaitableWebpage::__call`'s `Playwright::timeout() <= 1000` direct path and
`waitForExpectation`'s loop bound). Two trade-offs recorded:
`page()->evaluate()` skips the wrapper's post-action server-exception check
(every test still runs awaitable assertions after, which perform it), and
scripts lose retry semantics they should never have had.

### U4 — pest-plugin-phpstan (DP5: measured, answered **record + defer**)

Auto-registration via `phpstan/extension-installer` proven on this tree
(GeneratedConfig lists both `pestphp/pest` and `pestphp/pest-plugin-phpstan`;
pest core registered an extension in v4 already — tiny, two
universalObjectCratesClasses). Counts (all with the plugins loaded, `-v`
against the agent-formatter truncation):

| config | errors | delta |
|---|---|---|
| level 5, app-only (current phpstan.neon) | **450** | +7 vs the 443 backlog — git-blame-attributed to `4da7542`/`1443b7d`/`476c508` (settings-backup line, post-dating the 443 measurement); **the upgrade itself moved the app-side count by zero** |
| level 5, app+tests | **1,150** | tests/ adds +700: **452 are five mechanical dynamic-API families** (Livewire tester 201, browser-plugin Webpage 88, Mockery high-order 88, TestResponse macros 59, PendingAwaitablePage 16 — stub-teachable, the filament-macros.stub precedent) + **37 genuine `pest.expectation.redundant` findings** + remainder |
| level 6, app-only | **929** | level 6 costs +479 app-side |
| level 6, app+tests | **2,367** | the documented ~426 estimate (`phpstan.neon:86` comment) was off **5.6×** |

Operator answered DP5: **record + defer** — no `phpstan.neon` change; a
future wiring batch starts by writing the five stubs, wires tests/ at level
5, then climbs.

### U5 — pest-plugin-rector dry-run

`docs/research/rector-dry-run-reports/2026-08-12-pest-coding-style.md`:
22 files / 133 errors, byte-identical across two cold serial runs; the 133
are the documented §0b larastan-boot family in per-test-file guise. Verdicts
0 adopt / 4 defer / **1 reject — `SimplifyToLiteralBooleanRector` rewriting
`expect($offenders)->toBe([])` into `toBeEmpty()` in two guard tests is an
assertion weakening** (`toBeEmpty()` passes `''`/`null`/`0`); the DP4 posture
(dry-run-locked, per-rule approval) caught exactly the hazard it was built
for, now evidenced. `rector.php` restored byte-identical;
`RectorScriptContractTest` green in the covering gate.

### U6 — TIA prerequisites (DP3: answered **Xdebug**, installed)

Operator chose Xdebug over PCOV (PhpStorm's built-in integration; also the
zero-compile path — Herd bundles prebuilt .so files through PHP **8.5**,
though the docs page lists only through 8.3). Installed per the Herd docs:
ini at `~/Library/Application Support/Herd/config/php/84/php.ini` (backup
taken: `php.ini.pre-xdebug-backup-2026-08-12`), `zend_extension=` the bundled
`xdebug-84-arm64.so`, **`xdebug.mode=off` default** so normal runs pay zero
overhead; TIA recording opts in per-run via `XDEBUG_MODE=coverage`
(functionally verified: `xdebug_get_code_coverage()` returns data under the
env, extension loaded, suite-visible overhead none at mode=off). Loaded build
is **v3.4.0alpha2-dev** — Herd ships a prerelease for 8.4; watch it on Herd
updates. **TIA's driver prerequisite is gone.** Still standing before replay
is trusted: the 3.9b two-session shared-graph contention question
(`~/.pest/tia/<project-key>`, machine-global, keyed by git remote). Adoption-
day config shape (doc-verified): `pest()->tia()->locally()`, no
`->baselined()` (needs gh auth + CI artifacts; no CI), built-in
Laravel/Livewire/Blade/browser watch defaults; `--baseline` only prints the
storage path.

### U7 — gate

Pre-upgrade baseline (this session, at `174531c`, code tree `09f7323`):
2,012 / 20,991 / 364.8s + pint + FilaCheck 35/35 — independently
cross-checked by the F13 session's three runs on the same code (345.8–366.0s,
identical counts). Post-upgrade gate (at `5eaa7bb`+dirty composer, committed
as `8617bb2`): **2,012 / 20,991 / 370.1s**, pint clean, FilaCheck 35/35,
all three named guard tests green. Test-count delta from Pest 5: **zero**.
Wall-time delta: +1.5% (noise). The pest binary self-reports "5.0.5" while
composer.lock holds 5.1.0 — upstream version-constant lag, cosmetic; the
lock is authoritative.

### Residuals out of this phase

- ~~Upstream filing decision~~ — filed as pestphp/pest#1852 (see U3);
  watching for a maintainer reply is the residual.
- TIA first baseline + the 3.9b contention experiment — designed follow-on,
  not started.
- DP5's five stub surfaces + 37 genuine pest findings — the future wiring
  batch's opening work-list.
- The +7 phpstan drift (450 vs 443) — routed to the orchestrator (settings-
  backup files, not this phase's).
- Herd's xdebug 8.4 build is an alpha — recheck on Herd updates.


## TIA — measured, not adopted (2026-08-12)

Executed by the dedicated 3.9b session (operator-approved follow-on). Measured on
**pest 5.1.0 / PHPUnit 13.3.0**, Xdebug **3.4.0alpha2-dev**, at HEAD **`cbf4479`**,
working tree clean and verified clean for the duration of both runs. Every mechanism was
read in installed vendor source and then measured; where a prior claim and the source
disagreed, the source plus a measurement won.

**Verdict: DO NOT ADOPT on 5.1.0.** Not for lack of payoff — the payoff is 181× — but
because a run can record a green result for code it never executed, and the runs that can
do it are as short as one second.

### T1 — what TIA buys (JOB 1)

Baseline: **2,027 / 21,026 / ~362s**, measured at `cbf4479` by the lane-lock session's own
gate. TIA cannot run with a PHPUnit-class test present (T4a), so all TIA runs used
**2,025 / 21,024** — that baseline minus exactly the 2 excluded skeleton tests. **2,025 is
a config artifact of that exclusion at `cbf4479` on 2026-08-12, not a suite count**; the
external `-c` config remains necessary to reproduce these numbers at that sha, even though
future runs will not need it. (The suite has since moved: 2,029 / 21,037 / 366.7s at
`3330fe7`/`505f043`.)

| run | wall clock | vs baseline | outcome |
|---|---|---|---|
| recording (`--tia`, `XDEBUG_MODE=coverage`) | **1,713s (28.5 min)** | **4.73× slower** | exit 1 — 5 browser failures |
| replay #1 (5 tests due for re-run) | **28s** | **12.9× faster** | exit 0 — 2,025 passed |
| replay #2 (steady state) | **2s** | **181× faster** | exit 0 — 2,025 passed |

**Byte-comparability: confirmed.** Both replays reported 2,025 / 21,024 — identical to each
other and to the baseline minus the exclusions — and all green, matching a real gate run.

**The 5 recording failures are Xdebug timing casualties, not defects.** All were browser
tests: two literal `Timeout 30000ms exceeded`, the rest Alpine/Livewire state and focus
assertions. Replay #1 re-executed all 5 without Xdebug and every one passed, flipping the
graph from 5×status-7 to 2,025×status-0. So **recording a baseline produces a false red in
exactly the tier this program documented as wait-fragile** (the 1s-cap regression,
[pest#1852](https://github.com/pestphp/pest/issues/1852)). Anyone recording will see red
and must know to disregard it — that belongs next to the browser-wait discipline, not in a
TIA footnote.

**The 2s figure is a replayed claim, not evidence.** It reports "2,025 passed" having
executed essentially nothing. That is the design, and it is why the graph's
trustworthiness is the whole question.

The Unit slice predicted a 3.67× Xdebug tax (3.15s → 11.57s); the suite came in at 4.73×,
because Xdebug slows the PHP side of every Livewire round-trip the browser tier waits on —
that wall time does not stay I/O-bound.

### T2 — the hazard, and which runs can cause it (JOB 2)

Scaffolded a throwaway git project outside the repo with its own pest install and its own
cache key — the approach pest's own TIA scenario tests use. Timing by **file handshake**,
not wall-clock sleeps, so "a commit lands mid-run" is deterministic rather than a race.

What is safe, confirmed behaviourally: **uncommitted edits by anyone** (`workingTreeChanges()`
runs `git status --porcelain -z --untracked-files=all` every run); **edits committed before
a run** (`git diff --name-only <recordedSha>..HEAD` — the affected test re-ran and failed
correctly); and **comment/whitespace-only edits**, hash-normalised to zero reruns by
`ContentHash`.

The hole: **a commit landing during a run.** The run stamps `setRecordedAtSha()` with HEAD
read at the **end** (`Tia.php:623` → `:638`), so another session's commit becomes this
run's baseline while the results still describe pre-commit code.
`structuralFingerprintShifted()` cannot see it — `Fingerprint::compute()` hashes
`composer.lock`, `phpunit.xml*`, vite config and lockfiles, **never app source**
(`Fingerprint.php:33-41`).

**Which modes stamp, and how short they are.** The obvious mitigation is T23b (no commits
while a suite is in flight), which would cover this *if* runs stay long enough that people
recognise a suite is running:

| mode | run length | stamps a commit it never executed | next `--tia` | ground truth |
|---|---|---|---|---|
| pure replay (empty affected set) | ~0s | **yes** — stamps new HEAD | — | — |
| **replay-with-refresh** | **1s** | **yes** | 2 passed (replayed) | **1 failed** |
| full record | 28.5 min here | **yes** | 2 passed (replayed) | **1 failed** |

A replay with a non-empty affected set activates the recorder (`canRefreshReplayEdges`,
`:1076`, `:1095-1102`) and takes the same end-of-run stamp; the pure-replay path stamps via
`bumpRecordedSha()` (`:1636-1657`), **which has no guard of any kind**.

**So the mitigation is consumed by the feature.** TIA exists to make runs short; adopting
it converts a rare, long, obvious window into a frequent, brief, invisible one, and T23b
stops being invoked — not because anyone stopped caring, but because nobody thinks "a suite
is in flight" about a one-second run. A safety argument that holds only in the world
without the feature is not a safety argument for adopting it. And note the difference in
kind: **flock is a mechanism; T23b is a discipline** — one breached twice in this program
in a single evening, by careful sessions.

**The false green is sticky.** With the graph stamped to another session's commit and no
further edits: `--tia` × 6 consecutively reported **2 passed (2 replayed)** every time,
against a plain-`pest` ground truth of **1 failed, 1 passed**. It clears only when
something actually executes the test — and a non-TIA run does exactly that, writing honest
results back into the graph, after which `--tia` reports "1 uncached" and re-runs it.
**What repairs a poisoned TIA graph is running the suite without TIA.**

**A tree at a different commit is handled correctly.** `:862-872` checks
`git merge-base --is-ancestor <recordedSha> HEAD`; an unreachable baseline prints
`WARN Recorded commit is no longer reachable — graph will be rebuilt`, discards and
re-records. Measured with two same-key copies, the twin at an older commit: WARN fired,
twin re-recorded, results matched ground truth. **But the benefit dies even though
correctness holds** — the trees ping-pong: twin rebuilds fully → source pays "1 affected
test file (from 2 changed files)" → twin rebuilds fully again. A shared key between trees
at different commits is not a correctness bug; it guarantees neither tree gets a cheap
replay.

**Where the cache lives — the founding premise, corrected.**
`Storage::tempDir()` = `$HOME/.pest/tia/<slug(basename)>-<sha256(origin|realpath)[0:16]>`
(`Storage.php:84-95`). Measured via the vendor's own `Storage::tempDir()`:

| tree | `.git` | key |
|---|---|---|
| `/Users/studioycm/Herd/PodText` | dir | `podtext-402768b9cc2968d4` |
| `/Users/studioycm/.codex/worktrees/0723/PodText` | file | `podtext-6eeb50053c994172` |
| `.../.claude/worktrees/app-custom-parts-refactor-bdd7f6` | file | `app-custom-parts-refactor-bdd7f6-b75c5f1bc646cd22` |

**"All worktrees share one graph" is half wrong** — note the codex worktree shares the
basename `PodText` and still lands on a distinct key. Same-tree sessions do share one;
same-basename clones/copies do too (reproduced). Detached HEAD makes TIA read-only
(`saveGraph()`/`deleteState()` return early), which is the codex worktree's state today.

**Live incident, not a lab result.** After the lane was released, the recorded graph grew
from the **2,025** results the TIA runs produced to **2,027** — the two tests the
measurement config excluded. Nothing of the 3.9b session's ran in between: the lane-lock
session's plain `php artisan test` gate did it, because **a non-TIA run still writes its
results into the TIA graph** (`addOutput()` reaches `snapshotTestResults()` at `:691-694`
even with TIA disabled). A session that had never heard of TIA silently updated
machine-global TIA state in a shared tree. Benign — honest results from a real execution,
which is the self-healing property — but it confirms the coupling outside the lab, and
means **a session cannot tell from its own behaviour whether it is participating in TIA's
state.**

**The graph file has no lock of its own.** Read at `:243`, written at `:297-303`; the write
is atomic (`FileState::write()` — tmp with random suffix, then `rename()`), so no torn
file, but it is plain read-modify-write with **no mutual exclusion**. The machine-global
lane lock (S3, `89a2ee1`/`810f6f2`) is the only thing serialising it, and knows nothing
about TIA — now recorded in `TestLaneContract`'s docblock, with the warning that a future
per-worker bypass (DP2) would remove protection nobody declared. Worker partials are worse
namespaced: `workerToken()` (`:1498`) is `TEST_TOKEN` else **`getmypid()`**, collected by
globbing `worker-edges-*` — not session-scoped; reachable only under `--parallel`, but
directly downstream of DP2's shape.

### T3 — what would flip the verdict

Smaller than "add a guard": **an existing guard has an incomplete input set.**
`structuralFingerprintShifted()` (`:234-239`) already compares start-of-run against
end-of-run state and discards-and-WARNs on drift (`:627-634`, `:713-722`), and
`$this->startFingerprint` is captured at `:850`. **No SHA is captured at start anywhere** —
that is the entire gap.

1. Capture `$changedFiles->currentSha()` at start, beside `$this->startFingerprint`.
2. **Record path:** fold a start-vs-end HEAD mismatch into the existing discard-and-WARN.
3. **Replay path:** `bumpRecordedSha()` must refuse to stamp on mismatch — it has **no
   guard at all**, which is why the 1-second case poisons.

**Rejected: stamping the start SHA instead.** If the tree moved mid-run the results
describe a *mix* of two commits and no single SHA honestly labels them; the only correct
answer is to refuse the recording, which is what the existing guard already does for config
drift. **Also rejected: making TIA take the lane lock** — PodText-specific, useless
upstream.

Same shape as #1852 — a guard that exists and doesn't cover the case — so it is filable.
**It does not change today's verdict**, only a future one.

### T4 — adoption checklist (for whoever reconsiders this)

Do not rediscover these 45 seconds into a first recording run.

- **T4a — no PHPUnit-class tests may exist.** `EnsureTiaIsRunningPestTestsOnly.php:39-43`
  panics mid-run (after boot and migrate) on any test class lacking Pest's
  `__initializeTestCase` marker; it never inspects the assertion, so **converting** to Pest
  satisfies it exactly as well as deleting. Census: **1 as of `505f043` (was 2 at
  `cbf4479`)** — `tests/Unit/ExampleTest.php`; the Feature one was a real thin smoke test
  (`RefreshDatabase`, asserts `/` responds — `dd7b552`) and was converted rather than
  deleted. **REDUCED, not resolved** — and resolving it makes TIA *runnable*, never
  *trustworthy*; the verdict rests on T2, not on this.
- **This property is load-bearing with nothing behind it.** If adoption is revisited,
  "no PHPUnit-class tests in `tests/`" wants an **arch test** — the failure mode is a
  mid-run panic, not a lint error.
- **So is worktree-key isolation.** It is incidental (an origin lookup that fails on
  worktrees), and a switch to `git config --get remote.origin.url` collapses every tree
  onto one key. If adoption proceeds, pin it with a guard.
- **T4b — TIA is whole-suite-only.** `PARTIAL_SELECTION_FLAGS` (`:135`) — `--filter`,
  `--exclude-filter`, `--group`, `--exclude-group`, `--testsuite`, `--dirty`, `--covers`,
  `--uses` — and explicit paths all disable it. The house guidelines push
  `--filter` for daily work, so every such run contributes nothing to the graph, silently.
  Group excludes in `phpunit.xml` are config not CLI, so the gate's plain
  `php artisan test` can carry `--tia`. Governance-level conflict, routed separately.
- **Budget the recording pass honestly**: 28.5 min here, plus 5 false browser reds to
  disregard.
- **If the 181× is wanted before the upstream fix**, the one constraint worth trusting is a
  mechanism this repo already owns: route every TIA run through a wrapper that takes the
  **commit mutex** (`~/.cache/podtext-coord/claim.sh`) for the run's duration. Cheap for
  replays (2s), expensive for recording (28.5 min of no commits in a three-session repo),
  and still imperfect — it holds only while every invocation uses the wrapper. But "use the
  wrapper" is a far narrower discipline than "never commit while anything runs".
- **Constraints that do NOT help:** per-worktree cache keys (worktrees are already
  isolated; the exposure is same-tree), and an opt-in flag (the hazard is in the mechanism,
  not the default).

### T5 — corrections this round made, including to its own work

- **3.9b's "all worktrees share one graph"** — half wrong; linked worktrees are isolated,
  incidentally and fragilely.
- **"The lane lock closes TIA's window, so TIA is safe here"** — refuted. The lock covers
  pest↔pest; the hazard is pest↔**git**, and committing is not a pest run.
- **"A pure replay does not advance the recorded SHA"** (this session's own earlier claim) —
  **wrong, and the cause is worth keeping**, because all three flaws are reusable warnings
  for anyone scripting a concurrency probe: (1) the watcher committed *unconditionally after
  its timeout*, so on a failed handshake the commit landed after the run and an artifact
  looked like a safety property — a probe must report HANDSHAKE-FAILED rather than proceed;
  (2) a 12-char graph SHA was compared against `git rev-parse --short`'s 7, so every match
  read as a mismatch; (3) one "source change" was comment-only, which `ContentHash`
  normalises to zero reruns, silently degrading that case to another pure replay. Corrected
  script re-run; the T2 table is from the clean version.
- **Blocked-run exit codes**: **75** (EX_TEMPFAIL) for lane contention (shipped);
  **73** (EX_CANTCREAT) proposed for the unwritable-lock-root path. Two different codes,
  both correct — flagged as a suspected error and confirmed fine on inspection.

### Evidence

Recorded graph (748KB, `recorded_at_sha cbf4479`, 942 source files, 168 test files with
edges) and a summary are preserved outside the repo, so the 28.5-minute recording need not
be repeated to check any figure here. Machine-global TIA caches were **purged** at
close-out (`~/.pest/tia/` empty) rather than left as a 748KB artifact of a rejected
experiment — the `test-residue` pattern.
