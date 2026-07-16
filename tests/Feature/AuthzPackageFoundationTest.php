<?php

use App\Enums\UserRole;
use App\Models\User;
use App\Support\Authorization\BlockedPackageMutationCommand;
use App\Support\Authorization\PackageMutationCommandGuard;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use BezhanSalleh\FilamentShield\Resources\Roles\RoleResource;
use Illuminate\Console\Application as ArtisanApplication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

uses(RefreshDatabase::class);

it('keeps Shield dormant and Permission outside application authorization', function (): void {
    $adminPanel = filament()->getPanel('admin');
    $publicPanel = filament()->getPanel('public');
    $userTraits = class_uses_recursive(User::class);

    expect(config('filament-shield.auth_provider_model'))->toBe(User::class)
        ->and(config('filament-shield.super_admin.enabled'))->toBeFalse()
        ->and(config('filament-shield.super_admin.define_via_gate'))->toBeFalse()
        ->and(config('filament-shield.panel_user.enabled'))->toBeFalse()
        ->and(config('filament-shield.permissions.generate'))->toBeFalse()
        ->and(config('filament-shield.policies.merge'))->toBeFalse()
        ->and(config('filament-shield.policies.generate'))->toBeFalse()
        ->and(config('filament-shield.resources.manage'))->toBe([])
        ->and(config('filament-shield.custom_permissions'))->toBe([])
        ->and(config('filament-shield.register_role_policy'))->toBeFalse()
        ->and(config('permission.register_permission_check_method'))->toBeFalse()
        ->and(config('permission.teams'))->toBeFalse()
        ->and(config('permission.events_enabled'))->toBeFalse()
        ->and(config('permission.enable_wildcard_permission'))->toBeFalse()
        ->and(config('permission.cache.key'))->toBe('podtext.permission.cache')
        ->and($adminPanel->hasPlugin(FilamentShieldPlugin::make()->getId()))->toBeFalse()
        ->and($publicPanel->hasPlugin(FilamentShieldPlugin::make()->getId()))->toBeFalse()
        ->and($adminPanel->getResources())->not->toContain(RoleResource::class)
        ->and($publicPanel->getResources())->not->toContain(RoleResource::class)
        ->and($userTraits)->not->toContain(HasRoles::class)
        ->and(method_exists(User::class, 'roles'))->toBeFalse()
        ->and(method_exists(User::class, 'permissions'))->toBeFalse();
});

it('blocks every package mutation command with an app-owned production replacement', function (): void {
    $artisan = new ArtisanApplication(app(), app('events'), app()->version());

    PackageMutationCommandGuard::block($artisan);

    expect(PackageMutationCommandGuard::COMMANDS)->toBe([
        'shield:generate',
        'shield:install',
        'shield:publish',
        'shield:seeder',
        'shield:setup',
        'shield:super-admin',
        'shield:translation',
        'permission:assign-role',
        'permission:create-permission',
        'permission:create-role',
        'permission:setup-teams',
    ]);

    foreach (PackageMutationCommandGuard::COMMANDS as $commandName) {
        expect($artisan->get($commandName))->toBeInstanceOf(BlockedPackageMutationCommand::class);
    }

    $blockedCommand = new BlockedPackageMutationCommand('shield:seeder');
    $blockedCommand->setLaravel(app());
    $tester = new CommandTester($blockedCommand);

    expect($tester->execute([]))->toBe(Command::FAILURE)
        ->and($tester->getDisplay())->toContain('disabled in production');
});

it('does not let isolated package definitions change legacy authority or create assignments', function (): void {
    $panel = filament()->getPanel('admin');
    $packageRole = Role::query()->create(['name' => 'admin', 'guard_name' => 'web']);
    $packagePermission = Permission::query()->create(['name' => 'panel.admin.access', 'guard_name' => 'web']);

    DB::table('role_has_permissions')->insert([
        'permission_id' => $packagePermission->getKey(),
        'role_id' => $packageRole->getKey(),
    ]);

    $expectations = [
        UserRole::SuperAdmin->value => [true, true],
        UserRole::Admin->value => [true, false],
        UserRole::Moderator->value => [false, false],
        UserRole::Transcriber->value => [false, false],
        UserRole::User->value => [false, false],
    ];

    foreach ($expectations as $roleValue => [$canAccessPanel, $isSuperAdmin]) {
        $user = User::factory()->role(UserRole::from($roleValue))->create();

        expect($user->canAccessPanel($panel))->toBe($canAccessPanel)
            ->and(Gate::forUser($user)->allows('super-admin'))->toBe($isSuperAdmin);
    }

    expect(DB::table('model_has_roles')->count())->toBe(0)
        ->and(DB::table('model_has_permissions')->count())->toBe(0);
});

it('runs only the package migration up down up on isolated SQLite memory', function (): void {
    $connection = 'authz_package_memory';
    $originalConnection = DB::getDefaultConnection();

    config([
        "database.connections.{$connection}" => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ],
        'database.default' => $connection,
        'permission.testing' => false,
    ]);

    DB::purge($connection);
    DB::setDefaultConnection($connection);

    $migration = require database_path('migrations/2026_07_16_172210_create_permission_tables.php');
    $tables = ['permissions', 'roles', 'model_has_permissions', 'model_has_roles', 'role_has_permissions'];
    $assertPackageSchema = function () use ($connection, $tables): void {
        $schema = Schema::connection($connection);

        foreach ($tables as $table) {
            expect($schema->hasTable($table))->toBeTrue();
        }

        expect($schema->getColumnListing('permissions'))->toBe(['id', 'name', 'guard_name', 'created_at', 'updated_at'])
            ->and($schema->getColumnListing('roles'))->toBe(['id', 'name', 'guard_name', 'created_at', 'updated_at'])
            ->and($schema->getColumnListing('model_has_permissions'))->toBe(['permission_id', 'model_type', 'model_id'])
            ->and($schema->getColumnListing('model_has_roles'))->toBe(['role_id', 'model_type', 'model_id'])
            ->and($schema->getColumnListing('role_has_permissions'))->toBe(['permission_id', 'role_id'])
            ->and($schema->hasColumn('roles', 'team_id'))->toBeFalse()
            ->and(collect($schema->getIndexes('permissions'))->contains(fn (array $index): bool => $index['unique'] && $index['columns'] === ['name', 'guard_name']))->toBeTrue()
            ->and(collect($schema->getIndexes('roles'))->contains(fn (array $index): bool => $index['unique'] && $index['columns'] === ['name', 'guard_name']))->toBeTrue()
            ->and(collect($schema->getIndexes('model_has_permissions'))->contains(fn (array $index): bool => $index['primary'] && $index['columns'] === ['permission_id', 'model_id', 'model_type']))->toBeTrue()
            ->and(collect($schema->getIndexes('model_has_roles'))->contains(fn (array $index): bool => $index['primary'] && $index['columns'] === ['role_id', 'model_id', 'model_type']))->toBeTrue()
            ->and(collect($schema->getIndexes('role_has_permissions'))->contains(fn (array $index): bool => $index['primary'] && $index['columns'] === ['permission_id', 'role_id']))->toBeTrue()
            ->and($schema->getForeignKeys('model_has_permissions'))->toHaveCount(1)
            ->and($schema->getForeignKeys('model_has_roles'))->toHaveCount(1)
            ->and($schema->getForeignKeys('role_has_permissions'))->toHaveCount(2);
    };

    try {
        $migration->up();
        $assertPackageSchema();

        $migration->down();

        foreach ($tables as $table) {
            expect(Schema::connection($connection)->hasTable($table))->toBeFalse();
        }

        $migration->up();
        $assertPackageSchema();
    } finally {
        $migration->down();
        DB::disconnect($connection);
        DB::setDefaultConnection($originalConnection);
        config(['database.default' => $originalConnection]);
    }
});
