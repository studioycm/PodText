<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * The six episode-list quick scopes (EQ-4, 2026-08-05). The non-pin scopes
 * partition the library exactly: drafts + visible + scheduled + blocked =
 * all; pinned is a pin-window scope that overlaps them. Membership
 * predicates live in EpisodeListScopeQuery — the one home.
 */
enum EpisodeListScope: string implements HasLabel
{
    case All = 'all';
    case Drafts = 'drafts';
    case Visible = 'visible';
    case Scheduled = 'scheduled';
    case Blocked = 'blocked';
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
