# Open findings — triage list

Everything found and **not fixed**. One line of what it is, what's proven, what's next.
Status: `OPEN` = nothing done · `BOUNDED` = diagnosed, fix not chosen · `PARTIAL` = half done ·
`FIXED` = closed here, kept because a live entry depends on it · `WITHDRAWN` = the finding's premise was wrong.

---

## A. Production timezone & locale

**Measured on production 2026-08-07** (read-only, `podtext.co.il`):

```
MySQL 8.0.46          global/session tz: SYSTEM      system tz: IDT
NOW() 17:00:23        UTC_TIMESTAMP() 14:00:23      TIMEDIFF 03:00:00
server charset utf8mb4 / utf8mb4_0900_ai_ci
schema default utf8mb3 / utf8mb3_unicode_ci     ← see A3
all 40 tables         utf8mb4_unicode_ci
```

### A1. The +3 is a timezone, not a broken clock — `BOUNDED`
`UTC_TIMESTAMP()` is real UTC; only `NOW()` is offset. So no NTP problem.
**Next:** decide between pinning the connection tz (cheap) or full UTC migration.

### A2. It is a DST-observing named zone (`IDT`), not a fixed `+03:00` — `OPEN`
This was the deciding open question and it came back the **worse** way.
Consequences, both now live:
- UTC literals landing in the spring-forward gap (local 02:00–02:59, late March) **cannot be stored faithfully**.
- Any per-row shift is **+2 in winter, +3 in summer** — a flat `INTERVAL 3 HOUR` is wrong.

**Next:** pinning `'timezone' => '+03:00'` in `config/database.php` would shift every winter row by an hour. Pin to a *named* zone or don't pin. Must be resolved before any clock work.

### A3. Schema default is `utf8mb3` — `OPEN` (new, not previously known)
All 40 existing tables are `utf8mb4_unicode_ci`, so nothing is broken today.
But **any new table created without an explicit charset inherits `utf8mb3`** — 3-byte, no emoji, no full Unicode.
**Next:** `ALTER DATABASE podtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci` — metadata only, does not touch table data. Cheap and worth doing.

### A4. `mysql.time_zone_name` unreadable — `OPEN`
`SELECT command denied to user 'podtext'`. So we cannot confirm whether named-zone conversion (`CONVERT_TZ`) works — and `CONVERT_TZ` returns **NULL** when those tables are empty, which would blank a column mid-migration.
**Next:** check as root before any migration that uses it.

### A5. Production MySQL 8.0.46 vs local Herd 9.4.0 — `OPEN`
Version divergence confirmed. This decides the MySQL test lane:
**Docker pinned to 8.0, not Herd's 9.4** — see `mysql-test-lane-spec.md` §6/§7, which said to revisit exactly here.

---

## B. PHPStan / larastan (507 errors, gate not wired in)

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

### B3. `PublicFrontConfigValidator.php:69` — `OPEN`
A `match ($key)` over 11+ string literals, **no default arm**. Throws on any unrecognised config group. A *scalar* match, so the enum sweep structurally cannot see it.
**Next:** add a default arm, or route the keys through an enum.

### B4. 507 errors triaged by identifier — `OPEN` (work items, not mysteries)
Triaged 2026-08-07 after B1. No baseline, no `@phpstan-ignore`. Five groups:

**1. Filament's untyped `Model` / `Builder` contracts — 171, a third of the total and the largest family by far.** Filament hands you `Illuminate\Database\Eloquent\Model` and unparameterized `Builder` (`Exporter::$record`, `Resource::getEloquentQuery()`, `modifyQueryUsing(fn (Builder $query) => …)`), and app code calls concrete-model methods on them. `property.notFound` on `Model::$reference_key` (48), `method.notFound` on `Model::transcriptions()` (20), `method.notFound` on `Builder::releasedBy()` / `currentlyPinned()` / `published()` / `orderByEffectivePublishedAt()` (61 — these are ordinary `scopeX()` methods that larastan resolves fine on `Builder<ContentItem>` but cannot on a raw `Builder`), 38 of the 58 `argument.type`, and `assign.propertyType` (4). **Real app typing debt, not a larastan limitation.** Remedy is to narrow at the boundary — type the closure `Builder<ContentItem>`, or give the Filament class a covariant `@property` — never to loosen the check.

**2. `BuildsPublicContentSettingsSubjectSchemas` calls a method its host may not have — 36.** The trait calls `$this->withImportLockSection()`, defined on `PublicContentSettingsSubjectPage`. `CardTemplateEditorPage` uses the trait but extends `SettingsPage`, so it does not inherit the method. An undeclared host requirement; a fatal if any of those paths is reachable from that page. **Real, and the most likely genuine bug in the remainder — worth its own look.**

**3. Two unimported class names, both real — `class.notFound`.** `ContentItemsTable.php:265` declares `?HasTable $livewire` with **no `use` statement**, so PHP resolves it to `App\Filament\Resources\ContentItems\Tables\HasTable`, which does not exist — the parameter accepts `null` and would `TypeError` on any real Livewire instance. `EditorialMetrics.php:617` has a `@return` array shape referencing `ProgressWidgetData`, a class that exists nowhere in the repo. Both are the kind of thing this install was bought to find.

**4. Small real findings, mostly one-offs.** `nullsafe.neverNull` (44 — unnecessary `?->`, now *accurate* because casts resolve), `staticClassAccess.privateMethod` (34 — `static::privateMethod()` in subclassable classes), `function.alreadyNarrowedType` (37 — redundant `is_string()`/`is_array()` guards), `return.type` (27), `method.unused` (6), `catch.neverThrown`, `property.onlyWritten` (2), `identical.alwaysTrue`, `larastan.noUnnecessaryCollectionCall`.

**5. Genuine tool limitations — 10, none needing suppression.** `varTag.nativeType` × 5 in `AppServiceProvider` (macro registration closures: the `@var` lands on `$this` because Filament's `Macroable` rebinds the closure), `unset.possiblyHookedProperty` on Filament's own `$cachedTabs`, `method.notFound` on `ConnectionInterface::getDriverName()`/`getSchemaBuilder()` × 2 (Laravel's contract genuinely lacks them; `DB::connection()` returns the interface, not `Connection`), and `class.notFound` × 2 inside `phpstan/filament-macros.stub` for `App\Enums\UserRole` — a class that exists and resolves fine everywhere else in the codebase, but is not visible in stub-file scope.

**Still not wired into `composer test`** — deliberately. See the note in `phpstan.neon`: a red gate trains people to ignore red. Wire it when the count reaches zero, not before.

---

## C. Enums

### C1. `MediaMutationOperationType::LegacyOwnerRepair` — `OPEN` (guard closed, defect open)
`assertOperationShape()` covers 10 of 11 cases, no default arm. **Does not crash** — both call sites catch `Throwable`, so the row parks forever in `CleanupPending`/`ManualReviewRequired` with the error text as its own explanation.
**Four more lists silently omit it too** (`:887`, `:900`, `:904`, `:1131`) — quarantine verification, cache invalidation, source cleanup, dangling-reference guard.
**Fixing only the match arm is worse than leaving it.** The retirement work must answer all five and clear the red list.

### C2. Two enums outside `app/Enums` — `OPEN`
`app/Support/Importer/SpotifyLinks/SpotifyEntityMode.php`, `app/Auth/LegacyRoleBackfill/PermissionCacheInvalidationOutcome.php`. Neither carries a contract.
Any tool globbing `app/Enums/*.php` misses them — which is what produced every wrong enum count in the playbook.
**Next:** move both, or decide they stay and record why.

### C3. `set-membership-without-totality` — `OPEN`, no guard possible
`in_array($case, [...])` decides membership like a `match` but claims no totality. **No analyser can check it, PHPStan included.**
**Next:** derive the arrays from enum predicates (`array_filter(cases())`) rather than writing literals.

---

## D. Repo / process

### D1. `ce0f3a0` won't bisect — `OPEN`, ruled leave-and-record
It references `HasFoldedSearchColumns` before that file exists (lands in `8ec2ada`). HEAD is correct; only bisect is affected.
**Cause:** a pathspec is *file*-level and both sessions edited `app/Models/ContentItem.php`.
**Next:** nothing, unless a history rewrite is wanted.

### D2. Push pairing — `OPEN`, operator action
66 commits unpushed. Auto-deploy now **cancelled by the operator**, so the urgency is gone, but the pairing still holds whenever deploy happens:
```bash
php artisan migrate --force && php artisan search:backfill-folds
```
Between the two, every pre-existing row is invisible to every search. **Already run and verified locally** — 136 rows, id 56 now findable.

### D3. Pre-test guard consolidation — `OPEN`
Guards are scattered (`TestCase` DB safety, `Pest.php` env forcing, floors in the enum sweep). Not yet a single first-running check with named failures.

---

## E. Deferred by decision (not defects)

- **MySQL test lane** — specced, not built. Now needs Docker/8.0 per A5.
- **Collation change** — the null option is live; §1 of the search spec proved the collation was never what decided Hebrew search.
- **FULLTEXT / Scout** — the only thing that makes `%term%` fast. Shadow columns changed *what* is compared, not that it scans.
- **`imports.name` / `file_name`** — deliberately unfolded (vendor model, machine filenames).
