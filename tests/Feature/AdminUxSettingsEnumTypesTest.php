<?php

use App\Enums\ImageUploadPurpose;
use App\Enums\MediaAcquisitionFilenameStrategy;
use App\Enums\MediaNamingStrategy;
use App\Enums\TranscriptionMode;
use App\Enums\TranscriptionPresentationMode;
use App\Filament\Pages\AdminUxSettings as AdminUxSettingsPage;
use App\Filament\Resources\ContentGroups\Pages\ListContentGroups;
use App\Filament\Resources\ContentItems\Pages\EditEpisodeWorkspace;
use App\Models\ContentItem;
use App\Models\Transcription;
use App\Models\User;
use App\Settings\AdminUxSettings;
use App\Support\Media\MediaAcquisitionNamer;
use App\Support\Media\ValidatedImage;
use App\Support\Transcriptions\MultiTranscriptionSurfaces;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Schemas\Components\Section;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\Assert;
use Livewire\Livewire;
use Spatie\LaravelSettings\SettingsContainer;

uses(RefreshDatabase::class);

function clearAdminUxSettingsCache(): void
{
    app()->forgetInstance(AdminUxSettings::class);
    app(SettingsContainer::class)->clearCache();
}

function writeRawAdminUxSetting(string $name, mixed $payload): void
{
    DB::table('settings')->updateOrInsert(
        [
            'group' => AdminUxSettings::group(),
            'name' => $name,
        ],
        [
            'locked' => false,
            'payload' => json_encode($payload),
            'created_at' => now(),
            'updated_at' => now(),
        ],
    );

    clearAdminUxSettingsCache();
}

it('types every enum-backed admin ux setting as its enum rather than a string', function (): void {
    clearAdminUxSettingsCache();

    $settings = app(AdminUxSettings::class);

    expect($settings->media_naming_strategy)->toBeInstanceOf(MediaNamingStrategy::class)
        ->and($settings->media_acquisition_filename_strategy)->toBeInstanceOf(MediaAcquisitionFilenameStrategy::class)
        ->and($settings->transcription_presentation_mode)->toBeInstanceOf(TranscriptionPresentationMode::class)
        ->and($settings->transcription_mode)->toBeInstanceOf(TranscriptionMode::class);
});

it('drops the retired tb1 picker container setting without breaking the group', function (): void {
    expect(DB::table('settings')
        ->where('group', AdminUxSettings::group())
        ->where('name', 'tb1_picker_container')
        ->exists())->toBeFalse()
        ->and(property_exists(AdminUxSettings::class, 'tb1_picker_container'))->toBeFalse()
        ->and(file_exists(app_path('Enums/Tb1PickerContainer.php')))->toBeFalse();

    clearAdminUxSettingsCache();

    $settings = app(AdminUxSettings::class);
    $settings->media_naming_strategy = MediaNamingStrategy::Title;
    $settings->save();

    clearAdminUxSettingsCache();

    expect(app(AdminUxSettings::class)->media_naming_strategy)->toBe(MediaNamingStrategy::Title);
});

it('leaves no translation keys behind for the retired tb1 picker container', function (): void {
    foreach (['en', 'he'] as $locale) {
        $admin = require lang_path("{$locale}/admin.php");

        expect($admin['fields'])->not->toHaveKey('tb1_picker_container')
            ->and($admin['helpers'])->not->toHaveKey('tb1_picker_container')
            ->and($admin)->not->toHaveKey('tb1_picker_containers');
    }
});

it('stores enum-backed admin ux settings as scalar payloads and reads them back as enums', function (): void {
    clearAdminUxSettingsCache();

    $settings = app(AdminUxSettings::class);
    $settings->media_naming_strategy = MediaNamingStrategy::SlugKey;
    $settings->transcription_mode = TranscriptionMode::Multi;
    $settings->save();

    clearAdminUxSettingsCache();

    $payload = DB::table('settings')
        ->where('group', AdminUxSettings::group())
        ->whereIn('name', ['media_naming_strategy', 'transcription_mode'])
        ->pluck('payload', 'name');

    expect(json_decode((string) $payload['media_naming_strategy']))->toBe('slug_key')
        ->and(json_decode((string) $payload['transcription_mode']))->toBe('multi')
        ->and(app(AdminUxSettings::class)->media_naming_strategy)->toBe(MediaNamingStrategy::SlugKey)
        ->and(app(AdminUxSettings::class)->transcription_mode)->toBe(TranscriptionMode::Multi);
});

it('saves enum-backed admin ux settings through the filament settings page', function (): void {
    $this->actingAs(User::factory()->superAdmin()->create());

    Livewire::test(AdminUxSettingsPage::class)
        ->set('data.media_naming_strategy', MediaNamingStrategy::ReferenceKey->value)
        ->set('data.media_acquisition_filename_strategy', MediaAcquisitionFilenameStrategy::CleanedOriginal->value)
        ->set('data.transcription_presentation_mode', TranscriptionPresentationMode::Modal->value)
        ->set('data.transcription_mode', TranscriptionMode::Multi->value)
        ->call('save')
        ->assertHasNoFormErrors();

    clearAdminUxSettingsCache();

    $settings = app(AdminUxSettings::class);

    expect($settings->media_naming_strategy)->toBe(MediaNamingStrategy::ReferenceKey)
        ->and($settings->media_acquisition_filename_strategy)->toBe(MediaAcquisitionFilenameStrategy::CleanedOriginal)
        ->and($settings->transcription_presentation_mode)->toBe(TranscriptionPresentationMode::Modal)
        ->and($settings->transcription_mode)->toBe(TranscriptionMode::Multi);
});

it('keeps multi transcription mode detection working against the typed property', function (): void {
    writeRawAdminUxSetting('transcription_mode', TranscriptionMode::Multi->value);

    expect(MultiTranscriptionSurfaces::isMultiMode())->toBeTrue();

    writeRawAdminUxSetting('transcription_mode', TranscriptionMode::Single->value);

    expect(MultiTranscriptionSurfaces::isMultiMode())->toBeFalse();
});

it('defaults the image export naming strategy from the typed setting instead of silently falling back', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(User::factory()->admin()->create());

    writeRawAdminUxSetting('media_naming_strategy', MediaNamingStrategy::ReferenceKey->value);

    Livewire::test(ListContentGroups::class)
        ->mountAction(TestAction::make('downloadContentImages')->table())
        ->assertSchemaStateSet([
            'media_naming_strategy' => MediaNamingStrategy::ReferenceKey,
        ], schema: 'mountedActionSchema0');
});

it('honours the typed filename strategy when naming acquired media', function (): void {
    writeRawAdminUxSetting(
        'media_acquisition_filename_strategy',
        MediaAcquisitionFilenameStrategy::CleanedOriginal->value,
    );

    $namer = app(MediaAcquisitionNamer::class);

    $destination = $namer->destination(new ValidatedImage(
        purpose: ImageUploadPurpose::ContentGroupCover,
        contents: 'x',
        mimeType: 'image/jpeg',
        extension: 'jpg',
        size: 1,
        width: 10,
        height: 10,
        sha256: hash('sha256', 'x'),
        originalFilename: 'My Original Cover.jpeg',
    ));

    expect($destination)->toMatch('#^content-groups/covers/my-original-cover-[0-9A-HJKMNP-TV-Z]{26}\.jpg$#');
});

it('keeps the workspace transcription section collapsible and its data attributes scalar', function (): void {
    $this->actingAs(User::factory()->superAdmin()->create());

    writeRawAdminUxSetting('transcription_presentation_mode', TranscriptionPresentationMode::Collapsible->value);
    writeRawAdminUxSetting('transcription_mode', TranscriptionMode::Single->value);

    $item = ContentItem::factory()->create();
    Transcription::factory()->for($item)->create();

    Livewire::test(EditEpisodeWorkspace::class, [
        'record' => $item->getRouteKey(),
    ])
        ->assertSee('data-transcription-presentation-mode="collapsible"', false)
        ->assertSee('data-transcription-mode="single"', false)
        ->assertSchemaComponentExists(
            'workspaceTranscriptionSection',
            checkComponentUsing: function (Section $component): bool {
                Assert::assertTrue(
                    $component->isCollapsible(),
                    'Failed asserting that the transcription section is collapsible in collapsible presentation mode.',
                );

                return true;
            },
        );
});

it('drops the collapsible affordance in the other presentation modes', function (): void {
    $this->actingAs(User::factory()->superAdmin()->create());

    writeRawAdminUxSetting('transcription_presentation_mode', TranscriptionPresentationMode::Modal->value);

    $item = ContentItem::factory()->create();
    Transcription::factory()->for($item)->create();

    Livewire::test(EditEpisodeWorkspace::class, [
        'record' => $item->getRouteKey(),
    ])
        ->assertSchemaComponentExists(
            'workspaceTranscriptionSection',
            checkComponentUsing: function (Section $component): bool {
                Assert::assertFalse(
                    $component->isCollapsible(),
                    'Failed asserting that the transcription section is not collapsible in modal presentation mode.',
                );

                return true;
            },
        );
});

it('repairs unrecognised stored values so the enum cast cannot fail on load', function (): void {
    writeRawAdminUxSetting('media_naming_strategy', 'a_strategy_that_no_longer_exists');
    writeRawAdminUxSetting('transcription_mode', '');

    $migration = require database_path('settings/2026_08_01_000000_type_admin_ux_enum_settings.php');
    $migration->up();

    clearAdminUxSettingsCache();

    $settings = app(AdminUxSettings::class);

    expect($settings->media_naming_strategy)->toBe(MediaNamingStrategy::Slug)
        ->and($settings->transcription_mode)->toBe(TranscriptionMode::Single);
});
