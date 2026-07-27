<?php

namespace App\Support\Media;

readonly class SettingsOwnerImageSnapshot
{
    /**
     * @param  array<string, array<string, mixed>>  $slots
     */
    public function __construct(
        public string $subject,
        public string $fingerprint,
        public array $slots,
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function slot(string $key): ?array
    {
        return $this->slots[$key] ?? null;
    }
}
