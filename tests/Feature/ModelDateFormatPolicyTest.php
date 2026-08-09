<?php

use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\SplFileInfo;

it('keeps per-model date-format escape hatches out of the codebase', function (): void {
    $violations = [];

    $files = collect(File::allFiles(app_path('Models')))
        ->filter(fn (SplFileInfo $file): bool => $file->getExtension() === 'php');

    foreach ($files as $file) {
        $source = (string) file_get_contents($file->getPathname());
        $relativePath = str($file->getPathname())->after(base_path().DIRECTORY_SEPARATOR)->toString();

        foreach (['$dateFormat', '#[DateFormat', 'dateFormat:'] as $needle) {
            if (str_contains($source, $needle)) {
                $violations[] = "{$relativePath}: {$needle} changes the stored literal format (alignment spec §10.2)";
            }
        }
    }

    expect($violations)->toBeEmpty(implode("\n", $violations));
});
