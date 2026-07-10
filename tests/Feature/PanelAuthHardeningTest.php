<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

it('authorizes horizon through the admin panel access contract', function (): void {
    $user = User::factory()->create();

    expect(Gate::forUser($user)->allows('viewHorizon'))->toBeTrue();

    auth()->logout();

    expect(Gate::allows('viewHorizon'))->toBeFalse();
});
