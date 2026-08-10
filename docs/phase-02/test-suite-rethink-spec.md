# Test-suite rethink + post-alignment backlog — design spec

Status: **approved design, plan pending** · Written 2026-08-10, the day the
database-alignment program closed. Research base:
[`../research/test-suite-rethink-notes.md`](../research/test-suite-rethink-notes.md)
(suite facts, option space, larastan↔Rector loop),
[`../research/pest5-rector-phpstan-notes.md`](../research/pest5-rector-phpstan-notes.md)
(package facts), [`../research/larastan-playbook.md`](../research/larastan-playbook.md)
(PHPStan policy). Residual ledger:
[`open-findings-triage.md`](open-findings-triage.md) §F.

## Decision record (operator, 2026-08-10)

1. **Fix-now scope**: all four batches — docs truth-pass, code batch
   (F1/F2/F3), `lane:reset` helper (F4), `db:restore` TIMESTAMP guard (F6).
2. **Ops**: production `cron` + `rsyslog` restart (+ nginx reload) rides the
   **next deploy window checklist** (F7; recorded in
   `current-project-state.md`).
3. **Mission B shape**: staged rethink — measurement-first, phase gates.
4. **In-plan scope**: `pest-plugin-phpstan`, Rector, parallelization spike,
   TIA feasibility — all in, with the following override:
5. **Phasing override (verbatim intent)**: *"Pest 5 + plugins only at the end
   and maybe in a separate session — wait for approval. We do want to
   introduce Rector and check how to work with larastan and Rector."*
   Consequence: everything on the Pest-5 line (`pestphp/pest-plugin-phpstan`,
   `pestphp/pest-plugin-rector`, TIA execution) moves to the final phase
   behind its own approval; plain `rector/rector` +
   `driftingly/rector-laravel` land now, app-side, wired to `phpstan.neon`.

## Part 1 — fix-now batch (this round, before the phased work)

House gate applies to every commit batch: `php -d memory_limit=2G
vendor/bin/pest --compact`, `vendor/bin/pint --dirty --format agent`,
`composer filacheck` (never `vendor/bin/filacheck`), `npm run build` only if
assets change. Commits to local `main` with explicit pathspecs; push only on
the operator's word (auto-deploy OFF).

### 1.1 F1 — remove the dead sqlite arms from `LegacyRoleBackfillSchemaContract`

- The three `in_array($driver, ['sqlite', 'mysql'], true)` guards (`:21`,
  `:67`, `:178`) become a single loud shape: any non-`mysql` driver throws
  the existing exception with the same message style. The sqlite descriptor
  branches and the `'length' => $driver === 'sqlite' ? null : $length`
  ternary (`:248`) are deleted.
- `expected(string $driver)` keeps its signature; `expected('sqlite')` now
  throws — add the refusal assertion where the mysql descriptor test lives.
- Nothing else may change behavior: the mysql descriptor must remain
  byte-identical (the schema-descriptor test in
  `AuthzLegacyRoleBackfillTest` is the oracle).

### 1.2 F2 — restore nullability-drift coverage

- In the schema-drift fixture of `AuthzLegacyRoleBackfillTest` (the
  `model_has_roles`/`role_has_permissions` recreation test), add a
  nullability drift on a **non-PK** column: `roles.name` → NULL-able via a
  raw `ALTER TABLE ... MODIFY`, restating every other property byte-identical
  (read the live definition from `information_schema` first; change only
  nullability).
- Assert the analyzer reports it as column-property drift naming
  `roles.name`. Contingency, verified first: if the schema descriptor turns
  out not to capture nullability at all, the item grows into "add nullability
  to the descriptor + contract", and that larger shape comes back for
  approval instead of being smuggled in.
- Revert inside `finally` (MySQL DDL auto-commits and escapes
  `RefreshDatabase` — the I2/M1 lesson; the compensating action must run on
  failure too).

### 1.3 F3 — stop `EpisodesTableR1Test` dodging the DST rule

- Its `changePublishedAt` payloads are `'Y-m-d H:i'`; the wired
  `ExistsInTimezone` rule throws-and-passes on format mismatch
  (`getInternalFormat()` is `'Y-m-d H:i:s'`), so those calls never exercise
  the rule (T23 residual). Switch every synthesized `published_at` payload in
  that file to seconds-bearing form; assertions stay otherwise unchanged.
- `DstInputEdgeTest` keeps owning the gap-rejection case; this item is about
  payload realism, not new coverage.

### 1.4 F4 — `db:test-lane-reset` helper + fresh-worktree remedy docs

- **Extraction first**: move the 12-clause one-shape table
  (`TestCase::refusalFor()` + `rawEnvDatabases()`) into a small pure app-side
  class — `App\Support\Testing\TestLaneContract` (rename at spec review if
  wanted; the plan treats it as decided) — with `TestCase` delegating. This
  is the seed of the §D3 consolidation and lets the command share the exact
  clause table instead of duplicating it. `TestLaneGuardTest` follows the
  code and keeps its mutation coverage.
- **Command** — `db:test-lane-reset`, staying in the `db:*` family (same
  rename-at-review rule): refuses unless the `mysql_testing` config passes the clause table;
  refuses while this tree's run-lock is flocked; refuses when
  `information_schema.PROCESSLIST` shows any other connection to the lane
  schema (`DB = ? AND ID <> CONNECTION_ID()`) — that clause is what protects
  a *different worktree's* in-flight suite, which the per-tree lock cannot
  see; requires the typed-name confirmation (house `db:restore` pattern).
  Action: drop all tables in the lane schema (FK checks off, schema itself
  kept so its charset default survives), then delete the matching
  fingerprint file, then print the next step (first pest boot re-fingerprints
  the empty schema and migrates fresh).
- **Tests**: every refusal direction is live-testable in-suite (the suite
  itself holds both the flock and a lane connection, so the command must
  refuse *while being tested* — assert exactly that). The destructive happy
  path is structurally untestable in-suite; cover it by unit-testing the
  statement generation and record the one manual end-to-end run in the
  round's report.
- **Docs**: fresh-worktree remedy section (state doc already carries the
  pointer; the `podtext-worktree-provisioning` checklist gains the lane
  steps).

### 1.5 F6 — `db:restore` TIMESTAMP-replay warning

- While streaming the dump for the existing B1/B2 content refusals, also
  detect `TIMESTAMP` column DDL in `CREATE TABLE` statements. If present
  *and* the target connection config pins `'timezone' => '+00:00'`, print
  the shifted-literal replay caveat (the state-doc wording) and require an
  explicit additional confirmation. No behavior change for dumps without
  TIMESTAMP DDL.
- Tests: fixture dump with TIMESTAMP DDL → warning + confirmation path;
  without → silent as today; refusal tests untouched.

## Part 2 — the staged rethink

Phase order honors the 2026-08-10 override: research → Rector → structure →
**Pest 5 last, behind its own approval**. Every phase ends at an operator
gate; no phase starts implementation while its predecessor's gate is open.

### Phase R — measure and inventory (no app-code changes)

R1 per-file duration profile (slowest 20, browser/feature split) ·
R2 `migrate:fresh` + transaction share of the ~600s ·
R3 guard-cost timing on an idle lane (the ~1,900× per-boot COLUMNS count —
decides F9's permanent label) ·
R4 browser share + trap inventory (waits/Alpine probes) against the plugin
5.0 change list — prep for Phase U, no upgrade ·
R5 classification of the 41 RefreshDatabase opt-out files (DB-free /
manual-state / accidental) ·
R6 PHPUnit 13 changelog read → written risk memo (Phase U prep) ·
R7 parallelization spike on paper + tiny probes: worker flock behavior,
grant requirements, `-d memory_limit` propagation — no grant changes, no
runner switch ·
R8 DATETIME same-second tie sweep (order-sensitive assertions without
tie-breaks).

**Deliverable**: measurement report appended to
`docs/research/test-suite-rethink-notes.md`. **Gate**: operator reads it and
sets Phase S scope + DP1–DP3.

### Phase T — Rector introduction (app-side, now)

T1 `composer require --dev rector/rector driftingly/rector-laravel`
(introduction approved 2026-08-10) ·
T2 `rector.php`: `->withPHPStanConfigs(['phpstan.neon'])` so type rules see
larastan's cast/generics knowledge; paths `app database routes` (tests join
in Phase U); zero rules enabled at first — prove the safe-by-default warning
state ·
T3 composer scripts: `composer rector` bakes `--dry-run`; `composer
rector:fix` exists but is approval-gated (Rector is FilaCheck's
writes-to-source hazard class) ·
T4 a guard test pinning the script contract (the `--dry-run` flag cannot be
dropped silently — same pattern as `FilacheckAgentModeGuardTest`) ·
T5 first dry-run report: **one** `rector-laravel` set — pick the
lowest-risk, highest-signal candidate for the installed Laravel 13 (the plan
names it after reading the set list against `composer show`), diff committed
as a report document, no source writes.

**Deliverable**: the working loop documented (research notes §5) + first
dry-run report. **Gate**: each write pass is its own operator approval
(DP4), reviewed like a PR, then `phpstan analyse` proves no new errors →
`pint` → targeted pest.

### Phase S — structure fixes (scope set at the R gate)

Candidates, priced by R: §D3 guard consolidation with named failures
(building on the F4 extraction) · run-lock relocation to a lane-keyed path
(DP7 — closes the cross-worktree gap for good) · Unit-suite bypass closure
(bind the guard or an arch rule that `tests/Unit` never touches DB facades) ·
tie-break fixes from R8 · RefreshDatabase corrections from R5 · guard trims
only if R3 registered.

### Phase U — Pest 5 + plugins (END; separate approval; possibly a separate session)

U1 composer moves: `pestphp/pest ^5.0`, `pest-plugin-browser ^5.0`,
`pest-plugin-laravel ^5.0`, `pest-plugin-drift ^5.0`, plus new dev deps
`pest-plugin-phpstan`, `pest-plugin-rector` ·
U2 **re-prove the run-lock lifetime** — the `$GLOBALS` fix is
bootstrapper-shape-dependent; rerun the mid-run probe from
`.superpowers/sdd/final-review-fixes.md` §1 with a longer sleep than its
documented ~0.6s margin ·
U3 browser-suite shakedown against R4's trap inventory (stash-baseline
attribution rules apply) ·
U4 `pest-plugin-phpstan` wiring; re-measure the level-6 estimate
(`phpstan.neon` documents ~426 reports) → DP5 ·
U5 Pest coding-style Rector set dry-run report (tests-side) ·
U6 TIA: DP3 first — no coverage driver exists on Herd PHP 8.4.23 (measured
2026-08-10: no pcov/xdebug loaded, none installed) ·
U7 full gate + state-doc update.

**Gate**: this phase does not *start* without the operator's explicit
go-ahead (their 2026-08-10 instruction), independent of the per-commit gates.

## Out of scope for this round

The 443-error PHPStan backlog itself and the 5 `varTag.nativeType` macro
errors (parked larastan/tooling roadmap — slotting order recorded in
`open-findings-triage.md` §F10) · FULLTEXT/Scout · §C1–C3 enum work · the
effective-transcription HasOne refactor (parked by operator note) · CI
setup (sharding facts recorded for that future day).

## Operator decision points

DP1–DP8 tabled in `docs/research/test-suite-rethink-notes.md` §7; the phase
gates above are where each falls due.

## Testing rules for the round

Every code item carries its test in the same batch; guard tests get a
mutation check where cheap (flip the guarded contract, watch it fail);
no test deletions without approval; browser-test failures follow the
stash-baseline attribution protocol; the full house gate runs before every
commit batch.
