<?php

namespace App\Filament\Widgets\Concerns;

use App\Enums\DashboardRange;
use App\Enums\EpisodeListScope;
use App\Enums\FunnelStage;
use App\Enums\ImportConnectionProvider;
use App\Support\Dashboard\EditorialMetrics;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

/**
 * Shared reading of the dashboard command bar. Requires
 * {@see InteractsWithPageFilters}.
 *
 * `$pageFilters` is live, URL-bound and — per Filament's own widget docs —
 * never validated, so every reader here narrows the raw value to something the
 * board can safely query and render with. Nothing else may read the array
 * directly.
 */
trait ReadsDashboardFilters
{
    protected function dashboardRange(): DashboardRange
    {
        // tryFrom() falls back to the default for anything unrecognised.
        return DashboardRange::fromFilter($this->pageFilters['range'] ?? null);
    }

    /** An id that does not resolve to a podcast scopes to the whole library. */
    protected function dashboardPodcastId(): ?int
    {
        $podcast = $this->pageFilters['podcast'] ?? null;

        if (blank($podcast) || ! is_numeric($podcast)) {
            return null;
        }

        return app(EditorialMetrics::class)->podcastExists((int) $podcast)
            ? (int) $podcast
            : null;
    }

    /** The intake source scope, if the command bar holds a valid provider. */
    protected function dashboardSource(): ?ImportConnectionProvider
    {
        return ImportConnectionProvider::tryFrom((string) ($this->pageFilters['source'] ?? ''));
    }

    /** The legend chip currently driving the board, if any. */
    protected function dashboardStatus(): ?string
    {
        $status = $this->pageFilters['status'] ?? null;

        return in_array($status, FunnelStage::values(), strict: true) ? $status : null;
    }

    /**
     * Carry the active podcast scope into a Resource doorway so the linked
     * table opens on the same slice the widget was describing. The query key
     * is `filters` — the alias `ListRecords` declares on `$tableFilters` —
     * because that is the parameter the opened table actually reads.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    /**
     * A doorway onto an episode scope — the number's own query, named.
     *
     * The defect this exists to prevent: a card's number comes from
     * EditorialMetrics and its link was hand-written beside it, in a second
     * vocabulary (table filters) that restates the same predicate. The two
     * drifted silently — a card reading 1 opening a list of 3 looks exactly
     * like one reading 1 opening a list of 1.
     *
     * An EpisodeListScope case IS the predicate, on both sides:
     * EpisodeListScopeQuery::apply() answers the count and the tab, so a
     * doorway built from a case cannot point somewhere the count did not come
     * from. Metrics with no exact scope get no link at all — see
     * dashboard-widget-principles ES-1, a doorway-less number beats one
     * pointing at an approximately-right list.
     *
     * @return array<string, mixed>
     */
    protected function scopedTableScope(EpisodeListScope $scope): array
    {
        return ['tab' => $scope->value, ...$this->scopedTableFilters()];
    }

    protected function scopedTableFilters(array $filters = []): array
    {
        $podcastId = $this->dashboardPodcastId();

        if ($podcastId !== null) {
            $filters['content_group_id'] = ['value' => $podcastId];
        }

        return $filters === [] ? [] : ['filters' => $filters];
    }
}
