# Open findings — triage list

Everything found and **not fixed**. One line of what it is, what's proven, what's next.
Status: `OPEN` = nothing done · `BOUNDED` = diagnosed, fix not chosen · `PARTIAL` = half done ·
`FIXED` = closed here, kept because a live entry depends on it · `WITHDRAWN` = the finding's premise was wrong.

---

## A. Production timezone & locale

**2026-08-10: §A is closed end-to-end by the database-alignment program**
(55 commits `94a3328..7d715c9`; `db:check-settings` exit 0 — "No drift found" —
on both local and production). Labels updated in place below; the measured
block stays as the historical baseline.

**Measured on production 2026-08-07** (read-only, `podtext.co.il`):

```
MySQL 8.0.46          global/session tz: SYSTEM      system tz: IDT
NOW() 17:00:23        UTC_TIMESTAMP() 14:00:23      TIMEDIFF 03:00:00
server charset utf8mb4 / utf8mb4_0900_ai_ci
schema default utf8mb3 / utf8mb3_unicode_ci     ← see A3
all 40 tables         utf8mb4_unicode_ci
```

### A1. The +3 is a timezone, not a broken clock — `FIXED` 2026-08-09 (alignment Phase 3)
`UTC_TIMESTAMP()` is real UTC; only `NOW()` was offset. The decision went to
**full UTC**: OS `timedatectl set-timezone UTC`, `default-time-zone = '+00:00'`
in `mysqld.cnf`, tz tables loaded. `TIMEDIFF(NOW(), UTC_TIMESTAMP())` =
`00:00:00` on all three apps.

### A2. It is a DST-observing named zone (`IDT`), not a fixed `+03:00` — `FIXED` 2026-08-09
Mooted by going full UTC instead of any per-row shift or `+03:00` pin — the
winter/summer asymmetry never got a chance to bite. The connection now pins
`'timezone' => '+00:00'` in git (`3911495`), and the spring-forward wall-clock
gap is guarded at *input* by the `ExistsInTimezone` rule on every
`DateTimePicker` (`6ef6099`/`71dc28b`).

### A3. Schema default is `utf8mb3` — `FIXED` 2026-08-09 (alignment Phases 1–2)
`ALTER DATABASE` ran first inside migration `2026_08_09_000000`; schema default
+ 40 tables + 183 columns are `utf8mb4_0900_ai_ci` everywhere, oracle-verified
byte-identical. The utf8mb3 finding is gone from `db:check-settings`.

### A4. `mysql.time_zone_name` unreadable — `FIXED` 2026-08-09 (T12/T13)
tz tables are loaded on all three daemons (production + both local), verified
via `CONVERT_TZ('2026-01-15 10:00:00','UTC','Asia/Jerusalem')` → `12:00:00` on
each.

### A5. Production MySQL 8.0.46 vs local Herd 9.4.0 — `FIXED` 2026-08-09 (alignment Phase 4)
The lane was decided and **built**: a dedicated MySQL **8.0.46** daemon on
`127.0.0.1:3307` — a second native Herd service, not the Docker container the
first read of this finding suggested. The §3 Hebrew-collation matrix measured
identical on 8.0.46 vs 9.4.0 (T19 canary), closing the version-identity
question.

---

## B. PHPStan / larastan (445 errors, gate not wired in)

### B1. Enum-and-datetime casts resolve as `string` — `FIXED` 2026-08-07
`$item->status` typed as `string`, not `PublicationStatus`, so every enum comparison was reported "always false". Not enum-specific — a `datetime` cast also resolved to `string` (`PruneMediaQuarantine.php:48`, *"Cannot call method timezone() on string"*).

**Cause: larastan's `parseModelCastsMethod`, which defaults to `false`.** Laravel merges `casts()` into the cast map in `HasAttributes::initializeHasAttributes()` — a trait initializer, so it runs only from `Model::__construct()`. larastan builds models with `newInstanceWithoutConstructor()`, so the initializer never fires and `getCasts()` sees only the empty `$casts` property. With the flag off, larastan falls back to the *declared* return type of `casts()` — plain `array`, not a constant array — and skips the merge silently.

The flag is now set in `phpstan.neon`, with the full mechanism in the comment there and the research in [`docs/research/larastan-playbook.md`](../research/larastan-playbook.md).

**Why the five earlier hypotheses all read as "no change":** none of them touched the cast map. `databaseMigrationsPath` in particular is load-bearing but for a *different* job — it supplies the column type that larastan falls back to. That fallback is why the failure looked partial (`integer`/`boolean` casts appeared fine; only `datetime`, `array` and enum casts diverged from their column type), and it is also the evidence that refutes the sixth hypothesis:

> **The leading hypothesis — larastan's bootstrap failing silently under Filament — is refuted.** If the container had not booted, the migration scan could not have produced the `string|null` fallback types that were actually observed. larastan also fails *loudly*: `bootstrap.php:40-51` routes any throwable to `BootstrapErrorHandler` and `exit(1)`.

**Measured, level 5, `app` + `database` + `routes`:** 614 → 507 errors; cold run 20.4s → 21.5s. The families that vanished were entirely false positives: all 7 `match.alwaysFalse`, all 17 `method.nonObject`, all 16 `function.impossibleType`, all 8 `booleanAnd.alwaysFalse`, all 6 `instanceof.alwaysFalse`, and 7 of 11 `deadCode.unreachable`. That last one is why the defect was worse than wrong types: a cast attribute believed to be a string makes guard clauses always-terminate, so PHPStan marked the code after them unreachable and *stopped analysing it*.

Guarded by `tests/Feature/LarastanCastResolutionGuardTest.php`, which pins both halves against the real binary (the flag works; the hazard is still real) and was mutation-checked by flipping the config.

### B2. The motivating rule doesn't fire — `WITHDRAWN`, the premise was wrong
The claim was that B1 suppressed `match.unhandled` on `MediaFilesystemMutationCoordinator.php:1540`. **It did not.** That rule was firing there on the unfixed config, correctly naming `MediaMutationOperationType::LegacyOwnerRepair`. The match subject at that site comes from `MediaMutationOperationType::tryFrom(...)` narrowed by an `instanceof` guard (`:1439`, `:1450`) — a native enum by construction, never a model attribute, so the cast defect could not reach it.

The finding was an artefact of PHPStan's agent error formatter, which truncates its error list and appends `"truncated": true, "hint": "Pass -v to see all errors."`. The site was below the cut. **Pass `-v` and check the `truncated` field before concluding a rule does not fire.**

What B1 *did* suppress at that file were the neighbouring `match.alwaysFalse` reports at `:550`–`:551`, plus the equivalents in `ConnectionTester.php` and `GoogleApiDriveClientFactory.php` — all now gone. C1 remains the open defect; the parser test and PHPStan both catch it, so keep both.

### B3. `PublicFrontConfigValidator.php:69` — `OPEN`, re-characterised 2026-08-07
A `match ($key)` over 14 string literals with **no default arm**. A *scalar* match, so the enum sweep structurally cannot see it — only PHPStan reports it.

**Correction: it does not "throw on any unrecognised config group",** as this entry previously said. `validateGroups()` guards first — `if (! in_array($key, $settingsKeys, true))` records `unknown_top_level_key` and `continue`s — so an unknown key never reaches the match. The match is unreachable for exactly the input that was claimed to break it.

**The real risk is the inverse, and it is a trap rather than a live bug.** Safety rests on two hand-maintained lists in two files agreeing: `PublicFrontConfigRegistry::settingsKeys()` (14 strings) and the match arms (the same 14). Add a fifteenth key to `settingsKeys()`, forget the arm, and the first save of that config group raises `UnhandledMatchError` in production. That is the C3 `set-membership-without-totality` pattern, so fix it *with* C3 rather than alone.

**Next — and NOT the default arm this entry used to recommend.** A default arm would swallow the missing key silently, converting a loud crash into quiet wrong behaviour, which is worse than the trap it closes. Either route the keys through a backed enum, so `match.unhandled` fires at analysis time the moment a case is added without an arm; or add a guard test asserting `settingsKeys()` and the match arms are the same set. The enum route is preferred — PHPStan already watches this line, so the guard costs nothing to keep.

Housekeeping note (2026-08-10): `phpstan.neon`'s no-baseline comment still
carries this entry's *pre-correction* wording ("eleven string literals",
"throws on any unrecognised config group"). Refresh that comment on the
roadmap's first touch of the file — the policy it argues for is unchanged.

### B4. 445 errors triaged by identifier — `OPEN` (work items, not mysteries)
Triaged 2026-08-07 after B1; the counts below are from before B5, which cut 62 of them. No baseline, no `@phpstan-ignore`. Five groups:

**1. Untyped `Model` / `Builder` — 171, a third of the total and the largest family by far.** Two distinct sources, and B5 establishes the split: **122 originate in Filament's own contracts** (`Exporter::$record`, `Resource::getEloquentQuery()`, `modifyQueryUsing(fn (Builder $query) => …)`), and **49 originate in our own un-generic'd relations**. Shapes: `method.notFound` on `Builder`/`Builder<Model>` (61 — ordinary `scopeReleasedBy()` / `scopeCurrentlyPinned()` / `scopePublished()` methods that larastan resolves fine on `Builder<ContentItem>` but cannot on a raw `Builder`), `property.notFound` on `Model::$reference_key` (48), `method.notFound` on `Model::…` (20), 38 of the 58 `argument.type`, and `assign.propertyType` (4). **Real app typing debt, not a larastan limitation.** Remedy is to narrow at the boundary — type the closure `Builder<ContentItem>`, or give the Filament class a covariant `@property` — never to loosen the check.

**2. `BuildsPublicContentSettingsSubjectSchemas` calls a method its host may not have — 36.** The trait calls `$this->withImportLockSection()`, defined on `PublicContentSettingsSubjectPage`. `CardTemplateEditorPage` uses the trait but extends `SettingsPage`, so it does not inherit the method. An undeclared host requirement; a fatal if any of those paths is reachable from that page. **Real, and the most likely genuine bug in the remainder — worth its own look.**

**3. Two unimported class names, both real — `class.notFound`.** `ContentItemsTable.php:265` declares `?HasTable $livewire` with **no `use` statement**, so PHP resolves it to `App\Filament\Resources\ContentItems\Tables\HasTable`, which does not exist — the parameter accepts `null` and would `TypeError` on any real Livewire instance. `EditorialMetrics.php:617` has a `@return` array shape referencing `ProgressWidgetData`, a class that exists nowhere in the repo. Both are the kind of thing this install was bought to find.

**4. Small real findings, mostly one-offs.** `nullsafe.neverNull` (44 — unnecessary `?->`, now *accurate* because casts resolve), `staticClassAccess.privateMethod` (34 — `static::privateMethod()` in subclassable classes), `function.alreadyNarrowedType` (37 — redundant `is_string()`/`is_array()` guards), `return.type` (27), `method.unused` (6), `catch.neverThrown`, `property.onlyWritten` (2), `identical.alwaysTrue`, `larastan.noUnnecessaryCollectionCall`.

**5. Genuine tool limitations — 10, none needing suppression.** `varTag.nativeType` × 5 in `AppServiceProvider` (macro registration closures: the `@var` lands on `$this` because Filament's `Macroable` rebinds the closure), `unset.possiblyHookedProperty` on Filament's own `$cachedTabs`, `method.notFound` on `ConnectionInterface::getDriverName()`/`getSchemaBuilder()` × 2 (Laravel's contract genuinely lacks them; `DB::connection()` returns the interface, not `Connection`), and `class.notFound` × 2 inside `phpstan/filament-macros.stub` for `App\Enums\UserRole` — a class that exists and resolves fine everywhere else in the codebase, but is not visible in stub-file scope.

**Still not wired into `composer test`** — deliberately. See the note in `phpstan.neon`: a red gate trains people to ignore red. Wire it when the count reaches zero, not before.

### B5. Relationship generics missing on 43 of 45 relations — `FIXED` 2026-08-07
larastan infers a relation's *kind* from the return type but not the *related model*. Without `@return HasMany<Transcription, $this>`, `$item->transcriptions` is a collection of `Model`, and everything reached through it is an error. This repo had 45 relationship methods and 2 generics.

Annotating the other 43 took **507 → 445, with zero new errors introduced**. `property.notFound` 65→33, `argument.type` 58→44, `return.type` 27→20, `method.notFound` 129→121, `argument.unresolvableType` 1→0. The change is 51 lines of PHPDoc and nothing else — PHP does not read docblocks, so it has no runtime surface at all.

Guarded by `tests/Feature/EloquentRelationshipGenericsGuardTest.php`, mutation-checked by deleting one annotation. It asserts every relation carries a generic, so a new relation added without one fails a test instead of quietly giving back part of the win.

Four things established along the way:
- **Check generic arity against the installed framework.** `HasMany`/`HasOne`/`BelongsTo`/`MorphTo` take two; `BelongsToMany`/`MorphToMany` take two plus defaulted pivot and accessor; `HasOneThrough` takes three (related, intermediate, declaring).
- **`morphTo()` with no argument cannot be narrowed.** It returns `MorphTo<Model, $this>` and PHPStan checks the body against the tag, so annotating the union the morph map actually admits (`ContentGroup|ContentItem`) is a claim it *rejects* — it produced a fresh `return.type` error on `MediaAttachment::attachable()`. That method now carries `Model` plus a comment saying why, because the instinct on a second pass is to re-narrow it.
- **Three annotations are unverifiable by PHPStan, and are pinned by test instead.** `tags()` / `contentTags()` / `enabledContentTags()` call `morphToMany(self::getTagClassName(), …)` — a dynamic class string — so PHPStan accepts the `ContentTag` claim without checking it. `config/tags.php:9` is the source of truth and the guard test asserts it, so editing that config now fails a test rather than silently invalidating three annotations.
- **Reflection reports a trait's methods as declared by the using class.** The guard test's count canary caught Spatie `HasTags::tagsTranslated()` being treated as ours. Filter relation discovery on `getFileName()`, not `getDeclaringClass()`.

**Deliberately not done: the dead `instanceof` guard.** Typing `ContentGroup::contentItems()` makes `if (! $item instanceof ContentItem) { continue; }` in `ContentImagesExportManager` provably unreachable, and PHPStan now reports it (`instanceof.alwaysTrue`, the one error separating 445 from 444). Removing it is the only runtime-affecting edit in the vicinity, so it was split off as its own decision rather than smuggled in with 51 lines of comments. Still open.

Source of the rules: [szepeviktor's `larastan-preflight-reviewer` skill](https://github.com/szepeviktor/skills/blob/master/skills/larastan-preflight-reviewer/SKILL.md) — a larastan collaborator's, unlicensed, so applied rather than copied. Its `Attribute<TGet, TSet>` and `JsonResource` `@mixin` rules are **not yet applied** and are the obvious next candidates. Its `casts()` array-shape rule is deliberately **rejected** — see `phpstan.neon`.

---

## C. Enums

### C1. `MediaMutationOperationType::LegacyOwnerRepair` — `OPEN` (guard closed, defect open)
`assertOperationShape()` covers 10 of 11 cases, no default arm. **Does not crash** — both call sites catch `Throwable`, so the row parks forever in `CleanupPending`/`ManualReviewRequired` with the error text as its own explanation.
**Four more lists silently omit it too** (`:887`, `:900`, `:904`, `:1131`) — quarantine verification, cache invalidation, source cleanup, dangling-reference guard.
**Fixing only the match arm is worse than leaving it.** The retirement work must answer all five and clear the red list.

### C2. Two enums outside `app/Enums` — `FIXED` 2026-08-07 (`005eda6`)
`app/Support/Importer/SpotifyLinks/SpotifyEntityMode.php`, `app/Auth/LegacyRoleBackfill/PermissionCacheInvalidationOutcome.php`. Neither carries a contract.
Any tool globbing `app/Enums/*.php` misses them — which is what produced every wrong enum count in the playbook.
**Next:** move both, or decide they stay and record why.
**Closed:** both moved into `app/Enums` (`005eda6`), guarded by `EnvironmentGuardsTest`'s "declares every enum under app/Enums" test.

### C3. `set-membership-without-totality` — `OPEN`, no guard possible
`in_array($case, [...])` decides membership like a `match` but claims no totality. **No analyser can check it, PHPStan included.**
**Next:** derive the arrays from enum predicates (`array_filter(cases())`) rather than writing literals.

---

## D. Repo / process

### D1. `ce0f3a0` won't bisect — `OPEN`, ruled leave-and-record
It references `HasFoldedSearchColumns` before that file exists (lands in `8ec2ada`). HEAD is correct; only bisect is affected.
**Cause:** a pathspec is *file*-level and both sessions edited `app/Models/ContentItem.php`.
**Next:** nothing, unless a history rewrite is wanted.

### D2. Push pairing — `FIXED` 2026-08-08 (alignment Phase 0)
Both halves closed: the pairing ran on production behind the Phase 0 window
(deploy `75045371` — the folding migration plus `search:backfill-folds`, 1,053
rows across 12 models, shadows 100%), and the push backlog cleared — all 55
alignment commits are pushed, production deployed through `75082462`. The
pairing rule itself stays live for any future restore/replay: between
`migrate --force` and `search:backfill-folds`, pre-existing rows are invisible
to search.

### D3. Pre-test guard consolidation — `OPEN`, now owned by the suite rethink
Guards are scattered and the alignment program added more (`TestCase` clause
table + fingerprint + TIMESTAMP check, `Pest.php` env forcing + run-lock, floors
in the enum sweep). Not yet a single first-running check with named failures.
Owned by `docs/phase-02/test-suite-rethink-spec.md` (structure phase), which
also owns the cross-worktree lock gap recorded there.

---

## E. Deferred by decision (not defects)

- **MySQL test lane** — `BUILT` 2026-08-09 (alignment Phase 4): dedicated Herd
  MySQL 8.0.46 daemon on 3307, `mysql_testing` connection behind the one-shape
  guard and flock run-lock; the suite runs on it exclusively. (Docker turned
  out unnecessary — a second native Herd service pins 8.0.46.)
- **Collation change** — `DONE` 2026-08-09 by the alignment program:
  `utf8mb4_0900_ai_ci` on schema + tables + columns everywhere, oracle-verified
  byte-identical. (§1 of the search spec stands: collation was never what
  decided Hebrew search — folding did.)
- **FULLTEXT / Scout** — the only thing that makes `%term%` fast. Shadow columns changed *what* is compared, not that it scans.
- **`imports.name` / `file_name`** — deliberately unfolded (vendor model, machine filenames).

---

## F. Database-alignment program residuals — triaged 2026-08-10, operator-confirmed

Buckets: `fix-now` (this round) · `ride-along` · `parked-roadmap` ·
`accepted-forever`. Fix designs live in
`docs/phase-02/test-suite-rethink-spec.md`; sources are the program ledger
(`.superpowers/sdd/progress.md`, gitignored) and the task reports cited inline.

### F1. Dead `expected('sqlite')` branches — `FIXED` 2026-08-10 (`e3a539c`)
`LegacyRoleBackfillSchemaContract` still accepts and describes a sqlite schema
(`:21`, `:67`, `:178`, `:248`) but zero callers pass `'sqlite'` since the suite
went mysql-only (T19). Remove the arms, keep the driver refusal loud, re-pin
tests.

### F2. Nullability-drift fixture coverage — `FIXED` 2026-08-10 (`7cdaadf`)
The `model_has_roles` drift fixture swapped its nullable-PK drift for a
shorter-VARCHAR drift (MySQL refuses a nullable PRIMARY KEY column at DDL
time), so *nullability* drift is exercised nowhere. Add it on a non-PK column
(`roles.name`) in the same fixture.

### F3. `EpisodesTableR1Test` payloads dodge the DST rule — `FIXED` 2026-08-10 (`982a65b`)
Its `changePublishedAt` calls send `'Y-m-d H:i'` (no seconds); the
`ExistsInTimezone` rule throws-and-passes on format mismatch, so that field's
DST coverage lives only in `DstInputEdgeTest` (T23 residual). Send
seconds-bearing payloads.

### F4. Fresh-worktree lane-fingerprint refusal — `FIXED` 2026-08-10 (`a45efc4`, `0f3a32a`, `274b536`, `fb6b212`)
First lane use requires an *empty* schema; the fingerprint file is gitignored,
so every fresh worktree hard-refuses a populated lane (fail-closed, correct).
Remedy approved: documented steps + a `lane:reset`-style helper. Design
constraint: the flock run-lock file is per-tree while the lane is
machine-global — the helper must probe for live lane connections, not just the
local lock, or it papers over cross-worktree collisions.

**Closed:** the shipped command is `db:test-lane-reset`, refusal-layered (clause table → flock → processlist probe → typed confirmation), with the clause table extracted to `App\Support\Testing\TestLaneContract`.

### F5. `mysqldump` + `gzip` are undocumented suite prerequisites — `FIXED` 2026-08-10 (`732c710`)
`DatabaseSnapshotCommandsTest` shells the real dump pipeline against the lane,
so both binaries are hard test dependencies. Documented in
`current-project-state.md` (this pass); keep beside the lane env block if one
lands in `.env.example`.

### F6. Pre-alignment snapshot replay under the pinned connection — `FIXED` 2026-08-10 (`17c19ff`, `f83949f`, `3bba8cc`, `a63d23f`)
The caveat is documented in `current-project-state.md` (restore only with the
`+00:00` pin removed, or onto an unpinned connection). Approved hardening:
`db:restore` warns when a dump carries `TIMESTAMP` column DDL while the target
connection pins `+00:00`.

**Closed:** shipped stronger than specced — `db:restore` *refuses outright* (exit 1) unless `--allow-timestamp-dump`, with scan completeness verified against the gzip trailer; the body above kept its original "warns" wording per the keep-description rule, and `current-project-state.md` carries the accurate phrasing.

### F7. Production cron/rsyslog still stamp +03:00 — operator window (decided)
The T12 window restarted mysql/php-fpm/Horizon only; daemons that inherited the
old OS zone keep stamping +03:00 until restarted. Decision 2026-08-10: restart
`cron` + `rsyslog` (and reload nginx) rides the **next deploy window
checklist** — noted in `current-project-state.md`.

### F8. `possible_keys` vs `key` in `MediaRelationshipPerformanceTest` — `accepted-forever`
Asserting `key` would pin an optimizer tie-break MySQL does not guarantee on
near-empty fixture tables (measured: a different valid index wins). The
in-test comment is the record (T19).

### F9. Per-boot `information_schema.COLUMNS` count — `accepted`, CLOSED (measured 2026-08-10)
`TestCase::assertDisposableSchema()` counts lane TIMESTAMP columns on every
app boot (~1,900×/suite) — the deliberate T17 trade-off that catches
DDL-leak deadlocks at the next boot. **The measurement this entry waited for
was taken** (rethink Phase R, R3): 0.524 ms/query × 1,931 boots = **1.01s per
suite, 0.16% of a 622.2s wall** — far under the revisit threshold, so it
stays accepted and the item is closed. Source:
`docs/research/test-suite-rethink-notes.md` R3 ("F9 verdict: stays
`accepted`, closed").

### F10. PHPStan backlog — `parked-roadmap` (owner unchanged: larastan/tooling roadmap)
443 errors (§B4) plus 5 pre-existing `varTag.nativeType` in
`AppServiceProvider` macro closures. Slotting order when the roadmap opens:
the two `class.notFound` one-liners (§B4 group 3); the
`BuildsPublicContentSettingsSubjectSchemas` trait-host fatal (§B4 group 2 —
the one genuine runtime bug in the family, operator may pull it forward); the
Carbon-macro stub route that would clear all 5 varTag errors (T23 flag);
Filament-boundary narrowing (122); own-relation typing (49); §B3+§C3 enum
totality; the `phpstan.neon` comment refresh noted under B3. Level-6 wiring
(tests/) is Pest-5-gated and rides the rethink's final phase.

### F11. SQLite artifacts — `FIXED` (file + composer + DP9 default flip)
The 528KB `database/database.sqlite` was deleted and the `composer.json`
`post-create-project-cmd` touch line removed (`d1d0671`, from the 2026-08-10
cross-session sweep — Task 7B). The `config/database.php` sqlite `:memory:`
connection block is keep-forever: consumed by `tests/TestCase.php`'s
containment (a stray `DB::connection('sqlite')` must hit memory, never a repo file); `NonMysqlRefusalTest` exercises non-mysql refusals via its own throwaway connection. **DP9 is
CLOSED**: the operator approved the flip at the R gate and it shipped —
`config/database.php:20` now falls back to `mysql` (`203125c`), with
`.env.example` stating the conditional failure mode honestly (`57c1898`).

### F12. `SettingsBackupManager` per-unit re-saves — `FIXED` (2026-08-11, `476c508`; diagnosed 2026-08-10)
The per-unit reading is **disproven**: one `import()` fires exactly ONE
batched `SettingsSaved` (4 selected units → 1 event, instrumented), and no
production caller loops. The real mechanism is **per-backup visual-snapshot
fan-out ×2 backups per operation with no dedup on the full-set path**: one
import = BeforeImport backup (2 thumbs + fullTargets(4 bare/7 content-rich) ×
themes(2) × formats(1) rows) + post-save System backup (2 thumbs), one
node+Playwright spawn per row (~1.2–1.8s) — **12 spawns/import bare, 18
content-rich, 10 for a fully locked NO-OP import** (pre-fix: it still saved
and still snapshotted). Sp3a's 68.5s reconciles as ~52 spawns × ~1.32s. Verdict:
**confirmed production-affecting (efficiency, not correctness)** — worker
time + headless-Chromium load on live public pages per settings operation;
the suite-side tax was already neutralized by S2b. Side-flag: `prune()`
retention ~~is~~ was System-only, so Manual/BeforeImport/BeforeRestore
backups and snapshot files accumulated unboundedly — since closed as
register 1.9 (`4da7542`, below). Evidence + measured table:
`.superpowers/sdd/task-F12-report.md`; characterization pin:
`tests/Feature/F12SettingsBackupDiagnosisTest.php`.
**Shipped (2026-08-11, `476c508`; all three fixes operator-approved at the
implementing session's decision gate; register 1.9 deliberately NOT folded
in):** (1) one spawn per backup — `SettingsBackupSnapshotJob` hands every
pending row to `processBatch()`, which writes one uuid-named job JSON
(per-target `snapshot_id` + `results_path`), spawns once, and maps the
script's per-target results back to rows so one target's failure marks
exactly its own row; the 150ms inter-row sleep was dropped and the spawn
timeout scales per target, capped under the job timeout; (2) candidate-empty
imports keep the BeforeImport audit row + `import_report` but skip snapshot
scheduling and the save→`SettingsSaved`→`createSystem` cycle; (3)
payload-minus-locks full-set dedup via `full_snapshot_source_backup_id`
(nullOnDelete) with the gallery/zip falling back through
`effectiveSnapshots()` — borrowed rows badged, never retryable. Measured per
operation (before → after): save 2→1, bare import 12→2, content-rich import
18→2, manual 10→1, ungated restore 10→1, gated restore 12→2, locked no-op
import 10→0. The characterization pin was re-argued number by number; gate
2000 tests / 20,947 assertions green. Reviewed by the F12 diagnosis session
(approved 2026-08-11, 2 Important + 1 Minor findings, addressed in the same
follow-up batch that carries this entry).
**Answers the open question in
`docs/research/settings-performance/21-authz-subsystem-dormancy-record.md`
(§ "Open question for whoever owns settings lifecycle", :135-142):** that
doc asks whether the plain `put()` in the pre-fix
`SettingsBackupSnapshotManager::processSnapshot()` (no atomic write, no
lock, no size bound) can ever hand a truncated job file to its reader. From
this diagnosis: **no, not today** — the `put()` and the `node` spawn were
sequential statements in one process, the file had exactly one writer and
one reader, its payload is bounded metadata, and the only variable in its
path was the snapshot's integer key. **Not a defect; it is also not a
guarantee the code states** — it fell out of one-row-per-process. Fix (1)
rewrote that exact site into `processBatch()` (cited by symbol on purpose —
line numbers here drifted within a day of shipping) and discharged the
constraint: the batch write and the spawn remain sequential statements in
one process, the job file is uuid-named per invocation (no invocation can
reuse a file it did not write, even across concurrent retries), the pair is
deleted after result mapping, and the ordering is now ASSERTED by the batch
tests in `tests/Feature/SettingsBackupSnapshotsTest.php` — register 2.6's
hazard no longer rests on luck. The `prune()` ceiling above ~~remains a
separate, still-open fix~~ was fixed as the separate register-1.9 round
(2026-08-11, `4da7542`): per-source count ceilings inside the same
`prune()` call — System unchanged at `max(1, retention)`; BeforeImport and
BeforeRestore keep the newest 25; Manual keep-forever by default (all
env-tunable; `<= 0` = keep forever for non-System sources) — with a
borrow-liveness guard (an owner whose full set is still borrowed by a
surviving backup is skipped until its last borrower is pruned; a same-pass
borrower does not protect its owner). Bounded-deferral holds because **a
source whose own ceiling is keep-forever never borrows** — operator
decision at the design gate ("Manual never borrows"), shipped as the
ceiling-driven rule `isKeepForeverSource()` after the implementation
review showed the source-name form left `RETENTION_BEFORE_IMPORT=0` (a
documented knob) able to pin an owner forever; under default config the
two are identical, and such a create re-renders its own set inside its one
spawn. A **second, independent** refusal keeps Manual borrowers out
whatever their ceiling — retention protects a live-borrowed owner from
pruning but not from a hand delete, so a curated keeper owns its files
rather than risking a silently emptied gallery.
`createManual()` joined the write coordinator so every prune and
borrow establishment serialize under the settings write lock;
chain-freedom (no backup both borrows and owns) is pinned as a test; the
backups table now states the active retention policy (en+he). Designed,
operator-gated (AskUserQuestion), and reviewed pre-implementation by the
1.8 batching session (approved with 1 Important + 2 Minor, all
addressed); gate 2008 tests / 20,982 assertions / 357s.

### F13. Media-picker browser tests never adopted the classified-artifact filter — `FIXED` (identified 2026-08-12, fixed 2026-08-12, `d84b1c7`)

Three full-suite runs across `c1cbae9`/`1443b7d` failed on the **first**
attempt and passed on an immediate re-run of the same commit, each ~**+30.5s**
over a green run (387.6s / 387.5s vs 357.1s — the identical delta identifies
the same test all three times). Identified on the third:
`tests/Browser/MediaPickerUploadFocusReturnBrowserTest` → *"it returns focus
to the workspace when the upload settles"*, failing at bare
`$page->assertNoJavaScriptErrors()` (`:138`; siblings `:214`, `:273`) on
`ResizeObserver loop completed with undelivered notifications` at
`/admin/content-groups/{id}/edit`. `tests/Browser` in isolation: 56/56.

**Not a new class, and not a mystery.** That exact message is already
classified in-repo as a Chromium artifact from the Filament body/sidebar
observer (`settings-step5b-card-template-preview-lg-column-handoff.md:168-175`),
and `current-project-state.md:235` already records the same signature ("one
ResizeObserver flake under full-run load passed 3/3 in isolation"). The
**remedy also already exists and is a standing requirement**: record the
artifact's count, strip *only* that exact known message from Pest's
accumulator, and still fail on every unexpected message — explicitly "must
not be described as a literal zero-message run"
(`docs/research/settings-performance/44-settings-development-metrics-retirement-mini2-implementation-plan.md:128`
keeps that filtering mandatory). `MediaPickerUploadFocusReturn` — a sibling
file in the same suite — simply never adopted it. A solved problem unapplied
at a second site: **`one-home` applied to a technique rather than a value**,
which is why the fix below is not "paste the literal a sixth time".

**The existing seven sites are two divergent techniques at two layers, and
that is what makes this harder than an extraction** (established on
re-audit, verified against the tree):

- **Strict form, 7 sites** — `MediaPickerBrowserTest` (`:504`, `:688`,
  `:809`, `:1052`, `:1283`, **`:1423`** — six, corrected on implementation
  from the five first registered here) and
  `MediaResourceGalleryBrowserTest:836`: JS-side, inside the evaluate block,
  mutating `window.__pestBrowser.jsErrors` with **exact message equality**
  (trailing period included). This is the step5b shape and the one the
  standing requirement describes.
- **Loose form, 1 site** — `MediaPickerCloneReproBrowserTest:269-276`: a
  PHP-side helper filtering with
  `! str_contains($error, 'ResizeObserver loop completed')` — a **prefix
  match**, which would swallow any future ResizeObserver variant — and
  carrying a second suppression beside it,
  `! str_contains($error, 'isFromCancelledTransition')`. **Correction (made
  on implementation, against this entry's original wording): this is not the
  loose form of the same technique at a different layer. It is a different
  error channel.** `window.__m2.pageErrors` is that test's *own* accumulator,
  declared at `:106-110` and fed by its own `error`/`unhandledrejection`
  listeners at `:128`/`:131`; it never touches
  `window.__pestBrowser.jsErrors`. Same message text, different channel —
  which is why the fix tightens it in place rather than routing it through
  the shared helper.

Consequences for the fix, in order of how easily they are got wrong:
**(1)** the two intercept at different layers (browser-side accumulator vs
post-hoc PHP array), so one helper cannot serve both without choosing a
layer — design work, not extraction. **(2)** consolidating on the
*convenient* form would **weaken six sites**: the PHP substring form is the
easier one to share and is the wrong one, because a prefix match is exactly
what the mini2 requirement forbids. The consolidation must land on strict
equality. **(3)** `isFromCancelledTransition` must not ride along. It is
**not** an unexplained blanket suppression — the helper's own docblock
(`:262-267`) justifies it as Alpine's overlapping-modal-transition rejection
noise, predating that test's defect — but that justification exists **only
at that call site**: the string appears exactly once in the whole repo and
in no doc. So it is a `one-home` case of its own nested inside this one, and
it needs classifying in a doc *before* any shared helper could carry it.

**Resolved (`d84b1c7`, 2026-08-12).** `tests/Pest.php` owns the technique:
`knownResizeObserverArtifact()` holds the literal,
`stripKnownResizeObserverArtifacts()` counts and strips only exact matches and
returns the count, and `assertNoUnexpectedJavaScriptErrors()` composes the two
so unexpected messages still fail through Pest's own assertion. Every
converted site is one line, and the helper is the only place the suite reaches
into `window.__pestBrowser.jsErrors`. Consolidation landed on strict equality,
not the convenient substring form.

Four things worth carrying forward, three of them corrections to this entry's
own first draft:

1. **Six strict sites in `MediaPickerBrowserTest`, not five** (above).
2. **CloneRepro is a different channel, not a loose variant** (above). Its
   ResizeObserver arm tightened to exact equality against the shared literal;
   exact matching is safe across the `describeError()` transform in its path,
   because that function returns strings unchanged and an `Error`'s
   `.message` verbatim, so Chromium's text reaches the accumulator unaltered
   either way.
3. **The `:504` strip is redundant for that test's own end-of-test assertion
   but not for its mid-test `js_errors` diagnostic at `:644`, whose
   `.slice(0, 5)` cap lets a benign artifact evict a real error — so it
   survives as a strip-only helper call.** Its count is now recorded in the
   payload that diagnostic feeds, so a `pending_cleared`/`direct_shows_media`
   failure states how many artifacts were stripped.
4. **`isFromCancelledTransition` now has a written home** rather than only a
   call-site line: Alpine cancels modal transitions when cycles overlap, and
   that rejection noise predates the M2 defect and is unrelated to
   component-root integrity. It stays a **substring** match, deliberately —
   it arrives on the `unhandledrejection` channel where the reason object's
   serialized shape varies — and stays scoped to
   `MediaPickerCloneReproBrowserTest`, the only place in the repo that
   observes it.

**Isolation could not have proved this fix, and that is the durable lesson.**
The artifact never fires in isolation, so a green isolated suite exercises the
filter *zero* times — the count-canary trap. The filter's behavior is
therefore pinned by `tests/Browser/JavaScriptErrorArtifactFilterBrowserTest`,
which seeds the accumulator directly and asserts both the count and the
strictness: a near-miss differing from the classified message **only by its
trailing period survives the strip**, which is precisely what the old
substring form would have swallowed. Prose forbids a prefix match; that test
is what makes the prohibition fail loudly.

**Scope deliberately not crossed:** `CardTemplatePreviewBrowserTest` keeps its
own copies of the literal — **four declarations under two different variable
names** (`knownResizeObserverMessage` at `:489`/`:651`, `knownMessage` at
`:1466`/`:1614`), which is the `one-home` defect compounding inside one file
rather than merely persisting. It is left alone for a reason that stands on
its own: that file does not strip-then-assert, it *measures* — it counts the
artifact into a returned payload and asserts on both the count and the
surviving messages — so a stripping helper would break what it is testing.
The mini2 plan (`44-…-mini2-implementation-plan.md:127-129`) is the reason
that filtering cannot simply be *deleted* — and nothing more. It is **not**
the reason the file is left unrefactored, and must not be cited as one: it
mandates retaining the behavior, which a shared helper would also satisfy, and
reading it as "do not refactor this file" would ossify the boundary on a
prohibition the document never makes. A future consolidation
there is open, and it needs a measurement-shaped helper, not this one.

**Accepted limit, recorded because it is how this could quietly come back.**
The guard test seeds the accumulator from the same constant the helper reads,
so it proves the filter is *self-consistent* — not that the literal still
matches what Chromium actually emits. That is `expectation-from-home`
(`defect-cause-patterns.md:312`), an accepted limit here rather than a defect,
since nothing in-process can observe the real message on demand. The
consequence is the part that matters: **if this flake ever returns, the first
suspect is the literal drifting from Chromium's real text** — a Playwright
bump dropping the trailing period would do exactly that — **and the guard test
will stay green throughout.** Confirm the emitted text before re-litigating
the filter. Recorded here deliberately as a *disclosed* limit and **not
counted as an `expectation-from-home` sighting**: a documented accepted limit
is not a defect instance, and counting it would spend that pattern's sweep
trigger on one. The non-registration is a decision, not an omission.

**Also found while converting:** `visit()` returns `PendingAwaitablePage`,
which only becomes `AwaitableWebpage` once a method is called on it. Every
existing call site arrives post-`->resize()`, so a narrowly-typed shared
helper passes the entire suite and then `TypeError`s on the first caller who
writes plain `visit()` — the shape a new test is most likely to use. The
helper accepts both.

**Proof:** three consecutive green full-suite runs — 2012 tests / 20,991
assertions at 353.0s / 366.6s / 353.9s, none carrying the +30.5s artifact
signature that identified the failure. One green run would have been
worthless at ~1-in-2. Touched files 20/20 in isolation; pint clean; FilaCheck
35/35.

**Found by:** the register-1.8 session while independently re-verifying the
1.9 gate (three sightings, one identification). Not filed against 1.9 — it
predates that round and touches none of its surface; the 1.9 session's own
runs were all green. Registered per the register-at-the-moment rule.
**Provenance note:** both sessions wrote this entry within a minute of each
other (the 1.8 session's `8cc4783` also swept up the 1.9 session's
still-uncommitted append of the same finding — `shared-index-entanglement`,
live). This is the merge of the two, keeping every distinct fact from each;
the 1.8 session is re-auditing the merged text.
