<?php

use App\Support\PublicFront\Forms\PublicFormDefinitionRegistry;
use App\Support\PublicFront\PublicFrontConfigRegistry;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $defaults = PublicFormDefinitionRegistry::rateLimitDefaults();

        if (! $this->migrator->exists('public_content.public_forms')) {
            $this->migrator->add('public_content.public_forms', PublicFrontConfigRegistry::defaults()['public_forms']);

            return;
        }

        $this->migrator->update('public_content.public_forms', function (mixed $publicForms) use ($defaults): array {
            if (! is_array($publicForms)) {
                return PublicFrontConfigRegistry::defaults()['public_forms'];
            }

            $definitions = collect($publicForms['definitions'] ?? [])
                ->filter(fn (mixed $definition): bool => is_array($definition))
                ->map(function (array $definition) use ($defaults): array {
                    $definition['settings'] = [
                        ...$defaults,
                        ...($definition['settings'] ?? []),
                    ];

                    return $definition;
                })
                ->values()
                ->all();

            return [
                ...$publicForms,
                'require_email_verification' => (bool) ($publicForms['require_email_verification'] ?? false),
                'definitions' => $definitions,
            ];
        });
    }

    public function down(): void
    {
        if (! $this->migrator->exists('public_content.public_forms')) {
            return;
        }

        $this->migrator->update('public_content.public_forms', function (mixed $publicForms): array {
            if (! is_array($publicForms)) {
                return [];
            }

            unset($publicForms['require_email_verification']);

            $publicForms['definitions'] = collect($publicForms['definitions'] ?? [])
                ->filter(fn (mixed $definition): bool => is_array($definition))
                ->map(function (array $definition): array {
                    unset($definition['settings']['submitter_email_verification']);

                    return $definition;
                })
                ->values()
                ->all();

            return $publicForms;
        });
    }
};
