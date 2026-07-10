<?php

namespace App\Support\SettingsLifecycle;

class SettingsLifecycleUnit
{
    /**
     * @param  array<int, string>  $semantics
     */
    public function __construct(
        public readonly string $group,
        public readonly string $path,
        public readonly string $label,
        public readonly string $labelKey,
        public readonly string $section,
        public readonly string $sectionLabel,
        public readonly string $structuralType,
        public readonly ?string $expectedScalarType,
        public readonly array $semantics = [],
    ) {}
}
