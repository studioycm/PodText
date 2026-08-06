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

    public function getLabel(): string
    {
        return __("admin.episode_scopes.{$this->value}");
    }

    public function description(): string
    {
        return __("admin.episode_scopes.descriptions.{$this->value}");
    }
}
