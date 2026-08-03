<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Finder\SplFileInfo;

/*
 * Guard for the `unscanned-home` defect pattern and the `one-home-compiled`
 * widget principle: both Filament themes disable Tailwind's automatic source
 * detection (`source(none)` in the vendor base), so a class literal compiles
 * only when its file sits inside that theme's `@source` globs. A PHP class
 * home born outside the globs renders as no styling at all — routing every
 * call site through the home protects nothing the compiler doesn't also read.
 *
 * The test discovers class-emitting PHP under app/ with FilaCheck-style
 * Tailwind token regexes (borrowed from FilacheckPro\Rules\CustomThemeNeededRule
 * and adapted to the literal shapes this app emits) and asserts every emitter
 * is covered by the scan scope of BOTH themes, unless a named exception row
 * says which panel provably never renders it, and why. The theme CSS files are
 * the single source of truth for the scopes, so glob edits are picked up
 * automatically; scanning one panel too many costs kilobytes, scanning one too
 * few costs production styling.
 *
 * A green run does not prove the compiled output (a present-but-misspelled
 * glob is invisible at source level) — that residual risk is covered by the
 * on-demand `compiled-sentinels` group in CompiledThemeSentinelTest.php.
 * The historical regression pin for the first fixed instance lives in
 * DashboardEnumsTest ("scans the enum colour homes into the admin theme").
 */
it('covers every class-emitting php home with the @source scan scope of both themes', function (): void {
    $scopeOf = function (string $themeCss): array {
        $prefixes = collect(explode("\n", File::get(resource_path($themeCss))))
            ->map(fn (string $line): string => trim($line))
            ->filter(fn (string $line): bool => str_starts_with($line, '@source'))
            ->map(fn (string $line): string => (string) Str::of($line)
                ->after("'")
                ->before("'")
                ->replaceStart('../../../../', '')
                ->before('**'))
            ->values();

        // A degenerate parse must fail loudly: an empty scope, or an empty
        // prefix that would make the startsWith() coverage check match every
        // file, would otherwise turn the whole guard into a silent pass.
        expect($prefixes->all())->not->toBeEmpty();
        $prefixes->each(fn (string $prefix) => expect($prefix)->toMatch('#^(?:app|resources|vendor)/[\w./-]*$#'));

        return $prefixes->all();
    };

    $adminScope = $scopeOf('css/filament/admin/theme.css');
    $publicScope = $scopeOf('css/filament/public/theme.css');

    $palette = 'slate|gray|zinc|neutral|stone|red|orange|amber|yellow|lime|green|emerald|teal|cyan|sky|blue|indigo|violet|purple|fuchsia|pink|rose';
    $semantic = 'primary|secondary|success|danger|warning|info';

    $tokenPatterns = [
        // Colour utilities with a palette or panel-semantic family and a
        // numeric shade: bg-gray-400, stroke-success-500, text-sky-700.
        "/\\b(?:bg|text|border|ring|stroke|fill|shadow|accent|outline|decoration|divide|from|via|to)-(?:{$palette}|{$semantic})-\\d{2,3}\\b/",
        // Numeric spacing, including logical properties and half steps:
        // p-5, gap-1.5, px-2.5, ms-5, space-y-4.
        '/\b(?:p|px|py|ps|pe|pt|pb|m|mx|my|ms|me|mt|mb|gap|gap-x|gap-y|space-x|space-y)-\d+(?:\.\d+)?\b/',
        // Variant-prefixed utilities: dark:bg-sky-950, hover:text-sky-900, md:flex.
        '/\b(?:sm|md|lg|xl|2xl|dark|hover|focus|focus-visible|group-hover):[\w-]+/',
        // Arbitrary selector variants owning descendant styling: [&_h1]:text-3xl.
        '/\[&[^\]]*\]:/',
        // Arbitrary values behind a known utility root, so PHP regex strings
        // like intl-[a-z]{2} in URL parsers never match:
        // text-[var(--podcast-identity-color)], w-[200px].
        '/\b(?:bg|text|border|ring|stroke|fill|shadow|outline|w|h|size|min-w|min-h|max-w|max-h|p|px|py|m|mx|my|gap|inset|top|start|end|rounded|font|leading|tracking|grid-cols|basis)-\[[^\]\s]+\]/',
        // Numeric and keyword sizing: h-5, w-5, h-24, w-full, size-6.
        '/\b(?:w|h|size)-(?:\d+|full|screen|min|max|fit|px)\b/',
        // Layout, radius and typography keywords common in PHP-built markup.
        '/\b(?:inline-flex|items-center|justify-center|justify-between|line-clamp-\d|rounded-(?:none|sm|md|lg|xl|2xl|3xl|full)|text-(?:xs|sm|base|lg|xl|[2-9]xl)|truncate)\b/',
    ];

    $emitters = collect(File::allFiles(app_path()))
        ->filter(fn (SplFileInfo $file): bool => $file->getExtension() === 'php')
        ->filter(function (SplFileInfo $file) use ($tokenPatterns): bool {
            $contents = $file->getContents();

            foreach ($tokenPatterns as $pattern) {
                if (preg_match($pattern, $contents) === 1) {
                    return true;
                }
            }

            return false;
        })
        ->map(fn (SplFileInfo $file): string => 'app/'.str_replace(DIRECTORY_SEPARATOR, '/', $file->getRelativePathname()))
        ->sort()
        ->values();

    // Anchor the detector against permanent class homes so a broken regex set
    // (discovering nothing) cannot degrade the guard into a vacuous pass.
    expect($emitters->all())
        ->toContain('app/Support/Markdown/SafeMarkdownRenderer.php')
        ->toContain('app/Support/PublicFront/ItemPage/PublicItemPageRegistry.php')
        ->toContain('app/Enums/FunnelStage.php');

    // Panel exceptions: each row names the panel that provably never renders
    // the home, with the reason. Keep this map minimal — coverage by glob is
    // the default, an exception is a documented ruling.
    $exceptions = [
        'app/Filament/' => [
            'public' => 'admin-panel resources, pages and widgets; the public panel renders only the app/Filament/Public subtree, which the public theme globs directly',
        ],
        'app/Livewire/' => [
            'public' => 'admin Livewire components; the public panel renders only the app/Livewire/Public subtree, which the public theme globs directly',
        ],
        'app/Support/PublicFront/Icons/' => [
            'public' => 'admin-only icon-picker option markup (IconSelect option HTML in settings schemas); no public surface renders these strings',
        ],
    ];

    $isExcepted = fn (string $file, string $panel): bool => collect($exceptions)
        ->contains(fn (array $panels, string $prefix): bool => str_starts_with($file, $prefix) && array_key_exists($panel, $panels));

    $violations = [];

    foreach ($emitters as $file) {
        foreach (['admin' => $adminScope, 'public' => $publicScope] as $panel => $scope) {
            if (Str::startsWith($file, $scope) || $isExcepted($file, $panel)) {
                continue;
            }

            $violations[] = "{$file} is outside the {$panel} theme's @source scan scope";
        }
    }

    expect($violations)->toBe([]);

    // Every exception row must still be earning its place: it has to absorb at
    // least one discovered emitter the globs do not cover. A row that stopped
    // matching anything is stale documentation and must be removed.
    $staleExceptions = collect($exceptions)
        ->flatMap(fn (array $panels, string $prefix): array => collect($panels)
            ->keys()
            ->map(fn (string $panel): array => [$prefix, $panel])
            ->all())
        ->reject(function (array $row) use ($emitters, $adminScope, $publicScope): bool {
            [$prefix, $panel] = $row;
            $scope = $panel === 'admin' ? $adminScope : $publicScope;

            return $emitters->contains(
                fn (string $file): bool => str_starts_with($file, $prefix) && ! Str::startsWith($file, $scope),
            );
        })
        ->map(fn (array $row): string => "unused exception row: {$row[0]} ({$row[1]})")
        ->values()
        ->all();

    expect($staleExceptions)->toBe([]);
});
