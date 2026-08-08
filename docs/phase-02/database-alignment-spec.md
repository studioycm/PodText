# Database alignment — spec

**Status:** approved in conversation 2026-08-08; adversarially re-verified the
same day against the full LaravelDaily archive (407-lesson index + 29 raw
course scrapes), vendor framework/Filament source, and live probes on the local
daemon — 4 claims corrected, the rest confirmed; corrections are folded in
below and marked where they overturned the first draft. The archive itself has
**zero content** on collation, TIMESTAMP semantics, or MySQL-vs-SQLite testing:
decisions 1-3 and 6 rest entirely on this repo's own measurements, and nothing
in the archive contradicts them. Not implemented. Supersedes the clock half of
`hebrew-collation-and-clock-plan.md` and rewrites `mysql-test-lane-spec.md`
§3/§6/§7.

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

**No Hebrew tailoring exists among the Unicode/utf8mb4 collations** [MEASURED] —
MySQL ships tailorings for Czech, Spanish, Turkish and others; Hebrew needs
none, because the root DUCET order already sorts it correctly. (Scoped
deliberately: `hebrew_general_ci`/`hebrew_bin` do exist, but in the legacy
8-bit ISO-8859-8 `hebrew` charset — not utf8mb4 candidates. An unscoped "none
exists" fails anyone re-running `SHOW COLLATION LIKE '%hebrew%'`.)

### The config key that actually controls the future

**`config/database.php:57` — `'collation' => env('DB_COLLATION',
'utf8mb4_unicode_ci')` — is the live mechanism that made today's schema
uniformly `unicode_ci` on a server whose default is `0900_ai_ci`.** Verified in
vendor: `MySqlGrammar::compileCreateEncoding()` stamps **every** future
`CREATE TABLE` with an explicit collate clause read from this key, and
`MySqlConnector` sets the session collation from it. Left untouched, it
recreates the drift with the very next migration — the schema-default change in
step 2 offers zero protection, because the grammar always wins when config
carries a collation.

So step 2 includes: **hardcode `'charset' => 'utf8mb4'` and `'collation' =>
'utf8mb4_0900_ai_ci'` on the app `mysql` connection, removing the `env()`
indirection** — the same no-drift rule `config/app.php` already applies to the
timezone, and the same hardcoding the `mysql_testing` block uses. The stale
`mariadb` block at `config/database.php:77` carries the same default; fix it in
the same pass.

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

External precedent, found in the LaravelDaily archive after this decision was
made independently: the `laravel-user-timezones` course uses
`$table->dateTime()` throughout and its instructor defends the choice in the
comments — *"we are strictly working with UTC all the time and we want Carbon
to handle the offset, not the DB."* Same reasoning, same conclusion.

Two pieces of fine print, both measured, neither changing the decision:

- **"Does not convert. Ever." has one write-side exception.** MySQL 8.0.19+
  converts *offset-suffixed* literals (`'2026-01-15 10:00:00+05:30'`) into the
  session zone even for `DATETIME` columns — measured: stored `06:30` under the
  IDT session. Laravel's write format is `'Y-m-d H:i:s'` with no offset
  (vendor `Grammar.php:282-284`), so app writes never hit this — but until
  step 5 pins the session, a raw-SQL or import path using ISO offset literals
  would convert into Jerusalem wall, not UTC.
- **The conversion permanently collapses the October fold.** The two distinct
  epochs that render as the same fall-back literal become one stored value
  forever. Acceptable precisely because the app could never distinguish them
  before either (both already read back identically through `TIMESTAMP`) — but
  stated here so nobody "discovers" it later.

The no-op claim itself was re-proven empirically during review: winter, summer,
spring-edge, max-epoch and **both** October-fold instants all read back
byte-identical after `ALTER … DATETIME` under the current session; the
counterfactual (pinning `+00:00` *first*) shifted every literal by −2/−3h.
Winter staying `10:00` also re-proves A2: the session conversion is per-instant
named-zone, not a flat +3.

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
| 9 | Display | **Asia/Jerusalem**, one home | ⚠️ home exists (`UiTimezone`), two live drift instances — see below |
| 10 | User input | read as Jerusalem, converted to UTC; **spring gap rejected, autumn fold policy stated** | ⚠️ gap unhandled; fold silently resolves to the later instant |
| 11 | Scheduler | explicit `->timezone()` when a human means local | ⚠️ see 10.4 |

**1 and 4 differ deliberately.** Your Mac's clock is a convenience nothing
depends on. A production server's clock is inherited by cron, syslog and nginx,
so it must be the neutral value. Forge defaulting it to Asia/Jerusalem is a
sensible guess for an Israeli account and the wrong setting for a server.

**The app layer is already correct in both environments.** The database layer is
wrong in both, identically. That is the entire gap.

Row 9's ⚠️, found in review: `AuthorsTable.php:38,43` carry the only two bare
`->dateTime()` calls in the app — rendering **UTC in Filament's default format
today** — and all 21 `DateTimePicker` sites are browser-native, so their
19 `->displayFormat('d/m/Y H:i')` chains are dead code and the visible picker
format is currently the **admin's browser locale**, outside the repo. Both are
per-site drift instances that §8's global hooks close; they are also the proof
that per-site configuration drifts, which is the argument for the hooks.

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
GRANT ALL PRIVILEGES ON `podtext\_test`.* TO 'podtext_test'@'127.0.0.1';
-- paratest, if ever enabled: Laravel derives "{database}_test_{token}", i.e.
-- podtext_test_test_1, and calls Schema::createDatabase — so the parallel
-- grant needs the wildcard AND CREATE:
-- GRANT ALL PRIVILEGES ON `podtext\_test\_test\_%`.* TO 'podtext_test'@'127.0.0.1';
FLUSH PRIVILEGES;
```

(`\_` because an unescaped `_` is itself a single-character wildcard in GRANT
database patterns. The old spec's grant did not cover the paratest-derived
names its own regex anticipated — `TestDatabases.php:200-209` appends
`_test_{token}` to the *already-suffixed* base, which the guard regex matches
but the unescaped grant did not reach.)

No grant on `podtext` is issued to the test user, and the test user lives on a
daemon where `podtext` does not exist at all. Passwords go in `.env` (gitignored)
with placeholders in `.env.example` — **never `.env.testing`**, which
`.gitignore:15-17` does not cover.

---

## 7. The test lane

**Server: a second Herd service, MySQL 8.0.46, port 3307.**

```bash
herd services:create mysql --service-version=8.0.46
# then set its port to 3307 — every Herd MySQL version defaults to 3306
# [MEASURED], and the 9.4.0 app daemon already holds it. The lane's
# "port ≠ app port" guard clause is violated at creation otherwise.
```

(Herd database services are a Herd Pro capability. This machine already runs
two — MySQL 9.4.0 and Redis — so the license exists; recorded because the lane
rides on it.)

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
  two-shape design. The sqlite shape is hard-coded in **four places that must
  move together**: `phpunit.xml:38-40`, `tests/Pest.php:21-33` (the putenv
  loop), `tests/TestCase.php` (config overrides + `forceSafeTestingEnvironment`
  + the guard), and `tests/Feature/EnvironmentGuardsTest.php:24-31`, which
  asserts the retiring shape *by name*. Half-moving them is the failure mode.
- Carried over from the superseded spec so it isn't lost in the rewrite:
  **`tests/Unit` bypasses the guard** — `Pest.php` binds `Tests\TestCase` to
  `Feature`/`Browser` only. Latent while unit tests don't boot the app;
  restate it in the new guard's docblock.
- No `phpunit-mysql.xml`, no second composer script.
- The `driver-lenient-fallback` divergence class stops existing rather than
  being caught by a lane someone might forget to run.
- **Browser tests carry over safely** [verified in vendor]: pest-plugin-browser
  has no artisan-serve child — `LaravelHttpServer` is an in-process Amp socket
  server dispatching Playwright traffic through the same app kernel on the same
  connection, so the per-test transaction stays visible and there is no second
  process to drift from the forced env.

**Schema vehicle — load-bearing, and the old draft got it wrong.** The lane's
schema is not `ALTER`ed; it is rebuilt by `migrate:fresh` replaying the
migration files, which contain 28 `->timestamp(` and 26 `->timestamps(` calls.
Run steps 2-3 as hand SQL against `podtext` and production, and the lane is
recreated with 80 `TIMESTAMP` columns under the old collation **forever**,
regardless of ordering. Therefore **steps 2-3 ship as a Laravel migration**
(generated `MODIFY`s per §9) that every environment runs — production through
the normal deploy, the lane through every `migrate:fresh` replay. Collation
alone would survive via the connection config (the grammar stamps CREATE
TABLEs), but the column type has no config knob; only a migration reaches all
three environments.

**One shared lane schema vs concurrent pest runs.** SQLite `:memory:` made
concurrent runs isolated *by construction*; the lane makes them collide — two
processes would `migrate:fresh` over each other mid-run, and concurrent
sessions on this machine are documented reality (`tests/Pest.php:35-96` exists
precisely because of them — per-pid fake-disk tokens). The lane needs a
process-level run lock (flock on a file under `storage/framework/testing/`,
fail fast with a clear message when held) until/unless paratest with per-process
databases replaces it.

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
pointed at the clock. After step 3 that objection dissolves for stored values:
`DATETIME` columns do not convert, so the session clock is inert for **column
storage** on both sides. (Not for SQL-generated time — `NOW()`/`CURDATE()`/
`DATE()` still read the session clock and only agree everywhere at step 5;
the §9 ordering covers this, and §11's `useCurrent`/`CURRENT_TIMESTAMP` ban is
what keeps SQL-generated time out of the schema meanwhile.)

Two operational teeth, so the ordering rule is a refusal rather than prose:

- The lane's first-use fingerprint also records and asserts
  `information_schema` **TIMESTAMP column count = 0** whenever the connection
  carries `'timezone' => '+00:00'` — a lane built before step 3's migration
  exists refuses to run, instead of silently testing the wrong clock semantics.
- `db:check-settings` runs against the lane too (§11), so lane drift fails the
  same command that checks the other two environments.

If the lane must exist **before** step 3 lands, drop the `timezone` key and
leave it on `SYSTEM` to mirror production honestly — and the fingerprint
assertion above is what forces that choice to be made consciously.

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

Two placement facts the clause table depends on: the guard must keep running in
`refreshApplication()` **before** any migration fires (today's ordering — moved
later, the first `migrate:fresh` drops tables before the guard ever sees them),
and paratest's `switchToDatabase()` rewrites the connection config **after** the
guard has run, so the derived `_test_N` names are structurally unguarded — the
grant is their only barrier.

Honest limit, unchanged from the old spec: after the first run the schema holds
exactly the app's migrations and is indistinguishable from a real copy. The
check answers *"is this a stranger's database?"*, never *"is this a second copy
of mine?"*

**Expected fallout — this is the point, not a side effect.** Budget for SQL
strict-mode rejections, MySQL not rolling back DDL, ordering assumptions
resting on SQLite's rowid, identifier quoting, `||`, `group_concat`,
`lockForUpdate()`. Expect the suite several times slower than `:memory:` — the
last full gate was 1,853 tests, green under full Eloquent strict mode
(`f751455`, 2026-08-08).

**Two unrelated things are both called "strict mode" and will both fire during
the lane's first red run** — mis-attribution is the trap:

- **SQL strict mode** (`'strict' => true` on the connection →
  `STRICT_TRANS_TABLES`): MySQL rejecting truncation, bad values, zero-dates
  that SQLite accepted. *This* is the lane's expected new-failure class.
- **Eloquent strict mode** (`Model::shouldBeStrict()` outside production,
  since `f751455`): already enforced on the current suite. Its
  missing-attribute guard catches column-subset selects reading unselected
  attributes — the five defects that commit fixed were exactly that shape.
  Lane failures from this guard are **signal, not lane noise**: the lane
  changes no select lists, so a new missing-attribute violation means a real
  query defect surfaced by different data flow, not an engine artifact.

Named additions from review:

- **DDL-in-test sites, with addresses**: `TranscriptionsModelTest.php:65`
  (`Schema::dropIfExists`) and `AuthzLegacyRoleBackfillTest.php:247,766-776`
  run DDL inside transaction-wrapped tests; on MySQL each implicit-commits.
  RefreshDatabase self-heals (vendor resets `$migrated` when the PDO is no
  longer in transaction, forcing a re-`migrate:fresh`) — so the cost is extra
  full migrations, not corruption. Verified equally: the transaction-per-test
  cleanup shape itself is engine-identical, and `ShouldQueueAfterCommit` jobs
  fire immediately at transaction level 1 by framework design — no after-commit
  divergence.
- **The 29 test files that do NOT use `RefreshDatabase`** (123 of 152 do): on
  `:memory:` they saw an empty, tableless database; on the persistent lane they
  see the fully migrated schema plus anything a DDL implicit commit leaked — a
  distinct behaviour-change class from the strict-mode/rowid items.
- **The Spatie permission migration's `config('permission.testing')` branch**
  (`2026_07_16_172210…:40`, in-code comment: "a fix for sqlite testing") flips
  for the first time on the lane, inside the `migrate:fresh` replay path.

Two gains: the folded-search shadow columns finally execute on the real engine,
and `LegacyRoleBackfillSchemaContract::inspect()` becomes runnable against a
real MySQL — **as an index/key-shape canary only**: verified in source, its
`normalizeColumn()` folds `timestamp` and `datetime` to one name and contains
zero collation checks, so it is structurally blind to both divergences this
spec fixes. A lane rebuilt with 80 `TIMESTAMP` columns under `unicode_ci`
would pass it. The column-type and collation canary is `db:check-settings`
(§11), not this class.

---

## 8. Display and input, globally

[MEASURED] today: **52 files call `UiTimezone::name()`, 18 call `UiFormats::`.**
Filament has global hooks that make almost all of that unnecessary, and
`AppServiceProvider` already uses `configureUsing` for `Select`, `SelectFilter`,
`Tab`, `Table`, `Action` and `Section` — this is the same mechanism.

| What | Hook | Covers |
| --- | --- | --- |
| Timezone | `FilamentTimezone::set(UiTimezone::name())` | **exactly three vendor readers, enumerated** [verified by exhaustive grep]: tables `CanFormatState.php:480` (gated `isDateTime()` — `since()`/`isoDateTime()` set it too), infolists `CanFormatState.php:472` (same gate), `DateTimePicker.php:731` (gated `hasTime()`; `TimePicker` keeps `hasTime()` true, so covered). "Converts back on save" verified: `DateTimeStateCast::get()` shifts picker-tz → app-tz at dehydration; `set()` is the exact inverse on hydration. |
| Table formats | `Table::configureUsing(…->defaultDateDisplayFormat(UiFormats::date())->defaultDateTimeDisplayFormat(UiFormats::dateTime())->defaultTimeDisplayFormat(UiFormats::time())…)` | verified: `tables/.../CanFormatState.php:84` resolves a bare `->dateTime()` via `$column->getTable()`. Bonus coverage: table `Group.php:322` date labels and the `Range` summarizer read the same Table defaults. **All three formats must be set** — the vendor defaults diverge (`'M j, Y H:i:s'` on Table/Schema, `'M j, Y H:i'` on the picker), so an un-set one is silent drift. |
| Form + infolist formats | `Schema::configureUsing(…)`, same three setters | verified: `infolists/.../CanFormatState.php:84` resolves via the container, which is a `Schema` |
| Picker formats | `DateTimePicker::configureUsing(…)`, same setters **plus `->native(false)`** | pickers hold their **own** copy (`DateTimePicker.php:71-79`); they do not read the Schema's. **And the format only exists on non-native pickers** — see below. |

**Native pickers were silently defeating the day-first rule.** All 21 app
`DateTimePicker` sites are browser-native (`CanBeNative.php` defaults
`$isNative = true`; zero `->native(false)` anywhere), and the native branch
renders a bare `datetime-local` input the **browser** formats by its own
locale — `displayFormat` is only read in the non-native JS branch. So the 19
per-site `->displayFormat('d/m/Y H:i')` chains are dead code today, and
day-first picker display currently rests on the admin's browser locale — a
correctness dependency outside the repo, exactly the class this spec exists to
remove. **Decision: `->native(false)` globally via the same `configureUsing`**,
which makes the format hooks live and the display deterministic. Operator-visible
consequence, stated plainly: admins get Filament's JS picker instead of the
browser-native control.

Deliberate exclusion, documented by Filament and **not to be "fixed"**: the
default timezone does not apply to date-only fields (`DatePicker`, `->date()`),
because applying a zone to a value with no time shifts the date itself.

**Known-uncovered surfaces** (enumerated so nobody adds one expecting
Jerusalem): table `Group` date labels and the `Range` summarizer apply **no**
timezone conversion at all; `->time()` falls back to `config('app.timezone')`
(UTC) because `isTime` does not set `isDateTime`; the tooltip family
(`dateTooltip()` etc.) never sets `isDateTime` either. All currently unused in
app — the guard in §11 is what keeps that true, or makes an adoption
deliberate.

**Outside Filament** — raw Blade — one Carbon macro registered beside the rest:

```php
Carbon::macro('forDisplay', fn (?string $format = null): string => $this
    ->timezone(UiTimezone::name())
    ->translatedFormat($format ?? UiFormats::dateTime()));
```

Still one call per site, but **zero configuration** at the site. Same route for
exports and the dashboard's Jerusalem-day bucketing. Three facts about the
macro, all measured this review rather than assumed: no existing Carbon macro
or mixin exists anywhere in `app/` (no collision); a macro registered on
`Carbon` is callable on `CarbonImmutable` instances too (importer support code
holds those); and `translatedFormat` renders **Hebrew month names with zero
extra setup** — nesbot/carbon's auto-discovered Laravel provider syncs Carbon's
locale to `app.locale` on boot and on `LocaleUpdated` (measured in the booted
app: locale `he`, `15 ינואר 2026`). The multi-language research note claiming
prose formats need an explicit `Carbon::setLocale` is **wrong for this stack**;
nobody should add a redundant one. A date-only variant (or a `$format` argument
of `UiFormats::date()`) covers the presenter sites that show dates without
times. Pin the `CarbonImmutable` reach and the Hebrew rendering with one small
test each — both are exactly the verified-vs-asserted class.

**How the 52 + 18 call sites actually split** [counted in review — the honest
version of "almost all collapse"]:

- **~25 files collapse entirely** — Filament chains the hooks replace: 21
  picker chains, 21 `->dateTime(format, tz)` columns, `->since(tz)`, 2
  `DatePicker` display formats.
- **~17 display sites keep one `forDisplay()` call each** — Blade views,
  card presenters, the transcript viewer, CSV builders, snapshot labels.
- **~9 files legitimately keep `UiTimezone`** because they are *input parsing*
  or *domain logic*, not display: the Spotify importers and lookup, import
  date parsing, `DashboardRange`, `PublicationDateAutofill`,
  `JerusalemDailySeries`, `EditorialMetrics`.

That three-way split is the §11 guard's allowlist: Filament component chains
are banned; Carbon-level input/logic/macro-definition sites are exempt. A naive
content scan would flag the nine legitimate survivors or miss the chains.

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

### The vehicle is a Laravel migration — not hand SQL

Hand SQL reaches `podtext` and production but **not the test lane**, which
rebuilds its schema by replaying the migration files (`migrate:fresh`) — it
would be recreated with 80 `TIMESTAMP` columns under the old collation on every
run, forever (§7). One migration, run everywhere through the normal mechanism,
is the only vehicle that reaches all three environments.

### ALTER mechanics, all measured

- **Collation converts with `CONVERT TO`**, not the default-only form:
  `ALTER TABLE t COLLATE=utf8mb4_0900_ai_ci` changes **only the table default**
  and leaves all 183 columns on `unicode_ci` — a footgun that passes a naive
  eyeball. The converting form is
  `ALTER TABLE t CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci`.
  Charset stays utf8mb4 → utf8mb4, so there is no TEXT-type-promotion hazard.
- **A bare `MODIFY col DATETIME` silently drops `NOT NULL`** [measured] — and
  3 of the 80 columns carry it (`failed_jobs.failed_at`,
  `form_verification_codes.expires_at`, `public_form_submissions.submitted_at`).
  Every `MODIFY` must therefore be **generated from `information_schema`**
  (type + `IS_NULLABLE` + `COLUMN_DEFAULT`), in this spec's own
  generate-don't-hand-list spirit.
- **Precision: nothing to preserve.** Zero migrations pass a precision
  argument, the framework default is 0, and all 80 columns measure
  `DATETIME_PRECISION 0` — plain `DATETIME` is the right target.
- **The pass must run to completion before app traffic resumes.** A
  half-converted schema can raise `1267 Illegal mix of collations` on JOINs
  between a converted and an unconverted column. Production sits behind
  maintenance mode; local has no such window, so: run it, don't browse
  mid-pass.
- **Zero model or cast changes ride along.** Eloquent is column-type-blind:
  the write/read format is the connection grammar's `'Y-m-d H:i:s'`
  (`Grammar.php:282`), no code path consults the column's SQL type, and the 23
  `datetime` casts across 10 models need nothing. Stated so no implementer
  "helpfully" touches casts inside the ALTER PR — §12 makes it a non-goal.

### Rehearsal before reality (operator rule, 2026-08-08)

**The migration runs against no real database until it has passed on a
rehearsal database.** Standing rule for this project: destructive or
type-changing passes get rehearsed on a disposable copy first.

- **Two rehearsal databases, same script**: `podtext_rehearsal` on the local
  9.4.0 daemon (rehearses the local run) and on the 8.0.46 lane daemon
  (rehearses production's engine). Neither is dropped without explicit
  operator approval.
- **Contents**: a restore of the real snapshot (step 0's tooling doubles as
  the rehearsal input — the rehearsal is also the snapshot's restore test),
  **plus ~100 synthetic edge rows** spread across every date/time column
  shape the schema has (all 80 TIMESTAMP columns are reachable through their
  tables; include the 3 NOT NULL columns and `failed_jobs.failed_at`'s
  default):
  - winter literal (`+02` frame) and summer literal (`+03` frame)
  - spring-forward boundary pair (`01:59` valid / `03:00` first-valid)
  - both October-fold instants (two epochs rendering the same `01:30` wall)
  - epoch edges: `1970-01-01 02:00:01` wall (TIMESTAMP minimum under +02) and
    `2038-01-19` max-epoch boundary
  - `NULL` in every nullable date column
  - collation payloads in collated columns riding the same rows: niqqud,
    final-form, U+05C7, trailing-space, emoji pairs — so the CONVERT TO pass
    is rehearsed on hostile strings, not just the ALTER on hostile dates
- **Oracle, mechanical**: before the run, dump per-row
  `SHA1(CONCAT_WS(...))` over every date column and every collated unique
  column, plus `information_schema` COLUMN_TYPE / IS_NULLABLE /
  COLUMN_DEFAULT / COLLATION_NAME. After the run, compare: every date literal
  byte-identical; NOT NULL and defaults preserved (except the one default
  deliberately dropped); every collated column `0900_ai_ci`; zero 1062, zero
  1267; wall-clock runtime recorded as the production window estimate.
- **Pass criterion**: the migration, the pre-flight scripts, and
  `db:check-settings` all green on both rehearsal databases — then and only
  then does the migration touch `podtext`, and only after that, production.

### Prerequisite: production is one migration behind

[MEASURED 2026-08-08] local has **82** migrations, production **81**; production's
latest is `2026_08_04_185049_add_provider_to_imports_table`. The missing one is
`2026_08_06_223132_add_hebrew_search_folding_columns`.

That migration carries a **deploy-ordering hazard already recorded in
`current-project-state.md`**: it adds the shadow columns NULL while every search
predicate already compares against a shadow, so between `migrate` finishing and
`search:backfill-folds` finishing, *every pre-existing row is invisible to every
search* — public and admin alike.

```bash
php artisan migrate --force && php artisan search:backfill-folds
```

**Land that first, as its own deploy, and confirm search works before starting
step 2.** Bundling it with the collation and column-type window would mean that
if search misbehaves afterwards, nobody can tell which change caused it — the
same reasoning that put the search fix ahead of the migration in the original
plan's §6.

Note the shadow columns are indexed **non-uniquely**, so they fall outside the
B3/B5 scans by construction, not by omission — those scans cover
unique/primary indexes, which is where a collation change can raise `1062`.

---

## 10. Carried caveats and known gaps

### 10.1 `failed_jobs.failed_at` — a dormant hazard, not corrupted data

The one `DEFAULT CURRENT_TIMESTAMP` column in the schema. **The first draft's
diagnosis was wrong and is corrected here** [verified in vendor during review]:
`config/queue.php:124` sets the failed driver to `database-uuids`, and
`DatabaseUuidFailedJobProvider.php:63` writes `'failed_at' => Date::now()`
explicitly on every log — framework-logged failures **never exercise the
default** on this Laravel version. The existing rows are app-written UTC whose
round trip cancels like every other app value; there is no 3-hour error to
preserve. (The 5 local rows were checked; if production rows predate this
Laravel version or were inserted by hand, re-verify their frame there before
claiming otherwise.)

The default **is** still a dormant DB-generated-time hazard — any insert that
omits the column gets MySQL's clock. Fix unchanged, now proven safe: drop the
default (the provider always supplies the value) and let the migration say so.

### 10.2 `$table->timestamps()` will re-introduce `TIMESTAMP`

[VERIFIED in vendor] `Blueprint.php:1293` — `timestamps()` is literally two
`timestamp()` calls, and `MySqlGrammar::typeTimestamp()` emits `TIMESTAMP`.
So every future migration drifts back by default.

**Prose cannot hold this. A statement-scan guard must**, in the shape this repo
already uses for enum literals and format literals. Coverage findings from
review, both of which a naive ban would miss:

- **`softDeletes()` and `softDeletesTz()` also emit `TIMESTAMP`**
  (`Blueprint.php:1362/1374`) with no `timestamp` literal at the call site.
  Ban them too; `softDeletesDatetime()` (`Blueprint.php:1386`) is the
  compliant form to name in the guard message.
- **The type ban alone does not kill DB-generated time**: `typeDateTime()`
  honours `useCurrent()`/`useCurrentOnUpdate()` as well, so
  `DATETIME DEFAULT CURRENT_TIMESTAMP` is reachable — §11's separate
  `useCurrent`/`CURRENT_TIMESTAMP` ban is load-bearing, not redundant.
- **Per-model date-format escape hatches** change both the stored literal and
  read parsing: `protected $dateFormat`, `#[DateFormat]`, and
  `#[Table(dateFormat:)]` all resolve into the same slot
  (`HasAttributes.php:213-215`). Zero usages today; add all three spellings to
  the anti-drift scan so it stays that way.

### 10.3 The DST input edges — the spring gap and the autumn fold

`FilamentTimezone::set()` makes pickers read and write Jerusalem correctly, but
an admin picking **02:30 on 27 March 2026** selects a time that does not exist,
and Carbon silently moves it to 03:30 with no error [re-measured in review; the
transition is real: IST→IDT at 02:00 local]. No configuration prevents this.
It needs an explicit validation rule, applied globally through
`DateTimePicker::configureUsing()`.

The rule is feasible **and not defeated by pre-conversion** [verified in
vendor]: validation runs against raw Livewire state — the Jerusalem wall-clock
string — *before* `dehydrateState()` performs the Jerusalem→UTC conversion
(`HasState.php:453` vs `:489`). Design constraints, so the implementer doesn't
rediscover them: gate on `$component->hasTime()` and read
`$component->getTimezone()` (not `UiTimezone` directly) so per-site overrides
and the DatePicker exclusion stay consistent; detection is round-trip — parse
the raw string in the picker's zone, re-format, compare — because PHP has no
dedicated gap API and the normalize-forward mismatch *is* the detector.

**The autumn fold now has a stated policy.** 01:00–01:59 on fall-back night
occurs twice; PHP resolves the ambiguous string to the **second** (post-fold,
+02:00) occurrence, silently [measured]. Policy: **accept-later** — the PHP
default stands, documented rather than discovered. Rejecting the fold hour
would refuse a time that genuinely exists twice, and the one-hour ambiguity
costs at most an hour of ordering precision once a year on manually-typed
input; `now()`-driven values are unaffected.

Config handles the 363 ordinary days; one rule handles the gap day, one stated
policy handles the fold day.

### 10.4 The scheduler already disagrees with its author — twice now

`routes/console.php:13` — `Schedule::command('media:prune-quarantine --apply')
->dailyAt('03:30')` with no `->timezone()`. Laravel evaluates against
`config('app.timezone')`, so it runs at **03:30 UTC = 06:30 Israel**. And the
pattern is reproducing: `aef8c81` (2026-08-08) added
`Schedule::command('model:prune')->dailyAt('03:50')`, also unpinned — written
"next to the existing quarantine prune", which is exactly how an undecided
default propagates. Two tasks now; the fix is one decision applied to both
(explicit `->timezone(UiTimezone::name())` if the author means Israel night,
or a comment saying UTC is meant), plus a guard-shape candidate: a test that
every schedule entry states its timezone intent.

[VERIFIED] this also confirms step 4 is safe: the scheduler already evaluates in
UTC and Forge's cron fires every minute, so changing the server's OS timezone
moves no scheduled job.

### 10.5 Surviving blockers from the old plan

Six of B1-B9 existed only because that plan dumped and restored into a new
schema. In-place `ALTER` has no dump (B1, B2, B6), no cutover (B7), raises a
real `1062` instead of silently corrupting an index under `UNIQUE_CHECKS=0`
(B4), and preserves literals instead of re-rendering epochs under a different
zone (B9).

**Three survived. All three were run against production on 2026-08-08, and all
three came back clean:**

```
unique/primary indexes with collated columns:  30      (generated, not listed)
B3 duplicate risk under 0900_ai_ci:            NONE
B5 trailing-space rows (length-based):         NONE
```

- **B3 — the duplicate scan.** `0900_ai_ci` merges values `unicode_ci` keeps
  distinct (`'א' = 'א'+U+05C7` goes 0 → 1), so two legal rows can become a
  duplicate key. Run against **production**, because a local clearance would be
  close to vacuous — 821 rows of near-ASCII identifiers against production's
  3,544 [MEASURED]. Method: for each unique/primary index, `GROUP BY` its
  columns with `COLLATE utf8mb4_0900_ai_ci` applied to the collated ones,
  excluding rows where any indexed column is NULL (MySQL permits multiple NULLs
  in a unique index, which would otherwise report a false positive — a live
  clause, not theory: `curator.reference_key` is a nullable collated
  unique-indexed column).
  **Result: zero colliding groups. The collation change is safe on today's
  production data.** Re-run immediately before the window, not once.
  **The scan must also assert its own precondition** [found in review]: it is
  sufficient only while the schema has zero prefix (`SUB_PART`) and zero
  functional (`EXPRESSION`) unique indexes — a prefix-unique index would make
  full-column `GROUP BY` silently under-detect. Both measure 0 today; one
  `information_schema.STATISTICS` query inside the scan turns the assumption
  into a refusal.
- **B5 — trailing space.** The old check `col <> TRIM(TRAILING ' ' FROM col)` is
  a tautology on a PAD SPACE collation. Length-based:
  `LENGTH(col) <> LENGTH(TRIM(TRAILING CHAR(32) FROM col))`. **Result: zero
  rows.** Note this blocker's direction is the opposite of B3's: PAD SPACE →
  NO PAD makes previously-equal values *distinct*, which can never raise 1062.
  It is a behaviour pre-flight, not a migration blocker — a stored `'abc '`
  would silently stop matching a lookup for `'abc'`.
- **B8 — generate the index inventory from `information_schema`.** The old plan
  said 5, then 22, then 39. **The real figure is 30, identical on local and
  production** [MEASURED both sides]. Never hand-list — and note this is the
  third different number that document produced, which is the argument for
  generating it rather than the argument for any one figure.

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
| Ban `$table->timestamp(` / `timestamps()` / **`softDeletes()` / `softDeletesTz()`** in new migrations; name `softDeletesDatetime()` as the compliant form in the failure message | 10.2 — the silent drift back, including the two spellings with no `timestamp` literal at the call site |
| Ban `useCurrent()` / `useCurrentOnUpdate()` / `CURRENT_TIMESTAMP` in migrations | the entire DB-generated-time class — reachable through `DATETIME` too, so this is not redundant with the type ban |
| Ban `protected $dateFormat`, `#[DateFormat]`, `#[Table(dateFormat:)]` in models | per-model date-format escape hatches that change the stored literal (zero today; keep it so) |
| Extend `db:check-settings` to assert collation (name **and** `PAD_ATTRIBUTE`), column type, and non-zero exit; run it against **all three** servers, lane included | drift on any server; also the 8.0.46-vs-9.4 `0900_ai_ci` identity, closed cheaply by re-running the §3 matrix once on the lane |
| Extend the existing `Asia/Jerusalem` anti-drift test to ban now-redundant `->timezone()`/`UiFormats::`/`->displayFormat()` at Filament component chains, with §8's three-way allowlist (input-parsing and domain-logic `UiTimezone` sites are exempt) | §8 regressing back to per-site config — without flagging the nine legitimate survivors |
| One-line Pest tests: `forDisplay` on a `CarbonImmutable` instance; `forDisplay` rendering Hebrew month names | §8's two verified-not-asserted macro claims staying true across carbon upgrades |
| The lane guard's own refusal test, one case per clause in §7 | a guard nobody tests is prose wearing a guard's clothes |
| `LegacyRoleBackfillSchemaContract::inspect()` vs `expected('mysql')` on the real lane — **index/key shape only** | the lane's index/FK shape silently diverging; it is structurally blind to column type and collation (§7), which `db:check-settings` owns |

---

## 12. Non-goals

Stated explicitly because *"make all three environments the same"* is exactly
the frame under which someone helpfully makes them the same in a way they must
not be.

- **The folded-search shadow indexes stay asymmetric.** The folding migration
  indexes every varchar shadow (`title_search`, `name_search`, …) and
  deliberately skips the LONGTEXT description/transcript shadows, because MySQL
  cannot index a LONGTEXT without a prefix length and expressing one would mean
  per-driver schema branching. **Do not add indexes to the LONGTEXT shadows for
  symmetry.** Verified independently on 9.4.0: under the shipped query shape
  (`FoldedSearch::pattern()` wraps both sides, so `LIKE '%term%'`) a B-tree
  gives no range access at all — `type=index`, a full index scan — so a
  symmetric index would buy nothing and cost inserts and disk.
  See `docs/research/laraveldaily/database-design-notes.md` §1b/§1c.
- **Not a schema redesign.** Column types change from `TIMESTAMP` to `DATETIME`
  and nothing else. No new indexes, no dropped ones, no renames.
- **Not a search change.** `HebrewSearchFold`'s shadow-column design is settled
  and unaffected; the collation governs `=`, unique indexes and sorting.
- **Not a cast refactor.** The 23 `datetime` casts stay exactly as they are —
  no `immutable_datetime` migration, no `Date::use(CarbonImmutable)` binding.
  Switching to immutable casts changes return types app-wide (in-place mutation
  call sites break silently) and is orthogonal to the storage change; it must
  not ride the ALTER window. `CarbonImmutable` already appears ad hoc in import
  support code, so a future immutable move is plausible — as its own decision.
- **JSON columns are out of scope, on purpose.** 16 JSON columns exist across
  10 migrations; `information_schema` reports no collation for them (they sit
  outside the 183-column count and outside the ALTER pass), and JSON scalar
  comparison is `utf8mb4_bin` — accent- and case-**sensitive** — regardless of
  this spec. Do not "make JSON match", and do not assume `0900_ai_ci` semantics
  reach JSON-extracted Hebrew.

## 13. Open

- **The snapshot's shape (step 0)** — dump files with history, a server-side
  schema copy, or both. Undecided; it gates steps 2 and 3.
- **Production 8.0 → 8.4 LTS.** Noted, deliberately unscoped. Ubuntu's package
  keeps receiving backported security fixes for the 24.04 window, so there is no
  urgency — but Forge cannot do it in place, and the daemon is shared by three
  sites.
- ~~Target #10 verification~~ — **closed in review.** The picker round-trip is
  correct by construction: `DateTimeStateCast::set()` (app-tz parse →
  picker-tz on hydration) and `::get()` (picker-tz → app-tz on dehydration)
  are exact inverses outside the DST edges, which §10.3 handles. One round-trip
  test in the implementation pins it.
- **No CI story.** The suite becomes MySQL-only with no CI config in the repo;
  a future pipeline needs a `mysql:8.0.46` service container, or an explicit
  "local-only suite" decision. Recorded, not designed here.
- **The `testing-advanced` course (31 lessons, Sep 2024)** is the one archive
  item plausibly covering test-database configuration in depth, and it is
  catalogue-only — never scraped. Optional fetch before building the lane;
  apply the full staleness protocol if fetched.
