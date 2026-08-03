<?php

use App\Settings\AdminUxSettings;
use App\Settings\PublicContentSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * A Spatie settings property with no stored row throws `MissingSettings` for
 * the whole group — every screen that loads it dies, not just the one reading
 * that property. A property default does not rescue this: the value loads, but
 * `ensureNoMissingSettings()` folds default-loaded properties into the *saving*
 * check, so the settings page then throws on save.
 *
 * The realistic cause is not a hand-deleted row. It is adding a property and
 * forgetting its seed migration. These tests turn that into a CI failure.
 *
 * @return array<int, string>
 */
function declaredSettingsProperties(string $settingsClass): array
{
    return collect((new ReflectionClass($settingsClass))->getProperties(ReflectionProperty::IS_PUBLIC))
        ->reject(fn (ReflectionProperty $property): bool => $property->isStatic())
        ->filter(fn (ReflectionProperty $property): bool => $property->getDeclaringClass()->getName() === $settingsClass)
        ->map(fn (ReflectionProperty $property): string => $property->getName())
        ->values()
        ->all();
}

/**
 * @return array<int, string>
 */
function storedSettingsRows(string $settingsClass): array
{
    return DB::table('settings')
        ->where('group', $settingsClass::group())
        ->pluck('name')
        ->all();
}

it('seeds a stored row for every declared settings property', function (string $settingsClass): void {
    $missing = array_values(array_diff(
        declaredSettingsProperties($settingsClass),
        storedSettingsRows($settingsClass),
    ));

    expect($missing)->toBe([], sprintf(
        '%s declares these properties with no seeded row, so loading the group throws MissingSettings: %s',
        class_basename($settingsClass),
        implode(', ', $missing),
    ));
})->with([
    AdminUxSettings::class,
    PublicContentSettings::class,
]);

it('leaves no stored row behind for a property that no longer exists', function (string $settingsClass): void {
    $orphans = array_values(array_diff(
        storedSettingsRows($settingsClass),
        declaredSettingsProperties($settingsClass),
    ));

    expect($orphans)->toBe([], sprintf(
        '%s has stored rows with no matching property. They are never read, so delete them in a settings migration: %s',
        class_basename($settingsClass),
        implode(', ', $orphans),
    ));
})->with([
    AdminUxSettings::class,
    PublicContentSettings::class,
]);

it('keeps every settings group loadable and saveable as migrated', function (string $settingsClass): void {
    $settings = app($settingsClass);

    expect($settings)->toBeInstanceOf($settingsClass);

    $settings->save();
})->with([
    AdminUxSettings::class,
    PublicContentSettings::class,
]);

/**
 * @return array<int, string>
 */
function enumTypedSettingsProperties(string $settingsClass): array
{
    return collect((new ReflectionClass($settingsClass))->getProperties(ReflectionProperty::IS_PUBLIC))
        ->reject(fn (ReflectionProperty $property): bool => $property->isStatic())
        ->filter(fn (ReflectionProperty $property): bool => $property->getDeclaringClass()->getName() === $settingsClass)
        ->filter(function (ReflectionProperty $property): bool {
            $type = $property->getType();

            $namedTypes = match (true) {
                $type instanceof ReflectionNamedType => [$type],
                $type instanceof ReflectionUnionType => $type->getTypes(),
                default => [],
            };

            foreach ($namedTypes as $namedType) {
                if ($namedType instanceof ReflectionNamedType && ! $namedType->isBuiltin() && enum_exists($namedType->getName())) {
                    return true;
                }
            }

            return false;
        })
        ->map(fn (ReflectionProperty $property): string => $property->getName())
        ->values()
        ->all();
}

it('keeps raw-payload settings writers pointed at raw-assignable properties only', function (): void {
    // SettingsBackupManager::applyPayload() and NormalizePublicContentSettings
    // assign decoded JSON straight to `$settings->{$property}` with no cast
    // layer. That is safe exactly as long as every PublicContentSettings
    // property accepts its raw payload value; an enum-typed property would
    // TypeError on the rarely-exercised restore/normalize paths.
    $enumTyped = enumTypedSettingsProperties(PublicContentSettings::class);

    expect($enumTyped)->toBe([], sprintf(
        'PublicContentSettings now has enum-typed properties (%s), but SettingsBackupManager::applyPayload() and NormalizePublicContentSettings assign raw JSON payloads to it. Route those writers through casts before typing the properties.',
        implode(', ', $enumTyped),
    ));

    // Detector sanity: AdminUxSettings HAS enum-typed properties (E5), so an
    // empty result above cannot be the detector failing to detect.
    expect(enumTypedSettingsProperties(AdminUxSettings::class))->not->toBe([]);
});
