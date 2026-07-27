<?php

namespace App\Support\PublicFront\About;

final class PublicAboutBlockKey
{
    /**
     * @param  array<string, mixed>  $block
     */
    public static function fromPersistedBlock(array $block, int $index): ?string
    {
        $data = is_array($block['data'] ?? null) ? $block['data'] : $block;

        return self::derive(
            $data['key'] ?? null,
            $block['type'] ?? $data['type'] ?? null,
            $index,
        );
    }

    public static function derive(mixed $key, mixed $type, int $index): ?string
    {
        if (! is_string($type) || ! in_array(trim($type), PublicAboutPageRegistry::blockTypes(), true)) {
            return null;
        }

        $type = trim($type);

        if (is_string($key)) {
            $key = trim($key);

            if (preg_match('/^[a-z][a-z0-9_-]*$/', $key) === 1) {
                return $key;
            }
        }

        return "{$type}_".($index + 1);
    }
}
