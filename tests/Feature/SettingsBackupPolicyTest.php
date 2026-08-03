<?php

use App\Enums\SettingsBackupSource;
use App\Filament\Resources\SettingsBackups\Pages\ListSettingsBackups;
use App\Models\SettingsBackupVersion;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

function settingsBackupRow(): SettingsBackupVersion
{
    return SettingsBackupVersion::query()->create([
        'scope' => 'public_content',
        'label' => 'Policy test backup',
        'payload_json' => json_encode(['settings' => []], JSON_THROW_ON_ERROR),
        'checksum' => hash('sha256', 'policy-test'),
        'payload_hash' => hash('sha256', 'policy-test'),
        'source' => SettingsBackupSource::Manual,
    ]);
}

it('scopes settings-backup authority through a real policy', function (): void {
    $backup = settingsBackupRow();

    // The table DeleteAction authorizes through the model policy, so the
    // authority must be explicit — not the non-strict allow-on-missing
    // default. Delete follows the CuratorMediaPolicy convention: ordinary
    // destructive maintenance belongs to panel admins (the panel gate is
    // already admin-or-above); creation stays impossible because backups are
    // written by the lifecycle managers, never by hand.
    expect(Gate::getPolicyFor(SettingsBackupVersion::class))->not->toBeNull()
        ->and(Gate::forUser(User::factory()->admin()->create())->allows('delete', $backup))->toBeTrue()
        ->and(Gate::forUser(User::factory()->superAdmin()->create())->allows('delete', $backup))->toBeTrue()
        ->and(Gate::forUser(User::factory()->moderator()->create())->allows('delete', $backup))->toBeFalse()
        ->and(Gate::forUser(User::factory()->admin()->create())->allows('viewAny', SettingsBackupVersion::class))->toBeTrue()
        ->and(Gate::forUser(User::factory()->admin()->create())->allows('view', $backup))->toBeTrue()
        ->and(Gate::forUser(User::factory()->admin()->create())->allows('create', SettingsBackupVersion::class))->toBeFalse()
        ->and(Gate::forUser(User::factory()->admin()->create())->allows('update', $backup))->toBeFalse();
});

it('still lets a panel admin delete a backup through the table action', function (): void {
    $backup = settingsBackupRow();

    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(ListSettingsBackups::class)
        ->callAction(TestAction::make('delete')->table($backup));

    expect(SettingsBackupVersion::query()->whereKey($backup->getKey())->exists())->toBeFalse();
});
