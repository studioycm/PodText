<?php

namespace App\Providers;

use App\Settings\PublicContentSettings;
use App\Support\PublicFront\PublicFrontRenderContext;
use App\Support\PublicFront\PublicFrontRenderContextFactory;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Spatie\LaravelSettings\Events\SettingsSaved;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(
            PublicFrontRenderContext::class,
            fn (): PublicFrontRenderContext => $this->app
                ->make(PublicFrontRenderContextFactory::class)
                ->make(),
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(SettingsSaved::class, function (SettingsSaved $event): void {
            if (! $event->settings instanceof PublicContentSettings) {
                return;
            }

            $this->app->forgetInstance(PublicFrontRenderContext::class);
        });
    }
}
