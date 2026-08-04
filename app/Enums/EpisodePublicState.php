<?php

namespace App\Enums;

use App\Models\ContentItem;
use Carbon\CarbonInterface;
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

        // Blockers outrank the clock. A scheduled episode whose podcast or
        // transcript will still be unpublished when its date arrives is not
        // "scheduled" — it is broken, and saying so now is the only moment
        // the operator can still fix it. Prerequisites are therefore judged
        // as of the episode's own air time, so a legitimately co-scheduled
        // podcast or transcript does not raise a false alarm.
        $airsAt = $record->published_at ?? now();

        // Judged at the later of now and the air time: a row already past its
        // date is asked "are you visible now?", a future-dated one is asked
        // "will you be visible then?". Using the air time alone would call an
        // episode blocked because its podcast happened to publish after the
        // episode's own (past) date, even though both are live today.
        $judgeAt = $airsAt->gt(now()) ? $airsAt : now();

        $group = $record->contentGroup;
        $groupReleased = $group !== null
            && $group->status === PublicationStatus::Published
            && ($group->published_at === null || $group->published_at->lte($judgeAt));

        if (! $groupReleased) {
            return self::BlockedGroup;
        }

        if (! self::hasTranscriptionReleasedBy($record, $judgeAt)) {
            return self::BlockedTranscription;
        }

        return $airsAt->gt(now())
            ? self::Scheduled
            : self::Visible;
    }

    private static function hasTranscriptionReleasedBy(ContentItem $record, CarbonInterface $judgeAt): bool
    {
        // Batch path: the table query primes both release flags and the row
        // picks the one its own judging moment needs. Single-record callers
        // (action notifications) pay for one exists query instead.
        $attribute = $judgeAt->gt(now())
            ? 'has_transcription_by_air_time'
            : 'has_transcription_now';

        $flag = $record->hasAttribute($attribute)
            ? $record->getAttribute($attribute)
            : null;

        if ($flag !== null) {
            return (bool) $flag;
        }

        return $record->transcriptions()->releasedBy($judgeAt)->exists();
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
