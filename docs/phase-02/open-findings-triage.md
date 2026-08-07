# Open findings — triage list

Everything found and **not fixed**. One line of what it is, what's proven, what's next.
Status: `OPEN` = nothing done · `BOUNDED` = diagnosed, fix not chosen · `PARTIAL` = half done.

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

## B. PHPStan / larastan (614 errors, gate not wired in)

### B1. Enum-and-datetime casts resolve as `string` — `BOUNDED`
`$item->status` types as `string`, not `PublicationStatus`, so every enum comparison is reported "always false". Not enum-specific — a `datetime` cast also resolves to `string` (`PruneMediaQuarantine.php:48`, *"Cannot call method timezone() on string"*).

**Four hypotheses tested and eliminated:**
| tested | result |
| --- | --- |
| `$attributes` string default beside the cast | no change |
| `databaseMigrationsPath` set/unset | no change |
| `@property` annotation on the model | no change |
| PHPStan result cache | no change (cleared) |

Confirmed *not* the cause: no accessor, no `@mixin`, `casts()` is a plain literal array, `ModelPropertyExtension` is registered, `bootstrapFiles` is present.
**Next:** likely larastan's app bootstrap failing silently on this app (Filament panels/providers). Run larastan with `-v`/debug and check whether the container boots. This is the single biggest lever on the 614.

### B2. The motivating rule doesn't fire — `BOUNDED`
Because of B1, `match.unhandled` never reports on `MediaFilesystemMutationCoordinator.php:1540`: PHPStan thinks the subject is a `string`, so it isn't a match over an enum. **The parser test catches what PHPStan misses** — keep both. Fixing B1 should make this fire.

### B3. `PublicFrontConfigValidator.php:69` — `OPEN`
A `match ($key)` over 11+ string literals, **no default arm**. Throws on any unrecognised config group. A *scalar* match, so the enum sweep structurally cannot see it.
**Next:** add a default arm, or route the keys through an enum.

### B4. 614 errors untriaged — `OPEN`
Real ones in the sample: `Cannot call method timezone() on string`, `Offset … on array{} does not exist`, `Result of && is always false`.
**Next:** triage by identifier after B1; do not baseline.

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
