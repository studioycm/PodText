<?php

namespace App\Filament\Public\Pages;

use App\Filament\Public\Pages\Concerns\HidesPublicPageHeader;
use Filament\Pages\Page;
use Filament\Panel;

class BrowseContributors extends Page
{
    use HidesPublicPageHeader;

    protected string $view = 'filament.public.pages.browse-contributors';

    protected static bool $shouldRegisterNavigation = false;

    public static function getSlug(?Panel $panel = null): string
    {
        return 'contributors';
    }

    public static function getRelativeRouteName(Panel $panel): string
    {
        return 'contributors.index';
    }

    public function getTitle(): string
    {
        return __('public.pages.contributors.title');
    }
}
