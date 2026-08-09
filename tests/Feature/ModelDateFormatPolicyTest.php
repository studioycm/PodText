<?php

it('keeps per-model date-format escape hatches out of the codebase', function (): void {
    $violations = [];

    foreach (glob(app_path('Models/*.php')) ?: [] as $file) {
        $source = (string) file_get_contents($file);

        foreach (['$dateFormat', '#[DateFormat', 'dateFormat:'] as $needle) {
            if (str_contains($source, $needle)) {
                $violations[] = basename($file).": {$needle} changes the stored literal format (alignment spec §10.2)";
            }
        }
    }

    expect($violations)->toBeEmpty(implode("\n", $violations));
});
