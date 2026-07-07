<?php

namespace App\Filament\Public\Pages;

use App\Filament\Public\Pages\Concerns\HidesPublicPageHeader;
use App\Support\PublicFront\PublicFrontRenderContext;
use Filament\Pages\Page;
use Filament\Panel;
use Illuminate\Contracts\Support\Htmlable;

class BrowsePublicContentGroups extends Page
{
    use HidesPublicPageHeader;

    protected string $view = 'filament.public.pages.browse-content-groups';

    protected static bool $shouldRegisterNavigation = false;

    public static function getSlug(?Panel $panel = null): string
    {
        return 'podcasts';
    }

    public static function getRelativeRouteName(Panel $panel): string
    {
        return 'podcasts.index';
    }

    public function mount(): void
    {
        abort_unless((bool) ($this->pageConfig()['enabled'] ?? true), 404);
    }

    public function getTitle(): string|Htmlable
    {
        return $this->pageConfig()['title'] ?? __('public.pages.podcasts.title');
    }

    /**
     * @return array<string, mixed>
     */
    public function pageConfig(): array
    {
        return app(PublicFrontRenderContext::class)->podcastsPage();
    }
}
