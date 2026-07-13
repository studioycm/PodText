<?php

namespace App\Support\Importer\SpotifyLinks;

use App\Models\ContentGroup;

class SpotifyGroupMatch
{
    public const TIER_SHOW_ID = 'show_id';

    public const TIER_EXACT_TITLE = 'exact_title';

    public const TIER_CLOSE_TITLE = 'close_title';

    public function __construct(
        public readonly ContentGroup $group,
        public readonly string $tier,
    ) {}

    public function shouldLinkByDefault(): bool
    {
        return in_array($this->tier, [
            self::TIER_SHOW_ID,
            self::TIER_EXACT_TITLE,
        ], true);
    }
}
