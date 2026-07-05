<?php

namespace App\Support\PublicFront\Menu;

use App\Filament\Public\Pages\AboutPage;
use App\Filament\Public\Pages\BrowseContributors;
use App\Filament\Public\Pages\BrowsePublicContentGroups;
use App\Filament\Public\Pages\SearchContentItems;

class PublicRouteRegistry
{
    /**
     * @return array<string>
     */
    public static function keys(): array
    {
        return [
            'home',
            'search',
            'podcasts',
            'contributors',
            'about',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::keys())
            ->mapWithKeys(fn (string $key): array => [$key => __("admin.public_front_routes.{$key}")])
            ->all();
    }

    public function url(string $routeKey): ?string
    {
        return match ($routeKey) {
            'home' => '/',
            'search' => SearchContentItems::getUrl(panel: 'public'),
            'podcasts' => BrowsePublicContentGroups::getUrl(panel: 'public'),
            'contributors' => BrowseContributors::getUrl(panel: 'public'),
            'about' => AboutPage::getUrl(panel: 'public'),
            default => null,
        };
    }

    /**
     * @param  array<int, array<string, string>>  $routeLabels
     */
    public function label(string $routeKey, array $routeLabels = []): string
    {
        $configured = collect($routeLabels)
            ->first(fn (array $item): bool => ($item['route_key'] ?? null) === $routeKey);

        if (is_array($configured) && filled($configured['label'] ?? null)) {
            return (string) $configured['label'];
        }

        return __("public.menu.routes.{$routeKey}");
    }
}
