<?php

namespace App\Enums;

use App\Models\ContentItem;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * The per-row public-visibility verdict for episode tables (P-EL2
 * row-answers-first). Derived from ContentItem::scopePublished's exact
 * contract; EpisodeListScopeTest pins badge↔scope parity so this resolver
 * cannot drift from the query-side truth. When both the podcast and the
 * transcript block, the podcast wins the label — it is the upstream fix.
 */
enum EpisodePublicState: string implements HasColor, HasLabel
{
    case Visible = 'visible';
    case Scheduled = 'scheduled';
    case Draft = 'draft';
    case BlockedGroup = 'blocked_group';
    case BlockedTranscription = 'blocked_transcription';

    public static function for(ContentItem $record): self
    {
        if ($record->status !== PublicationStatus::Published) {
            return self::Draft;
        }

        if ($record->published_at !== null && $record->published_at->gt(now())) {
            return self::Scheduled;
        }

        $group = $record->contentGroup;
        $groupVisible = $group !== null
            && $group->status === PublicationStatus::Published
            && ($group->published_at === null || $group->published_at->lte(now()));

        if (! $groupVisible) {
            return self::BlockedGroup;
        }

        if (! self::hasPublishedTranscription($record)) {
            return self::BlockedTranscription;
        }

        return self::Visible;
    }

    private static function hasPublishedTranscription(ContentItem $record): bool
    {
        // Batch path: the table query withExists()es this flag; the single-
        // record path (action notifications) falls back to one exists query.
        $flag = $record->hasAttribute('has_published_transcription')
            ? $record->getAttribute('has_published_transcription')
            : null;

        if ($flag !== null) {
            return (bool) $flag;
        }

        return $record->transcriptions()->published()->exists();
    }

    public function getLabel(): string
    {
        return __("admin.episode_public_state.{$this->value}");
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Visible => 'success',
            self::Scheduled => 'info',
            self::Draft => 'gray',
            self::BlockedGroup, self::BlockedTranscription => 'danger',
        };
    }

    public function isBlocked(): bool
    {
        return $this === self::BlockedGroup || $this === self::BlockedTranscription;
    }
}
