<?php

namespace App\Support\Settings\CardTemplates;

use App\Support\PublicFront\Cards\PublicFrontCardTemplateRegistry;
use JsonException;

class CardTemplateIdentity
{
    public const KEY_PATTERN = '/^[a-z][a-z0-9_-]*$/';

    public const KEY_MAX_LENGTH = 80;

    public const LABEL_MAX_LENGTH = 120;

    public function validFamily(mixed $family): bool
    {
        return is_string($family)
            && in_array($family, PublicFrontCardTemplateRegistry::families(), true);
    }

    public function validKey(mixed $key): bool
    {
        return is_string($key)
            && strlen($key) <= self::KEY_MAX_LENGTH
            && preg_match(self::KEY_PATTERN, $key) === 1;
    }

    public function valid(string $family, string $key): bool
    {
        return $this->validFamily($family) && $this->validKey($key);
    }

    public function make(string $family, string $key): string
    {
        return "{$family}:{$key}";
    }

    /**
     * @param  array<string, mixed>  $template
     */
    public function fromTemplate(array $template): ?string
    {
        $family = $template['family'] ?? null;
        $key = $template['key'] ?? null;

        if (! is_string($family) || ! is_string($key) || ! $this->valid($family, $key)) {
            return null;
        }

        return $this->make($family, $key);
    }

    /**
     * @param  array<int, mixed>  $templates
     * @return array<int, array{index: int, template: array<string, mixed>}>
     */
    public function locate(array $templates, string $family, string $key): array
    {
        $matches = [];

        foreach ($templates as $index => $template) {
            if (! is_array($template)) {
                continue;
            }

            if (($template['family'] ?? null) !== $family || ($template['key'] ?? null) !== $key) {
                continue;
            }

            $matches[] = [
                'index' => $index,
                'template' => $template,
            ];
        }

        return $matches;
    }

    /**
     * @throws JsonException
     */
    public function canonicalJson(mixed $value): string
    {
        return json_encode(
            $this->canonicalize($value),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
    }

    public function fingerprint(mixed $value): string
    {
        return hash('sha256', $this->canonicalJson($value));
    }

    private function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(fn (mixed $item): mixed => $this->canonicalize($item), $value);
        }

        ksort($value);

        return array_map(fn (mixed $item): mixed => $this->canonicalize($item), $value);
    }
}
