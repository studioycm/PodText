<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\ImporterSettings;
use App\Filament\Pages\SpotifyLinksFetcher;
use App\Filament\Widgets\Concerns\AdminOnlyWidget;
use App\Filament\Widgets\Concerns\ShowsLoadingSkeleton;
use App\Support\Dashboard\EditorialMetrics;
use Filament\Widgets\Widget;

/**
 * Decision 4's card: the persisted connection-test echo (status +
 * last_tested_at) for every Spotify connection, and the derived
 * reduced-mode note when none is Connected — the same rule the fetcher
 * itself warns with. No fetch record exists and none is displayed.
 */
class SpotifyConnectionWidget extends Widget
{
    use AdminOnlyWidget;
    use ShowsLoadingSkeleton;

    protected string $view = 'filament.widgets.spotify-connection';

    protected static ?int $sort = -25;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = null;

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        return [
            ...app(EditorialMetrics::class)->spotifyConnectionEcho(),
            'manageUrl' => ImporterSettings::getUrl(),
            'fetcherUrl' => SpotifyLinksFetcher::getUrl(),
        ];
    }

    protected function skeletonRows(): int
    {
        return 2;
    }
}
