<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum DashboardLens: string implements HasLabel
{
    case Overview = 'overview';
    case Blockers = 'blockers';
    case Intake = 'intake';

    public static function fromFilter(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::Overview;
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $lens): array => [$lens->value => $lens->getLabel()])
            ->all();
    }

    public function getLabel(): string
    {
        return __("admin.dashboard.lenses.{$this->value}");
    }
}
