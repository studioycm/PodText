<?php

namespace App\Providers\Filament;

use App\Filament\Public\Pages\BrowseCategoryContentItems;
use App\Filament\Public\Pages\BrowseContentGroups;
use App\Filament\Public\Pages\BrowseContributors;
use App\Filament\Public\Pages\BrowseTagContentItems;
use App\Filament\Public\Pages\SearchContentItems;
use App\Filament\Public\Pages\ShowContentGroup;
use App\Filament\Public\Pages\ShowContentItem;
use App\Filament\Public\Pages\ShowContributor;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class PublicPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('public')
            ->path('')
            ->viteTheme('resources/css/filament/public/theme.css')
            ->brandName(fn (): string => __('app.name'))
            ->brandLogo(fn (): string => asset('images/podtext-logo.jpg'))
            ->brandLogoHeight('60px')
            ->font('Varela Round')
            ->colors([
                'primary' => Color::Amber,
            ])
            ->pages([
                BrowseContentGroups::class,
                SearchContentItems::class,
                BrowseCategoryContentItems::class,
                BrowseTagContentItems::class,
                BrowseContributors::class,
                ShowContributor::class,
                ShowContentGroup::class,
                ShowContentItem::class,
            ])
            ->widgets([])
            ->navigation(false)
            ->userMenu(false)
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ]);
    }
}
