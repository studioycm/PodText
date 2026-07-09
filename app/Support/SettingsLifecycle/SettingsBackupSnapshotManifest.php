<?php

namespace App\Support\SettingsLifecycle;

use App\Models\Author;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Support\PublicContent\PublicContentItemQueries;
use App\Support\PublicContent\PublicContributorDiscovery;
use App\Support\PublicFront\Groups\PublicContentGroupQueries;

class SettingsBackupSnapshotManifest
{
    public const SCREEN_CONTRIBUTOR = 'contributor';

    public const SCREEN_CONTRIBUTORS = 'contributors';

    public const SCREEN_EPISODE = 'episode';

    public const SCREEN_HOME = 'home';

    public const SCREEN_PODCAST = 'podcast';

    public const SCREEN_PODCASTS = 'podcasts';

    public const SCREEN_SEARCH = 'search';

    /**
     * @return array<int, array{screen_key: string, url: string}>
     */
    public function thumbnailTargets(): array
    {
        return array_values(array_filter([
            $this->fixedTarget(self::SCREEN_HOME, '/'),
            $this->fixedTarget(self::SCREEN_PODCASTS, '/podcasts'),
        ]));
    }

    /**
     * @return array<int, array{screen_key: string, url: string}>
     */
    public function fullTargets(): array
    {
        return array_values(array_filter([
            $this->fixedTarget(self::SCREEN_HOME, '/'),
            $this->fixedTarget(self::SCREEN_SEARCH, '/search'),
            $this->fixedTarget(self::SCREEN_PODCASTS, '/podcasts'),
            $this->podcastTarget(),
            $this->episodeTarget(),
            $this->fixedTarget(self::SCREEN_CONTRIBUTORS, '/contributors'),
            $this->contributorTarget(),
        ]));
    }

    /**
     * @return array{screen_key: string, url: string}
     */
    private function fixedTarget(string $screenKey, string $path): array
    {
        return [
            'screen_key' => $screenKey,
            'url' => $this->url($path),
        ];
    }

    /**
     * @return array{screen_key: string, url: string}|null
     */
    private function podcastTarget(): ?array
    {
        $contentGroup = PublicContentGroupQueries::base()
            ->orderByRaw('homepage_order is null')
            ->orderBy('homepage_order')
            ->orderBy('title')
            ->orderBy('id')
            ->first();

        if (! $contentGroup instanceof ContentGroup) {
            return null;
        }

        return [
            'screen_key' => self::SCREEN_PODCAST,
            'url' => $this->url("/podcasts/{$contentGroup->slug}"),
        ];
    }

    /**
     * @return array{screen_key: string, url: string}|null
     */
    private function episodeTarget(): ?array
    {
        $contentItem = PublicContentItemQueries::pinnedFirst(PublicContentItemQueries::base())
            ->with('contentGroup')
            ->first();

        if (! $contentItem instanceof ContentItem || ! $contentItem->contentGroup instanceof ContentGroup) {
            return null;
        }

        return [
            'screen_key' => self::SCREEN_EPISODE,
            'url' => $this->url("/items/{$contentItem->contentGroup->slug}/{$contentItem->slug}"),
        ];
    }

    /**
     * @return array{screen_key: string, url: string}|null
     */
    private function contributorTarget(): ?array
    {
        $author = PublicContributorDiscovery::contributors()
            ->first();

        if (! $author instanceof Author) {
            return null;
        }

        return [
            'screen_key' => self::SCREEN_CONTRIBUTOR,
            'url' => $this->url("/contributors/{$author->slug}"),
        ];
    }

    private function url(string $path): string
    {
        $baseUrl = rtrim((string) config('app.url'), '/');

        return $baseUrl.'/'.ltrim($path, '/');
    }
}
