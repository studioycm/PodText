# Database Alignment Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Align local, test and production databases on one engine family, `utf8mb4_0900_ai_ci`, `DATETIME` columns and a UTC clock — then make Filament/Blade display and input Jerusalem-local globally, with guards so none of it regresses.

**Architecture:** One driver-guarded Laravel migration (generated from `information_schema`) converts collation + column types everywhere including future `migrate:fresh` replays; rehearsal databases prove it before any real run; a dedicated 8.0.46 Herd daemon replaces SQLite as the only test lane; `configureUsing` hooks + one Carbon macro replace ~70 per-site timezone/format calls; statement-scan tests hold every decision.

**Tech Stack:** Laravel 13.24 · MySQL 9.4.0 (local app) / 8.0.46 (lane + production) · Pest 4 · Filament 5.7 · Herd services · Forge (deploys are manual — auto-deploy is OFF).

**Spec:** `docs/phase-02/database-alignment-spec.md` (authoritative; adversarially reviewed 2026-08-08). Phase 0 (snapshot tooling, production catch-up) is **complete** — `db:snapshot`/`db:restore` exist and are proven.

## Global Constraints

- Full gate per task group: `php -d memory_limit=2G vendor/bin/pest --compact` (baseline: 1,865 passing), `vendor/bin/pint --dirty --format agent`, `composer filacheck` (NEVER `vendor/bin/filacheck` — it force-fixes under agents), `php -d memory_limit=2G vendor/bin/phpstan analyse` on touched files.
- Commit per task to local `main`. **Push and deploy only at the marked 🛑 STOP gates, with operator approval** — auto-deploy is OFF; production deploys are explicit `forge_deploy_site` calls.
- Production windows use native `php artisan down --secret="<label>"` (artisan/Horizon/scheduler keep running; verified in Phase 0).
- `podtext_restore_check` (local 3306) is the designated 9.4.0 rehearsal seed. Rehearsal DBs are dropped **only on operator approval**.
- Console commands use `#[Signature]`/`#[Description]` attributes (house style since `4c9605a`).
- Migrations: never `$table->timestamp(`/`timestamps()`/`softDeletes()` from here on — this plan installs the guard that bans them; use `dateTime()`/`datetimes()`/`softDeletesDatetime()`.
- All new user-facing strings via translation keys in BOTH `lang/en` and `lang/he` (cross-cutting rule).
- The suite runs SQLite until Task 19 flips the lane. Every migration/command added before that must driver-guard (`if driver !== 'mysql' return/refuse`).

---

## Phase 1 — dedicated local user

### Task 1: Local `podtext` MySQL user; `.env` off `root`

**Files:**
- Modify: `.env` (local only, gitignored — `DB_USERNAME`, `DB_PASSWORD`)
- No code changes.

**Interfaces:**
- Produces: local app connection running as `podtext`@`127.0.0.1` with grants on `podtext`.* and `podtext_restore_check`.* only (the rehearsal seed must stay reachable for Task 8).

- [ ] **Step 1: Create user and grants** (operator supplies a password; never write it to any tracked file)

```bash
php artisan tinker --execute '
DB::statement("CREATE USER IF NOT EXISTS \"podtext\"@\"127.0.0.1\" IDENTIFIED BY \"CHOSEN_PASSWORD\"");
DB::statement("GRANT ALL PRIVILEGES ON `podtext`.* TO \"podtext\"@\"127.0.0.1\"");
DB::statement("GRANT ALL PRIVILEGES ON `podtext_restore_check`.* TO \"podtext\"@\"127.0.0.1\"");
DB::statement("FLUSH PRIVILEGES");
echo "user created";'
```

- [ ] **Step 2: Switch `.env`** — `DB_USERNAME=podtext`, `DB_PASSWORD=<the password>`; run `php artisan config:clear`.

- [ ] **Step 3: Verify as the new user**

Run: `php artisan db:check-settings && php artisan tinker --execute 'echo DB::selectOne("SELECT CURRENT_USER() u")->u;'`
Expected: settings output unchanged (exit 1 on the two known findings is correct); `podtext@127.0.0.1`.

- [ ] **Step 4: Verify root is out**

Run: `grep -c 'DB_USERNAME=root' .env`
Expected: `0`.

No commit (only gitignored files changed). Update `.env.example` only if its placeholder still says `root` — measured 2026-08-08: it already says `podtext`.

---

## Phase 2 — the alignment migration, rehearsed then real

### Task 2: 8.0.46 Herd service on port 3307

**Files:** none (Herd runbook).

**Interfaces:**
- Produces: MySQL 8.0.46 daemon at `127.0.0.1:3307`, root-accessible locally, hosting `podtext_rehearsal` (Task 8) and later `podtext_test` (Task 17).

- [ ] **Step 1:** `herd services:create mysql --service-version=8.0.46` — when Herd prompts for a port (default 3306 is taken), set **3307**. If created on the wrong port, fix via Herd's services UI before proceeding.
- [ ] **Step 2: Verify**

Run: `mysql -h 127.0.0.1 -P 3307 -u root -e "SELECT @@version, @@port"`
Expected: `8.0.46` / `3307`.

### Task 3: Extend `db:check-settings` — column type + PAD_ATTRIBUTE

**Files:**
- Modify: `app/Console/Commands/CheckDatabaseSettings.php`
- Test: `tests/Feature/CheckDatabaseSettingsTest.php` (create)

**Interfaces:**
- Produces: `db:check-settings` additionally reports/fails on (a) any `timestamp`-typed column, (b) collation whose `PAD_ATTRIBUTE` ≠ `NO PAD` once the target collation is expected. Pure helper `CheckDatabaseSettings::columnTypeProblems(array $typeCounts): array` for SQLite-side testing.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Console\Commands\CheckDatabaseSettings;

it('warns and exits cleanly on the sqlite suite driver', function (): void {
    $this->artisan('db:check-settings')
        ->expectsOutputToContain('Not a MySQL connection')
        ->assertSuccessful();
});

it('flags timestamp columns as alignment drift', function (): void {
    expect(CheckDatabaseSettings::columnTypeProblems(['timestamp' => 3, 'datetime' => 77]))
        ->toHaveCount(1)
        ->and(CheckDatabaseSettings::columnTypeProblems(['timestamp' => 3, 'datetime' => 77])[0])
        ->toContain('3 column(s) are still TIMESTAMP');
    expect(CheckDatabaseSettings::columnTypeProblems(['datetime' => 80]))->toBeEmpty();
});
```

- [ ] **Step 2:** Run: `php artisan test --compact --filter=CheckDatabaseSettings` — expect FAIL (`columnTypeProblems` undefined).
- [ ] **Step 3: Implement.** In `CheckDatabaseSettings`, after the collation loops add:

```php
$types = collect(DB::select("SELECT DATA_TYPE dt, COUNT(*) n FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND DATA_TYPE IN ('timestamp','datetime','date','time') GROUP BY 1"))
    ->mapWithKeys(fn (object $row): array => [$row->dt => (int) $row->n])->all();

foreach ($types as $type => $count) {
    $this->line(str_pad('column types', 17)."{$type}  ×{$count}");
}

array_push($problems, ...self::columnTypeProblems($types));

$pad = DB::selectOne('SELECT PAD_ATTRIBUTE p FROM information_schema.COLLATIONS WHERE COLLATION_NAME = ?',
    [config('database.connections.mysql.collation')]);

if ($pad !== null && $pad->p !== 'NO PAD') {
    $problems[] = "The configured collation pads ({$pad->p}); the alignment target utf8mb4_0900_ai_ci is NO PAD.";
}
```

and the pure helper:

```php
/**
 * @param  array<string, int>  $typeCounts
 * @return list<string>
 */
public static function columnTypeProblems(array $typeCounts): array
{
    $timestamps = $typeCounts['timestamp'] ?? 0;

    return $timestamps === 0 ? [] : [
        "{$timestamps} column(s) are still TIMESTAMP. The alignment target is DATETIME everywhere — "
        .'TIMESTAMP converts through the session timezone on read and write. See database-alignment-spec.md §4.',
    ];
}
```

- [ ] **Step 4:** Run: `php artisan test --compact --filter=CheckDatabaseSettings` — expect PASS. Then `vendor/bin/pint --dirty --format agent`.
- [ ] **Step 5: Commit** — `feat(db): teach db:check-settings the column-type and pad-attribute drift`

### Task 4: Pre-flight command `db:preflight-alignment`

**Files:**
- Create: `app/Console/Commands/PreflightAlignment.php`
- Test: `tests/Feature/PreflightAlignmentTest.php`

**Interfaces:**
- Produces: read-only `db:preflight-alignment` running, against the CURRENT default MySQL database: (1) precondition assert — zero `SUB_PART`/`EXPRESSION` unique indexes; (2) B3 — per unique/primary index, `GROUP BY` its columns with `COLLATE utf8mb4_0900_ai_ci` on collated ones, NULL rows excluded, reporting colliding groups; (3) B5 — length-based trailing-space count per collated unique column. Exit 1 on any finding. Pure static `duplicateScanSql(string $table, array $columns, array $collatedColumns): string` for testing.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Console\Commands\PreflightAlignment;

it('refuses the sqlite suite driver', function (): void {
    $this->artisan('db:preflight-alignment')
        ->expectsOutputToContain('only supports MySQL')
        ->assertFailed();
});

it('builds a duplicate scan that collates only collated columns and excludes NULL rows', function (): void {
    $sql = PreflightAlignment::duplicateScanSql('roles', ['name', 'guard_name', 'team_id'], ['name', 'guard_name']);

    expect($sql)
        ->toContain('`name` COLLATE utf8mb4_0900_ai_ci')
        ->toContain('`guard_name` COLLATE utf8mb4_0900_ai_ci')
        ->not->toContain('`team_id` COLLATE')
        ->toContain('`name` IS NOT NULL AND `guard_name` IS NOT NULL AND `team_id` IS NOT NULL')
        ->toContain('HAVING COUNT(*) > 1');
});
```

- [ ] **Step 2:** Run: `php artisan test --compact --filter=PreflightAlignment` — expect FAIL.
- [ ] **Step 3: Implement**

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * The three pre-flights the alignment migration requires, generated from
 * information_schema — never hand-listed (spec §10.5: the old plan produced
 * three different index counts by hand; the generated figure is 30).
 * Read-only; safe anywhere, including production.
 */
#[Signature('db:preflight-alignment')]
#[Description('B3 duplicate scan, B5 trailing-space scan, and their structural precondition, for the 0900_ai_ci change.')]
class PreflightAlignment extends Command
{
    public function handle(): int
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->error('db:preflight-alignment only supports MySQL; got '.DB::connection()->getDriverName().'.');

            return self::FAILURE;
        }

        $findings = 0;

        // Precondition: a prefix or functional unique index would make the
        // full-column GROUP BY under-detect. Assert none exist (spec §10.5).
        $odd = DB::selectOne("SELECT
            SUM(CASE WHEN SUB_PART IS NOT NULL THEN 1 ELSE 0 END) prefixed,
            SUM(CASE WHEN EXPRESSION IS NOT NULL THEN 1 ELSE 0 END) functional
            FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND NON_UNIQUE = 0");

        if ((int) $odd->prefixed > 0 || (int) $odd->functional > 0) {
            $this->error("Precondition failed: {$odd->prefixed} prefix / {$odd->functional} functional unique index parts — the B3 scan cannot be trusted. Stop.");

            return self::FAILURE;
        }

        $indexes = DB::select("SELECT s.TABLE_NAME t, s.INDEX_NAME i,
                GROUP_CONCAT(s.COLUMN_NAME ORDER BY s.SEQ_IN_INDEX) cols,
                GROUP_CONCAT(CASE WHEN c.COLLATION_NAME IS NOT NULL THEN s.COLUMN_NAME END ORDER BY s.SEQ_IN_INDEX) collated
            FROM information_schema.STATISTICS s
            JOIN information_schema.COLUMNS c ON c.TABLE_SCHEMA = s.TABLE_SCHEMA
                AND c.TABLE_NAME = s.TABLE_NAME AND c.COLUMN_NAME = s.COLUMN_NAME
            WHERE s.TABLE_SCHEMA = DATABASE() AND s.NON_UNIQUE = 0
            GROUP BY 1, 2 HAVING collated IS NOT NULL");

        $this->line('unique/primary indexes with collated columns: '.count($indexes));

        foreach ($indexes as $index) {
            $columns = explode(',', $index->cols);
            $collated = explode(',', (string) $index->collated);

            $n = (int) DB::selectOne(self::duplicateScanSql($index->t, $columns, $collated))->n;

            if ($n > 0) {
                $this->error("B3: {$index->t}.{$index->i} → {$n} colliding group(s) under utf8mb4_0900_ai_ci");
                $findings++;
            }

            foreach ($collated as $column) {
                if ($column === '') {
                    continue;
                }
                $pad = (int) DB::table($index->t)
                    ->whereRaw("LENGTH(`{$column}`) <> LENGTH(TRIM(TRAILING CHAR(32) FROM `{$column}`))")->count();

                if ($pad > 0) {
                    $this->warn("B5: {$index->t}.{$column} → {$pad} row(s) with trailing spaces (NO PAD makes these stop matching)");
                    $findings++;
                }
            }
        }

        if ($findings === 0) {
            $this->info('Pre-flight clean: zero B3 collisions, zero B5 trailing-space rows.');

            return self::SUCCESS;
        }

        return self::FAILURE;
    }

    /**
     * @param  list<string>  $columns
     * @param  list<string>  $collatedColumns
     */
    public static function duplicateScanSql(string $table, array $columns, array $collatedColumns): string
    {
        $select = implode(', ', array_map(
            fn (string $column): string => in_array($column, $collatedColumns, true)
                ? "`{$column}` COLLATE utf8mb4_0900_ai_ci"
                : "`{$column}`",
            $columns,
        ));
        $notNull = implode(' AND ', array_map(fn (string $column): string => "`{$column}` IS NOT NULL", $columns));

        return "SELECT COUNT(*) n FROM (SELECT 1 FROM `{$table}` WHERE {$notNull} GROUP BY {$select} HAVING COUNT(*) > 1) x";
    }
}
```

- [ ] **Step 4:** Run: `php artisan test --compact --filter=PreflightAlignment` — PASS. Pint.
- [ ] **Step 5: Sanity-run against live local** — `php artisan db:preflight-alignment` — expect `indexes: 30`, clean, exit 0.
- [ ] **Step 6: Commit** — `feat(db): add the generated B3/B5 pre-flight command for the collation change`

### Task 5: The alignment migration

**Files:**
- Create: `database/migrations/2026_08_09_000000_align_collation_and_datetime_columns.php`
- Test: `tests/Feature/AlignmentMigrationTest.php`

**Interfaces:**
- Consumes: nothing (self-contained; reads `information_schema` at runtime).
- Produces: after `php artisan migrate` on any MySQL database: every table `utf8mb4`/`utf8mb4_0900_ai_ci` (schema default included), zero `timestamp` columns, `NOT NULL` preserved, `failed_jobs.failed_at` default dropped. On SQLite: no-op (suite-safe until Task 19). Down: refuses (`RuntimeException`) — restore path is `db:restore`.

- [ ] **Step 1: Write the failing test** (SQLite proves the guard + statement generator only; the rehearsal proves the rest)

```php
<?php

use Illuminate\Support\Facades\DB;

it('no-ops on the sqlite suite driver', function (): void {
    // The suite reaching this line at all proves migrate ran it without error.
    expect(DB::connection()->getDriverName())->toBe('sqlite');
});

it('generates MODIFY definitions that preserve nullability and drop CURRENT_TIMESTAMP defaults', function (): void {
    $migration = require database_path('migrations/2026_08_09_000000_align_collation_and_datetime_columns.php');

    $rows = [
        (object) ['c' => 'created_at', 'n' => 'YES', 'd' => null],
        (object) ['c' => 'failed_at', 'n' => 'NO', 'd' => 'CURRENT_TIMESTAMP'],
        (object) ['c' => 'expires_at', 'n' => 'NO', 'd' => null],
    ];

    expect($migration->modifyClauses($rows))->toBe([
        'MODIFY `created_at` DATETIME NULL',
        'MODIFY `failed_at` DATETIME NOT NULL',
        'MODIFY `expires_at` DATETIME NOT NULL',
    ]);
});
```

- [ ] **Step 2:** Run: `php artisan test --compact --filter=AlignmentMigration` — expect FAIL (file missing).
- [ ] **Step 3: Implement**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The alignment migration: utf8mb4_0900_ai_ci everywhere, TIMESTAMP → DATETIME
 * everywhere, generated from information_schema — never hand-listed.
 *
 * ORDER IS THE TRICK (spec §4/§9): this must run with the session timezone
 * LEFT AS-IS. Each TIMESTAMP value materializes as exactly the literal the app
 * has always read back, so nothing shifts. Pinning the session to UTC first
 * would shift every literal by -2/-3h — that is why clock work is a later
 * phase, gated on this migration having run.
 *
 * A Laravel migration rather than hand SQL because the test lane rebuilds its
 * schema by replaying migrations: hand SQL would leave the lane on 80
 * TIMESTAMP columns forever (spec §7).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return; // SQLite suite (until the MySQL lane lands) — nothing to align.
        }

        $tables = DB::select('SELECT TABLE_NAME t FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = "BASE TABLE"');

        foreach ($tables as $table) {
            // Two statements on purpose: CONVERT TO cannot be combined with
            // other alter options, and it must precede the MODIFYs so the
            // datetime columns land in an already-converted table.
            DB::statement("ALTER TABLE `{$table->t}` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci");

            $clauses = $this->modifyClauses(DB::select('SELECT COLUMN_NAME c, IS_NULLABLE n, COLUMN_DEFAULT d
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND DATA_TYPE = "timestamp"
                ORDER BY ORDINAL_POSITION', [$table->t]));

            if ($clauses !== []) {
                DB::statement("ALTER TABLE `{$table->t}` ".implode(', ', $clauses));
            }
        }

        $database = DB::connection()->getDatabaseName();
        DB::statement("ALTER DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci");
    }

    public function down(): void
    {
        throw new RuntimeException('Irreversible by design — restore a db:snapshot instead (spec §9).');
    }

    /**
     * A bare `MODIFY col DATETIME` silently drops NOT NULL (measured, spec §9),
     * so nullability is restated from information_schema. A CURRENT_TIMESTAMP
     * default is deliberately NOT restated: the one such column
     * (failed_jobs.failed_at) is app-written on this Laravel version and the
     * default was a dormant DB-generated-time hazard (spec §10.1).
     *
     * @param  list<object{c: string, n: string, d: ?string}>  $rows
     * @return list<string>
     */
    public function modifyClauses(array $rows): array
    {
        return array_map(
            fn (object $row): string => sprintf(
                'MODIFY `%s` DATETIME %s',
                $row->c,
                $row->n === 'NO' ? 'NOT NULL' : 'NULL',
            ),
            $rows,
        );
    }
};
```

- [ ] **Step 4:** Run: `php artisan test --compact --filter=AlignmentMigration` — PASS. Then the whole suite (`php -d memory_limit=2G vendor/bin/pest --compact`) — the migration replays in every `migrate:fresh`; expect 1,865+ green. Pint, phpstan on the new file.
- [ ] **Step 5: Commit** — `feat(db): the alignment migration — 0900_ai_ci + DATETIME, generated, driver-guarded`

**Do NOT run `php artisan migrate` on local `podtext` yet** — rehearsal first (Tasks 6–8).

### Task 6: Edge-row seeder `db:seed-rehearsal-edges`

**Files:**
- Create: `app/Console/Commands/SeedRehearsalEdges.php`
- Test: `tests/Feature/SeedRehearsalEdgesTest.php`

**Interfaces:**
- Consumes: a restored snapshot in the current database (template rows to clone).
- Produces: ~100 synthetic rows spread over every table that has TIMESTAMP columns, carrying the edge matrix; refuses to run unless `DATABASE()` matches `/rehearsal|restore_check/`. Public consts `DateEdges` and `CollationPayloads` (Task 8 documents them; the oracle does not depend on them).

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Console\Commands\SeedRehearsalEdges;

it('refuses the sqlite suite driver', function (): void {
    $this->artisan('db:seed-rehearsal-edges')
        ->expectsOutputToContain('only supports MySQL')
        ->assertFailed();
});

it('carries the full edge matrix from the spec', function (): void {
    $edges = SeedRehearsalEdges::DateEdges;

    expect($edges)->toContain('2026-01-15 10:00:00')  // winter, +02 frame
        ->toContain('2026-07-15 10:00:00')            // summer, +03 frame
        ->toContain('2026-03-27 01:59:00')            // last instant before the spring gap
        ->toContain('2026-03-27 03:00:00')            // first instant after it
        ->toContain('2026-10-25 01:30:00')            // the October fold wall
        ->toContain('1970-01-01 02:00:01')            // TIMESTAMP minimum under +02
        ->toContain('2038-01-19 05:14:07');           // max epoch as +02 wall

    expect(SeedRehearsalEdges::CollationPayloads)
        ->toContain('שָׁלוֹם')                          // niqqud
        ->toContain('שלום')                            // its unpointed twin
        ->toContain('טעם ')                            // trailing space (B5 fodder)
        ->toContain('🎧')->toContain('🎤');            // the unicode_ci emoji collapse
});
```

- [ ] **Step 2:** Run: `php artisan test --compact --filter=SeedRehearsalEdges` — FAIL.
- [ ] **Step 3: Implement**

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Rehearsal-only: spray the alignment migration's edge matrix across every
 * table that has TIMESTAMP columns, by cloning an existing template row per
 * table and overwriting its date and collated-string values. Clone-based so
 * FKs and NOT NULLs are satisfied without hand-listing 40 table shapes.
 *
 * Refuses any database not named like a rehearsal copy. Never deployed logic —
 * a tool for spec §9's "rehearsal before reality" gate.
 */
#[Signature('db:seed-rehearsal-edges')]
#[Description('Clone edge-case rows (DST boundaries, epoch edges, hostile Hebrew/emoji strings) into a rehearsal database.')]
class SeedRehearsalEdges extends Command
{
    /** Wall-clock literals, written verbatim through the current session. */
    public const DateEdges = [
        '2026-01-15 10:00:00', '2026-07-15 10:00:00',
        '2026-03-27 01:59:00', '2026-03-27 03:00:00',
        '2026-10-25 01:30:00',
        '1970-01-01 02:00:01', '2038-01-19 05:14:07',
    ];

    /** Hostile strings for collated columns riding the same rows. */
    public const CollationPayloads = ['שָׁלוֹם', 'שלום', 'כׇּל-הזמן', 'ם סופית', 'טעם ', '🎧', '🎤'];

    public function handle(): int
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->error('db:seed-rehearsal-edges only supports MySQL; got '.DB::connection()->getDriverName().'.');

            return self::FAILURE;
        }

        $database = DB::connection()->getDatabaseName();

        if (preg_match('/rehearsal|restore_check/', $database) !== 1) {
            $this->error("Refused: `{$database}` is not a rehearsal database (name must match /rehearsal|restore_check/).");

            return self::FAILURE;
        }

        $tables = DB::select('SELECT DISTINCT TABLE_NAME t FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND DATA_TYPE = "timestamp"');

        $seeded = 0;
        $skipped = [];

        foreach ($tables as $table) {
            $template = (array) (DB::table($table->t)->first() ?? []);

            if ($template === []) {
                $skipped[] = $table->t;

                continue;
            }

            $columns = DB::select('SELECT COLUMN_NAME c, DATA_TYPE dt, IS_NULLABLE n, COLLATION_NAME coll, COLUMN_KEY k, EXTRA e
                FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?', [$table->t]);

            foreach (self::DateEdges as $i => $edge) {
                $row = $template;

                foreach ($columns as $column) {
                    if ($column->e === 'auto_increment') {
                        unset($row[$column->c]);
                    } elseif ($column->dt === 'timestamp') {
                        // NULL every other clone on nullable date columns, so
                        // NULL-preservation is rehearsed too.
                        $row[$column->c] = ($column->n === 'YES' && $i % 2 === 1) ? null : $edge;
                    } elseif ($column->coll !== null && isset($row[$column->c]) && is_string($row[$column->c])) {
                        // Unique-safe: payload + a nonce suffix.
                        $payload = self::CollationPayloads[$i % count(self::CollationPayloads)];
                        $row[$column->c] = Str::limit($payload.' '.Str::random(8), 60, '');
                    }
                }

                DB::table($table->t)->insert($row);
                $seeded++;
            }
        }

        $this->info("Seeded {$seeded} edge rows across ".(count($tables) - count($skipped)).' tables.');

        if ($skipped !== []) {
            // Count canary: empty tables cannot be cloned — say so, never silently.
            $this->warn('Skipped (no template row): '.implode(', ', $skipped));
        }

        return self::SUCCESS;
    }
}
```

- [ ] **Step 4:** Run: `php artisan test --compact --filter=SeedRehearsalEdges` — PASS. Pint, phpstan.
- [ ] **Step 5: Commit** — `feat(db): rehearsal edge-row seeder for the alignment dry runs`

### Task 7: Oracle command `db:alignment-oracle`

**Files:**
- Create: `app/Console/Commands/AlignmentOracle.php`
- Test: `tests/Feature/AlignmentOracleTest.php`

**Interfaces:**
- Produces: `db:alignment-oracle capture` writes `storage/app/db-snapshots/oracle-<database>.json` — per table: row count, incremental SHA1 over every date column + every collated column rendered `CAST(... AS CHAR)`, NULLs encoded distinctly, plus `information_schema` properties (COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, COLLATION_NAME per column). `db:alignment-oracle compare` re-captures and diffs against the file: data hashes must be IDENTICAL; property diffs must be EXACTLY the intended ones (timestamp→datetime, unicode_ci→0900_ai_ci, the one dropped default). Pure static `rowExpression(array $columns): string` for testing.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Console\Commands\AlignmentOracle;

it('refuses the sqlite suite driver', function (): void {
    $this->artisan('db:alignment-oracle', ['mode' => 'capture'])
        ->expectsOutputToContain('only supports MySQL')
        ->assertFailed();
});

it('encodes NULL distinctly from empty string in the row expression', function (): void {
    $sql = AlignmentOracle::rowExpression(['created_at', 'title']);

    // CONCAT_WS silently SKIPS NULLs, which would make (NULL,'x') hash like
    // ('x') — every column must be COALESCE-wrapped with a sentinel.
    expect($sql)
        ->toContain("COALESCE(CAST(`created_at` AS CHAR), '␀')")
        ->toContain("COALESCE(CAST(`title` AS CHAR), '␀')")
        ->toContain("CONCAT_WS('|',");
});
```

- [ ] **Step 2:** Run: `php artisan test --compact --filter=AlignmentOracle` — FAIL.
- [ ] **Step 3: Implement**

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * The mechanical before/after oracle for the alignment migration (spec §9):
 * value preservation is proven by hash equality, property change by an exact
 * expected diff — not by eyeballing.
 */
#[Signature('db:alignment-oracle {mode : capture or compare}')]
#[Description('Capture or compare per-table value hashes and column properties around the alignment migration.')]
class AlignmentOracle extends Command
{
    public function handle(): int
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->error('db:alignment-oracle only supports MySQL; got '.DB::connection()->getDriverName().'.');

            return self::FAILURE;
        }

        $path = storage_path('app/db-snapshots/oracle-'.DB::connection()->getDatabaseName().'.json');

        if ($this->argument('mode') === 'capture') {
            file_put_contents($path, json_encode($this->state(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $this->info("Oracle captured to {$path}.");

            return self::SUCCESS;
        }

        $before = json_decode((string) file_get_contents($path), true);

        if (! is_array($before)) {
            $this->error("No capture at {$path} — run capture first.");

            return self::FAILURE;
        }

        return $this->compare($before, $this->state());
    }

    /**
     * @return array{hashes: array<string, array{rows: int, sha1: string}>, properties: array<string, array{type: string, nullable: string, default: ?string, collation: ?string}>}
     */
    private function state(): array
    {
        $hashes = [];

        $tables = DB::select('SELECT DISTINCT TABLE_NAME t FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND (DATA_TYPE IN ("timestamp", "datetime") OR COLLATION_NAME IS NOT NULL)');

        foreach ($tables as $table) {
            $columns = array_map(
                fn (object $row): string => $row->c,
                DB::select('SELECT COLUMN_NAME c FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
                      AND (DATA_TYPE IN ("timestamp", "datetime") OR COLLATION_NAME IS NOT NULL)
                    ORDER BY ORDINAL_POSITION', [$table->t]),
            );

            // Incremental hash over ordered rows: GROUP_CONCAT would silently
            // truncate at group_concat_max_len and report false equality.
            $context = hash_init('sha1');
            $rows = 0;

            foreach (DB::cursor('SELECT '.self::rowExpression($columns)." line FROM `{$table->t}` ORDER BY 1") as $row) {
                hash_update($context, $row->line."\n");
                $rows++;
            }

            $hashes[$table->t] = ['rows' => $rows, 'sha1' => hash_final($context)];
        }

        $properties = [];

        foreach (DB::select('SELECT TABLE_NAME t, COLUMN_NAME c, COLUMN_TYPE ty, IS_NULLABLE n, COLUMN_DEFAULT d, COLLATION_NAME coll
            FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() ORDER BY 1, 2') as $row) {
            $properties["{$row->t}.{$row->c}"] = [
                'type' => $row->ty, 'nullable' => $row->n, 'default' => $row->d, 'collation' => $row->coll,
            ];
        }

        return ['hashes' => $hashes, 'properties' => $properties];
    }

    /**
     * @param  array{hashes: array<string, array{rows: int, sha1: string}>, properties: array<string, array<string, ?string>>}  $before
     * @param  array{hashes: array<string, array{rows: int, sha1: string}>, properties: array<string, array<string, ?string>>}  $after
     */
    private function compare(array $before, array $after): int
    {
        $failures = 0;

        foreach ($before['hashes'] as $table => $expected) {
            $actual = $after['hashes'][$table] ?? null;

            if ($actual !== $expected) {
                $this->error("VALUE DRIFT in `{$table}`: ".json_encode($expected).' → '.json_encode($actual));
                $failures++;
            }
        }

        foreach ($before['properties'] as $key => $old) {
            $new = $after['properties'][$key] ?? null;

            if ($new === null) {
                $this->error("Column vanished: {$key}");
                $failures++;

                continue;
            }

            $intended = str_starts_with((string) $old['type'], 'timestamp')
                && str_starts_with((string) $new['type'], 'datetime')
                && $new['nullable'] === $old['nullable']
                && ($new['collation'] === $old['collation']
                    || ($old['collation'] === 'utf8mb4_unicode_ci' && $new['collation'] === 'utf8mb4_0900_ai_ci'));

            $collationOnly = $old['type'] === $new['type']
                && $new['nullable'] === $old['nullable'] && $new['default'] === $old['default']
                && $old['collation'] === 'utf8mb4_unicode_ci' && $new['collation'] === 'utf8mb4_0900_ai_ci';

            if ($old !== $new && ! $intended && ! $collationOnly) {
                $this->error("UNINTENDED PROPERTY CHANGE {$key}: ".json_encode($old).' → '.json_encode($new));
                $failures++;
            }
        }

        if ($failures === 0) {
            $this->info('Oracle PASS: values byte-identical; only the intended property changes occurred.');

            return self::SUCCESS;
        }

        return self::FAILURE;
    }

    /**
     * @param  list<string>  $columns
     */
    public static function rowExpression(array $columns): string
    {
        $wrapped = implode(', ', array_map(
            fn (string $column): string => "COALESCE(CAST(`{$column}` AS CHAR), '␀')",
            $columns,
        ));

        return "CONCAT_WS('|', {$wrapped})";
    }
}
```

- [ ] **Step 4:** Run: `php artisan test --compact --filter=AlignmentOracle` — PASS. Pint, phpstan.
- [ ] **Step 5: Commit** — `feat(db): before/after oracle for the alignment migration`

### Task 8: 🛑 Rehearsal — both engines, gate before reality

**Files:** none (runbook; uses Tasks 2–7). Record results in `docs/phase-02/database-alignment-rehearsal-log.md` (create).

- [ ] **Step 1: 9.4.0 rehearsal** (seed = `podtext_restore_check`, kept by operator decision):

```bash
DB_DATABASE=podtext_restore_check php artisan db:restore --latest --force
DB_DATABASE=podtext_restore_check php artisan db:seed-rehearsal-edges
DB_DATABASE=podtext_restore_check php artisan db:preflight-alignment
DB_DATABASE=podtext_restore_check php artisan db:alignment-oracle capture
time DB_DATABASE=podtext_restore_check php artisan migrate --force
DB_DATABASE=podtext_restore_check php artisan db:alignment-oracle compare
DB_DATABASE=podtext_restore_check php artisan db:check-settings
```

Expected: preflight clean (B3 may legitimately flag seeded `שָׁלוֹם`/`שלום` collisions **only if** both landed in the same unique column — if so, note it: that is the scan working; delete the colliding clone and re-run) · oracle compare PASS · check-settings: collation `utf8mb4_0900_ai_ci` ×all, `datetime ×N`, zero timestamp — only the clock finding remains (exit 1, expected until Phase 3).

- [ ] **Step 2: 8.0.46 rehearsal** (production's engine, on the Task 2 daemon):

```bash
mysql -h 127.0.0.1 -P 3307 -u root -e "CREATE DATABASE podtext_rehearsal"
DB_PORT=3307 DB_DATABASE=podtext_rehearsal php artisan db:restore --latest --force
```

then repeat Step 1's sequence with `DB_PORT=3307 DB_DATABASE=podtext_rehearsal`. Expected: identical results on 8.0.46 — this is also the §3-matrix identity canary for the version gap.

- [ ] **Step 3: Record** both runs (durations, outputs, any B3 seeded-collision notes) in `docs/phase-02/database-alignment-rehearsal-log.md`; the 8.0.46 `time` result is the production window estimate. Commit the log.
- [ ] **Step 4: 🛑 STOP — operator reviews the rehearsal log and approves proceeding to real databases.** Rehearsal DBs stay (drop only on operator approval).

### Task 9: Config hardcode + workaround docblocks

**Files:**
- Modify: `config/database.php:56-57` (mysql), `:76-77` (mariadb)
- Modify: `app/Support/PublicContent/PublicTranscriptionSelector.php:196-219` (docblock only)
- Modify: `app/Support/Dashboard/JerusalemDailySeries.php` (docblock only)
- Test: `tests/Feature/DatabaseConfigAlignmentTest.php` (create)

**Interfaces:**
- Produces: `config('database.connections.mysql.charset') === 'utf8mb4'`, `...collation === 'utf8mb4_0900_ai_ci'`, no `env()` indirection (same for mariadb). This is what stamps every FUTURE `CREATE TABLE` (spec §3).

- [ ] **Step 1: Failing test**

```php
<?php

it('hardcodes the aligned charset and collation with no env indirection', function (): void {
    foreach (['mysql', 'mariadb'] as $connection) {
        expect(config("database.connections.{$connection}.charset"))->toBe('utf8mb4');
        expect(config("database.connections.{$connection}.collation"))->toBe('utf8mb4_0900_ai_ci');
    }

    $source = file_get_contents(config_path('database.php'));
    expect($source)->not->toContain('DB_CHARSET')->not->toContain('DB_COLLATION');
});
```

- [ ] **Step 2:** Run: `php artisan test --compact --filter=DatabaseConfigAlignment` — FAIL.
- [ ] **Step 3:** In both blocks replace the two lines with:

```php
'charset' => 'utf8mb4',
'collation' => 'utf8mb4_0900_ai_ci', // hardcoded on purpose: this key stamps every CREATE TABLE (alignment spec §3); env indirection is how unicode_ci drifted in.
```

In `PublicTranscriptionSelector::sqlMoment()`'s docblock: replace the argument-against-migration paragraph with: the DB clock is aligned by the alignment program; `sqlMoment()` **stays** (decision: do not revert to `CURRENT_TIMESTAMP` — §11's guard bans DB-generated time regardless of the clock being fixed). In `JerusalemDailySeries`: note PHP bucketing is now a *presentation* choice (Jerusalem days), no longer a clock workaround.

- [ ] **Step 4:** Test PASS · full suite green · pint · commit — `fix(db): hardcode the aligned charset/collation; retire the clock-workaround docblocks`

### Task 10: Local `podtext` alignment run

**Files:** none (runbook).

- [ ] **Step 1:** `php artisan db:snapshot --label=pre-alignment`
- [ ] **Step 2:** `php artisan db:preflight-alignment` — expect clean (821 rows of near-ASCII; a finding here means STOP and investigate).
- [ ] **Step 3:** `php artisan db:alignment-oracle capture && php artisan migrate --force && php artisan db:alignment-oracle compare` — compare must PASS. Do not browse the app mid-pass (a half-converted schema can 1267 on JOINs).
- [ ] **Step 4:** `php artisan db:check-settings` — expect: schema+tables+columns `utf8mb4_0900_ai_ci`, `datetime ×80`, zero timestamp, ONLY the clock finding left.
- [ ] **Step 5:** Browse the local app (episodes list, search, admin dashboard) — dates must render exactly as before.

### Task 11: 🛑 Production alignment window

**Files:** none (runbook). Requires operator approval to push + deploy + open the window.

- [ ] **Step 1: 🛑 STOP — operator approves the window.** Then push: `git push origin main`.
- [ ] **Step 2:** Deploy via Forge (`forge_deploy_site`, wait) — **Forge's deploy script runs `migrate --force`, so the window opens BEFORE deploy**:

```bash
# on the server, current/: BEFORE deploying
php artisan down --secret="alignment-window"
php artisan db:snapshot --label=pre-alignment
php artisan db:preflight-alignment          # must be clean — re-run, never trust the 08-08 result
php artisan db:alignment-oracle capture
```

- [ ] **Step 3:** Trigger the deploy (runs the migration). Then on the server: `php artisan db:alignment-oracle compare && php artisan db:check-settings` — compare PASS; check-settings: aligned collation, zero timestamp, `utf8mb3` finding GONE, clock finding still present (Phase 3).
- [ ] **Step 4:** `php artisan up` · `curl -s -o /dev/null -w "%{http_code}" https://podtext.co.il/up` → 200 · spot-check dates via tinker render exactly as before · `php artisan db:snapshot --label=post-alignment`.
- [ ] **Step 5:** Update `docs/phase-02/current-project-state.md` (alignment executed: local + production) and commit.

---

## Phase 3 — clocks

### Task 12: 🛑 Freeze ikc4 + ari, then production OS → UTC

**Files:** none in this repo (server runbook). Measured 2026-08-08: both apps run `config('app.timezone') === 'UTC'` (their data is already UTC-frame), 28 + 62 date columns, one `CURRENT_TIMESTAMP` default each; ikc4's `.env` carries an inert-but-dangerous `APP_TIMEZONE="Asia/Jerusalem"` line.

- [ ] **Step 1: 🛑 STOP — operator approves touching ikc4/ari.** Back up both: `mysqldump --single-transaction --skip-tz-utc --no-tablespaces --quick` each into `/home/forge/backups/` (same flag discipline as Phase 0).
- [ ] **Step 2: Freeze both** (hand SQL is correct here — no lane replays their migrations). For each schema, generate and run from `information_schema` exactly like the migration does: per table `CONVERT TO` **is not wanted here** (collation is podtext's decision, not theirs) — run ONLY the TIMESTAMP→DATETIME MODIFYs with nullability restated, and drop their single `CURRENT_TIMESTAMP` default. Verify: `SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='<schema>' AND DATA_TYPE='timestamp'` → 0; spot-check a few literals unchanged.
- [ ] **Step 3:** Delete ikc4's `APP_TIMEZONE="Asia/Jerusalem"` `.env` line (it is ignored today and a silent clock flip if their config ever reads it); rebuild their config cache (`php artisan config:cache` in that site's current/).
- [ ] **Step 4: OS flip:** `sudo timedatectl set-timezone UTC` (root command via Forge), then restart mysql (`sudo systemctl restart mysql`) and php-fpm. Verify: `timedatectl` shows UTC; on ALL THREE apps `TIMEDIFF(NOW(), UTC_TIMESTAMP())` → `00:00:00`; spot-check each app renders dates unchanged (they must — all three are DATETIME-frozen).
- [ ] **Step 5:** podtext `php artisan db:check-settings` → **exit 0, "No drift found"** for the first time. Record in state doc.

### Task 13: Pin the connection + `my.cnf`; load tz tables

**Files:**
- Modify: `config/database.php` mysql block (add one key)
- Modify: `tests/Feature/DatabaseConfigAlignmentTest.php` (extend)

- [ ] **Step 1: Extend the Task 9 test**

```php
it('pins the mysql session clock to UTC', function (): void {
    expect(config('database.connections.mysql.timezone'))->toBe('+00:00');
});
```

- [ ] **Step 2:** Run filtered — FAIL. Add to the mysql block:

```php
'timezone' => '+00:00', // pinned AFTER the DATETIME conversion (alignment spec §9 step 5): declared in git, not inherited from whichever OS the server runs.
```

Run filtered — PASS. Full suite green (SQLite ignores the key). Commit — `fix(db): pin the mysql session to UTC now the columns are DATETIME`.

- [ ] **Step 3: `my.cnf` on both servers.** Local Herd: find the service's config (`~/Library/Application Support/Herd/config/services/mysql*/my.cnf` — locate with `find`), add `default-time-zone = '+00:00'` under `[mysqld]`, restart the service. Production: same key in `/etc/mysql/mysql.conf.d/mysqld.cnf` via Forge root command, restart mysql. Verify both: `SELECT @@global.time_zone` → `+00:00`.
- [ ] **Step 4: tz tables, all three daemons** (closes A4): `mysql_tzinfo_to_sql /usr/share/zoneinfo | mysql -u root mysql` (adapt host/port per daemon; production as root via Forge). Verify each: `SELECT CONVERT_TZ('2026-01-15 10:00:00','UTC','Asia/Jerusalem')` → `2026-01-15 12:00:00` (not NULL).

Operational note: do the **production** `my.cnf` edit and tz-table load inside Task 12's window, before its `systemctl restart mysql` — one restart serves the OS flip, the pin and the tables, instead of three.

### Task 14: Schedule timezone intent

**Files:**
- Modify: `routes/console.php:13-14`
- Test: `tests/Feature/ScheduleTimezoneIntentTest.php` (create)

- [ ] **Step 1: Failing test**

```php
<?php

use Illuminate\Console\Scheduling\Schedule;

it('gives every schedule entry an explicit timezone', function (): void {
    $events = app(Schedule::class)->events();

    expect($events)->not->toBeEmpty();

    foreach ($events as $event) {
        expect($event->timezone)
            ->not->toBeNull()
            ->toBe(\App\Support\UiTimezone::name(), "Unpinned schedule entry: {$event->command}");
    }
});
```

- [ ] **Step 2:** Run filtered — FAIL (both entries unpinned). Fix:

```php
Schedule::command('media:prune-quarantine --apply')->timezone(UiTimezone::name())->dailyAt('03:30');
Schedule::command('model:prune')->timezone(UiTimezone::name())->dailyAt('03:50');
```

(with `use App\Support\UiTimezone;` — the operator wrote 03:30/03:50 meaning Israel night; they now run at Israel 03:30/03:50 all year.)

- [ ] **Step 3:** PASS · full suite · pint · commit — `fix(schedule): pin both entries to the Jerusalem wall clock, and guard the intent`
- [ ] **Step 4: 🛑 Phase 3 push + deploy gate.** On operator word: `git push origin main`, deploy via Forge (carries Task 13's config pin AND this schedule change together), then verify on production: `/up` 200, `php artisan db:check-settings` exit 0, `php artisan schedule:list` shows both entries with the Jerusalem timezone.

---

## Phase 4 — the MySQL test lane (SQLite retired)

### Task 15: Lane database, user, grants

**Files:** none (runbook, on the Task 2 daemon).

- [ ] **Step 1:**

```bash
mysql -h 127.0.0.1 -P 3307 -u root <<'SQL'
CREATE DATABASE IF NOT EXISTS `podtext_test` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
CREATE USER IF NOT EXISTS 'podtext_test'@'127.0.0.1' IDENTIFIED BY 'CHOSEN_TEST_PASSWORD';
GRANT ALL PRIVILEGES ON `podtext\_test`.* TO 'podtext_test'@'127.0.0.1';
FLUSH PRIVILEGES;
SQL
```

- [ ] **Step 2:** Drop the stale empty `podtext_test` on **3306** (wrong daemon, wrong collation): verify empty first (`SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='podtext_test'` → 0), then `DROP DATABASE podtext_test` on 3306.
- [ ] **Step 3:** Add to `.env`: `DB_TESTING_HOST=127.0.0.1`, `DB_TESTING_PORT=3307`, `DB_TESTING_DATABASE=podtext_test`, `DB_TESTING_USERNAME=podtext_test`, `DB_TESTING_PASSWORD=<chosen>`. Add the same four keys with empty values + a comment block to `.env.example` (commit that).

### Task 16: `mysql_testing` connection

**Files:**
- Modify: `config/database.php` (new connection after `mysql`)
- Test: extend `tests/Feature/DatabaseConfigAlignmentTest.php`

- [ ] **Step 1: Failing test**

```php
it('defines the lane connection with no url key and no shared env', function (): void {
    $lane = config('database.connections.mysql_testing');

    expect($lane['driver'])->toBe('mysql');
    expect($lane)->not->toHaveKey('url');       // a DSN silently overrides host+database
    expect($lane['charset'])->toBe('utf8mb4');
    expect($lane['collation'])->toBe('utf8mb4_0900_ai_ci');
    expect($lane['timezone'])->toBe('+00:00');

    $source = file_get_contents(config_path('database.php'));
    // The lane must never read the app's database name.
    expect(substr_count($source, "env('DB_DATABASE'"))->toBe(2); // sqlite + mysql only
});
```

- [ ] **Step 2:** FAIL, then add:

```php
'mysql_testing' => [
    'driver' => 'mysql',
    'host' => env('DB_TESTING_HOST', '127.0.0.1'),
    'port' => env('DB_TESTING_PORT', '3307'),
    'database' => env('DB_TESTING_DATABASE'),   // no default — missing must fail closed
    'username' => env('DB_TESTING_USERNAME'),
    'password' => env('DB_TESTING_PASSWORD', ''),
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_0900_ai_ci',
    'timezone' => '+00:00',
    'prefix' => '',
    'prefix_indexes' => true,
    'strict' => true,
    'engine' => null,
    // no 'url' key on purpose — a DSN overrides host and database at connect time
],
```

- [ ] **Step 3:** PASS · pint · commit — `feat(test-lane): the mysql_testing connection, sharing no env key with the app`

### Task 17: The one-shape guard + the four SQLite sites move together

**Files:**
- Modify: `phpunit.xml:29-47` · `tests/Pest.php:21-33` · `tests/TestCase.php` (full guard rewrite) · `tests/Feature/EnvironmentGuardsTest.php` (rewrite)
- Create: `tests/Feature/TestLaneGuardTest.php`

**Interfaces:**
- Produces: `Tests\TestCase::assertSafeTestingDatabase()` accepting EXACTLY one shape (mysql_testing per the clause table below), refusal-tested clause by clause; `phpunit.xml` forces `DB_CONNECTION=mysql_testing` (and no longer forces `DB_DATABASE`); first-use fingerprint under `storage/framework/testing/mysql-lane/`.

- [ ] **Step 1: Write the guard's refusal tests first** (`TestLaneGuardTest.php`) — the guard exposes `public static function refusalFor(array $config, array $rawEnvDatabases): ?string` (pure, stubbed-config testable):

```php
<?php

use Tests\TestCase;

$valid = fn (): array => [
    'default' => 'mysql_testing',
    'connections' => ['mysql_testing' => [
        'driver' => 'mysql', 'host' => '127.0.0.1', 'port' => '3307',
        'database' => 'podtext_test', 'username' => 'podtext_test', 'password' => 'x',
    ], 'mysql' => ['port' => '3306', 'database' => 'podtext', 'username' => 'podtext']],
];

it('accepts the canonical lane shape', function () use ($valid): void {
    expect(TestCase::refusalFor($valid(), ['podtext']))->toBeNull();
});

it('refuses every broken shape', function (callable $mutate, string $needle) use ($valid): void {
    $config = $valid();
    $mutate($config);
    expect((string) TestCase::refusalFor($config, ['podtext']))->toContain($needle);
})->with([
    'app connection as default' => [fn (array &$c) => $c['default'] = 'mysql', 'mysql_testing'],
    'DSN present' => [fn (array &$c) => $c['connections']['mysql_testing']['url'] = 'mysql://x', 'url'],
    'bad name' => [fn (array &$c) => $c['connections']['mysql_testing']['database'] = 'podtext', 'name'],
    'name equals a raw .env database' => [fn (array &$c) => $c['connections']['mysql_testing']['database'] = 'podtext_test' /* rawEnv below */, 'env'],
    'remote host' => [fn (array &$c) => $c['connections']['mysql_testing']['host'] = '10.0.0.9', 'host'],
    'app port' => [fn (array &$c) => $c['connections']['mysql_testing']['port'] = '3306', 'port'],
    'root user' => [fn (array &$c) => $c['connections']['mysql_testing']['username'] = 'root', 'root'],
    'app username' => [fn (array &$c) => $c['connections']['mysql_testing']['username'] = 'podtext', 'username'],
    'empty database' => [fn (array &$c) => $c['connections']['mysql_testing']['database'] = null, 'closed'],
]);

it('refuses when the lane name appears in the raw env files', function () use ($valid): void {
    expect((string) TestCase::refusalFor($valid(), ['podtext', 'podtext_test']))->toContain('env');
});
```

- [ ] **Step 2:** FAIL. **Step 3: Rewrite `tests/TestCase.php`:**

```php
<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    protected function refreshApplication(): void
    {
        $this->forceSafeTestingEnvironment();

        parent::refreshApplication();

        config([
            'app.env' => 'testing',
            'cache.default' => 'array',
            'database.default' => 'mysql_testing',
            'queue.default' => 'sync',
            'session.driver' => 'array',
            // Even an explicit DB::connection('mysql') inside a test must not
            // reach the real schema (spec §7 free hardening).
            'database.connections.mysql.database' => 'unreachable_from_tests',
            'database.connections.mariadb.database' => 'unreachable_from_tests',
        ]);
        $this->app->detectEnvironment(fn (): string => 'testing');

        $this->assertSafeTestingDatabase();
    }

    private function forceSafeTestingEnvironment(): void
    {
        foreach ([
            'APP_ENV' => 'testing',
            'CACHE_STORE' => 'array',
            'DB_CONNECTION' => 'mysql_testing',
            'DB_URL' => '',
            'QUEUE_CONNECTION' => 'sync',
            'SESSION_DRIVER' => 'array',
        ] as $key => $value) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }

    /**
     * One accepted shape. Everything else throws, BEFORE any migration runs.
     * NOTE for tests/Unit: Pest binds this class to Feature/Browser only —
     * unit tests bypass the guard (latent while they do not boot the app;
     * carried from the superseded spec).
     */
    private function assertSafeTestingDatabase(): void
    {
        $refusal = self::refusalFor(config('database'), self::rawEnvDatabases());

        if ($refusal === null) {
            $this->assertDisposableSchema();

            return;
        }

        throw new RuntimeException('Refusing to run tests: '.$refusal);
    }

    /**
     * Pure clause table (spec §7). Returns the first refusal, null when safe.
     *
     * @param  array<string, mixed>  $config
     * @param  list<string>  $rawEnvDatabases
     */
    public static function refusalFor(array $config, array $rawEnvDatabases): ?string
    {
        if (($config['default'] ?? null) !== 'mysql_testing') {
            return 'database.default must be mysql_testing, never the app connection; got '.json_encode($config['default'] ?? null).'.';
        }

        $lane = $config['connections']['mysql_testing'] ?? [];

        return match (true) {
            ($lane['driver'] ?? null) !== 'mysql' => 'the lane driver must be mysql.',
            array_key_exists('url', $lane) => 'a url/DSN key silently overrides host and database — remove it.',
            ($lane['database'] ?? null) === null || $lane['database'] === '' => 'no lane database configured — failing closed.',
            preg_match('/^[a-z][a-z0-9_]*_test(_[0-9]+)?$/', (string) $lane['database']) !== 1 => 'the lane database name must match /^[a-z][a-z0-9_]*_test(_[0-9]+)?$/.',
            in_array((string) $lane['database'], $rawEnvDatabases, true) => 'the lane database name appears as a DB_DATABASE in the raw .env files — a forced var could be masking the real name.',
            ! in_array((string) ($lane['host'] ?? ''), ['127.0.0.1', 'localhost', '::1'], true) => 'the lane host must be local — a remote host is never a test target.',
            (string) ($lane['port'] ?? '') === (string) ($config['connections']['mysql']['port'] ?? '') => 'the lane port equals the app connection port — the lane must live on its own daemon.',
            ($lane['username'] ?? '') === '' => 'the lane username is empty.',
            ($lane['username'] ?? '') === 'root' => 'root would bypass the schema-scoped grant — the last barrier. Refused.',
            ($lane['username'] ?? '') === ($config['connections']['mysql']['username'] ?? null) => 'the lane username equals the app username — the grant barrier would be gone.',
            default => null,
        };
    }

    /**
     * DB_DATABASE values read from the raw .env files — NOT env(), which a
     * forced phpunit var could mask.
     *
     * @return list<string>
     */
    private static function rawEnvDatabases(): array
    {
        $values = [];

        foreach ([base_path('.env'), base_path('.env.example')] as $file) {
            if (! is_file($file)) {
                continue;
            }
            if (preg_match('/^DB_DATABASE=(.*)$/m', (string) file_get_contents($file), $m) === 1) {
                $values[] = trim($m[1], "\"' ");
            }
        }

        return array_values(array_filter($values));
    }

    /**
     * First use of a schema must find it empty; afterwards a fingerprint file
     * remembers it. Also asserts the lane carries zero TIMESTAMP columns while
     * its connection pins +00:00 — the spec §7 ordering refusal made real.
     */
    private function assertDisposableSchema(): void
    {
        $lane = config('database.connections.mysql_testing');
        $directory = storage_path('framework/testing/mysql-lane');
        $fingerprint = $directory.'/'.sha1($lane['host'].'|'.$lane['port'].'|'.$lane['database']);

        if (! is_file($fingerprint)) {
            $tables = (int) DB::connection('mysql_testing')
                ->selectOne('SELECT COUNT(*) n FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?', [$lane['database']])->n;

            if ($tables > 0) {
                throw new RuntimeException("Refusing first use: `{$lane['database']}` already holds {$tables} tables and no fingerprint exists — is this a stranger's database?");
            }

            @mkdir($directory, 0755, true);
            file_put_contents($fingerprint, now()->toIso8601String());

            return;
        }

        if (($lane['timezone'] ?? null) === '+00:00') {
            $timestamps = (int) DB::connection('mysql_testing')
                ->selectOne('SELECT COUNT(*) n FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND DATA_TYPE = "timestamp"', [$lane['database']])->n;

            if ($timestamps > 0) {
                throw new RuntimeException("The lane pins +00:00 but holds {$timestamps} TIMESTAMP columns — it would test clock semantics production does not have. Run the alignment migration first (spec §7).");
            }
        }
    }
}
```

- [ ] **Step 4: Move the other three sites.** `phpunit.xml`: `DB_CONNECTION` force → `mysql_testing`; DELETE the `DB_DATABASE` force line (the lane must not read it); keep `DB_URL` blank force. `tests/Pest.php:21-33`: same swap in the putenv loop (`DB_CONNECTION => mysql_testing`, drop `DB_DATABASE`). `EnvironmentGuardsTest.php`: rewrite its sqlite-shape assertions to pin the NEW contract (default is `mysql_testing`, `mysql.database === 'unreachable_from_tests'`, guard throws on the app connection).
- [ ] **Step 5:** Run: `php artisan test --compact --filter=TestLaneGuard` — PASS (pure clause tests). **The full suite now runs against the lane for the first time — expect reds; that is Task 19's job, not a defect in this task.** Commit the guard work — `feat(test-lane)!: one-shape MySQL guard; SQLite forcing removed from all four sites`

### Task 18: Run-lock for concurrent pest runs

**Files:**
- Modify: `tests/Pest.php` (after the env loop)

- [ ] **Step 1:** Add:

```php
/*
 * One shared lane schema; concurrent pest runs would migrate:fresh over each
 * other (SQLite :memory: made this impossible by construction — the lane does
 * not). flock, held for the process lifetime; fail fast, never queue.
 */
$laneLock = fopen(storage_path('framework/testing/mysql-lane-run.lock'), 'c+');

if ($laneLock === false || ! flock($laneLock, LOCK_EX | LOCK_NB)) {
    fwrite(STDERR, "Another pest run holds the MySQL lane. Wait for it to finish.\n");
    exit(1);
}
```

- [ ] **Step 2:** Verify: start a long test run, then `php artisan test --compact --filter=anything` in a second shell → the second exits 1 with the message. Commit — `feat(test-lane): flock run-lock against concurrent suite runs`

### Task 19: 🛑 First red run — fallout to green

**Files:** unknown by nature — whatever the red run names. Known suspects (spec §7): the 29 non-RefreshDatabase files (empty-DB assumptions now see a migrated schema), `TranscriptionsModelTest.php:65` + `AuthzLegacyRoleBackfillTest.php:247,766-776` (DDL implicit commits → re-migrate cost), `config('permission.testing')` branch flipping, rowid-order assumptions, SQL-strict rejections.

- [ ] **Step 1:** `php -d memory_limit=2G vendor/bin/pest --compact` — record every failure with its attribution: **SQL strict mode** (engine difference — fix the code/test) vs **Eloquent strict mode** (real column-subset defect — fix the query) vs **ordering/rowid** (add explicit `orderBy`) vs **DDL-commit leakage** (isolate or re-scope the test). Fix in small commits, one family at a time. NEVER weaken the guard or re-point at SQLite to get green.
- [ ] **Step 2:** Re-run the §3 collation matrix once on the lane daemon (8.0.46) and append the result to the rehearsal log — the version-identity canary.
- [ ] **Step 3:** Full suite green on the lane. Record duration vs the 566s SQLite baseline in the rehearsal log. `composer filacheck` + pint + phpstan.
- [ ] **Step 4:** 🛑 STOP — operator reviews the fallout diff before push (this is the largest behavioral change to the test system in the repo's history).

---

## Phase 5 — display + input globals

### Task 20: Filament global hooks

**Files:**
- Modify: `app/Providers/AppServiceProvider.php` (in `boot()`, beside the existing `configureUsing` block at :194-330)
- Test: `tests/Feature/FilamentLocalizationDefaultsTest.php` (create)

**Interfaces:**
- Produces: app-wide defaults — every Filament datetime renders Jerusalem day-first with zero per-site config; pickers are non-native JS with `UiFormats` display formats.

- [ ] **Step 1: Failing test**

```php
<?php

use App\Support\UiFormats;
use App\Support\UiTimezone;
use Filament\Forms\Components\DateTimePicker;
use Filament\Support\Facades\FilamentTimezone;
use Filament\Tables\Table;

it('sets the app-wide Filament timezone to the UI timezone', function (): void {
    expect(FilamentTimezone::get())->toBe(UiTimezone::name());
});

it('defaults every table and picker to the UiFormats day-first shapes', function (): void {
    $table = Table::make(new class extends \Livewire\Component implements \Filament\Tables\Contracts\HasTable
    {
        use \Filament\Tables\Concerns\InteractsWithTable;
    });

    expect($table->getDefaultDateDisplayFormat())->toBe(UiFormats::date());
    expect($table->getDefaultDateTimeDisplayFormat())->toBe(UiFormats::dateTime());
    expect($table->getDefaultTimeDisplayFormat())->toBe(UiFormats::time());

    $picker = DateTimePicker::make('probe');
    expect($picker->isNative())->toBeFalse();
    expect($picker->getDefaultDateTimeDisplayFormat())->toBe(UiFormats::dateTime());
});
```

(If `Table::make()` needs a heavier livewire host in practice, use an existing table component from the app instead — the assertion is on the resolved defaults, not the host.)

- [ ] **Step 2:** FAIL. **Step 3:** In `AppServiceProvider::boot()`:

```php
use Filament\Forms\Components\DateTimePicker;
use Filament\Schemas\Schema;
use Filament\Support\Facades\FilamentTimezone;

// One home for wall-clock and shape: every Filament datetime loads, renders
// and saves through the UI timezone and the day-first formats — per-site
// ->timezone()/->displayFormat() chains are banned by UiTimezonePolicyTest.
FilamentTimezone::set(UiTimezone::name());

Table::configureUsing(fn (Table $table) => $table
    ->defaultDateDisplayFormat(UiFormats::date())
    ->defaultDateTimeDisplayFormat(UiFormats::dateTime())
    ->defaultTimeDisplayFormat(UiFormats::time()));

Schema::configureUsing(fn (Schema $schema) => $schema
    ->defaultDateDisplayFormat(UiFormats::date())
    ->defaultDateTimeDisplayFormat(UiFormats::dateTime())
    ->defaultTimeDisplayFormat(UiFormats::time()));

DateTimePicker::configureUsing(fn (DateTimePicker $picker) => $picker
    // Browser-native pickers render in the BROWSER's locale — a dependency
    // outside the repo. Non-native makes the display format real. (Operator
    // approved: JS picker replaces the browser control.)
    ->native(false)
    ->defaultDateDisplayFormat(UiFormats::date())
    ->defaultDateTimeDisplayFormat(UiFormats::dateTime())
    ->defaultTimeDisplayFormat(UiFormats::time()));
```

(Merge into the existing `Table::configureUsing` closure at :289 rather than registering a second one.)

- [ ] **Step 4:** PASS · full suite · pint · commit — `feat(ui): global Jerusalem timezone and day-first formats for all Filament surfaces`

### Task 21: `forDisplay` Carbon macro

**Files:**
- Modify: `app/Providers/AppServiceProvider.php` (`boot()`)
- Test: `tests/Feature/ForDisplayMacroTest.php` (create)

**Interfaces:**
- Produces: `$carbon->forDisplay()` → `d/m/Y H:i` in Jerusalem; `->forDisplay(UiFormats::date())` → date-only; works on `Carbon` and `CarbonImmutable`; renders Hebrew month names under `translatedFormat` when a prose format is passed.

- [ ] **Step 1: Failing test**

```php
<?php

use App\Support\UiFormats;
use Carbon\Carbon;
use Carbon\CarbonImmutable;

it('renders UTC instants on Jerusalem walls, day-first', function (): void {
    expect(Carbon::parse('2026-01-15 10:00:00', 'UTC')->forDisplay())->toBe('15/01/2026 12:00'); // +02 winter
    expect(Carbon::parse('2026-07-15 10:00:00', 'UTC')->forDisplay())->toBe('15/07/2026 13:00'); // +03 summer
    expect(Carbon::parse('2026-07-15 10:00:00', 'UTC')->forDisplay(UiFormats::date()))->toBe('15/07/2026');
});

it('works on CarbonImmutable — importer support code holds those', function (): void {
    expect(CarbonImmutable::parse('2026-01-15 10:00:00', 'UTC')->forDisplay())->toBe('15/01/2026 12:00');
});

it('renders Hebrew month names with zero extra setup', function (): void {
    // nesbot/carbon's Laravel provider syncs the locale to app.locale ('he').
    expect(Carbon::parse('2026-01-15 10:00:00', 'UTC')->forDisplay('j F Y'))->toContain('ינואר');
});
```

- [ ] **Step 2:** FAIL. **Step 3:** In `boot()` (and mirror onto `CarbonImmutable::macro` — `Carbon::mixin` does not reach the immutable class automatically in all versions; registering both is two lines and unambiguous):

```php
use Carbon\Carbon;
use Carbon\CarbonImmutable;

$forDisplay = function (?string $format = null): string {
    /** @var \Carbon\CarbonInterface $this */
    return $this->copy()->setTimezone(UiTimezone::name())
        ->translatedFormat($format ?? UiFormats::dateTime());
};
Carbon::macro('forDisplay', $forDisplay);
CarbonImmutable::macro('forDisplay', $forDisplay);
```

- [ ] **Step 4:** PASS · full suite · pint · commit — `feat(ui): the forDisplay macro — one call, zero per-site configuration`

### Task 22: Route the call sites through the globals

**Files (the counted sweep — verify each with grep before editing, counts from spec §8):**
- Modify: ~25 Filament chain files — strip `->timezone(UiTimezone::name())`, `->displayFormat(...)`, and format arguments now equal to the defaults (21 DateTimePicker chains, 21 `->dateTime(format, timezone:)` columns, 1 `->since(tz)`, 2 DatePicker `displayFormat`s).
- Modify: `app/Filament/Resources/Authors/Tables/AuthorsTable.php:38,43` — the two bare `->dateTime()` (today rendering UTC in vendor format) become bare `->dateTime()` **correctly** under the Task 20 defaults — verify rendering, no code change needed beyond removing nothing; list them in the sweep's verification.
- Modify: the ~17 non-Filament display sites → `->forDisplay()` / `->forDisplay(UiFormats::date())` (7 Blade views, `PublicContentItemCardPresenter.php:108-110`, `PublicContentGroupCardPresenter`, `OwnerImagePresenter.php:597`, `ContentItemTranscriptViewer.php:185`, `SettingsImportReport.php:148`, `ImporterCsvBuilder.php:54`, `PruneMediaQuarantine.php:48`, `SettingsBackupSnapshot.php:89`, `SettingsBackupVersion.php:90`).
- Do NOT touch the ~9 input-parsing/domain-logic sites: `SpotifyLinksDirectImporter.php:303,310`, `EpisodeSpotifyLookup.php:157`, `ConfiguresContentImports.php:260`, `DashboardRange.php:50,67,79`, `PublicationDateAutofill.php:17`, `JerusalemDailySeries.php:37,64`, `EditorialMetrics.php:602`.

- [ ] **Step 1:** Sweep with grep first; record the actual counts (`grep -rln 'UiTimezone::name()' app resources | wc -l` before and after — expect 52 → ~9-11).
- [ ] **Step 2:** Edit file by file; after each family run the file's feature tests filtered.
- [ ] **Step 3:** Full suite green · pint · visual spot-check (admin table dates, a picker, a public card — all day-first Jerusalem).
- [ ] **Step 4:** Commit — `refactor(ui): collapse per-site timezone/format chains into the global hooks`

### Task 23: DST input rules

**Files:**
- Modify: `app/Providers/AppServiceProvider.php` (extend the Task 20 `DateTimePicker::configureUsing`)
- Create: `app/Rules/ExistsInTimezone.php`
- Test: `tests/Feature/DstInputEdgeTest.php`
- Lang: add `validation.custom.nonexistent_wall_time` (or inline message via trans key) to `lang/en/validation.php` + `lang/he/validation.php`

- [ ] **Step 1: Failing test**

```php
<?php

use App\Rules\ExistsInTimezone;
use Illuminate\Support\Facades\Validator;

it('rejects the spring-forward gap and accepts its edges', function (string $value, bool $valid): void {
    $validator = Validator::make(['at' => $value], ['at' => new ExistsInTimezone('Asia/Jerusalem', 'Y-m-d H:i:s')]);

    expect($validator->passes())->toBe($valid);
})->with([
    'inside the gap' => ['2026-03-27 02:30:00', false],
    'last valid before' => ['2026-03-27 01:59:00', true],
    'first valid after' => ['2026-03-27 03:00:00', true],
    'the fold hour (accept-later policy)' => ['2026-10-25 01:30:00', true],
    'ordinary time' => ['2026-08-08 12:00:00', true],
]);
```

- [ ] **Step 2:** FAIL. **Step 3:**

```php
<?php

namespace App\Rules;

use Carbon\Carbon;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Throwable;

/**
 * Rejects wall-clock times that do not exist in the given timezone — the
 * spring-forward gap, where PHP silently normalizes 02:30 to 03:30 (measured,
 * alignment spec §10.3). Detection is round-trip: parse, re-format, compare —
 * PHP has no dedicated gap API; the normalize-forward mismatch IS the
 * detector. The autumn fold hour exists twice and is ACCEPTED (accept-later
 * policy, spec §10.3) — it round-trips cleanly, so it passes here.
 */
class ExistsInTimezone implements ValidationRule
{
    public function __construct(
        private readonly string $timezone,
        private readonly string $format,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return; // presence is someone else's rule
        }

        try {
            $parsed = Carbon::createFromFormat($this->format, $value, $this->timezone);
        } catch (Throwable) {
            return; // format errors are someone else's rule too
        }

        if ($parsed !== false && $parsed->format($this->format) !== $value) {
            $fail(__('validation.custom.nonexistent_wall_time', ['timezone' => $this->timezone]));
        }
    }
}
```

Wire it globally in the Task 20 picker hook, gated exactly as the spec requires:

```php
DateTimePicker::configureUsing(fn (DateTimePicker $picker) => $picker
    ->native(false)
    ->defaultDateDisplayFormat(UiFormats::date())
    ->defaultDateTimeDisplayFormat(UiFormats::dateTime())
    ->defaultTimeDisplayFormat(UiFormats::time())
    ->rule(
        fn (DateTimePicker $component) => new \App\Rules\ExistsInTimezone(
            $component->getTimezone() ?? config('app.timezone'),
            $component->getFormat(),
        ),
        condition: fn (DateTimePicker $component): bool => $component->hasTime(),
    ));
```

Implementation note: verify `Field::rule($rule, $condition)`'s exact signature
against the installed vendor (`vendor/filament/schemas/src/Concerns/CanBeValidated.php`)
before wiring — if the condition parameter differs in this minor, gate inside
the rule closure instead by returning the always-passing `'nullable'` string
for date-only pickers (never return null from a rule closure without checking
that Filament filters nulls).

```php
```

Add both lang strings (en: "This time does not exist in :timezone on that date — the clock skips it."; he equivalent).

- [ ] **Step 4:** PASS · **picker round-trip test** (closes spec §13's item): a Livewire test filling a DateTimePicker with a Jerusalem wall time and asserting the model receives the UTC instant, then the edit form redisplays the original wall time. Use an existing episode resource form (e.g. `EditContentItem` with `published_at`) via `livewire()->fillForm()->call('save')` + `assertDatabaseHas` with the UTC literal.
- [ ] **Step 5:** Full suite · pint · commit — `feat(input): reject nonexistent Jerusalem wall times; pin the picker round trip`

### Task 23b: 🛑 Phase 5 push + deploy gate

- [ ] **Step 1:** Full gate first (`pest`, `pint --dirty`, `composer filacheck`, phpstan on touched files, `npm run build` — Phase 5 touched Blade views).
- [ ] **Step 2: 🛑 STOP — operator approves.** `git push origin main`, deploy via Forge. This deploy is **operator-visible**: pickers become Filament JS controls, all dates render day-first Jerusalem. Verify on production: `/up` 200, an admin table shows `d/m/Y H:i`, a picker opens as the JS control and shows day-first, one public card date is day-first.

---

## Phase 6 — guards + docs

### Task 24: Migration statement-scan guard

**Files:**
- Create: `tests/Feature/MigrationDateColumnPolicyTest.php`

- [ ] **Step 1: Write the guard (it should pass immediately — the alignment migration removed every violation from the LIVE schema, but old migration FILES still contain banned calls, so scope it to migrations newer than the alignment):**

```php
<?php

/**
 * Future migrations must not reintroduce TIMESTAMP or DB-generated time
 * (alignment spec §10.2/§11). Old files are history; the alignment migration
 * neutralizes them at replay. Everything after it must be clean.
 */
const AlignmentMigration = '2026_08_09_000000';

const BannedCalls = [
    '->timestamp(' => 'use ->dateTime() — TIMESTAMP converts through the session clock',
    '->timestamps(' => 'use ->datetimes() (Laravel 13 DATETIME pair) or two ->dateTime() columns',
    '->softDeletes(' => 'use ->softDeletesDatetime()',
    '->softDeletesTz(' => 'use ->softDeletesDatetime()',
    '->timestampTz(' => 'use ->dateTime()',
    '->timestampsTz(' => 'use ->datetimes()',
    '->useCurrent(' => 'DB-generated time disagrees with the app clock by design — write the value from PHP',
    '->useCurrentOnUpdate(' => 'same class — write the value from PHP',
    'CURRENT_TIMESTAMP' => 'same class — write the value from PHP',
];

it('keeps post-alignment migrations free of TIMESTAMP and DB-generated time', function (): void {
    $violations = [];

    foreach (glob(database_path('migrations/*.php')) ?: [] as $file) {
        // Lexicographic compare on the 17-char date stamp: the alignment
        // migration itself and everything before it are history — the
        // alignment neutralizes them at replay. Everything after must be clean.
        if (substr(basename($file), 0, 17) <= AlignmentMigration) {
            continue;
        }

        $source = (string) file_get_contents($file);

        foreach (BannedCalls as $needle => $fix) {
            if (str_contains($source, $needle)) {
                $violations[] = basename($file).": {$needle} — {$fix}";
            }
        }
    }

    expect($violations)->toBeEmpty(implode("\n", $violations));
});

it('is not vacuous: the scan sees the migration directory', function (): void {
    expect(glob(database_path('migrations/*.php')))->not->toBeEmpty();
});
```

- [ ] **Step 2:** Run filtered — PASS (green-first guard; mutate a copy to confirm it fires: temporarily add `->timestamps()` to a scratch migration file, watch it fail, remove).
- [ ] **Step 3:** Commit — `test(guards): ban TIMESTAMP and DB-generated time in post-alignment migrations`

### Task 25: Model date-format escape-hatch guard

**Files:**
- Create: `tests/Feature/ModelDateFormatPolicyTest.php`

- [ ] **Step 1:**

```php
<?php

it('keeps per-model date-format escape hatches out of the codebase', function (): void {
    $violations = [];

    foreach (glob(app_path('Models/*.php')) ?: [] as $file) {
        $source = (string) file_get_contents($file);

        foreach (['$dateFormat', '#[DateFormat', 'dateFormat:'] as $needle) {
            if (str_contains($source, $needle)) {
                $violations[] = basename($file).": {$needle} changes the stored literal format (alignment spec §10.2)";
            }
        }
    }

    expect($violations)->toBeEmpty(implode("\n", $violations));
});
```

- [ ] **Step 2:** PASS green-first (zero usages measured); mutation-check; commit — `test(guards): ban per-model date-format overrides`

### Task 26: Extend `UiTimezonePolicyTest` — the three-way allowlist

**Files:**
- Modify: `tests/Feature/UiTimezonePolicyTest.php`

- [ ] **Step 1:** Add a second scan to the existing file-content test: ban `->timezone(` and `->displayFormat(` inside `app/Filament/` (Filament chains — the globals own these now), with an explicit allowlist array for the exempt families (input-parsing/domain-logic files listed in Task 22's do-not-touch block, plus `AppServiceProvider.php` where the globals themselves live). Assert the allowlist entries still exist on disk (a stale allowlist must fail loudly).
- [ ] **Step 2:** PASS (Task 22 already stripped the chains) · mutation-check by re-adding one `->timezone()` chain temporarily · commit — `test(guards): Filament chains may not re-acquire per-site timezone/format config`

### Task 27: Docs — deferral banners + state

**Files:**
- Modify: `docs/phase-02/hebrew-collation-and-clock-plan.md` (top banner), `docs/phase-02/mysql-test-lane-spec.md` (top banner), `docs/phase-02/current-project-state.md`

- [ ] **Step 1:** Add to both old docs, under the title: `> **Superseded 2026-08-08 by [database-alignment-spec.md](database-alignment-spec.md)**, which carries the reviewed decisions (collation chosen on merit incl. the emoji evidence; clock solved by DATETIME conversion; the lane on a second Herd daemon). This document stays as the measurement record.`
- [ ] **Step 2:** Update `current-project-state.md` with the completed program (phases, commits, lane runtime, guard list).
- [ ] **Step 3:** Commit — `docs: close the alignment program — banners, state, and the guard inventory`
- [ ] **Step 4:** 🛑 STOP — operator decides the final push/deploy and whether the rehearsal DBs may now be dropped.

---

## Self-review notes (already applied)

- Spec coverage: §3 → T5/T9, §4 → T5/T8, §6 → T1/T15, §7 → T2/T15–T19, §8 → T20–T23, §9 → T5–T11, §10.1 → T5, §10.2 → T24/T25, §10.3 → T23, §10.4 → T14, §10.5 → T4, §11 → T3/T24–T26, §13 ikc4/ari + OS → T12, tz tables → T13. Non-goals honored: no cast changes, no JSON collation, no LONGTEXT shadow indexes anywhere above.
- The `->datetimes()` helper named in T24's messages exists in Laravel 13 (`Blueprint::datetimes()` — DATETIME `created_at`/`updated_at` pair); verify at implementation and fall back to two `->dateTime()` calls if the installed minor lacks it.
- T17's `refusalFor` name-vs-rawEnv clause: after T15 adds `DB_TESTING_DATABASE=podtext_test` to `.env`, the raw-env scan must read only `DB_DATABASE=` lines (it does — the regex is anchored), or the lane would refuse its own name.
- Ordering hazards honored: T5 never runs on real DBs before T8's gate; T13's `+00:00` pin lands only after T11; T17's fingerprint refuses a pinned lane with TIMESTAMP columns.
