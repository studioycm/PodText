<?php

use App\Enums\TranscriptionMode;
use App\Enums\UserRole;
use App\Filament\Imports\ContentItemImporter;
use App\Jobs\SettingsBackupSnapshotJob;
use App\Models\Media;
use App\Models\User;
use App\Settings\AdminUxSettings;
use App\Support\Testing\TestLaneContract;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\LaravelSettings\SettingsContainer;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

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

/*
 * One shared lane schema; concurrent pest runs would migrate:fresh over each
 * other (SQLite :memory: made this impossible by construction — the lane does
 * not). flock, held for the process lifetime; fail fast, never queue.
 *
 * Machine-global, lane-identity-keyed (D3/DP7), HOME-anchored not
 * TMPDIR-anchored (post-review relocation — see TestLaneContract::sharedRoot()
 * for why TMPDIR alone is unsafe: OS purges, context-dependent value): the
 * path lives under this user's HOME, never this tree's storage/, so two
 * worktrees on the same machine now contend for the SAME lock file — the
 * cross-worktree gap this used to leave open is closed.
 *
 * The identity must match what the booted config resolves post-boot (that is
 * what lets db:test-lane-reset's own flock probe see THIS lock — proven by
 * TestLaneResetCommandTest). getenv() alone is not enough: .env has not been
 * parsed yet this early in Pest's boot (BootFiles::load() runs before any
 * Laravel bootstrapping), so getenv('DB_TESTING_DATABASE') reads false here
 * even though config/database.php's env() will read the real value moments
 * later — a getenv()-only lock silently lands on a different hash than every
 * post-boot caller computes, and the two can never see each other's lock
 * (measured: it does not fail loud, it fails invisibly). Falling back to the
 * raw .env file — the same file Dotenv is about to parse, same pattern as
 * rawEnvDatabases() — keeps the two identities the same without waiting for
 * boot. getenv() is still checked first so an actually-exported environment
 * (CI, a forced var) wins over the file.
 */
$rawPreBootEnv = static function (string $key, string $default): string {
    $fromEnv = getenv($key);

    if ($fromEnv !== false && $fromEnv !== '') {
        return $fromEnv;
    }

    $envFile = dirname(__DIR__).'/.env';

    if (is_file($envFile) && preg_match('/^'.preg_quote($key, '/').'=(.*)$/m', (string) file_get_contents($envFile), $m) === 1) {
        return trim($m[1], "\"' \r");
    }

    return $default;
};

$laneLockPath = TestLaneContract::runLockPath(
    $rawPreBootEnv('DB_TESTING_HOST', '127.0.0.1'),
    $rawPreBootEnv('DB_TESTING_PORT', '3307'),
    $rawPreBootEnv('DB_TESTING_DATABASE', ''),
);

@mkdir(dirname($laneLockPath), 0755, true);

$laneLock = fopen($laneLockPath, 'c+');

if ($laneLock === false) {
    fwrite(STDERR, "Could not open the run-lock file at {$laneLockPath} — check the directory is writable.\n");
    exit(1);
}

if (! flock($laneLock, LOCK_EX | LOCK_NB)) {
    fwrite(STDERR, "Another pest run holds the MySQL lane. Wait for it to finish.\n");
    exit(1);
}

// The include runs inside BootFiles::load() — a method scope. Without this,
// the handle is garbage-collected right after bootstrap and the lock silently
// releases (proven by a mid-run probe). Globals live as long as the process.
$GLOBALS['mysqlLaneRunLock'] = $laneLock;

// Opportunistic one-time cleanup (D3/DP7): the pre-relocation per-tree lock
// file is now permanently dead — nothing will ever open it again — so remove
// it if a run from before this migration left it behind in this tree.
// Transitional: probe it non-blocking first and skip the unlink if a pre-S3
// process (still running the old code, still flocking this exact path) holds
// it right now — this file only fully stops mattering once every checkout
// has picked up this change.
$legacyLockPath = dirname(__DIR__).'/storage/framework/testing/mysql-lane-run.lock';

if (is_file($legacyLockPath)) {
    $legacyProbe = @fopen($legacyLockPath, 'r');

    if ($legacyProbe !== false) {
        if (flock($legacyProbe, LOCK_EX | LOCK_NB)) {
            flock($legacyProbe, LOCK_UN);

            if (@unlink($legacyLockPath)) {
                fwrite(STDERR, "Removed the stale per-tree lane lock at {$legacyLockPath} — superseded by the machine-global lock (D3/DP7).\n");
            }
        }

        fclose($legacyProbe);
    }
}

/*
|--------------------------------------------------------------------------
| Process-scoped fake disk roots
|--------------------------------------------------------------------------
|
| `Storage::fake($disk)` roots every faked disk at
| storage/framework/testing/disks/<disk> and `cleanDirectory()`s it. Without a
| token that root is shared by every pest process on the machine, so a second
| run faking the same disk deletes fixtures out from under an in-flight browser
| test: the Storage panel lists nothing, DOM waits expire, and the failure
| reads as a flake with no JS errors. Proven mechanism and measurements:
| docs/research/browser-timeout-contention-investigation.md.
|
| The token suffix Laravel already supports is the cure. Paratest sets
| TEST_TOKEN per worker, so only fill it in when it is absent, and verify
| before changing this: a token affects the fake disk root ONLY — cache prefix,
| compiled view path and database are identical with and without it, because
| the parallel cache/view/database callbacks are never invoked outside the
| parallel runner.
|
*/

$testingDisksPath = dirname(__DIR__).'/storage/framework/testing/disks';

if ((string) ($_SERVER['TEST_TOKEN'] ?? '') === '') {
    $processToken = 'p'.getmypid();

    putenv("TEST_TOKEN={$processToken}");
    $_ENV['TEST_TOKEN'] = $processToken;
    $_SERVER['TEST_TOKEN'] = $processToken;

    register_shutdown_function(static function () use ($testingDisksPath, $processToken): void {
        foreach ((array) glob($testingDisksPath.'/*_test_'.$processToken) as $root) {
            if (is_string($root) && is_dir($root)) {
                (new Filesystem)->deleteDirectory($root);
            }
        }
    });
}

/*
 * Sweep roots orphaned by runs that died before their shutdown hook (SIGKILL,
 * crashes). Only this scheme's `p<pid>` roots are considered, only when their
 * process is gone, and only past an age floor that no single run can reach —
 * a live concurrent session's root must never be removed.
 */
foreach ((array) glob($testingDisksPath.'/*_test_p[0-9]*') as $orphanCandidate) {
    if (! is_string($orphanCandidate) || ! is_dir($orphanCandidate)) {
        continue;
    }

    $ownerPid = (int) mb_substr((string) mb_strrchr($orphanCandidate, 'p'), 1);
    $modifiedAt = (int) @filemtime($orphanCandidate);

    if ($ownerPid > 0 && posix_kill($ownerPid, 0)) {
        continue;
    }

    if ($modifiedAt > 0 && $modifiedAt < time() - 3600) {
        (new Filesystem)->deleteDirectory($orphanCandidate);
    }
}

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
 // ->use(RefreshDatabase::class)
    ->in('Feature', 'Browser');

pest()->browser()->timeout(30000);

/*
 * The real browser fetches subresources itself, so public-disk URLs must stay
 * relative to reach the pest in-process server; APP_URL-based absolute URLs
 * would point at the Herd vhost, which serves the primary checkout's storage.
 */
uses()->beforeEach(function (): void {
    config()->set('filesystems.disks.public.url', '/storage');
    Storage::forgetDisk('public');
})->in('Browser');

dataset('authz five roles', [
    'super-admin' => [UserRole::SuperAdmin],
    'admin' => [UserRole::Admin],
    'moderator' => [UserRole::Moderator],
    'transcriber' => [UserRole::Transcriber],
    'user' => [UserRole::User],
]);

dataset('authz package definition states', [
    'legacy-only' => [false],
    'additive-package-definitions' => [true],
]);

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function fakeSettingsBackupSnapshotQueue(): void
{
    Queue::fake([
        SettingsBackupSnapshotJob::class,
    ]);
}

/**
 * Fake the settings-snapshots node process with the batched results contract.
 *
 * The fake reads the batch job JSON at spawn time — a missing or truncated
 * file therefore fails the run, which is what keeps the write-then-spawn
 * ordering property (register 2.6) observable in every test that uses this —
 * writes the per-target results file the script contract promises, and fails
 * only the requested targets. Do not assert inside the closure: the manager
 * catches Throwable around the spawn, so an in-closure expectation failure
 * would be swallowed into FAILED rows instead of failing the test.
 *
 * @param  array<int, int|string>  $failTargets  snapshot ids or screen keys to fail
 */
function fakeSettingsSnapshotProcess(array $failTargets = [], string $failError = 'Snapshot capture failed.'): void
{
    Process::fake(function ($process) use ($failTargets, $failError) {
        $command = $process->command;
        $commandLine = is_array($command) ? implode(' ', $command) : (string) $command;

        if (! str_contains($commandLine, 'settings-snapshots.mjs')) {
            return Process::result();
        }

        $payload = json_decode((string) file_get_contents((string) $command[2]), true, flags: JSON_THROW_ON_ERROR);
        $failed = 0;
        $results = [];

        foreach ((array) ($payload['targets'] ?? []) as $target) {
            $fails = in_array($target['snapshot_id'] ?? null, $failTargets, true)
                || in_array($target['screen_key'] ?? null, $failTargets, true);
            $failed += $fails ? 1 : 0;
            $results[] = [
                'snapshot_id' => $target['snapshot_id'] ?? null,
                'ok' => ! $fails,
                'error' => $fails ? $failError : null,
            ];
        }

        file_put_contents((string) $payload['results_path'], json_encode(['results' => $results], JSON_THROW_ON_ERROR));

        return Process::result(
            errorOutput: $failed > 0 ? $failError : '',
            exitCode: $failed > 0 ? 1 : 0,
        );
    });
}

function setTestTranscriptionMode(TranscriptionMode $mode): void
{
    DB::table('settings')->updateOrInsert(
        [
            'group' => AdminUxSettings::group(),
            'name' => 'transcription_mode',
        ],
        [
            'locked' => false,
            'payload' => json_encode($mode->value),
            'created_at' => now(),
            'updated_at' => now(),
        ],
    );

    app()->forgetInstance(AdminUxSettings::class);
    app(SettingsContainer::class)->clearCache();
}

function seedAuthzPackageDefinitions(bool $enabled): void
{
    if (! $enabled) {
        return;
    }

    $role = Role::query()->create([
        'name' => 'additive-admin',
        'guard_name' => 'web',
    ]);
    $permission = Permission::query()->create([
        'name' => 'panel.admin.access',
        'guard_name' => 'web',
    ]);

    DB::table('role_has_permissions')->insert([
        'permission_id' => $permission->getKey(),
        'role_id' => $role->getKey(),
    ]);
}

function expectAuthzPackageAssignmentsEmpty(): void
{
    expect(DB::table('model_has_roles')->count())->toBe(0)
        ->and(DB::table('model_has_permissions')->count())->toBe(0);
}

/** A completed vendor import carrying real failed rows — the intake queue's import fixture. */
function failedImport(int $failed = 2, int $total = 5, string $fileName = 'episodes.csv'): Import
{
    $import = new Import;
    $import->forceFill([
        'file_name' => $fileName,
        'file_path' => "imports/{$fileName}",
        'importer' => ContentItemImporter::class,
        'total_rows' => $total,
        'processed_rows' => $total,
        'successful_rows' => $total - $failed,
        'user_id' => User::factory()->admin()->create()->getKey(),
    ])->save();

    foreach (range(1, $failed) as $index) {
        $import->failedRows()->create([
            'data' => ['title' => "row {$index}"],
            'validation_error' => 'missing identifier',
        ]);
    }

    return $import;
}

/** A media row whose file genuinely exists on the (faked) public disk — no findings. */
function cleanMedia(): Media
{
    $media = Media::factory()->create();
    Storage::disk('public')->put($media->path, 'binary');

    return $media;
}

/** A media row whose file is absent — exactly the missing_file finding. */
function missingFileMedia(): Media
{
    return Media::factory()->create();
}
