# MySQL test lane — spec

**Status:** design, approved in principle (operator chose Herd, 2026-08-06).
Not implemented. Nothing in this document has been applied to the repo.

**Why it exists:** `driver-lenient-fallback` in
`docs/research/defect-cause-patterns.md`. The suite runs on SQLite, production
runs on MySQL, and where they disagree SQLite is almost always the permissive
one — so divergence shows up as a **green suite**, never as a failure. A
passing gate currently is not evidence that a query runs.

---

## 1. What is being protected

`.env`: `DB_CONNECTION=mysql`, `DB_HOST=127.0.0.1`, `DB_PORT=3306`,
**`DB_DATABASE=podtext`**, `DB_USERNAME=root`. Herd's MySQL is **9.4.0**.

The threat is not hypothetical. `RefreshDatabase` runs `migrate:fresh`
(`vendor/.../Testing/RefreshDatabase.php:117`), once per process, against
whatever `database.default` resolves to. `migrate:fresh` **drops every table in
that schema**. 119 of 136 files under `tests/Feature` and `tests/Browser` call
`uses(RefreshDatabase::class)`. One mis-pointed run empties `podtext`.

That is why the current guard is absolute, and why the answer is to teach it a
**second shape** rather than to relax it.

---

## 2. The barriers, and what each one survives

This is the important section, and it answers the obvious question — *is this
just documentation and trust?* No. There are four layers, and only the last two
survive a mistake in the layers above them.

| # | Barrier | Kind | Survives |
| --- | --- | --- | --- |
| 1 | This document | prose | nothing. It explains; it does not enforce. |
| 2 | `phpunit-mysql.xml` forcing the lane | config | a wrong shell env (`force="true"` beats it) |
| 3 | `assertSafeTestingDatabase()` two-shape guard | code, in-process | a wrong `.env`, a typo'd schema name, a remote host, a root login, a DSN, an unknown lane value — **fails closed on each** |
| 4 | **MySQL GRANT scoped to `podtext_test` only** | server-side | *everything above it.* A bug in the guard, a hand-typed `artisan migrate:fresh`, a future refactor of `TestCase`, a mid-test config mutation. MySQL answers 1044/1142 instead of dropping the schema. |

**Layer 4 is the real barrier.** Layers 2 and 3 are there so that mistakes are
loud and early instead of silent; layer 4 is there so that a mistake in 2 or 3
still cannot destroy anything. The guard must therefore also **refuse `root`
and refuse a username equal to the app's**, because a copy-pasted
`DB_USERNAME=root` would silently delete layer 4 while leaving layers 1–3
looking healthy.

A fifth layer exists and is not being taken today: running the test MySQL in a
**separate server** where `podtext` does not exist at all (§7).

---

## 3. The second shape — a connection that shares no env key with the app

```php
// config/database.php — deliberately does NOT read DB_DATABASE or DB_URL
'mysql_testing' => [
    'driver' => 'mysql',
    'host' => env('DB_TESTING_HOST', '127.0.0.1'),
    'port' => env('DB_TESTING_PORT', '3306'),
    'database' => env('DB_TESTING_DATABASE'),   // no default — missing must fail closed
    'username' => env('DB_TESTING_USERNAME'),
    'password' => env('DB_TESTING_PASSWORD', ''),
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',        // pinned on purpose — see §6
    'prefix' => '', 'prefix_indexes' => true, 'strict' => true, 'engine' => null,
    // no 'url' key at all — a DSN silently overrides host and database
],
```

**Not sharing `DB_DATABASE` is the single most important structural decision.**
No typo in one variable can alias the real schema, because the real schema's
name lives in a variable this connection never reads.

### The grant (run once, by the operator)

```sql
CREATE DATABASE IF NOT EXISTS `podtext_test`
  DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE USER IF NOT EXISTS 'podtext_test'@'127.0.0.1' IDENTIFIED BY '<password>';

GRANT ALL PRIVILEGES ON `podtext_test`.* TO 'podtext_test'@'127.0.0.1';

-- only if --parallel is ever enabled; \_ escapes LIKE's single-char wildcard
GRANT ALL PRIVILEGES ON `podtext\_test\_%`.* TO 'podtext_test'@'127.0.0.1';

FLUSH PRIVILEGES;
```

No grant on `podtext` is issued. That is the whole point.

---

## 4. The guard

`assertSafeTestingDatabase()` accepts **exactly two shapes** and throws on
everything else. Lane selection reads `$_SERVER['DB_TESTING_LANE']`; absent or
misspelled resolves toward sqlite or toward refusal, **never toward MySQL**.

Shape A — sqlite: `database.default === 'sqlite'` and
`sqlite.database === ':memory:'`. Unchanged from today.

Shape B — mysql, every clause required:

| Clause | Refusal reason |
| --- | --- |
| `database.default === 'mysql_testing'` | the app's `mysql` connection is never an accepted test connection |
| driver is `mysql` | |
| no `url`/DSN key | a DSN overrides host and database at connect time and makes every check below meaningless |
| name matches `/^[a-z][a-z0-9_]*_test(_[0-9]+)?$/` | optional suffix is paratest's token |
| name ≠ any `DB_DATABASE` read **from the raw `.env` files**, not `env()` | a forced phpunit var could otherwise mask the real name and defeat the comparison |
| name ≠ any other configured connection's database | |
| host ∈ `127.0.0.1`, `localhost`, `::1` | a remote host is never a test target |
| username not empty, **not `root`**, ≠ the app's `DB_USERNAME` | protects layer 4 from being silently removed |
| schema is disposable (below) | |

### Disposable-schema check

First use of a schema must find it **empty**, recorded afterwards by a local
fingerprint file keyed on `sha1(host|port|database)` under
`storage/framework/testing/mysql-lane/`. Re-point at a populated unknown schema
and there is no fingerprint → refused. Delete the fingerprint → next run
demands an empty schema again.

Honest limit: after the first run the schema holds exactly the app's migrations
and is **indistinguishable from a real copy of the app database**. The check
answers *"is this a stranger's database?"*, never *"is this a second copy of
mine?"*

A sentinel *table* was the alternative and is worse: `migrate:fresh` drops it,
and re-creating it in `afterRefreshingDatabase()` issues DDL after
`beginDatabaseTransaction()`, implicitly committing and silently breaking
per-test isolation.

### Free hardening

In `refreshApplication()`, after boot, point every non-lane server connection at
a name that cannot exist:

```php
config([
    'database.connections.mysql.database' => 'unreachable_from_tests',
    'database.connections.mariadb.database' => 'unreachable_from_tests',
]);
```

so even an explicit `DB::connection('mysql')` inside a test cannot reach
`podtext`.

### The guard needs its own test

A guard nobody tests is layer 1 wearing layer 3's clothes. Add a Pest test that
feeds `assertSafeTestingDatabase()` a **stubbed config** — no connection — for
each shape it must reject: app connection, DSN present, bad name, name equal to
`podtext`, remote host, `root`, app username, unknown lane. One case per
refusal, asserting the message. This is the difference between a guard and a
comment.

---

## 5. Opting in — `phpunit.xml`'s `force="true"` stays

`force="true"` is **load-bearing**: it is exactly what stops a stray
`DB_CONNECTION=mysql` in the shell from reaching a run. Do not remove it.

- ❌ `.env.testing` — the forced vars beat it, and `.env.testing` is **not in
  `.gitignore`** (`.gitignore:15-17` lists only `.env`, `.env.backup`,
  `.env.production`), so configuring the lane that way commits credentials.
- ✅ a committed **`phpunit-mysql.xml`**, identical to `phpunit.xml` except it
  forces `DB_TESTING_LANE=mysql` and `DB_CONNECTION=mysql_testing`, and does
  **not** force `DB_DATABASE`.

```bash
composer test:mysql
```

mirroring the existing `composer test` (`config:clear` then `artisan test
--configuration=phpunit-mysql.xml`). Both lanes are reviewable diffs, and
neither can half-apply.

`tests/TestCase.php:29-44` and `tests/Pest.php:21-33` both force the env matrix
and both must become lane-aware.

---

## 6. The version and collation trap

Local Herd MySQL is **9.4.0**. Production is almost certainly 8.x on Forge.
This matters more than it looks:

- MySQL 9's server default collation is `utf8mb4_0900_ai_ci`. The `ai` is
  **accent-insensitive** — which is precisely the property that folds Hebrew
  niqqud. `utf8mb4_unicode_ci` folds differently.
- So a lane that inherits the server default would test Hebrew comparison
  behaviour that **production does not have**.

**Testing on the wrong MySQL version or collation is a new instance of
`driver-lenient-fallback`, not a cure for it.** Two consequences, both
mandatory:

1. The connection pins `charset`/`collation` explicitly (§3) rather than
   inheriting the server default.
2. **Before building the lane, confirm production's actual version, charset and
   collation.** If they differ from Herd's in any way that touches collation or
   SQL modes, the Docker option in §7 stops being an upgrade and becomes the
   requirement.

---

## 7. Docker — what it would and would not change

To be explicit, since the question came up: Docker would be for the **test
database only**. The application keeps using Herd's MySQL exactly as it does
today. Nothing about local development changes.

| | Herd MySQL 9.4.0 (chosen) | Docker MySQL on 3307, pinned to production's version |
| --- | --- | --- |
| Setup | zero + one `GRANT` | compose file, a documented start step, ~300 MB RAM |
| Isolation | policy: name rules + grant. `podtext` lives in the same daemon | **physical** — `podtext` does not exist in that server |
| Fidelity | whatever Herd ships (9.4.0) | exactly production's version and collation |
| Blast radius | same-daemon | none |

**Decision: Herd, with the grant.** Revisit the moment §6's version check comes
back divergent — at that point Docker is not a safety upgrade, it is the only
way the lane tells the truth.

---

## 8. Residual risk, stated plainly

1. **The guard only covers the test suite.** A hand-typed `migrate:fresh`,
   `db:wipe` or a seeder still hits `podtext`, and the app user is `root`.
   Largest remaining hole; the lane does nothing about it.
2. **`tests/Unit` bypasses the guard** — `tests/Pest.php:109-111` binds
   `Tests\TestCase` to `Feature`/`Browser` only. Latent today because those
   tests do not boot the app.
3. **Mid-test reconfiguration is unguarded.** The guard runs at
   `refreshApplication()`; anything mutating the connection afterwards is
   invisible to it. Only the grant stops that.
4. **The content check cannot spot a second copy of this app's database.**
5. **Same-daemon blast radius persists on Herd.** Only §7 removes that class.
6. **`--parallel` moves the goalposts.** `switchToDatabase()`
   (`TestDatabases.php:172-191`) rewrites config *after* the guard has run, and
   the lane would need `CREATE`/`DROP DATABASE` on `podtext\_test\_%`.
   `brianium/paratest` is not installed; leave parallel off initially.
7. **`.env.testing` is not gitignored.**

---

## 9. Cost

Roughly one working hour of plumbing:

- ~35 lines in `config/database.php`
- rewrite the guard in `tests/TestCase.php` (~150 lines), make
  `forceSafeTestingEnvironment()` lane-aware
- ~10 lines in `tests/Pest.php`
- `phpunit-mysql.xml` (~50 lines, mostly a copy)
- 4 `DB_TESTING_*` lines in `.env`, placeholders in `.env.example`
- one `composer test:mysql` script
- the guard's own refusal test
- run the `GRANT` block once, create `podtext_test` empty

**The real cost is the first red run, and that is the point of the exercise.**
Budget for: `strict` mode rejections, MySQL not rolling back DDL (a genuinely
new class of leakage between tests — not a safety issue, but a real failure
mode), ordering assumptions that only hold on SQLite's rowid, and the known
divergences — identifier quoting, niqqud collation folding, `||`,
`group_concat` separator, `lockForUpdate()`. Expect the suite several times
slower than `:memory:`: one `migrate:fresh` over the full migration set per
process, plus a real round trip per query.

**Rejected outright:** relaxing the guard to "any connection when
`APP_ENV=testing`", and removing `force="true"`. Both trade a hard barrier for
a convention.

**Recommended supplement, not substitute:** MySQL in CI as well, so the
divergence class is caught on push even when nobody runs the local lane.
