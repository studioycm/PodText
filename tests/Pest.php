<?php

use App\Enums\TranscriptionMode;
use App\Enums\UserRole;
use App\Jobs\SettingsBackupSnapshotJob;
use App\Settings\AdminUxSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Spatie\LaravelSettings\SettingsContainer;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

foreach ([
    'APP_ENV' => 'testing',
    'CACHE_STORE' => 'array',
    'DB_CONNECTION' => 'sqlite',
    'DB_DATABASE' => ':memory:',
    'DB_URL' => '',
    'QUEUE_CONNECTION' => 'sync',
    'SESSION_DRIVER' => 'array',
] as $key => $value) {
    putenv("{$key}={$value}");
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
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
