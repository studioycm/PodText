<?php

namespace App\Filament\Public\Pages;

use App\Filament\Public\Pages\Concerns\HidesPublicPageHeader;
use App\Support\PublicFront\PublicFrontRenderContext;
use Filament\Pages\Page;
use Filament\Panel;

class BrowseContributors extends Page
{
    use HidesPublicPageHeader;

    protected string $view = 'filament.public.pages.browse-contributors';

    protected static bool $shouldRegisterNavigation = false;

    /** @var array<string, mixed> */
    public array $contributorsConfig = [];

    public static function getSlug(?Panel $panel = null): string
    {
        return 'contributors';
    }

    public static function getRelativeRouteName(Panel $panel): string
    {
        return 'contributors.index';
    }

    public function mount(): void
    {
        $this->contributorsConfig = app(PublicFrontRenderContext::class)->contributorsPage();

        abort_unless(
            $this->contributorsConfig['enabled'] ?? true,
            404,
        );
    }

    public function getTitle(): string
    {
        return __('public.pages.contributors.title');
    }
}
