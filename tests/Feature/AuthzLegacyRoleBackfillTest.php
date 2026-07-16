<?php

use App\Auth\CompatibilityGrantManifest;
use App\Auth\LegacyRoleBackfill\AnalysisReport;
use App\Auth\LegacyRoleBackfill\AnalysisReportValidator;
use App\Auth\LegacyRoleBackfill\ArtifactException;
use App\Auth\LegacyRoleBackfill\BackfillException;
use App\Auth\LegacyRoleBackfill\BackfillRefusalException;
use App\Auth\LegacyRoleBackfill\BackfillResult;
use App\Auth\LegacyRoleBackfill\CanonicalJson;
use App\Auth\LegacyRoleBackfill\LegacyRoleBackfillAnalyzer;
use App\Auth\LegacyRoleBackfill\LegacyRoleBackfillApplier;
use App\Auth\LegacyRoleBackfill\LegacyRoleBackfillRollback;
use App\Auth\LegacyRoleBackfill\PrivacyHasher;
use App\Auth\LegacyRoleBackfill\PrivateArtifactRepository;
use App\Enums\TranscriptionMode;
use App\Enums\UserRole;
use App\Filament\Pages\AdminTools;
use App\Filament\Resources\Authors\AuthorResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Traits\HasRoles;
use Symfony\Component\Process\Process;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Http::preventStrayRequests();
    Mail::fake();

    expect(config('database.default'))->toBe('sqlite')
        ->and(config('database.connections.sqlite.database'))->toBe(':memory:')
        ->and(DB::connection()->getDriverName())->toBe('sqlite')
        ->and(DB::connection()->getDatabaseName())->toBe(':memory:');

    config([
        'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
        'permission.teams' => false,
        'permission.testing' => false,
    ]);

    $root = sys_get_temp_dir().'/podtext-authz1c-'.Str::ulid();
    File::ensureDirectoryExists($root, 0700);
    test()->artifactRoot = $root;
    app()->instance(PrivateArtifactRepository::class, new PrivateArtifactRepository($root));
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

afterEach(function (): void {
    File::deleteDirectory(test()->artifactRoot);
});

function authzCreateLegacyUsers(): array
{
    return collect(UserRole::cases())
        ->mapWithKeys(fn (UserRole $role, int $index): array => [
            $role->value => User::factory()->create([
                'id' => 910_001 + $index,
                'role' => $role,
            ]),
        ])
        ->all();
}

function authzAnalyzer(): LegacyRoleBackfillAnalyzer
{
    return app(LegacyRoleBackfillAnalyzer::class);
}

function authzArtifactRepository(): PrivateArtifactRepository
{
    return app(PrivateArtifactRepository::class);
}

function authzRegistrar(bool $result = true): PermissionRegistrar&MockInterface
{
    $registrar = Mockery::mock(PermissionRegistrar::class);
    $registrar->shouldReceive('forgetCachedPermissions')->once()->andReturn($result);

    return $registrar;
}

function authzApplier(PermissionRegistrar $registrar, ?Closure $hook = null): LegacyRoleBackfillApplier
{
    return new LegacyRoleBackfillApplier(
        analyzer: authzAnalyzer(),
        validator: app(AnalysisReportValidator::class),
        artifacts: authzArtifactRepository(),
        registrar: $registrar,
        afterWriteHook: $hook,
    );
}

function authzApply(AnalysisReport $report, PermissionRegistrar $registrar): BackfillResult
{
    return authzApplier($registrar)->apply(
        report: $report,
        acceptedSource: $report->sourceFingerprint(),
        acceptedReport: $report->reportFingerprint(),
        confirmation: 'AUTHZ1-C',
    );
}

it('produces deterministic privacy-safe raw five-role evidence', function (): void {
    authzCreateLegacyUsers();

    $first = authzAnalyzer()->analyze()->toArray();
    $second = authzAnalyzer()->analyze()->toArray();

    expect($first['status'])->toBe('ready')
        ->and($first['source']['total'])->toBe(5)
        ->and($first['source']['per_role'])->toBe(array_fill_keys(UserRole::values(), 1))
        ->and(array_column($first['source']['users'], 'user_hash'))->toHaveCount(5)
        ->each->toMatch('/^[a-f0-9]{64}$/')
        ->and($first['fingerprints']['source'])->toBe($second['fingerprints']['source'])
        ->and($first['fingerprints']['target_before'])->toBe($second['fingerprints']['target_before'])
        ->and($first['fingerprints']['target_planned'])->toBe($second['fingerprints']['target_planned'])
        ->and($first['access_parity'])->toBe($second['access_parity'])
        ->and(collect($first['access_parity']['matrix'])->map(fn (array $entry): array => $entry['abilities'])->all())
        ->toBe(CompatibilityGrantManifest::grants())
        ->and($first['legacy_authority'])->toBeTrue();

    $json = json_encode($first, JSON_THROW_ON_ERROR);

    foreach (User::query()->get(['id', 'name', 'email']) as $user) {
        expect($json)->not->toContain((string) $user->id)
            ->not->toContain($user->name)
            ->not->toContain($user->email);
    }
});

it('enumerates raw invalid types and simultaneous source faults without enum fallback', function (): void {
    $report = authzAnalyzer()->analyzeSourceRows([
        ['id' => 1, 'role' => null],
        ['id' => 1, 'role' => UserRole::Admin->value],
        ['id' => 8, 'role' => ''],
        ['id' => 9, 'role' => '   '],
        ['id' => 2, 'role' => ' admin '],
        ['id' => 3, 'role' => 'ADMIN'],
        ['id' => 4, 'role' => 'unknown'],
        ['id' => 5, 'role' => 100],
        ['id' => 6, 'role' => true],
        ['id' => 7, 'role' => "\xB1\x31"],
    ])->toArray();

    expect($report['status'])->toBe('blocked')
        ->and($report['issue_totals'])->toHaveKeys([
            'source_duplicate_identity',
            'source_invalid_role',
            'source_invalid_role_type',
        ])
        ->and($report['source']['per_role'][UserRole::User->value])->toBe(0)
        ->and(json_encode($report, JSON_INVALID_UTF8_SUBSTITUTE))->not->toContain('unknown')
        ->not->toContain('ADMIN')
        ->not->toContain(' admin ');
});

it('collects simultaneous source and package faults with zero database mutation', function (): void {
    DB::table('users')->insert([
        'name' => 'Private Person',
        'email' => 'private@example.test',
        'password' => 'irrelevant',
        'role' => 'corrupt role',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('roles')->insert([
        ['name' => 'ADMIN', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
        ['name' => UserRole::Admin->value, 'guard_name' => 'api', 'created_at' => now(), 'updated_at' => now()],
    ]);
    DB::table('permissions')->insert(['name' => 'premature', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()]);
    $before = [
        'roles' => DB::table('roles')->get()->map(fn ($row) => (array) $row)->all(),
        'permissions' => DB::table('permissions')->get()->map(fn ($row) => (array) $row)->all(),
    ];

    $report = authzAnalyzer()->analyze();
    $name = authzArtifactRepository()->publishReport($report, 'complete-errors.json');
    $after = [
        'roles' => DB::table('roles')->get()->map(fn ($row) => (array) $row)->all(),
        'permissions' => DB::table('permissions')->get()->map(fn ($row) => (array) $row)->all(),
    ];
    $json = file_get_contents(test()->artifactRoot.'/authorization/authz1-c/reports/'.$name);

    expect($report->isBlocked())->toBeTrue()
        ->and($report->toArray()['issue_totals'])->toHaveKeys([
            'source_invalid_role',
            'role_case_collision',
            'role_wrong_guard',
            'permission_rows_present',
        ])
        ->and($after)->toBe($before)
        ->and($json)->not->toContain('Private Person')
        ->not->toContain('private@example.test')
        ->not->toContain('corrupt role');
});

it('refuses target, config, team, model, schema, and key drift', function (): void {
    authzCreateLegacyUsers();

    DB::table('roles')->insert(['name' => 'foreign', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()]);
    expect(authzAnalyzer()->analyze()->toArray()['issue_totals'])->toHaveKey('role_unknown');

    DB::table('roles')->delete();
    DB::table('permissions')->insert(['name' => 'premature', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()]);
    expect(authzAnalyzer()->analyze()->toArray()['issue_totals'])->toHaveKey('permission_rows_present');

    DB::table('permissions')->delete();
    config(['permission.teams' => true]);
    expect(authzAnalyzer()->analyze()->toArray()['issue_totals'])->toHaveKey('config_teams_enabled');

    config(['permission.teams' => false, 'auth.providers.users.model' => stdClass::class]);
    expect(authzAnalyzer()->analyze()->toArray()['issue_totals'])->toHaveKey('config_provider_drift');

    config(['auth.providers.users.model' => User::class, 'auth.defaults.guard' => 'api']);
    expect(authzAnalyzer()->analyze()->toArray()['issue_totals'])->toHaveKey('config_guard_drift');

    config(['auth.defaults.guard' => 'web', 'permission.column_names.model_morph_key' => 'subject_id']);
    expect(authzAnalyzer()->analyze()->toArray()['issue_totals'])->toHaveKey('config_column_drift');

    config(['permission.column_names.model_morph_key' => 'model_id']);
    Schema::table('roles', fn (Blueprint $table) => $table->string('unexpected_schema_column')->nullable());
    expect(authzAnalyzer()->analyze()->toArray()['issue_totals'])->toHaveKey('schema_column_drift');

    config(['app.key' => 'base64:not-valid']);
    expect(fn () => new PrivacyHasher)->toThrow(BackfillException::class);
});

it('reports every unsafe package-table row class in one target scan', function (): void {
    $users = authzCreateLegacyUsers();
    $adminRole = DB::table('roles')->insertGetId([
        'name' => UserRole::Admin->value,
        'guard_name' => 'web',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $userRole = DB::table('roles')->insertGetId([
        'name' => UserRole::User->value,
        'guard_name' => 'web',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $permission = DB::table('permissions')->insertGetId([
        'name' => 'premature.permission',
        'guard_name' => 'web',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('role_has_permissions')->insert(['permission_id' => $permission, 'role_id' => $adminRole]);
    DB::table('model_has_permissions')->insert([
        'permission_id' => $permission,
        'model_id' => $users[UserRole::Admin->value]->id,
        'model_type' => (new User)->getMorphClass(),
    ]);
    DB::table('model_has_roles')->insert([
        [
            'role_id' => $adminRole,
            'model_id' => $users[UserRole::Admin->value]->id,
            'model_type' => (new User)->getMorphClass(),
        ],
        [
            'role_id' => $userRole,
            'model_id' => $users[UserRole::Admin->value]->id,
            'model_type' => (new User)->getMorphClass(),
        ],
        [
            'role_id' => $userRole,
            'model_id' => 999_999,
            'model_type' => (new User)->getMorphClass(),
        ],
        [
            'role_id' => $userRole,
            'model_id' => $users[UserRole::User->value]->id,
            'model_type' => 'Foreign\\Model',
        ],
    ]);

    $issues = authzAnalyzer()->analyze()->toArray()['issue_totals'];

    expect($issues)->toHaveKeys([
        'permission_rows_present',
        'role_grant_rows_present',
        'direct_grant_rows_present',
        'assignment_multiple',
        'assignment_wrong_role',
        'assignment_orphan_user',
        'assignment_wrong_model_type',
    ]);
});

it('applies exactly one same-slug protected assignment per raw legacy user', function (): void {
    $users = authzCreateLegacyUsers();
    $report = authzAnalyzer()->analyze();
    $result = authzApply($report, authzRegistrar());

    expect($result->status)->toBe('applied')
        ->and($result->insertedRoles)->toBe(5)
        ->and($result->insertedAssignments)->toBe(5)
        ->and(DB::table('roles')->count())->toBe(5)
        ->and(DB::table('model_has_roles')->count())->toBe(5)
        ->and(DB::table('permissions')->count())->toBe(0)
        ->and(DB::table('role_has_permissions')->count())->toBe(0)
        ->and(DB::table('model_has_permissions')->count())->toBe(0)
        ->and(authzAnalyzer()->analyze()->status())->toBe('already_applied');

    foreach ($users as $slug => $user) {
        expect(DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_id', $user->id)
            ->where('model_has_roles.model_type', (new User)->getMorphClass())
            ->value('roles.name'))->toBe($slug);
    }
});

it('preserves a valid pre-existing subset and dispatches no package model events', function (): void {
    $users = authzCreateLegacyUsers();
    $roleId = DB::table('roles')->insertGetId([
        'name' => UserRole::Admin->value,
        'guard_name' => 'web',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('model_has_roles')->insert([
        'role_id' => $roleId,
        'model_id' => $users[UserRole::Admin->value]->id,
        'model_type' => (new User)->getMorphClass(),
    ]);
    $events = [];
    Event::listen('eloquent.*', function (string $event) use (&$events): void {
        if (str_contains($event, 'Spatie\\Permission')) {
            $events[] = $event;
        }
    });

    $result = authzApply(authzAnalyzer()->analyze(), authzRegistrar());

    expect($result->insertedRoles)->toBe(4)
        ->and($result->insertedAssignments)->toBe(4)
        ->and(DB::table('model_has_roles')->count())->toBe(5)
        ->and($events)->toBe([]);
});

it('refuses report, source, target, path, symlink, overwrite, and JSON tampering', function (): void {
    authzCreateLegacyUsers();
    $report = authzAnalyzer()->analyze();
    $repository = authzArtifactRepository();
    $name = $repository->publishReport($report, 'accepted.json');

    expect(fn () => authzApplier(Mockery::mock(PermissionRegistrar::class))->apply($report, str_repeat('0', 64), $report->reportFingerprint(), 'AUTHZ1-C'))
        ->toThrow(BackfillRefusalException::class)
        ->and(fn () => $repository->loadReport('../accepted.json'))->toThrow(ArtifactException::class)
        ->and(fn () => $repository->publishReport($report, $name))->toThrow(ArtifactException::class);

    $path = test()->artifactRoot.'/authorization/authz1-c/reports/'.$name;
    $payload = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    $payload['status'] = 'blocked';
    file_put_contents($path, json_encode($payload, JSON_THROW_ON_ERROR));
    expect(fn () => $repository->loadReport($name))->toThrow(ArtifactException::class);

    $malformed = test()->artifactRoot.'/authorization/authz1-c/reports/malformed.json';
    file_put_contents($malformed, '{');
    expect(fn () => $repository->loadReport('malformed.json'))->toThrow(ArtifactException::class);

    $link = test()->artifactRoot.'/authorization/authz1-c/reports/link.json';
    symlink($malformed, $link);
    expect(fn () => $repository->loadReport('link.json'))->toThrow(ArtifactException::class);

    $oversized = test()->artifactRoot.'/authorization/authz1-c/reports/oversized.json';
    file_put_contents($oversized, str_repeat('x', (10 * 1024 * 1024) + 1));
    expect(fn () => $repository->loadReport('oversized.json'))->toThrow(ArtifactException::class)
        ->and(substr(sprintf('%o', fileperms(dirname($path))), -4))->toBe('0700')
        ->and(substr(sprintf('%o', fileperms($path)), -4))->toBe('0600');

    $repository->publishReport($report, 'public-mode.json');
    $publicModePath = test()->artifactRoot.'/authorization/authz1-c/reports/public-mode.json';
    chmod($publicModePath, 0644);
    expect(fn () => $repository->loadReport('public-mode.json'))->toThrow(ArtifactException::class);
});

it('serializes concurrent publication and refuses the duplicate artifact name', function (): void {
    authzCreateLegacyUsers();
    $payload = base64_encode(CanonicalJson::encode(authzAnalyzer()->analyze()->toArray()));
    $autoload = base_path('vendor/autoload.php');
    $code = <<<'PHP'
require $argv[1];
$payload = json_decode(base64_decode($argv[2], true), true, 512, JSON_THROW_ON_ERROR);
$report = App\Auth\LegacyRoleBackfill\AnalysisReport::fromArray($payload);
$repository = new App\Auth\LegacyRoleBackfill\PrivateArtifactRepository($argv[3]);
try {
    $repository->publishReport($report, 'concurrent.json');
    exit(0);
} catch (App\Auth\LegacyRoleBackfill\ArtifactException) {
    exit(2);
}
PHP;
    $first = new Process([PHP_BINARY, '-r', $code, $autoload, $payload, test()->artifactRoot]);
    $second = new Process([PHP_BINARY, '-r', $code, $autoload, $payload, test()->artifactRoot]);
    $first->start();
    $second->start();
    $first->wait();
    $second->wait();
    $exitCodes = [$first->getExitCode(), $second->getExitCode()];
    sort($exitCodes);

    expect($exitCodes)->toBe([0, 2])
        ->and(authzArtifactRepository()->loadReport('concurrent.json')->status())->toBe('ready');
});

it('rejects semantic report tampering even when its plain report fingerprint is recomputed', function (): void {
    authzCreateLegacyUsers();
    $payload = authzAnalyzer()->analyze()->toArray();
    array_pop($payload['target_planned']['assignment_hashes']);
    unset($payload['fingerprints']['report']);
    $payload['fingerprints']['report'] = hash('sha256', CanonicalJson::encode($payload));
    $tampered = AnalysisReport::fromArray($payload);
    $registrar = Mockery::mock(PermissionRegistrar::class);
    $registrar->shouldNotReceive('forgetCachedPermissions');

    expect(fn () => authzApplier($registrar)->apply(
        $tampered,
        $tampered->sourceFingerprint(),
        $tampered->reportFingerprint(),
        'AUTHZ1-C',
    ))->toThrow(BackfillRefusalException::class)
        ->and(DB::table('roles')->count())->toBe(0)
        ->and(DB::table('model_has_roles')->count())->toBe(0);
});

it('refuses source target and reporting-key drift before any write or cache reset', function (): void {
    $users = authzCreateLegacyUsers();
    $report = authzAnalyzer()->analyze();
    $registrar = Mockery::mock(PermissionRegistrar::class);
    $registrar->shouldNotReceive('forgetCachedPermissions');

    DB::table('users')->where('id', $users[UserRole::User->value]->id)->update(['role' => UserRole::Transcriber->value]);
    expect(fn () => authzApplier($registrar)->apply($report, $report->sourceFingerprint(), $report->reportFingerprint(), 'AUTHZ1-C'))
        ->toThrow(BackfillRefusalException::class);

    DB::table('users')->where('id', $users[UserRole::User->value]->id)->update(['role' => UserRole::User->value]);
    DB::table('roles')->insert([
        'name' => UserRole::Admin->value,
        'guard_name' => 'web',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    expect(fn () => authzApplier($registrar)->apply($report, $report->sourceFingerprint(), $report->reportFingerprint(), 'AUTHZ1-C'))
        ->toThrow(BackfillRefusalException::class);

    DB::table('roles')->delete();
    config(['app.key' => 'base64:'.base64_encode(str_repeat('b', 32))]);
    expect(fn () => authzApplier($registrar)->apply($report, $report->sourceFingerprint(), $report->reportFingerprint(), 'AUTHZ1-C'))
        ->toThrow(BackfillRefusalException::class)
        ->and(DB::table('roles')->count())->toBe(0)
        ->and(DB::table('model_has_roles')->count())->toBe(0);
});

it('rolls back every authorization write and skips cache when final validation fails', function (): void {
    authzCreateLegacyUsers();
    $report = authzAnalyzer()->analyze();
    $registrar = Mockery::mock(PermissionRegistrar::class);
    $registrar->shouldNotReceive('forgetCachedPermissions');
    $applier = authzApplier($registrar, fn () => throw new BackfillException('induced'));

    expect(fn () => $applier->apply($report, $report->sourceFingerprint(), $report->reportFingerprint(), 'AUTHZ1-C'))
        ->toThrow(BackfillException::class)
        ->and(DB::table('roles')->count())->toBe(0)
        ->and(DB::table('model_has_roles')->count())->toBe(0)
        ->and(authzArtifactRepository()->operationExists(
            authzArtifactRepository()->operationName($report->reportFingerprint(), 'prepared'),
        ))->toBeTrue();
});

it('recomputes locked state from the beginning on a database deadlock retry', function (): void {
    expect(DB::connection()->transactionLevel())->toBe(1);
    DB::rollBack();

    try {
        authzCreateLegacyUsers();
        $report = authzAnalyzer()->analyze();
        $attempts = 0;
        $hook = function () use (&$attempts): void {
            $attempts++;

            if ($attempts === 1) {
                throw new QueryException(
                    'sqlite',
                    'select 1',
                    [],
                    new PDOException('database is locked'),
                );
            }
        };
        $applier = authzApplier(authzRegistrar(), $hook);
        $result = $applier->apply(
            $report,
            $report->sourceFingerprint(),
            $report->reportFingerprint(),
            'AUTHZ1-C',
        );

        expect($attempts)->toBe(2)
            ->and($result->status)->toBe('applied')
            ->and(DB::table('roles')->count())->toBe(5)
            ->and(DB::table('model_has_roles')->count())->toBe(5);
    } finally {
        DB::table('model_has_roles')->delete();
        DB::table('roles')->delete();
        DB::table('users')->delete();
        DB::beginTransaction();
    }
});

it('recovers a post-commit cache failure only from exact prepared planned state', function (): void {
    authzCreateLegacyUsers();
    $report = authzAnalyzer()->analyze();

    expect(fn () => authzApply($report, authzRegistrar(false)))
        ->toThrow(BackfillException::class)
        ->and(DB::table('roles')->count())->toBe(5)
        ->and(DB::table('model_has_roles')->count())->toBe(5);

    $result = authzApply($report, authzRegistrar(true));

    expect($result->status)->toBe('recovered')
        ->and($result->receiptName)->not->toBeNull()
        ->and(authzArtifactRepository()->backfillReceiptExists($result->receiptName))->toBeTrue();
});

it('recovers a post-commit cache exception without repeating database writes', function (): void {
    authzCreateLegacyUsers();
    $report = authzAnalyzer()->analyze();
    $failingRegistrar = Mockery::mock(PermissionRegistrar::class);
    $failingRegistrar->shouldReceive('forgetCachedPermissions')->once()->andThrow(new RuntimeException('induced cache failure'));

    expect(fn () => authzApply($report, $failingRegistrar))->toThrow(RuntimeException::class)
        ->and(DB::table('roles')->count())->toBe(5)
        ->and(DB::table('model_has_roles')->count())->toBe(5);

    $result = authzApply($report, authzRegistrar());

    expect($result->status)->toBe('recovered')
        ->and(DB::table('roles')->count())->toBe(5)
        ->and(DB::table('model_has_roles')->count())->toBe(5);
});

it('refuses a recomputed but semantically changed cache journal on completed rerun', function (): void {
    authzCreateLegacyUsers();
    $report = authzAnalyzer()->analyze();
    authzApply($report, authzRegistrar());
    $cacheName = authzArtifactRepository()->operationName($report->reportFingerprint(), 'cache_reset');
    $path = test()->artifactRoot.'/authorization/authz1-c/operations/'.$cacheName;
    $journal = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    $journal['state'] = 'complete';
    unset($journal['journal_fingerprint']);
    $journal['journal_fingerprint'] = hash('sha256', CanonicalJson::encode($journal));
    file_put_contents($path, CanonicalJson::encode($journal)."\n");
    chmod($path, 0600);
    $registrar = Mockery::mock(PermissionRegistrar::class);
    $registrar->shouldNotReceive('forgetCachedPermissions');

    expect(fn () => authzApply($report, $registrar))->toThrow(BackfillRefusalException::class);
});

it('resumes a rolled-back prepared operation and refuses partial planned state', function (): void {
    authzCreateLegacyUsers();
    $report = authzAnalyzer()->analyze();
    $registrar = Mockery::mock(PermissionRegistrar::class);
    $registrar->shouldNotReceive('forgetCachedPermissions');

    expect(fn () => authzApplier($registrar, fn () => throw new BackfillException('induced'))->apply(
        $report,
        $report->sourceFingerprint(),
        $report->reportFingerprint(),
        'AUTHZ1-C',
    ))->toThrow(BackfillException::class);

    expect(authzApply($report, authzRegistrar())->status)->toBe('applied');

    DB::table('model_has_roles')->orderBy('model_id')->limit(1)->delete();
    $completedReport = authzAnalyzer()->analyze();
    expect($completedReport->isBlocked())->toBeFalse();
    expect(fn () => authzApply($report, Mockery::mock(PermissionRegistrar::class)))
        ->toThrow(BackfillRefusalException::class);
});

it('makes a completed second apply a true database cache and artifact no-op', function (): void {
    authzCreateLegacyUsers();
    $report = authzAnalyzer()->analyze();
    $registrar = Mockery::mock(PermissionRegistrar::class);
    $registrar->shouldReceive('forgetCachedPermissions')->once()->andReturnTrue();
    $applier = authzApplier($registrar);
    $first = $applier->apply($report, $report->sourceFingerprint(), $report->reportFingerprint(), 'AUTHZ1-C');
    $filesBefore = File::allFiles(test()->artifactRoot.'/authorization/authz1-c');
    $second = $applier->apply($report, $report->sourceFingerprint(), $report->reportFingerprint(), 'AUTHZ1-C');

    expect($first->status)->toBe('applied')
        ->and($second->status)->toBe('no_op')
        ->and(DB::table('roles')->count())->toBe(5)
        ->and(DB::table('model_has_roles')->count())->toBe(5)
        ->and(File::allFiles(test()->artifactRoot.'/authorization/authz1-c'))->toHaveCount(count($filesBefore));
});

it('rolls back only receipt-inserted assignments, preserves roles, and supports reapply', function (): void {
    $users = authzCreateLegacyUsers();
    $roleId = DB::table('roles')->insertGetId([
        'name' => UserRole::Admin->value,
        'guard_name' => 'web',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('model_has_roles')->insert([
        'role_id' => $roleId,
        'model_id' => $users[UserRole::Admin->value]->id,
        'model_type' => (new User)->getMorphClass(),
    ]);
    $report = authzAnalyzer()->analyze();
    $applied = authzApply($report, authzRegistrar());
    $receipt = authzArtifactRepository()->loadBackfillReceipt($applied->receiptName);
    $rollback = new LegacyRoleBackfillRollback(authzAnalyzer(), authzArtifactRepository(), app(PrivacyHasher::class));
    $result = $rollback->rollback($receipt, $receipt->afterFingerprint(), 'ROLLBACK-AUTHZ1-C');

    expect($result->status)->toBe('rolled_back')
        ->and($result->deletedAssignments)->toBe(4)
        ->and(DB::table('roles')->count())->toBe(5)
        ->and(DB::table('model_has_roles')->count())->toBe(1)
        ->and($rollback->rollback($receipt, $receipt->afterFingerprint(), 'ROLLBACK-AUTHZ1-C')->status)->toBe('no_op');

    $reanalysis = authzAnalyzer()->analyze();
    expect($reanalysis->status())->toBe('ready')
        ->and(authzApply($reanalysis, authzRegistrar())->status)->toBe('applied')
        ->and(DB::table('model_has_roles')->count())->toBe(5);
});

it('keeps the complete five-role legacy authority matrix unchanged after apply and rollback', function (UserRole $role): void {
    $users = authzCreateLegacyUsers();
    $expected = [
        'panel' => $role->isAtLeast(UserRole::Admin),
        'horizon' => $role->isAtLeast(UserRole::Admin),
        'maintenance' => $role->isAtLeast(UserRole::Admin),
        'super_admin' => $role === UserRole::SuperAdmin,
        'multi_transcription_single_mode' => false,
        'multi_transcription_multi_admin_minimum' => $role->isAtLeast(UserRole::Admin),
        'multi_transcription_multi_super_minimum' => $role === UserRole::SuperAdmin,
    ];
    $snapshot = function () use ($users, $role): array {
        $user = $users[$role->value]->fresh();
        setTestTranscriptionMode(TranscriptionMode::Single);
        $singleMode = Gate::forUser($user)->allows('multi-transcription', [UserRole::Admin]);
        setTestTranscriptionMode(TranscriptionMode::Multi);
        $multiAdminMinimum = Gate::forUser($user)->allows('multi-transcription', [UserRole::Admin]);
        $multiSuperMinimum = Gate::forUser($user)->allows('multi-transcription', [UserRole::SuperAdmin]);
        setTestTranscriptionMode(TranscriptionMode::Single);

        return [
            'panel' => $user->canAccessPanel(Filament::getPanel('admin')),
            'horizon' => Gate::forUser($user)->allows('viewHorizon'),
            'maintenance' => $user->canAccessPanel(Filament::getPanel('admin')),
            'super_admin' => Gate::forUser($user)->allows('super-admin'),
            'multi_transcription_single_mode' => $singleMode,
            'multi_transcription_multi_admin_minimum' => $multiAdminMinimum,
            'multi_transcription_multi_super_minimum' => $multiSuperMinimum,
            'admin_http' => $this->actingAs($user)->get('/admin')->status(),
            'horizon_http' => $this->actingAs($user)->get('/horizon')->status(),
            'author_resource_http' => $this->actingAs($user)->get(AuthorResource::getUrl('index'))->status(),
            'admin_tools_http' => $this->actingAs($user)->get(AdminTools::getUrl())->status(),
            'user_resource_http' => $this->actingAs($user)->get(UserResource::getUrl('index'))->status(),
        ];
    };

    $expected += [
        'admin_http' => $expected['panel'] ? 200 : 403,
        'horizon_http' => $expected['horizon'] ? 200 : 403,
        'author_resource_http' => $expected['panel'] ? 200 : 403,
        'admin_tools_http' => $expected['panel'] ? 200 : 403,
        'user_resource_http' => $expected['super_admin'] ? 200 : 403,
    ];

    expect($snapshot())->toBe($expected);
    $report = authzAnalyzer()->analyze();
    $applied = authzApply($report, authzRegistrar());
    expect($snapshot())->toBe($expected);

    $receipt = authzArtifactRepository()->loadBackfillReceipt($applied->receiptName);
    (new LegacyRoleBackfillRollback(authzAnalyzer(), authzArtifactRepository(), app(PrivacyHasher::class)))
        ->rollback($receipt, $receipt->afterFingerprint(), 'ROLLBACK-AUTHZ1-C');
    expect($snapshot())->toBe($expected);
})->with('authz five roles');

it('executes analyze backfill and rollback only through accepted command fingerprints', function (): void {
    $users = authzCreateLegacyUsers();

    $analyzeExit = Artisan::call('authz:roles:analyze', ['--report' => 'command-analysis.json']);
    $analyzeOutput = Artisan::output();
    $report = authzArtifactRepository()->loadReport('command-analysis.json');

    expect($analyzeExit)->toBe(0)
        ->and($analyzeOutput)->toContain('source_fingerprint: '.$report->sourceFingerprint())
        ->toContain('report_fingerprint: '.$report->reportFingerprint())
        ->not->toContain($users[UserRole::Admin->value]->email)
        ->not->toContain((string) $users[UserRole::Admin->value]->id);

    Cache::put((string) config('permission.cache.key'), ['primed' => true]);

    $backfillExit = Artisan::call('authz:roles:backfill', [
        'report' => 'command-analysis.json',
        '--accept-source' => $report->sourceFingerprint(),
        '--accept-report' => $report->reportFingerprint(),
        '--confirm' => 'AUTHZ1-C',
    ]);
    $backfillOutput = Artisan::output();
    $receiptName = authzArtifactRepository()->backfillReceiptName($report->reportFingerprint());

    expect($backfillOutput)->toContain('AUTHZ1-C backfill status: applied')
        ->and($backfillExit)->toBe(0)
        ->and($backfillOutput)->toContain('receipt: '.$receiptName);

    $receipt = authzArtifactRepository()->loadBackfillReceipt($receiptName);

    $rollbackExit = Artisan::call('authz:roles:rollback', [
        'receipt' => $receiptName,
        '--accept-after' => $receipt->afterFingerprint(),
        '--confirm' => 'ROLLBACK-AUTHZ1-C',
    ]);

    expect($rollbackExit)->toBe(0)
        ->and(Artisan::output())->toContain('AUTHZ1-C rollback status: rolled_back')
        ->and(DB::table('roles')->count())->toBe(5)
        ->and(DB::table('model_has_roles')->count())->toBe(0);
});

it('keeps package assignments dormant and exposes only the three controlled commands', function (): void {
    expect(class_uses_recursive(User::class))->not->toContain(HasRoles::class)
        ->and(method_exists(User::class, 'roles'))->toBeFalse()
        ->and(config('permission.register_permission_check_method'))->toBeFalse()
        ->and(Artisan::all())->toHaveKeys([
            'authz:roles:analyze',
            'authz:roles:backfill',
            'authz:roles:rollback',
        ]);
});
