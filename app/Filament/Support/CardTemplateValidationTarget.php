<?php

namespace App\Filament\Support;

use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Field;

final readonly class CardTemplateValidationTarget
{
    public function __construct(
        public bool $exact,
        public Field $component,
        public string $statePath,
        public ?Field $fallbackComponent = null,
        public ?string $fallbackStatePath = null,
        public ?Builder $topBuilder = null,
        public ?string $topUuid = null,
        public ?string $childUuid = null,
        public ?int $childPosition = null,
        public ?string $field = null,
        public ?string $reveal = null,
    ) {}
}
