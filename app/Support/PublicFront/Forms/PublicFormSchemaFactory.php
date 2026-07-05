<?php

namespace App\Support\PublicFront\Forms;

class PublicFormSchemaFactory
{
    /**
     * @param  array<string, mixed>|null  $definition
     * @return array<int, array<string, mixed>>
     */
    public function fields(?array $definition): array
    {
        if ($definition === null) {
            return [];
        }

        return collect($definition['fields'] ?? [])
            ->filter(fn (mixed $field): bool => is_array($field))
            ->map(function (array $field): array {
                $type = $field['type'] ?? 'text';

                return [
                    'key' => (string) ($field['key'] ?? ''),
                    'type' => $type,
                    'label' => (string) ($field['label'] ?? $field['key'] ?? ''),
                    'placeholder' => (string) ($field['placeholder'] ?? ''),
                    'help_text' => (string) ($field['help_text'] ?? ''),
                    'required' => (bool) ($field['required'] ?? false),
                    'options' => is_array($field['options'] ?? null) ? $field['options'] : [],
                    'min_length' => $field['min_length'] ?? null,
                    'max_length' => $field['max_length'] ?? null,
                    'validation_semantics' => $field['validation_semantics'] ?? 'none',
                    'is_option_field' => in_array($type, ['select', 'checkbox'], true),
                    'is_boolean_field' => in_array($type, ['toggle'], true)
                        || ($type === 'checkbox' && blank($field['options'] ?? [])),
                ];
            })
            ->filter(fn (array $field): bool => filled($field['key']))
            ->values()
            ->all();
    }
}
