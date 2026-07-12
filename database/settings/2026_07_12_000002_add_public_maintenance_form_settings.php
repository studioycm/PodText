<?php

use App\Support\PublicFront\Maintenance\MaintenanceForm;
use App\Support\PublicFront\PublicFrontConfigRegistry;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $defaults = [
            'form_key' => null,
            'form_location' => MaintenanceForm::LOCATION_RENDERED_PAGE,
            'form_position' => MaintenanceForm::POSITION_AFTER_CONTENT,
        ];

        if (! $this->migrator->exists('public_content.maintenance')) {
            $this->migrator->add('public_content.maintenance', [
                ...PublicFrontConfigRegistry::defaults()['maintenance'],
                ...$defaults,
            ]);

            return;
        }

        $this->migrator->update('public_content.maintenance', function (mixed $maintenance) use ($defaults): array {
            if (! is_array($maintenance)) {
                return [
                    ...PublicFrontConfigRegistry::defaults()['maintenance'],
                    ...$defaults,
                ];
            }

            return [
                ...PublicFrontConfigRegistry::defaults()['maintenance'],
                ...$maintenance,
                ...$defaults,
            ];
        });
    }

    public function down(): void
    {
        if (! $this->migrator->exists('public_content.maintenance')) {
            return;
        }

        $this->migrator->update('public_content.maintenance', function (mixed $maintenance): array {
            if (! is_array($maintenance)) {
                return [];
            }

            unset(
                $maintenance['form_key'],
                $maintenance['form_location'],
                $maintenance['form_position'],
            );

            return $maintenance;
        });
    }
};
