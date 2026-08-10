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

### F9. Per-boot `information_schema.COLUMNS` count — `accepted`, pending one measurement
`TestCase::assertDisposableSchema()` counts lane TIMESTAMP columns on every
app boot (~1,900×/suite) — the deliberate T17 trade-off that catches
DDL-leak deadlocks at the next boot. The rethink's research phase measures its
real cost; revisit only if it registers against the ~600s wall.

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

### F11. SQLite artifacts — `FIXED` (file + composer) / DP9 open (config default)
The 528KB `database/database.sqlite` was deleted and the `composer.json`
`post-create-project-cmd` touch line removed (`d1d0671`, from the 2026-08-10
cross-session sweep — Task 7B). The `config/database.php` sqlite `:memory:`
connection block is keep-forever: consumed by `tests/TestCase.php`'s
containment, `NonMysqlRefusalTest`, and `TestLaneResetCommandTest`. The
`DB_CONNECTION` default of `sqlite` (`config/database.php:20`) is **DP9** —
recommended flip to `mysql` (a missing env key should fail loudly against a
credentialed daemon, not silently open a file); operator decides at the R
gate.
