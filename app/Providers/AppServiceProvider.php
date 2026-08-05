<?php

namespace App\Providers;

use App\Enums\UserRole;
use App\Http\Middleware\RequireResendWebhookSecret;
use App\Listeners\ResendWebhookEventSubscriber;
use App\Livewire\Admin\DisabledVendorCuratorSurface;
use App\Livewire\Admin\MediaPickerPanel;
use App\Models\ContentGroup;
use App\Models\ContentItem;
use App\Models\ImportConnection;
use App\Models\Media;
use App\Models\PublicFormSubmission;
use App\Models\SettingsBackupVersion;
use App\Models\Transcription;
use App\Models\User;
use App\Observers\CuratorMediaObserver;
use App\Observers\EditorialMetricsCacheObserver;
use App\Policies\ContentItemPolicy;
use App\Policies\CuratorMediaPolicy;
use App\Policies\ImportPolicy;
use App\Policies\SettingsBackupPolicy;
use App\Settings\PublicContentSettings;
use App\Support\Authorization\PackageMutationCommandGuard;
use App\Support\Dashboard\EditorialMetrics;
use App\Support\Importer\Contracts\GoogleDriveClientFactory;
use App\Support\Importer\Contracts\SpotifyClientFactory;
use App\Support\Importer\Google\GoogleApiDriveClientFactory;
use App\Support\Importer\Spotify\SpotifyHttpClientFactory;
use App\Support\ImportExport\ImportExportQueueTracer;
use App\Support\Media\CuratorImageUploadPolicy;
use App\Support\Media\MediaInventoryDiagnostics;
use App\Support\Media\MediaLibraryTaskQuery;
use App\Support\Media\MediaMutationLease;
use App\Support\Media\MediaReferenceFinder;
use App\Support\PublicContent\PublicTranscriptionPolicy;
use App\Support\PublicFront\Cards\PublicFrontCardTemplateResolver;
use App\Support\PublicFront\PublicFormTargetStatus;
use App\Support\PublicFront\PublicFrontConfigCache;
use App\Support\PublicFront\PublicFrontRenderContext;
use App\Support\PublicFront\PublicFrontRenderContextFactory;
use App\Support\SettingsLifecycle\SettingsBackupManager;
use App\Support\SettingsLifecycle\SettingsLifecycleSchema;
use App\Support\Transcriptions\MultiTranscriptionSurfaces;
use Awcodes\Curator\Facades\Curator;
use BezhanSalleh\FilamentShield\Commands\TranslationCommand;
use BezhanSalleh\FilamentShield\Facades\FilamentShield;
use Filament\Actions\Action;
use Filament\Actions\Imports\Models\FailedImportRow;
use Filament\Actions\Imports\Models\Import;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Component as SchemaComponent;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Enums\Width;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\LazyLoadingViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\LaravelSettings\Events\SettingsSaved;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        config()->set('livewire.temporary_file_upload.disk', 'local');

        $resendApiKey = config('resend.api_key');

        if (! is_string($resendApiKey) || trim($resendApiKey) === '') {
            config()->set('resend.api_key', config('services.resend.key'));
        }

        $this->app->bind(GoogleDriveClientFactory::class, GoogleApiDriveClientFactory::class);
        $this->app->bind(SpotifyClientFactory::class, SpotifyHttpClientFactory::class);

        $this->app->scoped(EditorialMetrics::class);
        $this->app->scoped(SettingsLifecycleSchema::class);
        $this->app->scoped(PublicFrontCardTemplateResolver::class);
        $this->app->scoped(MediaInventoryDiagnostics::class);
        $this->app->scoped(MediaReferenceFinder::class);
        $this->app->scoped(MediaLibraryTaskQuery::class);
        $this->app->scoped(MediaMutationLease::class);
        $this->app->scoped(PublicFormTargetStatus::class);

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
        $production = $this->app->isProduction();

        FilamentShield::prohibitDestructiveCommands($production);
        TranslationCommand::prohibit($production);
        PackageMutationCommandGuard::register();

        // The dashboard snapshot must not outlive the writes it counts.
        ContentItem::observe(EditorialMetricsCacheObserver::class);
        ContentGroup::observe(EditorialMetricsCacheObserver::class);
        Transcription::observe(EditorialMetricsCacheObserver::class);

        // Board 3's snapshot sources. Vendor import models are observable
        // like any Eloquent model; failedRows()->createMany() saves each
        // row through Eloquent, so the observer's saved hook fires per row
        // and import failures invalidate immediately.
        PublicFormSubmission::observe(EditorialMetricsCacheObserver::class);
        Media::observe(EditorialMetricsCacheObserver::class);
        ImportConnection::observe(EditorialMetricsCacheObserver::class);
        Import::observe(EditorialMetricsCacheObserver::class);
        FailedImportRow::observe(EditorialMetricsCacheObserver::class);

        Model::preventLazyLoading(! $this->app->isProduction());
        Model::handleLazyLoadingViolationUsing(function (Model $model, string $relation): void {
            if (! $model->exists || $model->wasRecentlyCreated) {
                return;
            }

            if (app()->isProduction()) {
                Log::warning('Lazy loading violation detected.', [
                    'model' => $model::class,
                    'relation' => $relation,
                ]);

                return;
            }

            throw new LazyLoadingViolationException($model, $relation);
        });

        $this->app->booted(function (): void {
            foreach (Route::getRoutes()->getRoutes() as $route) {
                if ($route->getName() !== 'resend.webhook') {
                    continue;
                }

                $route->middleware(RequireResendWebhookSecret::class);

                return;
            }
        });

        Relation::morphMap([
            ContentGroup::class => ContentGroup::class,
            ContentItem::class => ContentItem::class,
            'content_group' => ContentGroup::class,
            'content_item' => ContentItem::class,
        ]);

        Curator::acceptedFileTypes(app(CuratorImageUploadPolicy::class)->globalMimeTypes())
            ->maxSize(CuratorImageUploadPolicy::MAX_KILOBYTES)
            ->disk('public')
            ->visibility('public')
            ->preserveFilenames(false);

        // Q7 (2026-08-03): the global default encodes the safe branch —
        // searchable selects load options through their capped server search,
        // and bounded option sets opt in per-site with an explicit preload().
        Select::configureUsing(fn (Select $select): Select => $select
            ->preload(false)
            ->optionsLimit(50));
        SelectFilter::configureUsing(fn (SelectFilter $filter): SelectFilter => $filter
            ->preload(false)
            ->optionsLimit(50));

        Gate::policy(Media::class, CuratorMediaPolicy::class);
        Gate::policy(SettingsBackupVersion::class, SettingsBackupPolicy::class);
        Gate::policy(Import::class, ImportPolicy::class);
        Gate::policy(ContentItem::class, ContentItemPolicy::class);
        Gate::define(UserRole::SuperAdmin->value, fn (User $user): bool => $user->hasRoleAtLeast(UserRole::SuperAdmin));
        Gate::define('multi-transcription', function (User $user, UserRole|string|null $minimum = null): bool {
            $minimumRole = $minimum instanceof UserRole
                ? $minimum
                : UserRole::tryFrom((string) $minimum) ?? UserRole::SuperAdmin;

            return MultiTranscriptionSurfaces::userCan($user, $minimumRole);
        });

        SchemaComponent::macro('multiTranscription', function (?UserRole $minimum = null) {
            /** @var SchemaComponent $this */
            return $this->hidden(fn (): bool => Gate::denies('multi-transcription', [$minimum ?? UserRole::SuperAdmin]));
        });

        SchemaComponent::macro('superAdminOnly', function () {
            /** @var SchemaComponent $this */
            return $this->hidden(fn (): bool => Gate::denies(UserRole::SuperAdmin->value));
        });

        Action::macro('multiTranscription', function (?UserRole $minimum = null) {
            /** @var Action $this */
            return $this->hidden(fn (): bool => Gate::denies('multi-transcription', [$minimum ?? UserRole::SuperAdmin]));
        });

        Action::macro('superAdminOnly', function () {
            /** @var Action $this */
            return $this->hidden(fn (): bool => Gate::denies(UserRole::SuperAdmin->value));
        });

        Media::observe(CuratorMediaObserver::class);
        Livewire::component('admin.media-picker-panel', MediaPickerPanel::class);
        Livewire::component('curator-panel', DisabledVendorCuratorSurface::class);
        Livewire::component('curator-curation', DisabledVendorCuratorSurface::class);

        $this->app->make(ImportExportQueueTracer::class)->register();

        // Badge deferral, centrally where Filament allows it. Every Tab —
        // resource filter tabs, schema tabs, and the tabs relation managers
        // build — loads its badge after the page paints instead of blocking
        // the first render. A tab whose badge is a raw value rather than a
        // closure simply gains nothing; nothing breaks.
        //
        // Two places this cannot reach, by Filament's design:
        // - RelationManager::getTabComponent() calls ->deferBadge() itself
        //   from $isBadgeDeferred, so it wins over this default and each
        //   relation manager opts in on its own class;
        // - navigation items have no deferred-badge API at all in 5.7, so
        //   the sidebar uses NavigationBadgeCount (lazy closure + short
        //   cache) as the substitute.
        Tab::configureUsing(function (Tab $tab): void {
            if (! $this->isAdminPanel()) {
                return;
            }

            $tab->deferBadge();
        });

        Table::configureUsing(function (Table $table): void {
            if (! $this->isAdminPanel()) {
                return;
            }

            $table->recordActionsPosition(RecordActionsPosition::BeforeColumns);

            // Namespace each admin table's URL pagination key by its owning
            // component (`{lcfirst basename}Page`), mirroring the identifier
            // Filament derives for relation managers, so two paginated
            // components on one screen never fight over the bare `page`
            // parameter. Resource ListRecords pages keep Filament's bare keys:
            // their `filters`/`search`/`sort` are static #[Url] bindings an
            // identifier cannot rename, and ListMedia reads the bare `page`
            // request parameter in mount(). An explicit
            // ->queryStringIdentifier() in a component's table() still wins,
            // because table() methods run after this hook.
            if (! $table->getLivewire() instanceof ListRecords) {
                $table->queryStringIdentifier(Str::lcfirst(class_basename($table->getLivewire()::class)));
            }
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

            $this->app->make(PublicFrontConfigCache::class)->forget();
            $this->app->make(SettingsBackupManager::class)->createSystem();
            $this->app->forgetInstance(PublicFrontRenderContext::class);
            $this->app->forgetInstance(PublicTranscriptionPolicy::class);
        });
        Event::subscribe(ResendWebhookEventSubscriber::class);

        RateLimiter::for('public-form-submissions', function (Request $request): Limit {
            return Limit::perMinute(10)->by('submit:'.$this->publicFormThrottleKey($request));
        });

        RateLimiter::for('public-form-verification-codes', function (Request $request): Limit {
            return Limit::perMinute(5)->by('code:'.$this->publicFormThrottleKey($request));
        });

        RateLimiter::for('external-image-downloads', fn (): Limit => Limit::perMinute(10)
            ->by('external-image-downloads'));
    }

    private function publicFormThrottleKey(Request $request): string
    {
        $formKey = $request->string('form_key')->toString() ?: 'unknown';

        return $formKey.':'.$request->ip();
    }

    private function isAdminPanel(): bool
    {
        return Filament::getCurrentPanel()?->getId() === 'admin';
    }
}
