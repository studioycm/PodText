<?php

use App\Enums\TranscriptionMode;
use App\Enums\UserRole;
use App\Filament\Pages\AdminTools;
use App\Filament\Resources\Authors\AuthorResource;
use App\Filament\Resources\Authors\Pages\ListAuthors;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\UserResource;
use App\Models\Author;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Http::preventStrayRequests();
});

function authz1LegacySnapshot(string $html, string $componentName): string
{
    preg_match_all('/wire:snapshot="([^"]+)"/u', $html, $matches);
    $componentNames = [];

    foreach ($matches[1] ?? [] as $encodedSnapshot) {
        $snapshot = htmlspecialchars_decode($encodedSnapshot, ENT_QUOTES);
        $payload = json_decode($snapshot, true, flags: JSON_THROW_ON_ERROR);
        $snapshotComponentName = (string) ($payload['memo']['name'] ?? '');
        $componentNames[] = $snapshotComponentName;

        if ($snapshotComponentName === $componentName) {
            return $snapshot;
        }
    }

    throw new RuntimeException(sprintf(
        'No Livewire snapshot named [%s] was found. Available: %s',
        $componentName,
        implode(', ', $componentNames),
    ));
}

it('keeps exact five-role Admin panel admission independent of package definitions', function (UserRole $role, bool $withPackageDefinitions): void {
    seedAuthzPackageDefinitions($withPackageDefinitions);

    $user = User::factory()->role($role)->create();
    $allowed = $role->isAtLeast(UserRole::Admin);

    expect($user->canAccessPanel(Filament::getPanel('admin')))->toBe($allowed);

    $response = $this->actingAs($user)->get('/admin');

    $allowed ? $response->assertOk() : $response->assertForbidden();

    expectAuthzPackageAssignmentsEmpty();
})->with('authz five roles')->with('authz package definition states');

it('keeps the exact five-role legacy Gate matrix independent of package definitions', function (UserRole $role, bool $withPackageDefinitions): void {
    seedAuthzPackageDefinitions($withPackageDefinitions);

    $user = User::factory()->role($role)->create();

    setTestTranscriptionMode(TranscriptionMode::Single);

    expect(Gate::forUser($user)->allows('super-admin'))->toBe($role === UserRole::SuperAdmin)
        ->and(Gate::forUser($user)->allows('multi-transcription', [UserRole::Admin]))->toBeFalse()
        ->and(Gate::forUser($user)->allows('multi-transcription', [UserRole::SuperAdmin]))->toBeFalse();

    setTestTranscriptionMode(TranscriptionMode::Multi);

    expect(Gate::forUser($user)->allows('multi-transcription', [UserRole::Admin]))
        ->toBe($role->isAtLeast(UserRole::Admin))
        ->and(Gate::forUser($user)->allows('multi-transcription', [UserRole::SuperAdmin]))
        ->toBe($role === UserRole::SuperAdmin);

    expectAuthzPackageAssignmentsEmpty();
})->with('authz five roles')->with('authz package definition states');

it('keeps ordinary Author Resource direct and real Livewire access at the Admin panel perimeter', function (UserRole $role, bool $withPackageDefinitions): void {
    seedAuthzPackageDefinitions($withPackageDefinitions);

    $author = Author::factory()->create();
    $actor = User::factory()->role($role)->create();
    $allowed = $role->isAtLeast(UserRole::Admin);
    $url = AuthorResource::getUrl('index');

    $directResponse = $this->actingAs($actor)->get($url);
    $allowed ? $directResponse->assertOk() : $directResponse->assertForbidden();

    $snapshotOwner = $allowed ? $actor : User::factory()->admin()->create();
    $snapshotResponse = $this->actingAs($snapshotOwner)->get($url)->assertOk();
    $snapshot = authz1LegacySnapshot($snapshotResponse->getContent(), ListAuthors::class);

    $livewireResponse = $this->actingAs($actor)
        ->withHeader('X-Livewire', 'true')
        ->postJson(app('livewire')->getUpdateUri(), [
            'components' => [[
                'snapshot' => $snapshot,
                'updates' => [],
                'calls' => [],
            ]],
        ]);

    if ($allowed) {
        $livewireResponse->assertOk();

        expect($livewireResponse->json('components.0.effects'))->toBeArray();

        Livewire::test(ListAuthors::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$author]);
    } else {
        $livewireResponse->assertForbidden();
    }

    expectAuthzPackageAssignmentsEmpty();
})->with('authz five roles')->with('authz package definition states');

it('keeps the Admin Tools direct and real Livewire page at the Admin panel perimeter', function (UserRole $role, bool $withPackageDefinitions): void {
    seedAuthzPackageDefinitions($withPackageDefinitions);

    $actor = User::factory()->role($role)->create();
    $allowed = $role->isAtLeast(UserRole::Admin);
    $url = AdminTools::getUrl();

    $directResponse = $this->actingAs($actor)->get($url);
    $allowed ? $directResponse->assertOk() : $directResponse->assertForbidden();

    $snapshotOwner = $allowed ? $actor : User::factory()->admin()->create();
    $snapshotResponse = $this->actingAs($snapshotOwner)->get($url)->assertOk();
    $snapshot = authz1LegacySnapshot($snapshotResponse->getContent(), AdminTools::class);

    $livewireResponse = $this->actingAs($actor)
        ->withHeader('X-Livewire', 'true')
        ->postJson(app('livewire')->getUpdateUri(), [
            'components' => [[
                'snapshot' => $snapshot,
                'updates' => [],
                'calls' => [],
            ]],
        ]);

    if ($allowed) {
        $livewireResponse->assertOk();

        expect($livewireResponse->json('components.0.effects'))->toBeArray();

        Livewire::test(AdminTools::class)->assertOk();
    } else {
        $livewireResponse->assertForbidden();
    }

    expectAuthzPackageAssignmentsEmpty();
})->with('authz five roles')->with('authz package definition states');

it('keeps User Resource direct, Livewire, record-action, and save access Super-only', function (UserRole $role, bool $withPackageDefinitions): void {
    seedAuthzPackageDefinitions($withPackageDefinitions);

    $target = User::factory()->role(UserRole::User)->create();
    $actor = User::factory()->role($role)->create();
    $allowed = $role === UserRole::SuperAdmin;
    $indexUrl = UserResource::getUrl('index');
    $editUrl = UserResource::getUrl('edit', ['record' => $target]);

    $indexResponse = $this->actingAs($actor)->get($indexUrl);
    $editResponse = $this->actingAs($actor)->get($editUrl);

    if ($allowed) {
        $indexResponse->assertOk();
        $editResponse->assertOk();
    } else {
        $indexResponse->assertForbidden();
        $editResponse->assertForbidden();
    }

    expect(UserResource::canViewAny())->toBe($allowed)
        ->and(UserResource::canEdit($target))->toBe($allowed);

    $snapshotOwner = $allowed ? $actor : User::factory()->superAdmin()->create();
    $listSnapshotResponse = $this->actingAs($snapshotOwner)->get($indexUrl)->assertOk();
    $editSnapshotResponse = $this->actingAs($snapshotOwner)->get($editUrl)->assertOk();
    $listSnapshot = authz1LegacySnapshot($listSnapshotResponse->getContent(), ListUsers::class);
    $editSnapshot = authz1LegacySnapshot($editSnapshotResponse->getContent(), EditUser::class);

    $listLivewireResponse = $this->actingAs($actor)
        ->withHeader('X-Livewire', 'true')
        ->postJson(app('livewire')->getUpdateUri(), [
            'components' => [[
                'snapshot' => $listSnapshot,
                'updates' => [],
                'calls' => [],
            ]],
        ]);

    $editLivewireResponse = $this->actingAs($actor)
        ->withHeader('X-Livewire', 'true')
        ->postJson(app('livewire')->getUpdateUri(), [
            'components' => [[
                'snapshot' => $editSnapshot,
                'updates' => [],
                'calls' => [],
            ]],
        ]);

    $saveLivewireResponse = $this->actingAs($actor)
        ->withHeader('X-Livewire', 'true')
        ->postJson(app('livewire')->getUpdateUri(), [
            'components' => [[
                'snapshot' => $editSnapshot,
                'updates' => ['data.role' => UserRole::Moderator->value],
                'calls' => [
                    ['method' => 'save', 'params' => []],
                ],
            ]],
        ]);

    if ($allowed) {
        $listLivewireResponse->assertOk();
        $editLivewireResponse->assertOk();
        $saveLivewireResponse->assertOk();

        Livewire::test(ListUsers::class)
            ->assertOk()
            ->assertActionExists(TestAction::make('edit')->table($target));

        Livewire::test(EditUser::class, ['record' => $target->getRouteKey()])
            ->assertOk();

        expect($target->refresh()->role)->toBe(UserRole::Moderator);
    } else {
        $listLivewireResponse->assertForbidden();
        $editLivewireResponse->assertForbidden();
        $saveLivewireResponse->assertForbidden();

        Livewire::test(ListUsers::class)->assertForbidden();
        Livewire::test(EditUser::class, ['record' => $target->getRouteKey()])->assertForbidden();

        expect($target->refresh()->role)->toBe(UserRole::User);
    }

    expectAuthzPackageAssignmentsEmpty();
})->with('authz five roles')->with('authz package definition states');
