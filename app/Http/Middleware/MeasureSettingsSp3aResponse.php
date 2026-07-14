<?php

namespace App\Http\Middleware;

use App\Support\SettingsLifecycle\SettingsLifecycleSchema;
use Closure;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class MeasureSettingsSp3aResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! app()->environment('local') || ! $request->boolean('sp3a_measure')) {
            return $next($request);
        }

        config()->set('settings.profiling.enabled', $request->boolean('sp3a_profile'));
        $totalQueries = 0;
        $settingsReads = 0;

        DB::listen(function (QueryExecuted $query) use (&$totalQueries, &$settingsReads): void {
            $totalQueries++;

            if (str_starts_with(strtolower(ltrim($query->sql)), 'select') && str_contains(strtolower($query->sql), 'settings')) {
                $settingsReads++;
            }
        });

        $response = $next($request);
        $metrics = app(SettingsLifecycleSchema::class)->metrics();
        $content = $response->getContent();

        $response->headers->set('X-SP3A-Uncompressed-Bytes', (string) (is_string($content) ? strlen($content) : 0));
        $response->headers->set('X-SP3A-Total-Queries', (string) $totalQueries);
        $response->headers->set('X-SP3A-Settings-Reads', (string) $settingsReads);
        $response->headers->set('X-SP3A-Lifecycle-Derivations', (string) $metrics['derivations']);
        $response->headers->set('X-SP3A-Duplicate-Lifecycle-Loads', (string) $metrics['duplicate_loads']);

        return $response;
    }
}
