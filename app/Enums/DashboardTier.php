<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * The two kinds of trouble a published episode can be in.
 *
 * This is the operator's round-2 decision 1 made durable in a type. Before it,
 * the tiers existed only as the shape of two query methods, and their colours
 * were hand-written in the burndown bars, the gap bar and a stat card — which
 * is how the stat card came to paint the invisible count `warning` while the
 * funnel painted the same number `danger`.
 *
 * A colour is only safe from that drift once every call site reads it here.
 */
enum DashboardTier: string implements HasColor, HasDescription, HasIcon, HasLabel
{
    /** The public cannot see the episode at all. */
    case Invisible = 'invisible';

    /** The public can see it; something about it is incomplete. */
    case Attention = 'attention';

    public function getLabel(): string
    {
        return __("admin.dashboard.tiers.{$this->value}");
    }

    public function getDescription(): string
    {
        return __("admin.dashboard.tiers.{$this->value}_description");
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Invisible => 'danger',
            self::Attention => 'warning',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Invisible => Heroicon::OutlinedEyeSlash,
            self::Attention => Heroicon::OutlinedExclamationTriangle,
        };
    }

    /** Tailwind fill for a solid bar in this tier's colour. */
    public function barClass(): string
    {
        return match ($this) {
            self::Invisible => 'bg-danger-500',
            self::Attention => 'bg-warning-500',
        };
    }

    /** Tailwind classes for a soft-filled band in this tier's colour. */
    public function bandClass(): string
    {
        return match ($this) {
            self::Invisible => 'bg-danger-100 text-danger-800 dark:bg-danger-500/20 dark:text-danger-300',
            self::Attention => 'bg-warning-100 text-warning-800 dark:bg-warning-500/20 dark:text-warning-300',
        };
    }

    /**
     * The reasons that put an episode in this tier. Derived from the reasons
     * themselves, so a new reason joins its tier by declaring one method.
     *
     * @return array<int, DashboardReason>
     */
    public function reasons(): array
    {
        return array_values(array_filter(
            DashboardReason::cases(),
            fn (DashboardReason $reason): bool => $reason->tier() === $this,
        ));
    }
}
