<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * The pinned filter's tri-state. A type home rather than three loose string
 * literals, so the options, the query arms and the indicator can never fall
 * out of step, and forged filter state narrows at the door.
 */
enum EpisodePinScope: string implements HasLabel
{
    case All = 'all';
    case Pinned = 'pinned';
    case Unpinned = 'unpinned';

    public static function fromFilter(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::All;
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $scope): array => [$scope->value => $scope->getLabel()])
            ->all();
    }

    public function getLabel(): string
    {
        return __("admin.filters.pinned_options.{$this->value}");
    }

    /** «All» is the resting state, so it shows no filter indicator. */
    public function indicator(): ?string
    {
        return $this === self::All ? null : $this->getLabel();
    }
}
