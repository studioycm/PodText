<?php

use Illuminate\Support\Facades\File;

/*
 * On-demand compiled-output sentinels for the theme scan scope — the
 * `compiled-sentinels` group, excluded from the main gate in phpunit.xml
 * (like the phase-4 RTL browser group, these run explicitly, not on every
 * `php artisan test`):
 *
 *     php artisan test --group=compiled-sentinels
 *
 * Why they exist: ThemeScanScopeTest proves every class-emitting PHP home
 * sits inside the `@source` globs at source level, but a present-yet-
 * misspelled glob (or a stale build) is invisible to that guard — only the
 * compiled CSS proves Tailwind actually read the home. These tests grep one
 * sentinel utility per theme per class home against the built assets, so
 * they REQUIRE a fresh `npm run build` before running; they are excluded
 * from the main gate because `public/build` is gitignored build output, not
 * part of the test-time source tree.
 *
 * Compiled selectors escape special characters (`.px-2\.5`,
 * `.dark\:bg-sky-950`, `.\[\&_a\]\:text-primary-700`), so probing the raw
 * source-form literal against the compiled text would miss real classes.
 * assertCompiled() strips the escaping backslashes first and then searches
 * for the source-form literal, keeping every probe readable as the exact
 * class it pins.
 *
 * Discriminating power (recorded from the pre-fix build of 2026-08-03):
 * the markdown `[&_…]` probes, `text-sky-700`, `dark:bg-sky-950` (both
 * themes) and `bg-violet-500` (public) were 0-hit before the scan-scope fix
 * — they prove the new globs are live. The icon (`h-5` +
 * `dark:border-gray-600`), card-option (`p-5`/`rounded-full`) and `px-2.5`
 * probes already compiled via other scanned sources (accidental coverage);
 * they pin today's state so the coincidence can never silently end.
 */
uses()->group('compiled-sentinels');

/**
 * @return array{admin: string, public: string}
 */
function compiledThemes(): array
{
    $manifestPath = public_path('build/manifest.json');

    expect(file_exists($manifestPath))
        ->toBeTrue('public/build/manifest.json is missing — run npm run build before the compiled-sentinels group.');

    $manifest = json_decode(File::get($manifestPath), true, 512, JSON_THROW_ON_ERROR);

    $read = function (string $entry) use ($manifest): string {
        $file = $manifest[$entry]['file'] ?? null;

        expect($file)->not->toBeNull("{$entry} is missing from the Vite manifest.");

        // Compiled class selectors escape . : [ ] & — strip the escaping so
        // probes can be written in readable source form.
        return str_replace('\\', '', File::get(public_path('build/'.$file)));
    };

    return [
        'admin' => $read('resources/css/filament/admin/theme.css'),
        'public' => $read('resources/css/filament/public/theme.css'),
    ];
}

function assertCompiled(string $css, string $theme, string $home, array $probes): void
{
    foreach ($probes as $probe) {
        expect(str_contains($css, $probe))
            ->toBeTrue("[{$probe}] ({$home}) is not in the compiled {$theme} theme — @source glob dead or build stale.");
    }
}

it('compiles the markdown prose contract into both built themes', function (): void {
    $themes = compiledThemes();

    // app/Support/Markdown/SafeMarkdownRenderer.php — the public prose and
    // transcript class contracts; the research's `_a]`/`_h1]`/`_blockquote`
    // probes, in full source form.
    foreach ($themes as $theme => $css) {
        assertCompiled($css, $theme, 'app/Support/Markdown', [
            '[&_a]:text-primary-700',
            '[&_h1]:text-3xl',
            '[&_blockquote]:border-s-2',
        ]);
    }
});

it('compiles the item-page badge maps into the built public theme', function (): void {
    // app/Support/PublicFront/ItemPage/PublicItemPageRegistry.php — badge
    // size/colour and podcast-identity maps.
    assertCompiled(compiledThemes()['public'], 'public', 'app/Support/PublicFront/ItemPage', [
        'px-2.5',
        'text-sky-700',
        'dark:bg-sky-950',
    ]);
});

it('compiles the item-page badge maps into the built admin theme', function (): void {
    assertCompiled(compiledThemes()['admin'], 'admin', 'app/Support/PublicFront/ItemPage', [
        'text-sky-700',
        'dark:bg-sky-950',
    ]);
});

it('compiles the enum colour homes into the built public theme', function (): void {
    // app/Enums — symmetric-superset ruling; bg-violet-500 exists only in
    // DashboardReason, so it proves the enum glob, not a coincidence.
    assertCompiled(compiledThemes()['public'], 'public', 'app/Enums', [
        'bg-violet-500',
    ]);
});

it('compiles the icon-picker markup into the built admin theme', function (): void {
    // app/Support/PublicFront/Icons/PublicFrontIconRegistry.php — select
    // option HTML; previously alive only through accidental coverage.
    assertCompiled(compiledThemes()['admin'], 'admin', 'app/Support/PublicFront/Icons', [
        'h-5',
        'dark:border-gray-600',
    ]);
});

it('compiles the card-option classes into the built admin theme', function (): void {
    // app/Support/PublicContent/PublicContentCardOptions.php — density and
    // radius classes for the admin card-template preview.
    assertCompiled(compiledThemes()['admin'], 'admin', 'app/Support/PublicContent', [
        'p-5',
        'rounded-full',
    ]);
});
