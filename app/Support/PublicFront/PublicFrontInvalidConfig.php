<?php

namespace App\Support\PublicFront;

use Illuminate\Support\Str;

class PublicFrontInvalidConfig
{
    public function __construct(
        public readonly string $path,
        public readonly string $reason,
        public readonly string $valuePreview = '',
    ) {}

    public static function make(string $path, string $reason, mixed $value = null): self
    {
        return new self(
            path: $path,
            reason: $reason,
            valuePreview: self::preview($value),
        );
    }

    /**
     * @return array{path: string, reason: string, value_preview: string}
     */
    public function toArray(): array
    {
        return [
            'path' => $this->path,
            'reason' => $this->reason,
            'value_preview' => $this->valuePreview,
        ];
    }

    private static function preview(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_scalar($value)) {
            return Str::limit((string) $value, 80);
        }

        if (is_array($value)) {
            return '[array]';
        }

        return '[object]';
    }
}
