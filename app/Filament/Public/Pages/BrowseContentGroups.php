<?php

namespace App\Filament\Public\Pages;

use Filament\Pages\Page;
use Filament\Panel;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View;

class BrowseContentGroups extends Page
{
    protected string $view = 'filament.public.pages.browse-content-items';

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

    public function getHeader(): ?View
    {
        return view('filament.public.pages.empty-page-header');
    }
}
