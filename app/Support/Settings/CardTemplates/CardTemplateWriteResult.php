<?php

namespace App\Support\Settings\CardTemplates;

class CardTemplateWriteResult
{
    public function __construct(
        public readonly string $family,
        public readonly string $key,
        public readonly string $fingerprint,
    ) {}
}
