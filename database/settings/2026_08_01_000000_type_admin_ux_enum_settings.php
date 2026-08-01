<?php

use App\Enums\MediaAcquisitionFilenameStrategy;
use App\Enums\MediaNamingStrategy;
use App\Enums\TranscriptionMode;
use App\Enums\TranscriptionPresentationMode;
use App\Settings\AdminUxSettings;
use Spatie\LaravelSettings\Factories\SettingsRepositoryFactory;
use Spatie\LaravelSettings\Migrations\SettingsMigration;
use Spatie\LaravelSettings\SettingsRepositories\SettingsRepository;

/**
 * These five settings are now typed as their enums on `AdminUxSettings`.
 *
 * Spatie casts them with `EnumCast`, which resolves stored payloads through
 * `from()` rather than `tryFrom()` — so a payload matching no case throws a
 * `ValueError` on every request that loads the group, not only on the screen
 * that reads it. Well-formed rows already hold the backing values and pass
 * through untouched; this migration exists to repair the rest.
 *
 * It talks to the settings repository rather than `$this->migrator`, because
 * `SettingsMigrator::getPropertyPayload()` applies the cast while reading —
 * so a migrator-based repair would throw on precisely the values it is meant
 * to fix. The repository is the layer below the cast.
 */
return new class extends SettingsMigration
{
    /**
     * Enum class, then the value to fall back to, per setting name.
     *
     * @var array<string, array{class-string<BackedEnum>, string}>
     */
    private const TYPED_PROPERTIES = [
        'media_naming_strategy' => [MediaNamingStrategy::class, 'slug'],
        'media_acquisition_filename_strategy' => [MediaAcquisitionFilenameStrategy::class, 'app_generated'],
        'transcription_presentation_mode' => [TranscriptionPresentationMode::class, 'collapsible'],
        'transcription_mode' => [TranscriptionMode::class, 'single'],
    ];

    public function up(): void
    {
        $group = AdminUxSettings::group();
        $repository = $this->repository();
        $repaired = [];

        foreach (self::TYPED_PROPERTIES as $name => [$enum, $fallback]) {
            if (! $repository->checkIfPropertyExists($group, $name)) {
                $repository->createProperty($group, $name, $fallback);

                continue;
            }

            $payload = $repository->getPropertyPayload($group, $name);

            if (is_string($payload) && $enum::tryFrom($payload) instanceof $enum) {
                continue;
            }

            $repaired[$name] = $fallback;
        }

        if ($repaired !== []) {
            $repository->updatePropertiesPayload($group, $repaired);
        }
    }

    public function down(): void
    {
        // Payloads stay scalar in both directions; there is nothing to undo.
    }

    private function repository(): SettingsRepository
    {
        return SettingsRepositoryFactory::create(AdminUxSettings::repository());
    }
};
