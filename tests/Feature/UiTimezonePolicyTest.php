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
    $offenders = collect(['app', 'resources'])
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
