<?php

namespace App\Filament\Public\Pages;

use App\Filament\Public\Pages\Concerns\HidesPublicPageHeader;
use Filament\Pages\Page;
use Filament\Panel;

class SearchContentItems extends Page
{
    use HidesPublicPageHeader;

    protected string $view = 'filament.public.pages.search-content-items';

    protected static bool $shouldRegisterNavigation = false;

    public static function getSlug(?Panel $panel = null): string
    {
        return 'search';
    }

    public static function getRelativeRouteName(Panel $panel): string
    {
        return 'search';
    }

    public function getTitle(): string
    {
        return __('public.pages.search.title');
    }
}
