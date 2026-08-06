<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * The episode-list quick scopes (EQ-4, 2026-08-05; blocked split by cause
 * 2026-08-06). The non-pin scopes partition the library exactly:
 * drafts + visible + scheduled + blocked_group + blocked_transcription =
 * all; pinned is a pin-window scope that overlaps them.
 *
 * The two blocked scopes are disjoint by the same precedence
 * EpisodePublicState uses — the podcast wins, because it is the upstream
 * fix — so the partition survives an episode blocked by both.
 *
 * Membership predicates live in EpisodeListScopeQuery — the one home.
 */
enum EpisodeListScope: string implements HasLabel
{
    case All = 'all';
    case Drafts = 'drafts';
    case Visible = 'visible';
    case Scheduled = 'scheduled';
    // Split by cause, sharing EpisodePublicState's vocabulary: these are two
    // different jobs for two different people. A podcast that is not published
    // is one switch away — on another record, often releasing several episodes
    // at once. A missing transcript is work of unknown length. Merging them
    // hid every quick win inside a pile of real work.
    case BlockedGroup = 'blocked_group';
    case BlockedTranscription = 'blocked_transcription';
    case Pinned = 'pinned';

    // Triage scopes: each asks "which single thing is missing", and each
    // OVERLAPS the partition rather than extending it. Ready-to-publish is a
    // slice of drafts, pinned-not-visible of pinned, and will-break-on-air of
    // the blocked pair — so partitionsLibrary() below, not a hand-list in a
    // test, is what decides which scopes must sum to the whole.
    case ReadyToPublish = 'ready_to_publish';
    case PinnedNotVisible = 'pinned_not_visible';
    case WillBreakOnAir = 'will_break_on_air';

    /**
     * Does this scope carve out part of the exact partition?
     *
     * Structural rather than a list in a test: adding an overlapping scope
     * cannot silently break the sum, and adding a partitioning one forces the
     * question to be answered here, where the reason lives.
     */
    public function partitionsLibrary(): bool
    {
        return match ($this) {
            self::Drafts, self::Visible, self::Scheduled,
            self::BlockedGroup, self::BlockedTranscription => true,
            self::All, self::Pinned, self::ReadyToPublish,
            self::PinnedNotVisible, self::WillBreakOnAir => false,
        };
    }

    /**
     * Does this scope earn a tab?
     *
     * «מוצמדים» does not. Pinning is an attribute an episode carries, not a
     * stage it passes through, and the table already has a tri-state pin
     * filter that answers it — including «לא מוצמדים», which a tab cannot.
     * A tab beside that filter is a second door onto one question, and the
     * narrower door was the one worth keeping.
     *
     * The scope itself stays: `counts()` still reports it, the two triage
     * scopes still lean on the same pin window, and the dashboard doorways
     * now open the filter instead.
     */
    public function showsAsTab(): bool
    {
        return match ($this) {
            self::All, self::Drafts, self::Visible, self::Scheduled,
            self::BlockedGroup, self::BlockedTranscription,
            self::ReadyToPublish, self::PinnedNotVisible,
            self::WillBreakOnAir => true,
            self::Pinned => false,
        };
    }

    public function getLabel(): string
    {
        return __("admin.episode_scopes.{$this->value}");
    }

    public function description(): string
    {
        return __("admin.episode_scopes.descriptions.{$this->value}");
    }
}
