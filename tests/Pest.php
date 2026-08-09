<?php

use App\Enums\TranscriptionMode;
use App\Enums\UserRole;
use App\Filament\Imports\ContentItemImporter;
use App\Jobs\SettingsBackupSnapshotJob;
use App\Models\Media;
use App\Models\User;
use App\Settings\AdminUxSettings;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
 */
$laneLock = fopen(dirname(__DIR__).'/storage/framework/testing/mysql-lane-run.lock', 'c+');

if ($laneLock === false || ! flock($laneLock, LOCK_EX | LOCK_NB)) {
    fwrite(STDERR, "Another pest run holds the MySQL lane. Wait for it to finish.\n");
    exit(1);
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
