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
