<?php

use App\Support\UiTimezone;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\SplFileInfo;

it('reads the UI timezone from its single configured home', function (): void {
    expect(config('localization.ui_timezone'))->toBe('Asia/Jerusalem')
        ->and(UiTimezone::name())->toBe('Asia/Jerusalem');

    config()->set('localization.ui_timezone', 'Europe/Berlin');

    expect(UiTimezone::name())->toBe('Europe/Berlin');
});

it('formats a stored UTC instant on the configured UI timezone', function (): void {
    $stored = Carbon::parse('2026-07-30 22:30:00', 'UTC');

    expect($stored->copy()->timezone(UiTimezone::name())->format('d/m/Y H:i'))
        ->toBe('31/07/2026 01:30');
});

it('keeps the UI timezone literal out of application and view code', function (): void {
    $offenders = collect(['app', 'resources', 'routes'])
        ->flatMap(fn (string $directory): array => File::allFiles(base_path($directory)))
        ->filter(fn (SplFileInfo $file): bool => str_contains(
            (string) file_get_contents($file->getPathname()),
            'Asia/Jerusalem',
        ))
        ->map(fn (SplFileInfo $file): string => str($file->getPathname())
            ->after(base_path().DIRECTORY_SEPARATOR)
            ->toString())
        ->values()
        ->all();

    expect($offenders)->toBe([]);
});

it('keeps per-site timezone/format chains out of the Filament admin surface', function (): void {
    // Measured empty at introduction (Task 26, 2026-08-09): a grep sweep of
    // app/Filament/ for `->timezone(` and `->displayFormat(` returned zero
    // hits — Task 22 already stripped every such chain in favor of the
    // global Filament hooks installed in AppServiceProvider::boot(). Kept as
    // an explicit array, not omitted, so the on-disk assertion below still
    // runs and a future exception has somewhere to go.
    //
    // The families this ban conceptually exempts (input-parsing/domain-logic
    // Carbon chains such as JerusalemDailySeries.php and
    // EditorialMetrics.php, plus AppServiceProvider.php where the global
    // defaults themselves are declared) all live OUTSIDE app/Filament/, so
    // none of them need an entry here. Do not widen this scan to app/
    // generally to "cover" them — that would re-flag those files' already
    // reviewed, legitimate Carbon chains.
    $allowlist = [
        //
    ];

    collect($allowlist)->each(function (string $relativePath): void {
        expect(File::exists(base_path($relativePath)))->toBeTrue(
            "Allowlisted file no longer exists on disk: {$relativePath}. Update the UiTimezonePolicyTest allowlist.",
        );
    });

    $offenders = collect(File::allFiles(base_path('app/Filament')))
        ->filter(fn (SplFileInfo $file): bool => str((string) file_get_contents($file->getPathname()))
            ->contains(['->timezone(', '->displayFormat(']))
        ->map(fn (SplFileInfo $file): string => str($file->getPathname())
            ->after(base_path().DIRECTORY_SEPARATOR)
            ->toString())
        ->reject(fn (string $relativePath): bool => in_array($relativePath, $allowlist, true))
        ->values()
        ->all();

    expect($offenders)->toBe([]);
});
