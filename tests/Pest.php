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
use Pest\Browser\Api\AwaitableWebpage;
use Pest\Browser\Api\PendingAwaitablePage;
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

$laneHost = $rawPreBootEnv('DB_TESTING_HOST', '127.0.0.1');
$lanePort = $rawPreBootEnv('DB_TESTING_PORT', '3307');
$laneDatabase = $rawPreBootEnv('DB_TESTING_DATABASE', '');

$laneLockPath = TestLaneContract::runLockPath($laneHost, $lanePort, $laneDatabase);

@mkdir(dirname($laneLockPath), 0755, true);

$laneLock = fopen($laneLockPath, 'c+');

if ($laneLock === false) {
    // Loud on both streams and never exit 1, for the same reason the refusal
    // below is: a stdout-silent abort is indistinguishable from a silent pass.
    $laneAbort = TestLaneContract::runLockUnopenable($laneLockPath);

    fwrite(STDERR, $laneAbort['stderr']);
    fwrite(STDOUT, $laneAbort['stdout']);

    exit($laneAbort['code']);
}

if (! flock($laneLock, LOCK_EX | LOCK_NB)) {
    /*
     * Refused. This block is deliberately loud on BOTH streams and exits 75,
     * not 1 — see TestLaneContract::runLockRefusal() for why an agent runner
     * once read this very refusal as a silent pass and had to `pgrep` to
     * discover it had been blocked at all.
     */
    $laneRefusal = TestLaneContract::runLockRefusal(
        @file_get_contents($laneLockPath),
        time(),
        static fn (int $pid): bool => posix_kill($pid, 0),
    );

    fwrite(STDERR, $laneRefusal['stderr']);
    fwrite(STDOUT, $laneRefusal['stdout']);

    exit($laneRefusal['code']);
}

// The include runs inside BootFiles::load() — a method scope. Without this,
// the handle is garbage-collected right after bootstrap and the lock silently
// releases (proven by a mid-run probe). Globals live as long as the process.
$GLOBALS['mysqlLaneRunLock'] = $laneLock;

/*
 * Won the lock — now say who we are, in the lock file itself, so the next
 * process to be refused can name us instead of reporting an anonymous "busy"
 * (and so a human can just `cat ~/.cache/podtext-test-lane/*.lock`).
 *
 * The label carries the TREE as well as the argv on purpose: the lock is
 * machine-global, so two worktrees produce identical command lines and the
 * checkout path is the only thing that tells them apart.
 */
$laneHolderPid = (int) getmypid();
$laneHolderLane = $laneDatabase.'@'.$laneHost.':'.$lanePort;
$laneHolderStartedAt = time();
$laneHolderLabel = dirname(__DIR__).': '.implode(' ', array_map(
    static fn (mixed $argument): string => (string) $argument,
    (array) ($_SERVER['argv'] ?? []),
));

$stampLaneHolder = static function (string $state, ?int $releasedAt = null) use (
    $laneHolderPid,
    $laneHolderLabel,
    $laneHolderLane,
    $laneHolderStartedAt,
): void {
    // Reads the handle back out of $GLOBALS instead of closing over it, and
    // that is load-bearing: a captured handle would keep the lock alive on its
    // own and so MASK the GC trap the $GLOBALS line above exists to fix — a
    // future deletion of that line would still pass its guard. Reading it back
    // means the trap stays observable (handle gone → no release record).
    $handle = $GLOBALS['mysqlLaneRunLock'] ?? null;

    if (! is_resource($handle)) {
        return;
    }

    TestLaneContract::writeRunLockRecord(
        $handle,
        $state,
        $laneHolderPid,
        $laneHolderLabel,
        $laneHolderLane,
        $laneHolderStartedAt,
        $releasedAt,
    );
};

$stampLaneHolder('held');

// Normal exit clears the record, so a later reader sees `released` rather than
// a finished run masquerading as a live one. A killed run cannot run this —
// which is exactly why flock, not the record, remains the authority.
register_shutdown_function(static fn () => $stampLaneHolder('released', time()));

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

/**
 * Chromium's exact ResizeObserver notice, trailing period included.
 *
 * Classified in-repo as a benign artifact of Filament's body/sidebar observer
 * rather than a page defect
 * (docs/phase-02/settings-step5b-card-template-preview-lg-column-handoff.md:168-175).
 * It appears only under full-run load, which is why it wears a flake's clothes.
 *
 * Matching is exact by requirement, never a substring: a prefix match would
 * also swallow future ResizeObserver messages that nobody has classified
 * (docs/research/settings-performance/44-settings-development-metrics-retirement-mini2-implementation-plan.md:128).
 */
function knownResizeObserverArtifact(): string
{
    return 'ResizeObserver loop completed with undelivered notifications.';
}

/**
 * Drop only the classified artifact from Pest's accumulator; report how many.
 *
 * The count is the point. A run that strips three artifacts is not a
 * zero-message run, and describing it as one would hide the very thing the
 * classification records — so the count comes back to the caller instead of
 * being discarded inside the filter.
 *
 * Call this before any diagnostic that reads the accumulator, not only before
 * an assertion: a capped read (`slice`) lets a benign artifact evict a real
 * error from the payload someone reads while debugging.
 *
 * `window.__pestBrowser.jsErrors` is a pest-plugin-browser internal, and this
 * function is the single place the suite reaches into it. The read is
 * deliberately not optional-chained: the plugin's init script defines that
 * object before any page script runs, so if it is ever missing the internal
 * has moved and this should fail loudly here rather than silently strip
 * nothing everywhere.
 *
 * Both page shapes are accepted because `visit()` returns a
 * PendingAwaitablePage that only becomes an AwaitableWebpage once something is
 * called on it: existing call sites arrive here post-`resize()` and a new one
 * written as plain `visit()` would not, and a shared helper should not fail on
 * the difference.
 */
function stripKnownResizeObserverArtifacts(AwaitableWebpage|PendingAwaitablePage $page): int
{
    $known = json_encode(knownResizeObserverArtifact(), JSON_THROW_ON_ERROR);

    return (int) $page->page()->evaluate(<<<JS
        () => {
            const known = {$known};
            const errors = window.__pestBrowser.jsErrors;

            window.__pestBrowser.jsErrors = errors.filter((error) => error.message !== known);

            return errors.length - window.__pestBrowser.jsErrors.length;
        }
        JS);
}

/**
 * Fail on every JavaScript error except the classified artifact.
 *
 * Strips the artifact, then hands the rest to Pest's own assertion, so an
 * unexpected message still fails the test with the plugin's own reporting.
 * Returns the artifact count for callers that record it in a failure payload.
 */
function assertNoUnexpectedJavaScriptErrors(AwaitableWebpage|PendingAwaitablePage $page): int
{
    $artifacts = stripKnownResizeObserverArtifacts($page);

    $page->assertNoJavaScriptErrors();

    return $artifacts;
}

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
