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

    /**
     * Tailwind classes for a soft-filled band in this stage's colour.
     *
     * Deliberately case-complete like every stage vocabulary here, although
     * only the visible band renders today (born whole in the E3 drift
     * consolidation; no other arm ever had a call site). A partial match
     * would put UnhandledMatchError in reach of any future all-stages loop —
     * the pattern the funnel view already uses for barClass() — and the
     * admin theme's app/Enums @source glob keeps the dormant arms compiled.
     */
    public function bandClass(): string
    {
        return match ($this) {
            self::Draft => 'bg-gray-100 text-gray-700 dark:bg-white/5 dark:text-gray-300',
            self::Published => 'bg-info-100 text-info-800 dark:bg-info-500/20 dark:text-info-300',
            self::Transcribed => 'bg-primary-100 text-primary-800 dark:bg-primary-500/20 dark:text-primary-300',
            self::Visible => 'bg-success-100 text-success-800 dark:bg-success-500/20 dark:text-success-300',
        };
    }
}
