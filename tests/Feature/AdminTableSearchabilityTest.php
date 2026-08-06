<?php

use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

/*
|--------------------------------------------------------------------------
| Replacement for FilaCheck's table-without-searchable-columns
|--------------------------------------------------------------------------
|
| That rule is disabled in config/filacheck-pro.php because it decides with
| `preg_match('/->searchable\s*\(/', $snippet)` and so cannot see the
| `->foldedSearchable()` macro, which calls `->searchable(query: …)`
| underneath. This keeps the signal it provided and teaches it the macro.
|
*/

/** @return array<string, array<int, string>> */
function adminTableSources(): array
{
    $sources = [];

    // Datasets are built while Pest collects tests, before the application
    // container exists, so the path cannot come from app_path().
    foreach (Finder::create()->files()->in(dirname(__DIR__, 2).'/app/Filament')->name('*.php') as $file) {
        /** @var SplFileInfo $file */
        $contents = $file->getContents();

        if (! str_contains($contents, 'TextColumn::make(')) {
            continue;
        }

        $sources[$file->getRelativePathname()] = [$contents];
    }

    return $sources;
}

it('keeps at least one searchable column on every admin table that lists text', function (string $contents): void {
    expect(
        str_contains($contents, '->searchable(')
            || str_contains($contents, '->foldedSearchable('),
    )->toBeTrue('Table lists text columns but none are searchable.');
})->with(adminTableSources());

it('folds admin search on the tables filacheck would otherwise have watched', function (): void {
    // The six tables the rule flagged are exactly those whose only searchable
    // columns are folded ones. Behaviour coverage for them lives in
    // HebrewSearchFoldingAdminTest; this pins that the macro is what they use.
    $flagged = [
        'Resources/HomepageSections/Tables/HomepageSectionsTable.php',
        'Resources/SettingsBackups/Tables/SettingsBackupsTable.php',
        'Resources/ContentTags/Tables/ContentTagsTable.php',
        'Resources/Categories/Tables/CategoriesTable.php',
        'Pages/ImporterSettings.php',
        'Widgets/BlockersQueueWidget.php',
    ];

    foreach ($flagged as $path) {
        expect(file_get_contents(app_path('Filament/'.$path)))
            ->toContain('->foldedSearchable(');
    }
});
