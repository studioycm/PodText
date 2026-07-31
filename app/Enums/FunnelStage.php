<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * The four publication funnel stages.
 *
 * Before this enum the stages were bare strings whose labels and colours were
 * redefined in four places — the filter allow-list, the funnel's bar map, the
 * legend chip classes, and three views' translation lookups. One definition
 * each now lives here.
 */
enum FunnelStage: string implements HasColor, HasIcon, HasLabel
{
    case Draft = 'draft';
    case Published = 'published';
    case Transcribed = 'transcribed';
    case Visible = 'visible';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function getLabel(): string
    {
        return __("admin.dashboard.legend.{$this->value}");
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Published => 'info',
            self::Transcribed => 'primary',
            self::Visible => 'success',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Draft => Heroicon::OutlinedPencilSquare,
            self::Published => Heroicon::OutlinedPaperAirplane,
            self::Transcribed => Heroicon::OutlinedDocumentText,
            self::Visible => Heroicon::OutlinedEye,
        };
    }

    /** Tailwind fill for a solid bar in this stage's colour. */
    public function barClass(): string
    {
        return match ($this) {
            self::Draft => 'bg-gray-400 dark:bg-gray-500',
            self::Published => 'bg-info-500',
            self::Transcribed => 'bg-primary-500',
            self::Visible => 'bg-success-500',
        };
    }

    /** Tailwind classes for this stage's legend chip. */
    public function chipClass(): string
    {
        return match ($this) {
            self::Draft => 'border border-gray-300 text-gray-600 dark:border-white/10 dark:text-gray-300',
            self::Published => 'bg-info-50 text-info-700 dark:bg-info-500/10 dark:text-info-300',
            self::Transcribed => 'bg-primary-50 text-primary-700 dark:bg-primary-500/10 dark:text-primary-300',
            self::Visible => 'bg-success-50 text-success-700 dark:bg-success-500/10 dark:text-success-300',
        };
    }

    /** Tailwind stroke for a sparkline drawn in this stage's colour. */
    public function strokeClass(): string
    {
        return match ($this) {
            self::Draft => 'stroke-gray-400 dark:stroke-gray-500',
            self::Published => 'stroke-info-500 dark:stroke-info-400',
            self::Transcribed => 'stroke-primary-500 dark:stroke-primary-400',
            self::Visible => 'stroke-success-500 dark:stroke-success-400',
        };
    }
}
