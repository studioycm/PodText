<?php

namespace App\Providers;

use App\Observers\CuratorMediaObserver;
use App\Policies\CuratorMediaPolicy;
use App\Settings\PublicContentSettings;
use App\Support\Importer\Contracts\GoogleDriveClientFactory;
use App\Support\Importer\Contracts\SpotifyClientFactory;
use App\Support\Importer\Google\GoogleApiDriveClientFactory;
use App\Support\Importer\Spotify\SpotifyHttpClientFactory;
use App\Support\ImportExport\ImportExportQueueTracer;
use App\Support\PublicContent\PublicTranscriptionPolicy;
use App\Support\PublicFront\PublicFrontConfigCache;
use App\Support\PublicFront\PublicFrontRenderContext;
use App\Support\PublicFront\PublicFrontRenderContextFactory;
use App\Support\Settings\SettingsPageProfiler;
use App\Support\SettingsLifecycle\SettingsBackupManager;
use Awcodes\Curator\Models\Media;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Schemas\Components\Section;
use Filament\Support\Enums\Width;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Spatie\LaravelSettings\Events\SettingsSaved;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(GoogleDriveClientFactory::class, GoogleApiDriveClientFactory::class);
        $this->app->bind(SpotifyClientFactory::class, SpotifyHttpClientFactory::class);

        $this->app->scoped(SettingsPageProfiler::class);

        $this->app->scoped(
            PublicFrontRenderContext::class,
            fn (): PublicFrontRenderContext => $this->app
                ->make(PublicFrontRenderContextFactory::class)
                ->make(),
        );

        $this->app->scoped(
            PublicTranscriptionPolicy::class,
            fn (): PublicTranscriptionPolicy => PublicTranscriptionPolicy::fromContext(
                $this->app->make(PublicFrontRenderContext::class),
            ),
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());

        Gate::policy(Media::class, CuratorMediaPolicy::class);
        Media::observe(CuratorMediaObserver::class);

        $this->app->make(ImportExportQueueTracer::class)->register();

        Table::configureUsing(function (Table $table): void {
            if (! $this->isAdminPanel()) {
                return;
            }

            $table->recordActionsPosition(RecordActionsPosition::BeforeColumns);
        });

        Action::configureUsing(function (Action $action): void {
            if (! $this->isAdminPanel()) {
                return;
            }

            $action->modalWidth(fn (Action $action): Width => $action->isConfirmationRequired()
                ? Width::Medium
                : Width::SevenExtraLarge);
        });

        Section::configureUsing(function (Section $section): void {
            if (! $this->isAdminPanel()) {
                return;
            }

            $section->columnSpanFull();
        });

        Event::listen(SettingsSaved::class, function (SettingsSaved $event): void {
            if (! $event->settings instanceof PublicContentSettings) {
                return;
            }

            $profiler = $this->app->make(SettingsPageProfiler::class);

            $profiler->withRequestKind(SettingsPageProfiler::REQUEST_SAVE, function () use ($profiler): void {
                $profiler->measure('settings_saved.listener.total', function () use ($profiler): void {
                    $this->app->make(PublicFrontConfigCache::class)->forget();
                    $profiler->measure(
                        'settings_saved.backup_creation',
                        fn () => $this->app->make(SettingsBackupManager::class)->createSystem(),
                    );
                    $this->app->forgetInstance(PublicFrontRenderContext::class);
                    $this->app->forgetInstance(PublicTranscriptionPolicy::class);
                });
            });
        });
    }

    private function isAdminPanel(): bool
    {
        return Filament::getCurrentPanel()?->getId() === 'admin';
    }
}
