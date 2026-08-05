<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Dashboard;
use App\Filament\Resources\Media\MediaResource;
use App\Filament\Resources\Media\Pages\ListMedia;
use App\Filament\Support\AdminNavigationOrder;
use Awcodes\Curator\CuratorPlugin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationBuilder;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\View\TablesRenderHook;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Illuminate\Contracts\View\View;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->login()
            ->profile()
            ->passwordReset()
            ->plugin(
                CuratorPlugin::make()
                    ->label(fn (): string => __('admin.curator.label'))
                    ->pluralLabel(fn (): string => __('admin.curator.plural_label'))
                    ->navigationGroup(fn (): ?string => AdminNavigationOrder::group(MediaResource::class))
                    ->navigationIcon(Heroicon::OutlinedPhoto)
                    ->navigationSort(fn (): int => AdminNavigationOrder::sort(MediaResource::class) ?? 20)
                    ->curations(false)
                    ->showBadge(false),
            )
            ->brandLogo(fn (): string => asset('images/podtext-logo.svg'))
            ->darkModeBrandLogo(fn (): string => asset('images/podtext-logo-dark.svg'))
            ->brandLogoHeight('48px')
            ->favicon(fn (): string => asset('favicon.svg'))
            ->renderHook(
                PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_AFTER,
                fn (): View => view('filament.resources.media.gallery-keyboard-nav'),
                scopes: ListMedia::class,
            )
            ->renderHook(
                TablesRenderHook::TOOLBAR_END,
                fn (): View => view('filament.resources.media.gallery-top-pagination'),
            )
            // Filament shows the result count only at the foot of the table,
            // so a filtered episode list made you scroll to learn what it
            // matched. Same hook, same self-guarding view convention.
            ->renderHook(
                TablesRenderHook::TOOLBAR_END,
                fn (): View => view('filament.resources.content-items.toolbar-record-count'),
            )
            // The episodes list wants its collapsible-filters trigger on the
            // toolbar row with the other table controls, not in a row of its
            // own above the panel. The hook is unscoped; the view guards
            // itself to that page.
            ->renderHook(
                TablesRenderHook::TOOLBAR_COLUMN_MANAGER_TRIGGER_BEFORE,
                fn (): View => view('filament.resources.content-items.filters-toolbar-trigger'),
            )
            ->sidebarWidth('15rem')
            ->sidebarCollapsibleOnDesktop()
//            ->sidebarFullyCollapsibleOnDesktop()
            ->maxContentWidth(Width::Full)
            ->brandName(fn (): string => __('app.name'))
            ->font('Varela Round')
            ->colors([
                'primary' => Color::Amber,
                'danger' => Color::Rose,
                'success' => Color::Emerald,
                'warning' => Color::Orange,
                'info' => Color::Blue,
                'gray' => Color::Slate,
            ])
            ->databaseNotifications()
            ->navigation(fn (NavigationBuilder $builder): NavigationBuilder => AdminNavigationOrder::panelNavigation($builder))
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->discoverClusters(in: app_path('Filament/Clusters'), for: 'App\Filament\Clusters')
            ->pages([
                Dashboard::class,
            ])
            ->widgets([
                AccountWidget::class,
            ])
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
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
