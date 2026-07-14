<?php

use App\Http\Middleware\MeasureSettingsSp3aResponse;
use App\Support\PublicFront\Maintenance\MaintenancePageRenderer;
use App\Support\PublicFront\PublicFrontConfigReader;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\MessageBag;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands()
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(MeasureSettingsSp3aResponse::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $maintenanceCsrfRetryResponse = function (Request $request): ?SymfonyResponse {
            if (! $request->routeIs('public.maintenance-form.submit') && ! $request->is('maintenance/form')) {
                return null;
            }

            $maintenance = app(PublicFrontConfigReader::class)->group('maintenance');

            if (! (bool) ($maintenance['enabled'] ?? false)) {
                return null;
            }

            return app(MaintenancePageRenderer::class)->response(
                maintenance: $maintenance,
                formData: is_array($request->input('data')) ? $request->input('data') : [],
                formErrors: new MessageBag([
                    'form' => __('public.maintenance_form.csrf_retry'),
                ]),
                sourceUrl: $request->string('source_url')->toString() ?: url()->previous(),
            );
        };

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->render(fn (TokenMismatchException $exception, Request $request): ?SymfonyResponse => $maintenanceCsrfRetryResponse($request));

        $exceptions->respond(function (SymfonyResponse $response, Throwable $exception, Request $request) use ($maintenanceCsrfRetryResponse): SymfonyResponse {
            if ($response->getStatusCode() !== 419) {
                return $response;
            }

            return $maintenanceCsrfRetryResponse($request) ?? $response;
        });
    })->create();
