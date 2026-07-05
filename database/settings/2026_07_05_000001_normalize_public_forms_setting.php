<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->update('public_content.public_forms', function (mixed $publicForms): array {
            $publicForms = is_object($publicForms) ? (array) $publicForms : $publicForms;

            if (! is_array($publicForms)) {
                return [
                    'definitions' => [],
                ];
            }

            if (array_key_exists('definitions', $publicForms) && is_array($publicForms['definitions'])) {
                return $publicForms;
            }

            return [
                'definitions' => array_is_list($publicForms) ? $publicForms : [],
            ];
        });
    }

    public function down(): void
    {
        $this->migrator->update('public_content.public_forms', function (mixed $publicForms): array {
            $publicForms = is_object($publicForms) ? (array) $publicForms : $publicForms;

            if (! is_array($publicForms)) {
                return [];
            }

            if (array_key_exists('definitions', $publicForms) && is_array($publicForms['definitions'])) {
                return $publicForms['definitions'];
            }

            return $publicForms;
        });
    }
};
