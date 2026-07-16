<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Http::preventStrayRequests();
});

it('authorizes the Horizon gate and HTTP dashboard through the exact five-role panel contract', function (UserRole $role, bool $withPackageDefinitions): void {
    seedAuthzPackageDefinitions($withPackageDefinitions);

    $user = User::factory()->role($role)->create();
    $allowed = $role->isAtLeast(UserRole::Admin);

    expect(Gate::forUser($user)->allows('viewHorizon'))->toBe($allowed);

    $response = $this->actingAs($user)->get('/horizon');

    $allowed ? $response->assertOk() : $response->assertForbidden();

    expectAuthzPackageAssignmentsEmpty();
})->with('authz five roles')->with('authz package definition states');

it('denies Horizon to guests', function (): void {
    expect(Gate::allows('viewHorizon'))->toBeFalse();

    $this->get('/horizon')->assertForbidden();
});
