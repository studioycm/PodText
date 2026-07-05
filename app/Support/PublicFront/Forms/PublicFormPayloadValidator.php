<?php

namespace App\Support\PublicFront\Forms;

use Closure;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PublicFormPayloadValidator
{
    /**
     * @param  array<string, mixed>  $definition
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function validate(array $definition, array $payload): array
    {
        $rules = $this->rules($definition);

        $validated = Validator::make(
            Arr::only($payload, array_keys($rules)),
            $rules,
            attributes: $this->attributes($definition),
        )->validate();

        return $this->sanitizePayload($definition, $validated);
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array<string, array<int, mixed>>
     */
    public function rules(array $definition): array
    {
        $rules = [];

        foreach ($definition['fields'] ?? [] as $field) {
            if (! is_array($field) || blank($field['key'] ?? null)) {
                continue;
            }

            $key = (string) $field['key'];
            $rules[$key] = $this->rulesForField($field);

            if (($field['type'] ?? null) === 'checkbox' && $this->optionValues($field) !== []) {
                $rules["{$key}.*"] = [Rule::in($this->optionValues($field))];
            }
        }

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array<string, string>
     */
    public function attributes(array $definition): array
    {
        return collect($definition['fields'] ?? [])
            ->filter(fn (mixed $field): bool => is_array($field) && filled($field['key'] ?? null))
            ->mapWithKeys(fn (array $field): array => [$field['key'] => (string) ($field['label'] ?? $field['key'])])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $field
     * @return array<int, mixed>
     */
    private function rulesForField(array $field): array
    {
        $type = $field['type'] ?? 'text';
        $required = (bool) ($field['required'] ?? false);
        $rules = [$required ? 'required' : 'nullable'];

        if ($type === 'toggle') {
            return $required ? ['accepted'] : ['nullable', 'boolean'];
        }

        if ($type === 'checkbox' && $this->optionValues($field) === []) {
            return $required ? ['accepted'] : ['nullable', 'boolean'];
        }

        if ($type === 'checkbox') {
            return [
                $required ? 'required' : 'nullable',
                'array',
                $required ? 'min:1' : null,
            ];
        }

        if ($type === 'select') {
            return [
                ...$rules,
                'string',
                Rule::in($this->optionValues($field)),
            ];
        }

        $rules[] = 'string';
        $rules[] = 'max:'.($field['max_length'] ?? ($type === 'textarea' ? 5000 : 255));

        if (filled($field['min_length'] ?? null)) {
            $rules[] = 'min:'.$field['min_length'];
        }

        if ($type === 'email' || ($field['validation_semantics'] ?? null) === 'email') {
            $rules[] = 'email:rfc';
        }

        if ($type === 'phone' || ($field['validation_semantics'] ?? null) === 'phone') {
            $rules[] = 'regex:/^[0-9+().\\-\\s]{6,40}$/';
        }

        if ($type === 'url' || ($field['validation_semantics'] ?? null) === 'url') {
            $rules[] = 'url';
            $rules[] = $this->httpUrlRule();
        }

        return array_values(array_filter($rules));
    }

    /**
     * @param  array<string, mixed>  $field
     * @return array<int, string>
     */
    private function optionValues(array $field): array
    {
        return collect($field['options'] ?? [])
            ->filter(fn (mixed $option): bool => is_array($option) && filled($option['value'] ?? null))
            ->pluck('value')
            ->map(fn (mixed $value): string => (string) $value)
            ->values()
            ->all();
    }

    private function httpUrlRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if ($value === null || $value === '') {
                return;
            }

            $scheme = parse_url((string) $value, PHP_URL_SCHEME);

            if (! in_array(strtolower((string) $scheme), ['http', 'https'], true)) {
                $fail(__('validation.url', ['attribute' => $attribute]));
            }
        };
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function sanitizePayload(array $definition, array $payload): array
    {
        return collect($definition['fields'] ?? [])
            ->filter(fn (mixed $field): bool => is_array($field) && filled($field['key'] ?? null))
            ->mapWithKeys(function (array $field) use ($payload): array {
                $key = (string) $field['key'];

                if (! array_key_exists($key, $payload)) {
                    return [];
                }

                return [$key => $this->sanitizeValue($payload[$key])];
            })
            ->all();
    }

    private function sanitizeValue(mixed $value): mixed
    {
        if (is_bool($value) || is_int($value) || is_float($value) || $value === null) {
            return $value;
        }

        if (is_array($value)) {
            return collect($value)
                ->map(fn (mixed $item): mixed => $this->sanitizeValue($item))
                ->values()
                ->all();
        }

        return e(trim((string) $value), false);
    }
}
