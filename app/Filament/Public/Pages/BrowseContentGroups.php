<?php

namespace App\Filament\Public\Pages;

use Filament\Pages\Page;
use Filament\Panel;
use Illuminate\Contracts\Support\Htmlable;

class BrowseContentGroups extends Page
{
    protected string $view = 'filament.public.pages.browse-content-groups';

    protected static bool $shouldRegisterNavigation = false;

    public static function getSlug(?Panel $panel = null): string
    {
        return '';
    }

    public static function getRelativeRouteName(Panel $panel): string
    {
        return 'home';
    }

    public function getTitle(): string|Htmlable
    {
        return __('public.pages.browse.title');
    }
}
