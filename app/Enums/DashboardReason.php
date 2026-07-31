<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * Why a published episode is in the work queue, and which tier it belongs to.
 *
 * `hidesFromPublic()` is the split made durable: a reason either stops the
 * public seeing the episode at all, or leaves it visible but incomplete. Before
 * this enum the four reasons were bare strings whose colours were redefined in
 * four places and whose tier lived only in the shape of two query methods.
 */
enum DashboardReason: string implements HasColor, HasIcon, HasLabel
{
    case MissingTranscription = 'missing_transcription';
    case UnpublishedGroup = 'unpublished_group';
    case MissingMedia = 'missing_media';
    case MissingCategory = 'missing_category';

    /**
     * Reasons the public cannot see the episode at all.
     *
     * @return array<int, self>
     */
    public static function gap(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $reason): bool => $reason->hidesFromPublic(),
        ));
    }

    /**
     * Reasons that leave the episode visible but incomplete.
     *
     * @return array<int, self>
     */
    public static function attention(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $reason): bool => ! $reason->hidesFromPublic(),
        ));
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $reason): array => [$reason->value => $reason->getLabel()])
            ->all();
    }

    public function hidesFromPublic(): bool
    {
        return match ($this) {
            self::MissingTranscription, self::UnpublishedGroup => true,
            self::MissingMedia, self::MissingCategory => false,
        };
    }

    public function getLabel(): string
    {
        return __("admin.dashboard.reasons.{$this->value}");
    }

    public function getColor(): string
    {
        return match ($this) {
            self::MissingTranscription => 'danger',
            self::UnpublishedGroup => 'danger',
            self::MissingMedia => 'warning',
            self::MissingCategory => 'violet',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::MissingTranscription => Heroicon::OutlinedDocumentMinus,
            self::UnpublishedGroup => Heroicon::OutlinedRectangleStack,
            self::MissingMedia => Heroicon::OutlinedSpeakerXMark,
            self::MissingCategory => Heroicon::OutlinedTag,
        };
    }

    /** Tailwind fill for a solid bar in this reason's colour. */
    public function barClass(): string
    {
        return match ($this) {
            self::MissingTranscription => 'bg-danger-500',
            self::UnpublishedGroup => 'bg-danger-400',
            self::MissingMedia => 'bg-warning-500',
            self::MissingCategory => 'bg-violet-500',
        };
    }
}
