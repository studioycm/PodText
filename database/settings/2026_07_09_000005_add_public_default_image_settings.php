<?php

use App\Support\PublicFront\PublicFrontConfigRegistry;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $defaults = PublicFrontConfigRegistry::defaults()['default_images'];

        if ($this->migrator->exists('public_content.default_images')) {
            $this->migrator->update('public_content.default_images', function (mixed $defaultImages) use ($defaults): array {
                $defaultImages = is_object($defaultImages) ? (array) $defaultImages : $defaultImages;
                $defaultImages = is_array($defaultImages) ? $defaultImages : [];

                return collect($defaults)
                    ->mapWithKeys(function (array $familyDefaults, string $family) use ($defaultImages): array {
                        $familyConfig = $defaultImages[$family] ?? [];
                        $familyConfig = is_object($familyConfig) ? (array) $familyConfig : $familyConfig;
                        $familyConfig = is_array($familyConfig) ? $familyConfig : [];

                        return [
                            $family => [
                                ...$familyDefaults,
                                ...array_intersect_key($familyConfig, $familyDefaults),
                            ],
                        ];
                    })
                    ->all();
            });

            return;
        }

        $this->migrator->add('public_content.default_images', $defaults);
    }

    public function down(): void
    {
        $this->migrator->deleteIfExists('public_content.default_images');
    }
};
