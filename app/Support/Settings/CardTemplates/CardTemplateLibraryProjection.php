<?php

namespace App\Support\Settings\CardTemplates;

class CardTemplateLibraryProjection
{
    /**
     * @param  array<int, array<string, mixed>>  $records
     */
    public function __construct(
        public readonly array $records,
        public readonly CardTemplateReferences $references,
    ) {}
}
