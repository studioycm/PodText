<?php

use App\Support\UiFormats;
use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\SplFileInfo;

it('reads the date and time formats from their single configured home', function (): void {
    expect(config('localization.date_format'))->toBe('d/m/Y')
        ->and(config('localization.date_time_format'))->toBe('d/m/Y H:i')
        ->and(config('localization.time_format'))->toBe('H:i')
        ->and(UiFormats::date())->toBe('d/m/Y')
        ->and(UiFormats::dateTime())->toBe('d/m/Y H:i')
        ->and(UiFormats::time())->toBe('H:i');

    config()->set('localization.date_format', 'Y-m-d');

    expect(UiFormats::date())->toBe('Y-m-d');
});

it('formats numbers on the configured locale instead of ignoring it', function (): void {
    expect(config('localization.number_locale'))->toBe('he')
        ->and(UiFormats::number(1234567))->toBe('1,234,567')
        ->and(UiFormats::number(0))->toBe('0');

    // The reason the home exists: number_format() ignored the app locale, so
    // a locale change must actually reroute the output. German grouping
    // differs visibly from Hebrew/English, which makes the rerouting provable.
    config()->set('localization.number_locale', 'de');

    expect(UiFormats::number(1234567))->toBe('1.234.567');
});

/**
 * The anti-drift guard for the F1 localization home, and the pinned
 * format-count definition it enforces (pinned 2026-08-03, F1-pre):
 *
 * - Pattern set: the literal format strings `d/m/Y`, `d/m/Y H:i`, `H:i`, and
 *   calls to `number_format(`. The scan matches the atoms `d/m/Y`, `H:i` and
 *   `number_format(`; the composite `d/m/Y H:i` is covered by its two atoms.
 * - Scope: `app/Filament/Widgets`, `app/Support/Dashboard`, and
 *   `resources/views/filament/widgets` — the dashboard paths only. At pin
 *   time these held 7 date-format occurrences + 5 `number_format(` calls
 *   = 12 sites, all routed through `App\Support\UiFormats` by F2.
 * - App-wide occurrences (63 `d/m/Y` + 7 `number_format(` grep lines across
 *   `app` and `resources`) are deliberately parked separate work. Widening
 *   this guard's scope is that parked sweep's job, not a quick edit here.
 * - Allow-list: the home itself (`app/Support/UiFormats.php`) and its value
 *   store (`config/localization.php`) both live outside the scanned paths,
 *   the same way the UI-timezone guard's home does. Enum-internal values of
 *   any kind (colours, labels) are out of scope: this guard is about formats.
 *
 * The scan is statement-level, not line-level: file contents are
 * whitespace-collapsed before matching, so a call site split across lines
 * (`number_format (`, `->format(\n'd/m/Y'\n)`) cannot slip past the way a
 * line-anchored grep would let it.
 */
it('keeps date and number format literals out of dashboard code', function (): void {
    $patterns = ['d/m/Y', 'H:i', 'number_format('];

    $offenders = collect([
        app_path('Filament/Widgets'),
        app_path('Support/Dashboard'),
        resource_path('views/filament/widgets'),
    ])
        ->flatMap(fn (string $directory): array => File::allFiles($directory))
        ->flatMap(function (SplFileInfo $file) use ($patterns): array {
            $statement = (string) preg_replace('/\s+/', '', (string) file_get_contents($file->getPathname()));

            return collect($patterns)
                ->filter(fn (string $pattern): bool => str_contains($statement, $pattern))
                ->map(fn (string $pattern): string => str($file->getPathname())
                    ->after(base_path().DIRECTORY_SEPARATOR)
                    ->append(' → ', $pattern)
                    ->toString())
                ->all();
        })
        ->values()
        ->all();

    expect($offenders)->toBe([]);
});
