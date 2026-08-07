# Database alignment — spec

**Status:** approved in conversation 2026-08-08. Not implemented. Supersedes the
clock half of `hebrew-collation-and-clock-plan.md` and rewrites
`mysql-test-lane-spec.md` §3/§6/§7.

**Goal:** local dev, the test lane and production run the same database engine,
the same collation and the same clock — and none of that correctness depends on
a setting that lives outside this repository.

Everything marked **[MEASURED]** was run read-only on 2026-08-07/08. Production
was contacted read-only; nothing was changed.

---

## 1. The principle

> **Being an Israeli app is a presentation fact, not a storage fact.**
> Storage names moments unambiguously. The UI shows them on Jerusalem walls.
> No layer may translate behind your back.

Every decision below follows from that one line.

---

## 2. Measured state

### Production — `podtext.co.il` on Forge server `general-dev`

```
Ubuntu 24.04.4 LTS      OS timezone Asia/Jerusalem (IDT, +0300), NTP healthy
MySQL 8.0.46-0ubuntu0.24.04.3   (Ubuntu archive package; no Oracle apt repo)
40 tables · 3,544 rows · 4.9 MB
schema default   utf8mb3 / utf8mb3_unicode_ci      ← wrong
all 40 tables    utf8mb4_unicode_ci
clock            global/session SYSTEM · system IDT · TIMEDIFF(NOW(),UTC) 03:00:00
db user          podtext (dedicated — correct)
```

Three sites share that MySQL daemon: `podtext.co.il`, `ikc4.data4.work`,
`ari.data4.work`.

### Local — Herd

```
MySQL 9.4.0 on 3306, database podtext
40 tables · 821 rows · 5.8 MB
schema default   utf8mb4 / utf8mb4_unicode_ci
40 tables · 183 collated columns   utf8mb4_unicode_ci
clock            global/session SYSTEM · system IDT · offset 03:00:00
named zones loaded   0            ← 'Asia/Jerusalem' is unresolvable
db users         root only        ← no dedicated app user exists
```

### Schema, both environments

```
80 TIMESTAMP columns · 0 DATETIME · 0 DATE · 0 TIME
DEFAULT CURRENT_TIMESTAMP:   exactly 1  (failed_jobs.failed_at)
ON UPDATE CURRENT_TIMESTAMP: 0
```

The `db-clock-coupling` ledger entry says 28. It counted `$table->timestamp(`
calls in migrations, not columns. **The real count is 80** — same undercount
class as B8's index inventory. Generate these numbers, never hand-list them.

### Version ceiling [MEASURED]

`apt-cache policy mysql-server-8.4 mysql-server-9.0` returns **nothing** on
production — neither package exists in any configured repo, and Ubuntu 24.04
ships MySQL 8.0 only (candidates: 8.0.46, 8.0.36). Forge's own docs:
*"Laravel Forge doesn't provide automatic database server upgrades."* Its
8.0/8.4/9.x menu applies at **server-creation time only**.

**Production is on 8.0.46 and stays there.** Moving to 8.4 LTS is a separate,
unscheduled project — noted, not in scope.

---

## 3. The collation decision — chosen on merit

The operator's instruction was explicit: *the collation must be what we best
use, not what is current and not what is default.*

Behaviour of each candidate, [MEASURED] on Hebrew and on emoji:

| test | `unicode_ci` (today) | **`0900_ai_ci`** | `0900_as_cs` | `general_ci` |
| --- | --- | --- | --- | --- |
| niqqud — `שָׁלוֹם` = `שלום` | ✓ | ✓ | ✗ | ✗ |
| final form — `ם` = `מ` | ✓ | ✓ | ✗ | ✗ |
| modern mark — `א` = `א`+U+05C7 | **✗ wrong** | ✓ | ✗ | ✗ |
| trailing space — `abc` = `abc ` | **✓ collapses** | ✗ distinct | ✗ | ✓ |
| **emoji — 🎧 = 🎤** | **✓ ALL EQUAL** | ✗ distinct | ✗ | ✓ |

**Decision: `utf8mb4` / `utf8mb4_0900_ai_ci`, everywhere, no per-column
exceptions.**

Reasons, in order of weight:

1. **`utf8mb4_unicode_ci` treats every emoji as the same character.** It is
   UCA 4.0.0 (2003) and MySQL collapses everything outside its weight table to
   one weight. Two rows differing only by emoji collide in a unique index. In a
   podcast catalogue that is a live defect, and it has nothing to do with Hebrew.
2. **It mis-handles modern Hebrew.** Marks assigned to Unicode after 4.0 are
   unassigned to it. `0900_ai_ci` is UCA 9.0.0.
3. **NO PAD** makes trailing spaces significant, matching PHP's `===` instead of
   surprising you with PAD SPACE equality.
4. It is materially faster than the legacy UCA implementations.

That it is also MySQL's server default on both 8.0 and 9.x is **incidental, not
the argument**.

**No Hebrew-specific collation exists** [MEASURED] — MySQL ships tailorings for
Czech, Spanish, Turkish and others; Hebrew needs none, because the root DUCET
order already sorts it correctly.

**This overturns `hebrew-collation-and-clock-plan.md` §3**, which called the
null option "genuinely defensible". That analysis weighed sorting and Hebrew `=`
semantics and never measured emoji.

### Search is unaffected

Hebrew search already works, and does not depend on this. `HebrewSearchFold`
writes niqqud-stripped `*_search` shadow columns searched with a portable
`LIKE`. That design stands. The collation governs `=`, unique indexes and
sorting — not search.

---

## 4. The clock — the answer is to remove the question

### Why not "set everything to Jerusalem"

Not a clock problem — a calendar problem. [MEASURED] on the live configuration:

| written | read back |
| --- | --- |
| `2026-03-27 01:59:00` | `2026-03-27 01:59:00` ✓ |
| **`2026-03-27 02:30:00`** | **`2026-03-27 03:00:00`** ✗ 30 minutes appeared |
| `2026-10-25 01:30:00` | `01:30:00` — and so does the instant an hour later |

Israel deletes an hour every March and duplicates one every October. A Jerusalem
wall clock is a fine way to **show** a moment and a lossy way to **name** one.
UTC is not "more correct" — it is *injective*, and that is its only relevant
property.

[MEASURED] the offset is **+2 in winter and +3 in summer**, so any migration
using a flat `INTERVAL 3 HOUR` is wrong. This is finding A2.

### The real bug is a disagreement, not a wrong clock

Nobody has the wrong time — `UTC_TIMESTAMP()` is true UTC (A1), NTP is healthy
on both machines. The bug is that a written string means different things to
different layers: Laravel writes `10:00` meaning UTC, MySQL reads it meaning
Israel.

That translation exists **only because the columns are `TIMESTAMP`**, which
converts on both read and write using the session clock. The conversion is
symmetric, which is why it stayed invisible: round-tripping is lossless, and it
breaks only for values the *database* generates — a surface of exactly one
column.

### The fix: change the column type

`DATETIME` does not convert. Ever. The string written is the string returned, on
any server, under any timezone.

**Converting `TIMESTAMP → DATETIME` with the session left as it is today is a
no-op for every value.** MySQL renders each stored epoch through the current
session and stores that literal — which is precisely the literal the app has
always read back. Nothing shifts. Nothing displays differently.

This is also the answer to *"how do we move to UTC after living on Jerusalem?"*
**You never fix the times. You freeze them, then change the clock underneath.**

What it kills at once:

| problem | why it dies |
| --- | --- |
| Spring-forward hole | nothing is interpreted as local time on write |
| Restore onto a differently-configured server shifts everything | the literal is the literal |
| `mysqldump` / TablePlus / `php artisan db` / replicas reading shifted values | every client sees the same string |
| The seasonal +2/+3 data migration | there are no epochs left to shift |
| Dependence on the Mac's and Forge's OS timezone | structurally removed |
| The 2038 ceiling | `DATETIME` runs to year 9999 |

**`TIMESTAMP` is safe only while every client is configured identically,
forever. `DATETIME` is safe unconditionally.**

---

## 5. Target state

| # | Place | Setting | Today |
| --- | --- | --- | --- |
| 1 | Local dev machine OS (the Mac) | **Asia/Jerusalem** — it is a personal setting; nothing inherits it once 2 is pinned | ✅ |
| 2 | Local DB server + session | **UTC**, pinned in `config/database.php` *and* `default-time-zone` in `my.cnf` | ✗ `SYSTEM` → IDT |
| 3 | Local app | **UTC**, hardcoded, no `env()` | ✅ `config/app.php` |
| 4 | Production server OS | **UTC** — no human reads it, everything unpinned inherits it | ✗ Asia/Jerusalem |
| 5 | Production DB | **UTC**, pinned — identical to 2 | ✗ `SYSTEM` → IDT |
| 6 | Production app | **UTC** — identical to 3 | ✅ |
| 7 | Test DB | **UTC**, pinned — identical to 2 | to build |
| 8 | Column type | **`DATETIME`**, never `TIMESTAMP` | ✗ 80 `TIMESTAMP` |
| 9 | Display | **Asia/Jerusalem**, one home | ✅ `UiTimezone` |
| 10 | User input | read as Jerusalem, converted to UTC; **DST gap rejected** | ⚠️ gap unhandled |
| 11 | Scheduler | explicit `->timezone()` when a human means local | ⚠️ see 10.4 |

**1 and 4 differ deliberately.** Your Mac's clock is a convenience nothing
depends on. A production server's clock is inherited by cron, syslog and nginx,
so it must be the neutral value. Forge defaulting it to Asia/Jerusalem is a
sensible guess for an Israeli account and the wrong setting for a server.

**The app layer is already correct in both environments.** The database layer is
wrong in both, identically. That is the entire gap.

---

## 6. Dedicated database users

`root` as the application user removes the barrier every other safety layer
leans on. Production already has a dedicated `podtext` user; local does not.

```sql
-- local, on the app daemon (Herd MySQL 9.4.0, port 3306)
CREATE USER 'podtext'@'127.0.0.1' IDENTIFIED BY '<password>';
GRANT ALL PRIVILEGES ON `podtext`.* TO 'podtext'@'127.0.0.1';

-- test, on the NEW daemon (Herd MySQL 8.0.46, port 3307)
CREATE DATABASE `podtext_test`
  DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
CREATE USER 'podtext_test'@'127.0.0.1' IDENTIFIED BY '<password>';
GRANT ALL PRIVILEGES ON `podtext_test`.* TO 'podtext_test'@'127.0.0.1';
FLUSH PRIVILEGES;
```

No grant on `podtext` is issued to the test user, and the test user lives on a
daemon where `podtext` does not exist at all. Passwords go in `.env` (gitignored)
with placeholders in `.env.example` — **never `.env.testing`**, which
`.gitignore:15-17` does not cover.

---

## 7. The test lane

**Server: a second Herd service, MySQL 8.0.46, port 3307.**

```bash
herd services:create mysql --service-version=8.0.46
```

[MEASURED] `herd services:versions mysql` offers 9.7.1, 9.4.0, 8.4.10 and
**8.0.46** — production's exact version. This replaces both options the old spec
weighed: it gives Docker's physical isolation (`podtext` does not exist in that
daemon) without a compose file, a container or a separate lifecycle.

**This supersedes `mysql-test-lane-spec.md` §6/§7.** That section argued for
Docker because "MySQL 9's server default collation is `utf8mb4_0900_ai_ci`" —
but production's 8.0 server default is `utf8mb4_0900_ai_ci` too [MEASURED], so
that specific argument was wrong. The conclusion survives for other reasons
(SQL modes, optimizer behaviour, reserved words) and is now free.

**SQLite is retired entirely.** One lane, not two. Consequences:

- `assertSafeTestingDatabase()` becomes **one shape**, simpler than the old
  two-shape design.
- No `phpunit-mysql.xml`, no second composer script.
- The `driver-lenient-fallback` divergence class stops existing rather than
  being caught by a lane someone might forget to run.

**Connection** — `mysql_testing` in `config/database.php`, sharing **no env key**
with the app connection:

```php
'mysql_testing' => [
    'driver' => 'mysql',
    'host' => env('DB_TESTING_HOST', '127.0.0.1'),
    'port' => env('DB_TESTING_PORT', '3307'),
    'database' => env('DB_TESTING_DATABASE'),   // no default — must fail closed
    'username' => env('DB_TESTING_USERNAME'),
    'password' => env('DB_TESTING_PASSWORD', ''),
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_0900_ai_ci',
    'timezone' => '+00:00',
    'prefix' => '', 'prefix_indexes' => true, 'strict' => true, 'engine' => null,
    // no 'url' key at all — a DSN silently overrides host and database
],
```

Not sharing `DB_DATABASE` is the load-bearing structural decision: no typo in
one variable can alias the real schema, because the real schema's name lives in
a variable this connection never reads.

**Why `'timezone' => '+00:00'` here is safe rather than dishonest.** Ordinarily
a lane whose clock is *correct* while production's is *wrong* would hide the
disagreement — tests green, production broken, which is `driver-lenient-fallback`
pointed at the clock. After step 3 that objection dissolves: `DATETIME` columns
do not convert, so the session clock is inert on both sides and lane and
production agree by construction rather than by configuration. If the lane were
built **before** step 3, it must instead be left on `SYSTEM` to mirror
production honestly.

**Guard clauses**, all required, each with its own refusal test:

| Clause | Refusal reason |
| --- | --- |
| `database.default === 'mysql_testing'` | the app's `mysql` connection is never a test connection |
| driver is `mysql` | |
| no `url`/DSN key | a DSN overrides host and database and makes every check below meaningless |
| name matches `/^[a-z][a-z0-9_]*_test(_[0-9]+)?$/` | optional suffix is paratest's token |
| name ≠ any `DB_DATABASE` read from the **raw `.env` files**, not `env()` | a forced phpunit var could mask the real name |
| **port ≠ the app connection's port** | new clause, made possible by the separate daemon |
| host ∈ `127.0.0.1`, `localhost`, `::1` | a remote host is never a test target |
| username non-empty, **not `root`**, ≠ the app's `DB_USERNAME` | protects the grant barrier |
| first-use empty-schema fingerprint under `storage/framework/testing/mysql-lane/` | catches a stranger's database |

Plus, after boot: point `mysql` and `mariadb` at `unreachable_from_tests`.

Honest limit, unchanged from the old spec: after the first run the schema holds
exactly the app's migrations and is indistinguishable from a real copy. The
check answers *"is this a stranger's database?"*, never *"is this a second copy
of mine?"*

**Expected fallout — this is the point, not a side effect.** Budget for strict
mode rejections, MySQL not rolling back DDL, ordering assumptions resting on
SQLite's rowid, identifier quoting, `||`, `group_concat`, `lockForUpdate()`.
Expect the suite several times slower than `:memory:` — the last full gate was
1,651 tests in 473s.

Two gains: the folded-search shadow columns finally execute on the real engine,
and `LegacyRoleBackfillSchemaContract::expected('mysql')` stops being asserted
from SQLite and becomes verifiable for real — turning that dormant class into a
canary that the lane's schema is production-shaped.

---

## 8. Display and input, globally

[MEASURED] today: **52 files call `UiTimezone::name()`, 18 call `UiFormats::`.**
Filament has global hooks that make almost all of that unnecessary, and
`AppServiceProvider` already uses `configureUsing` for `Select`, `SelectFilter`,
`Tab`, `Table`, `Action` and `Section` — this is the same mechanism.

| What | Hook | Covers |
| --- | --- | --- |
| Timezone | `FilamentTimezone::set(UiTimezone::name())` | every `DateTimePicker`/`TimePicker` (loads Jerusalem, **converts back on save**), every `TextColumn::dateTime()`, every `TextEntry::dateTime()` |
| Table formats | `Table::configureUsing(…->defaultDateTimeDisplayFormat(UiFormats::dateTime())…)` | verified: `tables/.../CanFormatState.php:84` resolves a bare `->dateTime()` via `$column->getTable()` |
| Form + infolist formats | `Schema::configureUsing(…)` | verified: `infolists/.../CanFormatState.php:84` resolves via the container, which is a `Schema` |
| Picker formats | `DateTimePicker::configureUsing(…)` | pickers hold their **own** copy (`DateTimePicker.php:71-77`); they do not read the Schema's |

Deliberate exclusion, documented by Filament and **not to be "fixed"**: the
default timezone does not apply to date-only fields (`DatePicker`, `->date()`),
because applying a zone to a value with no time shifts the date itself.

**Outside Filament** — raw Blade — one Carbon macro registered beside the rest:

```php
Carbon::macro('forDisplay', fn (?string $format = null): string => $this
    ->timezone(UiTimezone::name())
    ->translatedFormat($format ?? UiFormats::dateTime()));
```

Still one call per site, but **zero configuration** at the site. Same route for
exports and the dashboard's Jerusalem-day bucketing.

**Rejected: pushing the timezone into the model** — a cast returning a
Jerusalem-zoned Carbon, or a base-model override. It looks like the most global
answer and poisons everything downstream: `save()` would write wall-clock,
`whereDate()` would compare the wrong day, and comparisons against `now()` (UTC)
would be 2-3 hours out. **Model stays UTC; convert at the edge.**

---

## 9. Order of operations

The order is the whole trick. Reversed, step 4 shifts every date in the app.

| # | Step | Environments | Notes |
| --- | --- | --- | --- |
| 0 | Snapshot / restore tooling | local, production | **prerequisite** — the rollback for 2 and 3. Shape still open. |
| 1 | Dedicated users | local, test | production already has one |
| 2 | Collation → `utf8mb4_0900_ai_ci` | all three | schema default, tables, columns. Production also fixes `utf8mb3`. Preceded by the B3 scan. |
| 3 | `TIMESTAMP` → `DATETIME` ×80 | all three | **session left as-is** — value-preserving. Same `ALTER` pass as 2. |
| 4 | Production server OS → UTC | production only | safe only after 3. Keep the Mac on Jerusalem. |
| 5 | Pin session UTC in `config/database.php` + `my.cnf` | all three | now belt-and-braces, not load-bearing |
| 6 | Load tz tables (`mysql_tzinfo_to_sql`) | all three | one command; closes A4 |
| 7 | Test lane | — | §7 |
| 8 | Display/input globals | — | §8 |
| 9 | Guards | — | §11 |

Steps 2 and 3 are both `ALTER TABLE` and should run as one pass per table.
**At 40 tables / 3,544 rows / 4.9 MB this completes in seconds**, so production
needs a brief maintenance-mode window, not a scheduled outage.

---

## 10. Carried caveats and known gaps

### 10.1 `failed_jobs.failed_at` carries a real error

The one `DEFAULT CURRENT_TIMESTAMP` column in the schema. Those rows were
written **by MySQL** in local time, so they are genuinely ~3 hours off from the
app's UTC frame — unlike every app-written value, whose round trip cancels.
Converting to `DATETIME` **preserves that error rather than fixing it.**

Fix: drop the default and let Laravel write the column. Decide explicitly; do
not let it ride along silently.

### 10.2 `$table->timestamps()` will re-introduce `TIMESTAMP`

[VERIFIED in vendor] `Blueprint.php:1293` — `timestamps()` is literally two
`timestamp()` calls, and `MySqlGrammar::typeTimestamp()` emits `TIMESTAMP`.
So every future migration drifts back by default.

**Prose cannot hold this. A statement-scan guard must**, in the shape this repo
already uses for enum literals and format literals.

### 10.3 The DST input gap

`FilamentTimezone::set()` makes pickers read and write Jerusalem correctly, but
an admin picking **02:30 on 27 March 2026** selects a time that does not exist,
and Carbon silently moves it to 03:30 with no error. No configuration prevents
this. It needs an explicit validation rule, applied globally through
`DateTimePicker::configureUsing()`.

Config handles the 364 ordinary days; one rule handles the day it does not.

### 10.4 The scheduler already disagrees with its author

`routes/console.php:13` — `Schedule::command('media:prune-quarantine --apply')
->dailyAt('03:30')` with no `->timezone()`. Laravel evaluates against
`config('app.timezone')`, so it runs at **03:30 UTC = 06:30 Israel**.

[VERIFIED] this also confirms step 4 is safe: the scheduler already evaluates in
UTC and Forge's cron fires every minute, so changing the server's OS timezone
moves no scheduled job.

### 10.5 Surviving blockers from the old plan

Six of B1-B9 existed only because that plan dumped and restored into a new
schema. In-place `ALTER` has no dump (B1, B2, B6), no cutover (B7), raises a
real `1062` instead of silently corrupting an index under `UNIQUE_CHECKS=0`
(B4), and preserves literals instead of re-rendering epochs under a different
zone (B9).

**Three survive and are mandatory:**

- **B3 — the duplicate scan.** `0900_ai_ci` merges values `unicode_ci` keeps
  distinct (`'א' = 'א'+U+05C7` goes 0 → 1). Two legal rows can become a
  duplicate key. Must run against **production** data, not local: the local
  corpus is ~460 rows of near-ASCII identifiers with one non-ASCII
  unique-indexed value and zero niqqud in indexed columns — 821 rows against
  production's 3,544 [MEASURED] — so a local clearance is close to vacuous.
- **B5 — the trailing-space check must be length-based.**
  `col <> TRIM(TRAILING ' ' FROM col)` is a tautology on a PAD SPACE collation.
  Use `LENGTH(col) <> LENGTH(TRIM(TRAILING CHAR(32) FROM col))`. This matters
  more now, not less: `0900_ai_ci` is NO PAD, so trailing spaces become
  significant.
- **B8 — generate the index inventory from `information_schema`.** The old plan
  said 5, then 22; the real figure was 39. Never hand-list.

### 10.6 Two files that become misleading

Both exist *because* of the clock disagreement and must be revisited in the same
change, not after it:

- `PublicTranscriptionSelector::sqlMoment()` — its docblock argues *against*
  this migration. Update it, and answer explicitly whether it reverts to
  `CURRENT_TIMESTAMP`.
- `JerusalemDailySeries` — buckets days in PHP precisely because raw SQL
  `DATE()` buckets on the database timezone.

### 10.7 `db:check-settings` is not deployed

[MEASURED] production reports *"Command db:check-settings is not defined"* —
commit `9632b91` is local only. Deploy it before production verification, or run
the SQL directly.

---

## 11. Guards

Prose advises; only a test fails.

| Guard | Prevents |
| --- | --- |
| Ban `$table->timestamp(` / `timestamps()` in new migrations | 10.2 — the silent drift back |
| Ban `useCurrent()` / `CURRENT_TIMESTAMP` in migrations | the entire DB-generated-time class |
| Extend `db:check-settings` to assert collation **and** column type, non-zero exit | drift on any of the three servers |
| Extend the existing `Asia/Jerusalem` anti-drift test to ban now-redundant `->timezone()`/`UiFormats::` at Filament call sites | §8 regressing back to per-site config |
| The lane guard's own refusal test, one case per clause in §7 | a guard nobody tests is prose wearing a guard's clothes |
| `LegacyRoleBackfillSchemaContract::inspect()` vs `expected('mysql')` on the real lane | the lane's schema silently diverging from production's shape |

---

## 12. Open

- **The snapshot's shape (step 0)** — dump files with history, a server-side
  schema copy, or both. Undecided; it gates steps 2 and 3.
- **Production 8.0 → 8.4 LTS.** Noted, deliberately unscoped. Ubuntu's package
  keeps receiving backported security fixes for the 24.04 window, so there is no
  urgency — but Forge cannot do it in place, and the daemon is shared by three
  sites.
- **Target #10 verification** — whether Filament's pickers currently round-trip
  admin input through Jerusalem correctly, independent of the DST gap.
