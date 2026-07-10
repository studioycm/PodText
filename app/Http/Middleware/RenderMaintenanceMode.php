<?php

namespace App\Http\Middleware;

use App\Support\PublicFront\PublicFrontConfigReader;
use Closure;
use Filament\Facades\Filament;
use Filament\Models\Contracts\FilamentUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RenderMaintenanceMode
{
    public function __construct(
        private readonly PublicFrontConfigReader $configReader,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $maintenance = $this->configReader->group('maintenance');

        if (! (bool) ($maintenance['enabled'] ?? false)) {
            return $next($request);
        }

        if ($this->adminCanBypass()) {
            return $next($request);
        }

        $retryAfter = max(1, (int) ($maintenance['retry_after_hours'] ?? 24)) * 3600;

        return response()
            ->view('public.maintenance', [
                'maintenance' => $maintenance,
                'retryAfter' => $retryAfter,
            ], 503)
            ->header('Retry-After', (string) $retryAfter);
    }

    private function adminCanBypass(): bool
    {
        $adminPanel = Filament::getPanel('admin', isStrict: false);

        if ($adminPanel === null) {
            return false;
        }

        $user = Auth::guard($adminPanel->getAuthGuard())->user();

        return $user instanceof FilamentUser && $user->canAccessPanel($adminPanel);
    }
}
