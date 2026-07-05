<?php

namespace App\Support\PublicFront\Forms;

use App\Enums\PublicFormFieldType;

class PublicFormDefinitionRegistry
{
    /**
     * @return array<string>
     */
    public static function fieldTypes(): array
    {
        return array_map(
            fn (PublicFormFieldType $type): string => $type->value,
            PublicFormFieldType::cases(),
        );
    }

    /**
     * @return array<string, string>
     */
    public static function fieldTypeOptions(): array
    {
        return collect(PublicFormFieldType::cases())
            ->mapWithKeys(fn (PublicFormFieldType $type): array => [$type->value => $type->getLabel()])
            ->all();
    }

    /**
     * @return array<string>
     */
    public static function displayModes(): array
    {
        return ['modal', 'slide_over'];
    }

    /**
     * @return array<string, string>
     */
    public static function displayModeOptions(): array
    {
        return [
            'modal' => __('admin.public_form_display_modes.modal'),
            'slide_over' => __('admin.public_form_display_modes.slide_over'),
        ];
    }

    /**
     * @return array<string>
     */
    public static function validationSemantics(): array
    {
        return [
            'none',
            'person_name',
            'organization',
            'message',
            'email',
            'phone',
            'url',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function validationSemanticOptions(): array
    {
        return collect(self::validationSemantics())
            ->mapWithKeys(fn (string $value): array => [$value => __("admin.public_form_validation_semantics.{$value}")])
            ->all();
    }

    /**
     * @return array<string, int>
     */
    public static function rateLimitDefaults(): array
    {
        return [
            'rate_limit_attempts' => 5,
            'rate_limit_decay_seconds' => 600,
        ];
    }

    public static function defaultSubmitLabel(): string
    {
        return __('public.forms.submit');
    }

    public static function defaultSuccessMessage(): string
    {
        return __('public.forms.success');
    }
}
