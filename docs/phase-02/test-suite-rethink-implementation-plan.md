# Test-Suite Rethink + Post-Alignment Backlog Implementation Plan

> **EXECUTED 2026-08-10** (Part 1 + Phases R/T/S complete and reviewed; Phase U remains operator-gated). **Checkbox state was not maintained during execution — do not read `- [ ]` boxes as status.** The record is the round ledger (`.superpowers/sdd/progress.md`) and the commits it names (A11 ruling, 2026-08-11).
> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Close the operator-confirmed post-alignment fix-now batch (spec Part 1, F1–F6), then run the staged suite rethink: measurements (R), Rector introduction wired to larastan (T), structure fixes behind the R gate (S), and the Pest 5 upgrade last behind its own approval (U).

**Architecture:** Part 1 is five independent, individually-committed fixes against the existing lane/guard architecture — one shared extraction (`TestLaneContract`) feeds both the suite guard and the new reset command. Part 2 is measurement-first: no structural change lands before Phase R's numbers exist, no Rector rule writes without a reviewed dry-run diff, and nothing on the Pest-5 line moves before the operator's separate go-ahead.

**Tech Stack:** Laravel 13.24 / PHP 8.4.23 (Herd) · Pest 4.7.8 on the MySQL 8.0.46 test lane (`mysql_testing` @ 127.0.0.1:3307, `podtext_test`) · larastan 3.10 at level 5 · Rector 2.6 + driftingly/rector-laravel 2.5 (new, dev-only).

**Spec:** `docs/phase-02/test-suite-rethink-spec.md` (approved 2026-08-10). Research: `docs/research/test-suite-rethink-notes.md`.

## Global Constraints

- Full gate before every commit batch: `php -d memory_limit=2G vendor/bin/pest --compact` (~10 min; 1,953 tests), `vendor/bin/pint --dirty --format agent`, `composer filacheck`. `npm run build` only if assets change (none in this plan).
- **Never** run `vendor/bin/filacheck` directly — it force-enables `--fix` under agents. `composer filacheck` only. `composer filacheck:fix` needs explicit operator approval.
- `-d memory_limit=2G` is required on every `vendor/bin/pest` invocation (the Bash-tool shell may fall back to 128M; the flag does NOT propagate through `php artisan test`, so always invoke `vendor/bin/pest` directly).
- Commits to local `main` with **explicit pathspecs only** — never `git add -A`, never `pint --dirty` as a commit gate for files you didn't touch (other sessions share this tree). No push: auto-deploy is OFF and push happens only on the operator's word.
- End every commit message with: `Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>`
- One suite run at a time on the lane: the flock run-lock refuses a second run in this tree; NEVER wipe the lane or its fingerprint to unblock anything while another session may be running (coordinate first). Do not edit tracked files while a full suite is running (T23b contamination lesson).
- No test deletions without operator approval. Do not weaken any existing assertion.
- PHPStan protocol for touched app files: before changing file X, run `php -d memory_limit=2G vendor/bin/phpstan analyse <X> -v` and record the error count; after the change, the count must be equal or lower (443-error backlog is parked — add nothing to it). The `-v` matters: the agent formatter truncates without it.
- New PHP follows house style: constructor promotion, explicit return types, curly braces always, PHPDoc over inline comments, `Tests\TestCase` conventions. Run `vendor/bin/pint --dirty --format agent` after each PHP edit.

---

## Part 1 — fix-now batch

### Task 1: F1 — make `LegacyRoleBackfillSchemaContract` mysql-only

**Files:**
- Modify: `app/Auth/LegacyRoleBackfill/LegacyRoleBackfillSchemaContract.php`
- Test: `tests/Feature/AuthzLegacyRoleBackfillTest.php`

**Interfaces:**
- Consumes: `AnalysisIssue` (`public string $code`, constants like `AnalysisIssue::SCHEMA_COLUMN_PROPERTY_DRIFT = 'schema_column_property_drift'`), `BackfillRefusalException` (same namespace).
- Produces: `expected(string $driver): array` now throws `BackfillRefusalException` for anything but `'mysql'`; `issues(array $actual): array` short-circuits any non-mysql `driver` key to `[new AnalysisIssue(AnalysisIssue::SCHEMA_COLUMN_PROPERTY_DRIFT)]`. The mysql descriptor stays byte-identical.

- [ ] **Step 1: Write the failing refusal test**

In `tests/Feature/AuthzLegacyRoleBackfillTest.php`, add to the imports block (alphabetical position, before `use App\Auth\LegacyRoleBackfill\AnalysisReport;`):

```php
use App\Auth\LegacyRoleBackfill\AnalysisIssue;
```

Immediately after the test `it('records the complete MySQL schema descriptor and exposes a pure MySQL expectation', ...)` (ends near line 779), insert:

```php
it('refuses non-mysql drivers everywhere in the schema contract', function (): void {
    $contract = new LegacyRoleBackfillSchemaContract(
        DB::connection(),
        config('permission.table_names'),
        (new User)->getTable(),
    );

    expect(fn () => $contract->expected('sqlite'))->toThrow(BackfillRefusalException::class)
        ->and(fn () => $contract->expected('pgsql'))->toThrow(BackfillRefusalException::class);

    $issues = $contract->issues(['driver' => 'sqlite', 'tables' => []]);

    expect($issues)->toHaveCount(1)
        ->and($issues[0]->code)->toBe(AnalysisIssue::SCHEMA_COLUMN_PROPERTY_DRIFT);
});
```

- [ ] **Step 2: Run it to verify it fails**

```bash
php -d memory_limit=2G vendor/bin/pest tests/Feature/AuthzLegacyRoleBackfillTest.php --compact --filter='refuses non-mysql drivers'
```

Expected: FAIL — `expected('sqlite')` does not currently throw (sqlite is still in the accepted pair), and `issues()` for a sqlite actual currently returns six `schema_missing_table` issues, not one property-drift.

- [ ] **Step 3: Record the phpstan baseline for the file**

```bash
php -d memory_limit=2G vendor/bin/phpstan analyse app/Auth/LegacyRoleBackfill/LegacyRoleBackfillSchemaContract.php -v
```

Record the error count (expected 0; whatever it is, the post-change count must not exceed it).

- [ ] **Step 4: Remove the sqlite arms**

All edits in `app/Auth/LegacyRoleBackfill/LegacyRoleBackfillSchemaContract.php`:

(a) `inspect()` guard (lines 19–23) becomes:

```php
        $driver = $this->connection->getDriverName();

        if ($driver !== 'mysql') {
            throw new BackfillRefusalException('The database driver is unsupported for AUTHZ1-C schema inspection.');
        }
```

(b) The two `array_map` lines (37–38) drop the driver argument:

```php
            $columns = array_map(fn (array $column): array => $this->normalizeColumn($column), $builder->getColumns($table));
            $indexes = array_map(fn (array $index): array => $this->normalizeIndex($index), $builder->getIndexes($table));
```

(c) `expected()` guard (line 67) becomes:

```php
        if ($driver !== 'mysql') {
            throw new BackfillRefusalException('The database driver is unsupported for AUTHZ1-C schema expectations.');
        }
```

(d) In the `$integer` closure: `'unsigned' => $driver === 'mysql',` → `'unsigned' => true,`

(e) In the `$string` closure: `'length' => $driver === 'mysql' ? $length : null,` → `'length' => $length,`

(f) Every `$driver === 'mysql' ? 'btree' : null` inside `$named()` and the `$tables` array becomes the literal `'btree'` (six occurrences: `$named()`'s primary + unique, `model_has_permissions` index + primary, `model_has_roles` index + primary, `users` index + primary — replace each ternary in place).

(g) `role_has_permissions`'s conditional index list becomes unconditional:

```php
                'indexes' => $indexes([
                    $index('primary', ['permission_id', 'role_id'], 'btree'),
                    $index('index', ['role_id'], 'btree'),
                ]),
```

(h) `issues()` guard (lines 176–180) becomes:

```php
        $driver = $actual['driver'] ?? null;

        if (! is_string($driver) || $driver !== 'mysql') {
            return [new AnalysisIssue(AnalysisIssue::SCHEMA_COLUMN_PROPERTY_DRIFT)];
        }
```

(i) `normalizeColumn()` drops its `$driver` parameter and the two driver conditionals:

```php
    /** @param array<string, mixed> $column @return array<string, mixed> */
    private function normalizeColumn(array $column): array
    {
        $rawType = strtolower((string) ($column['type'] ?? $column['type_name'] ?? ''));
        $typeName = strtolower((string) ($column['type_name'] ?? strtok($rawType, '(')));
        $type = match (true) {
            str_contains($typeName, 'int') => 'integer',
            in_array($typeName, ['varchar', 'char', 'string'], true) => 'string',
            in_array($typeName, ['timestamp', 'datetime'], true) => 'datetime',
            default => $typeName,
        };
        preg_match('/\((\d+)\)/', $rawType, $matches);
        $length = isset($matches[1]) ? (int) $matches[1] : null;
        $default = $column['default'] ?? null;

        if (is_string($default)) {
            $default = trim($default, "'\"");
        }

        return [
            'name' => (string) ($column['name'] ?? ''),
            'type' => $type,
            'length' => $length,
            'unsigned' => str_contains($rawType, 'unsigned'),
            'nullable' => ($column['nullable'] ?? null) === true,
            'default' => $default,
            'auto_increment' => ($column['auto_increment'] ?? null) === true,
        ];
    }
```

(j) `normalizeIndex()` drops its `$driver` parameter:

```php
    /** @param array<string, mixed> $index @return array<string, mixed> */
    private function normalizeIndex(array $index): array
    {
        return [
            'kind' => ($index['primary'] ?? false) ? 'primary' : (($index['unique'] ?? false) ? 'unique' : 'index'),
            'columns' => array_values($index['columns'] ?? []),
            'type' => strtolower((string) ($index['type'] ?? '')),
        ];
    }
```

- [ ] **Step 5: Run the refusal test — PASS; run the descriptor oracle — PASS**

```bash
php -d memory_limit=2G vendor/bin/pest tests/Feature/AuthzLegacyRoleBackfillTest.php --compact --filter='refuses non-mysql drivers|records the complete MySQL schema descriptor'
```

Expected: PASS 2/2. The descriptor test compares live-inspected schema against `expected('mysql')` byte for byte — it is the proof the mysql half did not move.

- [ ] **Step 6: Run the whole file**

```bash
php -d memory_limit=2G vendor/bin/pest tests/Feature/AuthzLegacyRoleBackfillTest.php --compact
```

Expected: PASS (55 tests: the file's 54 + the new one).

- [ ] **Step 7: phpstan after, pint**

```bash
php -d memory_limit=2G vendor/bin/phpstan analyse app/Auth/LegacyRoleBackfill/LegacyRoleBackfillSchemaContract.php -v
vendor/bin/pint --dirty --format agent
```

Expected: error count ≤ Step 3's; pint passed.

- [ ] **Step 8: Commit**

```bash
git add app/Auth/LegacyRoleBackfill/LegacyRoleBackfillSchemaContract.php tests/Feature/AuthzLegacyRoleBackfillTest.php
git commit -m "refactor(auth): schema contract is mysql-only — the sqlite arms were dead since T19

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 2: F2 — nullability-drift fixture coverage

**Files:**
- Test: `tests/Feature/AuthzLegacyRoleBackfillTest.php`

**Interfaces:**
- Consumes: `authzAnalyzer()` and `authzCreateLegacyUsers()` helpers already defined in the file; `issue_totals` (array keyed by `AnalysisIssue` code strings) from `analyze()->toArray()`.
- Produces: nothing new — coverage only.

- [ ] **Step 1: Write the failing test (assertion only, no drift yet)**

Insert immediately after `it('enumerates column property primary unique secondary and foreign-key schema drift together', ...)` (ends near line 817):

```php
it('detects nullability drift on a non-key column as property drift', function (): void {
    authzCreateLegacyUsers();

    expect(authzAnalyzer()->analyze()->toArray()['issue_totals'])
        ->toHaveKey('schema_column_property_drift');
});
```

- [ ] **Step 2: Run it to verify it fails**

```bash
php -d memory_limit=2G vendor/bin/pest tests/Feature/AuthzLegacyRoleBackfillTest.php --compact --filter='detects nullability drift'
```

Expected: FAIL — `Failed asserting that an array has the key 'schema_column_property_drift'` (clean schema, zero drift). This red run is the mutation check: it proves the assertion cannot pass without the drift.

- [ ] **Step 3: Add the drift and the unconditional revert**

Replace the test body with:

```php
it('detects nullability drift on a non-key column as property drift', function (): void {
    authzCreateLegacyUsers();

    // roles.name is NOT NULL in the real migration and is not part of any
    // primary key, so MySQL accepts the nullability flip (unlike the PK
    // column the original fixture had to abandon — spec F2). Only the
    // nullability changes: length, charset (table default), and the absent
    // default are restated identically.
    DB::statement('ALTER TABLE roles MODIFY name VARCHAR(255) NULL');

    try {
        expect(authzAnalyzer()->analyze()->toArray()['issue_totals'])
            ->toHaveKey('schema_column_property_drift');
    } finally {
        // MySQL DDL auto-commits and escapes RefreshDatabase's rollback —
        // the revert must run even when the assertion fails (I2/M1 lesson).
        DB::statement('ALTER TABLE roles MODIFY name VARCHAR(255) NOT NULL');
    }
});
```

- [ ] **Step 4: Run it — PASS — and prove the revert leaves no trace**

```bash
php -d memory_limit=2G vendor/bin/pest tests/Feature/AuthzLegacyRoleBackfillTest.php --compact --filter='detects nullability drift|records the complete MySQL schema descriptor'
```

Expected: PASS 2/2. The descriptor test asserts `issue_totals === []` on the live schema — it fails if the revert left `roles.name` nullable, so running both together proves the cleanup is byte-clean.

- [ ] **Step 5: Commit**

```bash
git add tests/Feature/AuthzLegacyRoleBackfillTest.php
git commit -m "test(auth): nullability drift on a non-key column is detected as property drift

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 3: F3 — seconds-bearing payload in `EpisodesTableR1Test`

**Files:**
- Test: `tests/Feature/EpisodesTableR1Test.php:432`

**Interfaces:** none — payload realism only. `DstInputEdgeTest` keeps owning gap rejection.

- [ ] **Step 1: Change the payload**

Line 432, inside `it('reschedules from the date cell modal in the Jerusalem timezone', ...)`:

```php
            ['published_at' => '2026-08-10 09:30:00'],
```

(was `'2026-08-10 09:30'` — a shape no real browser sends: flatpickr's raw property is always `Y-m-d H:i:s`, so the no-seconds string made `ExistsInTimezone` throw-and-pass instead of evaluating, T23 residual.)

- [ ] **Step 2: Run the whole file**

```bash
php -d memory_limit=2G vendor/bin/pest tests/Feature/EpisodesTableR1Test.php --compact
```

Expected: PASS 38/38. The UTC assertion (`2026-08-10 06:30:00`) is unchanged — seconds were already `:00`.

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/EpisodesTableR1Test.php
git commit -m "test(episodes): send the seconds-bearing payload a real browser sends — the DST rule now sees it

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 4: F4a — extract the lane clause table to `App\Support\Testing\TestLaneContract`

**Files:**
- Create: `app/Support/Testing/TestLaneContract.php`
- Modify: `tests/TestCase.php`
- Modify: `tests/Feature/TestLaneGuardTest.php` (call sites)
- Modify: `tests/Feature/EnvironmentGuardsTest.php:49` (call site)

**Interfaces:**
- Produces (Task 5 consumes all three):
  - `TestLaneContract::refusalFor(array $config, array $rawEnvDatabases): ?string`
  - `TestLaneContract::rawEnvDatabases(): array` (list<string>)
  - `TestLaneContract::fingerprintPath(string $host, string $port, string $database): string`
- `TestCase::refusalFor()` and `TestCase::rawEnvDatabases()` are **deleted** (grep must find no remaining references).

- [ ] **Step 1: Create the class (bodies moved verbatim from `tests/TestCase.php`)**

`app/Support/Testing/TestLaneContract.php`:

```php
<?php

namespace App\Support\Testing;

/**
 * The one accepted shape for the disposable MySQL test lane, extracted from
 * tests/TestCase.php so non-test tooling (db:test-lane-reset) refuses on the
 * same clause table the suite boots on. Pure static: no state, no connection
 * — callers pass the config array and the raw env names in.
 */
final class TestLaneContract
{
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
            array_key_exists('unix_socket', $lane) => 'a unix_socket key bypasses host and port — remove it.',
            ($lane['database'] ?? null) === null || $lane['database'] === '' => 'no lane database configured — failing closed.',
            preg_match('/^[a-z][a-z0-9_]*_test(_[0-9]+)?$/', (string) $lane['database']) !== 1 => 'the lane database name must match /^[a-z][a-z0-9_]*_test(_[0-9]+)?$/.',
            in_array((string) $lane['database'], $rawEnvDatabases, true) => 'the lane database name appears as a DB_DATABASE in the raw .env files — a forced var could be masking the real name.',
            ! in_array((string) ($lane['host'] ?? ''), ['127.0.0.1', '::1'], true) => 'the lane host must be 127.0.0.1 or ::1 — localhost means the unix socket (the app daemon), and a remote host is never a test target.',
            preg_match('/^\d+$/', (string) ($lane['port'] ?? '')) !== 1 => 'the lane port must be an explicit number — an empty port silently resolves to the app daemon.',
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
    public static function rawEnvDatabases(): array
    {
        $values = [];

        foreach ([base_path('.env'), base_path('.env.example')] as $file) {
            if (! is_file($file)) {
                continue;
            }
            if (preg_match('/^DB_DATABASE=(.*)$/m', (string) file_get_contents($file), $m) === 1) {
                $values[] = trim($m[1], "\"' \r");
            }
        }

        return array_values(array_filter($values));
    }

    /** The first-use fingerprint file for a lane identity (host|port|database). */
    public static function fingerprintPath(string $host, string $port, string $database): string
    {
        return storage_path('framework/testing/mysql-lane/'.sha1($host.'|'.$port.'|'.$database));
    }
}
```

- [ ] **Step 2: Rewire `tests/TestCase.php`**

(a) Add import: `use App\Support\Testing\TestLaneContract;`

(b) `assertSafeTestingDatabase()` body's first line becomes:

```php
        $refusal = TestLaneContract::refusalFor(config('database'), TestLaneContract::rawEnvDatabases());
```

(c) **Delete** the entire `refusalFor()` and `rawEnvDatabases()` methods (their PHPDoc included).

(d) In `assertDisposableSchema()`, replace the two path lines (`$directory = ...; $fingerprint = ...;`) with:

```php
        $fingerprint = TestLaneContract::fingerprintPath((string) $lane['host'], (string) $lane['port'], (string) $lane['database']);
        $directory = dirname($fingerprint);
```

- [ ] **Step 3: Update the two test call sites**

`tests/Feature/TestLaneGuardTest.php`: replace `use Tests\TestCase;` with `use App\Support\Testing\TestLaneContract;` and both `TestCase::refusalFor(` calls with `TestLaneContract::refusalFor(`.

`tests/Feature/EnvironmentGuardsTest.php`: add `use App\Support\Testing\TestLaneContract;`, and line 49 becomes `expect(TestLaneContract::refusalFor($config, []))->not->toBeNull();`. Remove the now-unused `use Tests\TestCase;` import.

- [ ] **Step 4: Prove no dangling references, run the guard tests**

```bash
grep -rn "TestCase::refusalFor\|TestCase::rawEnvDatabases" app tests
php -d memory_limit=2G vendor/bin/pest tests/Feature/TestLaneGuardTest.php tests/Feature/EnvironmentGuardsTest.php --compact
```

Expected: grep silent; PASS 19/19 (15 + 4).

- [ ] **Step 5: Mutation check — the moved table is the live one**

Temporarily change the `'root'` clause string in `TestLaneContract` to `'toor'`, run:

```bash
php -d memory_limit=2G vendor/bin/pest tests/Feature/TestLaneGuardTest.php --compact --filter='refuses every broken shape'
```

Expected: FAIL on the `root user` dataset case. Revert the mutation (`git diff app/Support/Testing/TestLaneContract.php` must be the Step-1 content again), re-run: PASS.

- [ ] **Step 6: phpstan + pint + commit**

```bash
php -d memory_limit=2G vendor/bin/phpstan analyse app/Support/Testing/TestLaneContract.php -v
vendor/bin/pint --dirty --format agent
git add app/Support/Testing/TestLaneContract.php tests/TestCase.php tests/Feature/TestLaneGuardTest.php tests/Feature/EnvironmentGuardsTest.php
git commit -m "refactor(test-lane): extract the one-shape clause table to App\Support\Testing\TestLaneContract

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

Expected: phpstan 0 errors on the new file.

---

### Task 5: F4b — `db:test-lane-reset` command + fresh-worktree remedy

**Files:**
- Create: `app/Console/Commands/ResetTestLane.php`
- Test: `tests/Feature/TestLaneResetCommandTest.php` (new)

**Interfaces:**
- Consumes: `TestLaneContract::refusalFor/rawEnvDatabases/fingerprintPath` (Task 4).
- Produces: `ResetTestLane::foreignLaneConnections(string $database): int` and `ResetTestLane::dropStatements(string $database, array $tables): array` (public static, unit-tested).

**Measured facts this design stands on (probed 2026-08-10):** a second `fopen`+`flock(LOCK_EX|LOCK_NB)` on the run-lock **from the same process is denied** on this macOS, so the in-suite refusal test is deterministic; `information_schema.PROCESSLIST` without the PROCESS privilege shows the caller's **own user's** threads, and every suite connects as the lane user, so a concurrent suite in another worktree **is** visible to the probe.

- [ ] **Step 1: Write the failing tests**

`tests/Feature/TestLaneResetCommandTest.php`:

```php
<?php

use App\Console\Commands\ResetTestLane;

/*
 * The destructive happy path is structurally untestable in-suite: the suite
 * process itself holds the flock run-lock, so the command MUST refuse while
 * being tested — and that refusal is exactly what the first test pins. The
 * drop is covered by the pure statement generator plus one manual
 * end-to-end run recorded in the round report (spec F4).
 */

it("refuses while this tree's pest process holds the lane run-lock", function (): void {
    $this->artisan('db:test-lane-reset')
        ->expectsOutputToContain('run-lock')
        ->assertExitCode(1);
});

it('refuses a non-lane-shaped config before any probe or prompt', function (): void {
    config(['database.connections.mysql_testing.username' => 'root']);

    $this->artisan('db:test-lane-reset')
        ->expectsOutputToContain('root')
        ->assertExitCode(1);
});

it('sees a second live lane connection through the processlist probe', function (): void {
    $lane = config('database.connections.mysql_testing');
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%s;dbname=%s', $lane['host'], $lane['port'], $lane['database']),
        (string) $lane['username'],
        (string) $lane['password'],
    );

    try {
        expect(ResetTestLane::foreignLaneConnections((string) $lane['database']))->toBeGreaterThanOrEqual(1);
    } finally {
        $pdo = null;
    }
});

it('generates schema-qualified drop statements', function (): void {
    expect(ResetTestLane::dropStatements('podtext_test', ['alpha', 'beta']))->toBe([
        'DROP TABLE IF EXISTS `podtext_test`.`alpha`',
        'DROP TABLE IF EXISTS `podtext_test`.`beta`',
    ]);
});
```

- [ ] **Step 2: Run to verify failure**

```bash
php -d memory_limit=2G vendor/bin/pest tests/Feature/TestLaneResetCommandTest.php --compact
```

Expected: FAIL — `ResetTestLane` does not exist / command not registered.

- [ ] **Step 3: Implement the command**

`app/Console/Commands/ResetTestLane.php`:

```php
<?php

namespace App\Console\Commands;

use App\Support\Testing\TestLaneContract;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Reset the dedicated MySQL test lane to first-use state.
 *
 * A fresh worktree has no fingerprint file (gitignored), so the suite
 * refuses a populated lane as a stranger's database — fail-closed and
 * correct. This command is the sanctioned remedy: it empties the lane schema
 * and removes the fingerprint, so the next pest boot re-fingerprints an
 * empty schema and migrates fresh.
 *
 * Refusal layers, in order (spec F4):
 * - the extracted one-shape clause table — the same table the suite boots on;
 * - this tree's flock run-lock (a suite in THIS tree is mid-run; a second
 *   same-process fd is denied too, which is what the in-suite test pins);
 * - live lane connections via information_schema.PROCESSLIST — every suite
 *   connects as the lane user, and PROCESSLIST shows the caller's own user
 *   without the PROCESS privilege, so a suite running from ANOTHER worktree
 *   is visible here even though its flock file is not;
 * - the typed-name confirmation (unless --force);
 * - lock_wait_timeout=3 on the drop session, so a holder the probes missed
 *   fails the drop fast instead of hanging it.
 */
#[Signature('db:test-lane-reset {--force : Skip the typed confirmation}')]
#[Description('Empty the dedicated MySQL test lane and remove its fingerprint, so the next pest boot starts first-use clean.')]
class ResetTestLane extends Command
{
    public function handle(): int
    {
        $config = array_merge(config('database'), ['default' => 'mysql_testing']);
        $refusal = TestLaneContract::refusalFor($config, TestLaneContract::rawEnvDatabases());

        if ($refusal !== null) {
            $this->error('Refusing to reset: '.$refusal);

            return self::FAILURE;
        }

        $lane = $config['connections']['mysql_testing'];
        $database = (string) $lane['database'];
        $lockHandle = fopen(storage_path('framework/testing/mysql-lane-run.lock'), 'c+');

        if ($lockHandle === false || ! flock($lockHandle, LOCK_EX | LOCK_NB)) {
            $this->error('Refusing to reset: a pest run in this tree holds the MySQL lane run-lock.');

            return self::FAILURE;
        }

        try {
            $foreign = self::foreignLaneConnections($database);

            if ($foreign > 0) {
                $this->error("Refusing to reset: {$foreign} other connection(s) are live on `{$database}` — a suite may be running from another worktree.");

                return self::FAILURE;
            }

            if (! $this->option('force')) {
                $typed = $this->ask("This EMPTIES `{$database}` and deletes its fingerprint. Type the database name to continue");

                if ($typed !== $database) {
                    $this->warn('Aborted — the typed name did not match.');

                    return self::FAILURE;
                }
            }

            $connection = DB::connection('mysql_testing');
            $tables = array_map(
                static fn (object $row): string => (string) $row->name,
                $connection->select("SELECT TABLE_NAME name FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME", [$database]),
            );

            $connection->statement('SET SESSION lock_wait_timeout = 3');
            $connection->statement('SET FOREIGN_KEY_CHECKS = 0');

            try {
                foreach (self::dropStatements($database, $tables) as $statement) {
                    $connection->statement($statement);
                }
            } finally {
                $connection->statement('SET FOREIGN_KEY_CHECKS = 1');
            }

            $fingerprint = TestLaneContract::fingerprintPath((string) $lane['host'], (string) $lane['port'], $database);

            if (is_file($fingerprint)) {
                unlink($fingerprint);
            }

            $this->info(sprintf('Lane `%s` emptied (%d tables dropped) and fingerprint removed. The next pest boot re-fingerprints and migrates fresh.', $database, count($tables)));

            return self::SUCCESS;
        } finally {
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
        }
    }

    /** Lane connections other than this one — the cross-worktree suite probe. */
    public static function foreignLaneConnections(string $database): int
    {
        return (int) DB::connection('mysql_testing')
            ->selectOne('SELECT COUNT(*) n FROM information_schema.PROCESSLIST WHERE DB = ? AND ID <> CONNECTION_ID()', [$database])->n;
    }

    /**
     * @param  list<string>  $tables
     * @return list<string>
     */
    public static function dropStatements(string $database, array $tables): array
    {
        return array_map(
            static fn (string $table): string => sprintf('DROP TABLE IF EXISTS `%s`.`%s`', str_replace('`', '', $database), str_replace('`', '', $table)),
            $tables,
        );
    }
}
```

- [ ] **Step 4: Run the tests**

```bash
php -d memory_limit=2G vendor/bin/pest tests/Feature/TestLaneResetCommandTest.php --compact
```

Expected: PASS 4/4.

- [ ] **Step 5: phpstan + pint + commit**

```bash
php -d memory_limit=2G vendor/bin/phpstan analyse app/Console/Commands/ResetTestLane.php -v
vendor/bin/pint --dirty --format agent
git add app/Console/Commands/ResetTestLane.php tests/Feature/TestLaneResetCommandTest.php
git commit -m "feat(test-lane): db:test-lane-reset — the sanctioned fresh-worktree remedy, refusal-layered

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

- [ ] **Step 6: Manual end-to-end (AFTER the Task 7 full-gate suite run, never during)**

With no suite running anywhere (check with the other sessions first):

```bash
php artisan db:test-lane-reset
ls storage/framework/testing/mysql-lane/
php -d memory_limit=2G vendor/bin/pest tests/Feature/TestLaneGuardTest.php --compact
```

Expected: command prints the dropped-table count and removes the fingerprint (`ls` shows none for the lane identity); the pest run boots FIRST-USE (empty schema → new fingerprint → `migrate:fresh`) and passes 15/15. Record this run's output in the round report.

---

### Task 6: F6 — `db:restore` refuses TIMESTAMP-DDL dumps onto the pinned connection

**Files:**
- Modify: `app/Console/Commands/RestoreDatabase.php`
- Test: `tests/Feature/DatabaseSnapshotCommandsTest.php`

**Interfaces:**
- Produces: `RestoreDatabase::timestampDdlRefusal(string $path, ?string $connectionTimezone, bool $allowTimestampDump): ?string` (public static, pure over the file).
- Measured fact: the lane connection hardcodes `'timezone' => '+00:00'` in `config/database.php`, so the in-suite wiring test needs no config mutation — and the refusal fires BEFORE the typed confirmation, so the command-level test can never reach a real restore.

- [ ] **Step 1: Write the failing tests**

Add to `tests/Feature/DatabaseSnapshotCommandsTest.php` (top of file already imports the command classes; add `use Illuminate\Support\Facades\File;` if absent):

```php
/** A tiny gzipped dump fixture; $withTimestamp switches the one column type under test. */
function timestampProbeDump(bool $withTimestamp): string
{
    $column = $withTimestamp ? '`created_at` timestamp NULL DEFAULT NULL' : '`created_at` datetime NULL DEFAULT NULL';
    $sql = "-- fixture\nCREATE TABLE `probe` (\n  `id` bigint unsigned NOT NULL,\n  {$column},\n  PRIMARY KEY (`id`)\n);\n";
    File::ensureDirectoryExists(SnapshotDatabase::snapshotDirectory());
    $path = SnapshotDatabase::snapshotDirectory().'/fixture-timestamp-probe.sql.gz';
    file_put_contents($path, gzencode($sql));

    return $path;
}

it('classifies TIMESTAMP DDL against the pinned connection', function (bool $withTimestamp, ?string $timezone, bool $allow, bool $refused): void {
    $path = timestampProbeDump($withTimestamp);

    try {
        $refusal = RestoreDatabase::timestampDdlRefusal($path, $timezone, $allow);
        $refused ? expect($refusal)->toContain('TIMESTAMP') : expect($refusal)->toBeNull();
    } finally {
        @unlink($path);
    }
})->with([
    'timestamp + pinned = refused' => [true, '+00:00', false, true],
    'timestamp + unpinned = allowed' => [true, null, false, false],
    'timestamp + pinned + flag = allowed' => [true, '+00:00', true, false],
    'datetime + pinned = allowed' => [false, '+00:00', false, false],
]);

it('refuses to restore a TIMESTAMP dump through the command before any confirmation', function (): void {
    timestampProbeDump(true);

    try {
        $this->artisan('db:restore', ['file' => 'fixture-timestamp-probe.sql.gz'])
            ->expectsOutputToContain('TIMESTAMP')
            ->assertExitCode(1);
    } finally {
        @unlink(SnapshotDatabase::snapshotDirectory().'/fixture-timestamp-probe.sql.gz');
    }
});
```

- [ ] **Step 2: Run to verify failure**

```bash
php -d memory_limit=2G vendor/bin/pest tests/Feature/DatabaseSnapshotCommandsTest.php --compact --filter='TIMESTAMP'
```

Expected: FAIL — `timestampDdlRefusal` does not exist; the command test reaches the typed-confirmation prompt instead of refusing (it must never get there once implemented).

- [ ] **Step 3: Implement**

In `app/Console/Commands/RestoreDatabase.php`:

(a) Signature gains the flag (matching `--allow-utc-dump`'s house pattern):

```php
#[Signature('db:restore {file? : Snapshot filename or path; omit to list} {--latest : Restore the newest snapshot} {--allow-utc-dump : Permit a --tz-utc dump} {--allow-timestamp-dump : Permit TIMESTAMP-column DDL onto a +00:00-pinned connection} {--force : Skip the typed confirmation}')]
```

(b) Class docblock gains a third trap bullet after the B2 one:

```
 * - A dump defining TIMESTAMP columns restored onto the +00:00-pinned
 *   connection, then replayed through the alignment migration, would
 *   materialize shifted literals the oracle cannot catch (state-doc
 *   snapshot-restore caveat). Refused unless --allow-timestamp-dump.
```

(c) In `handle()`, immediately after the `$database = (string) $config['database'];` line:

```php
        $timezone = $config['timezone'] ?? null;
        $timestampRefusal = self::timestampDdlRefusal($path, is_string($timezone) ? $timezone : null, (bool) $this->option('allow-timestamp-dump'));

        if ($timestampRefusal !== null) {
            $this->error($timestampRefusal);

            return self::FAILURE;
        }
```

(d) New method after `contentRefusal()` — this one streams the WHOLE file, because CREATE TABLE statements live throughout the dump, not in the 64 KB header:

```php
    /**
     * Scan the entire decompressed stream for TIMESTAMP column DDL. Unlike
     * contentRefusal()'s header traps, CREATE TABLE statements appear
     * throughout the dump, so this reads to EOF — chunked, with a carry for
     * lines split across chunk boundaries.
     */
    public static function timestampDdlRefusal(string $path, ?string $connectionTimezone, bool $allowTimestampDump): ?string
    {
        if ($allowTimestampDump || $connectionTimezone !== '+00:00') {
            return null;
        }

        $handle = gzopen($path, 'rb');

        if ($handle === false) {
            return "Could not open {$path} as a gzip stream.";
        }

        $pattern = '/^\s*`[^`]+`\s+timestamp[\s(]/mi';
        $carry = '';
        $found = false;

        while (! gzeof($handle)) {
            $chunk = $carry.(string) gzread($handle, 65536);
            $lastNewline = strrpos($chunk, "\n");
            [$scannable, $carry] = $lastNewline === false
                ? ['', $chunk]
                : [substr($chunk, 0, $lastNewline + 1), substr($chunk, $lastNewline + 1)];

            if ($scannable !== '' && preg_match($pattern, $scannable) === 1) {
                $found = true;

                break;
            }
        }

        if (! $found && $carry !== '' && preg_match($pattern, $carry) === 1) {
            $found = true;
        }

        gzclose($handle);

        return $found
            ? 'Refused: the dump defines TIMESTAMP columns while the target connection pins +00:00 — replaying it (and the alignment migration) would materialize shifted literals the oracle cannot catch. Restore with the pin temporarily removed, onto an unpinned connection, or pass --allow-timestamp-dump only if that is understood.'
            : null;
    }
```

- [ ] **Step 4: Run the new tests, then the whole command test file**

```bash
php -d memory_limit=2G vendor/bin/pest tests/Feature/DatabaseSnapshotCommandsTest.php --compact
```

Expected: PASS (existing refusal/round-trip tests untouched and green; 5 new passing).

- [ ] **Step 5: phpstan + pint + commit**

```bash
php -d memory_limit=2G vendor/bin/phpstan analyse app/Console/Commands/RestoreDatabase.php -v
vendor/bin/pint --dirty --format agent
git add app/Console/Commands/RestoreDatabase.php tests/Feature/DatabaseSnapshotCommandsTest.php
git commit -m "feat(db): db:restore refuses TIMESTAMP-DDL dumps onto the pinned connection — the snapshot-replay caveat, operationalized

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 7: Part 1 close — full gate, manual reset verification, state docs

**Files:**
- Modify: `docs/phase-02/open-findings-triage.md` (§F status flips)
- Modify: `docs/phase-02/current-project-state.md` (follow-ups block)

- [ ] **Step 1: Full gate on the final Part-1 tree**

```bash
php -d memory_limit=2G vendor/bin/pest --compact
vendor/bin/pint --dirty --format agent
composer filacheck
```

Expected: pest ~1,964 passing (1,953 + 1 from Task 1 + 1 from Task 2 + 4 from Task 5 + 5 from Task 6), pint passed, FilaCheck 35/35. If the known `CardTemplatePreviewBrowserTest:663` single-read flake fires, re-run that file in isolation and record both results honestly — do not silently re-roll the full suite.

- [ ] **Step 2: Run Task 5 Step 6 (manual `db:test-lane-reset` end-to-end) now**, record output.

- [ ] **Step 3: Update the docs (scope enlarged 2026-08-10 by the verified cross-session sweep + operator broadening)**

In `open-findings-triage.md`: §F mark F1–F6 `FIXED <date>` with their commit SHAs (keep each entry's one-line description); F7 stays "next deploy window"; F8–F10 unchanged; flip §C2 to `FIXED` by `005eda6` (both enums moved; guarded by `EnvironmentGuardsTest`'s declares-every-enum test); add **§F11** registering the sqlite residuals (fixed by Task 7B — cite its commit) and the config-default question as DP9. In `current-project-state.md`, extend the "Post-program follow-ups" block: `db:test-lane-reset` is the now-landed remedy (name it), and the `db:restore` TIMESTAMP refusal replaces "approved as a ride-along hardening". In `app/Console/Commands/CheckDatabaseSettings.php`, replace the stale docblock sentence "The suite runs on sqlite, which has no charset, no collation and no session timezone, so this whole class of setting is structurally invisible to `php artisan test`." with: "The suite now runs on the dedicated MySQL lane, so these settings are testable there — this command remains the production-side drift alarm." In `docs/research/defect-cause-patterns.md`: close `driver-lenient-fallback` (the line "**Status:** open. Registered at discovery; no lane built yet." becomes "**Status:** closed 2026-08-09 — the MySQL lane is built and is the suite's only driver; the one-shape guard learned the second shape (alignment Phase 4).") and close `db-clock-coupling`'s status line the same way (the alignment migration ran; the connection pins `+00:00` hardcoded — note the env-var proposal was deliberately not taken).

- [ ] **Step 4: Commit**

```bash
git add docs/phase-02/open-findings-triage.md docs/phase-02/current-project-state.md app/Console/Commands/CheckDatabaseSettings.php docs/research/defect-cause-patterns.md
git commit -m "docs: Part 1 close — F-ledger flips, C2/F11, stale sqlite-era prose retired

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

🛑 **Report to the operator** (commits list, gate record, manual-reset transcript). Push only if they say so.

---

### Task 7B: SQLite residual removal (operator scope addition, 2026-08-10)

**Files:**
- Delete: `database/database.sqlite` (528 KB, mtime Jul 10 — nothing references it as a test target)
- Modify: `composer.json` (the `post-create-project-cmd` line that recreates it)

**Deliberately NOT touched** (keep-forever, consumed by `tests/TestCase.php`'s `:memory:` containment, `NonMysqlRefusalTest`, and `TestLaneResetCommandTest`): the `sqlite` connection block in `config/database.php`. The `DB_CONNECTION` default of `sqlite` at `config/database.php:20` is **DP9** — recommended flip to `mysql` (a missing env key should fail loudly against a credentialed daemon, not silently open a file), decided at the R gate, not here.

- [ ] **Step 1: Prove the file is unreferenced and untracked**

```bash
git check-ignore database/database.sqlite && echo IGNORED
grep -rn "database.sqlite" app config tests composer.json phpunit.xml | grep -v "config/database.php"
```

Expected: `IGNORED`; grep shows ONLY the `composer.json` post-create line (the `config/database.php` sqlite-block reference is excluded and stays).

- [ ] **Step 2: Remove**

```bash
rm database/database.sqlite
```

Then edit `composer.json`'s `post-create-project-cmd` array: delete the line containing `file_exists('database/database.sqlite') || touch('database/database.sqlite');` (keep the array's other entries untouched; remove a dangling comma if one results).

- [ ] **Step 3: Verify**

```bash
composer validate --no-check-all
php -d memory_limit=2G vendor/bin/pest tests/Feature/NonMysqlRefusalTest.php tests/Feature/TestLaneGuardTest.php --compact
```

Expected: composer.json valid; 20/20 passing (5 + 15) — the `:memory:` containment consumers are unaffected by the file's absence.

- [ ] **Step 4: Commit**

```bash
git add composer.json
git commit -m "chore: retire the sqlite artifact — the file is gone and create-project stops recreating it

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

## Part 2 — staged rethink

### Task 8: Phase R — measurements and inventories (no app-code changes)

**Files:**
- Modify: `docs/research/test-suite-rethink-notes.md` (append `## Phase R measurements`)
- Scratch only otherwise (use the session scratchpad; nothing lands in the repo but the notes).

- [ ] **Step 1 (R1): per-file duration profile**

```bash
php -d memory_limit=2G vendor/bin/pest --compact --log-junit storage/framework/testing/junit-profile.xml
php -r '
$xml = simplexml_load_file("storage/framework/testing/junit-profile.xml");
$files = [];
foreach ($xml->xpath("//testcase") as $case) {
    $file = (string) ($case["file"] ?? ($case["classname"] ?? "unknown"));
    $files[$file] = ($files[$file] ?? 0) + (float) $case["time"];
}
arsort($files);
$total = array_sum($files);
printf("total %.1fs across %d files\n", $total, count($files));
foreach (array_slice($files, 0, 20, true) as $f => $t) { printf("%7.1fs  %s\n", $t, $f); }
$browser = array_sum(array_filter($files, fn ($k) => str_contains($k, "Browser"), ARRAY_FILTER_USE_KEY));
printf("browser share: %.1fs (%.0f%%)\n", $browser, $browser / $total * 100);
'
```

Record: top-20 table, browser share, feature share. Delete the XML afterwards.

- [ ] **Step 2 (R2): boot + migrate share**

```bash
time php -d memory_limit=2G vendor/bin/pest tests/Feature/EnvironmentGuardsTest.php --compact
```

The first-boot `migrate:fresh` dominates this tiny file's wall time; record it as the per-process migration cost and compare against R1's total.

- [ ] **Step 3 (R3): guard-query cost on the idle lane** (decides F9's permanent label)

```bash
php artisan tinker --execute '
$q = fn () => DB::connection("mysql_testing")->selectOne("SELECT COUNT(*) n FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND DATA_TYPE = \"timestamp\"", ["podtext_test"])->n;
$q();
$t = hrtime(true);
for ($i = 0; $i < 1000; $i++) { $q(); }
$ms = (hrtime(true) - $t) / 1000 / 1e6;
printf("%.3f ms/query -> ~%.1f s per suite at 1900 boots\n", $ms, $ms * 1900 / 1000);
'
```

- [ ] **Step 4 (R4): browser trap inventory** — list every wait/settle/Alpine-probe pattern in `tests/Browser/` (grep `waitFor|settle|_x_dataStack|scrollWidth|getBoundingClientRect`), note which are single-reads vs condition-waits (the `CardTemplatePreviewBrowserTest:663` specimen included), and read the `pest-plugin-browser` 5.x changelog (WebFetch `https://github.com/pestphp/pest-plugin-browser/releases`) against that list. Output: a table in the notes — Phase U's shakedown checklist.

- [ ] **Step 5 (R5): RefreshDatabase opt-out classification** — for each of the 33 Feature + 8 Unit files without `RefreshDatabase`:

```bash
grep -rL "RefreshDatabase" tests/Feature tests/Unit --include='*Test.php'
```

Classify each: (a) genuinely DB-free, (b) manual state management (DDL/finally patterns), (c) accidental omission that writes rows. Category (c) items become Phase S work.

- [ ] **Step 6 (R6): PHPUnit 13 changelog risk memo** — read `https://github.com/sebastianbergmann/phpunit/blob/13.0.0/ChangeLog-13.0.md` (WebFetch), list every entry that touches anything this suite uses (data providers, TestCase hooks, JUnit output, process isolation, deprecations FilaCheck/blueprint might trip). Output: risk table with a verdict per entry.

- [ ] **Step 7 (R7): parallelization probes (no runner switch, no grant changes)** —
  (a) record `SHOW GRANTS` as the lane user: `php artisan tinker --execute 'collect(DB::connection("mysql_testing")->select("SHOW GRANTS"))->each(fn ($r) => print_r($r));'`;
  (b) confirm the worker-name fit: Laravel parallel workers get `podtext_test_test_{n}` — assert it against the clause regex in a one-liner: `php -r 'var_export(preg_match("/^[a-z][a-z0-9_]*_test(_[0-9]+)?$/", "podtext_test_test_1"));'` (expect 1);
  (c) document the flock conflict: each worker includes `tests/Pest.php`, so worker 2 dies on the run-lock today — the Phase S design must make the lock worker-aware (skip when `TEST_TOKEN` is a paratest token, parent-level lane lock instead — ties into DP7);
  (d) estimate memory: N workers × the 2G limit vs machine RAM.

- [ ] **Step 8 (R8): DATETIME tie-sensitivity sweep** — list order-sensitive assertions without tie-breaks: grep tests for `defaultSort|orderBy.*desc|assertCanSeeTableRecords` near fixtures created in loops without `travel()`. Output: file list with a flake-risk verdict each (the `ec47df7` pattern is the fix template).

- [ ] **Step 9 (R9): CI feasibility decision-support (operator broadening, 2026-08-10)** — no pipeline file is written in R; this step produces the decision material for **DP-CI**:
  (a) map every clause of the one-shape table against a GitHub Actions `mysql:8.0.46` service container: host `127.0.0.1` ✓/✗, mapped explicit port, database `podtext_test` + non-root user via init script, name-collision clause vs a runner-checkout `.env.example` — determine whether CI satisfies the guard **without weakening a single clause**;
  (b) inventory the runner prerequisites the suite now carries: `mysqldump` + `gzip` on PATH, Chrome/browser deps for `tests/Browser`, the 2G memory limit, the fingerprint first-use flow on a always-fresh runner (works by construction: fresh runner = empty schema);
  (c) estimate wall time (~10 min single-threaded today) and note what sharding would later buy;
  (d) recommendation table: implement-in-S / defer-post-U / record local-only-decision, with one-line rationale each.

- [ ] **Step 10 (R10): deferred-items re-assessment (operator broadening, 2026-08-10)** — one consolidated table; every row gets `fix-in-S` or `keep-deferred (why)`. Rows, at minimum: every task-review Minor held in the progress ledger (Tasks 1–6 — contract comment staleness, `dropStatements` backtick escaping, views-survive-reset, unlink result, fopen-message conflation, fixture-in-real-snapshot-dir, describe-block placement, `LANE` const, `+00:00` literal coupling, handle() guard extraction); F8 (`possible_keys`) and F9 (per-boot COLUMNS count — pair with R3's measurement); DP9 (config `DB_CONNECTION` default `sqlite` → `mysql`); the `CardTemplatePreviewBrowserTest:663` single-read flake fix plus R4's sibling single-reads; `defect-cause-patterns.md` entries that still touch the suite. The operator's R-gate call turns this table into Phase S's checklist.

- [ ] **Step 11: Append the measurement report** to `docs/research/test-suite-rethink-notes.md` under `## Phase R measurements (2026-08-DD)`, with DP1–DP3, DP-CI, and DP9 pre-filled as recommendations and the R10 table included. Commit:

```bash
git add docs/research/test-suite-rethink-notes.md
git commit -m "docs(rethink): Phase R measurement report

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

🛑 **GATE: operator reads the report, sets Phase S scope from the R10 table, answers DP1–DP3, DP-CI, and DP9.**

---

### Task 9: Phase T — Rector introduction, wired to larastan

**Files:**
- Modify: `composer.json` (require-dev + scripts)
- Create: `rector.php`
- Test: `tests/Feature/RectorScriptContractTest.php` (new)
- Create: `docs/research/rector-dry-run-reports/` (first report lands here)

**Interfaces:**
- Produces: `composer rector` (dry-run-only), `composer rector:fix` (approval-gated), `rector.php` reading `phpstan.neon` so type rules see larastan's cast/generics knowledge.

- [ ] **Step 1: Install (introduction approved 2026-08-10; this is the one sanctioned dependency change)**

```bash
composer require --dev rector/rector:^2.6 driftingly/rector-laravel:^2.5
```

Expected: clean resolution (no constraint conflicts are known against this tree). If composer reports any, STOP and surface them — do not force.

- [ ] **Step 2: Write the failing contract test**

`tests/Feature/RectorScriptContractTest.php`:

```php
<?php

use Illuminate\Support\Facades\File;

/*
 * Rector writes to source files — FilaCheck's hazard class. The composer
 * script is the only sanctioned entry point, and it must stay dry-run-only;
 * writing goes through rector:fix, which needs explicit operator approval
 * (same contract FilacheckAgentModeGuardTest pins for FilaCheck).
 */

it('keeps composer rector dry-run-only and the write path separate', function (): void {
    $scripts = json_decode(File::get(base_path('composer.json')), true, 512, JSON_THROW_ON_ERROR)['scripts'];

    expect(implode(' ', (array) ($scripts['rector'] ?? [])))->toContain('--dry-run')
        ->and($scripts)->toHaveKey('rector:fix')
        ->and(implode(' ', (array) ($scripts['rector:fix'] ?? [])))->not->toContain('--dry-run');
});

it('keeps rector wired to larastan through phpstan.neon', function (): void {
    expect(File::get(base_path('rector.php')))
        // corrected after rectorphp#8006/#8141 — Rector skips extension-installer
        ->toContain("withPHPStanConfigs([__DIR__.'/phpstan.neon', __DIR__.'/vendor/larastan/larastan/extension.neon'])");
});
```

- [ ] **Step 3: Run to verify failure** (`rector.php` missing, scripts missing):

```bash
php -d memory_limit=2G vendor/bin/pest tests/Feature/RectorScriptContractTest.php --compact
```

- [ ] **Step 4: Create `rector.php` (zero rules — safe-by-default proven first)**

```php
<?php

use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([__DIR__.'/app', __DIR__.'/database', __DIR__.'/routes'])
    ->withPHPStanConfigs([__DIR__.'/phpstan.neon', __DIR__.'/vendor/larastan/larastan/extension.neon']) // corrected after rectorphp#8006/#8141 — Rector skips extension-installer
    ->withCache(__DIR__.'/storage/framework/cache/rector');
```

(tests/ deliberately absent — the Pest coding-style rules are `pest-plugin-rector`, a Pest-5-line package, Phase U.)

- [ ] **Step 5: Add the composer scripts** to the `"scripts"` block of `composer.json`:

```json
        "rector": [
            "vendor/bin/rector process --dry-run --ansi"
        ],
        "rector:fix": [
            "vendor/bin/rector process --ansi"
        ],
```

- [ ] **Step 6: Prove the safe-by-default state**

```bash
composer rector
```

Expected: Rector runs, finds the paths, and reports **no changes** with a warning that no rules/sets are registered — record the exact output in the dry-run report. Then run the contract test: PASS 2/2.

- [ ] **Step 7: First dry-run report — one set, `LaravelSetList::LARAVEL_CODE_QUALITY`**

Add `use RectorLaravel\Set\LaravelSetList;` at the top of `rector.php` and insert the set call into the fluent chain before the closing semicolon, so the file ends:

```php
    ->withPHPStanConfigs([__DIR__.'/phpstan.neon', __DIR__.'/vendor/larastan/larastan/extension.neon']) // corrected after rectorphp#8006/#8141 — Rector skips extension-installer
    ->withCache(__DIR__.'/storage/framework/cache/rector')
    ->withSets([LaravelSetList::LARAVEL_CODE_QUALITY]);
```

```bash
composer rector > storage/framework/testing/rector-laravel-code-quality.txt 2>&1; tail -5 storage/framework/testing/rector-laravel-code-quality.txt
```

(The capture file is gitignored scratch; the report document quotes from it, then it is deleted.)

Write `docs/research/rector-dry-run-reports/2026-08-DD-laravel-code-quality.md`: the full diff output, then a per-rule verdict table (rule → files touched → adopt / reject / defer → one-line why; anything touching Filament fluent chains defaults to reject — Rector cannot see Filament's Macroable any better than larastan). The set STAYS in `rector.php` — `composer rector` is dry-run-locked and `rector:fix` is approval-gated, so a registered set is inert until DP4 approval.

- [ ] **Step 8: Full gate + commit**

```bash
php -d memory_limit=2G vendor/bin/pest --compact
vendor/bin/pint --dirty --format agent
composer filacheck
git add composer.json composer.lock rector.php tests/Feature/RectorScriptContractTest.php docs/research/rector-dry-run-reports/
git commit -m "feat(tooling): introduce Rector wired to larastan — dry-run-locked, first set reported

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

🛑 **GATE (DP4): each write pass is its own operator approval.** Procedure per approved pass: clear the rector cache first (`composer rector -- --clear-cache` — a warm cache under-reports, measured in the 2026-08-10 dry-run report) — and note `rector.php` pins serial mode (`withoutParallel`); never measure or fix through parallel mode (nondeterministic, measured 2026-08-10, see the dry-run report's §0c) → narrow `rector.php` to the approved rules (`->withRules([...])` replacing the set for that run if only part is approved, keeping `->withoutParallel()` in the narrowed chain) → `composer rector:fix` → `git diff` review → `php -d memory_limit=2G vendor/bin/phpstan analyse -v` (no new errors vs the 443 baseline count) → pint → targeted pest → full gate → commit → restore `rector.php` to its committed shape if it was narrowed.

---

### Task 10: Phase S — structure fixes (blocked on the Task 8 gate)

Candidates already identified — the R gate prices and picks; each selected item then gets its own task addendum to this plan (same TDD shape as Part 1) before implementation:

- §D3 guard consolidation with named failures, building on `TestLaneContract` (Task 4 seeded it).
- Run-lock relocation to a lane-keyed path (DP7) — closes the cross-worktree gap; must co-design with R7(c)'s worker-aware lock so parallel doesn't re-break it.
- Unit-suite bypass closure: an arch rule that `tests/Unit` never touches DB facades, or guard binding.
- R8's tie-break fixes (the `ec47df7` `travel()` template).
- R5 category-(c) RefreshDatabase corrections.
- The `CardTemplatePreviewBrowserTest:663` single-read → condition-wait fix, plus siblings from R4.
- Guard trims only if R3 registered against the ~600s wall.
- **CI pipeline implementation per DP-CI** (workflow file + `mysql:8.0.46` service container + lane bootstrap; the one-shape clause table itself does not change — operator broadening 2026-08-10).
- **Every R10 row the operator verdicts `fix-in-S`**, including DP9's config-default flip if approved.

**Entry condition:** operator has read the Phase R report and named the scope. Do not start any candidate before that. Per the 2026-08-10 broadening, Phase S completes — suite "working straight" — before Phase U opens.

**SATISFIED 2026-08-10 at the R gate: full scope (all four buckets) selected. The concrete addenda follow as Tasks S4→S5→S1→S2→S3 (execution order: quick wins first, structure last).**

---

### Task S4: opportunistic one-liners (batch)

**Files:** `app/Console/Commands/ResetTestLane.php` · `app/Auth/LegacyRoleBackfill/LegacyRoleBackfillSchemaContract.php` · `tests/Feature/TestLaneResetCommandTest.php`

One commit, all mechanical, each reviewer-prescribed earlier:
- `ResetTestLane`: `private const LANE = 'mysql_testing';` replacing the three literals (`:38`, `:76`, `:111`); split the `fopen === false` case from the flock-denied case with its own message (`Could not open the run-lock file — check storage/framework/testing exists and is writable.`); check `unlink()`'s result (failure → `$this->error(...)` + `self::FAILURE`); `dropStatements()` doubles backticks (`str_replace('`', '``', ...)`) instead of stripping.
- Contract `:108`: drop the stale trailing sentence `Same value on both drivers.` (keep the rest of the comment).
- `TestLaneResetCommandTest`: update the drop-statements unit expectation if the escaping change alters output for plain names (it must not — plain names contain no backticks; add one dataset case with an embedded backtick proving the doubling).
- Gate: `php -d memory_limit=2G vendor/bin/pest tests/Feature/TestLaneResetCommandTest.php tests/Feature/AuthzLegacyRoleBackfillTest.php --compact` green; phpstan on both app files (equal-or-lower); pint; commit `fix(test-lane): review one-liners — LANE const, honest failure messages, backtick doubling, stale comment`.

### Task S5: DP9 — the default connection flip

**Files:** `config/database.php:20` · `.env.example` (the sqlite warning prose near `:30`)

- `'default' => env('DB_CONNECTION', 'sqlite'),` → `'default' => env('DB_CONNECTION', 'mysql'),`
- `.env.example`: the warning that explained the sqlite fallback now states the mysql fallback (missing `DB_CONNECTION` fails loudly against a credentialed daemon instead of opening a file).
- Proof: full-suite unaffected structurally (tests force `mysql_testing` before config is ever read) — run `php -d memory_limit=2G vendor/bin/pest tests/Feature/EnvironmentGuardsTest.php tests/Feature/TestLaneGuardTest.php --compact` as the fast sanity pair + `php artisan config:show database.default` in the dev context (expect `mysql`, driven by the real `.env`).
- Commit: `chore(config): DP9 — a missing DB_CONNECTION now fails loudly on mysql, the last sqlite-shaped default retired`.

### Task S1: browser single-read family → stable reads

**Files:** `tests/Browser/CardTemplatePreviewBrowserTest.php` (28 of the 54 occurrences) + the three sibling files R4 counted (`grep -rln "horizontal_overflow" tests/Browser` is the authoritative list)

- Mechanism (pinned): inside each measurement's JS evaluation block, replace every single-read layout assertion input (`document.documentElement.scrollWidth > clientWidth + 1`, bare `getBoundingClientRect()` deltas feeding `full_bleed`) with a stable-read helper embedded in the same script: read the value on two consecutive `requestAnimationFrame` ticks (await a Promise wrapping `requestAnimationFrame`) until two consecutive reads agree, capped at 10 frames, then use the agreed value. One helper function per evaluation block (the blocks are self-contained strings — no shared JS file; keep the helper identical across blocks, comment-tagged `// stable-read (R4)` for future greps).
- TDD shape: this is flake-hardening of existing green tests — the red/green cycle is replaced by (a) before: run `CardTemplatePreviewBrowserTest` 3× consecutively green (baseline), (b) after the edit: 3× consecutively green again, (c) mutation check: temporarily force the helper to return the FIRST read (defeating stability) — tests must still pass on an idle machine (proves the helper is transparent), revert; the real proof is the disappearance of the contention flake over the coming full-gate runs — say exactly that in the report, no stronger claim.
- Scope discipline: assertions themselves (thresholds, expected values) do not change; only how the measured inputs are read.
- Gate: the touched browser files 3× green; pint; commit `test(browser): stable reads for layout measurements — the single-read contention flake class retired`.

### Task S2: the 148.7s file — diagnose, then fix what the diagnosis licenses

**Files:** `tests/Feature/PublicMaintenanceModeTest.php` (18 tests) + whatever the diagnosis names (report-first task)

- Step 1 (measure): `php -d memory_limit=2G vendor/bin/pest tests/Feature/PublicMaintenanceModeTest.php --compact --log-junit storage/framework/testing/junit-mm.xml`, aggregate per-test times; identify whether the cost is flat (~8s × 18 — boot/fixture-shaped) or concentrated (a few tests dominate).
- Step 2 (root-cause): read the file + the code paths its slowest tests exercise; name the mechanism with evidence (e.g. repeated settings rebuilds, full public-page renders per assertion, an unfaked external boundary, redundant migrations). NO fix before the mechanism is named in writing.
- Step 3 (fix only what the diagnosis licenses): mechanical, behavior-preserving speedups (shared fixtures via beforeEach consolidation, faking an unfaked boundary, removing redundant renders) — implement with before/after per-test timings; anything structural (splitting the file, changing what's asserted) is REPORTED as a proposal instead, not done.
- Gate: file green + measured delta in the report; full-suite wall-time re-measured at the Phase S close, not per-task; pint; commit `test(maintenance): <mechanism> — <before>s → <after>s`.

### Task S1b: adjacent single-read booleans → stable reads (operator-approved 2026-08-10, "pull S1b into Phase S after S2")

**Files:** the same four browser files S1 touched — the UNCONVERTED single-read layout booleans S1's implementer disclosed and deliberately left (`modal_within_viewport`, `preview_is_logical_end`, `columns_do_not_overlap`, `opener_hidden`/`opener_visible`, `status_one_row`, `header/footer_visible_after_scroll`, `every_card_within_viewport`, `review_within_viewport`, and siblings — grep-inventory first, count before/after).

Same pinned mechanism as S1 (`stableRead`, byte-identical helper text, tag `// stable-read (R4)`); same HARD boundary (no assertion/threshold/expected-value changes — BLOCKED if one would need to move); same verification shape (primary file 3× green before/after, mutation transparency check, siblings 1× each); note S1's reviewer finding #2 applies here too — the guarantee is boolean-granularity flicker retirement, claim nothing stronger. Commit: `test(browser): stable reads for the remaining layout booleans — S1's disclosed remainder`.

### Task S2b: the settings-save subprocess tax — sweep the siblings

**Files:** every test file that saves settings without the fake (inventory first): `grep -rl "PublicContentSettings" tests/Feature | xargs grep -L "fakeSettingsBackupSnapshotQueue"` (refine to files that actually `->save()`); S2 measured the mechanism (each save = SettingsSaved → backup manager → SettingsBackupSnapshotJob → real `node` subprocess ×2 under the sync queue).

- Add `fakeSettingsBackupSnapshotQueue()` to each hit's `beforeEach` (the established 14-file convention); files whose tests ASSERT on the snapshot job/backup behavior are exempt — list them explicitly in the report instead.
- Per-file before/after timing (junit or `--compact` duration) in the report; expect material wins only on save-heavy files.
- Gate: each touched file green; pint; one commit `test(settings): fake the snapshot job where saves never assert on it — the S2 tax, swept`.

### Task S3: D3 consolidation + DP7 — machine-global lane lock and fingerprint

**Files:** `app/Support/Testing/TestLaneContract.php` · `tests/Pest.php` · `tests/TestCase.php` · `app/Console/Commands/ResetTestLane.php` · `tests/Feature/TestLaneGuardTest.php` / `tests/Feature/TestLaneResetCommandTest.php` / `tests/Feature/EnvironmentGuardsTest.php` (follow the code)

- Design (pinned at the R gate): the run-lock and the fingerprint both become **machine-global, lane-identity-keyed** paths owned by `TestLaneContract`: `runLockPath(host, port, database)` and `fingerprintPath(...)` (existing) both move under `sys_get_temp_dir().'/podtext-test-lane/'` with the same `sha1(host|port|database)` naming. Consequences, each pinned by a test: (a) two worktrees can no longer both hold "the" lock — the cross-worktree gap closes for good; (b) a fresh worktree inherits the machine's fingerprint and stops hard-refusing a lane the machine already owns (the F4 pain solved at the root — `db:test-lane-reset` remains the remedy for a genuinely foreign lane); (c) `ResetTestLane`'s flock probe now guards cross-worktree too (its PROCESSLIST layer stays as belt); (d) `tests/Pest.php`'s lock acquisition switches to the new path — **the `$GLOBALS` lifetime persistence stays exactly as is** (the GC trap does not move).
- D3 half: `TestCase::refreshApplication()`'s guard sequence gets named-failure messages consolidated behind one entry call (`TestLaneContract::assertSafeBoot(...)` orchestrating refusalFor → fingerprint → TIMESTAMP check), so the next reader finds ONE guard entrypoint; `EnvironmentGuardsTest`'s diagnosis tests follow.
- Migration note: on first run after this lands, the old per-tree fingerprint is simply ignored (stale file in storage/framework/testing/mysql-lane/ — delete opportunistically); the new location starts empty → first boot re-fingerprints against the populated lane... **which would hard-refuse**. The task therefore ships a one-time bridge: if the machine-global fingerprint is absent but the legacy per-tree one exists for the same identity, adopt it (write the new file, log one line). Pinned by a test with a faked legacy file.
- Gate: full guard-test set green; a two-process probe mirroring `final-review-fixes.md` §1 proving the lock holds across TREES (second flock attempt from a different cwd fails while a suite runs — scriptable with the fixture pattern, 20s sleep margin); phpstan; pint; **full suite** (this touches the boot path); commit `refactor(test-lane): machine-global lane lock + fingerprint — the cross-worktree gap closes (D3/DP7)`.

---

---

### Task 11: Phase U — Pest 5 + plugins — **EXECUTED 2026-08-12** by the dedicated Phase U session (`5eaa7bb`/`8617bb2`/`bf605db`; plan `docs/phase-02/pest5-upgrade-implementation-plan.md`, record in `test-suite-rethink-notes.md` § Phase U record)

Operator's standing instruction (2026-08-10): *"Pest 5 + plugins only at the end and maybe in a separate session — wait for approval."* Checklist for that day, prepared here so the session can start cold:

- Composer moves: `pestphp/pest ^5.0`, `pestphp/pest-plugin-browser ^5.0`, `pestphp/pest-plugin-laravel ^5.0`, `pestphp/pest-plugin-drift ^5.0`; new dev deps `pestphp/pest-plugin-phpstan`, `pestphp/pest-plugin-rector`. Dry-run first: `composer update --dry-run pestphp/*` and surface every constraint hit (filament/blueprint, FilaCheck's pest-adjacent checks, spatie test helpers).
- **Re-prove the run-lock lifetime before anything else**: the `$GLOBALS` persistence fix is bootstrapper-shape-dependent (`BootFiles::load()` method scope). Re-run the mid-run probe from `.superpowers/sdd/final-review-fixes.md` §1 verbatim, with the sleep raised to 20s and a filter whose suite runs well past it. A silent lock regression here un-protects the lane for every future concurrent run.
- Browser shakedown against R4's trap inventory; failures follow the stash-baseline attribution protocol.
- `pest-plugin-phpstan`: explicit include in `phpstan.neon` (check whether `phpstan/extension-installer` auto-registers it first), add `tests/` to paths in a THROWAWAY config copy, re-measure the level-6 estimate (~426 documented), report → DP5.
- `pest-plugin-rector`: add the Pest coding-style set to `rector.php` `withPaths + tests/`, dry-run report only (same DP4 write gate).
- TIA: blocked on DP3 (no coverage driver exists on Herd PHP 8.4.23 — measured); if PCOV is approved and installed, record baseline semantics with two-sessions-one-worktree explicitly tested before anyone trusts it.
- Full gate + `LarastanCastResolutionGuardTest` + `FilacheckAgentModeGuardTest` + `RectorScriptContractTest` all green; state-doc update; report.

---

## Execution notes

- Tasks 1–6 are independent of each other except Task 5's dependency on Task 4. Execute in order anyway — each is a separate commit and a separate review gate.
- Task 8 must not run while any other session is using the lane (its Step 1 is a full suite run; check `mcp` session list / ask, per the shared-tree memory).
- Tasks 10 and 11 are gate-blocked outlines by design: no checkboxes inside them may be executed until their gates open.
