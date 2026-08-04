<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * The four editorial event kinds the board streams and queues.
 *
 * Decision 17 made durable: before this enum the stream's type list and chip
 * colours were hand-written in `ActivityStreamWidget` next to translation
 * keys derived by string interpolation — the exact drift shape (unrouted-enum) the
 * funnel fixed with `FunnelStage`. The values are the `activityStream()`
 * vocabulary and must not change: they ride Livewire state and the
 * legend-to-stream mapping.
 */
enum StreamEventType: string implements HasColor, HasIcon, HasLabel
{
    case Transcription = 'transcription';
    case Import = 'import';
    case Media = 'media';
    case Submission = 'submission';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function getLabel(): string
    {
        return __("admin.dashboard.stream.types.{$this->value}");
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Transcription => 'primary',
            self::Import => 'info',
            self::Media => 'warning',
            self::Submission => 'success',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Transcription => Heroicon::OutlinedDocumentText,
            self::Import => Heroicon::OutlinedArrowDownTray,
            self::Media => Heroicon::OutlinedPhoto,
            self::Submission => Heroicon::OutlinedInboxArrowDown,
        };
    }

    /** Tailwind classes for this event kind's chip — the shipped palette. */
    public function chipClass(): string
    {
        return match ($this) {
            self::Transcription => 'bg-primary-50 text-primary-700 dark:bg-primary-500/10 dark:text-primary-300',
            self::Import => 'bg-info-50 text-info-700 dark:bg-info-500/10 dark:text-info-300',
            self::Media => 'bg-warning-50 text-warning-700 dark:bg-warning-500/10 dark:text-warning-300',
            self::Submission => 'bg-success-50 text-success-700 dark:bg-success-500/10 dark:text-success-300',
        };
    }
}
