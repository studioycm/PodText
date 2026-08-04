<?php

use App\Enums\ImportConnectionProvider;
use App\Enums\TranscriptionMode;
use App\Enums\UserRole;
use App\Filament\Resources\Imports\Pages\ListImports;
use App\Models\User;
use Filament\Actions\Imports\Models\Import;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    setTestTranscriptionMode(TranscriptionMode::Multi);
    Storage::fake('public');
    $this->actingAs(User::factory()->admin()->create());
});

it('lists imports read-only for admins with honest failure counts', function (): void {
    $legacy = failedImport(failed: 2, total: 5);
    $declared = failedImport(failed: 1, fileName: 'drive.csv');
    $declared->forceFill([
        'provider' => 'google_drive',
        'name' => 'Drive backfill',
    ])->save();

    // Column-state assertions, not assertSee: the provider filter's own
    // option labels sit in the page HTML, so a bare assertSee on a label
    // passes vacuously no matter what the column renders.
    Livewire::test(ListImports::class)
        ->assertCanSeeTableRecords(Import::all())
        // The display rule: name ?: file_name.
        ->assertTableColumnStateSet('name', 'episodes.csv', record: $legacy)
        ->assertTableColumnStateSet('name', 'Drive backfill', record: $declared)
        // null provider reads as manual — the one home rule.
        ->assertTableColumnStateSet('provider', ImportConnectionProvider::Manual->getLabel(), record: $legacy)
        ->assertTableColumnStateSet('provider', ImportConnectionProvider::GoogleDrive->getLabel(), record: $declared);

    foreach (['en', 'he'] as $locale) {
        foreach (['singular', 'plural', 'navigation'] as $key) {
            expect(Lang::hasForLocale("admin.resources.imports.{$key}", $locale))->toBeTrue();
        }
    }
});

it('denies the listing to non-admins', function (): void {
    // The user factory defaults to the admin role — the plain role must be
    // explicit or this asserts nothing.
    $this->actingAs(User::factory()->role(UserRole::User)->create());

    Livewire::test(ListImports::class)
        ->assertForbidden();
});

it('links the signed failure CSV from the row action', function (): void {
    failedImport();

    Livewire::test(ListImports::class)
        ->assertSeeHtml('failed-rows/download')
        ->assertSeeHtml('signature=');
});
